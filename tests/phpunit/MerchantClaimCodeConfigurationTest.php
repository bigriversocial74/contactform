<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantClaimCodeConfigurationTest extends TestCase
{
    public function testClaimCodePepperUsesConfiguredSecretOrPersistentGeneratedSecret(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/_claims.php');

        self::assertIsString($source);
        self::assertStringContainsString("['security']['claim_code_pepper']", $source);
        self::assertStringContainsString("['storage']['root']", $source);
        self::assertStringContainsString("'.secrets'", $source);
        self::assertStringContainsString("'claim-code-pepper'", $source);
        self::assertStringContainsString('bin2hex(random_bytes(32))', $source);
        self::assertStringContainsString("fopen(\$path,'x')", $source);
        self::assertStringContainsString('chmod($path,0600)', $source);
        self::assertStringContainsString("preg_match('/^[a-f0-9]{64}$/i'", $source);
        self::assertStringContainsString('Check the persistent storage directory permissions or configure MG_CLAIM_CODE_PEPPER.', $source);
    }

    public function testLocationSaveStillHashesAndNeverStoresPlaintextClaimCode(): void
    {
        $locations = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/locations.php');

        self::assertIsString($locations);
        self::assertStringContainsString('mg_claim_code_pepper()', $locations);
        self::assertStringContainsString("hash_hmac('sha256',\$claimCode,\$pepper)", $locations);
        self::assertStringNotContainsString('claim_code VARCHAR', $locations);
    }
}
