<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/public/loyalty-quest/_participant.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=mg_merchant_require_permission($method==='GET'?'merchant.campaigns.view':'merchant.campaigns.manage');
$merchantId=(int)$user['id'];$pdo=mg_db();mg_merchant_ensure_workspace($pdo,$user);
if($method==='GET'){
    $campaignId=strtolower(trim((string)($_GET['campaign_id']??'')));
    $status=trim((string)($_GET['status']??'submitted'));
    if($campaignId!==''&&(strlen($campaignId)!==36||preg_match('/^[a-f0-9-]{36}$/',$campaignId)!==1))mg_fail('Invalid Loyalty Quest.',422);
    if(!in_array($status,['submitted','verified','rejected','all'],true))$status='submitted';
    $sql="SELECT lqe.public_id,lqe.evidence_type,lqe.status,lqe.latitude,lqe.longitude,lqe.accuracy_meters,lqe.distance_meters,lqe.proof_url,lqe.proof_note,lqe.reference_id,lqe.review_note,lqe.created_at,lqe.verified_at,lqe.rejected_at,
        lqp.public_id participation_public_id,lqp.status participation_status,lqp.progress_count,lqp.required_count,
        c.public_id campaign_public_id,c.title campaign_title,cc.email participant_email,cc.name participant_name,u.display_name participant_display_name
        FROM loyalty_quest_evidence lqe
        INNER JOIN loyalty_quest_participations lqp ON lqp.id=lqe.participation_id AND lqp.merchant_user_id=lqe.merchant_user_id
        INNER JOIN campaigns c ON c.id=lqe.campaign_id AND c.merchant_user_id=lqe.merchant_user_id AND c.campaign_type='loyalty_quest'
        INNER JOIN users u ON u.id=lqe.participant_user_id
        LEFT JOIN campaign_contacts cc ON cc.id=lqp.contact_id
        WHERE lqe.merchant_user_id=?";
    $params=[$merchantId];
    if($campaignId!==''){$sql.=' AND c.public_id=?';$params[]=$campaignId;}
    if($status!=='all'){$sql.=' AND lqe.status=?';$params[]=$status;}
    $sql.=' ORDER BY lqe.created_at ASC LIMIT 200';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    mg_ok(['reviews'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[],'schema_ready'=>true]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$evidenceId=strtolower(trim((string)($input['evidence_id']??'')));$decision=trim((string)($input['decision']??''));$note=trim((string)($input['review_note']??''));
if(strlen($evidenceId)!==36||preg_match('/^[a-f0-9-]{36}$/',$evidenceId)!==1||!in_array($decision,['approve','reject'],true)||mb_strlen($note)>4000)mg_fail('Invalid review decision.',422);
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare("SELECT lqe.*,lqp.public_id participation_public_id,lqp.progress_count,lqp.required_count,lqp.contact_id,c.public_id campaign_public_id,c.public_slug
        FROM loyalty_quest_evidence lqe
        INNER JOIN loyalty_quest_participations lqp ON lqp.id=lqe.participation_id AND lqp.merchant_user_id=lqe.merchant_user_id
        INNER JOIN campaigns c ON c.id=lqe.campaign_id AND c.merchant_user_id=lqe.merchant_user_id AND c.campaign_type='loyalty_quest'
        WHERE lqe.public_id=? AND lqe.merchant_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$evidenceId,$merchantId]);$evidence=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$evidence)mg_fail('Quest evidence not found.',404);
    if((string)$evidence['status']!=='submitted')mg_fail('Quest evidence has already been reviewed.',409);
    $campaign=mg_lqp_campaign($pdo,(string)$evidence['campaign_public_id'],true);
    $userStmt=$pdo->prepare('SELECT * FROM users WHERE id=? AND status=\'active\' LIMIT 1');$userStmt->execute([(int)$evidence['participant_user_id']]);$participant=$userStmt->fetch(PDO::FETCH_ASSOC);
    if(!$participant)mg_fail('Participant account is not available.',409);
    $contactStmt=$pdo->prepare('SELECT * FROM campaign_contacts WHERE id=? AND merchant_user_id=? LIMIT 1');$contactStmt->execute([(int)$evidence['contact_id'],$merchantId]);$contact=$contactStmt->fetch(PDO::FETCH_ASSOC);
    if(!$contact)mg_fail('Participant contact is not available.',409);
    $partStmt=$pdo->prepare('SELECT * FROM loyalty_quest_participations WHERE id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');$partStmt->execute([(int)$evidence['participation_id'],$merchantId]);$participation=$partStmt->fetch(PDO::FETCH_ASSOC);
    if(!$participation)mg_fail('Quest participation is not available.',409);
    if($decision==='reject'){
        $pdo->prepare("UPDATE loyalty_quest_evidence SET status='rejected',reviewer_user_id=?,review_note=?,rejected_at=NOW(),updated_at=NOW() WHERE id=? AND merchant_user_id=?")
            ->execute([$merchantId,$note!==''?$note:null,(int)$evidence['id'],$merchantId]);
        $pdo->prepare("UPDATE loyalty_quest_participations SET status='rejected',reviewed_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND merchant_user_id=?")
            ->execute([(int)$participation['id'],$merchantId]);
        mg_lqp_event($pdo,$campaign,null,(int)$contact['id'],'quest.evidence_rejected',['participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId,'review_note'=>$note]);
        mg_audit('merchant.loyalty_quest_evidence_rejected','loyalty_quest_evidence',['evidence_id'=>$evidenceId,'participation_id'=>(string)$participation['public_id']],$merchantId);
        $pdo->commit();mg_ok(['evidence_id'=>$evidenceId,'status'=>'rejected','participation_status'=>'rejected'],'Quest evidence rejected.');
    }
    $pdo->prepare("UPDATE loyalty_quest_evidence SET status='verified',reviewer_user_id=?,review_note=?,verified_at=NOW(),updated_at=NOW() WHERE id=? AND merchant_user_id=?")
        ->execute([$merchantId,$note!==''?$note:null,(int)$evidence['id'],$merchantId]);
    $newProgress=min((int)$participation['required_count'],(int)$participation['progress_count']+1);$percent=(int)round(100*$newProgress/max(1,(int)$participation['required_count']));
    $pdo->prepare("UPDATE loyalty_quest_participations SET status='in_progress',progress_count=?,completion_percent=?,reviewed_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND merchant_user_id=?")
        ->execute([$newProgress,$percent,(int)$participation['id'],$merchantId]);
    $partStmt->execute([(int)$participation['id'],$merchantId]);$participation=$partStmt->fetch(PDO::FETCH_ASSOC);
    $reward=null;$status='in_progress';
    if($newProgress>=(int)$participation['required_count']){$reward=mg_lqp_issue_reward($pdo,$campaign,$contact,$participation,$participant);$status='completed';}
    else{mg_lqp_event($pdo,$campaign,null,(int)$contact['id'],'quest.evidence_approved',['participation_id'=>(string)$participation['public_id'],'evidence_id'=>$evidenceId,'progress_count'=>$newProgress]);}
    mg_audit('merchant.loyalty_quest_evidence_approved','loyalty_quest_evidence',['evidence_id'=>$evidenceId,'participation_id'=>(string)$participation['public_id'],'participation_status'=>$status],$merchantId);
    $pdo->commit();mg_ok(['evidence_id'=>$evidenceId,'status'=>'verified','participation_status'=>$status,'progress_count'=>$newProgress,'reward'=>$reward],'Quest evidence approved.');
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','merchant.loyalty_quest_review_failed','Unable to review Loyalty Quest evidence.',['exception_class'=>$error::class],$merchantId);mg_fail('Unable to review Loyalty Quest evidence.',500);}
