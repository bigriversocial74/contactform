<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$ledgerTools = is_file($root . '/examples/local-quest-rewards/ledger-tools.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/ledger-tools.php') : '';
$ledgerPage = is_file($root . '/examples/local-quest-rewards/admin-ledger.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/admin-ledger.php') : '';
$validator = is_file($root . '/scripts/validate_local_quest_demo_v2.php') ? (string)file_get_contents($root . '/scripts/validate_local_quest_demo_v2.php') : '';
$checks = [
    ['name'=>'ledger helper file','ok'=>$ledgerTools !== ''],
    ['name'=>'ledger page file','ok'=>$ledgerPage !== ''],
    ['name'=>'ledger helpers','ok'=>str_contains($ledgerTools, 'lqr_ledger_rows') && str_contains($ledgerTools, 'lqr_ledger_metrics') && str_contains($ledgerTools, 'lqr_ledger_chain')],
    ['name'=>'ledger event map','ok'=>str_contains($ledgerTools, 'reward.issue') && str_contains($ledgerTools, 'reward.claim.report') && str_contains($ledgerTools, 'webhook.verified')],
    ['name'=>'ledger page ui','ok'=>str_contains($ledgerPage, 'Transaction Ledger') && str_contains($ledgerPage, 'lqr_ledger_filtered_rows') && str_contains($ledgerPage, 'Lifecycle chain')],
    ['name'=>'existing validator includes v9','ok'=>str_contains($validator, 'partner transaction ledger helpers') && str_contains($validator, 'partner transaction ledger page')],
];
$failed = array_values(array_filter($checks, static fn(array $check): bool => empty($check['ok'])));
$result = ['ok'=>count($failed)===0,'checks'=>$checks,'failed'=>$failed,'generated_at'=>gmdate('c')];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
