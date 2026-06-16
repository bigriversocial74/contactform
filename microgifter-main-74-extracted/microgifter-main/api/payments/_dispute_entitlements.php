<?php
declare(strict_types=1);

function mg_dispute_revoke_entitlements(PDO $pdo,int $orderId,?int $actorUserId=null): int
{
    $stmt=$pdo->prepare("SELECT e.* FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status IN ('active','suspended') FOR UPDATE");
    $stmt->execute([$orderId]);
    $count=0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $entitlement){
        $from=(string)$entitlement['status'];
        $pdo->prepare("UPDATE entitlements SET status='revoked',revoked_at=NOW(),revocation_reason='dispute_lost',updated_at=NOW() WHERE id=? AND status IN ('active','suspended')")
            ->execute([(int)$entitlement['id']]);
        mg_entitlement_event($pdo,(int)$entitlement['id'],'entitlement.revoked',$from,'revoked',$actorUserId,'dispute_lost',[]);
        $count++;
    }
    return $count;
}
