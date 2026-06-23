<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$files = [
  'api/public/offers/detail.php',
  'api/public/offers/search.php',
  'api/public/wallet/add.php',
  'api/merchant/campaign-insights.php',
  'offers.php',
  'assets/js/stage12-agent-offers.js',
  'assets/js/stage12-campaign-insights.js',
  'includes/merchant-campaigns-view.php',
];
$ok = true;
foreach ($files as $file) { $ok = $ok && is_file($root . '/' . $file); }
$read = static fn(string $file): string => is_file($root . '/' . $file) ? (string) file_get_contents($root . '/' . $file) : '';
$checks = [
  'offer_detail_endpoint' => str_contains($read('api/public/offers/detail.php'), 'agent_discoverable') && str_contains($read('api/public/offers/detail.php'), 'related_offers'),
  'offer_search_endpoint' => str_contains($read('api/public/offers/search.php'), 'agent_discoverable'),
  'wallet_add_consent' => str_contains($read('api/public/wallet/add.php'), 'mg_require_api_user') && str_contains($read('api/public/wallet/add.php'), 'approved_by_user'),
  'offers_page' => str_contains($read('offers.php'), 'data-stage12-agent-offers') && str_contains($read('offers.php'), '/assets/js/stage12-agent-offers.js'),
  'offers_js' => str_contains($read('assets/js/stage12-agent-offers.js'), '/api/public/offers/search.php') && str_contains($read('assets/js/stage12-agent-offers.js'), '/api/public/wallet/add.php'),
  'insights_endpoint' => str_contains($read('api/merchant/campaign-insights.php'), 'projected_30d') && str_contains($read('api/merchant/campaign-insights.php'), 'agent_adds'),
  'insights_js' => str_contains($read('assets/js/stage12-campaign-insights.js'), '/api/merchant/campaign-insights.php'),
  'merchant_view_loads_insights' => str_contains($read('includes/merchant-campaigns-view.php'), '/assets/js/stage12-campaign-insights.js'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
