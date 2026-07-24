<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public-donations-governance.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-governance-locks.php';

final class PublicDonationsGovernanceContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testGranularPermissionMapIsComplete(): void
    {
        self::assertSame([
            'view' => 'merchant.public_donations.view',
            'manage' => 'merchant.public_donations.manage',
            'assign' => 'merchant.public_donations.assign',
            'allocate' => 'merchant.public_donations.allocate',
            'recall' => 'merchant.public_donations.recall',
            'report' => 'merchant.public_donations.report',
        ], MG_PUBLIC_DONATIONS_GOVERNANCE_ACTIONS);
    }

    public function testWorkspaceRolesFollowLeastPrivilege(): void
    {
        foreach (['owner', 'manager'] as $role) {
            foreach (array_keys(MG_PUBLIC_DONATIONS_GOVERNANCE_ACTIONS) as $action) {
                self::assertTrue(mg_public_donations_governance_workspace_allows(['workspace_role' => $role], $action), $role . ':' . $action);
            }
        }

        foreach (['marketing', 'marketer'] as $role) {
            foreach (['view', 'report', 'manage', 'assign'] as $action) {
                self::assertTrue(mg_public_donations_governance_workspace_allows(['workspace_role' => $role], $action), $role . ':' . $action);
            }
            self::assertFalse(mg_public_donations_governance_workspace_allows(['workspace_role' => $role], 'allocate'));
            self::assertFalse(mg_public_donations_governance_workspace_allows(['workspace_role' => $role], 'recall'));
        }

        foreach (['staff', 'viewer'] as $role) {
            self::assertTrue(mg_public_donations_governance_workspace_allows(['workspace_role' => $role], 'view'));
            self::assertTrue(mg_public_donations_governance_workspace_allows(['workspace_role' => $role], 'report'));
            foreach (['manage', 'assign', 'allocate', 'recall'] as $action) {
                self::assertFalse(mg_public_donations_governance_workspace_allows(['workspace_role' => $role], $action), $role . ':' . $action);
            }
        }
    }

    public function testOperationalCopyAndPrivacyContractAreExplicit(): void
    {
        $copy = mg_public_donations_governance_operational_copy();
        self::assertSame('merchant_funded_promotional_rewards', $copy['funding_type']);
        self::assertFalse($copy['cash_donation']);
        self::assertFalse($copy['tax_deductible_charitable_contribution']);
        self::assertStringContainsString('not cash donations', $copy['statement']);
        self::assertStringContainsString('not cash donations', str_replace('are ', '', $copy['statement']));

        $privacy = mg_public_donations_governance_privacy_contract();
        self::assertTrue($privacy['private_or_unavailable_accounts_are_aggregate_only']);
        self::assertFalse($privacy['final_recipient_identity_exposed']);
        self::assertFalse($privacy['claim_codes_exposed']);
        self::assertFalse($privacy['ownership_identifiers_exposed']);
        self::assertTrue($privacy['anonymized_commerce_evidence_preserved']);
        self::assertTrue($privacy['campaign_attribution_preserved']);
    }

    public function testInstallerProvisionsPermissionsWithoutGrantingCommunityMerchantPower(): void
    {
        $sql = $this->read('database/20260724_public_donations_community_v1_single_install.sql');
        foreach (MG_PUBLIC_DONATIONS_GOVERNANCE_ACTIONS as $permission) {
            self::assertStringContainsString("'{$permission}'", $sql);
        }
        self::assertStringContainsString("WHERE role.slug IN ('merchant','admin','super_admin')", $sql);
        self::assertStringNotContainsString("WHERE role.slug IN ('merchant','community'", $sql);
    }

    public function testCompletedReplaysPrecedeConcurrencyAndBudgetAdmission(): void
    {
        $allocation = $this->read('api/merchant/public-donations-allocation.php');
        $recall = $this->read('api/merchant/public-donations-recall.php');

        $allocationReplay = strpos($allocation, '$completedReplay = mg_public_donations_allocation_operation');
        $allocationAdmission = strpos($allocation, 'mg_public_donations_governance_admit_operation');
        self::assertNotFalse($allocationReplay);
        self::assertNotFalse($allocationAdmission);
        self::assertLessThan($allocationAdmission, $allocationReplay);

        $recallReplay = strpos($recall, '$replay = mg_public_donations_recall_operation');
        $recallAdmission = strpos($recall, 'mg_public_donations_governance_admit_operation');
        self::assertNotFalse($recallReplay);
        self::assertNotFalse($recallAdmission);
        self::assertLessThan($recallAdmission, $recallReplay);
    }

    public function testAllMerchantEntryPointsUseWorkspaceOwnerGovernance(): void
    {
        $files = [
            'api/merchant/public-donations-community.php',
            'api/merchant/public-donations-allocation.php',
            'api/merchant/public-donations-recall.php',
            'api/merchant/community-support.php',
        ];
        foreach ($files as $file) {
            $source = $this->read($file);
            self::assertStringContainsString('mg_public_donations_governance_context', $source, $file);
            self::assertStringContainsString("$" . "governance['merchant_user_id']", $source, $file);
            self::assertStringContainsString("$" . "governance['actor_user_id']", $source, $file);
        }
    }

    public function testConcurrencyUsesNamedLocksAndLockedBudgetRows(): void
    {
        $governance = $this->read('includes/public-donations-governance.php');
        $locks = $this->read('includes/public-donations-governance-locks.php');

        self::assertStringContainsString('SELECT status,requested_quantity,completed_quantity', $governance);
        self::assertStringContainsString('FOR UPDATE', $governance);
        self::assertStringNotContainsString('SELECT COALESCE(SUM(CASE WHEN status=', $governance);
        self::assertStringContainsString('GET_LOCK(?, 8)', $locks);
        self::assertStringContainsString('RELEASE_LOCK(?)', $locks);
    }

    public function testPublicAndMerchantSurfacesKeepRequiredReleaseCopyAndNoPublicTransaction(): void
    {
        $publicView = $this->read('includes/public-donations-public-view.php');
        $page = $this->read('merchant-community-support.php');
        $navigation = $this->read('includes/merchant-navigation.php');

        self::assertStringContainsString('It is not cash, a charitable receipt, or a tax-deductible contribution.', $publicView);
        self::assertStringContainsString('No public transaction', $publicView);
        self::assertStringNotContainsString('<form', $publicView);
        self::assertStringNotContainsString('type="number"', $publicView);
        self::assertStringContainsString('Community Support is not available', $page);
        self::assertStringContainsString('mg_merchant_navigation_public_donations_visible', $navigation);
    }
}
