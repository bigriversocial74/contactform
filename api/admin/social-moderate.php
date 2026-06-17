<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/social/_social.php';
mg_require_method('POST');
$user=mg_require_permission('social.moderate');
$input=mg_input();mg_require_csrf_for_write($input);
$reportId=trim((string)($input['report_id']??''));$decision=trim((string)($input['decision']??''));$note=mb_substr(trim((string)($input['resolution_note']??'')),0,1000);
if($reportId===''||!in_array($decision,['dismiss','hide','remove','resolve'],true))mg_fail('Report and valid moderation decision are required.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare("SELECT * FROM social_reports WHERE public_id=? AND status IN ('open','reviewing') LIMIT 1 FOR UPDATE");$stmt->execute([$reportId]);$report=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$report)throw new RuntimeException('Open report not found.');
    $status=$decision==='dismiss'?'dismissed':'resolved';
    if($report['subject_type']==='post'){
        $moderation=match($decision){'hide'=>'hidden','remove'=>'removed',default=>'clear'};
        $pdo->prepare('UPDATE feed_posts SET moderation_status=?,updated_at=NOW() WHERE public_id=?')->execute([$moderation,$report['subject_reference']]);
    }elseif($report['subject_type']==='comment'){
        $commentStatus=match($decision){'hide'=>'hidden','remove'=>'removed',default=>'visible'};
        $pdo->prepare('UPDATE feed_post_comments SET status=?,updated_at=NOW() WHERE public_id=?')->execute([$commentStatus,$report['subject_reference']]);
    }
    $pdo->prepare('UPDATE social_reports SET status=?,reviewed_by_user_id=?,resolution_note=?,reviewed_at=NOW() WHERE id=?')->execute([$status,(int)$user['id'],$note,(int)$report['id']]);
    $pdo->commit();
    mg_audit('social.report_moderated','social_report',['report_id'=>$reportId,'decision'=>$decision,'subject_type'=>$report['subject_type'],'subject_reference'=>$report['subject_reference']],(int)$user['id']);
    mg_ok(['report_id'=>$reportId,'status'=>$status,'decision'=>$decision],'Report resolved.');
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),404);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to moderate report.',500);}
