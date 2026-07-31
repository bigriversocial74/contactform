<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'install' => 'examples/local-quest-rewards/install.php',
    'install_functions' => 'examples/local-quest-rewards/install-functions.php',
    'lock' => 'examples/local-quest-rewards/install-lock.php',
    'config' => 'examples/local-quest-rewards/config.example.php',
    'cover' => 'examples/local-quest-rewards/cover.php',
    'public_css' => 'examples/local-quest-rewards/assets/public-site.css',
    'admin_auth' => 'examples/local-quest-rewards/admin-auth.php',
    'admin_credentials' => 'examples/local-quest-rewards/admin-credentials.php',
    'quest_controls' => 'examples/local-quest-rewards/quest-controls.php',
    'webhook' => 'examples/local-quest-rewards/webhook.php',
    'webhook_storage' => 'examples/local-quest-rewards/webhook-storage.php',
    'start' => 'examples/local-quest-rewards/start.php',
    'diagnostics' => 'examples/local-quest-rewards/runtime-diagnostics.php',
    'readiness' => 'examples/local-quest-rewards/admin-developer-readiness.php',
    'demo_tools' => 'examples/local-quest-rewards/admin-demo-tools.php',
    'migration' => 'examples/local-quest-rewards/database/local_quest_production_foundation_v1.sql',
    'participant_migration' => 'examples/local-quest-rewards/database/local_quest_participant_auth_v1.sql',
    'readme' => 'examples/local-quest-rewards/README.md',
    'workflow' => '.github/workflows/local-quest-checks.yml',
];
function lqpf_read(string $root,string $path): string { $full=$root.'/'.$path; return is_file($full)?(string)file_get_contents($full):''; }
function lqpf_has(string $content,array $needles): bool { foreach($needles as $needle) if(!str_contains($content,$needle)) return false; return true; }
$contents=[];$checks=[];
foreach($files as $key=>$path){$contents[$key]=lqpf_read($root,$path);$checks[]=['name'=>'file:'.$path,'ok'=>$contents[$key]!==''];}
$installer=$contents['install'].'\n'.$contents['install_functions'];
$checks[]=['name'=>'guarded production installer','ok'=>lqpf_has($installer,["require __DIR__.'/install-lock.php'",'lqi_guard_installer();','lqi_sql_statements','local_quest_production_foundation_v1.sql','local_quest_participant_auth_v1.sql',"'driver'=>'mysql'",'lqi_atomic_write','lqi_api_test','lqi_write_lock();','assets/form-review.js'])];
$checks[]=['name'=>'installer parser strips comments safely','ok'=>str_contains($contents['install_functions'],"preg_replace('/^\\s*(?:--|#).*$/m'")&&str_contains($contents['install_functions'],'lqi_run_sql_file')];
$checks[]=['name'=>'installer lock writes protected marker','ok'=>lqpf_has($contents['lock'],['.installed.lock','.install-unlock','file_put_contents','@chmod','Installer locked.'])];
$checks[]=['name'=>'sql-only safe example config','ok'=>lqpf_has($contents['config'],["'driver' => 'mysql'","'bootstrap_enabled' => false","'security' => [","'installation' => ["])&&!str_contains($contents['config'],"'driver' => 'json'")&&!str_contains($contents['config'],'change-me-admin-password')];
$checks[]=['name'=>'professional public landing page','ok'=>lqpf_has($contents['cover'],["header('Location: install.php')",'assets/public-site.css','meta name="description"','application/ld+json','Explore. Gift.','Earn Rewards.','Featured Gifts &amp; Quests','Everything you earn stays organized.','FOR LOCAL BUSINESSES','hero-phone.svg','quest-placeholder.svg'])&&str_contains($contents['public_css'],'.hero-media')&&str_contains($contents['public_css'],'.wallet-section')];
$checks[]=['name'=>'owner-only credential management','ok'=>lqpf_has($contents['admin_credentials'],["require __DIR__ . '/admin-roles.php'","lqr_admin_require_role(\$current ?? [], 'owner')",'The final active owner cannot be disabled.','lqr_admin_role_options'])];
$checks[]=['name'=>'admin session hardening','ok'=>lqpf_has($contents['admin_auth'],['session_regenerate_id(true);','lqr_admin_login_guard','lqr_admin_note_login_failure',"'bootstrap_enabled' => false"])&&!str_contains($contents['admin_auth'],"'password' => 'change-me-admin-password'")];
$checks[]=['name'=>'public quest visibility enforcement','ok'=>lqpf_has($contents['quest_controls'],['lqr_quest_is_public','if (!$includeRestricted && !lqr_quest_is_public($quest)) continue;'])];
$checks[]=['name'=>'database-backed private webhooks','ok'=>lqpf_has($contents['webhook'],["require __DIR__ . '/webhook-storage.php'","require __DIR__ . '/admin-auth.php'",'lqr_webhook_delivery_exists','lqr_webhook_store_delivery','http_response_code(204)'])&&lqpf_has($contents['webhook_storage'],['lqr_webhook_deliveries','lqr_webhook_recent_deliveries','lqr_webhook_delivery_count'])&&!str_contains($contents['webhook'],'webhook-events.log')];
$checks[]=['name'=>'launcher and readiness use sql webhook evidence','ok'=>str_contains($contents['start'],'lqr_webhook_delivery_count')&&str_contains($contents['readiness'],'lqr_webhook_recent_deliveries')&&str_contains($contents['demo_tools'],'lqr_webhook_store_delivery')&&!str_contains($contents['start'],'webhook-events.log')&&!str_contains($contents['readiness'],'webhook-events.log')&&!str_contains($contents['demo_tools'],'webhook-events.log')];
$checks[]=['name'=>'production schema version package','ok'=>lqpf_has($contents['migration'],['CREATE TABLE IF NOT EXISTS lqr_schema_versions','description VARCHAR(255)','ADD COLUMN IF NOT EXISTS description','2026.07.10-production-foundation-v1'])];
$checks[]=['name'=>'participant auth schema compatibility','ok'=>lqpf_has($contents['participant_migration'],['CREATE TABLE IF NOT EXISTS lqr_participant_auth_tokens','CREATE TABLE IF NOT EXISTS lqr_participant_login_attempts','INSERT INTO lqr_schema_versions (version_key,description,applied_at)'])&&str_contains($contents['install_functions'],'local_quest_participant_auth_v1.sql')];
$checks[]=['name'=>'production diagnostics','ok'=>lqpf_has($contents['diagnostics'],['Installer locked','Production schema','Schema version','Webhook SQL storage'])];
$checks[]=['name'=>'readme documents production flow','ok'=>lqpf_has($contents['readme'],['## Installation','## Public participant experience','## Webhooks','## Security foundation','## Validation'])];
$checks[]=['name'=>'workflow runs production validator','ok'=>str_contains($contents['workflow'],'validate_local_quest_production_foundation_v1.php')&&str_contains($contents['workflow'],"php: ['8.2', '8.3']")];
$failed=array_values(array_filter($checks,static fn(array $check):bool=>empty($check['ok'])));
$result=['ok'=>$failed===[],'checks'=>$checks,'failed'=>$failed,'generated_at'=>gmdate('c')];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['ok']?0:1);
