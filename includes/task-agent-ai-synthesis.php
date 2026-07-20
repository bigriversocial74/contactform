<?php
declare(strict_types=1);

function mg_multi_agent_runtime_prompt(array $agent, array $template): string
{
    $name = (string)($agent['name'] ?? 'Specialized Agent');
    $key = (string)($template['key'] ?? '');
    $mission = match ($key) {
        'birthday_occasion' => 'Help the customer compare shortlisted gift options, explain fit, or write a thoughtful message using supplied recipient and occasion context.',
        'local_shopping' => 'Help the customer compare shortlisted local products, services, experiences, and creative work. Never invent inventory.',
        'merchant_campaign' => 'Help an authorized merchant compare campaign options or improve draft language. Never publish or change merchant data.',
        default => 'Help the user synthesize the focused context supplied for this agent.',
    };

    return "You are Microgifter's {$name}. {$mission}\n\nRules:\n"
        . "- Keep this agent's context separate from every other agent.\n"
        . "- Never reveal secrets, payment data, claim codes, direct contact details, or hidden profile data.\n"
        . "- Use only permission-safe context, sanitized memory, and the current published-product shortlist.\n"
        . "- Clearly disclose missing information and do not infer private facts.\n"
        . "- Do not purchase, publish, message, schedule, charge, claim, redeem, transfer, shortlist, or save data.\n"
        . "- All write actions are handled by deterministic server controls after user approval.\n"
        . "- Return JSON only: {\"reply\":\"helpful response\",\"cards\":[{\"type\":\"recommendation|question|warning\",\"title\":\"short title\",\"body\":\"specific content\",\"action\":\"none|seed_prompt\",\"prompt\":\"optional follow-up\"}]}";
}

function mg_task_agent_ai_synthesis(
    PDO $pdo,
    int $userId,
    array $agent,
    array $template,
    array $context,
    array $history,
    string $message,
    string $aiReason,
    string $requestedModelId,
    string $threadPublicId
): ?array {
    $packageContext = mg_ai_credit_package_context($pdo, $userId);
    if (!mg_personal_agent_ai_package_eligible($packageContext)) return null;

    $model = mg_personal_agent_model($pdo, $userId, $requestedModelId);
    if (!$model) return null;

    $maxOutput = max(350, min(900, (int)($model['max_output_tokens'] ?? 700)));
    mg_ai_credit_preflight($pdo, $userId, 'anthropic', $maxOutput, 'specialized_agent');

    $messages = [];
    foreach ($history as $item) {
        if (!in_array($item['role'] ?? '', ['user', 'assistant'], true)) continue;
        $messages[] = [
            'role' => $item['role'],
            'content' => mb_substr((string)($item['body'] ?? ''), 0, 1400),
        ];
    }

    $safeContext = mg_multi_agent_runtime_model_context($context, $message);
    $response = mg_anthropic_messages([
        'model' => (string)$model['model_key'],
        'max_tokens' => $maxOutput,
        'temperature' => 0.2,
        'system' => mg_multi_agent_runtime_prompt($agent, $template)
            . "\n\nAI call reason: {$aiReason}"
            . "\n\nPermission-safe focused context JSON:\n"
            . json_encode($safeContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'messages' => $messages,
    ]);

    $decoded = mg_anthropic_extract_json_object(mg_anthropic_text_from_response($response));
    $reply = mg_personal_agent_text($decoded['reply'] ?? '', 4000);
    if ($reply === '') throw new RuntimeException('AI returned an empty reply.');

    $raw = mg_anthropic_last_response();
    $usage = is_array($raw['usage'] ?? null) ? $raw['usage'] : [];
    $tokens = [
        'input' => (int)($usage['input_tokens'] ?? 0),
        'output' => (int)($usage['output_tokens'] ?? 0),
        'total' => (int)($usage['input_tokens'] ?? 0) + (int)($usage['output_tokens'] ?? 0),
    ];

    $credits = mg_ai_credit_consume(
        $pdo,
        $userId,
        (int)$model['provider_id'],
        (int)$model['id'],
        'anthropic',
        $tokens['input'],
        $tokens['output'],
        'specialized_agent',
        (string)($raw['id'] ?? ''),
        [
            'agent_id' => (string)$agent['public_id'],
            'thread_id' => $threadPublicId,
            'ai_reason' => $aiReason,
        ]
    );

    return [
        'result' => [
            'reply' => $reply,
            'cards' => mg_task_agent_sanitize_model_cards(is_array($decoded['cards'] ?? null) ? $decoded['cards'] : []),
        ],
        'model_key' => (string)$model['model_key'],
        'tokens' => $tokens,
        'credits' => $credits,
    ];
}
