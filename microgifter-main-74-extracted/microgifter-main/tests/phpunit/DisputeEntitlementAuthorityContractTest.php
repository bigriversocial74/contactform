<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DisputeEntitlementAuthorityContractTest extends TestCase
{
    public function testLostDisputeRevokesSuspendedEntitlements(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/api/payments/_disputes.php');
        $helper=file_get_contents(dirname(__DIR__,2).'/api/payments/_dispute_entitlements.php');
        self::assertIsString($service);
        self::assertIsString($helper);
        self::assertStringContainsString('mg_dispute_revoke_entitlements(',$service);
        self::assertStringContainsString('entitlement.revoked',$helper);
    }
}
