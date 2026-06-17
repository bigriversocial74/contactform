<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CartCheckoutWiringContractTest extends TestCase
{
    public function testCartPageIsServerCartCheckoutPage(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/cart.php');
        self::assertIsString($source);

        foreach([
            'data-cart-page',
            'data-cart-items',
            'data-cart-summary',
            'data-cart-refresh',
            'data-cart-checkout',
            'data-cart-clear',
            'Checkout uses server-side cart totals, frozen checkout drafts, idempotent order creation, and provider-safe payment sessions.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testCartJavascriptUsesServerCartAndCheckoutPipeline(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/cart.js');
        self::assertIsString($source);

        foreach([
            "C().api('GET','/api/commerce/cart.php')",
            "C().api('PATCH','/api/commerce/cart-item.php'",
            "C().api('DELETE','/api/commerce/cart-item.php'",
            "C().api('DELETE','/api/commerce/cart.php'",
            'C().createCheckoutFromCart()'
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringContainsString('window.location.href=flow.session.checkout_url',$source);
    }

    public function testCommerceHelperCreatesDraftOrderAndCheckoutSession(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/customer-commerce.js');
        self::assertIsString($source);

        foreach([
            "api('POST','/api/commerce/cart-items.php'",
            "api('POST','/api/commerce/checkout-draft.php'",
            "idempotency_key:'draft:'+uuid()",
            "api('POST','/api/commerce/orders.php'",
            "idempotency_key:'order:'+uuid()",
            "api('POST','/api/payments/order-checkout-session.php'",
            "idempotency_key:'payment:'+uuid()",
            "success_url:safePath('/checkout-success.php','/checkout-success.php')",
            "cancel_url:safePath('/cart.php','/cart.php')",
            'safeCheckoutUrl(sessionData.checkout_url)',
            'safePath:safePath',
            'safeCheckoutUrl:safeCheckoutUrl',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testCartEndpointsUseServerSideStateAndCsrfForWrites(): void
    {
        $cart=file_get_contents(dirname(__DIR__,2).'/api/commerce/cart.php');
        $items=file_get_contents(dirname(__DIR__,2).'/api/commerce/cart-items.php');
        $item=file_get_contents(dirname(__DIR__,2).'/api/commerce/cart-item.php');
        self::assertIsString($cart);
        self::assertIsString($items);
        self::assertIsString($item);

        foreach([
            'mg_cart_active($pdo,(int)$user[\'id\'])',
            'mg_require_csrf_for_write($input)',
            'DELETE FROM cart_items WHERE cart_id=?',
            'mg_cart_recalculate($pdo,(int)$cart[\'id\'])',
        ] as $needle){
            self::assertStringContainsString($needle,$cart);
        }

        foreach([
            'mg_require_method(\'POST\')',
            'mg_require_csrf_for_write($input)',
            'mg_resolve_published_product_version($pdo,$versionId)',
            'Cart items must use one currency.',
            'Cart currently supports one merchant.',
            'mg_cart_recalculate($pdo,(int)$cart[\'id\'])',
        ] as $needle){
            self::assertStringContainsString($needle,$items);
        }

        foreach([
            "if(!in_array(\$method,['PATCH','DELETE'],true))mg_fail('Method not allowed.',405)",
            'mg_require_csrf_for_write($input)',
            'Cart item not found.',
            'Quantity must be between 1 and 100.',
            'mg_cart_recalculate($pdo,(int)$cart[\'id\'])',
        ] as $needle){
            self::assertStringContainsString($needle,$item);
        }
    }

    public function testCheckoutDraftOrderAndPaymentEndpointsAreIdempotentAndServerSide(): void
    {
        $draft=file_get_contents(dirname(__DIR__,2).'/api/commerce/checkout-draft.php');
        $orders=file_get_contents(dirname(__DIR__,2).'/api/commerce/orders.php');
        $session=file_get_contents(dirname(__DIR__,2).'/api/payments/order-checkout-session.php');
        self::assertIsString($draft);
        self::assertIsString($orders);
        self::assertIsString($session);

        foreach([
            'mg_require_method(\'POST\')',
            'mg_require_csrf_for_write($input)',
            'A valid idempotency key is required.',
            'Cart is empty.',
            'Checkout currently supports one merchant.',
            'checkout_drafts WHERE buyer_user_id=? AND idempotency_key=?',
            'Checkout draft idempotency key is already bound to a different cart snapshot.',
        ] as $needle){
            self::assertStringContainsString($needle,$draft);
        }

        foreach([
            'mg_require_csrf_for_write($input)',
            'mg_checkout_create_order($pdo,(int)$user[\'id\'],$draftId,$idempotency)',
            'commerce.order_created',
        ] as $needle){
            self::assertStringContainsString($needle,$orders);
        }

        foreach([
            'mg_require_method(\'POST\')',
            'commerce.checkout.create',
            'mg_require_csrf_for_write($input)',
            'mg_payment_create_checkout_session',
            'commerce.payment_session_created',
        ] as $needle){
            self::assertStringContainsString($needle,$session);
        }
    }
}
