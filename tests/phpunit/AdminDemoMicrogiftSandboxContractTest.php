<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminDemoMicrogiftSandboxContractTest extends TestCase
{
    public function testAdminEndpointSeedsDatabaseBackedDemoMicrogiftThroughCanonicalFlow(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/admin/demo-microgifts.php');
        self::assertIsString($source);

        self::assertStringContainsString("require_once dirname(__DIR__) . '/microgifts/_engine.php'",$source);
        self::assertStringContainsString("require_once dirname(__DIR__) . '/microgifts/_lifecycle.php'",$source);
        self::assertStringContainsString("require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php'",$source);
        self::assertStringContainsString("mg_require_permission('admin.users.view')",$source);
        self::assertStringContainsString("'source_type' => 'administrator'",$source);
        self::assertStringContainsString("'mg_demo' => true",$source);
        self::assertStringContainsString("'sandbox_mode' => 'admin_demo'",$source);
        self::assertStringContainsString("'financial_side_effects' => 'disabled'",$source);
        self::assertStringContainsString('mg_microgift_issue($pdo,$adminUserId',$source);
        self::assertStringContainsString('mg_action_center_project_lifecycle($pdo,$instance)',$source);
        self::assertStringContainsString("'claim_code' =>",$source);
    }

    public function testAdminEndpointCanDisableDemoRecordsWithoutDeletingProductionRows(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/admin/demo-microgifts.php');
        self::assertIsString($source);

        self::assertStringContainsString("source_type='administrator'",$source);
        self::assertStringContainsString("source_reference LIKE ?",$source);
        self::assertStringContainsString('UPDATE microgift_inbox_items SET archived_at=COALESCE(archived_at,NOW())',$source);
        self::assertStringContainsString("UPDATE microgift_instances SET status='cancelled'",$source);
        self::assertStringContainsString("UPDATE microgift_credentials SET status='revoked'",$source);
        self::assertStringContainsString('mg_microgift_event($pdo,\'microgift.demo_cancelled\'',$source);
    }

    public function testActionCenterExposesSystemDemoMetadataWithoutPreviewOnlyFlag(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/_action_center.php');
        self::assertIsString($source);

        self::assertStringContainsString('i.metadata_json instance_metadata_json',$source);
        self::assertStringContainsString('sandbox_mode',$source);
        self::assertStringContainsString('demo_scenario',$source);
        self::assertStringContainsString('is_demo_preview',$source);
        self::assertStringContainsString('is_system_demo',$source);
        self::assertStringContainsString("admin_demo",$source);
        self::assertStringNotContainsString("'is_demo'=>true",$source);
    }
}
