<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage6BCustomerAccountCommerceTest extends TestCase
{
    public function testCustomerAccountApisRequireAuthenticatedUser(): void
    {
        foreach (['commerce-summary.php','items.php','gifts.php','claims.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__,2) . '/api/account/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('mg_require_api_user()', $source);
            self::assertStringContainsString("mg_require_method('GET')", $source);
        }
    }

    public function testItemScopesAreStrictlyWhitelisted(): void
    {
        $source = file_get_contents(dirname(__DIR__,2) . '/api/account/items.php');
        self::assertIsString($source);
        foreach (['owned','purchased','sent','received','redeemed'] as $scope) {
            self::assertStringContainsString("'{$scope}'", $source);
        }
        self::assertStringContainsString('mg_account_scope', $source);
        self::assertStringContainsString('mg_account_limit', $source);
    }

    public function testItemQueriesRemainUserScoped(): void
    {
        $source = file_get_contents(dirname(__DIR__,2) . '/api/account/_commerce.php');
        self::assertIsString($source);
        self::assertStringContainsString('i.owner_user_id=?', $source);
        self::assertStringContainsString('i.issuer_user_id=?', $source);
        self::assertStringContainsString('i.recipient_user_id=?', $source);
        self::assertStringContainsString('CASE WHEN o.buyer_user_id=', $source);
        self::assertStringContainsString('THEN o.public_id ELSE NULL END order_id', $source);
    }

    public function testGiftAndClaimQueriesAreParticipantScoped(): void
    {
        $source = file_get_contents(dirname(__DIR__,2) . '/api/account/_commerce.php');
        self::assertIsString($source);
        self::assertStringContainsString("'g.sender_user_id' : 'g.recipient_user_id'", $source);
        self::assertStringContainsString('(c.claimant_user_id=? OR g.recipient_user_id=?)', $source);
    }

    public function testCommerceCenterIntegratesAllCustomerLifecycleViews(): void
    {
        $page = file_get_contents(dirname(__DIR__,2) . '/account-commerce.php');
        self::assertIsString($page);
        foreach (['data-account-commerce-overview','data-account-orders','data-account-items','data-account-gifts','data-account-claims'] as $attribute) {
            self::assertStringContainsString($attribute, $page);
        }
        self::assertStringContainsString('/assets/js/account-commerce.js', $page);
        self::assertStringContainsString('/assets/js/account-orders.js', $page);
    }

    public function testFrontendCallsOnlyCustomerScopedAccountApis(): void
    {
        $source = file_get_contents(dirname(__DIR__,2) . '/assets/js/account-commerce.js');
        self::assertIsString($source);
        self::assertStringContainsString('/api/account/commerce-summary.php', $source);
        self::assertStringContainsString('/api/account/items.php?scope=', $source);
        self::assertStringContainsString('/api/account/gifts.php?scope=', $source);
        self::assertStringContainsString('/api/account/claims.php?status=', $source);
        self::assertStringNotContainsString('/api/merchant/', $source);
        self::assertStringNotContainsString('/api/admin/', $source);
    }

    public function testCommerceCenterIsDiscoverableFromAccountMenuAndCheckoutSuccess(): void
    {
        $root = dirname(__DIR__,2);
        $header = file_get_contents($root . '/includes/header.php');
        $appHeader = file_get_contents($root . '/includes/header-components/app-header.php');
        $loggedInHeader = file_get_contents($root . '/includes/header-templates/logged-in.php');
        $success = file_get_contents($root . '/checkout-success.php');
        self::assertIsString($header);
        self::assertIsString($appHeader);
        self::assertIsString($loggedInHeader);
        self::assertIsString($success);
        self::assertStringContainsString('app-header.php', $header);
        self::assertStringContainsString('logged-in.php', $appHeader);
        self::assertStringContainsString('/account-commerce.php', $loggedInHeader);
        self::assertStringContainsString('/account-commerce.php', $success);
    }
}
