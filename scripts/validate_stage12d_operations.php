<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$files = [
  'api/merchant/campaign-detail.php',
  'api/merchant/wallet-lookup.php',
  'api/account/wallet-items.php',
  'assets/js/stage12-campaign-tools.js',
  'assets/js/stage12-wallet.js',
];
$ok = true;
foreach ($files as $file) { $ok = $ok && is_file($root . '/' . $file); }
$read = static fn(string $file): string => is_file($root . '/' . $file) ? (string) file_get_contents($root . '/' . $file) : '';
$checks = [
  'detail_api' => str_contains($read('api/merchant/campaign-detail.php'), 'campaign_events'),
  'detail_contacts' => str_contains($read('api/merchant/campaign-detail.php'), 'campaign_contacts'),
  'lookup_api' => str_contains($read('api/merchant/wallet-lookup.php'), 'wallet_items'),
  'list_state' => str_contains($read('api/account/wallet-items.php'), 'display_value'),
  'tools_js' => str_contains($read('assets/js/stage12-campaign-tools.js'), 'campaign-detail.php'),
  'wallet_js' => str_contains($read('assets/js/stage12-wallet.js'), 'Wallet summary'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
