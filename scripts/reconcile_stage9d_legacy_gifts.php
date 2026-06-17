<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
require_once dirname(__DIR__).'/api/microgifts/_operations.php';
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $stmt=$pdo->query("SELECT g.id,g.public_id,g.sender_user_id,g.recipient_user_id,g.status FROM gifts g LEFT JOIN microgift_instances i ON i.legacy_gift_id=g.id WHERE i.id IS NULL");
    $created=0;
    foreach($stmt->fetchAll() as $gift){
        mg_microgift_create_review($pdo,'legacy_unmapped','legacy_gift',(string)$gift['public_id'],'Legacy gift requires compatibility review.',[
            'legacy_gift_id'=>(int)$gift['id'],'user_id'=>$gift['recipient_user_id']?:$gift['sender_user_id'],'priority'=>'normal'
        ],['legacy_status'=>$gift['status']]);
        $created++;
    }
    $mismatch=$pdo->query("SELECT i.id,i.public_id,i.owner_user_id,p.owner_user_id pppm_owner FROM microgift_instances i INNER JOIN pppm_items p ON p.id=i.pppm_item_id WHERE i.owner_user_id IS NOT NULL AND p.owner_user_id IS NOT NULL AND i.owner_user_id<>p.owner_user_id");
    foreach($mismatch->fetchAll() as $row){
        mg_microgift_create_review($pdo,'ownership_mismatch','microgift_instance',(string)$row['public_id'],'Microgift and PPPM ownership require reconciliation.',[
            'instance_id'=>(int)$row['id'],'user_id'=>(int)$row['owner_user_id'],'priority'=>'high'
        ],['microgift_owner'=>(int)$row['owner_user_id'],'pppm_owner'=>(int)$row['pppm_owner']]);
        $created++;
    }
    $pdo->commit();
    echo "Stage 9D compatibility reconciliation completed; {$created} items inspected.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"Stage 9D compatibility reconciliation failed.\n");exit(1);}
