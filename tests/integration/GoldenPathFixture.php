<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/api/microgifts/_location_claim_authority.php';
require_once __DIR__.'/CheckoutBehaviorFixture.php';

function mg_golden_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function mg_golden_fixture(PDO $pdo,string $runId): array
{
    $buyerEmail=$runId.'-buyer@example.test';
    $merchantEmail=$runId.'-merchant@example.test';
    $otherEmail=$runId.'-other@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Golden Buyer');
    $merchantId=mg_it_user($pdo,$merchantEmail,'Golden Merchant');
    $otherId=mg_it_user($pdo,$otherEmail,'Golden Other');
    $location=mg_it_location($pdo,$merchantId,$runId);
    $workspaceId=(int)mg_golden_scalar($pdo,'SELECT workspace_id FROM merchant_locations WHERE id=?',[$location['id']]);
    $otherLocationPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO merchant_locations (public_id,workspace_id,merchant_user_id,name,location_code,country_code,timezone,status,is_primary,created_at,updated_at) VALUES (?,?,?,'Other Golden Location',?,'US','UTC','active',0,NOW(),NOW())")
        ->execute([$otherLocationPublic,$workspaceId,$merchantId,'OTHER-'.$runId]);

    $catalog=mg_checkout_fixture_catalog($pdo,$merchantId,$runId);
    $pdo->prepare("INSERT INTO catalog_product_version_locations (product_version_id,merchant_location_id,availability_status,is_primary,created_at,updated_at) VALUES (?,?,'available',1,NOW(),NOW())")
        ->execute([$catalog['version_id'],$location['id']]);
    $draft=mg_checkout_fixture_draft($pdo,['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId]+$catalog,'golden');
    $order=mg_checkout_create_order($pdo,$buyerId,$draft['draft_public'],'golden-order-'.$runId);
    $orderPublic=(string)$order['order']['order_id'];
    $orderId=(int)mg_golden_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$orderPublic]);
    $session=mg_payment_create_checkout_session($pdo,$buyerId,$orderPublic,'golden-session-'.$runId);
    $intentId=(int)mg_golden_scalar($pdo,'SELECT id FROM payment_intents WHERE public_id=?',[$session['payment_intent_id']]);
    $capture=mg_finance_record_paid_order($pdo,$orderId,$intentId,'golden-provider-'.$runId,$buyerId);

    $stmt=$pdo->prepare("SELECT mi.*,ac.public_id action_item_public_id,p.public_id pppm_public_id
        FROM microgift_instances mi
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        INNER JOIN microgift_inbox_items ac ON ac.instance_id=mi.id AND ac.user_id=?
        INNER JOIN pppm_items p ON p.id=mi.pppm_item_id
        WHERE oi.order_id=? AND mi.source_type='commerce_order_item'
        ORDER BY mi.id LIMIT 1");
    $stmt->execute([$buyerId,$orderId]);
    $microgift=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$microgift)throw new RuntimeException('Golden Microgift was not found.');

    return compact('buyerEmail','merchantEmail','otherEmail','buyerId','merchantId','otherId','location','otherLocationPublic','catalog','orderId','orderPublic','capture','microgift');
}
