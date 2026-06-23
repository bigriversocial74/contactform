<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$files = [
  'api/public/offers/recommendations.php',
  'assets/js/stage12-agent-action-center.js',
  'includes/merchant-campaigns-view.php',
];
$ok = true;
foreach ($files as $file) { $ok = $ok && is_file($root . '/' . $file); }
$read = static fn(string $file): string => is_file($root . '/' . $file) ? (string) file_get_contents($root . '/' . $file) : '';
$checks = [
  'recommendations_api' => str_contains($read('api/public/offers/recommendations.php'), 'recommendations') && str_contains($read('api/public/offers/recommendations.php'), 'recommendation_score'),
  'recommendations_uses_activity' => str_contains($read('api/public/offers/recommendations.php'), 'wallet_add_count') && str_contains($read('api/public/offers/recommendations.php'), 'completion_count'),
  'action_center_js' => str_contains($read('assets/js/stage12-agent-action-center.js'), 'Agent action center') && str_contains($read('assets/js/stage12-agent-action-center.js'), '/api/merchant/campaign-insights.php'),
  'merchant_view_loads_action_center' => str_contains($read('includes/merchant-campaigns-view.php'), '/assets/js/stage12-agent-action-center.js'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
