<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public-donations-feature.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-reconciliation.php';

final class PublicDonationsOperationsAdminContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $content = file_get_contents($this->root . '/' . $path);
        self::assertIsString($content, $path);
        return $content;
    }

    public function testMerchantIdParserIsBoundedAndDeterministic(): void
    {
        self::assertSame([2, 5, 9], mg_public_donations_parse_merchant_ids('9,2;5 2 invalid'));
        self::assertSame([], mg_public_donations_parse_merchant_ids(''));
    }

    public function testEnvironmentRolloutDefaultsFailClosed(): void
    {
        putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=invalid');
        putenv('MG_PUBLIC_DONATIONS_MERCHANT_IDS=9,3');
        $config = mg_public_donations_environment_rollout();
        self::assertSame('disabled', $config['state']);
        self::assertSame([3, 9], $config['selected_merchant_ids']);
        self::assertSame('environment', $config['source']);
        self::assertFalse($config['override_active']);
        putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE');
        putenv('MG_PUBLIC_DONATIONS_MERCHANT_IDS');
    }

    public function testSingleInstallerDoesNotActivateOverride(): void
    {
        $sql = $this->read('database/20260724_public_donations_operations_admin_v1_single_install.sql');
        self::assertStringContainsString('override_active TINYINT(1) NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString("(1,0,'disabled',JSON_ARRAY(),1,NOW(),NOW())", $sql);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE id=VALUES(id)', $sql);
        self::assertStringNotContainsString("(1,1,'enabled'", $sql);
    }

    public function testReconciliationBoundariesRemainReportOnly(): void
    {
        $service = $this->read('api/admin/_public_donations_operations.php');
        $reconciliation = $this->read('includes/public-donations-reconciliation.php');

        self::assertStringContainsString('mg_public_donations_reconcile_apply', $service);
        self::assertStringContainsString('mg_public_donations_reconcile_schema_ready', $service);
        self::assertStringContainsString("'missing_attribution'", $reconciliation);
        self::assertStringContainsString("'missing_links'", $reconciliation);
        self::assertStringContainsString("'ownership_mismatches'", $reconciliation);

        $boundary = $service . "\n" . $reconciliation;
        self::assertStringNotContainsString('INSERT INTO campaign_donation_rewards', $boundary);
        self::assertDoesNotMatchRegularExpression(
            '/UPDATE\s+(?:wallet_items|pppm_items|microgift_instances)\s+SET\s+(?:user_id|owner_user_id|recipient_user_id)/i',
            $boundary
        );
    }

    public function testProtectedActionsRequireCsrfRateLimitsTypedConfirmationAndReceiptStorage(): void
    {
        $service = $this->read('api/admin/_public_donations_operations.php');
        $action = $this->read('api/admin/public-donations-operations-action.php');
        foreach ([
            'UPDATE PUBLIC DONATIONS ROLLOUT',
            'RETURN TO ENVIRONMENT CONFIG',
            'REPAIR PUBLIC DONATIONS',
            'mg_admin_public_donations_actor_can_repair',
            'mg_audit(',
            'mg_event(',
            'mg_security_log(',
        ] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringContainsString('mg_admin_public_donations_require_operations_user(true)', $action);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $action);
        self::assertStringContainsString("mg_rate_limit('admin.public_donations_operations.write'", $action);
        self::assertStringContainsString("mg_admin_public_donations_table(\$pdo, 'public_donations_reconciliation_receipts')", $action);
        self::assertStringContainsString('Import the Public Donations Operations Admin SQL before running reconciliation.', $action);
    }

    public function testViewAndManageAccessRemainSeparate(): void
    {
        $projection = $this->read('api/admin/_public_donations_operations_projection.php');
        $read = $this->read('api/admin/public-donations-operations.php');
        $action = $this->read('api/admin/public-donations-operations-action.php');
        $matrix = $this->read('includes/admin-permission-matrix.php');
        $app = $this->read('assets/js/admin-public-donations-operations-app.js');

        self::assertStringContainsString('mg_admin_public_donations_require_operations_user(bool $manage = false)', $projection);
        self::assertStringContainsString("'view' => true", $projection);
        self::assertStringContainsString("'manage' => \$canManage", $projection);
        self::assertStringContainsString('mg_admin_public_donations_require_operations_user()', $read);
        self::assertStringContainsString('mg_admin_public_donations_require_operations_user(true)', $action);
        self::assertStringContainsString("'admin.public_donations_operations.repair' => ['admin.admin_agent.execute']", $matrix);
        self::assertStringContainsString('Read-only access: rollout controls require the manage permission.', $app);
        self::assertStringContainsString('Read-only access: reconciliation execution requires the manage permission.', $app);
    }

    public function testWorkspaceExposesAllOperationalSurfaces(): void
    {
        $page = $this->read('admin/public-donations-operations.php');
        foreach ([
            'data-pdo-readiness',
            'data-pdo-rollout-form',
            'data-pdo-selected-merchants',
            'data-pdo-reconcile-form',
            'data-pdo-receipts',
            'data-pdo-operations',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
    }

    public function testNetAllocationIncludesEveryNonRecalledLifecycleState(): void
    {
        $projection = $this->read('api/admin/_public_donations_operations_projection.php');
        self::assertStringContainsString("SUM(status='recalled') AS recalled", $projection);
        self::assertStringContainsString('max(0, $gross - $recalled)', $projection);
        self::assertStringNotContainsString("SUM(status='allocated') AS net", $projection);
    }

    public function testRepairModesReuseCanonicalPhaseTenEngine(): void
    {
        self::assertSame([
            'counters',
            'batch_totals',
            'recalled_visibility',
            'assignments',
        ], MG_PUBLIC_DONATIONS_REPAIR_MODES);
        $service = $this->read('api/admin/_public_donations_operations.php');
        self::assertStringContainsString('mg_public_donations_reconcile_apply', $service);
        self::assertStringContainsString('mg_public_donations_reconcile_schema_ready', $service);
    }
}
