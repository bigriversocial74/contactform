<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
mg_require_method('GET');
$user=mg_require_api_user();$pdo=mg_db();
$status=trim((string)($_GET['status']??'all'));
if(!in_array($status,['all','joined','in_progress','pending_review','completed','rejected','cancelled'],true))$status='all';
$sql="SELECT lqp.public_id,lqp.status,lqp.progress_count,lqp.required_count,lqp.completion_percent,lqp.joined_at,lqp.started_at,lqp.submitted_at,lqp.completed_at,lqp.last_activity_at,
 c.public_id campaign_public_id,c.public_slug,c.title,c.description,c.ends_at,c.rules_json,
 COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name,
 rt.title reward_title,wi.public_id wallet_item_public_id,wi.status wallet_item_status,wi.expires_at wallet_expires_at,
 (SELECT COUNT(*) FROM loyalty_quest_evidence lqe WHERE lqe.participation_id=lqp.id) evidence_count
 FROM loyalty_quest_participations lqp
 INNER JOIN campaigns c ON c.id=lqp.campaign_id AND c.campaign_type='loyalty_quest'
 INNER JOIN users u ON u.id=lqp.merchant_user_id
 LEFT JOIN public_profiles pp ON pp.user_id=lqp.merchant_user_id
 LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=lqp.merchant_user_id
 LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
 LEFT JOIN wallet_items wi ON wi.id=lqp.wallet_item_id
 WHERE lqp.participant_user_id=?";
$params=[(int)$user['id']];if($status!=='all'){$sql.=' AND lqp.status=?';$params[]=$status;}$sql.=' ORDER BY lqp.last_activity_at DESC,lqp.id DESC LIMIT 100';
try{$stmt=$pdo->prepare($sql);$stmt->execute($params);$items=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$rules=json_decode((string)($row['rules_json']??''),true);if(!is_array($rules))$rules=[];$ref=(string)($row['public_slug']?:$row['campaign_public_id']);$items[]=['id'=>(string)$row['public_id'],'status'=>(string)$row['status'],'progress_count'=>(int)$row['progress_count'],'required_count'=>(int)$row['required_count'],'completion_percent'=>(int)$row['completion_percent'],'joined_at'=>$row['joined_at'],'submitted_at'=>$row['submitted_at'],'completed_at'=>$row['completed_at'],'last_activity_at'=>$row['last_activity_at'],'evidence_count'=>(int)$row['evidence_count'],'quest'=>['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['title'],'description'=>(string)($row['description']??''),'action_type'=>(string)($rules['action_type']??''),'verification_type'=>(string)($rules['verification_type']??''),'ends_at'=>$row['ends_at'],'url'=>'/loyalty-quest.php?campaign='.rawurlencode($ref)],'merchant'=>['name'=>(string)$row['merchant_name']],'reward'=>['title'=>$row['reward_title']??null,'wallet_item_id'=>$row['wallet_item_public_id']??null,'wallet_status'=>$row['wallet_item_status']??null,'expires_at'=>$row['wallet_expires_at']??null]];}$totals=['total'=>count($items),'in_progress'=>0,'pending_review'=>0,'completed'=>0];foreach($items as $item){if(isset($totals[$item['status']]))$totals[$item['status']]++;}mg_ok(['participations'=>$items,'totals'=>$totals,'schema_ready'=>true]);}catch(Throwable $error){mg_security_log('warning','account.loyalty_quests.unavailable','Participant quest portfolio is unavailable.',['exception_class'=>$error::class],(int)$user['id']);mg_ok(['participations'=>[],'totals'=>['total'=>0,'in_progress'=>0,'pending_review'=>0,'completed'=>0],'schema_ready'=>false],'Quest portfolio is temporarily unavailable.');}
