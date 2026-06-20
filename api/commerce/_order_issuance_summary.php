<?php
declare(strict_types=1);

function mg_order_issuance_summary(PDO $pdo,array $order,int $buyerUserId): array
{
    $expected=$pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM commerce_order_items WHERE order_id=?');
    $expected->execute([(int)$order['id']]);
    $expectedUnits=(int)$expected->fetchColumn();

    $pppm=$pdo->prepare(
        'SELECT COUNT(*) FROM pppm_items pi
         INNER JOIN commerce_order_items oi ON oi.public_id=pi.source_line_reference
         WHERE pi.source_reference=? AND oi.order_id=?'
    );
    $pppm->execute([(string)$order['public_id'],(int)$order['id']]);
    $pppmItems=(int)$pppm->fetchColumn();

    $microgifts=$pdo->prepare(
        'SELECT COUNT(*) FROM microgift_instances mi
         INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id
         WHERE oi.order_id=?'
    );
    $microgifts->execute([(int)$order['id']]);
    $microgiftItems=(int)$microgifts->fetchColumn();

    $inbox=$pdo->prepare(
        "SELECT COUNT(*) FROM microgift_inbox_items inbox
         INNER JOIN microgift_instances mi ON mi.id=inbox.instance_id
         INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id
         WHERE oi.order_id=? AND inbox.user_id=? AND inbox.folder='inbox'"
    );
    $inbox->execute([(int)$order['id'],$buyerUserId]);
    $inboxItems=(int)$inbox->fetchColumn();

    return [
        'expected_units'=>$expectedUnits,
        'pppm_items'=>$pppmItems,
        'microgifts'=>$microgiftItems,
        'inbox_items'=>$inboxItems,
        'complete'=>$expectedUnits>0
            && $pppmItems===$expectedUnits
            && $microgiftItems===$expectedUnits
            && $inboxItems===$expectedUnits,
    ];
}
