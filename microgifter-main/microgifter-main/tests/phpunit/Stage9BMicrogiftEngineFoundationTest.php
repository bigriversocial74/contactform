<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9BMicrogiftEngineFoundationTest extends TestCase
{
    public function testSchemaDefinesCanonicalTablesAndCompatibilityKeys(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_9b_microgift_engine.sql');
        self::assertIsString($sql);
        foreach (['microgift_templates','microgift_template_versions','microgift_instances','microgift_credentials','microgift_events'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('uq_microgift_instances_idempotency', $sql);
        self::assertStringContainsString('legacy_gift_id BIGINT UNSIGNED NULL', $sql);
        self::assertStringContainsString('pppm_item_id BIGINT UNSIGNED NULL', $sql);
        self::assertStringContainsString('commerce_order_item_id BIGINT UNSIGNED NULL', $sql);
    }

    public function testPublishedTemplateVersionsAreImmutableByContract(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/_engine.php');
        self::assertIsString($service);
        self::assertStringContainsString('Only draft versions may be published.', $service);
        self::assertStringContainsString("status='published'", $service);
        self::assertStringNotContainsString('UPDATE microgift_template_versions SET title=', $service);
    }

    public function testIssuanceRequiresSourceReferenceAndBoundIdempotency(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/_engine.php');
        self::assertIsString($service);
        self::assertStringContainsString('source_reference', $service);
        self::assertStringContainsString('idempotency_key', $service);
        self::assertStringContainsString('function mg_microgift_existing_issue', $service);
        self::assertStringContainsString('WHERE i.idempotency_key = ?', $service);
        self::assertStringContainsString('issuer_user_id', $service);
        self::assertStringContainsString('template_version_public_id', $service);
        self::assertStringContainsString('A verified paid commerce order item is required.', $service);
        self::assertStringContainsString('o.buyer_user_id', $service);
    }

    public function testCredentialServiceStoresOnlyHashPrefixAndLastFour(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_9b_microgift_engine.sql');
        $service = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/_engine.php');
        self::assertIsString($sql);
        self::assertIsString($service);
        self::assertStringContainsString('code_hash CHAR(64) NOT NULL', $sql);
        self::assertStringContainsString('code_prefix VARCHAR(8) NOT NULL', $sql);
        self::assertStringContainsString('code_last4 CHAR(4) NOT NULL', $sql);
        self::assertStringNotContainsString('raw_code', $sql);
        self::assertStringContainsString('mg_microgift_code_hash', $service);
        self::assertStringContainsString('random_bytes(16)', $service);
        self::assertStringContainsString("'code'=>\$rawCode", $service);
    }

    public function testApisUseAuthenticationPermissionAndCsrfBoundaries(): void
    {
        $templates = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/templates.php');
        $versions = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/versions.php');
        $issue = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/issue.php');
        $instances = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/instances.php');
        self::assertStringContainsString("mg_require_permission('microgift.templates.manage')", $templates);
        self::assertStringContainsString('mg_require_csrf_for_write', $templates);
        self::assertStringContainsString('mg_require_csrf_for_write', $versions);
        self::assertStringContainsString("mg_require_permission('microgift.instances.issue')", $issue);
        self::assertStringContainsString('mg_require_csrf_for_write', $issue);
        self::assertStringContainsString('mg_require_api_user()', $instances);
        self::assertStringContainsString('i.owner_user_id=? OR i.recipient_user_id=? OR i.issuer_user_id=?', $instances);
    }

    public function testStage9MigrationAndSmokeAreInConsolidatedValidation(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/pr-validation.yml');
        $runner = file_get_contents(dirname(__DIR__, 2) . '/scripts/stage9b.php');
        $smoke = file_get_contents(dirname(__DIR__, 2) . '/scripts/stage9b_smoke.php');
        self::assertIsString($workflow);
        self::assertIsString($runner);
        self::assertIsString($smoke);
        self::assertStringContainsString('php scripts/stage9b.php', $workflow);
        self::assertStringContainsString('php scripts/stage9b_smoke.php', $workflow);
        self::assertStringContainsString('stage_9b_microgift_engine.sql', $runner);
        self::assertStringContainsString('Stage 9B Microgift Engine smoke checks passed', $smoke);
    }
}
