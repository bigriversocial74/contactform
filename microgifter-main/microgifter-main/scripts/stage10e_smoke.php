<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
foreach([
'database/stage_10e_outbox_dashboard_policies_retention.sql',
'api/microgifts/_stage10e_operations.php',
'api/merchant/microgift-claim-dashboard.php',
'api/admin/microgift-rate-policies.php',
'scripts/stage10e_outbox_worker.php',
'scripts/stage10e_retention.php',
'tests/phpunit/Stage10EOutboxDashboardPoliciesRetentionTest.php',
'docs/stages/stage_10e_outbox_dashboard_policies_retention.md'
] as $file){if(!is_file($root.'/'.$file))throw new RuntimeException('Missing Stage 10E artifact: '.$file);}
echo "Stage 10E smoke validation passed.\n";
