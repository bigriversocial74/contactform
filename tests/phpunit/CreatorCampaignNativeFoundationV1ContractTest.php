<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignNativeFoundationV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        require_once $this->root . '/includes/creator-campaigns/definitions.php';
        require_once $this->root . '/includes/creator-campaigns/validation.php';
        require_once $this->root . '/includes/creator-campaigns/context.php';
    }

    public function testDomainIsIsolatedFromLegacyCampaignTables(): void
    {
        $paths = glob($this->root . '/includes/creator-campaigns/*.php') ?: [];
        $source = '';
        foreach ($paths as $path) {
            $source .= "\n" . file_get_contents($path);
        }
        self::assertDoesNotMatchRegularExpression('/\bFROM\s+campaigns\b/i', $source);
        self::assertDoesNotMatchRegularExpression('/\bINTO\s+campaigns\b/i', $source);
        self::assertStringContainsString('creator_campaigns', $source);
    }

    public function testWorkspaceRolesCannotBypassObjectLevelAuthorization(): void
    {
        self::assertTrue(mg_creator_campaign_workspace_role_allows('owner', 'merchant.creator_campaigns.manage'));
        self::assertTrue(mg_creator_campaign_workspace_role_allows('manager', 'merchant.creator_campaigns.publish'));
        self::assertTrue(mg_creator_campaign_workspace_role_allows('viewer', 'merchant.creator_campaigns.view'));
        self::assertFalse(mg_creator_campaign_workspace_role_allows('viewer', 'merchant.creator_campaigns.manage'));
        self::assertFalse(mg_creator_campaign_workspace_role_allows('claims_staff', 'merchant.creator_campaigns.publish'));
    }

    public function testLifecycleAllowsOnlyApprovedTransitions(): void
    {
        self::assertTrue(mg_creator_campaign_can_transition('draft', 'scheduled'));
        self::assertTrue(mg_creator_campaign_can_transition('active', 'paused'));
        self::assertTrue(mg_creator_campaign_can_transition('completed', 'archived'));
        self::assertFalse(mg_creator_campaign_can_transition('draft', 'completed'));
        self::assertFalse(mg_creator_campaign_can_transition('archived', 'active'));
        self::assertFalse(mg_creator_campaign_can_transition('cancelled', 'active'));
    }

    public function testCreateValidationNormalizesPhoenixTimeToUtc(): void
    {
        $normalized = mg_creator_campaign_normalize_create_input([
            'title' => 'Creator launch',
            'timezone' => 'America/Phoenix',
            'starts_at' => '2026-08-01 09:00:00',
            'ends_at' => '2026-08-01 17:00:00',
            'idempotency_key' => 'phpunit-create-0001',
        ]);
        self::assertSame('2026-08-01 16:00:00', $normalized['starts_at']);
        self::assertSame('2026-08-02 00:00:00', $normalized['ends_at']);
        self::assertSame('America/Phoenix', $normalized['timezone']);
    }

    public function testCreateValidationRejectsInvalidDatesAndAccessModes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_creator_campaign_normalize_create_input([
            'title' => 'Invalid creator launch',
            'starts_at' => '2026-08-02 09:00:00',
            'ends_at' => '2026-08-01 09:00:00',
            'access_mode' => 'public_but_not_real',
            'idempotency_key' => 'phpunit-create-0002',
        ]);
    }

    public function testMigrationContainsOwnershipIdempotencyAndOptimisticLocking(): void
    {
        $sql = (string) file_get_contents($this->root . '/database/20260721_creator_campaign_native_foundation_v1.sql');
        self::assertStringContainsString('REFERENCES merchant_workspaces(id)', $sql);
        self::assertStringContainsString('creation_idempotency_hash CHAR(64) NOT NULL', $sql);
        self::assertStringContainsString('lock_version INT UNSIGNED NOT NULL DEFAULT 1', $sql);
        self::assertStringContainsString('creator_campaign_status_events', $sql);
        self::assertStringContainsString('merchant.creator_campaigns.publish', $sql);
    }

    public function testStatusEventServiceIsAppendOnly(): void
    {
        $source = (string) file_get_contents($this->root . '/includes/creator-campaigns/status-service.php');
        self::assertStringContainsString('INSERT INTO creator_campaign_status_events', $source);
        self::assertDoesNotMatchRegularExpression('/UPDATE\s+creator_campaign_status_events/i', $source);
        self::assertDoesNotMatchRegularExpression('/DELETE\s+FROM\s+creator_campaign_status_events/i', $source);
    }
}
