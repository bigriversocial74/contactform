<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/operations/_operations.php';
require_once dirname(__DIR__, 2) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/payments/_readiness.php';

mg_require_method('GET');
$user = mg_require_permission('operations.readiness.view');
$pdo = mg_db();
$checks = [];
$orchestrationMigrationContracts = [
    'stage_17b_demand_signal_agent_orchestration',
    'stage_18b_demand_orchestration_operations',
];
$add = static function (string $key, string $status, string $summary, array $details = []) use (&$checks, $pdo): void {
    $checks[] = ['key' => $key, 'status' => $status, 'summary' => $summary, 'details' => $details];
    mg_operations_record_check($pdo, $key, $status, $summary, $details);
};

try {
    $pdo->query('SELECT 1');
    $add('database', 'pass', 'Database connection is available.');
} catch (Throwable $e) {
    $add('database', 'fail', 'Database connection failed.', ['message' => $e->getMessage()]);
}

try {
    $migrationStatus = mg_migration_status($pdo);
    $add(
        'stage_migrations',
        $migrationStatus['ready'] ? 'pass' : 'fail',
        $migrationStatus['ready']
            ? 'The canonical migration manifest is satisfied.'
            : 'The canonical migration manifest is incomplete or inconsistent.',
        [
            'ordered_count' => $migrationStatus['ordered_count'],
            'applied_key_count' => $migrationStatus['applied_key_count'],
            'missing' => $migrationStatus['missing'],
            'checksum_mismatches' => $migrationStatus['checksum_mismatches'],
            'orchestration_contracts' => $orchestrationMigrationContracts,
        ]
    );
} catch (Throwable $e) {
    $add('stage_migrations', 'fail', 'Migration readiness could not be evaluated.', ['message' => $e->getMessage()]);
}

$appEnvironment = strtolower(trim((string) mg_env('MG_APP_ENV', '')));
$productionEnvironment = in_array($appEnvironment, ['production', 'prod'], true);
$paymentProvider = mg_payment_provider_key();
$paymentMode = mg_payment_mode();
if ($paymentProvider === 'stripe') {
    try {
        $paymentReadiness = mg_payment_readiness($pdo, 'stripe', $paymentMode);
        $add(
            'stripe_platform',
            $paymentReadiness['ready'] ? 'pass' : 'fail',
            $paymentReadiness['ready']
                ? 'Stripe platform configuration is ready for ' . $paymentMode . ' mode.'
                : 'Stripe platform configuration is incomplete.',
            [
                'mode' => $paymentMode,
                'checks' => $paymentReadiness['checks'],
                'connected_accounts' => $paymentReadiness['connected_accounts'],
                'webhook_url' => $paymentReadiness['webhook_url'],
            ]
        );
        $sellingMerchants = $paymentReadiness['selling_merchants'];
        $add(
            'stripe_selling_merchants',
            (int) $sellingMerchants['blocked'] === 0 ? 'pass' : 'fail',
            (int) $sellingMerchants['blocked'] === 0
                ? 'Every merchant with a published product is Stripe-ready.'
                : 'Published-product merchants are blocked by incomplete Stripe Connect onboarding.',
            $sellingMerchants
        );
    } catch (Throwable $e) {
        $add('stripe_platform', 'fail', 'Stripe readiness could not be evaluated.', ['message' => $e->getMessage(), 'mode' => $paymentMode]);
    }
} else {
    $add(
        'payment_provider',
        $productionEnvironment ? 'fail' : 'warn',
        $productionEnvironment
            ? 'Production requires MG_PAYMENT_PROVIDER=stripe.'
            : 'The current environment is not using Stripe.',
        ['provider' => $paymentProvider, 'mode' => $paymentMode, 'environment' => $appEnvironment]
    );
}

$tables = [
    'users',
    'commerce_orders',
    'commerce_order_items',
    'catalog_pppm_templates',
    'catalog_product_version_locations',
    'pppm_items',
    'entitlements',
    'microgift_templates',
    'microgift_template_versions',
    'microgift_instances',
    'microgift_inbox_items',
    'microgift_delivery_events',
    'microgift_redemptions',
    'merchant_locations',
    'merchant_storefront_revisions',
    'merchant_storefront_revision_products',
    'tips',
    'subscriptions',
    'feed_posts',
    'feed_post_versions',
    'payment_platform_credentials',
    'payment_provider_accounts',
    'payment_webhook_events',
    'operational_incidents',
    'deployment_releases',
    'release_gate_results',
    'retention_policies',
    'operational_check_results',
    'demand_signal_orchestrations',
    'demand_signal_orchestration_events',
    'demand_signal_orchestration_attempts',
    'demand_signal_orchestration_incidents',
    'profile_moderation_cases',
    'profile_moderation_actions',
    'profile_moderation_appeals',
    'social_mutation_requests',
];
$missingTables = [];
$tableStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
foreach ($tables as $table) {
    $tableStmt->execute([$table]);
    if ((int) $tableStmt->fetchColumn() !== 1) {
        $missingTables[] = $table;
    }
}
$add(
    'operations_tables',
    $missingTables === [] ? 'pass' : 'fail',
    $missingTables === [] ? 'Canonical platform, payment, and hardening tables are present.' : 'Required platform tables are missing.',
    ['missing' => $missingTables]
);

$count = (int) $pdo->query("SELECT COUNT(*) FROM operational_incidents WHERE status IN ('open','investigating') AND severity IN ('sev1','sev2')")->fetchColumn();
$add('critical_incidents', $count === 0 ? 'pass' : 'fail', $count === 0 ? 'No open SEV1 or SEV2 incidents.' : 'Critical incidents are open.', ['count' => $count]);

$count = (int) $pdo->query("SELECT COUNT(*) FROM payment_webhook_events WHERE status='failed' AND received_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
$add('payment_webhooks', $count === 0 ? 'pass' : 'warn', $count === 0 ? 'No failed payment webhooks in the last 24 hours.' : 'Failed payment webhooks require review.', ['count' => $count]);

$count = (int) $pdo->query("SELECT (SELECT COUNT(*) FROM agent_workflow_runs WHERE status IN ('queued','planning','executing') AND updated_at<DATE_SUB(NOW(),INTERVAL 1 HOUR))+(SELECT COUNT(*) FROM agent_swarm_runs WHERE status IN ('queued','planning','running') AND updated_at<DATE_SUB(NOW(),INTERVAL 1 HOUR))")->fetchColumn();
$add('agent_queues', $count === 0 ? 'pass' : 'warn', $count === 0 ? 'No stale agent workflow or swarm runs.' : 'Stale agent runs require review.', ['count' => $count]);

foreach (mg_operations_demand_orchestration_health($pdo) as $check) {
    $add($check['key'], $check['status'], $check['summary'], $check['details']);
}

$failed = array_values(array_filter($checks, static fn(array $check): bool => $check['status'] === 'fail'));
$warnings = array_values(array_filter($checks, static fn(array $check): bool => $check['status'] === 'warn'));
mg_ok([
    'ready' => $failed === [],
    'status' => $failed !== [] ? 'fail' : ($warnings !== [] ? 'warn' : 'pass'),
    'checks' => $checks,
    'checked_by_user_id' => (int) $user['id'],
]);
