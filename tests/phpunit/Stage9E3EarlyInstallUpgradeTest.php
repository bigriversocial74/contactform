<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9E3EarlyInstallUpgradeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $relative): string
    {
        $content = file_get_contents($this->root . '/' . $relative);
        self::assertIsString($content, 'Unable to read ' . $relative);
        return $content;
    }

    public function testPreflightDetectsExistingUsersAndPreservesAccountFoundation(): void
    {
        $script = $this->read('scripts/stage9e3_preflight.php');
        self::assertStringContainsString('early_install_upgrade_preflight', $script);
        self::assertStringContainsString("'users'", $script);
        self::assertStringContainsString('Existing users detected', $script);
        self::assertStringContainsString('run additive stage scripts only', $script);
    }

    public function testUpgradeManifestMatchesZipUploadAndAdditiveStageFlow(): void
    {
        $script = $this->read('scripts/stage9e3_upgrade_manifest.php');
        self::assertStringContainsString('zip_upload_extract_over_existing_stage1_install', $script);
        self::assertStringContainsString('composer migrate', $script);
        self::assertStringContainsString('php scripts/stage7b.php', $script);
        self::assertStringContainsString('php scripts/stage8b.php', $script);
        self::assertStringContainsString('php scripts/stage9b.php', $script);
        self::assertStringContainsString('php scripts/stage9d.php', $script);
        self::assertStringContainsString('php scripts/stage9e3_smoke.php', $script);
    }

    public function testSmokeChecksRequiredStageTablesPermissionsAndContracts(): void
    {
        $script = $this->read('scripts/stage9e3_smoke.php');
        foreach (['pppm_items','entitlements','microgift_templates','microgift_instances','microgift_claims','microgift_redemptions','microgift_review_items'] as $table) {
            self::assertStringContainsString("'{$table}'", $script);
        }
        foreach (['microgift.templates.manage','microgift.claim','microgift.redeem','microgift.reviews.manage'] as $permission) {
            self::assertStringContainsString($permission, $script);
        }
        self::assertStringContainsString('docs/contracts/event_catalog_stage1_9.yaml', $script);
        self::assertStringContainsString('docs/contracts/api_contracts_stage1_9.yaml', $script);
    }

    public function testDocumentationCoversPreserveExistingAccountsAndZipUpload(): void
    {
        $doc = $this->read('docs/stages/stage_9e3_early_install_upgrade.md');
        $checklist = $this->read('docs/stages/stage_9e3_zip_upload_checklist.md');
        self::assertStringContainsString('early Stage 1 install', $doc);
        self::assertStringContainsString('download the latest repository zip', $doc);
        self::assertStringContainsString('extract it over the existing codebase', $doc);
        self::assertStringContainsString('Do not wipe or rebuild', $doc);
        self::assertStringContainsString('Export a full database backup', $checklist);
        self::assertStringContainsString('Confirm the two existing accounts can log in', $checklist);
    }

    public function testStage10GateRequiresLiveUpgradeSmokeBeforePlanning(): void
    {
        $doc = $this->read('docs/stages/stage_9e3_early_install_upgrade.md');
        self::assertStringContainsString('Stage 10 should begin only after', $doc);
        self::assertStringContainsString('preflight passes', $doc);
        self::assertStringContainsString('smoke checks pass', $doc);
        self::assertStringContainsString('existing login still works', $doc);
    }
}
