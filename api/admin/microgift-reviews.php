<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/microgifts/_operations.php';
$user=mg_require_permission('microgift.reviews.manage');
$pdo=mg_db();
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
if($method==='GET'){
    $status=trim((string)($_GET['status']??'open'));
    $stmt=$pdo->prepare("SELECT r.public_id,r.review_type,r.status,r.priority,r.summary,r.source_type,r.source_reference,r.resolution_note,r.created_at,r.updated_at,
        i.public_id instance_id,g.public_id legacy_gift_id
        FROM microgift_review_items r
        LEFT JOIN microgift_instances i ON i.id=r.instance_id
        LEFT JOIN gifts g ON g.id=r.legacy_gift_id
        WHERE (?='all' OR r.status=?) ORDER BY FIELD(r.priority,'critical','high','normal','low'),r.created_at ASC LIMIT 250");
    $stmt->execute([$status,$status]);
    mg_ok(['status'=>$status,'items'=>$stmt->fetchAll()]);
}
mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);
$reviewId=trim((string)($input['review_id']??''));
$action=trim((string)($input['action']??''));
if($reviewId===''||!in_array($action,['start','resolve','dismiss'],true))mg_fail('Invalid review action.',422);
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare('SELECT * FROM microgift_review_items WHERE public_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$reviewId]);$review=$stmt->fetch();
    if(!$review)throw new RuntimeException('Review item not found.');
    $status=['start'=>'in_review','resolve'=>'resolved','dismiss'=>'dismissed'][$action];
    $pdo->prepare('UPDATE microgift_review_items SET status=?,assigned_user_id=COALESCE(assigned_user_id,?),resolved_by_user_id=IF(? IN (\'resolved\',\'dismissed\'),?,resolved_by_user_id),resolution_note=?,resolved_at=IF(? IN (\'resolved\',\'dismissed\'),NOW(),resolved_at),updated_at=NOW() WHERE id=?')
        ->execute([$status,(int)$user['id'],$status,(int)$user['id'],trim((string)($input['resolution_note']??''))?:null,$status,(int)$review['id']]);
    mg_audit('microgift.review_'.$action,'microgift_review',['review_id'=>$reviewId,'status'=>$status],(int)$user['id']);
    $pdo->commit();mg_ok(['review_id'=>$reviewId,'status'=>$status]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update review item.',409);}
