<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-branding.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

function mg_pwa_admin_user_can_view(array $user): bool
{
    return (function_exists('mg_api_user_has_permission') && (
        mg_api_user_has_permission($user, 'admin.pwa_branding.view') ||
        mg_api_user_has_permission($user, 'admin.pwa_branding.manage') ||
        mg_api_user_has_permission($user, 'admin.settings.manage')
    )) || in_array('super_admin', (array)($user['roles'] ?? []), true);
}

function mg_pwa_admin_user_can_manage(array $user): bool
{
    return (function_exists('mg_api_user_has_permission') && (
        mg_api_user_has_permission($user, 'admin.pwa_branding.manage') ||
        mg_api_user_has_permission($user, 'admin.settings.manage')
    )) || in_array('super_admin', (array)($user['roles'] ?? []), true);
}

function mg_pwa_admin_user_can_test(array $user): bool
{
    return (function_exists('mg_api_user_has_permission') && (
        mg_api_user_has_permission($user, 'admin.pwa_notifications.test') ||
        mg_api_user_has_permission($user, 'admin.pwa_branding.manage') ||
        mg_api_user_has_permission($user, 'admin.settings.manage')
    )) || in_array('super_admin', (array)($user['roles'] ?? []), true);
}

function mg_pwa_admin_vapid_status(): array
{
    $config = function_exists('mg_pwa_push_config') ? mg_pwa_push_config() : [];
    $public = trim((string)($config['public_key'] ?? ''));
    $private = trim((string)($config['private_key'] ?? ''));
    $subject = trim((string)($config['subject'] ?? 'mailto:admin@microgifter.com'));
    $providerAvailable = (bool)($config['provider_available'] ?? false);
    $generatorAvailable = class_exists('\\Minishlink\\WebPush\\VAPID') && method_exists('\\Minishlink\\WebPush\\VAPID', 'createVapidKeys');

    return [
        'enabled' => (bool)($config['enabled'] ?? true),
        'public_key_configured' => $public !== '',
        'private_key_configured' => $private !== '',
        'subject_configured' => $subject !== '',
        'subject' => $subject !== '' ? $subject : 'mailto:admin@microgifter.com',
        'public_key_preview' => $public !== '' ? substr($public, 0, 12) . '…' . substr($public, -8) : '',
        'provider_available' => $providerAvailable,
        'generator_available' => $generatorAvailable,
        'env_names' => [
            'MG_ENABLE_PWA_PUSH',
            'MG_PWA_VAPID_PUBLIC_KEY',
            'MG_PWA_VAPID_PRIVATE_KEY',
            'MG_PWA_VAPID_SUBJECT',
        ],
    ];
}

function mg_pwa_admin_generate_vapid_keys(): array
{
    if (!class_exists('\\Minishlink\\WebPush\\VAPID') || !method_exists('\\Minishlink\\WebPush\\VAPID', 'createVapidKeys')) {
        mg_fail('The WebPush VAPID generator is not available. Install or load the Minishlink WebPush library, then try again.', 500);
    }

    $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
    $public = trim((string)($keys['publicKey'] ?? ''));
    $private = trim((string)($keys['privateKey'] ?? ''));
    if ($public === '' || $private === '') {
        mg_fail('Unable to generate a complete VAPID key pair.', 500);
    }

    $subject = trim((string)(function_exists('mg_config_value') ? mg_config_value('pwa_push', 'vapid_subject', '') : getenv('MG_PWA_VAPID_SUBJECT')));
    if ($subject === '') $subject = 'mailto:admin@microgifter.com';

    return [
        'public_key' => $public,
        'private_key' => $private,
        'subject' => $subject,
        'env_block' => "MG_ENABLE_PWA_PUSH=true\nMG_PWA_VAPID_PUBLIC_KEY={$public}\nMG_PWA_VAPID_PRIVATE_KEY={$private}\nMG_PWA_VAPID_SUBJECT={$subject}",
        'warning' => 'Copy this now into server environment config. The private key is not stored and will not be shown again.',
    ];
}

function mg_pwa_admin_table_exists_safe(PDO $pdo, string $table): bool
{
    try {
        return function_exists('mg_pwa_push_table_exists') ? mg_pwa_push_table_exists($pdo, $table) : false;
    } catch (Throwable) {
        return false;
    }
}

function mg_pwa_admin_push_counts(PDO $pdo): array
{
    $counts = [
        'active_subscriptions' => 0,
        'queued_deliveries' => 0,
        'failed_deliveries' => 0,
        'last_worker_attempt_at' => null,
        'last_delivery_status' => null,
    ];

    if (mg_pwa_admin_table_exists_safe($pdo, 'pwa_push_subscriptions')) {
        $counts['active_subscriptions'] = (int)$pdo->query("SELECT COUNT(*) FROM pwa_push_subscriptions WHERE status='active'")->fetchColumn();
    }
    if (mg_pwa_admin_table_exists_safe($pdo, 'pwa_notification_deliveries')) {
        $row = $pdo->query("SELECT COUNT(CASE WHEN status='queued' THEN 1 END) queued_deliveries, COUNT(CASE WHEN status='failed' THEN 1 END) failed_deliveries, MAX(last_attempt_at) last_worker_attempt_at FROM pwa_notification_deliveries")->fetch(PDO::FETCH_ASSOC) ?: [];
        $counts['queued_deliveries'] = (int)($row['queued_deliveries'] ?? 0);
        $counts['failed_deliveries'] = (int)($row['failed_deliveries'] ?? 0);
        $counts['last_worker_attempt_at'] = $row['last_worker_attempt_at'] ?? null;
        $last = $pdo->query("SELECT status FROM pwa_notification_deliveries ORDER BY COALESCE(last_attempt_at,created_at) DESC,id DESC LIMIT 1")->fetchColumn();
        $counts['last_delivery_status'] = $last ? (string)$last : null;
    }

    return $counts;
}

function mg_pwa_admin_push_readiness(PDO $pdo, int $userId): array
{
    $vapid = mg_pwa_admin_vapid_status();
    $health = function_exists('mg_pwa_push_health') ? mg_pwa_push_health($pdo) : [];
    $counts = mg_pwa_admin_push_counts($pdo);
    $tables = [
        'notifications' => mg_pwa_admin_table_exists_safe($pdo, 'notifications'),
        'notification_delivery_jobs' => mg_pwa_admin_table_exists_safe($pdo, 'notification_delivery_jobs'),
        'pwa_push_subscriptions' => mg_pwa_admin_table_exists_safe($pdo, 'pwa_push_subscriptions'),
        'pwa_notification_deliveries' => mg_pwa_admin_table_exists_safe($pdo, 'pwa_notification_deliveries'),
        'pwa_branding_settings' => mg_pwa_admin_table_exists_safe($pdo, 'pwa_branding_settings'),
        'pwa_branding_assets' => mg_pwa_admin_table_exists_safe($pdo, 'pwa_branding_assets'),
    ];
    $sqlReady = !in_array(false, $tables, true);
    $activeForUser = mg_pwa_admin_table_exists_safe($pdo, 'pwa_push_subscriptions') ? mg_pwa_push_active_subscription_count($pdo, $userId) : 0;
    $manifestPresent = is_file(dirname(__DIR__, 2) . '/manifest.php') || is_file(dirname(__DIR__, 2) . '/manifest.webmanifest');
    $lastTest = $health['last_test_notification_result'] ?? null;
    $lastTestStatus = is_array($lastTest) ? (string)($lastTest['status'] ?? '') : '';
    $lastTestGood = in_array($lastTestStatus, ['sent', 'delivered', 'opened'], true);
    $lastTestPending = in_array($lastTestStatus, ['queued'], true);

    $items = [
        ['key' => 'sql_imported', 'label' => 'SQL imported', 'status' => $sqlReady ? 'ready' : 'missing', 'detail' => $sqlReady ? 'Stage 25 PWA tables are installed.' : 'One or more Stage 25 or notification tables are missing.'],
        ['key' => 'pwa_push_enabled', 'label' => 'PWA push enabled', 'status' => !empty($vapid['enabled']) ? 'ready' : 'missing', 'detail' => !empty($vapid['enabled']) ? 'PWA push is enabled in config.' : 'PWA push is disabled in config.'],
        ['key' => 'vapid_public', 'label' => 'VAPID public key configured', 'status' => !empty($vapid['public_key_configured']) ? 'ready' : 'missing', 'detail' => !empty($vapid['public_key_configured']) ? 'Public key is available for browser subscriptions.' : 'Generate or add MG_PWA_VAPID_PUBLIC_KEY.'],
        ['key' => 'vapid_private', 'label' => 'VAPID private key configured', 'status' => !empty($vapid['private_key_configured']) ? 'ready' : 'missing', 'detail' => !empty($vapid['private_key_configured']) ? 'Private signing key is available server-side.' : 'Add MG_PWA_VAPID_PRIVATE_KEY to private server config.'],
        ['key' => 'webpush_provider', 'label' => 'WebPush library installed', 'status' => !empty($vapid['provider_available']) ? 'ready' : 'missing', 'detail' => !empty($vapid['provider_available']) ? 'Minishlink WebPush provider is available.' : 'Install/load the PHP WebPush provider.'],
        ['key' => 'service_worker', 'label' => 'Service worker present', 'status' => !empty($health['service_worker_file_present']) ? 'ready' : 'missing', 'detail' => !empty($health['service_worker_file_present']) ? 'sw.js exists at the app root.' : 'sw.js is missing from the app root.'],
        ['key' => 'manifest', 'label' => 'Manifest present', 'status' => $manifestPresent ? 'ready' : 'missing', 'detail' => $manifestPresent ? 'Manifest endpoint/file is present.' : 'manifest.php or manifest.webmanifest is missing.'],
        ['key' => 'active_subscription', 'label' => 'Current admin subscribed', 'status' => $activeForUser > 0 ? 'ready' : 'warning', 'detail' => $activeForUser > 0 ? $activeForUser . ' active subscription(s) for this admin.' : 'Open /notifications.php and allow browser notifications on this device.'],
        ['key' => 'worker_attempt', 'label' => 'Worker/test delivery has run', 'status' => $counts['last_worker_attempt_at'] ? 'ready' : 'warning', 'detail' => $counts['last_worker_attempt_at'] ? 'Last delivery attempt: ' . $counts['last_worker_attempt_at'] . '.' : 'No PWA delivery attempts yet. Use the test button or run the worker.'],
        ['key' => 'failed_deliveries', 'label' => 'Failed deliveries clear', 'status' => $counts['failed_deliveries'] > 0 ? 'warning' : 'ready', 'detail' => $counts['failed_deliveries'] > 0 ? $counts['failed_deliveries'] . ' failed PWA delivery record(s) need review.' : 'No failed PWA delivery records.'],
        ['key' => 'last_test', 'label' => 'Last test notification', 'status' => $lastTestGood ? 'ready' : ($lastTestPending ? 'warning' : 'warning'), 'detail' => is_array($lastTest) ? ('Last test status: ' . ($lastTestStatus ?: 'unknown') . '.') : 'No PWA test notification has been sent yet.'],
    ];

    $ready = 0;
    $warnings = 0;
    $missing = 0;
    foreach ($items as $item) {
        if ($item['status'] === 'ready') $ready++;
        elseif ($item['status'] === 'warning') $warnings++;
        else $missing++;
    }

    return [
        'items' => $items,
        'summary' => [
            'ready' => $ready,
            'warnings' => $warnings,
            'missing' => $missing,
            'total' => count($items),
            'status' => $missing > 0 ? 'missing' : ($warnings > 0 ? 'warning' : 'ready'),
        ],
        'tables' => $tables,
        'counts' => $counts + ['active_subscriptions_for_current_admin' => $activeForUser],
        'health' => $health,
        'worker_command' => 'php scripts/process_pwa_push_deliveries.php',
        'subscription_page' => '/notifications.php',
    ];
}

function mg_pwa_admin_payload(PDO $pdo, bool $canManage, bool $canTest, int $userId): array
{
    return mg_pwa_branding_payload($pdo) + [
        'can_manage' => $canManage,
        'can_test' => $canTest,
        'vapid' => mg_pwa_admin_vapid_status(),
        'readiness' => mg_pwa_admin_push_readiness($pdo, $userId),
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$userId = (int)$user['id'];
$pdo = mg_db();
$canView = mg_pwa_admin_user_can_view($user);
$canManage = mg_pwa_admin_user_can_manage($user);
$canTest = mg_pwa_admin_user_can_test($user);
if (!$canView && !$canManage) mg_fail('You do not have access to PWA branding settings.',403);

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.pwa_branding.read','user:' . $userId,120,60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_pwa_admin_payload($pdo, $canManage, $canTest, $userId), 'PWA branding loaded.');
    }
    if ($method === 'POST') {
        if (!$canManage && !$canTest) mg_fail('You do not have permission to update or test PWA branding.',403);
        mg_rate_limit('admin.pwa_branding.write','user:' . $userId,30,300);
        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'multipart/form-data') !== false) {
            if (!$canManage) mg_fail('You do not have permission to update PWA branding.',403);
            mg_require_csrf_for_write($_POST);
            if (strtolower(trim((string)($_POST['action'] ?? 'upload'))) !== 'upload') mg_fail('Invalid PWA branding action.',422);
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) mg_fail('No PWA image was provided.',422);
            $payload = mg_pwa_branding_upload($pdo, $_FILES['file'], strtolower(trim((string)($_POST['role'] ?? ''))), $userId);
            header('Cache-Control: private, no-store, max-age=0');
            mg_ok($payload + ['can_manage'=>true, 'can_test'=>$canTest, 'vapid'=>mg_pwa_admin_vapid_status(), 'readiness'=>mg_pwa_admin_push_readiness($pdo, $userId)], 'PWA branding image uploaded.', 201);
        }
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'save_settings')));
        if ($action === 'generate_vapid_keys') {
            if (!$canManage) mg_fail('You do not have permission to generate PWA configuration.',403);
            header('Cache-Control: private, no-store, max-age=0');
            mg_ok(mg_pwa_admin_payload($pdo, true, $canTest, $userId) + ['generated_vapid_keys'=>mg_pwa_admin_generate_vapid_keys()], 'VAPID key pair generated.');
        }
        if ($action === 'test_pwa_notification') {
            if (!$canTest) mg_fail('You do not have permission to test PWA notifications.',403);
            $test = mg_pwa_push_send_test_to_user($pdo, $userId);
            header('Cache-Control: private, no-store, max-age=0');
            mg_ok(mg_pwa_admin_payload($pdo, $canManage, true, $userId) + ['test_result'=>$test], !empty($test['created']) ? 'PWA test notification sent.' : 'PWA test notification could not be sent.');
        }
        if ($action !== 'save_settings') mg_fail('Invalid PWA branding action.',422);
        if (!$canManage) mg_fail('You do not have permission to update PWA branding.',403);
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : $input;
        $payload = mg_pwa_branding_save_settings($pdo, $settings, $userId);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok($payload + ['can_manage'=>true, 'can_test'=>$canTest, 'vapid'=>mg_pwa_admin_vapid_status(), 'readiness'=>mg_pwa_admin_push_readiness($pdo, $userId)], 'PWA branding settings saved.');
    }
    mg_fail('Method not allowed.',405);
} catch (Throwable $e) {
    mg_security_log('error','admin.pwa_branding.request_failed','PWA branding admin request failed.',['exception_class'=>$e::class],$userId);
    mg_fail('Unable to update PWA branding.',500);
}
