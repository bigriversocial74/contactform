<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-approvals.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    $stmt = $pdo->prepare("SELECT i.public_id item_public_id,i.action_key,i.title,i.reason,i.risk_level,i.confidence,i.suggested_payload_json,p.public_id plan_public_id,p.scope,p.summary,p.merchant_goal
        FROM ai_merchant_plan_items i
        INNER JOIN ai_merchant_plans p ON p.id=i.plan_id
        WHERE p.merchant_user_id=?
          AND i.status IN ('recommended','deferred','failed')
          AND i.suggested_payload_json LIKE '%merchant_agent_chat_creative_draft%'
        ORDER BY i.id DESC
        LIMIT 100");
    $stmt->execute([$merchantId]);
    $recipes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload = mg_ai_plan_json($row['suggested_payload_json'] ?? null);
        if ((string)($payload['source'] ?? '') !== 'merchant_agent_chat_creative_draft') continue;
        $approvalId = mg_agent_approval_id($merchantId, 'ai_plan', (string)$row['item_public_id']);
        $sourceChatId = (string)($payload['source_chat_message_id'] ?? '');
        $recipes[$approvalId] = [
            'approval_id' => $approvalId,
            'creative_draft' => true,
            'draft_type' => (string)($payload['draft_type'] ?? ''),
            'draft_label' => (string)($payload['draft_label'] ?? ''),
            'artifact_family' => (string)($payload['artifact_family'] ?? ''),
            'draft_title' => (string)($payload['draft_title'] ?? $row['title'] ?? ''),
            'draft_body' => (string)($payload['draft_body'] ?? $row['reason'] ?? ''),
            'recommended_campaign_type' => (string)($payload['recommended_campaign_type'] ?? ''),
            'recommended_reward_type' => (string)($payload['recommended_reward_type'] ?? ''),
            'recipe_key' => (string)($payload['recipe_key'] ?? ''),
            'channel_package' => is_array($payload['channel_package'] ?? null) ? $payload['channel_package'] : [],
            'draft_artifacts' => is_array($payload['draft_artifacts'] ?? null) ? $payload['draft_artifacts'] : [],
            'source_blocks' => is_array($payload['source_blocks'] ?? null) ? $payload['source_blocks'] : [],
            'source_cards' => is_array($payload['source_cards'] ?? null) ? $payload['source_cards'] : [],
            'source_chat_message_id' => $sourceChatId,
            'original_chat_url' => $sourceChatId !== '' ? '/merchant-agent-chat.php?chat=' . rawurlencode($sourceChatId) : '',
            'send_back_url' => $sourceChatId !== '' ? '/merchant-agent-chat.php?chat=' . rawurlencode($sourceChatId) . '&followup=1' : '/merchant-agent-chat.php',
            'edit_url' => str_contains((string)$row['action_key'], 'reward') || (string)($payload['draft_type'] ?? '') === 'reward' ? '/merchant-reward-templates.php' : '/merchant-campaigns.php',
            'action_key' => (string)($row['action_key'] ?? ''),
            'ai_plan_id' => (string)($row['plan_public_id'] ?? ''),
            'ai_plan_item_id' => (string)($row['item_public_id'] ?? ''),
        ];
    }
    mg_ok(['recipes' => $recipes], 'Campaign recipe review details loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.agent_approval_recipes.failed', 'Unable to load campaign recipe review details.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to load campaign recipe review details.', 500);
}
