<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignProductionAuditV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        require_once $this->root . '/includes/creator-campaigns/compensation-definitions.php';
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testPercentageCompensationUsesExactIntegerFloorArithmetic(): void
    {
        self::assertSame(1, mg_creator_campaign_compensation_percent_minor(10001, 1));
        self::assertSame(9999, mg_creator_campaign_compensation_percent_minor(19999, 5000));
        self::assertSame(0, mg_creator_campaign_compensation_percent_minor(9999, 1));
        self::assertSame(PHP_INT_MAX, mg_creator_campaign_compensation_percent_minor(PHP_INT_MAX, 10000));
    }

    public function testPercentageCompensationRejectsInvalidBasisPoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_creator_campaign_compensation_percent_minor(10000, 10001);
    }

    public function testCustomerRoleReceivesAllOwnCreatorPermissionsWithoutMcpAuthority(): void
    {
        $sql = $this->source('database/20260723_creator_campaign_phases_1_15_production_audit_repair_v1.sql');
        foreach ([
            'creator.campaigns.discover',
            'creator.campaign_applications.manage_own',
            'creator.campaign_invitations.respond_own',
            'creator.campaign_participants.view_own',
            'creator.campaign_agreements.view_own',
            'creator.campaign_agreements.respond_own',
            'creator.campaign_deliverables.view_own',
            'creator.campaign_submissions.manage_own',
            'creator.campaign_tracking.view_own',
            'creator.campaign_tracking.manage_own',
            'creator.campaign_earnings.view_own',
            'creator.campaign_payouts.view_own',
            'creator.campaign_disputes.manage_own',
        ] as $permission) {
            self::assertStringContainsString("'{$permission}'", $sql);
        }
        self::assertStringContainsString("WHERE r.slug='customer'", $sql);
        self::assertStringNotContainsString('INSERT INTO mcp_scope_catalog', $sql);
        self::assertStringNotContainsString('mcp_automation_grants', $sql);
    }

    public function testAdjustmentAndReversalRetriesReturnCanonicalEvents(): void
    {
        $source = $this->source('includes/creator-campaigns/compensation-service.php');
        self::assertStringContainsString('WHERE campaign_id=? AND idempotency_hash=? LIMIT 1 FOR UPDATE', $source);
        self::assertStringContainsString('WHERE reversal_of_event_id=? OR (campaign_id=? AND idempotency_hash=?)', $source);
        self::assertGreaterThanOrEqual(4, substr_count($source, "'idempotent'=>true"));
        self::assertStringContainsString('(string)$e->getCode()===\'23000\'', $source);
    }

    public function testSmokeReceiptsAreStatusAwareAndCurrentStateBound(): void
    {
        $phase15Sql = $this->source('database/20260723_creator_campaign_pilot_launch_onboarding_v15_single_install.sql');
        $repairSql = $this->source('database/20260723_creator_campaign_phases_1_15_production_audit_repair_v1.sql');
        $readiness = $this->source('includes/creator-campaign-onboarding/readiness.php');
        $smoke = $this->source('includes/creator-campaign-onboarding/smoke-test.php');

        self::assertStringContainsString(
            'uq_creator_campaign_onboarding_receipt_snapshot (onboarding_id,receipt_type,snapshot_hash,status)',
            $phase15Sql
        );
        self::assertStringContainsString("'onboarding_id,receipt_type,snapshot_hash,status'", $repairSql);
        self::assertStringContainsString('snapshot_hash=? AND status=?', $smoke);
        self::assertStringContainsString("'version'=>'creator_campaign_onboarding_smoke_v15_1'", $readiness);
        self::assertStringContainsString("'current_passing_smoke_test'", $readiness);
    }

    public function testFreshnessIncludesExactProductAndCurrentOperatorEvidence(): void
    {
        $repository = $this->source('includes/creator-campaign-onboarding/repository.php');
        $readiness = $this->source('includes/creator-campaign-onboarding/readiness.php');

        foreach ([
            "'current_version_id'",
            "'unit_value_cents'",
            "'currency'",
            "'ready_image_count'",
            "'active_pppm_count'",
            "'operator_evidence'",
            "'automatic_acceptance_disabled'",
            "'emergency_disabled'",
        ] as $needle) {
            self::assertStringContainsString($needle, $readiness);
        }
        self::assertStringContainsString('mg_creator_campaign_onboarding_operator_evidence', $repository);
        self::assertStringContainsString("mtm.status='active'", $repository);
        self::assertStringContainsString("u.status='active'", $repository);
        self::assertStringContainsString('INSERT IGNORE INTO creator_campaign_merchant_onboarding', $repository);
    }

    public function testCampaignContextTargetsPrimaryFiltersAndOperationalLinks(): void
    {
        $context = $this->source('assets/js/creator-campaign-context-filter.js');
        $launch = $this->source('includes/creator-campaign-onboarding/page-view-launch.php');

        foreach ([
            '[data-ccp-campaign-filter]',
            '[data-ccdv-campaign]',
            '[data-cct-campaign]',
            '[data-cce-rule-form] [name="campaign_id"]',
            '[data-ccb-form] [name="campaign_id"]',
            'campaignWorkspacePaths',
        ] as $needle) {
            self::assertStringContainsString($needle, $context);
        }
        foreach ([
            '/merchant-creator-deliverables.php?campaign=',
            '/merchant-creator-compensation.php?campaign=',
            '/merchant-creator-budgets.php?campaign=',
            '/merchant-creator-tracking.php?campaign=',
            '/merchant-creator-participation.php?campaign=',
        ] as $needle) {
            self::assertStringContainsString($needle, $launch);
        }
    }

    public function testSmokeAndActivationRemainNonExecuting(): void
    {
        $smoke = $this->source('includes/creator-campaign-onboarding/smoke-test.php');
        $service = $this->source('includes/creator-campaign-onboarding/service.php');

        foreach ([
            "'automatic_execution'=>false",
            "'campaign_published'=>false",
            "'payment_provider_called'=>false",
        ] as $needle) {
            self::assertStringContainsString($needle, $smoke);
        }
        self::assertStringNotContainsString('mg_mcp_', $smoke);
        self::assertStringNotContainsString('mg_creator_campaign_transition_status(', $smoke);
        self::assertStringNotContainsString('mg_creator_campaign_transition_status(', $service);
        self::assertStringNotContainsString('mcp_automation_grants', $service);
    }
}
