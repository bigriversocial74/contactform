<?php
declare(strict_types=1);

require_once __DIR__ . '/_payments.php';

function mg_checkout_column_exists(PDO $pdo,string $table,string $column): bool
{
    try{
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table,$column]);
        return (int)$stmt->fetchColumn()>0;
    }catch(Throwable){
        return false;
    }
}

function mg_checkout_session_payment_intent(PDO $pdo,array $checkout): ?array
{
    if(mg_checkout_column_exists($pdo,'checkout_sessions','payment_intent_id')&&!empty($checkout['payment_intent_id'])){
        $stmt=$pdo->prepare('SELECT * FROM payment_intents WHERE id=? AND order_id=? LIMIT 1');
        $stmt->execute([(int)$checkout['payment_intent_id'],(int)$checkout['order_id']]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if($row)return $row;
    }
    $stmt=$pdo->prepare('SELECT * FROM payment_intents WHERE order_id=? AND provider_key=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([(int)$checkout['order_id'],(string)$checkout['provider_key']]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_checkout_session_safe_provider_url(mixed $value,string $provider): string
{
    $url=trim((string)$value);
    if($url==='')return '';
    if(str_starts_with($url,'/')&&!str_starts_with($url,'//'))return $url;
    if(filter_var($url,FILTER_VALIDATE_URL)===false)return '';
    $parts=parse_url($url);
    if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https')return '';
    $host=strtolower((string)($parts['host']??''));
    if($provider==='stripe'&&($host==='checkout.stripe.com'||str_ends_with($host,'.checkout.stripe.com')))return $url;
    return '';
}

mg_require_method('GET');
$user=mg_require_api_user();
$id=trim((string)($_GET['id']??''));
if($id==='')mg_fail('Checkout session is required.',422);

$pdo=mg_db();
try{
    $checkoutStmt=$pdo->prepare('SELECT * FROM checkout_sessions WHERE public_id=? LIMIT 1');
    $checkoutStmt->execute([$id]);
    $checkout=$checkoutStmt->fetch(PDO::FETCH_ASSOC);
    if(!$checkout)mg_fail('Checkout session not found for this account.',404);

    $orderStmt=$pdo->prepare(
        "SELECT co.*,
                COALESCE(NULLIF(mw.display_name,''),NULLIF(pp.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),'Merchant') merchant_name
         FROM commerce_orders co
         INNER JOIN users u ON u.id=co.merchant_user_id
         LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=u.id
         LEFT JOIN public_profiles pp ON pp.user_id=u.id
         WHERE co.id=? AND co.buyer_user_id=? LIMIT 1"
    );
    $orderStmt->execute([(int)$checkout['order_id'],(int)$user['id']]);
    $order=$orderStmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)mg_fail('Checkout session not found for this account.',404);

    $intent=mg_checkout_session_payment_intent($pdo,$checkout);
    if(!$intent)mg_fail('Payment intent not found for this checkout session.',404);

    $expires=(string)($checkout['expires_at']??'');
    $sessionStatus=(string)($checkout['status']??'open');
    $expired=$expires!==''&&strtotime($expires)!==false&&strtotime($expires)<=time();
    if($expired&&in_array($sessionStatus,['created','open'],true))$sessionStatus='expired';

    $provider=(string)($checkout['provider_key']??$intent['provider_key']??'');
    $intentStatus=(string)($intent['status']??'created');
    $localProvider=in_array($provider,['sandbox','cash'],true);
    $canConfirm=$localProvider
        &&in_array($sessionStatus,['created','open'],true)
        &&(string)$order['payment_status']==='unpaid'
        &&!in_array($intentStatus,['failed','cancelled','succeeded'],true);
    $providerUrl=mg_checkout_session_safe_provider_url($checkout['provider_checkout_url']??'', $provider);
    $canContinue=$providerUrl!==''
        &&in_array($sessionStatus,['created','open'],true)
        &&(string)$order['payment_status']==='unpaid';

    $session=[
        'session_id'=>(string)$checkout['public_id'],
        'session_status'=>$sessionStatus,
        'provider_key'=>$provider,
        'provider_session_reference'=>(string)($checkout['provider_session_reference']??''),
        'expires_at'=>$expires,
        'payment_intent_id'=>(string)($intent['public_id']??''),
        'payment_intent_status'=>$intentStatus,
        'order_id'=>(string)$order['public_id'],
        'currency'=>(string)$order['currency'],
        'subtotal_cents'=>(int)$order['subtotal_cents'],
        'tax_cents'=>(int)$order['tax_cents'],
        'discount_cents'=>(int)$order['discount_cents'],
        'platform_fee_cents'=>(int)$order['platform_fee_cents'],
        'total_cents'=>(int)$order['total_cents'],
        'payment_status'=>(string)$order['payment_status'],
        'fulfillment_status'=>(string)$order['fulfillment_status'],
        'merchant_name'=>(string)$order['merchant_name'],
        'can_confirm'=>$canConfirm,
        'can_confirm_cash'=>$provider==='cash'&&$canConfirm,
        'checkout_url'=>$providerUrl,
        'can_continue_provider'=>$canContinue,
        'refresh_after_ms'=>(string)$order['payment_status']==='unpaid'&&in_array($sessionStatus,['created','open'],true)?5000:0,
    ];

    $items=$pdo->prepare('SELECT title_snapshot,quantity,unit_amount_cents,line_total_cents,currency FROM commerce_order_items WHERE order_id=? ORDER BY id');
    $items->execute([(int)$order['id']]);
    mg_ok(['session'=>$session,'items'=>$items->fetchAll(PDO::FETCH_ASSOC)]);
}catch(Throwable $error){
    mg_security_log('error','commerce.checkout_session_load_failed','Checkout session load failed.',[
        'session_id'=>$id,
        'exception_type'=>get_class($error),
    ],(int)$user['id']);
    mg_fail('Unable to load checkout session. Please return to the cart and resume checkout.',500);
}
