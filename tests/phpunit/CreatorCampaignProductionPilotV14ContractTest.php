<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignProductionPilotV14ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        return (string)file_get_contents($this->root . '/' . $path);
    }

    public function testOperatorSchemaIsAdditiveAndDoesNotGrantAuthority(): void
    {
        $sql = $this->read('database/20260722_creator_campaign_production_pilot_v14_single_install.sql');
        self::assertSame(3, substr_count($sql, 'CREATE TABLE IF NOT EXISTS creator_campaign_operator_'));
        self::assertStringContainsString('creator_campaign_operator_pilots', $sql);
        self::assertStringContainsString('creator_campaign_operator_events', $sql);
        self::assertStringContainsString('creator_campaign_operator_handoffs', $sql);
        self::assertStringNotContainsString('mcp_scope_catalog', $sql);
        self::assertStringNotContainsString('bounded_auto', $sql);
    }

    public function testEmergencyStopBlocksRunsAndPausesAuthority(): void
    {
        $runtime = $this->read('includes/creator-campaign-pilot/runtime.php');
        $service = $this->read('includes/creator-campaign-pilot/service.php') . $this->read('includes/creator-campaign-pilot/service-readiness.php') . $this->read('includes/creator-campaign-pilot/service-health.php') . $this->read('includes/creator-campaign-pilot/service-control.php');
        $playbookBridge = $this->read('api/internal/_mcp_creator_campaign_playbook_bridge.php');
        self::assertStringContainsString('mg_creator_campaign_pilot_assert_playbook_enabled', $runtime);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_PILOT_EMERGENCY_DISABLED', $runtime);
        self::assertStringContainsString('creator-campaign-pilot/runtime.php', $playbookBridge);
        self::assertStringContainsString('mg_creator_campaign_pilot_assert_playbook_enabled', $playbookBridge);
        self::assertStringContainsString('cancellation_requested_at=COALESCE', $service);
        self::assertStringContainsString('UPDATE mcp_automation_triggers', $service);
        self::assertStringContainsString('UPDATE mcp_automations', $service);
        self::assertStringContainsString('UPDATE mcp_automation_grants', $service);
        self::assertStringContainsString('g.revocation_version=g.revocation_version+1', $service);
    }

    public function testEmergencyClearIsNonRestorative(): void
    {
        $service = $this->read('includes/creator-campaign-pilot/service.php') . $this->read('includes/creator-campaign-pilot/service-readiness.php') . $this->read('includes/creator-campaign-pilot/service-health.php') . $this->read('includes/creator-campaign-pilot/service-control.php');
        self::assertStringContainsString("SET status='paused',emergency_disabled=0", $service);
        self::assertStringContainsString('Grants and definitions remain paused.', $service);
        self::assertStringNotContainsString("SET status='active',emergency_disabled=0", $service);
    }

    public function testAcceptedArtifactHandoffCreatesOnlyARequest(): void
    {
        $handoff = $this->read('includes/creator-campaign-pilot/action-handoff.php') . $this->read('includes/creator-campaign-pilot/action-handoff-seed.php') . $this->read('includes/creator-campaign-pilot/action-handoff-service.php');
        self::assertStringContainsString('mg_creator_campaign_pilot_prepare_action_request', $handoff);
        self::assertStringContainsString("status'] !== 'approved'", $handoff);
        self::assertStringContainsString("maximum_operation_class'] !== 'approval_gated'", $handoff);
        self::assertStringContainsString('mg_mcp_creator_campaign_action_contract', $handoff);
        self::assertStringContainsString('mg_mcp_creator_campaign_action_request', $handoff);
        self::assertStringContainsString("'owner_approval_required'=>true", $handoff);
        self::assertStringContainsString("'execution_performed'=>false", $handoff);
        self::assertStringNotContainsString('mg_mcp_creator_campaign_action_execute(', $handoff);
        self::assertStringNotContainsString('mg_mcp_creator_campaign_action_native_execute(', $handoff);
    }

    public function testOperatorCockpitContainsRequiredPilotSurfaces(): void
    {
        $page = $this->read('account-creator-campaign-pilot.php')
            . $this->read('includes/creator-campaign-pilot/page-view.php') . $this->read('includes/creator-campaign-pilot/page-view-runs.php') . $this->read('includes/creator-campaign-pilot/page-view-monitoring.php');
        foreach ([
            'Production pilot cockpit',
            'Pilot readiness',
            'Operator checklist',
            'Bounded assistants',
            'Run history',
            'Recent recommendations',
            'MCP security feed',
            'Emergency stop',
            'Create waiting-for-approval request',
        ] as $text) {
            self::assertStringContainsString($text, $page);
        }
    }

    public function testLegacyPhase13AndPhase4BoundariesRemainExternal(): void
    {
        $definitionView = $this->read('includes/mcp-automations/definitions-page-view.php');
        $draftView = $this->read('includes/mcp-drafts/account-page-phase3b-view.php');
        $actionView = $this->read('includes/mcp-creator-campaign-actions/owner-page-view.php');
        self::assertStringContainsString('Simulation-only deployment state', $definitionView);
        self::assertStringContainsString('No scheduler or canonical action path exists in Phase 4B', $definitionView);
        self::assertStringContainsString('Human approval remains non-executing', $draftView);
        self::assertStringContainsString('Two-step owner gate', $actionView);
    }
}
