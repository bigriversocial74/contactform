<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_action_decide(PDO $pdo,array $user,string $actionPublicId,string $decision,string $reason): array
{
    $ownerId=(int)($user['id']??0);$decision=strtolower(trim($decision));$reason=mb_substr(trim($reason),0,1000);
    if($ownerId<1)throw new MgMcpCreatorCampaignActionException('Authentication is required.',401);
    if(!in_array($decision,['approve','reject'],true))throw new MgMcpCreatorCampaignActionException('Approval decision is invalid.');
    if($reason==='')throw new MgMcpCreatorCampaignActionException('A decision reason is required for Creator Campaign canonical actions.');
    $pdo->beginTransaction();
    try{
        mg_mcp_creator_campaign_action_expire($pdo,$ownerId);$row=mg_mcp_creator_campaign_action_row($pdo,$actionPublicId,$ownerId,true);$target=$decision==='approve'?'approved':'rejected';
        if((string)$row['approval_status']!=='pending'){
            if((string)$row['approval_status']===$target||((string)$row['approval_status']==='consumed'&&$target==='approved')){$pdo->commit();return mg_mcp_creator_campaign_action_projection($row,true);}
            throw new MgMcpCreatorCampaignActionException('Approval decision conflicts with the recorded decision.',409,'MCP_CREATOR_CAMPAIGN_ACTION_DECISION_CONFLICT');
        }
        if(strtotime((string)$row['approval_expires_at'])<=time())throw new MgMcpCreatorCampaignActionException('Approval request has expired.',409,'MCP_CREATOR_CAMPAIGN_ACTION_APPROVAL_EXPIRED');
        $approval=$pdo->prepare("UPDATE mcp_creator_campaign_action_approvals SET status=?,decision_reason=?,decided_at=NOW(),decided_by_user_id=?,updated_at=NOW() WHERE action_id=? AND status='pending'");$approval->execute([$target,$reason,$ownerId,(int)$row['id']]);if($approval->rowCount()!==1)throw new MgMcpCreatorCampaignActionException('Approval decision lost its state lock.',409,'MCP_CREATOR_CAMPAIGN_ACTION_DECISION_CONFLICT');
        if($target==='approved'){
            $action=$pdo->prepare("UPDATE mcp_automation_actions SET status='approved',updated_at=NOW() WHERE id=? AND status='waiting_for_approval'");$action->execute([(int)$row['id']]);if($action->rowCount()!==1)throw new MgMcpCreatorCampaignActionException('Action approval lost its state lock.',409,'MCP_CREATOR_CAMPAIGN_ACTION_DECISION_CONFLICT');
            $pdo->prepare("UPDATE mcp_automation_runs SET status='approved',updated_at=NOW() WHERE id=? AND status='waiting_for_approval'")->execute([(int)$row['run_id']]);
        }else{
            $pdo->prepare("UPDATE mcp_automation_actions SET status='rejected',error_code='MCP_CREATOR_CAMPAIGN_ACTION_REJECTED',error_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=? AND status='waiting_for_approval'")->execute([$reason,(int)$row['id']]);
            $pdo->prepare("UPDATE mcp_automation_runs SET status='failed',error_code='MCP_CREATOR_CAMPAIGN_ACTION_REJECTED',error_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=? AND status='waiting_for_approval'")->execute([$reason,(int)$row['run_id']]);
        }
        $pdo->commit();$metadata=['action_id'=>$actionPublicId,'approval_id'=>(string)$row['approval_public_id'],'decision'=>$target,'tool'=>(string)$row['tool_name'],'reason'=>$reason,'canonical_effect_executed'=>false];mg_audit('mcp_creator_campaign_action_'.$target,'mcp_automation_action',$metadata,$ownerId);mg_event('mcp.creator_campaign.action.'.$target,$metadata,$ownerId);mg_security_log('warning','mcp.creator_campaign.action.'.$target,'Owner decided a Creator Campaign canonical action request.',$metadata,$ownerId);
        return mg_mcp_creator_campaign_action_projection(mg_mcp_creator_campaign_action_row($pdo,$actionPublicId,$ownerId));
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
