<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-chat.php';
require_once dirname(__DIR__) . '/merchant-agent-memory.php';

function mg_ai_chat_memory_system_prompt(): string
{
    return mg_ai_chat_system_prompt() . "\n\nMemory guidance:\n- Use merchant_agent_memory when recommending next steps.\n- Prefer saved preferences and approved patterns.\n- Avoid ideas similar to rejected or too-risky feedback.\n- Do not recommend action keys listed in avoid_actions unless there is a clear merchant benefit and lower-risk review-only framing.\n";
}

function mg_ai_chat_send_with_memory(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)$user['id'];
    $message = mg_ai_chat_clean($input['message'] ?? '', 2000);
    if ($message === '') mg_fail('Enter a message for the merchant agent.', 422);
    $scope = strtolower(mg_ai_chat_clean($input['scope'] ?? 'overview', 40)) ?: 'overview';
    if (!in_array($scope, mg_ai_chat_allowed_scopes(), true)) $scope = 'overview';
    $days = max(7, min(365, (int)($input['days'] ?? 90)));
    $model = mg_ai_merchant_find_anthropic_model($pdo, null);
    $provider = mg_ai_merchant_provider($pdo, (int)$model['provider_id']);
    mg_ai_enforce_rate_limits($pdo, $provider, $model, $merchantId, null);
    $history = array_slice(mg_ai_chat_recent_messages($pdo, $merchantId, 12), -12);
    $context = mg_ai_merchant_context($pdo, $user, ['scope' => $scope === 'overview' ? 'all' : $scope, 'days' => $days, 'merchant_goal' => $message]);
    $memory = mg_agent_memory_prompt_context($pdo, $merchantId);
    $request = [
        'model' => (string)$model['model_key'],
        'max_tokens' => max(512, min(2200, (int)($input['max_tokens'] ?? 1400))),
        'temperature' => 0.25,
        'system' => mg_ai_chat_memory_system_prompt(),
        'messages' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'merchant_message' => $message,
                    'scope' => $scope,
                    'review_window_days' => $days,
                    'allowed_action_urls' => mg_ai_chat_allowed_links(),
                    'recent_chat_history' => $history,
                    'merchant_operating_snapshot' => $context,
                    'merchant_agent_memory' => $memory,
                    'bridge_instruction' => 'When useful, include review_action_key and review_payload so the merchant can send a card to the Agent Review Queue.',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]],
        ]],
    ];
    try {
        $rawResponse = mg_anthropic_messages($request);
        $text = mg_anthropic_text_from_response($rawResponse);
        try { $decoded = mg_anthropic_extract_json_object($text); } catch (Throwable) { $decoded = ['reply' => $text, 'cards' => []]; }
        $reply = mg_ai_chat_clean($decoded['reply'] ?? $text, 6000);
        if ($reply === '') $reply = 'I reviewed the merchant workspace. I do not have a safe recommendation to create yet.';
        $cards = mg_ai_chat_normalize_cards($decoded['cards'] ?? []);
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, null, 'completed', $rawResponse, ['source' => 'merchant_agent_chat', 'scope' => $scope, 'memory_used' => true]);
        $pdo->beginTransaction();
        $userId = mg_ai_chat_record_message($pdo, $merchantId, 'user', $message, [], ['scope' => $scope]);
        $assistantId = mg_ai_chat_record_message($pdo, $merchantId, 'assistant', $reply, $cards, ['scope' => $scope, 'model' => (string)$model['model_key'], 'memory_snapshot' => $memory]);
        $pdo->commit();
        return [
            'user_message' => ['id' => $userId, 'role' => 'user', 'body' => $message, 'cards' => [], 'scope' => $scope, 'created_at' => date('c')],
            'assistant_message' => ['id' => $assistantId, 'role' => 'assistant', 'body' => $reply, 'cards' => $cards, 'scope' => $scope, 'model' => (string)$model['model_key'], 'created_at' => date('c')],
            'state' => mg_ai_chat_public_state($pdo, $merchantId) + ['memory' => mg_agent_memory_summary($pdo, $merchantId)],
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, null, 'failed', [], ['source' => 'merchant_agent_chat', 'scope' => $scope, 'error' => $error->getMessage(), 'memory_used' => true]);
        mg_security_log('error', 'merchant.agent_chat.failed', 'Merchant agent chat failed.', ['exception_class' => $error::class, 'scope' => $scope], $merchantId);
        mg_fail('Unable to run merchant agent chat: ' . $error->getMessage(), 500);
    }
}
