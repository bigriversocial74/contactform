<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static function(string $path) use($root):string {
    $full=$root.'/'.$path;
    return is_file($full)?(string)file_get_contents($full):'';
};

$required=[
    'database/20260714_personal_gifting_agent_phase2.sql',
    'config/migrations.php',
    'includes/personal-gifting-agent.php',
    'includes/personal-agent/core.php',
    'includes/personal-agent/data.php',
    'includes/personal-agent/context.php',
    'includes/personal-agent/actions.php',
    'includes/personal-agent/chat.php',
    'includes/personal-agent/workspace-dashboard.php',
    'includes/personal-agent/workspace-dialogs.php',
    'includes/personal-agent-sidebar.php',
    'includes/agent-workspace.php',
    'agent.php',
    'api/user-agent/_bootstrap.php',
    'api/user-agent/dashboard.php',
    'api/user-agent/context.php',
    'api/user-agent/chat.php',
    'api/user-agent/plans.php',
    'api/user-agent/reminders.php',
    'api/user-agent/memory.php',
    'api/user-agent/dates.php',
    'api/user-agent/settings.php',
    'assets/css/personal-gifting-agent.css',
    'assets/js/personal-gifting-agent.js',
    'assets/js/personal-gifting-agent-render.js',
    'assets/js/personal-gifting-agent-actions.js',
    'assets/js/agent-workspace.js',
    'tests/phpunit/PersonalGiftingAgentPhase2Test.php',
    '.github/workflows/personal-gifting-agent-phase2-validation.yml',
];
$checks=[];
foreach($required as $path)$checks['file: '.$path]=is_file($root.'/'.$path);

$sql=$read('database/20260714_personal_gifting_agent_phase2.sql');
$manifest=$read('config/migrations.php');
$service=$read('includes/personal-gifting-agent.php').$read('includes/personal-agent/core.php').$read('includes/personal-agent/data.php').$read('includes/personal-agent/context.php').$read('includes/personal-agent/actions.php').$read('includes/personal-agent/chat.php');
$workspace=$read('includes/agent-workspace.php').$read('includes/personal-agent/workspace-dashboard.php').$read('includes/personal-agent/workspace-dialogs.php');
$sidebar=$read('includes/personal-agent-sidebar.php');
$page=$read('agent.php');
$js=$read('assets/js/personal-gifting-agent.js').$read('assets/js/personal-gifting-agent-render.js').$read('assets/js/personal-gifting-agent-actions.js');
$css=$read('assets/css/personal-gifting-agent.css');
$chatApi=$read('api/user-agent/chat.php');
$plansApi=$read('api/user-agent/plans.php');
$remindersApi=$read('api/user-agent/reminders.php');
$memoryApi=$read('api/user-agent/memory.php');
$datesApi=$read('api/user-agent/dates.php');
$settingsApi=$read('api/user-agent/settings.php');

$tables=['user_agent_settings','user_gifting_plans','user_gifting_plan_members','user_gifting_reminders','user_agent_memory','user_agent_threads','user_agent_messages'];
$tableCoverage=true;
foreach($tables as $table)$tableCoverage=$tableCoverage&&str_contains($sql,'CREATE TABLE IF NOT EXISTS '.$table);
$checks['normalized Phase 2 schema']=$tableCoverage
    &&str_contains($sql,'FOREIGN KEY (owner_user_id) REFERENCES users(id)')
    &&str_contains($sql,'UNIQUE KEY uq_user_agent_memory_owner_key')
    &&str_contains($sql,'UNIQUE KEY uq_user_gifting_plan_member_private')
    &&str_contains($sql,'UNIQUE KEY uq_user_gifting_plan_member_linked');

$phase1Pos=strpos($manifest,"'20260714_user_contact_lists_phase1.sql'");
$phase2Pos=strpos($manifest,"'20260714_personal_gifting_agent_phase2.sql'");
$stage19Pos=strpos($manifest,"'stage_19c_claude_sonnet_merchant_agent_planner.sql'");
$marketPos=strpos($manifest,"'stage_19_merchant_market_snapshots.sql'");
$checks['canonical migration order']=$phase1Pos!==false&&$phase2Pos!==false&&$stage19Pos!==false&&$marketPos!==false&&$phase1Pos<$phase2Pos&&$stage19Pos<$phase2Pos&&$phase2Pos<$marketPos;

$checks['personal agent dashboard preserves Agent shell']=
    str_contains($page,"require __DIR__ . '/includes/agent-workspace.php';")
    &&str_contains($page,"'/assets/css/agent-workspace-layout.css'")
    &&str_contains($page,"'/assets/css/personal-gifting-agent.css'")
    &&str_contains($workspace,'data-personal-gifting-agent')
    &&str_contains($workspace,'data-agent-canvas')
    &&str_contains($workspace,'data-agent-composer')
    &&str_contains($workspace,'data-personal-agent-composer')
    &&str_contains($css,'.mg-personal-agent-composer')
    &&str_contains($css,'position:sticky');

$sidebarLabels=['Home','Contacts','Birthdays','Gift Calendar','Draft Plans','Reminders','Group Gifting','Agent Memory','Settings'];
$sidebarCoverage=true;
foreach($sidebarLabels as $label)$sidebarCoverage=$sidebarCoverage&&str_contains($sidebar,"'label' => '{$label}'");
$checks['personal agent left navigation']=$sidebarCoverage
    &&str_contains($workspace,"personal-agent-sidebar.php")
    &&!str_contains($sidebar,"'lists' =>")
    &&!str_contains($workspace,'Manage lists');

$checks['dashboard and context are owner scoped']=
    substr_count($service,'owner_user_id=?')>=15
    &&str_contains($service,'function mg_personal_agent_dashboard')
    &&str_contains($service,'function mg_personal_agent_resolve_context')
    &&str_contains($service,'mg_user_contact_list_eligibility_detail')
    &&str_contains($service,'Linked contact is no longer eligible.');

$checks['important dates plans reminders and memory services']=
    str_contains($service,'function mg_personal_agent_upcoming_dates')
    &&str_contains($service,'function mg_personal_agent_create_date')
    &&str_contains($service,'function mg_personal_agent_create_plan')
    &&str_contains($service,'function mg_personal_agent_create_reminder')
    &&str_contains($service,'function mg_personal_agent_save_memory')
    &&str_contains($service,'approval_required')
    &&str_contains($service,"['draft','planned','ready','completed','cancelled']");

$checks['private data is excluded from agent context']=
    !str_contains($service,"'phone_ciphertext'")
    &&!str_contains($service,"'address_line_1'")
    &&!str_contains($service,"'email'")
    &&str_contains($service,"'phone_masked'")
    &&str_contains($service,'unset($details[$privateDisplayKey])')
    &&str_contains($service,'Sensitive credentials and private contact details cannot be stored in Agent Memory.')
    &&str_contains($service,'Never expose or repeat full phone numbers');

$providerMarker='$provider[\'id\']=(int)$model[\'provider_id\']';
$checks['Claude reuse has safe fallback and approval boundary']=
    str_contains($service,'mg_anthropic_messages')
    &&str_contains($service,'mg_ai_enforce_rate_limits')
    &&str_contains($service,$providerMarker)
    &&str_contains($service,'foreach ($rows as $row)')
    &&str_contains($service,'function mg_personal_agent_fallback')
    &&str_contains($service,'Nothing will be purchased or sent without your review.')
    &&str_contains($service,"'save_draft_plan'")
    &&!str_contains($service,'INSERT INTO commerce_orders')
    &&!str_contains($service,'INSERT INTO pppm_items')
    &&!str_contains($service,'UPDATE gift_claims');

$writeApis=[$chatApi,$plansApi,$remindersApi,$memoryApi,$datesApi,$settingsApi];
$apiSecurity=true;
foreach($writeApis as $api)$apiSecurity=$apiSecurity&&str_contains($api,'mg_require_api_user')&&str_contains($api,'mg_require_csrf_for_write');
$checks['API authentication and CSRF']=$apiSecurity;

$checks['client supports all Phase 2 views and actions']=
    str_contains($js,"'/api/user-agent/dashboard.php'")
    &&str_contains($js,"'/api/user-agent/context.php")
    &&str_contains($js,"'/api/user-agent/chat.php'")
    &&str_contains($js,"'/api/user-agent/plans.php'")
    &&str_contains($js,"'/api/user-agent/reminders.php'")
    &&str_contains($js,"'/api/user-agent/memory.php'")
    &&str_contains($js,"'/api/user-agent/dates.php'")
    &&str_contains($js,"'/api/user-agent/settings.php'")
    &&str_contains($js,'data-agent-card-index')
    &&str_contains($js,'save_draft_plan')
    &&str_contains($js,'if (!payload.context_type || !payload.context_id)');

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}
if($failed!==[]){
    fwrite(STDERR,PHP_EOL.'Personal Gifting Agent Phase 2 validation failed: '.implode('; ',$failed).PHP_EOL);
    exit(1);
}
echo PHP_EOL.'Personal Gifting Agent Phase 2 contract: 10/10.'.PHP_EOL;
