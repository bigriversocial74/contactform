<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendHardeningContractTest extends TestCase
{
    public function testActionCenterStateMutationsRequireParsedInputAndCsrf(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            'api/account/action-center-read.php',
            'api/account/action-center-unread.php',
            'api/account/action-center-archive.php',
            'api/account/action-center-restore.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($root . '/' . $path);
            self::assertIsString($source, $path);
            self::assertStringContainsString("mg_require_method('POST')", $source, $path);
            self::assertStringContainsString('mg_require_api_user()', $source, $path);
            self::assertStringContainsString('mg_input()', $source, $path);
            self::assertStringContainsString('mg_require_csrf_for_write($input)', $source, $path);
            self::assertStringNotContainsString('$_POST', $source, $path);
        }
    }

    public function testMerchantLocationsDoesNotMutateSchemaDuringRequests(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/locations.php');
        self::assertIsString($source);

        self::assertStringContainsString('mg_merchant_locations_has_claim_code', $source);
        self::assertStringContainsString("'schema_ready'=>\$hasClaimCode", $source);
        self::assertStringNotContainsString('ALTER TABLE', $source);
        self::assertStringNotContainsString('mg_merchant_locations_ensure_claim_code', $source);
    }

    public function testStage11hMigrationIsRegisteredOrderedAndExecuted(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/stage_11h_backend_hardening.sql');
        $builder = file_get_contents($root . '/scripts/build_full_upgrade_sql.php');
        $runner = file_get_contents($root . '/scripts/stage11h.php');
        $smoke = file_get_contents($root . '/scripts/stage11h_smoke.php');
        $workflow = file_get_contents($root . '/.github/workflows/pr-validation.yml');

        foreach ([$migration, $builder, $runner, $smoke, $workflow] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('ADD COLUMN claim_code VARCHAR(80) NULL', $migration);
        self::assertStringContainsString('uq_merchant_locations_workspace_claim_code', $migration);
        self::assertStringContainsString('uq_merchant_locations_merchant_claim_code', $migration);
        self::assertStringContainsString('stage_11h_backend_hardening', $migration);

        $addendumPosition = strpos($builder, "'schema_v2_action_center_crm_addendum.sql'");
        $hardeningPosition = strpos($builder, "'stage_11h_backend_hardening.sql'");
        $stage12Position = strpos($builder, "'stage_12_universal_tips.sql'");
        self::assertNotFalse($addendumPosition);
        self::assertNotFalse($hardeningPosition);
        self::assertNotFalse($stage12Position);
        self::assertLessThan($hardeningPosition, $addendumPosition);
        self::assertLessThan($stage12Position, $hardeningPosition);

        self::assertStringContainsString('stage_11h_backend_hardening.sql', $runner);
        self::assertStringContainsString('merchant_locations.claim_code', $smoke);
        self::assertStringContainsString('php scripts/stage11h.php', $workflow);
        self::assertStringContainsString('php scripts/stage11h_smoke.php', $workflow);
    }
}
