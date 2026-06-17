<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10MerchantScopeHardeningTest extends TestCase
{
    public function testAuthenticatedMerchantScopeOverridesRequestPayload(): void
    {
        $root = dirname(__DIR__, 2);
        $operations = file_get_contents($root . '/api/microgifts/_claim_operations.php');

        self::assertIsString($operations);
        self::assertStringContainsString(
            '$claimInput[\'merchant_user_id\'] = $merchantUserId;',
            $operations
        );
        self::assertStringContainsString(
            '$claimInput[\'correlation_id\'] = $correlationId;',
            $operations
        );
        self::assertStringContainsString(
            'mg_microgift_atomic_merchant_redeem($pdo,$actorUserId,$claimInput)',
            $operations
        );
        self::assertStringNotContainsString(
            '$input+[\'merchant_user_id\'=>$merchantUserId',
            $operations
        );
    }
}
