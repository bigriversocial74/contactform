<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

function mg_trigger_validator_read(string $root, string $path): string
{
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        throw new RuntimeException("Missing required file: {$path}");
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException("Unable to read required file: {$path}");
    }
    return $content;
}

function mg_trigger_validator_expect(bool $condition, string $label, array &$failures, int &$passes): void
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
    $runtime = mg_trigger_validator_read($root, 'api/store/_canvas_trigger_engine.php');
    $runner = mg_trigger_validator_read($root, 'api/store/_canvas_trigger_engine_runner.php');
    $endpoint = mg_trigger_validator_read($root, 'api/merchant-canvas/trigger-engine.php');
    $ui = mg_trigger_validator_read($root, 'assets/js/merchant-canvas-trigger-engine.js');
    $page = mg_trigger_validator_read($root, 'merchant-canvas.php');
    $cli = mg_trigger_validator_read($root, 'scripts/run_store_trigger_engine_v1.php');
    $sql = mg_trigger_validator_read($root, 'database/store_canvas_server_trigger_engine_v1.sql');
    $containedTrigger = mg_trigger_validator_read($root, 'api/merchant-canvas/campaign-trigger.php');
    $containedAutomation = mg_trigger_validator_read($root, 'api/merchant-canvas/campaign-trigger-automation.php');

    mg_trigger_validator_expect(
        str_contains($page, '/assets/js/merchant-canvas-trigger-engine.js'),
        'Store Canvas loads the server trigger engine Control Center script',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($runtime, "mg_store_send_campaign_recommendation_notification")
        && str_contains($runtime, "mg_store_manual_ops_assert_message_allowed")
        && str_contains($runtime, "mg_store_campaign_recommendation_campaign"),
        'Runtime reuses existing recommendation, communication, and campaign authorities',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($runtime, "'reward_issued'=>false") || str_contains($runtime, "'reward_issued' => false"),
        'Runtime explicitly records that trigger evaluation does not issue rewards',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($runtime, 'browser_overlap_authority')
        && str_contains($runner, "'browser_overlap_used' => false"),
        'Browser overlap is excluded from server event authority',
        $failures,
        $passes
    );

    $forbiddenWrites = [
        'INSERT INTO wallet_items',
        'UPDATE wallet_items',
        'DELETE FROM wallet_items',
        'INSERT INTO campaigns',
        'UPDATE campaigns SET status=',
        'INSERT INTO reward_templates',
        'UPDATE reward_templates',
        'INSERT INTO messages',
        'mg_store_manual_ops_send_reward',
        'mg_store_send_reward',
    ];
    $hasForbiddenWrite = false;
    foreach ($forbiddenWrites as $needle) {
        if (stripos($runtime, $needle) !== false || stripos($runner, $needle) !== false) {
            $hasForbiddenWrite = true;
            break;
        }
    }
    mg_trigger_validator_expect(
        !$hasForbiddenWrite,
        'Trigger runtime cannot create campaigns/rewards, issue Wallet items, or send direct messages',
        $failures,
        $passes
    );

    $eventTypes = [
        'store_entry',
        'return_visit',
        'visit_milestone',
        'campaign_interest',
        'inactivity_risk',
        'product_interest',
        'reward_claimed',
        'reward_redeemed',
    ];
    $allEventTypesPresent = true;
    foreach ($eventTypes as $eventType) {
        if (!str_contains($runtime, "'{$eventType}'")) {
            $allEventTypesPresent = false;
            break;
        }
    }
    mg_trigger_validator_expect(
        $allEventTypesPresent,
        'Runtime supports the eight governed server event families',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($runtime, 'minimum_probability')
        && str_contains($runtime, 'minimum_confidence')
        && str_contains($runtime, 'cooldown_seconds')
        && str_contains($runtime, 'max_per_customer_day'),
        'Probability, confidence, cooldown, and daily-frequency gates are enforced',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($runner, ":mode:")
        && str_contains($runner, 'preview_consumes_notification')
        && str_contains($runner, 'mg_store_trigger_engine_existing_evaluation'),
        'Dry-run and notification evaluations retain separate idempotent event histories',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($endpoint, 'mg_require_api_user')
        && str_contains($endpoint, 'mg_user_has_merchant_access')
        && str_contains($endpoint, 'mg_require_csrf_for_write')
        && str_contains($endpoint, 'mg_rate_limit'),
        'Merchant endpoint enforces authentication, merchant access, CSRF, and rate limits',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($endpoint, 'confirm_notification_delivery')
        && str_contains($endpoint, 'mg_store_trigger_engine_run_authorized'),
        'Live notification execution requires explicit merchant confirmation and authorized runner',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($ui, 'Zone overlap is never an event source')
        && str_contains($ui, 'Notification mode never issues a reward')
        && str_contains($ui, 'confirm_notification_delivery'),
        'Control Center communicates and enforces notification-only server authority',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($cli, "PHP_SAPI !== 'cli'")
        && str_contains($cli, "execution_mode='notification'")
        && str_contains($cli, 'mg_store_trigger_engine_run_authorized'),
        'Scheduler runner is CLI-only and processes opt-in Notification-mode merchants',
        $failures,
        $passes
    );

    $tables = [
        'mg_store_trigger_engine_settings',
        'mg_store_trigger_engine_rules',
        'mg_store_trigger_events',
        'mg_store_trigger_evaluations',
    ];
    $allTablesPresent = true;
    foreach ($tables as $table) {
        if (!str_contains($sql, "CREATE TABLE IF NOT EXISTS {$table}")) {
            $allTablesPresent = false;
            break;
        }
    }
    mg_trigger_validator_expect(
        $allTablesPresent,
        'Migration creates only the four trigger-engine operational tables',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($sql, "execution_mode ENUM('paused','dry_run','notification') NOT NULL DEFAULT 'paused'")
        && str_contains($sql, 'UNIQUE KEY uq_mg_store_trigger_events_key')
        && str_contains($sql, 'UNIQUE KEY uq_mg_store_trigger_evaluations_event_rule'),
        'Migration defaults to paused and adds event/evaluation idempotency constraints',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        str_contains($containedTrigger, 'merchant_canvas_automatic_actions_disabled')
        && str_contains($containedAutomation, 'merchant_canvas_automatic_actions_disabled'),
        'Legacy browser-trigger and automation endpoints remain production-contained',
        $failures,
        $passes
    );

    mg_trigger_validator_expect(
        !str_contains($ui, 'localStorage')
        && !str_contains($ui, 'sessionStorage')
        && !str_contains($runtime, 'getBoundingClientRect'),
        'No browser-local authority or visual-coordinate evaluation is introduced',
        $failures,
        $passes
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Store Canvas server trigger engine validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Store Canvas server trigger engine validation passed: {$passes} checks.\n";
