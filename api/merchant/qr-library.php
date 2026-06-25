<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_qr_user_can(array $user, string $permission): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (array_intersect($roles, ['merchant', 'admin', 'super_admin'])) return true;
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return in_array($permission, $permissions, true) || in_array('merchant.manage', $permissions, true);
}

function mg_qr_require(array $user, string $permission): void
{
    if (!mg_qr_user_can($user, $permission)) mg_fail('Permission denied.', 403);
}

function mg_qr_kind_label(string $type): string
{
    return [
        'claim' => 'Claim QR',
        'lead' => 'Lead QR',
        'campaign' => 'Campaign QR',
        'storefront' => 'Storefront QR',
        'product' => 'Product QR',
        'custom' => 'Custom QR',
    ][$type] ?? 'QR Code';
}

function mg_qr_clean_text(mixed $value, string $fallback, int $max): string
{
    $text = trim((string) $value);
    if ($text === '') $text = $fallback;
    $text = preg_replace('/\s+/', ' ', $text) ?: $fallback;
    return mb_substr($text, 0, $max);
}

function mg_qr_allowed_hosts(): array
{
    $hosts = [];
    $current = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $current = preg_replace('/:\d+$/', '', $current) ?: '';
    if ($current !== '') $hosts[] = $current;
    $configured = trim((string) getenv('MG_QR_ALLOWED_EXTERNAL_HOSTS'));
    if ($configured !== '') {
        foreach (preg_split('/[,\s]+/', $configured) ?: [] as $host) {
            $host = strtolower(trim($host));
            $host = preg_replace('/^https?:\/\//', '', $host) ?: $host;
            $host = preg_replace('/\/.*$/', '', $host) ?: $host;
            $host = preg_replace('/:\d+$/', '', $host) ?: $host;
            if ($host !== '') $hosts[] = $host;
        }
    }
    return array_values(array_unique($hosts));
}

function mg_qr_host_allowed(string $host): bool
{
    $host = strtolower(trim($host));
    foreach (mg_qr_allowed_hosts() as $allowed) {
        if ($host === $allowed) return true;
        if (str_starts_with($allowed, '*.')) {
            $suffix = substr($allowed, 1);
            if (str_ends_with($host, $suffix)) return true;
        }
    }
    return false;
}

function mg_qr_public_host(string $host): bool
{
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}

function mg_qr_destination(mixed $value): string
{
    $url = trim((string) $value);
    if ($url === '') mg_fail('QR destination URL is required.', 422);
    if (mb_strlen($url) > 1000) mg_fail('QR destination URL is too long.', 422);
    if (preg_match('/[\r\n]/', $url)) mg_fail('Invalid QR destination URL.', 422);
    if (str_starts_with($url, '//')) mg_fail('Protocol-relative QR destinations are not allowed.', 422);
    if (str_starts_with($url, '/')) return $url;
    if (!filter_var($url, FILTER_VALIDATE_URL)) mg_fail('Invalid QR destination URL.', 422);
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) mg_fail('Invalid QR destination URL.', 422);
    $scheme = strtolower((string) $parts['scheme']);
    $host = strtolower((string) $parts['host']);
    if ($scheme !== 'https') mg_fail('External QR destinations must use https.', 422);
    if (!empty($parts['user']) || !empty($parts['pass'])) mg_fail('QR destination cannot include credentials.', 422);
    $port = isset($parts['port']) ? (int) $parts['port'] : 443;
    if ($port !== 443) mg_fail('External QR destinations must use the standard https port.', 422);
    if (!mg_qr_host_allowed($host)) mg_fail('External QR destination host is not allowed.', 422);
    if (!mg_qr_public_host($host)) mg_fail('External QR destination host is not public.', 422);
    return $url;
}

function mg_qr_status(mixed $value): string
{
    $status = strtolower(trim((string) $value));
    return in_array($status, ['draft', 'active', 'paused', 'archived'], true) ? $status : 'draft';
}

function mg_qr_type(mixed $value): string
{
    $type = strtolower(trim((string) $value));
    return in_array($type, ['claim', 'lead', 'campaign', 'storefront', 'product', 'custom'], true) ? $type : 'claim';
}

function mg_qr_metadata(mixed $value): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) mg_fail('QR metadata must be an object.', 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > 65535) mg_fail('QR metadata is too large.', 422);
    return $json;
}

function mg_qr_payload_url(string $shortCode): string
{
    return '/qr.php?c=' . rawurlencode($shortCode);
}

function mg_qr_short_code(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = substr(str_replace('-', '', mg_merchant_uuid()), 0, 12);
        $stmt = $pdo->prepare('SELECT id FROM merchant_qr_codes WHERE short_code = ? LIMIT 1');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) return $code;
    }
    do {
        $code = bin2hex(random_bytes(8));
        $stmt = $pdo->prepare('SELECT id FROM merchant_qr_codes WHERE short_code = ? LIMIT 1');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

function mg_qr_format(array $row): array
{
    $type = (string) ($row['qr_type'] ?? 'claim');
    $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
    return [
        'id' => (string) $row['public_id'],
        'label' => (string) $row['label'],
        'qr_type' => $type,
        'kind_label' => mg_qr_kind_label($type),
        'status' => (string) $row['status'],
        'short_code' => (string) $row['short_code'],
        'destination_url' => (string) $row['destination_url'],
        'qr_payload_url' => (string) $row['qr_payload_url'],
        'campaign_ref' => $row['campaign_ref'] ?? null,
        'product_ref' => $row['product_ref'] ?? null,
        'scan_count' => (int) ($row['scan_count'] ?? 0),
        'last_scanned_at' => $row['last_scanned_at'] ?? null,
        'metadata' => is_array($metadata) ? $metadata : [],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$user = mg_require_api_user();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    mg_qr_require($user, 'merchant.qr_library.view');
    $status = strtolower(trim((string) ($_GET['status'] ?? 'open')));
    $type = strtolower(trim((string) ($_GET['type'] ?? '')));
    $params = [(int) $workspace['id']];
    $where = 'workspace_id = ?';
    if ($status === 'open') {
        $where .= " AND status <> 'archived'";
    } elseif (in_array($status, ['draft', 'active', 'paused', 'archived'], true)) {
        $where .= ' AND status = ?';
        $params[] = $status;
    }
    if (in_array($type, ['claim', 'lead', 'campaign', 'storefront', 'product', 'custom'], true)) {
        $where .= ' AND qr_type = ?';
        $params[] = $type;
    }
    $stmt = $pdo->prepare("SELECT * FROM merchant_qr_codes WHERE {$where} ORDER BY FIELD(status,'active','draft','paused','archived'), updated_at DESC LIMIT 100");
    $stmt->execute($params);
    mg_ok(['items' => array_map('mg_qr_format', $stmt->fetchAll()), 'workspace_id' => (string) $workspace['public_id']]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);

$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string) ($input['action'] ?? 'create')));

if ($action === 'archive') {
    mg_qr_require($user, 'merchant.qr_library.manage');
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') mg_fail('QR code is required.', 422);
    $stmt = $pdo->prepare("UPDATE merchant_qr_codes SET status='archived', archived_at=NOW(), updated_by_user_id=?, updated_at=NOW() WHERE public_id=? AND workspace_id=? AND status <> 'archived'");
    $stmt->execute([(int) $user['id'], $id, (int) $workspace['id']]);
    if ($stmt->rowCount() < 1) mg_fail('QR code not found.', 404);
    mg_audit('merchant.qr_archived', 'merchant_qr_code', ['qr_id' => $id], (int) $user['id']);
    mg_ok(['id' => $id], 'QR code archived.');
}

mg_qr_require($user, 'merchant.qr_library.manage');
$label = mg_qr_clean_text($input['label'] ?? null, 'Untitled QR code', 180);
$type = mg_qr_type($input['qr_type'] ?? 'claim');
$status = mg_qr_status($input['status'] ?? 'draft');
$destination = mg_qr_destination($input['destination_url'] ?? null);
$campaignRef = mg_qr_clean_text($input['campaign_ref'] ?? '', '', 160) ?: null;
$productRef = mg_qr_clean_text($input['product_ref'] ?? '', '', 160) ?: null;
$metadataJson = mg_qr_metadata($input['metadata'] ?? null);

if ($action === 'update') {
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') mg_fail('QR code is required.', 422);
    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare('SELECT * FROM merchant_qr_codes WHERE public_id=? AND workspace_id=? LIMIT 1 FOR UPDATE');
        $lookup->execute([$id, (int) $workspace['id']]);
        $existing = $lookup->fetch();
        if (!$existing) mg_fail('QR code not found.', 404);
        $pdo->prepare('UPDATE merchant_qr_codes SET label=?, qr_type=?, status=?, destination_url=?, campaign_ref=?, product_ref=?, metadata_json=?, updated_by_user_id=?, updated_at=NOW(), archived_at=IF(?="archived",COALESCE(archived_at,NOW()),NULL) WHERE id=?')
            ->execute([$label, $type, $status, $destination, $campaignRef, $productRef, $metadataJson, (int) $user['id'], $status, (int) $existing['id']]);
        $fresh = $pdo->prepare('SELECT * FROM merchant_qr_codes WHERE id=? LIMIT 1');
        $fresh->execute([(int) $existing['id']]);
        $row = $fresh->fetch();
        $pdo->commit();
        mg_audit('merchant.qr_updated', 'merchant_qr_code', ['qr_id' => $id], (int) $user['id']);
        mg_ok(['item' => mg_qr_format($row)], 'QR code updated.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'merchant.qr_update_failed', 'QR update failed.', ['exception_type' => get_class($e)], (int) $user['id']);
        mg_fail('Unable to update QR code.', 500);
    }
}

if (!in_array($action, ['create', 'save'], true)) mg_fail('Unsupported QR library action.', 422);

$publicId = mg_merchant_uuid();
$shortCode = mg_qr_short_code($pdo);
$payloadUrl = mg_qr_payload_url($shortCode);
$pdo->prepare('INSERT INTO merchant_qr_codes (public_id,workspace_id,merchant_user_id,label,qr_type,status,short_code,destination_url,qr_payload_url,campaign_ref,product_ref,metadata_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
    ->execute([$publicId, (int) $workspace['id'], (int) $user['id'], $label, $type, $status, $shortCode, $destination, $payloadUrl, $campaignRef, $productRef, $metadataJson, (int) $user['id']]);
$stmt = $pdo->prepare('SELECT * FROM merchant_qr_codes WHERE public_id=? LIMIT 1');
$stmt->execute([$publicId]);
$row = $stmt->fetch();
mg_audit('merchant.qr_created', 'merchant_qr_code', ['qr_id' => $publicId, 'qr_type' => $type], (int) $user['id']);
mg_ok(['item' => mg_qr_format($row)], 'QR code created.', 201);
