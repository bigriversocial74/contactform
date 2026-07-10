<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{
    $content=file_get_contents($root.'/'.$path);
    if(!is_string($content))throw new RuntimeException('Missing '.$path);
    return $content;
};

$foundation=$read('api/commerce/_foundation.php');
$workflow=$read('api/commerce/_cart_checkout.php');
$route=$read('api/commerce/cart-checkout.php');
$sessionFoundation=$read('api/payments/_checkout_session.php');
$sessionApi=$read('api/payments/session.php');
$cartPage=$read('cart.php');
$checkoutPage=$read('checkout.php');
$commerceJs=$read('assets/js/customer-commerce.js');
$cartJs=$read('assets/js/cart.js');
$checkoutJs=$read('assets/js/checkout.js');
$productJs=$read('assets/js/public-product-v1.js');
$css=$read('assets/css/cart-checkout-runtime-v1.css');

$checks=[
    'cart_lifecycle'=>str_contains($foundation,"status='expired'")&&str_contains($foundation,'bool $create = true')&&str_contains($read('api/commerce/cart.php'),'false,false'),
    'current_version_integrity'=>str_contains($workflow,'mg_cart_checkout_validate_current_items')&&str_contains($workflow,'cp.current_version_id<>cpv.id')&&str_contains($foundation,'p.current_version_id=v.id'),
    'customer_cart_snapshot'=>str_contains($foundation,'product_public_id')&&str_contains($foundation,'cover_url')&&str_contains($foundation,"'revision'=>"),
    'resumable_orchestration'=>str_contains($route,'mg_cart_checkout_run')&&str_contains($workflow,"'draft:' . $workflowKey")&&str_contains($workflow,"'order:' . $workflowKey")&&str_contains($workflow,"'payment:' . $provider"),
    'stable_browser_workflow'=>str_contains($commerceJs,'sessionStorage')&&str_contains($commerceJs,'/api/commerce/cart-checkout.php')&&str_contains($cartJs,'createCheckoutFromCart(provider, currentCart.cart_id)'),
    'active_session_recovery'=>str_contains($sessionFoundation,'if($activeSession=$active->fetch')&&str_contains($sessionFoundation,'mg_payment_checkout_session_payload($activeSession'),
    'payment_method_truth'=>str_contains($cartPage,'data-cart-checkout-provider="stripe"')&&str_contains($cartPage,'data-cart-checkout-provider="cash"')&&str_contains($cartJs,'/api/payments/checkout-options.php'),
    'approved_provider_continuation'=>str_contains($commerceJs,"checkout.stripe.com")&&str_contains($sessionApi,'mg_checkout_session_safe_provider_url')&&str_contains($sessionApi,"'can_continue_provider'=>$canContinue"),
    'live_checkout_recovery'=>str_contains($checkoutJs,'visibilitychange')&&str_contains($checkoutJs,"window.addEventListener('online'")&&str_contains($checkoutJs,'refresh_after_ms')&&str_contains($checkoutPage,'aria-busy="true"'),
    'accessible_feedback'=>str_contains($cartJs,"new CustomEvent('mg:cart:error'")&&str_contains($productJs,"'mg:cart:error'")&&str_contains($cartPage,'aria-live="polite"')&&str_contains($css,'@media(max-width:700px)'),
];

$score=0;
foreach($checks as $name=>$passed){echo($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if($passed)$score++;}
echo 'Score: '.$score.'/10'.PHP_EOL;
exit($score===10?0:1);
