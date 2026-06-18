<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

mg_require_method('POST');
$user=mg_require_permission('distribution.allocations.manage');
$input=mg_input();mg_require_csrf_for_write($input);
$programId=trim((string)($input['program_id']??''));$winnerCount=max(1,min(10000,(int)($input['winner_count']??1)));
$pdo=mg_db();$pdo->beginTransaction();
try{
 $program=mg_distribution_program_for_update($pdo,(int)$user['id'],$programId);
 if(!in_array((string)$program['program_type'],['contest','giveaway','fundraiser','gaming','other'],true))mg_fail('This program does not support winner selection.',409);
 if(!mg_distribution_program_is_open($program))mg_fail('Distribution program is not active.',409);
 $stmt=$pdo->prepare("SELECT id,public_id,entries_count FROM distribution_recipients WHERE program_id=? AND eligibility_status='eligible' ORDER BY id FOR UPDATE");$stmt->execute([(int)$program['id']]);$eligible=$stmt->fetchAll();if(count($eligible)<$winnerCount)mg_fail('Not enough eligible recipients.',409);
 $seed=bin2hex(random_bytes(32));$ranked=[];
 foreach($eligible as $recipient){$best=null;for($i=0;$i<max(1,(int)$recipient['entries_count']);$i++){$score=hash_hmac('sha256',(string)$recipient['public_id'].'|'.$i,$seed);if($best===null||strcmp($score,$best)<0)$best=$score;}$ranked[]=['id'=>(int)$recipient['id'],'public_id'=>$recipient['public_id'],'score'=>$best];}
 usort($ranked,static fn(array $a,array $b):int=>strcmp((string)$a['score'],(string)$b['score']));$winners=array_slice($ranked,0,$winnerCount);
 $update=$pdo->prepare("UPDATE distribution_recipients SET eligibility_status='selected',eligibility_reason='deterministic_random_selection',updated_at=NOW() WHERE id=?");foreach($winners as $winner)$update->execute([$winner['id']]);
 $proof=['algorithm'=>'hmac_sha256_lowest_score','seed_commitment'=>hash('sha256',$seed),'eligible_count'=>count($eligible),'winner_count'=>$winnerCount,'selected'=>array_map(static fn(array $w):array=>['recipient_id'=>$w['public_id'],'score'=>$w['score']],$winners)];
 $pdo->commit();mg_audit('distribution.winners_selected','distribution_program',['program_id'=>$programId,'proof'=>$proof],(int)$user['id']);mg_ok(['program_id'=>$programId,'winners'=>array_column($winners,'public_id'),'selection_proof'=>$proof],'Winners selected.',201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to select winners.',500);}
