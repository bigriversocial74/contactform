<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/api/security.php';

final class SecurityFoundationTest extends TestCase
{
    public function testSensitiveSecurityContextValuesAreRedacted(): void
    {
        $input = [
            'password' => 'plain-text-password',
            'csrf_token' => 'csrf-secret',
            'nested' => [
                'api_key' => 'provider-secret',
                'claim_code' => 'CLAIM-123',
                'safe' => 'visible',
            ],
        ];

        $result = mg_redact_security_context($input);

        self::assertSame('[REDACTED]', $result['password']);
        self::assertSame('[REDACTED]', $result['csrf_token']);
        self::assertSame('[REDACTED]', $result['nested']['api_key']);
        self::assertSame('[REDACTED]', $result['nested']['claim_code']);
        self::assertSame('visible', $result['nested']['safe']);
    }

    public function testBearerAndUrlSecretsAreRedactedFromStrings(): void
    {
        $value = 'Bearer abc.def.ghi https://user:pass@example.test/path?token=secret&safe=yes';
        $result = mg_redact_security_context($value);

        self::assertStringContainsString('Bearer [REDACTED]', $result);
        self::assertStringContainsString('://[REDACTED]:[REDACTED]@', $result);
        self::assertStringContainsString('token=[REDACTED]', $result);
        self::assertStringNotContainsString('abc.def.ghi', $result);
        self::assertStringNotContainsString('user:pass', $result);
    }

    public function testSessionValidationSourceFailsClosed(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/security.php');

        self::assertIsString($source);
        self::assertStringContainsString('session.validate_failed_closed', $source);
        self::assertMatchesRegularExpression(
            '/function\s+mg_session_is_active.*?catch\s*\(Throwable.*?return\s+false;/s',
            $source
        );
    }

    public function testRateLimiterSourceFailsClosed(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/security.php');

        self::assertIsString($source);
        self::assertStringContainsString('rate_limit.failed_closed', $source);
        self::assertStringContainsString("mg_fail('Security service temporarily unavailable. Please try again shortly.', 503);", $source);
    }

    public function testLoginRedirectsToGiftInbox(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/auth/login.php');

        self::assertIsString($source);
        self::assertMatchesRegularExpression("/'redirect'\s*=>\s*'\/inbox\.php'/", $source);
        self::assertDoesNotMatchRegularExpression("/'redirect'\s*=>\s*'\/account\.php'/", $source);
    }

    public function testMigrationRunnerUsesExistingMigrationKeySchema(): void
    {
        $runner = file_get_contents(dirname(__DIR__, 2) . '/scripts/run_migrations.php');
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/stage_1_security_hardening_03N.sql');

        self::assertIsString($runner);
        self::assertIsString($migration);
        self::assertStringContainsString('migration_key', $runner);
        self::assertStringContainsString('migration_key', $migration);
        self::assertStringNotContainsString('WHERE migration = ?', $runner);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE checksum = VALUES(checksum)', $runner);
    }

    public function testSessionPermissionMigrationAddsDescriptionColumnBeforeUse(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/stage_1_security_hardening_03N_3.sql');

        self::assertIsString($migration);
        self::assertStringContainsString("COLUMN_NAME = 'description'", $migration);
        self::assertStringContainsString('ALTER TABLE permissions ADD COLUMN description', $migration);
        self::assertStringContainsString('INSERT INTO permissions (slug, name, description, created_at)', $migration);
    }

    public function testPublicHealthSourceDoesNotExposeDependencyDetails(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/health.php');

        self::assertIsString($source);
        self::assertStringNotContainsString("'database' => 'connected'", $source);
        self::assertStringNotContainsString("'runtime' =>", $source);
        self::assertStringContainsString("'status' => 'available'", $source);
    }
}
