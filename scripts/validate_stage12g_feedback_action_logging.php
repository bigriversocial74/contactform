<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$files = [
  'api/public/offers/feedback.php',
  'api/merchant/campaign-next-step.php',
  'assets/js/stage12-agent-offers.js',
  'assets/js/stage12-agent-action-center.js',
];
$ok = true;
foreach ($files as $file) { $ok = $ok && is_file($root . '/' . $file); }
$read = static fn(string $file): string => is_file($root . '/' . $file) ? (string) file_get_contents($root . '/' . $file) : '';
$checks = [
  'offer_feedback_endpoint' => str_contains($read('api/public/offers/feedback.php'), 'agent_offer.feedback') && str_contains($read('api/public/offers/feedback.php'), 'campaign_events'),
  'merchant_step_endpoint' => str_contains($read('api/merchant/campaign-next-step.php'), 'merchant.next_step') && str_contains($read('api/merchant/campaign-next-step.php'), 'campaign_events'),
  'offers_js_feedback' => str_contains($read('assets/js/stage12-agent-offers.js'), '/api/public/offers/feedback.php') && str_contains($read('assets/js/stage12-agent-offers.js'), 'data-offer-dismiss'),
  'action_js_logging' => str_contains($read('assets/js/stage12-agent-action-center.js'), '/api/merchant/campaign-next-step.php') && str_contains($read('assets/js/stage12-agent-action-center.js'), 'data-next-step'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
