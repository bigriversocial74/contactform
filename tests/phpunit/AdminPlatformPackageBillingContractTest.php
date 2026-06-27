<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class AdminPlatformPackageBillingContractTest extends TestCase
{
    public function testAdminPackagePageExposesBillingBuilder(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/admin/package-moderation.php');
        self::assertIsString($source);
        foreach ([
            "require_once dirname(__DIR__) . '/api/subscriptions/_package_billing.php'",
            'Stripe package builder',
            'data-platform-package-billing',
            'stripe_product_id_test',
            'stripe_price_id_test',
            'stripe_product_id_live',
            'stripe_price_id_live',
            'MG_ADMIN_PLATFORM_PACKAGES',
            '/assets/js/admin-platform-packages.js',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testAdminPlatformPackageApiSavesStripeIdentifiers(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/admin/platform-packages.php');
        self::assertIsString($source);
        foreach ([
            'mg_require_api_user',
            'mg_require_csrf_for_write',
            'platform_subscription_packages',
            'stripe_price_id_test',
            'stripe_price_id_live',
            'stripe_product_id_test',
            'stripe_product_id_live',
            'admin.commerce.manage',
            'subscriptions.admin',
            'platform_package.billing_saved',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testCheckoutHandoffPrefersConfiguredPackagePrice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_checkout_handoff.php');
        self::assertIsString($source);
        foreach ([
            "require_once __DIR__ . '/_package_billing.php'",
            'mg_platform_package_get',
            'mg_platform_package_stripe_price_id',
            "\$lineItem['price'] = \$priceId;",
            "\$lineItem['price_data']",
            'provider_price_id',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }
}
