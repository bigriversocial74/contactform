<?php
declare(strict_types=1);
require_once __DIR__.'/includes/mcp-creator-campaign-actions.php';

$user=mg_require_auth();$pdo=mg_db();$notice='';$errorMessage='';
if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='POST'){
    try{
        if(!mg_verify_csrf(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:null))throw new MgMcpCreatorCampaignActionException('Your session expired. Refresh and try again.',419,'MCP_CREATOR_CAMPAIGN_ACTION_CSRF_FAILED');
        $action=strtolower(trim((string)($_POST['action']??'')));$actionId=(string)($_POST['action_id']??'');
        if($action==='decide'){$decision=(string)($_POST['decision']??'');$result=mg_mcp_creator_campaign_action_decide($pdo,$user,$actionId,$decision,(string)($_POST['reason']??''));$notice=$decision==='approve'?'Action approved. Review the current state and use Execute approved action as the separate final step.':'Action rejected. No canonical action was executed.';}
        elseif($action==='execute'){
            if((string)($_POST['confirm_execute']??'')!=='1')throw new MgMcpCreatorCampaignActionException('Confirm execution before continuing.');
            $result=mg_mcp_creator_campaign_action_execute($pdo,$user,$actionId);$notice='Approved Creator Campaign action executed through the native service.';
        }else throw new MgMcpCreatorCampaignActionException('Unknown Creator Campaign action.');
        header('Location: /account-creator-campaign-actions.php?notice='.rawurlencode($notice),true,303);exit;
    }catch(MgMcpCreatorCampaignActionException $error){$errorMessage=$error->getMessage();}catch(Throwable $error){mg_security_log('error','mcp.creator_campaign.action.owner_failed','Creator Campaign owner action failed.',['exception_class'=>$error::class,'exception_message'=>mb_substr($error->getMessage(),0,500)],(int)$user['id']);$errorMessage='The Creator Campaign action could not be completed.';}
}
if(isset($_GET['notice']))$notice=mb_substr(trim((string)$_GET['notice']),0,500);
$statusFilter=strtolower(trim((string)($_GET['status']??'')));$schemaReady=false;$actions=[];
try{$schemaReady=mg_mcp_creator_campaign_action_schema_ready($pdo);if($schemaReady)$actions=mg_mcp_creator_campaign_action_list_owner($pdo,(int)$user['id'],['status'=>$statusFilter]);elseif($errorMessage==='')$errorMessage='Import the Phase 13C SQL before using canonical action approvals.';}catch(Throwable $error){if($errorMessage==='')$errorMessage='Creator Campaign action records are unavailable.';}
$counts=array_fill_keys(MG_MCP_CREATOR_CAMPAIGN_ACTION_STATUSES,0);foreach($actions as $item)$counts[(string)$item['status']]=($counts[(string)$item['status']]??0)+1;
$page_title='Creator Campaign Actions | Microgifter';$page_section='account';$header_mode='account';$agent_tab='agent-automations';$can_merchant_nav=true;$page_body_class='mg-creator-campaign-actions-page';$page_styles=['/assets/css/agent-workspace-layout.css','/assets/css/mcp-creator-campaign-actions.css?v=20260722-phase13c'];require __DIR__.'/includes/header.php';require __DIR__.'/includes/mcp-creator-campaign-actions/owner-page-view.php';require __DIR__.'/includes/footer.php';
