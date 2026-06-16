<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage6CloseoutReconciliationTest extends TestCase
{
    public function testSharedCommerceHelperLoadsBeforeCart(): void
    {
        $footer = file_get_contents(dirname(__DIR__,2) . '/includes/footer.php');
        self::assertIsString($footer);
        self::assertSame(1, substr_count($footer, "'/assets/js/customer-commerce.js'"));
        self::assertLessThan(strpos($footer, "'/assets/js/cart.js'"), strpos($footer, "'/assets/js/customer-commerce.js'"));
    }

    public function testAddToCartHasOneEventOwner(): void
    {
        $root=dirname(__DIR__,2);
        $contracts=require $root.'/config/frontend-contracts.php';
        $shared = file_get_contents($root . '/assets/js/customer-commerce.js');
        $cart = file_get_contents($root . '/' . $contracts['stable_entrypoints']['cart']['path']);
        self::assertIsString($shared);
        self::assertIsString($cart);
        self::assertStringNotContainsString('bindAddToCart', $shared);
        self::assertStringContainsString($contracts['dom_contracts']['cart_add'], $cart);
    }

    public function testCustomerScriptsUseSharedHelpers(): void
    {
        foreach (['account-orders.js','account-commerce.js','checkout.js','order-success.js'] as $file) {
            $source = file_get_contents(dirname(__DIR__,2) . '/assets/js/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('MGCustomerCommerce', $source);
        }
    }

    public function testCheckoutLifecycleOrderIsPreserved(): void
    {
        $source = file_get_contents(dirname(__DIR__,2) . '/assets/js/customer-commerce.js');
        self::assertIsString($source);
        $draft = strpos($source, '/api/commerce/checkout-draft.php');
        $order = strpos($source, '/api/commerce/orders.php');
        $session = strpos($source, '/api/payments/order-checkout-session.php');
        self::assertIsInt($draft);
        self::assertIsInt($order);
        self::assertIsInt($session);
        self::assertLessThan($order, $draft);
        self::assertLessThan($session, $order);
    }

    public function testAccountApisRemainAuthenticated(): void
    {
        foreach (['commerce-summary.php','items.php','gifts.php','claims.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__,2) . '/api/account/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('mg_require_api_user()', $source);
            self::assertStringContainsString("mg_require_method('GET')", $source);
        }
    }

    public function testCloseoutAndHandoffDocsExist(): void
    {
        $closeout = file_get_contents(dirname(__DIR__,2) . '/docs/stage-6-closeout-reconciliation.md');
        $handoff = file_get_contents(dirname(__DIR__,2) . '/docs/stage-7-handoff-from-stage-6.md');
        self::assertIsString($closeout);
        self::assertIsString($handoff);
        self::assertStringContainsString('Stage 6A', $closeout);
        self::assertStringContainsString('Stage 6B', $closeout);
        self::assertStringContainsString('Stage 6C', $closeout);
        self::assertStringContainsString('Stage 7A', $handoff);
    }
}
