<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$required = [
 'account-agent-automation-definitions.php','includes/mcp-automations.php','includes/mcp-automations/definitions.php','includes/mcp-automations/definition-lifecycle.php','includes/mcp-automations/simulations.php','includes/mcp-automations/definitions-repository.php','includes/mcp-automations/definitions-page-view.php','assets/css/mcp-automation-definitions.css','config/mcp_automation_definitions_phase4b_release.php','docs/MICROGIFTER_MCP_AUTOMATION_DEFINITIONS_PHASE4B.md','tests/phpunit/McpAutomationDefinitionsPhase4bV1ContractTest.php','.github/workflows/mcp-automation-definitions-phase4b.yml','database/20260720_microgifter_mcp_automation_foundation_v1.sql'
];
$ok=true;$files=[];foreach($required as $path){$exists=is_file($root.'/'.$path);$files[]=['path'=>$path,'exists'=>$exists];$ok=$ok&&$exists;}
$service=implode("\n",array_map(static fn(string $f):string=>is_file($f)?(string)file_get_contents($f):'',array_merge([$root.'/includes/mcp-automations.php'],glob($root.'/includes/mcp-automations/*.php')?:[])));
$page=(string)@file_get_contents($root.'/account-agent-automation-definitions.php')."\n".(string)@file_get_contents($root.'/includes/mcp-automations/definitions-page-view.php');
$release=(string)@file_get_contents($root.'/config/mcp_automation_definitions_phase4b_release.php');
$simulation=(string)@file_get_contents($root.'/includes/mcp-automations/simulations.php');
$checks=[
 'owner_grant_scope'=>str_contains($service,'a.owner_user_id=?')&&str_contains($service,'g.authorizing_user_id=?'),
 'fixed_playbook'=>str_contains($service,'MCP_AUTOMATION_PLAYBOOK_DENIED')&&str_contains($service,'mg_mcp_automation_playbook_catalog'),
 'manual_trigger_only'=>str_contains($service,"trigger_type='manual'")&&str_contains($release,"'allowed_trigger_types' => ['manual']"),
 'simulation_run_history'=>str_contains($service,'INSERT INTO mcp_automation_runs')&&str_contains($service,'mg_mcp_automation_recent_simulations'),
 'proposed_actions_only'=>str_contains($service,"'proposed',1")&&str_contains($simulation,"'execution_attempted' => false"),
 'zero_action_receipts'=>str_contains($simulation,"'action_receipts_created' => 0")&&!str_contains($simulation,'INSERT INTO mcp_action_receipts'),
 'grant_policy_revalidation'=>str_contains($service,'mg_mcp_automation_authorize_grant_action')&&str_contains($service,'mg_mcp_automation_assert_grant_activatable'),
 'lifecycle_controls'=>str_contains($service,'MCP_AUTOMATION_DEFINITION_TRANSITION_DENIED')&&str_contains($service,'cancellation_requested_at=COALESCE'),
 'runtime_disabled'=>str_contains($page,'Simulation-only deployment state')&&str_contains($page,'No scheduler or canonical action path exists in Phase 4B')&&str_contains($release,"'runtime_execution_enabled' => false"),
 'no_new_sql'=>str_contains($release,"'new_migrations' => []")&&!is_file($root.'/database/20260721_mcp_automation_definitions_phase4b_v1.sql'),
];
foreach($checks as $passed){$ok=$ok&&$passed;}
echo json_encode(['ok'=>$ok,'files'=>$files,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($ok?0:1);
