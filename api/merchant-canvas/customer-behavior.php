<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_behavior_memory.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    $merchantUserId = (int)$user['id'];
    $sessionId = mg_store_safe_public_id($_GET['session_id'] ?? '', 'Store session');
    mg_rate_limit('merchant_canvas.customer_behavior', 'user:' . $merchantUserId, 180, 60);

    if (!mg_store_behavior_schema_ready($pdo)) {
        mg_fail('Store Canvas behavior memory setup is incomplete. Import database/merchant_canvas_behavior_memory_predictive_v1.sql.', 503);
    }

    $context = mg_store_analytics_session_context($pdo, $merchantUserId, $sessionId);
    $session = is_array($context['session'] ?? null) ? $context['session'] : [];
    $customerUserId = (int)($context['customer_user_id'] ?? 0);
    $metadata = mg_store_analytics_json($session['metadata_json'] ?? null);
    $isTest = !empty($metadata['test_canvas_avatar']) || (($metadata['source'] ?? '') === 'merchant_canvas_test_seed');

    $profile = $isTest
        ? mg_store_behavior_test_profile([
            'seconds_inside' => max(0, strtotime('now') - strtotime((string)($session['entered_at'] ?? 'now'))),
            'last_active_at' => $session['last_active_at'] ?? null,
        ])
        : mg_store_behavior_profile_sync($pdo, $merchantUserId, $customerUserId);

    mg_ok([
        'profile' => $profile,
        'integration' => [
            'crm' => ['authority' => 'merchant_customer_pair', 'href' => '/merchant-crm.php'],
            'contacts' => ['authority' => 'merchant_crm_contact', 'href' => '/merchant-crm.php'],
            'campaigns' => ['authority' => 'merchant_approved_campaign', 'href' => '/merchant-campaigns.php'],
            'merchant_memory' => ['authority' => 'merchant_agent_memory', 'href' => '/merchant-memory.php'],
            'wallet_pppm' => ['reward_authority' => 'campaign_completion', 'destination' => 'wallet_then_inbox_pppm'],
            'journey' => ['authority' => 'mg_merchant_canvas_journey_events'],
        ],
        'future_capabilities' => [
            'customer_to_customer_chat' => false,
            'customer_item_sending' => false,
            'peer_matching' => false,
            'activation_policy' => 'server_gated_future_phase',
        ],
        'generated_at' => gmdate('c'),
    ]);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    $status = str_contains(strtolower($error->getMessage()), 'setup is incomplete') ? 503 : 404;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.customer_behavior_failed', 'Store Canvas customer behavior memory failed.', ['exception_class' => $error::class], (int)$user['id']);
    mg_fail('Unable to load customer behavior memory.', 500);
}
