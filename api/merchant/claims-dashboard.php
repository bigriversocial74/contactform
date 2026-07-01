<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';

mg_require_method('GET');
$user=mg_require_permission('merchant.claims.view');
$pdo=mg_db();
$workspace=mg_claim_workspace($pdo,$user);
$merchantId=(int)$user['id'];
$resultFilter=trim((string)($_GET['result']??$_GET['status']??'all'));
$locationFilter=trim((string)($_GET['location']??'all'));
$q=mb_substr(trim((string)($_GET['q']??'')),0,120);

$countStmt=$pdo->prepare("SELECT COUNT(*) total,
        SUM(result='approved') approved,
        SUM(result<>'approved') failed,
        SUM(result='rate_limited') rate_limited,
        SUM(result='invalid_claim_code') invalid_code
    FROM microgift_claim_attempts WHERE merchant_user_id=?");
$countStmt->execute([$merchantId]);
$counts=$countStmt->fetch(PDO::FETCH_ASSOC)?:[];
$redeemedStmt=$pdo->prepare("SELECT COUNT(*) FROM microgift_redemptions WHERE merchant_user_id=? AND status='completed'");
$redeemedStmt->execute([$merchantId]);
$microRedeemed=(int)$redeemedStmt->fetchColumn();
$walletRedeemed=0;
try{
    $walletCount=$pdo->prepare("SELECT COUNT(*) FROM wallet_item_redemptions WHERE merchant_user_id=? AND status='completed'");
    $walletCount->execute([$merchantId]);
    $walletRedeemed=(int)$walletCount->fetchColumn();
}catch(Throwable){$walletRedeemed=0;}
$counts['total']=(int)($counts['total']??0)+$walletRedeemed;
$counts['approved']=(int)($counts['approved']??0)+$walletRedeemed;
$counts['redeemed']=$microRedeemed+$walletRedeemed;

$where=['a.merchant_user_id=?'];
$params=[$merchantId];
if($resultFilter!==''&&$resultFilter!=='all'){
    if($resultFilter==='failed')$where[]="a.result<>'approved'";
    elseif($resultFilter==='approved'||$resultFilter==='redeemed'){$where[]='a.result=?';$params[]='approved';}
    else{$where[]='a.result=?';$params[]=$resultFilter;}
}
if($locationFilter!==''&&$locationFilter!=='all'){
    $where[]='l.public_id=?';
    $params[]=$locationFilter;
}
if($q!==''){
    $needle='%'.$q.'%';
    $where[]='(a.public_id LIKE ? OR i.public_id LIKE ? OR p.public_id LIKE ? OR t.name LIKE ? OR r.public_id LIKE ?)';
    array_push($params,$needle,$needle,$needle,$needle,$needle);
}

$sql="SELECT a.public_id attempt_id,a.result,a.reason_code,a.correlation_id,a.attempted_at,
        i.public_id instance_id,i.status instance_status,i.face_value_cents,i.currency,i.title_snapshot,
        i.owner_user_id,i.recipient_user_id,COALESCE(i.owner_user_id,i.recipient_user_id) customer_user_id,
        p.public_id pppm_id,l.public_id location_id,l.name location_name,
        r.public_id redemption_id,r.status redemption_status,r.redeemed_at,
        r.amount_cents redemption_amount_cents,r.currency redemption_currency,
        a.actor_user_id,'microgift' source_type
    FROM microgift_claim_attempts a
    LEFT JOIN microgift_instances i ON i.id=a.instance_id
    LEFT JOIN microgift_templates t ON t.id=i.template_id
    LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
    LEFT JOIN merchant_locations l ON l.id=a.location_id
    LEFT JOIN microgift_redemptions r ON r.claim_attempt_id=a.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY a.attempted_at DESC,a.id DESC LIMIT 500";
$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$attempts=$stmt->fetchAll(PDO::FETCH_ASSOC);

$walletAttempts=[];
try{
    $wWhere=["wr.merchant_user_id=?","wr.status='completed'"];
    $wParams=[$merchantId];
    if($resultFilter!==''&&$resultFilter!=='all'&&!in_array($resultFilter,['approved','redeemed'],true)){
        $wWhere[]='1=0';
    }
    if($locationFilter!==''&&$locationFilter!=='all'){
        $wWhere[]='ml.public_id=?';
        $wParams[]=$locationFilter;
    }
    if($q!==''){
        $needle='%'.$q.'%';
        $wWhere[]='(wr.public_id LIKE ? OR wi.public_id LIKE ? OR wi.title_snapshot LIKE ?)';
        array_push($wParams,$needle,$needle,$needle);
    }
    $walletSql="SELECT wr.public_id attempt_id,'approved' result,'wallet_redeemed' reason_code,wr.idempotency_key correlation_id,wr.redeemed_at attempted_at,
            wi.public_id instance_id,wi.status instance_status,wr.amount_cents face_value_cents,wr.currency,COALESCE(wi.title_snapshot,'Wallet reward') title_snapshot,
            wr.user_id owner_user_id,wr.user_id recipient_user_id,wr.user_id customer_user_id,
            NULL pppm_id,ml.public_id location_id,ml.name location_name,
            wr.public_id redemption_id,wr.status redemption_status,wr.redeemed_at,
            wr.amount_cents redemption_amount_cents,wr.currency redemption_currency,
            wr.user_id actor_user_id,'wallet' source_type
        FROM wallet_item_redemptions wr
        INNER JOIN wallet_items wi ON wi.id=wr.wallet_item_id
        LEFT JOIN merchant_locations ml ON ml.id=wr.location_id
        WHERE ".implode(' AND ',$wWhere)."
        ORDER BY wr.redeemed_at DESC,wr.id DESC LIMIT 500";
    $walletStmt=$pdo->prepare($walletSql);
    $walletStmt->execute($wParams);
    $walletAttempts=$walletStmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}catch(Throwable){$walletAttempts=[];}
$attempts=array_merge($attempts,$walletAttempts);
usort($attempts,static function(array $a,array $b): int{return(strtotime((string)($b['attempted_at']??''))?:0)<=>(strtotime((string)($a['attempted_at']??''))?:0);});
$attempts=array_slice($attempts,0,500);

$locations=$pdo->prepare("SELECT ml.public_id,ml.name,ml.status,ml.is_primary,
        COUNT(DISTINCT mcc.id) code_count,
        SUM(mcc.status='active') active_codes
    FROM merchant_locations ml
    LEFT JOIN merchant_claim_codes mcc ON mcc.location_id=ml.id AND mcc.merchant_user_id=?
    WHERE ml.workspace_id=? AND ml.merchant_user_id=?
    GROUP BY ml.id ORDER BY ml.is_primary DESC,ml.name");
$locations->execute([$merchantId,(int)$workspace['id'],$merchantId]);

$codes=$pdo->prepare("SELECT mcc.public_id,mcc.label,mcc.code_last4,mcc.status,mcc.valid_from,mcc.valid_until,
        mcc.usage_limit,mcc.usage_count,ml.public_id location_id,ml.name location_name
    FROM merchant_claim_codes mcc
    INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
    WHERE mcc.merchant_user_id=? AND ml.workspace_id=?
    ORDER BY ml.name,mcc.label,mcc.id");
$codes->execute([$merchantId,(int)$workspace['id']]);

$exceptions=$pdo->prepare("SELECT e.public_id,e.trigger_type exception_type,e.status,e.severity priority,e.summary,
        e.created_at,i.public_id instance_id,l.public_id location_id,l.name location_name
    FROM microgift_claim_escalations e
    LEFT JOIN microgift_instances i ON i.id=e.instance_id
    LEFT JOIN merchant_locations l ON l.id=e.location_id
    WHERE e.merchant_user_id=? AND e.status NOT IN ('resolved','closed')
    ORDER BY FIELD(e.severity,'critical','high','medium','low'),e.updated_at DESC LIMIT 50");
$exceptions->execute([$merchantId]);

mg_ok([
    'counts'=>[
        'total'=>(int)($counts['total']??0),
        'approved'=>(int)($counts['approved']??0),
        'failed'=>(int)($counts['failed']??0),
        'redeemed'=>(int)($counts['redeemed']??0),
        'rate_limited'=>(int)($counts['rate_limited']??0),
        'invalid_code'=>(int)($counts['invalid_code']??0),
    ],
    'attempts'=>$attempts,
    'locations'=>$locations->fetchAll(PDO::FETCH_ASSOC),
    'claim_codes'=>$codes->fetchAll(PDO::FETCH_ASSOC),
    'exceptions'=>$exceptions->fetchAll(PDO::FETCH_ASSOC),
]);
