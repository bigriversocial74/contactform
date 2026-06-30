<?php
declare(strict_types=1);

require_once __DIR__ . '/_payments.php';

function mg_checkout_column_exists(PDO $pdo,string $table,string $column): bool
{
    try{
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table,$column]);
        return (int)$stmt->fetchColumn()>0;
    }catch(Throwable $error){
        return false;
    }
}

function mg_checkout_select_expr(PDO $pdo,string $table,string $column,string $expr,string $fallback): string
{
    return mg_checkout_column_exists($pdo,$table,$column)?$expr.' '.$column:$fallback.' '.$column;
}

function mg_checkout_session_query(PDO $pdo): string
{
    $hasPaymentIntentId=mg_checkout_column_exists($pdo,'checkout_sessions','payment_intent_id');
    $providerSession=mg_checkout_select_expr($pdo,'checkout_sessions','provider_session_reference','cs.provider_session_reference',"''");
    $expires=mg_checkout_select_expr($pdo,'checkout_sessions','expires_at','cs.expires_at','NULL');
    if($hasPaymentIntentId){
        return "SELECT cs.public_id session_id,cs.status session_status,cs.provider_key,
                       {$providerSession},{$expires},
                       COALESCE(pi_link.public_id,pi_order.public_id,'') payment_intent_id,
                       COALESCE(pi_link.status,pi_order.status,'created') payment_intent_status,
                       o.public_id order_id,o.currency,o.subtotal_cents,o.tax_cents,o.discount_cents,
                       o.platform_fee_cents,o.total_cents,o.payment_status,o.fulfillment_status,
                       o.merchant_user_id,'' merchant_name
                FROM checkout_sessions cs
                INNER JOIN commerce_orders o ON o.id=cs.order_id
                LEFT JOIN payment_intents pi_link ON pi_link.id=cs.payment_intent_id AND pi_link.order_id=cs.order_id
                LEFT JOIN payment_intents pi_order ON pi_order.order_id=cs.order_id AND pi_order.provider_key=cs.provider_key
                WHERE cs.public_id=? AND o.buyer_user_id=?
                ORDER BY COALESCE(pi_link.id,pi_order.id,0) DESC
                LIMIT 1";
    }
    return "SELECT cs.public_id session_id,cs.status session_status,cs.provider_key,
                   {$providerSession},{$expires},
                   COALESCE(pi.public_id,'') payment_intent_id,COALESCE(pi.status,'created') payment_intent_status,
                   o.public_id order_id,o.currency,o.subtotal_cents,o.tax_cents,o.discount_cents,
                   o.platform_fee_cents,o.total_cents,o.payment_status,o.fulfillment_status,
                   o.merchant_user_id,'' merchant_name
            FROM checkout_sessions cs
            INNER JOIN commerce_orders o ON o.id=cs.order_id
            LEFT JOIN payment_intents pi ON pi.order_id=cs.order_id AND pi.provider_key=cs.provider_key
            WHERE cs.public_id=? AND o.buyer_user_id=?
            ORDER BY COALESCE(pi.id,0) DESC
            LIMIT 1";
}

mg_require_method('GET');
$user=mg_require_api_user();
$id=trim((string)($_GET['id']??''));
if($id==='')mg_fail('Checkout session is required.',422);

$pdo=mg_db();
try{
    $stmt=$pdo->prepare(mg_checkout_session_query($pdo));
    $stmt->execute([$id,(int)$user['id']]);
    $session=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$session)mg_fail('Checkout session not found for this account.',404);

    $expired=!empty($session['expires_at'])&&strtotime((string)$session['expires_at'])<=time();
    if($expired&&in_array((string)$session['session_status'],['created','open'],true)){
        $session['session_status']='expired';
    }
    $localProvider=in_array((string)$session['provider_key'],['sandbox','cash'],true);
    $session['can_confirm']=(
        $localProvider
        &&in_array((string)$session['session_status'],['created','open'],true)
        &&(string)$session['payment_status']==='unpaid'
        &&!in_array((string)$session['payment_intent_status'],['failed','cancelled','succeeded'],true)
    );
    $session['can_confirm_cash']=$session['provider_key']==='cash'&&$session['can_confirm'];
    $session['checkout_url']='';
    $session['can_continue_provider']=false;

    $items=$pdo->prepare(
        'SELECT title_snapshot,quantity,unit_amount_cents,line_total_cents,currency
         FROM commerce_order_items coi
         INNER JOIN commerce_orders o ON o.id=coi.order_id
         WHERE o.public_id=? AND o.buyer_user_id=?
         ORDER BY coi.id'
    );
    $items->execute([$session['order_id'],(int)$user['id']]);
    mg_ok(['session'=>$session,'items'=>$items->fetchAll(PDO::FETCH_ASSOC)]);
}catch(Throwable $error){
    mg_security_log('error','commerce.checkout_session_load_failed','Checkout session load failed.',[
        'session_id'=>$id,
        'exception_type'=>get_class($error),
    ],(int)$user['id']);
    mg_fail('Unable to load checkout session. Please return to the cart and create a new checkout session.',500);
}
