<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$paths = [
 'database/stage_12b_campaign_events_agent_context.sql',
 'api/public/wallet/add.php',
 'api/merchant/campaign-activity.php',
 'includes/merchant-campaigns-view.php',
 'includes/merchant-reward-templates-view.php',
 'assets/js/stage12-campaigns.js',
 'assets/js/stage12-reward-templates.js',
 'config/migrations.php',
];
$ok = true;
foreach ($paths as $p) { $ok = $ok && is_file($root . '/' . $p); }
$get = static function(string $p) use ($root): string { return is_file($root . '/' . $p) ? (string) file_get_contents($root . '/' . $p) : ''; };
$checks = [];
$checks['manifest'] = str_contains($get('config/migrations.php'), 'stage_12b_campaign_events_agent_context.sql');
$checks['migration'] = str_contains($get('database/stage_12b_campaign_events_agent_context.sql'), 'BIGINT UNSIGNED NULL');
$checks['wallet_auth'] = str_contains($get('api/public/wallet/add.php'), 'mg_require_api_user()');
$checks['activity_counts'] = str_contains($get('api/merchant/campaign-activity.php'), 'COUNT(DISTINCT wi.id)');
$checks['campaign_js'] = str_contains($get('includes/merchant-campaigns-view.php'), 'stage12-campaigns.js');
$checks['template_js'] = str_contains($get('includes/merchant-reward-templates-view.php'), 'stage12-reward-templates.js');
$checks['template_live'] = str_contains($get('includes/merchant-reward-templates-view.php'), 'data-stage12-template-list');
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok'=>$ok,'checks'=>$checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
