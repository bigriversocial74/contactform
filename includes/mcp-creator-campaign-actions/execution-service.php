<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_action_native_execute(PDO $pdo,array $user,array $row): array
{
    $tool=(string)$row['tool_name'];$contract=mg_mcp_creator_campaign_action_contract($tool);$input=mg_mcp_creator_campaign_action_decode($row['sanitized_input_json']??null);$nativeKey=(string)($input['_native_idempotency_key']??$row['idempotency_key']);
    $reason=(string)($input['reason']??$row['decision_reason']??$row['requested_reason']??'Owner-approved MCP Creator Campaign action.');
    $result=null;$referenceType='creator_campaign_action';$referenceId=(string)$row['public_id'];
    if(in_array($tool,['microgifter.creator_campaigns.publish','microgifter.creator_campaigns.schedule','microgifter.creator_campaigns.pause','microgifter.creator_campaigns.resume','microgifter.creator_campaigns.complete','microgifter.creator_campaigns.cancel'],true)){
        $campaign=mg_creator_campaign_repository_by_public_id($pdo,(string)$input['campaign_id'],(int)$row['workspace_id']);
        $result=mg_creator_campaign_transition_status($pdo,$user,(int)$campaign['id'],(string)$contract['native_action'],['expected_lock_version'=>(int)($input['expected_lock_version']??0),'reason'=>$reason,'idempotency_key'=>$nativeKey,'workspace_id'=>(int)$row['workspace_id'],'source'=>'mcp_phase13c_owner_execution']);
        $referenceType='creator_campaign';$referenceId=(string)($result['campaign']['public_id']??$input['campaign_id']);
    }elseif(str_contains($tool,'.application.')){
        $result=mg_creator_campaign_application_review_merchant($pdo,$user,(string)$input['application_id'],(string)$contract['native_action'],['expected_lock_version'=>(int)($input['expected_lock_version']??0),'reason'=>$reason,'internal_note'=>$input['internal_note']??null,'idempotency_key'=>$nativeKey]);$referenceType='creator_campaign_application';$referenceId=(string)($result['public_id']??$input['application_id']);
    }elseif($tool==='microgifter.creator_campaigns.invitation.send'){
        $result=mg_creator_campaign_invitation_create_merchant($pdo,$user,(string)$input['campaign_id'],['creator_profile_id'=>(string)$input['creator_profile_id'],'invitation_message'=>$input['invitation_message']??null,'internal_note'=>$input['internal_note']??null,'response_deadline_at'=>$input['response_deadline_at']??null,'idempotency_key'=>$nativeKey]);$referenceType='creator_campaign_invitation';$referenceId=(string)($result['public_id']??'');
    }elseif($tool==='microgifter.creator_campaigns.agreement.offer'){
        $terms=[];foreach(['summary','terms_text','deliverables','compensation','content_rights','disclosures','cancellation','reversal','creator_specific','change_summary','requires_reacceptance'] as $key)if(array_key_exists($key,$input))$terms[$key]=$input[$key];$result=mg_creator_campaign_agreement_offer_merchant($pdo,$user,(string)$input['participant_id'],$terms);$referenceType='creator_campaign_agreement';$referenceId=(string)($result['public_id']??'');
    }elseif(str_contains($tool,'.participant.')){
        $result=mg_creator_campaign_participant_transition_merchant($pdo,$user,(string)$input['participant_id'],(string)$contract['native_action'],['expected_lock_version'=>(int)($input['expected_lock_version']??0),'reason'=>$reason,'idempotency_key'=>$nativeKey]);$referenceType='creator_campaign_participant';$referenceId=(string)($result['public_id']??$input['participant_id']);
    }elseif(str_contains($tool,'.submission.')){
        $result=mg_creator_campaign_submission_review_merchant($pdo,$user,(string)$input['submission_id'],(string)$contract['native_action'],['expected_lock_version'=>(int)($input['expected_lock_version']??0),'feedback'=>$input['feedback']??$reason]);$referenceType='creator_campaign_submission';$referenceId=(string)($result['public_id']??$input['submission_id']);
    }elseif($tool==='microgifter.creator_campaigns.attribution.override'){
        $result=mg_creator_campaign_attribution_override_merchant($pdo,$user,(string)$input['attribution_id'],['expected_lock_version'=>(int)($input['expected_lock_version']??0),'source_id'=>$input['source_id']??'','reason'=>$reason]);$referenceType='creator_campaign_attribution';$referenceId=(string)($result['public_id']??$input['attribution_id']);
    }elseif(str_contains($tool,'.earning.')){
        $result=mg_creator_campaign_earning_decide_merchant($pdo,$user,(string)$input['earning_id'],(string)$contract['native_action'],['reason'=>$reason]);$referenceType='creator_campaign_earning_review';$referenceId=(string)($result['review_id']??$input['earning_id']);
    }elseif($tool==='microgifter.creator_campaigns.payout.record'){
        $result=mg_creator_campaign_payout_create($pdo,$user,(string)$input['participant_id'],['currency'=>$input['currency']??'USD','idempotency_key'=>$nativeKey]);$referenceType='creator_campaign_payout';$referenceId=(string)($result['payout_id']??'');
    }elseif($tool==='microgifter.creator_campaigns.dispute.resolve'){
        $result=mg_creator_campaign_dispute_transition($pdo,$user,(string)$input['dispute_id'],(string)$input['resolution'],['resolution_note'=>$input['resolution_note']??$reason]);$referenceType='creator_campaign_dispute';$referenceId=(string)($result['dispute_id']??$input['dispute_id']);
    }
    if(!is_array($result))throw new RuntimeException('Native Creator Campaign service returned no result.');
    return ['result'=>$result,'reference_type'=>$referenceType,'reference_id'=>$referenceId,'after_state_token'=>hash('sha256',json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))];
}

function mg_mcp_creator_campaign_action_execute(PDO $pdo,array $user,string $actionPublicId): array
{
    $ownerId=(int)($user['id']??0);if($ownerId<1)throw new MgMcpCreatorCampaignActionException('Authentication is required.',401);
    $pdo->beginTransaction();
    try{
        mg_mcp_creator_campaign_action_expire($pdo,$ownerId);$row=mg_mcp_creator_campaign_action_row($pdo,$actionPublicId,$ownerId,true);
        if((string)$row['status']==='succeeded'){$pdo->commit();return mg_mcp_creator_campaign_action_projection(mg_mcp_creator_campaign_action_attach_receipt($pdo,$row),true);}
        if((string)$row['approval_status']!=='approved'||(string)$row['status']!=='approved')throw new MgMcpCreatorCampaignActionException('Action must be explicitly approved before execution.',409,'MCP_CREATOR_CAMPAIGN_ACTION_NOT_APPROVED');
        if((int)($row['decided_by_user_id']??0)!==$ownerId)throw new MgMcpCreatorCampaignActionException('Only the recorded approving owner may execute this action.',403,'MCP_CREATOR_CAMPAIGN_ACTION_APPROVER_MISMATCH');
        if((string)$row['connection_status']!=='active'||(string)$row['client_status']!=='active'&& (string)$row['client_status']!=='development')throw new MgMcpCreatorCampaignActionException('MCP connection or client is no longer active.',403,'MCP_CREATOR_CAMPAIGN_ACTION_CONNECTION_INACTIVE');
        $contract=mg_mcp_creator_campaign_action_contract((string)$row['tool_name']);$target=mg_mcp_creator_campaign_action_decode($row['sanitized_input_json']??null)['_target']??[];
        try{mg_mcp_automation_authorize_grant_action($pdo,(string)$row['connection_public_id'],(string)$row['grant_public_id'],(string)$row['tool_name'],'approval_gated',(string)$row['risk_level'],(int)($row['proposed_amount_cents']??0),(int)($row['proposed_quantity']??1),['campaign_id'=>$target['campaign_id']??null],(int)$row['run_id']);}catch(MgMcpAutomationGrantException $e){throw new MgMcpCreatorCampaignActionException($e->getMessage(),$e->httpStatus(),$e->errorCode());}
        $receiptPublic=mg_public_uuid();$pdo->prepare("INSERT INTO mcp_action_receipts(public_id,action_id,run_id,grant_id,connection_id,tool_name,canonical_service,canonical_action,status,idempotency_key,amount_cents,quantity,before_state_token,metadata_json,attempted_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?, 'attempted',?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE updated_at=NOW()")
            ->execute([$receiptPublic,(int)$row['id'],(int)$row['run_id'],(int)$row['grant_id'],(int)$row['connection_db_id'],(string)$row['tool_name'],(string)$contract['native_service'],(string)$contract['native_action'],(string)$row['idempotency_key'],(int)($row['proposed_amount_cents']??0),(int)($row['proposed_quantity']??1),(string)($row['fresh_state_token']??''),json_encode(['approval_id'=>(string)$row['approval_public_id'],'approver_user_id'=>$ownerId,'workspace_id'=>(int)$row['workspace_id'],'resource_type'=>$target['type']??null,'resource_id'=>$target['id']??null],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        $pdo->prepare("UPDATE mcp_automation_actions SET status='executing',started_at=NOW(),updated_at=NOW() WHERE id=? AND status='approved'")->execute([(int)$row['id']]);$pdo->prepare("UPDATE mcp_automation_runs SET status='executing',started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$row['run_id']]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

    try{
        $native=mg_mcp_creator_campaign_action_native_execute($pdo,$user,$row);
        $pdo->beginTransaction();$locked=mg_mcp_creator_campaign_action_row($pdo,$actionPublicId,$ownerId,true);
        $pdo->prepare("UPDATE mcp_action_receipts SET status='succeeded',after_state_token=?,result_reference_type=?,result_reference_public_id=?,metadata_json=?,completed_at=NOW(),updated_at=NOW() WHERE action_id=?")
            ->execute([(string)$native['after_state_token'],(string)$native['reference_type'],(string)$native['reference_id'],json_encode(['approval_id'=>(string)$locked['approval_public_id'],'approver_user_id'=>$ownerId,'workspace_id'=>(int)$locked['workspace_id'],'native_result'=>$native['result']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$locked['id']]);
        $pdo->prepare("UPDATE mcp_automation_actions SET status='succeeded',result_reference_type=?,result_reference_public_id=?,error_code=NULL,error_message=NULL,completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(string)$native['reference_type'],(string)$native['reference_id'],(int)$locked['id']]);
        $pdo->prepare("UPDATE mcp_automation_runs SET status='succeeded',completed_at=NOW(),error_code=NULL,error_message=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$locked['run_id']]);
        $pdo->prepare("UPDATE mcp_creator_campaign_action_approvals SET status='consumed',executed_at=NOW(),updated_at=NOW() WHERE action_id=? AND status='approved'")->execute([(int)$locked['id']]);$pdo->prepare('UPDATE mcp_automation_grants SET last_used_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$locked['grant_id']]);$pdo->commit();
        $metadata=['action_id'=>$actionPublicId,'approval_id'=>(string)$locked['approval_public_id'],'tool'=>(string)$locked['tool_name'],'native_service'=>(string)mg_mcp_creator_campaign_action_contract((string)$locked['tool_name'])['native_service'],'result_reference_type'=>$native['reference_type'],'result_reference_id'=>$native['reference_id']];mg_audit('mcp_creator_campaign_action_executed','mcp_automation_action',$metadata,$ownerId);mg_event('mcp.creator_campaign.action.executed',$metadata,$ownerId);mg_security_log('warning','mcp.creator_campaign.action.executed','Owner executed an approved Creator Campaign canonical action.',$metadata,$ownerId);
        return mg_mcp_creator_campaign_action_projection(mg_mcp_creator_campaign_action_attach_receipt($pdo,mg_mcp_creator_campaign_action_row($pdo,$actionPublicId,$ownerId)));
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();$pdo->beginTransaction();try{$failed=mg_mcp_creator_campaign_action_row($pdo,$actionPublicId,$ownerId,true);$code=$e instanceof MgMcpCreatorCampaignActionException?$e->errorCode():'MCP_CREATOR_CAMPAIGN_NATIVE_ACTION_FAILED';$message=mb_substr($e->getMessage(),0,1000);$pdo->prepare("UPDATE mcp_action_receipts SET status='failed',error_code=?,error_message=?,completed_at=NOW(),updated_at=NOW() WHERE action_id=?")->execute([$code,$message,(int)$failed['id']]);$pdo->prepare("UPDATE mcp_automation_actions SET status='failed',error_code=?,error_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$code,$message,(int)$failed['id']]);$pdo->prepare("UPDATE mcp_automation_runs SET status='failed',error_code=?,error_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$code,$message,(int)$failed['run_id']]);$pdo->commit();}catch(Throwable){if($pdo->inTransaction())$pdo->rollBack();}
        mg_security_log('error','mcp.creator_campaign.action.failed','Approved Creator Campaign canonical action failed.',['action_id'=>$actionPublicId,'exception_class'=>$e::class,'message'=>mb_substr($e->getMessage(),0,500)],$ownerId);throw new MgMcpCreatorCampaignActionException('Native Creator Campaign action failed: '.$e->getMessage(),409,'MCP_CREATOR_CAMPAIGN_NATIVE_ACTION_FAILED');
    }
}
