<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-planner.php';

function mg_ai_chat_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mg_ai_chat_clean(mixed $value, int $max = 2000): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return mb_substr($text, 0, $max);
}

function mg_ai_chat_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_ai_chat_allowed_scopes(): array
{
    return ['overview','campaigns','rewards','crm','claims','analytics','developer_api','locations','onboarding'];
}

function mg_ai_chat_allowed_links(): array
{
    return [
        '/merchant.php',
        '/merchant-agent-chat.php',
        '/merchant-automation.php',
        '/merchant-agent-monitor.php',
        '/merchant-agent-approvals.php',
        '/merchant-agent-execution.php',
        '/merchant-agent-messages.php',
        '/merchant-campaigns.php',
        '/merchant-reward-templates.php',
        '/merchant-crm.php',
        '/merchant-followups.php',
        '/merchant-claims.php',
        '/merchant-intelligence.php',
        '/merchant-locations.php',
        '/merchant-distribution.php',
        '/account-subscriptions.php',
    ];
}

function mg_ai_chat_normalize_cards(mixed $cards): array
{
    if (!is_array($cards)) return [];
    $allowedLinks = mg_ai_chat_allowed_links();
    $out = [];
    foreach ($cards as $card) {
        if (!is_array($card)) continue;
        $title = mg_ai_chat_clean($card['title'] ?? '', 120);
        $body = mg_ai_chat_clean($card['body'] ?? $card['description'] ?? '', 500);
        if ($title === '' && $body === '') continue;
        $url = mg_ai_chat_clean($card['action_url'] ?? $card['url'] ?? '', 220);
        if ($url !== '' && !in_array($url, $allowedLinks, true)) $url = '';
        $out[] = [
            'type' => mg_ai_chat_clean($card['type'] ?? 'recommendation', 40) ?: 'recommendation',
            'title' => $title !== '' ? $title : 'Agent recommendation',
            'body' => $body,
            'action_label' => mg_ai_chat_clean($card['action_label'] ?? ($url !== '' ? 'Open' : ''), 60),
            'action_url' => $url,
        ];
        if (count($out) >= 4) break;
    }
    return $out;
}

function mg_ai_chat_system_prompt(): string
{
    return <<<'PROMPT'
You are Microgifter's merchant agent chat.

You answer in a chat-style feed for a local merchant using Microgifter.

Hard rules:
- You are advisory only. Do not execute actions.
- Do not issue rewards, redeem claims, move money, change payment state, send customer messages, alter wallet ownership, or change PPPM lifecycle state.
- You may recommend next steps, explain merchant data, point the merchant to the right Microgifter page, and suggest draft/review actions.
- Use merchant-facing language. Be direct, specific, and brief.
- Avoid customer-level private data. Use summaries, counts, trends, and operational observations.
- Return valid JSON only. No markdown. No prose outside JSON.

Return this JSON shape:
{
  "reply": "chat reply for the merchant",
  "cards": [
    {
      "type": "insight|recommendation|warning|next_step",
      "title": "short card title",
      "body": "short supporting detail",
      "action_label": "optional button label",
      "action_url": "optional app URL from the allowed links list"
    }
  ]
}
PROMPT;
}

function mg_ai_chat_recent_messages(PDO $pdo, int $merchantId, int $limit = 30): array
{
    $limit = max(1, min(60, $limit));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('merchant.agent_chat.user','merchant.agent_chat.assistant') ORDER BY id DESC LIMIT {$limit}");
    $stmt->execute([$merchantId]);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    return array_map(static function (array $row): array {
        $ctx = mg_ai_chat_json($row['event_context_json'] ?? null);
        $role = (string)($ctx['role'] ?? ((string)$row['event_type'] === 'merchant.agent_chat.user' ? 'user' : 'assistant'));
        return [
            'id' => (string) $row['public_id'],
            'role' => $role,
            'body' => (string) ($ctx['body'] ?? ''),
            'cards' => mg_ai_chat_normalize_cards($ctx['cards'] ?? []),
            'model' => (string) ($ctx['model'] ?? ''),
            'scope' => (string) ($ctx['scope'] ?? 'overview'),
            'created_at' => $row['created_at'] ?? null,
        ];
    }, $rows);
}

function mg_ai_chat_record_message(PDO $pdo, int $merchantId, string $role, string $body, array $cards = [], array $meta = []): string
{
    $publicId = mg_ai_chat_uuid();
    $eventType = $role === 'user' ? 'merchant.agent_chat.user' : 'merchant.agent_chat.assistant';
    $context = array_merge([
        'role' => $role,
        'body' => mg_ai_chat_clean($body, 6000),
        'cards' => mg_ai_chat_normalize_cards($cards),
        'source' => 'merchant_agent_chat',
        'guardrail_applied' => 'Merchant agent chat is advisory. It cannot execute financial, claim, wallet, redemption, or customer-send actions.',
    ], $meta);
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([$publicId, $merchantId, null, null, $eventType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return $publicId;
}

function mg_ai_chat_public_state(PDO $pdo, int $merchantId): array
{
    return [
        'messages' => mg_ai_chat_recent_messages($pdo, $merchantId),
        'quick_prompts' => [
            'What should I focus on today?',
            'Review my campaigns and rewards.',
            'Find CRM follow-up opportunities.',
            'What needs approval or review?',
        ],
        'scopes' => mg_ai_chat_allowed_scopes(),
    ];
}

function mg_ai_chat_send(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int) $user['id'];
    $message = mg_ai_chat_clean($input['message'] ?? '', 2000);
    if ($message === '') mg_fail('Enter a message for the merchant agent.', 422);

    $scope = strtolower(mg_ai_chat_clean($input['scope'] ?? 'overview', 40)) ?: 'overview';
    if (!in_array($scope, mg_ai_chat_allowed_scopes(), true)) $scope = 'overview';
    $days = max(7, min(365, (int) ($input['days'] ?? 90)));

    $model = mg_ai_merchant_find_anthropic_model($pdo, null);
    $provider = mg_ai_merchant_provider($pdo, (int) $model['provider_id']);
    mg_ai_enforce_rate_limits($pdo, $provider, $model, $merchantId, null);

    $history = array_slice(mg_ai_chat_recent_messages($pdo, $merchantId, 12), -12);
    $context = mg_ai_merchant_context($pdo, $user, [
        'scope' => $scope === 'overview' ? 'all' : $scope,
        'days' => $days,
        'merchant_goal' => $message,
    ]);

    $request = [
        'model' => (string) $model['model_key'],
        'max_tokens' => max(512, min(2200, (int) ($input['max_tokens'] ?? 1400))),
        'temperature' => 0.25,
        'system' => mg_ai_chat_system_prompt(),
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
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]],
        ]],
    ];

    try {
        $rawResponse = mg_anthropic_messages($request);
        $text = mg_anthropic_text_from_response($rawResponse);
        try {
            $decoded = mg_anthropic_extract_json_object($text);
        } catch (Throwable) {
            $decoded = ['reply' => $text, 'cards' => []];
        }
        $reply = mg_ai_chat_clean($decoded['reply'] ?? $text, 6000);
        if ($reply === '') $reply = 'I reviewed the merchant workspace. I do not have a safe recommendation to create yet.';
        $cards = mg_ai_chat_normalize_cards($decoded['cards'] ?? []);
        mg_ai_merchant_record_usage_event($pdo, (int) $provider['id'], (int) $model['id'], $merchantId, null, 'completed', $rawResponse, ['source' => 'merchant_agent_chat', 'scope' => $scope]);

        $pdo->beginTransaction();
        $userId = mg_ai_chat_record_message($pdo, $merchantId, 'user', $message, [], ['scope' => $scope]);
        $assistantId = mg_ai_chat_record_message($pdo, $merchantId, 'assistant', $reply, $cards, [
            'scope' => $scope,
            'model' => (string) $model['model_key'],
        ]);
        $pdo->commit();

        return [
            'user_message' => ['id' => $userId, 'role' => 'user', 'body' => $message, 'cards' => [], 'scope' => $scope, 'created_at' => date('c')],
            'assistant_message' => ['id' => $assistantId, 'role' => 'assistant', 'body' => $reply, 'cards' => $cards, 'scope' => $scope, 'model' => (string) $model['model_key'], 'created_at' => date('c')],
            'state' => mg_ai_chat_public_state($pdo, $merchantId),
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_ai_merchant_record_usage_event($pdo, (int) $provider['id'], (int) $model['id'], $merchantId, null, 'failed', [], ['source' => 'merchant_agent_chat', 'scope' => $scope, 'error' => $error->getMessage()]);
        mg_security_log('error', 'merchant.agent_chat.failed', 'Merchant agent chat failed.', [
            'exception_class' => $error::class,
            'scope' => $scope,
        ], $merchantId);
        mg_fail('Unable to run merchant agent chat: ' . $error->getMessage(), 500);
    }
}
