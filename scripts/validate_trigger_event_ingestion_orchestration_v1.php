<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

function mg_ingestion_validator_read(string $root, string $path): string
{
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException("Missing required file: {$path}");
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException("Unable to read required file: {$path}");
    return $content;
}

function mg_ingestion_validator_expect(bool $condition, string $label, array &$failures, int &$passes): void
{
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
}

try {
    $runtime = mg_ingestion_validator_read($root, 'api/store/_canvas_trigger_orchestration.php');
    $runner = mg_ingestion_validator_read($root, 'api/store/_canvas_trigger_orchestration_runner.php');
    $rules = mg_ingestion_validator_read($root, 'api/store/_canvas_trigger_orchestration_rules.php');
    $endpoint = mg_ingestion_validator_read($root, 'api/merchant-canvas/trigger-orchestration.php');
    $campaignOpen = mg_ingestion_validator_read($root, 'api/public/campaigns/open.php');
    $product = mg_ingestion_validator_read($root, 'api/catalog/public-product.php');
    $publicCampaignJs = mg_ingestion_validator_read($root, 'assets/js/public-campaign.js');
    $ui = mg_ingestion_validator_read($root, 'assets/js/merchant-canvas-trigger-orchestration.js');
    $page = mg_ingestion_validator_read($root, 'merchant-canvas.php');
    $cli = mg_ingestion_validator_read($root, 'scripts/run_trigger_event_orchestration_v1.php');
    $sql = mg_ingestion_validator_read($root, 'database/trigger_event_ingestion_orchestration_v1.sql');

    mg_ingestion_validator_expect(
        str_contains($page, '/assets/js/merchant-canvas-trigger-orchestration.js')
        && str_contains($ui, 'data-trigger-orchestration-tab')
        && str_contains($ui, 'Customer interaction timeline'),
        'Store Canvas loads the ingestion and orchestration Control Center workspace',
        $failures,
        $passes
    );

    $sources = ['store_session_events','campaign_events','wallet_reconciliation','behavior_profiles'];
    $allSources = true;
    foreach ($sources as $source) {
        if (!str_contains($runtime, "'{$source}'")) { $allSources = false; break; }
    }
    mg_ingestion_validator_expect(
        $allSources
        && str_contains($runtime, 'mg_store_session_events')
        && str_contains($runtime, 'campaign_events')
        && str_contains($runtime, 'wallet_items')
        && str_contains($runtime, 'mg_merchant_customer_behavior_profiles'),
        'Runtime ingests the four canonical Store, Campaign, Wallet, and behavior sources',
        $failures,
        $passes
    );

    $eventTypes = [
        'store_entry','return_visit','visit_milestone','campaign_interest','inactivity_risk',
        'product_interest','campaign_opened','campaign_participated','reward_claimed','reward_redeemed',
    ];
    $allEventTypes = true;
    foreach ($eventTypes as $eventType) {
        if (!str_contains($runtime, "'{$eventType}'") && !str_contains($sql, "'{$eventType}'")) { $allEventTypes = false; break; }
    }
    mg_ingestion_validator_expect(
        $allEventTypes,
        'Ingestion and orchestration support all ten governed event families',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($runtime, 'mg_store_send_campaign_recommendation_notification')
        && str_contains($runtime, 'mg_store_campaign_recommendation_campaign')
        && str_contains($runtime, 'mg_store_manual_ops_assert_message_allowed')
        && str_contains($runtime, 'mg_store_trigger_engine_delivery_limits'),
        'Queue delivery reuses canonical Campaign readiness, DNM, frequency, and recommendation authorities',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($runtime, 'mg_trigger_orchestration_retry')
        && str_contains($runtime, 'mg_trigger_orchestration_dead_letter')
        && str_contains($runtime, "status='retry'")
        && str_contains($runtime, "status='dead_letter'"),
        'Transient failures retry and terminal failures enter a durable dead-letter queue',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($runtime, 'emergency_pause')
        && str_contains($runtime, 'mg_trigger_orchestration_quiet_retry_at')
        && str_contains($runtime, 'post_claim_suppression')
        && str_contains($runtime, 'post_redemption_suppression'),
        'Emergency pause, quiet hours, claim suppression, and redemption suppression are enforced',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($runner, 'preview_requeued_count')
        && str_contains($runner, 'preview_consumes_notification')
        && str_contains($runner, "execution_mode='dry_run'")
        && str_contains($runner, "status='pending'"),
        'Dry-run evidence is durable without consuming the later Notification-mode queue event',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($rules, 'mg_store_campaign_recommendation_campaign')
        && str_contains($rules, 'mg_store_trigger_engine_rules')
        && str_contains($rules, 'orchestration_policy_public_id')
        && str_contains($rules, "'wallet_write_allowed' => false"),
        'Policy rules bind to existing Campaigns and the canonical trigger-rule inventory',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($endpoint, 'mg_require_api_user')
        && str_contains($endpoint, 'mg_user_has_merchant_access')
        && str_contains($endpoint, 'mg_require_csrf_for_write')
        && str_contains($endpoint, 'mg_rate_limit'),
        'Merchant endpoint enforces authentication, merchant access, CSRF, and rate limits',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($endpoint, 'confirm_notification_delivery')
        && str_contains($endpoint, 'mg_trigger_orchestration_process_queue_authorized')
        && str_contains($endpoint, "'reward_issue'=>false"),
        'Live queue delivery requires explicit merchant confirmation and the preview-safe runner',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($cli, "PHP_SAPI !== 'cli'")
        && str_contains($cli, 'ingestion_enabled=1')
        && str_contains($cli, 'scheduler_enabled=1')
        && str_contains($cli, 'mg_trigger_orchestration_process_queue_authorized'),
        'Scheduler is CLI-only and processes only merchants who explicitly enable ingestion and scheduling',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($product, 'mg_store_active_session_for_customer')
        && str_contains($product, "'viewed_product'")
        && str_contains($product, "'server_authoritative'=>true")
        && str_contains($product, "'browser_overlap_used'=>false"),
        'Product-interest events require an authenticated customer inside the matching merchant Store Canvas',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($campaignOpen, 'mg_require_api_user')
        && str_contains($campaignOpen, 'mg_require_csrf_for_write')
        && str_contains($campaignOpen, 'mg_store_active_session_for_customer')
        && str_contains($campaignOpen, "'campaign.opened'")
        && str_contains($publicCampaignJs, '/api/public/campaigns/open.php'),
        'Public campaign opens are authenticated, Store-session scoped, deduplicated canonical campaign events',
        $failures,
        $passes
    );

    $newTables = [
        'mg_store_trigger_ingestion_checkpoints',
        'mg_store_trigger_orchestration_policies',
        'mg_store_trigger_scheduler_runs',
        'mg_store_trigger_dead_letters',
    ];
    $allTables = true;
    foreach ($newTables as $table) {
        if (!str_contains($sql, "CREATE TABLE IF NOT EXISTS {$table}")) { $allTables = false; break; }
    }
    mg_ingestion_validator_expect(
        $allTables,
        'Migration creates the four ingestion, policy, scheduler, and dead-letter operational tables',
        $failures,
        $passes
    );

    $queueFields = [
        'attempt_count','max_attempts','available_at','locked_at','locked_by','processed_at',
        'last_error_code','last_error_message','dead_lettered_at',
    ];
    $allQueueFields = true;
    foreach ($queueFields as $field) {
        if (!str_contains($sql, "COLUMN {$field}")) { $allQueueFields = false; break; }
    }
    mg_ingestion_validator_expect(
        $allQueueFields
        && str_contains($sql, 'idx_mg_store_trigger_events_queue')
        && str_contains($sql, 'uq_mg_store_trigger_evaluations_event_rule_mode'),
        'Migration adds queue locking, retry evidence, dead-letter fields, and mode-aware evaluation idempotency',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($sql, 'emergency_pause')
        && str_contains($sql, 'ingestion_enabled')
        && str_contains($sql, 'scheduler_enabled')
        && str_contains($sql, 'last_scheduler_heartbeat_at'),
        'Migration adds opt-in scheduler controls, emergency pause, and heartbeat health',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($ui, 'Run ingestion')
        && str_contains($ui, 'Dry preview queue')
        && str_contains($ui, 'Global emergency pause')
        && str_contains($ui, 'Ingestion-source health')
        && str_contains($ui, 'Scheduler and run history')
        && str_contains($ui, 'data-orchestration-retry'),
        'Control Center exposes source health, policies, dry runs, emergency pause, retries, and scheduler history',
        $failures,
        $passes
    );

    $authorityFiles = $runtime . "\n" . $runner . "\n" . $rules . "\n" . $endpoint . "\n" . $cli;
    $forbiddenWrites = [
        'INSERT INTO wallet_items',
        'UPDATE wallet_items SET',
        'DELETE FROM wallet_items',
        'INSERT INTO campaigns',
        'UPDATE campaigns SET',
        'INSERT INTO reward_templates',
        'UPDATE reward_templates SET',
        'INSERT INTO mg_agent_messages',
        'INSERT INTO messages',
        'mg_store_manual_ops_send_reward',
        'mg_store_send_reward',
    ];
    $hasForbiddenWrite = false;
    foreach ($forbiddenWrites as $needle) {
        if (stripos($authorityFiles, $needle) !== false) { $hasForbiddenWrite = true; break; }
    }
    mg_ingestion_validator_expect(
        !$hasForbiddenWrite,
        'Ingestion and orchestration cannot create campaigns/rewards, issue Wallet items, or send direct messages',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($runtime, "'reward_issued'=>false")
        && str_contains($runtime, "'browser_overlap_used'=>false")
        && str_contains($runtime, "'protected_traits_used' => false")
        && !str_contains($ui, 'localStorage')
        && !str_contains($ui, 'sessionStorage')
        && !str_contains($runtime, 'getBoundingClientRect'),
        'No visual-coordinate, protected-trait, or browser-local execution authority is introduced',
        $failures,
        $passes
    );

    mg_ingestion_validator_expect(
        str_contains($runtime, 'mg_trigger_orchestration_timeline')
        && str_contains($runtime, 'mg_trigger_orchestration_recent_runs')
        && str_contains($runtime, 'reason_code')
        && str_contains($runtime, 'reason_text'),
        'Merchant timeline and scheduler history preserve an explanation for every outcome',
        $failures,
        $passes
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Trigger ingestion and orchestration validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Trigger ingestion and orchestration validation passed: {$passes} checks.\n";
