<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminCommerceOperationsContractTest extends TestCase
{
    public function testStage18lMigrationCreatesCasesAndPermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/stage_18l_admin_commerce_operations.sql');
        $manifest = file_get_contents($root . '/config/migrations.php');

        self::assertIsString($migration);
        self::assertIsString($manifest);
        self::assertStringContainsString('stage_18l_admin_commerce_operations.sql', $manifest);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS commerce_operation_cases', $migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS commerce_operation_case_events', $migration);
        self::assertStringContainsString("'admin.commerce.view'", $migration);
        self::assertStringContainsString("'admin.commerce.manage'", $migration);
        self::assertStringContainsString("r.slug IN ('admin','super_admin')", $migration);
        self::assertStringNotContainsString('permissions (slug,name,description', $migration);
    }

    public function testStage18l2MigrationAddsSplitCommercePermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/stage_18l2_admin_commerce_permission_split.sql');
        $manifest = file_get_contents($root . '/config/migrations.php');
        $matrix = file_get_contents($root . '/includes/admin-permission-matrix.php');

        self::assertIsString($migration);
        self::assertIsString($manifest);
        self::assertIsString($matrix);
        self::assertStringContainsString('stage_18l2_admin_commerce_permission_split.sql', $manifest);
        foreach ([
            'admin.commerce.orders.view',
            'admin.commerce.refunds.view',
            'admin.commerce.disputes.view',
            'admin.commerce.subscriptions.view',
            'admin.commerce.tips.view',
            'admin.commerce.microgifts.view',
            'admin.commerce.cases.view',
            'admin.commerce.cases.manage',
            'admin.commerce.tips.reverse',
        ] as $permission) {
            self::assertStringContainsString("'{$permission}'", $migration);
            self::assertStringContainsString("'{$permission}'", $matrix);
        }
        self::assertStringContainsString("r.slug IN ('admin','super_admin')", $migration);
    }

    public function testQueueCoversCanonicalCommerceDomainsAndBoundedPagination(): void
    {
        $root = dirname(__DIR__, 2);
        $queue = file_get_contents($root . '/api/admin/commerce/_list.php');
        $endpoint = file_get_contents($root . '/api/admin/commerce/queue.php');

        self::assertIsString($queue);
        self::assertIsString($endpoint);
        foreach (['commerce_orders','payment_refunds','payment_disputes','subscriptions','tips','microgift_instances','commerce_operation_cases'] as $table) {
            self::assertStringContainsString($table, $queue);
        }
        self::assertStringContainsString('MG_ADMIN_COMMERCE_MAX_LIMIT', file_get_contents($root . '/api/admin/commerce/_common.php'));
        self::assertStringContainsString("mg_require_method('GET')", $endpoint);
        self::assertStringContainsString('mg_admin_commerce_require_domain_user($domain)', $endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.commerce.queue'", $endpoint);
        self::assertStringContainsString('Cache-Control: private, no-store, max-age=0', $endpoint);
    }

    public function testInspectUsesSubjectSpecificReadPermission(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/admin/commerce/inspect.php');

        self::assertIsString($endpoint);
        self::assertStringContainsString('$type = mg_admin_commerce_subject_type', $endpoint);
        self::assertStringContainsString('mg_admin_commerce_require_domain_user($type)', $endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.commerce.inspect'", $endpoint);
    }

    public function testDetailReadersUseCanonicalLifecycleAndLedgerTables(): void
    {
        $root = dirname(__DIR__, 2);
        $order = file_get_contents($root . '/api/admin/commerce/_detail_order.php');
        $finance = file_get_contents($root . '/api/admin/commerce/_detail_finance.php');
        $subscription = file_get_contents($root . '/api/admin/commerce/_detail_subscription.php');
        $microgift = file_get_contents($root . '/api/admin/commerce/_detail_microgift.php');

        foreach ([$order,$finance,$subscription,$microgift] as $source) self::assertIsString($source);
        foreach (['payment_intents','payment_transactions','payment_refunds','payment_disputes','order_status_history','ledger_transaction_groups','microgift_instances'] as $table) {
            self::assertStringContainsString($table, $order);
        }
        self::assertStringContainsString('tip_reversals', $finance);
        self::assertStringContainsString('tip_payment_recoveries', $finance);
        self::assertStringContainsString('subscription_attempts', $subscription);
        self::assertStringContainsString('subscription_payment_recoveries', $subscription);
        self::assertStringContainsString('microgift_claim_attempts', $microgift);
        self::assertStringContainsString('microgift_lifecycle_actions', $microgift);
        self::assertStringNotContainsString('password_hash', $order . $finance . $subscription . $microgift);
        self::assertStringNotContainsString('token_hash', $order . $finance . $subscription . $microgift);
    }

    public function testOperationsEndpointIsCsrfProtectedTransactionalAndAudited(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/admin/commerce/operate.php');
        $actions = file_get_contents($root . '/api/admin/commerce/_actions.php');
        $cases = file_get_contents($root . '/api/admin/commerce/_case_actions.php');
        $matrix = file_get_contents($root . '/includes/admin-permission-matrix.php');

        self::assertIsString($endpoint);
        self::assertIsString($actions);
        self::assertIsString($cases);
        self::assertIsString($matrix);
        self::assertStringContainsString("mg_require_method('POST')", $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.commerce.operate'", $endpoint);
        self::assertStringContainsString('$pdo->beginTransaction()', $endpoint);
        self::assertStringContainsString('$pdo->commit()', $endpoint);
        self::assertStringContainsString('$pdo->rollBack()', $endpoint);
        self::assertStringContainsString("mg_audit('admin_commerce_'", $endpoint);
        self::assertStringContainsString("mg_event('admin.commerce.'", $endpoint);
        self::assertStringContainsString('mg_admin_commerce_action_required_permission($action)', $actions);
        self::assertStringContainsString("'admin.commerce.cases.manage'", $matrix);
        self::assertStringContainsString('at least 8 characters', file_get_contents($root . '/api/admin/commerce/_common.php'));
        self::assertStringContainsString('FOR UPDATE', $cases);
    }

    public function testReviewCasesAndTipReversalUseExistingAuthoritiesAsAliases(): void
    {
        $root = dirname(__DIR__, 2);
        $actions = file_get_contents($root . '/api/admin/commerce/_actions.php');
        $cases = file_get_contents($root . '/api/admin/commerce/_case_actions.php');
        $financial = file_get_contents($root . '/api/admin/commerce/_financial_actions.php');
        $matrix = file_get_contents($root . '/includes/admin-permission-matrix.php');

        self::assertIsString($actions);
        self::assertIsString($cases);
        self::assertIsString($financial);
        self::assertIsString($matrix);
        foreach (['open_case','assign_case','add_case_note','resolve_case','dismiss_case','reopen_case','reverse_tip'] as $action) {
            self::assertStringContainsString("'{$action}'", $actions);
        }
        self::assertStringContainsString("status IN ('open','reviewing')", $cases);
        self::assertStringContainsString('mg_tip_reverse', $financial);
        self::assertStringContainsString('mg_tip_notify_reversal', $financial);
        self::assertStringContainsString("'tips.reverse'", $matrix);
        self::assertStringContainsString("'admin.commerce.tips.reverse'", $matrix);
        self::assertStringNotContainsString('INSERT INTO ledger_entries', $financial);
    }
}
