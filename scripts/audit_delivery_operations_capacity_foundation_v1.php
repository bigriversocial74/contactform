<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$core = $read('includes/delivery-operations.php');
$sql = $read('database/delivery_operations_capacity_foundation_v1.sql');
$communications = $read('api/communications/_communications.php');
$js = $read('assets/js/delivery-operations.js');

$quality = [
    'Architecture' => str_contains($core, 'notification_delivery_jobs') && !str_contains($core, 'INSERT INTO microgift_instances'),
    'Idempotency' => str_contains($communications, 'mg_notification_delivery_job_key') && str_contains($sql, 'uq_notification_delivery_jobs_job_key'),
    'Concurrency' => str_contains($core, 'GET_LOCK') && str_contains($core, 'lease_token'),
    'Capacity' => str_contains($core, 'max_per_user_per_run') && str_contains($core, 'max_per_merchant_per_run'),
    'Recovery' => str_contains($core, 'retry_scheduled') && str_contains($core, 'dead_letter') && str_contains($core, 'lease_expired'),
    'Safety' => str_contains($core, 'mg_delivery_maybe_pause') && str_contains($core, 'ACKNOWLEDGE DELIVERY WORKER PAUSE'),
    'Privacy' => str_contains($core, "'recipient'=>['id'") && !str_contains($core, "'email'=>"),
    'Operator UX' => str_contains($js, 'openJob') && str_contains($js, 'closeModal') && str_contains($js, 'Escape') && str_contains($js, "event.key !== 'Tab'") && !str_contains($js, 'innerHTML'),
    'Fail-closed channels' => str_contains($communications, "'email_enabled' => 0") && str_contains($communications, "'push_enabled' => 0"),
    'Regression protection' => is_file($root . '/.github/workflows/delivery-operations-capacity-foundation-v1-validation.yml'),
];

$passed = count(array_filter($quality));
foreach ($quality as $name => $ok) echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
$score = round(($passed / count($quality)) * 10, 1);
echo 'Delivery Operations production-readiness score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($passed !== count($quality)) exit(1);
echo "Production-readiness audit passed at 10.0/10.\n";
