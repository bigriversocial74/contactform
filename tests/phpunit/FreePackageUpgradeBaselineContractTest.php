<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FreePackageUpgradeBaselineContractTest extends TestCase
{
    public function testPackageChangeDefaultsCurrentPackageToFree(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_changes.php');

        self::assertIsString($source);
        self::assertStringContainsString('string $fallback=\'free\'', $source);
        self::assertStringContainsString('$fallback=\'free\';', $source);
        self::assertStringNotContainsString('string $fallback=\'starter\'', $source);
        self::assertStringNotContainsString('$fallback=\'starter\';', $source);
        self::assertStringContainsString('if($planId===\'free\')return 0;', $source);
    }

    public function testOnlyActivePlatformSubscriptionsCountAsCurrentPaidPackage(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_changes.php');

        self::assertIsString($source);
        self::assertStringContainsString('MG_SUBSCRIPTION_PACKAGE_CHANGE_ACTIVE_STATUSES', $source);
        foreach (['active', 'trialing', 'cancel_pending', 'past_due'] as $status) {
            self::assertStringContainsString($status, $source);
        }
        self::assertStringContainsString('mg_platform_account_subscription_snapshot($pdo,$userId,false)', $source);
        self::assertStringContainsString('in_array((string)($platform[\'status\']??\'\'),MG_SUBSCRIPTION_PACKAGE_CHANGE_ACTIVE_STATUSES,true)', $source);
    }

    public function testLegacySubscriptionsQueryIgnoresInactiveRows(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_changes.php');

        self::assertIsString($source);
        self::assertStringContainsString('s.status IN (\'active\',\'trialing\',\'cancel_pending\',\'past_due\')', $source);
        self::assertStringNotContainsString('\'pending_payment\',\'paused\',\'canceled\',\'expired\'),s.updated_at', $source);
    }
}
