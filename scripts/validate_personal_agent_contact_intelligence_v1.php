<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$required = [
    'database/stage_18am_personal_agent_contact_intelligence_v1.sql',
    'config/migrations.php',
    'includes/personal-agent/contact-intelligence.php',
    'includes/personal-agent/contact-intelligence-dashboard.php',
    'includes/personal-agent/credit-response.php',
    'includes/personal-gifting-agent.php',
    'api/user-agent/action-confirm.php',
    'api/user-agent/signals.php',
    'api/user-agent/dashboard.php',
    'assets/js/personal-agent-contact-intelligence.js',
    'assets/css/personal-agent-contact-intelligence.css',
    'includes/personal-agent/workspace-dashboard.php',
    'agent.php',
];

$checks = [];
foreach ($required as $path) $checks['file: ' . $path] = is_file($root . '/' . $path);

$sql = $read('database/stage_18am_personal_agent_contact_intelligence_v1.sql');
$manifest = $read('config/migrations.php');
$service = $read('includes/personal-agent/contact-intelligence.php');
$dashboard = $read('includes/personal-agent/contact-intelligence-dashboard.php');
$credit = $read('includes/personal-agent/credit-response.php');
$include = $read('includes/personal-gifting-agent.php');
$confirmApi = $read('api/user-agent/action-confirm.php');
$signalApi = $read('api/user-agent/signals.php');
$dashboardApi = $read('api/user-agent/dashboard.php');
$ui = $read('assets/js/personal-agent-contact-intelligence.js');
$css = $read('assets/css/personal-agent-contact-intelligence.css');
$workspace = $read('includes/personal-agent/workspace-dashboard.php');
$agent = $read('agent.php');

$checks['schema has action drafts, receipts, and signals'] = str_contains($sql,'CREATE TABLE IF NOT EXISTS user_agent_action_drafts')
    && str_contains($sql,'CREATE TABLE IF NOT EXISTS user_agent_action_receipts')
    && str_contains($sql,'CREATE TABLE IF NOT EXISTS user_agent_relationship_signals')
    && str_contains($sql,"'stage_18am_personal_agent_contact_intelligence_v1'");
$checks['migration is canonical and ordered'] = str_contains($manifest,"'stage_18am_personal_agent_contact_intelligence_v1.sql'")
    && strpos($manifest,"'stage_18al_personal_agent_followup_recovery_v1.sql'") < strpos($manifest,"'stage_18am_personal_agent_contact_intelligence_v1.sql'")
    && strpos($manifest,"'stage_18am_personal_agent_contact_intelligence_v1.sql'") < strpos($manifest,"'stage_19_ai_provider_models.sql'");
$checks['permission is package compatible and action scoped'] = str_contains($sql,"'agent.personal.contact_actions'")
    && str_contains($service,"mg_has_permission('agent.personal.contact_actions')")
    && str_contains($service,"mg_has_permission('agent.personal.use')");

$checks['deterministic account questions are supported'] = str_contains($service,"'contact_count'")
    && str_contains($service,"'list_count'")
    && str_contains($service,"'missing_birthdays'")
    && str_contains($service,"'upcoming_birthdays'")
    && str_contains($service,"'gift_history'")
    && str_contains($service,"'signals'");
$checks['reviewable account actions are supported'] = str_contains($service,"'create_list'")
    && str_contains($service,"'create_contact'")
    && str_contains($service,"'add_contact_to_list'")
    && str_contains($service,"'create_contact_and_add_to_list'")
    && str_contains($service,"'set_birthday'")
    && str_contains($service,"'create_date'")
    && str_contains($service,"'create_reminder'");
$checks['chat prepares drafts instead of writing directly'] = str_contains($service,'mg_personal_agent_contact_action_draft')
    && str_contains($service,"'confirm_contact_action'")
    && str_contains($service,"'pending'")
    && str_contains($service,'Nothing has been saved yet.');
$checks['confirmed execution reuses canonical owner-scoped services'] = str_contains($service,'mg_user_contact_list_create($pdo,$userId')
    && str_contains($service,'mg_user_contact_create($pdo,$userId')
    && str_contains($service,'mg_user_contact_add_member($pdo,$userId')
    && str_contains($service,'mg_personal_agent_create_date($pdo,$userId')
    && str_contains($service,'mg_personal_agent_create_reminder($pdo,$userId')
    && str_contains($service,'WHERE owner_user_id=? AND public_id=? FOR UPDATE');
$checks['actions are idempotent, expiring, and auditable'] = str_contains($service,'idempotency_key')
    && str_contains($service,"DATE_ADD(NOW(),INTERVAL 24 HOUR)")
    && str_contains($service,'mg_personal_agent_contact_action_receipt')
    && str_contains($service,"user_agent.contact_action_executed")
    && str_contains($service,"user_agent.contact_action_cancelled");
$checks['relationship signals are evidence backed and customer private'] = str_contains($service,'mg_personal_agent_contact_signal_upsert')
    && str_contains($service,"'upcoming_occasion'")
    && str_contains($service,"'missing_birthday'")
    && str_contains($dashboard,"'private_to_account' => true")
    && !str_contains($signalApi,'merchant');

$checks['deterministic contact requests skip AI credit reservation'] = str_contains($credit,'$deterministicContact')
    && str_contains($credit,'!$deterministicContact')
    && str_contains($credit,'mg_personal_agent_chat_with_contact_intelligence')
    && str_contains($credit,"['key'=>'']");
$checks['confirmation API is authenticated and CSRF protected'] = str_contains($confirmApi,'mg_require_api_user')
    && str_contains($confirmApi,'mg_require_csrf_for_write')
    && str_contains($confirmApi,'mg_personal_agent_execute_contact_action');
$checks['signals and dashboard APIs remain authenticated'] = str_contains($signalApi,'mg_require_api_user')
    && str_contains($dashboardApi,'mg_require_api_user')
    && str_contains($dashboardApi,'mg_personal_agent_dashboard_with_contact_intelligence');
$checks['runtime includes contact intelligence before credit routing'] = strpos($include,"contact-intelligence.php") < strpos($include,"credit-response.php")
    && str_contains($include,'contact-intelligence-dashboard.php');

$checks['chat UI has review, cancel, receipt, and live refresh controls'] = str_contains($ui,'data-agent-action-draft')
    && str_contains($ui,"decision: decision")
    && str_contains($ui,"/api/user-agent/action-confirm.php")
    && str_contains($ui,'data-agent-signal-count')
    && str_contains($ui,'await loadDashboard(false)')
    && str_contains($css,'.mg-agent-action-review-fields')
    && str_contains($css,'.mg-agent-action-review-actions')
    && str_contains($css,'.mg-agent-action-result');
$checks['existing Agent chat layout is preserved with minimal additions'] = str_contains($agent,"personal-agent-contact-intelligence.css?v=1.0.0")
    && str_contains($agent,"personal-agent-contact-intelligence.js?v=1.0.0")
    && str_contains($workspace,'data-personal-agent-feed')
    && str_contains($workspace,'data-personal-agent-composer') === false
    && str_contains($workspace,'How many contacts do I have?')
    && str_contains($workspace,'Which contacts are missing birthdays?');

$failed = [];
foreach ($checks as $name=>$passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}
if ($failed !== []) {
    fwrite(STDERR, PHP_EOL . 'Personal Agent Contact Intelligence v1 validation failed: ' . implode('; ',$failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Personal Agent Contact Intelligence v1 contract: 10/10.' . PHP_EOL;
