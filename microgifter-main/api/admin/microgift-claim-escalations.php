<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_claim_operations.php';

$user = mg_require_permission('microgift.claim_escalations.manage');
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $status = trim((string)($_GET['status'] ?? 'open'));
    $severity = trim((string)($_GET['severity'] ?? ''));
    $sql = "SELECT e.public_id,e.trigger_type,e.severity,e.status,e.attempt_count,e.summary,e.first_seen_at,e.last_seen_at,e.resolved_at,
                   i.public_id instance_id,l.public_id location_id,l.name location_name,r.public_id review_id
            FROM microgift_claim_escalations e
            LEFT JOIN microgift_instances i ON i.id=e.instance_id
            LEFT JOIN merchant_locations l ON l.id=e.location_id
            LEFT JOIN microgift_review_items r ON r.id=e.review_item_id
            WHERE (?='all' OR e.status=?)";
    $params = [$status,$status];
    if ($severity !== '') {$sql .= ' AND e.severity=?';$params[]=$severity;}
    $sql .= " ORDER BY FIELD(e.severity,'critical','high','normal','low'),e.last_seen_at DESC LIMIT 250";
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    mg_ok(['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$publicId = trim((string)($input['escalation_id'] ?? ''));
$action = trim((string)($input['action'] ?? ''));
if ($publicId==='' || !in_array($action,['start','resolve','dismiss'],true)) mg_fail('Invalid escalation action.',422);

$pdo->beginTransaction();
try {
    $stmt=$pdo->prepare('SELECT * FROM microgift_claim_escalations WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Escalation not found.');
    $status=['start'=>'in_review','resolve'=>'resolved','dismiss'=>'dismissed'][$action];
    $pdo->prepare("UPDATE microgift_claim_escalations SET status=?,resolved_at=IF(? IN ('resolved','dismissed'),NOW(),resolved_at),updated_at=NOW() WHERE id=?")
        ->execute([$status,$status,(int)$row['id']]);
    if (!empty($row['review_item_id'])) {
        $reviewStatus = $status === 'in_review' ? 'in_review' : $status;
        $pdo->prepare('UPDATE microgift_review_items SET status=?,assigned_user_id=COALESCE(assigned_user_id,?),resolved_by_user_id=IF(? IN (\'resolved\',\'dismissed\'),?,resolved_by_user_id),resolved_at=IF(? IN (\'resolved\',\'dismissed\'),NOW(),resolved_at),updated_at=NOW() WHERE id=?')
            ->execute([$reviewStatus,(int)$user['id'],$reviewStatus,(int)$user['id'],$reviewStatus,(int)$row['review_item_id']]);
    }
    mg_audit('microgift.claim_escalation_'.$action,'microgift_claim_escalation',['escalation_id'=>$publicId,'status'=>$status],(int)$user['id']);
    $pdo->commit();
    mg_ok(['escalation_id'=>$publicId,'status'=>$status]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to update escalation.',409);
}
