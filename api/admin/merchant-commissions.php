<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_commissions.php';

$user = mg_require_permission('admin.payments.commissions.manage');
$userId = (int)$user['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function mg_admin_commission_text(mixed $value, int $max = 190): string
{
    $value = trim((string)$value);
    if (mb_strlen($value) > $max) throw new InvalidArgumentException('Commission input is too long.');
    return $value;
}

function mg_admin_commission_merchant_rows(PDO $pdo, string $query, int $limit = 40): array
{
    $limit = max(1, min(100, $limit));
    $where = ["u.status='active'", "(ms.id IS NOT NULL OR ppa.id IS NOT NULL OR r.slug='merchant')"];
    $params = [];
    if ($query !== '') {
        $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)) . '%';
        $where[] = "(LOWER(u.email) LIKE ? ESCAPE '!' OR LOWER(COALESCE(u.display_name,u.full_name,'')) LIKE ? ESCAPE '!' OR LOWER(COALESCE(ms.display_name,'')) LIKE ? ESCAPE '!')";
        array_push($params, $needle, $needle, $needle);
    }
    $sql = "SELECT u.id,u.email,COALESCE(ms.display_name,u.display_name,u.full_name,u.email) merchant_name,
                   ms.public_id storefront_id,ppa.status stripe_status,
                   GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ',') role_slugs
            FROM users u
            LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
            LEFT JOIN payment_provider_accounts ppa ON ppa.merchant_user_id=u.id AND ppa.provider_key='stripe' AND ppa.mode=?
            LEFT JOIN user_roles ur ON ur.user_id=u.id
            LEFT JOIN roles r ON r.id=ur.role_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY u.id,u.email,merchant_name,ms.public_id,ppa.status
            ORDER BY merchant_name,u.id
            LIMIT {$limit}";
    array_unshift($params, mg_payment_mode());
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rate = mg_commission_resolve_merchant_rate($pdo, (int)$row['id'], ['initialize' => false]);
        $rows[] = [
            'merchant_user_id' => (int)$row['id'],
            'merchant_name' => (string)$row['merchant_name'],
            'email' => (string)$row['email'],
            'storefront_id' => $row['storefront_id'] ?: null,
            'stripe_status' => $row['stripe_status'] ?: null,
            'roles' => array_values(array_filter(explode(',', (string)($row['role_slugs'] ?? '')))),
            'commission_rate_bps' => (int)$rate['commission_rate_bps'],
            'commission_rate_percent' => (int)$rate['commission_rate_bps'] / 100,
            'rate_mode' => (string)$rate['rate_mode'],
            'rate_source' => (string)$rate['rate_source'],
            'profile_id' => $rate['profile_public_id'] ?? null,
            'effective_from' => $rate['effective_from'] ?? null,
            'effective_until' => $rate['effective_until'] ?? null,
        ];
    }
    return $rows;
}

function mg_admin_commission_platform_payload(PDO $pdo): array
{
    $settings = mg_commission_platform_settings($pdo);
    return [
        'settings_id' => (string)$settings['public_id'],
        'starting_commission_bps' => (int)$settings['starting_commission_bps'],
        'starting_commission_percent' => (int)$settings['starting_commission_bps'] / 100,
        'rule_version' => (string)$settings['rule_version'],
        'updated_at' => $settings['updated_at'],
    ];
}

if ($method === 'GET') {
    mg_rate_limit('admin.commissions.read', 'user:' . $userId, 120, 60);
    mg_commission_require_schema($pdo);
    $query = mg_admin_commission_text($_GET['q'] ?? '', 120);
    $merchantId = max(0, (int)($_GET['merchant_user_id'] ?? 0));
    $bundleReference = mg_admin_commission_text($_GET['bundle_reference'] ?? '', 190);
    $payload = [
        'platform' => mg_admin_commission_platform_payload($pdo),
        'merchant_rate_modes' => mg_commission_profile_modes(),
        'bundle_rate_modes' => ['merchant_default', 'bundle_starting_rate', 'custom_participant_rates'],
        'merchants' => mg_admin_commission_merchant_rows($pdo, $query),
        'rule_version' => MG_COMMISSION_RULE_VERSION,
    ];
    if ($merchantId > 0) {
        $payload['merchant'] = mg_commission_public_profile($pdo, $merchantId, false);
        $history = $pdo->prepare("SELECT h.*,u.email changed_by_email FROM merchant_commission_history h LEFT JOIN users u ON u.id=h.changed_by_user_id WHERE h.merchant_user_id=? ORDER BY h.id DESC LIMIT 30");
        $history->execute([$merchantId]);
        $payload['merchant_history'] = $history->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($bundleReference !== '') {
        $stmt = $pdo->prepare('SELECT * FROM bundle_commission_profiles WHERE bundle_reference=? ORDER BY version_number DESC,id DESC LIMIT 20');
        $stmt->execute([$bundleReference]);
        $payload['bundle_profiles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($payload);
}

mg_require_method('POST');
mg_rate_limit('admin.commissions.write', 'user:' . $userId, 40, 300);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = mg_admin_commission_text($input['action'] ?? '', 80);
$confirmation = mg_admin_commission_text($input['confirmation'] ?? '', 80);
if (!hash_equals('CONFIRM COMMISSION CHANGE', $confirmation)) {
    mg_fail('Type CONFIRM COMMISSION CHANGE to save commission terms.', 422);
}

try {
    $pdo->beginTransaction();
    if ($action === 'update_platform_starting_rate') {
        $rate = mg_commission_normalize_bps($input['starting_commission_bps'] ?? null, 'Platform starting commission');
        $reason = mg_admin_commission_text($input['reason'] ?? '', 500);
        $result = mg_commission_update_platform_starting_rate($pdo, $rate, $userId, $reason);
        mg_audit('admin.commission.platform_updated', 'commission_platform_settings', ['starting_commission_bps' => $rate, 'reason' => $reason], $userId);
        $response = ['platform' => mg_admin_commission_platform_payload($pdo), 'changed' => !empty($result['changed'])];
        $message = 'Platform starting commission saved.';
    } elseif ($action === 'save_merchant_profile') {
        $merchantId = (int)($input['merchant_user_id'] ?? 0);
        $profile = mg_commission_save_merchant_profile($pdo, $merchantId, $input, $userId);
        mg_audit('admin.commission.merchant_profile_saved', 'merchant_commission_profile', [
            'merchant_user_id' => $merchantId,'profile_id' => (string)$profile['public_id'],'rate_mode' => (string)$profile['rate_mode'],
            'commission_rate_bps' => $profile['default_commission_bps'] === null ? null : (int)$profile['default_commission_bps'],
            'effective_from' => $profile['effective_from'],'effective_until' => $profile['effective_until'],
        ], $userId);
        $response = ['merchant' => mg_commission_public_profile($pdo, $merchantId, false), 'profile' => $profile];
        $message = 'Merchant commission terms saved.';
    } elseif ($action === 'save_bundle_profile') {
        $bundleReference = mg_admin_commission_text($input['bundle_reference'] ?? '', 190);
        $profile = mg_commission_save_bundle_profile($pdo, $bundleReference, $input, $userId);
        mg_audit('admin.commission.bundle_profile_saved', 'bundle_commission_profile', [
            'bundle_reference' => $bundleReference,'profile_id' => (string)$profile['public_id'],
            'commission_mode' => (string)$profile['commission_mode'],
            'starting_commission_bps' => $profile['starting_commission_bps'] === null ? null : (int)$profile['starting_commission_bps'],
        ], $userId);
        $response = ['bundle_profile' => $profile];
        $message = 'Bundle commission profile saved.';
    } elseif ($action === 'save_bundle_participant_terms') {
        $bundleProfileId = (int)($input['bundle_profile_id'] ?? 0);
        $merchantId = (int)($input['merchant_user_id'] ?? 0);
        $terms = mg_commission_save_bundle_participant_terms($pdo, $bundleProfileId, $merchantId, $input, $userId);
        mg_audit('admin.commission.bundle_participant_terms_saved', 'bundle_commission_participant_terms', [
            'bundle_profile_id' => $bundleProfileId,'merchant_user_id' => $merchantId,'terms_id' => (string)$terms['public_id'],
            'proposed_commission_bps' => (int)$terms['proposed_commission_bps'],
            'accepted_commission_bps' => $terms['accepted_commission_bps'] === null ? null : (int)$terms['accepted_commission_bps'],
            'terms_status' => (string)$terms['terms_status'],
        ], $userId);
        $response = ['bundle_participant_terms' => $terms];
        $message = 'Bundle participant commission terms saved.';
    } else {
        throw new InvalidArgumentException('Invalid commission action.');
    }
    $pdo->commit();
    mg_ok($response, $message);
} catch (InvalidArgumentException|MgCommissionException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), $error instanceof MgCommissionException ? $error->httpStatus : 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'admin.commission.write_failed', 'Commission settings update failed.', ['action' => $action, 'exception_type' => get_class($error)], $userId);
    mg_fail('Unable to save commission settings.', 500);
}
