<?php
declare(strict_types=1);

require_once __DIR__ . '/anthropic-client.php';
require_once __DIR__ . '/merchant-context-builder.php';

function mg_ai_merchant_allowed_scopes(): array
{
    return ['all','campaigns','rewards','crm','claims','analytics','reports','developer_api','locations','onboarding'];
}

function mg_ai_merchant_allowed_actions(): array
{
    return [
        'create_product_draft',
        'update_product_draft',
        'create_campaign_draft',
        'update_campaign_draft',
        'pause_campaign',
        'resume_campaign',
        'create_reward_template_draft',
        'update_reward_template_draft',
        'create_crm_followup_task',
        'create_message_draft',
        'send_customer_message',
        'create_trigger_zone',
        'update_trigger_zone',
        'create_report_snapshot',
        'create_merchant_alert',
        'recommend_package_upgrade',
        'recommend_location_fix',
        'recommend_api_integration',
        'recommend_claim_review',
        'recommend_reward_optimization',
        'recommend_campaign_optimization',
    ];
}

function mg_ai_merchant_find_anthropic_model(PDO $pdo, ?int $agentId = null): array
{
    if ($agentId !== null) {
        $stmt = $pdo->prepare("SELECT m.*, p.provider_key, p.display_name provider_name, p.env_var_name, p.enabled provider_enabled FROM agent_ai_settings aas INNER JOIN ai_models m ON m.id = aas.model_id INNER JOIN ai_providers p ON p.id = aas.provider_id WHERE aas.agent_id = ? AND p.provider_key = 'anthropic' AND p.enabled = 1 AND m.enabled = 1 LIMIT 1");
        $stmt->execute([$agentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && mg_ai_env_configured((string) $row['env_var_name'])) return $row;
    }
    $stmt = $pdo->prepare("SELECT m.*, p.provider_key, p.display_name provider_name, p.env_var_name, p.enabled provider_enabled FROM ai_models m INNER JOIN ai_providers p ON p.id = m.provider_id WHERE p.provider_key = 'anthropic' AND p.enabled = 1 AND m.enabled = 1 AND m.model_key IN ('claude-sonnet-4-6','claude-3-5-sonnet-latest') ORDER BY (m.model_key = 'claude-sonnet-4-6') DESC, m.is_default DESC, m.sort_order ASC LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) mg_fail('Claude Sonnet is not enabled in the AI model catalog.', 503);
    if (!mg_ai_env_configured((string) $row['env_var_name'])) mg_fail('Anthropic is not configured on the server. Set MG_ANTHROPIC_API_KEY.', 503);
    return $row;
}

function mg_ai_merchant_provider(PDO $pdo, int $providerId): array
{
    $stmt = $pdo->prepare('SELECT * FROM ai_providers WHERE id = ? LIMIT 1');
    $stmt->execute([$providerId]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($provider) || !(bool) $provider['enabled']) mg_fail('AI provider is not enabled.', 503);
    return $provider;
}

function mg_ai_merchant_plan_system_prompt(): string
{
    return <<<'PROMPT'
You are Microgifter's supervised merchant automation planner.

Your job:
- Review the merchant operating snapshot.
- Recommend practical actions for products, rewards, campaigns, CRM follow-ups, customer message drafts, Store Canvas trigger zones, claims operations, reports, analytics, onboarding, and developer API opportunities.
- Return only valid JSON. No markdown. No prose outside JSON.

Hard safety boundaries:
- You do not move money, redeem claims, alter wallet ownership, or change PPPM lifecycle state.
- You may create approval-ready plans for products, rewards, campaigns, messages, CRM tasks, reports, alerts, and Store Canvas trigger zones only when the action key is allowed.
- High and critical risk recommendations must require approval.
- Every recommendation must be merchant-owned, explainable, reversible where possible, and attached to audit logs.
- Avoid customer-level private data. Use summaries and counts.

Return this JSON shape:
{
  "summary": "short merchant-facing summary",
  "priority": "low|medium|high|critical",
  "recommendations": [
    {
      "action_key": "one allowed action key",
      "title": "merchant-facing action title",
      "target_type": "product|campaign|reward_template|crm_contact|message|trigger_zone|claim|report|location|developer_api|workspace|alert|none",
      "target_reference": "optional known public id or stable reference",
      "risk_level": "low|medium|high|critical",
      "requires_approval": true,
      "confidence": 0.0,
      "reason": "why this action matters",
      "suggested_payload": {}
    }
  ]
}
PROMPT;
}

function mg_ai_merchant_plan_user_prompt(array $context, array $allowedActions): string
{
    return json_encode(['instruction' => 'Create a supervised merchant automation plan using only the allowed action keys.', 'allowed_action_keys' => $allowedActions, 'merchant_context' => $context], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
}

function mg_ai_merchant_clean_string(mixed $value, int $max = 500): string
{
    $text = trim((string) $value);
    return $text === '' ? '' : mb_substr($text, 0, $max);
}

function mg_ai_merchant_normalize_plan_payload(array $payload): array
{
    $allowedActions = mg_ai_merchant_allowed_actions();
    $allowedRisks = ['low','medium','high','critical'];
    $allowedPriorities = ['low','medium','high','critical'];
    $summary = mg_ai_merchant_clean_string($payload['summary'] ?? '', 2000);
    if ($summary === '') $summary = 'Claude created a supervised merchant automation plan.';
    $priority = strtolower(mg_ai_merchant_clean_string($payload['priority'] ?? 'medium', 20));
    if (!in_array($priority, $allowedPriorities, true)) $priority = 'medium';
    $items = [];
    foreach ((array)($payload['recommendations'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $actionKey = strtolower(mg_ai_merchant_clean_string($item['action_key'] ?? '', 120));
        if (!in_array($actionKey, $allowedActions, true)) continue;
        $title = mg_ai_merchant_clean_string($item['title'] ?? '', 220);
        if ($title === '') continue;
        $risk = strtolower(mg_ai_merchant_clean_string($item['risk_level'] ?? 'medium', 20));
        if (!in_array($risk, $allowedRisks, true)) $risk = 'medium';
        $confidence = is_numeric($item['confidence'] ?? null) ? max(0.0, min(1.0, (float)$item['confidence'])) : null;
        $suggestedPayload = $item['suggested_payload'] ?? [];
        if (!is_array($suggestedPayload)) $suggestedPayload = [];
        $items[] = ['action_key' => $actionKey, 'title' => $title, 'target_type' => mg_ai_merchant_clean_string($item['target_type'] ?? 'none', 120) ?: 'none', 'target_reference' => mg_ai_merchant_clean_string($item['target_reference'] ?? '', 190) ?: null, 'risk_level' => $risk, 'requires_approval' => true, 'confidence' => $confidence, 'reason' => mg_ai_merchant_clean_string($item['reason'] ?? '', 4000), 'suggested_payload' => $suggestedPayload];
        if (count($items) >= 10) break;
    }
    if ($items === []) $items[] = ['action_key' => 'create_report_snapshot', 'title' => 'Generate a merchant performance report', 'target_type' => 'report', 'target_reference' => null, 'risk_level' => 'low', 'requires_approval' => true, 'confidence' => 0.5, 'reason' => 'No specific safe action was returned, so the fallback is a reviewable report snapshot.', 'suggested_payload' => ['scope' => 'merchant_summary']];
    return ['summary' => $summary, 'priority' => $priority, 'recommendations' => $items];
}

function mg_ai_merchant_record_usage_event(PDO $pdo, int $providerId, int $modelId, int $userId, ?int $agentId, string $status, array $response = [], array $metadata = []): void
{
    try {
        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
        $stmt = $pdo->prepare('INSERT INTO ai_usage_events (provider_id,model_id,user_id,agent_id,request_status,request_units,input_tokens,output_tokens,metadata_json,created_at) VALUES (?,?,?,?,?,0,?,?,?,NOW())');
        $stmt->execute([$providerId, $modelId, $userId, $agentId, $status, max(0, (int)($usage['input_tokens'] ?? 0)), max(0, (int)($usage['output_tokens'] ?? 0)), $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'ai.usage_completion_log_failed', 'Unable to log AI usage completion.', ['exception_class' => $error::class, 'status' => $status], $userId);
    }
}

function mg_ai_merchant_public_plan(array $plan, array $items): array
{
    return ['id' => (string)$plan['public_id'], 'scope' => (string)$plan['scope'], 'merchant_goal' => (string)($plan['merchant_goal'] ?? ''), 'status' => (string)$plan['status'], 'priority' => (string)$plan['priority'], 'summary' => (string)($plan['summary'] ?? ''), 'model' => ['provider' => (string)($plan['provider_key'] ?? 'anthropic'), 'model_key' => (string)($plan['model_key'] ?? '')], 'usage' => ['input_tokens' => (int)($plan['input_tokens'] ?? 0), 'output_tokens' => (int)($plan['output_tokens'] ?? 0)], 'items' => array_map(static function (array $item): array { $payload = []; if (!empty($item['suggested_payload_json'])) { $decoded = json_decode((string)$item['suggested_payload_json'], true); $payload = is_array($decoded) ? $decoded : []; } return ['id' => (string)$item['public_id'], 'sequence_no' => (int)$item['sequence_no'], 'action_key' => (string)$item['action_key'], 'target_type' => (string)$item['target_type'], 'target_reference' => $item['target_reference'] !== null ? (string)$item['target_reference'] : null, 'risk_level' => (string)$item['risk_level'], 'requires_approval' => (bool)$item['requires_approval'], 'confidence' => $item['confidence'] !== null ? (float)$item['confidence'] : null, 'title' => (string)$item['title'], 'reason' => (string)($item['reason'] ?? ''), 'suggested_payload' => $payload, 'status' => (string)$item['status']]; }, $items), 'created_at' => $plan['created_at'] ?? null];
}

function mg_ai_merchant_create_plan(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)$user['id'];
    $scope = strtolower(trim((string)($input['scope'] ?? 'all'))) ?: 'all';
    if (!in_array($scope, mg_ai_merchant_allowed_scopes(), true)) mg_fail('Invalid merchant AI planning scope.', 422);
    $agent = null;
    $agentPublicId = trim((string)($input['agent_id'] ?? ''));
    if ($agentPublicId !== '') $agent = mg_agent_require_owned($merchantId, $agentPublicId);
    $model = mg_ai_merchant_find_anthropic_model($pdo, $agent ? (int)$agent['id'] : null);
    $provider = mg_ai_merchant_provider($pdo, (int)$model['provider_id']);
    mg_ai_enforce_rate_limits($pdo, $provider, $model, $merchantId, $agent ? (int)$agent['id'] : null);
    $context = mg_ai_merchant_context($pdo, $user, $input);
    $allowedActions = mg_ai_merchant_allowed_actions();
    $system = mg_ai_merchant_plan_system_prompt();
    $userPrompt = mg_ai_merchant_plan_user_prompt($context, $allowedActions);
    $request = ['model' => (string)$model['model_key'], 'max_tokens' => max(1024, min(6000, (int)($input['max_tokens'] ?? 3000))), 'temperature' => 0.2, 'system' => $system, 'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $userPrompt]]]]];
    $rawResponse = [];
    try {
        $rawResponse = mg_anthropic_messages($request);
        $text = mg_anthropic_text_from_response($rawResponse);
        $decoded = mg_anthropic_extract_json_object($text);
        $normalized = mg_ai_merchant_normalize_plan_payload($decoded);
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, $agent ? (int)$agent['id'] : null, 'completed', $rawResponse, ['source' => 'merchant_agent_plan']);
        $usage = is_array($rawResponse['usage'] ?? null) ? $rawResponse['usage'] : [];
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $responseJson = json_encode($rawResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fingerprint = hash('sha256', $system . "\n" . $userPrompt);
        $pdo->beginTransaction();
        $planPublicId = mg_merchant_uuid();
        $stmt = $pdo->prepare('INSERT INTO ai_merchant_plans (public_id,merchant_user_id,agent_id,provider_id,model_id,scope,merchant_goal,status,priority,summary,prompt_fingerprint,input_context_json,raw_response_json,input_tokens,output_tokens,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$planPublicId, $merchantId, $agent ? (int)$agent['id'] : null, (int)$provider['id'], (int)$model['id'], $scope, mg_ai_merchant_clean_string($input['merchant_goal'] ?? '', 1000) ?: null, 'review_ready', $normalized['priority'], $normalized['summary'], $fingerprint, $contextJson, $responseJson, max(0, (int)($usage['input_tokens'] ?? 0)), max(0, (int)($usage['output_tokens'] ?? 0)), $merchantId]);
        $planId = (int)$pdo->lastInsertId();
        $itemStmt = $pdo->prepare('INSERT INTO ai_merchant_plan_items (public_id,plan_id,sequence_no,action_key,target_type,target_reference,risk_level,requires_approval,confidence,title,reason,suggested_payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'recommended\',NOW(),NOW())');
        $sequence = 0;
        foreach ($normalized['recommendations'] as $item) {
            $sequence++;
            $itemStmt->execute([mg_merchant_uuid(), $planId, $sequence, $item['action_key'], $item['target_type'], $item['target_reference'], $item['risk_level'], 1, $item['confidence'], $item['title'], $item['reason'], json_encode($item['suggested_payload'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        }
        $planStmt = $pdo->prepare('SELECT p.*, ap.provider_key, m.model_key FROM ai_merchant_plans p INNER JOIN ai_providers ap ON ap.id = p.provider_id INNER JOIN ai_models m ON m.id = p.model_id WHERE p.id = ? LIMIT 1');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        $items = mg_ai_context_rows($pdo, 'SELECT * FROM ai_merchant_plan_items WHERE plan_id = ? ORDER BY sequence_no ASC', [$planId], 50);
        mg_audit('merchant.ai_plan_created', 'ai_merchant_plan', ['plan_id' => $planPublicId, 'scope' => $scope, 'item_count' => count($items), 'model_key' => (string)$model['model_key']], $merchantId);
        mg_event('merchant.ai_plan_created', ['plan_id' => $planPublicId, 'scope' => $scope, 'item_count' => count($items)], $merchantId);
        $pdo->commit();
        return mg_ai_merchant_public_plan($plan ?: [], $items);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, $agent ? (int)$agent['id'] : null, 'failed', $rawResponse, ['source' => 'merchant_agent_plan', 'exception_class' => $error::class]);
        mg_security_log('error', 'merchant.ai_plan_failed', 'Merchant AI plan failed.', ['exception_class' => $error::class, 'scope' => $scope], $merchantId);
        mg_fail('Unable to create merchant AI plan: ' . $error->getMessage(), 502);
    }
}
