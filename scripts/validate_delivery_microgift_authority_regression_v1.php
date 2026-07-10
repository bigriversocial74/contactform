<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';

$delivery = implode("\n", [
    $read('includes/delivery-operations.php'),
    $read('includes/delivery-operations-config.php'),
    $read('includes/delivery-operations-adapters.php'),
    $read('includes/delivery-operations-worker.php'),
    $read('includes/delivery-operations-admin.php'),
    $read('api/communications/_communications.php'),
]);
$lifecycle = $read('api/microgifts/_lifecycle.php');
$claim = $read('api/microgifts/_claim_authority.php') . $read('api/microgifts/_lifecycle.php');
$redemption = $read('api/microgifts/_atomic_merchant_redemption.php');
$liveBehavior = $read('scripts/validate_microgift_behavior.php');

$checks = [
    'delivery layer does not issue Microgifts' => !str_contains($delivery, 'mg_microgift_issue(') && !str_contains($delivery, 'INSERT INTO microgift_instances'),
    'delivery layer does not claim or redeem value' => !str_contains($delivery, 'mg_microgift_claim(') && !str_contains($delivery, 'mg_microgift_atomic_merchant_redeem('),
    'canonical issue authority remains present' => str_contains($lifecycle, 'function mg_microgift_issue('),
    'canonical claim authority remains present' => str_contains($claim, 'function mg_microgift_claim('),
    'canonical merchant redemption remains present' => str_contains($redemption, 'function mg_microgift_atomic_merchant_redeem('),
    'live behavior suite retains transactional fixture boundary' => str_contains($liveBehavior, '$pdo->beginTransaction()') && str_contains($liveBehavior, '$pdo->rollBack()'),
    'live behavior suite retains issue coverage' => str_contains($liveBehavior, 'mg_microgift_issue(') && str_contains($liveBehavior, "'Issuance replay failed.'"),
    'live behavior suite retains transfer coverage' => str_contains($liveBehavior, 'mg_pppm_transfer_owner_canonical(') && str_contains($liveBehavior, "'Send replay failed.'"),
    'live behavior suite retains claim coverage' => str_contains($liveBehavior, 'mg_microgift_claim(') && str_contains($liveBehavior, "'Claim replay failed.'"),
    'live behavior suite retains redemption coverage' => str_contains($liveBehavior, 'mg_microgift_atomic_merchant_redeem(') && str_contains($liveBehavior, "'Redemption replay failed.'"),
    'live behavior suite retains notification outbox assertion' => str_contains($liveBehavior, 'notification_delivery_jobs') && str_contains($liveBehavior, "'Message delivery jobs were not queued.'"),
    'live behavior suite retains ledger-neutrality assertions' => str_contains($liveBehavior, 'ledger_transaction_groups') && str_contains($liveBehavior, 'ledger_neutral_for_merchant_funded'),
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if ($failed !== []) {
    fwrite(STDERR, 'Microgift authority compatibility regression failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "Microgift authority compatibility regression passed.\n";
