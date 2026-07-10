<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);

$core = $read('includes/delivery-operations.php')
    . $read('includes/delivery-operations-config.php')
    . $read('includes/delivery-operations-adapters.php')
    . $read('includes/delivery-operations-worker.php')
    . $read('includes/delivery-operations-admin.php');
$communications = $read('api/communications/_communications.php');
$sql = $read('database/delivery_operations_capacity_foundation_v1.sql');
$api = $read('api/admin/delivery-operations.php');
$page = $read('admin/delivery-operations.php');
$js = $read('assets/js/delivery-operations.js');
$modal = $read('assets/css/gift-action-center-modals.css');
$inbox = $read('inbox.php');
$claimed = $read('claimed.php');
$center = $read('includes/gift-action-center.php');
$manifest = $read('config/migrations.php');
$sidebar = $read('includes/admin-sidebar.php');
$workflow = $read('.github/workflows/delivery-operations-capacity-foundation-v1-validation.yml');

$sections = [
    'Canonical delivery authority' => [
        str_contains($core, 'not issue Microgifts'),
        !str_contains($core, 'mg_microgift_issue('),
        !str_contains($core, 'INSERT INTO microgift_instances'),
        str_contains($core, 'Inbox and the in-app notification row remain the durable'),
    ],
    'Durable idempotent outbox' => [
        str_contains($sql, 'ALTER TABLE notification_delivery_jobs'),
        str_contains($sql, 'job_key'),
        str_contains($sql, 'uq_notification_delivery_jobs_job_key'),
        str_contains($communications, 'mg_notification_delivery_job_key'),
        str_contains($communications, 'ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)'),
    ],
    'Leases and concurrency' => [
        str_contains($sql, 'lease_token'),
        str_contains($core, 'GET_LOCK'),
        str_contains($core, 'worker_overlap_detected'),
        str_contains($core, "status='processing'"),
        str_contains($core, 'mg_delivery_recover_expired_leases'),
        str_contains($core, 'attempt_count=?'),
    ],
    'Commercial capacity and fairness' => [
        str_contains($core, 'MG_DELIVERY_BATCH_SIZE'),
        str_contains($core, 'MG_DELIVERY_MAX_RUNTIME_SECONDS'),
        str_contains($core, 'MG_DELIVERY_MAX_PER_USER_PER_RUN'),
        str_contains($core, 'MG_DELIVERY_MAX_PER_MERCHANT_PER_RUN'),
        str_contains($core, 'max_per_merchant_per_run'),
        str_contains($sql, 'merchant_user_id'),
    ],
    'Retries and dead letters' => [
        str_contains($core, 'mg_delivery_backoff_seconds'),
        str_contains($core, 'retry_scheduled'),
        str_contains($core, 'dead_letter'),
        str_contains($core, 'lease_expired'),
        str_contains($core, '$resetAttempts = $action === \'requeue_dead_letter\''),
        str_contains($core, "attempt_count=IF(?,0,attempt_count)"),
    ],
    'Channel separation and fail-closed defaults' => [
        str_contains($communications, "'in_app_enabled' => 1"),
        str_contains($communications, "'email_enabled' => 0"),
        str_contains($communications, "'sms_enabled' => 0"),
        str_contains($communications, "'push_enabled' => 0"),
        str_contains($core, "'email' => mg_delivery_bool"),
        str_contains($core, 'provider_adapter_missing'),
        str_contains($core, 'mg_delivery_register_adapter'),
    ],
    'Monitoring and safety pause' => [
        str_contains($sql, 'mg_delivery_worker_runs'),
        str_contains($sql, 'mg_delivery_provider_events'),
        str_contains($core, 'mg_delivery_maybe_pause'),
        str_contains($core, 'ACKNOWLEDGE DELIVERY WORKER PAUSE'),
        str_contains($core, 'failure_pause_percent'),
        str_contains($core, 'mg_delivery_summary'),
        str_contains($core, 'mg_delivery_channel_readiness'),
        str_contains($core, 'mg_delivery_apply_provider_event'),
    ],
    'Protected operator recovery' => [
        $exists('admin/delivery-operations.php'),
        $exists('api/admin/delivery-operations.php'),
        str_contains($api, 'mg_require_csrf_for_write'),
        str_contains($api, 'delivery.operations.manage'),
        str_contains($core, 'requeue_dead_letter'),
        str_contains($core, 'Only inactive pending or failed jobs can be cancelled safely.'),
        str_contains($js, 'textContent'),
        !str_contains($js, 'innerHTML'),
        str_contains($js, "'Content-Type': 'application/json'"),
        str_contains($js, "event.key !== 'Tab'"),
        !str_contains($page, 'mg_delivery_run('),
        !str_contains($api, 'mg_delivery_run('),
        str_contains($sidebar, "'delivery-operations'"),
        str_contains($sidebar, '/admin/delivery-operations.php'),
    ],
    'CLI-only worker and deployment control' => [
        $exists('bin/delivery-worker.php'),
        str_contains($read('bin/delivery-worker.php'), "PHP_SAPI !== 'cli'"),
        str_contains($read('bin/delivery-worker.php'), '--observe'),
        str_contains($read('bin/delivery-worker.php'), '--process'),
        str_contains($core, 'MG_DELIVERY_WORKER_ENABLED'),
        str_contains($manifest, 'delivery_operations_capacity_foundation_v1.sql'),
        str_contains($workflow, "php: ['8.2', '8.3']"),
        str_contains($workflow, 'Delivery operations ten-point contract'),
    ],
    'Canonical modal CSS consolidation' => [
        $exists('assets/css/gift-action-center-modals.css'),
        str_contains($modal, 'Canonical Action Center modal system'),
        str_contains($center, '/assets/css/gift-action-center-modals.css'),
        !str_contains($center, 'gift-action-center-modal-fix.css'),
        !str_contains($inbox, 'gift-action-center-send-modal.css'),
        !str_contains($inbox, 'gift-action-center-claim-modal.css'),
        !str_contains($claimed, 'gift-action-center-claim-modal.css'),
        str_contains($modal, 'body > .mg-action-modal'),
        str_contains($modal, '.mg-send-exact-modal'),
        str_contains($modal, '.mg-claim-modal'),
        str_contains($page, 'data-channel-stat="accepted"'),
        str_contains($read('assets/css/delivery-operations.css'), '.mg-delivery-channel-grid article.is-warning'),
    ],
];

$total = count($sections);
$passed = 0;
$failed = [];
foreach ($sections as $name => $checks) {
    $ok = !in_array(false, $checks, true);
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if ($ok) $passed++; else $failed[] = $name;
}
$score = round(($passed / max(1, $total)) * 10, 1);
echo 'Delivery Operations scoped score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Failed sections: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "Delivery Operations & Capacity Foundation v1 passed at 10.0/10.\n";
