<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class Stage6ACustomerPurchaseFlowTest extends TestCase
{
    private static function sourceTokenForSelector(string $selector): string
    {
        if (preg_match('/^\[([a-zA-Z0-9_-]+)\]$/', $selector, $matches)) {
            return $matches[1];
        }
        return $selector;
    }

    public function testCartPageUsesStableCommerceContracts(): void
    {
        $root=dirname(__DIR__,2);
        $contracts=require $root.'/config/frontend-contracts.php';
        $page=file_get_contents($root.'/cart.php');
        $cart=file_get_contents($root.'/'.$contracts['stable_entrypoints']['cart']['path']);
        $shared=file_get_contents($root.'/'.$contracts['stable_entrypoints']['customer_commerce']['path']);
        self::assertIsString($page);
        self::assertIsString($cart);
        self::assertIsString($shared);
        self::assertStringContainsString(self::sourceTokenForSelector($contracts['dom_contracts']['cart_page']),$page);
        self::assertStringContainsString('/api/commerce/cart.php',$cart);
        self::assertStringContainsString('/api/commerce/cart-item.php',$cart);
        self::assertStringContainsString('/api/commerce/cart-items.php',$shared);
        self::assertStringContainsString('addProductVersion',$cart);
    }

    public function testCustomerCommerceCreatesDraftOrderAndPaymentSessionInOrder(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/customer-commerce.js');
        self::assertIsString($source);
        $draft=strpos($source,'/api/commerce/checkout-draft.php');
        $order=strpos($source,'/api/commerce/orders.php');
        $session=strpos($source,'/api/payments/order-checkout-session.php');
        self::assertIsInt($draft);
        self::assertIsInt($order);
        self::assertIsInt($session);
        self::assertLessThan($order,$draft);
        self::assertLessThan($session,$order);
        self::assertStringContainsString('idempotency_key',$source);
    }

    public function testCheckoutUsesPaymentSessionAndSandboxConfirmOnly(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/checkout.php');
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/checkout.js');
        self::assertIsString($page);
        self::assertIsString($script);
        self::assertStringContainsString('data-session-id',$page);
        self::assertStringContainsString('/api/payments/session.php',$script);
        self::assertStringContainsString('/api/payments/sandbox-confirm.php',$script);
        self::assertStringNotContainsString('product_version_id',$script);
    }

    public function testCheckoutSuccessLoadsReceipt(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/checkout-success.php');
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/order-success.js');
        self::assertIsString($page);
        self::assertIsString($script);
        self::assertStringContainsString('data-order-success',$page);
        self::assertStringContainsString('/api/commerce/receipt.php',$script);
        self::assertStringContainsString('PPPM',$page);
    }

    public function testAccountOrdersPageIsBuyerScopedThroughCustomerEndpoint(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/account/orders.php');
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/account-orders.js');
        self::assertIsString($page);
        self::assertIsString($script);
        self::assertStringContainsString('data-account-orders',$page);
        self::assertStringContainsString('/api/commerce/orders.php',$script);
        self::assertStringContainsString('/checkout-success.php?order=',$script);
    }
}
