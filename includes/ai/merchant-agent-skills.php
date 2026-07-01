<?php
declare(strict_types=1);

function mg_agent_skill_catalog(): array
{
    return [
        [
            'key' => 'merchant_analysis_charts',
            'label' => 'Analysis + charts',
            'description' => 'Analyze merchant products, campaigns, claims, redemptions, customer segments, and opportunities. Return chat-rendered chart, metric, forecast, and product-opportunity blocks when useful.',
            'default_enabled' => true,
            'block_types' => ['chart','metric_grid','forecast','product_opportunity','project'],
        ],
        [
            'key' => 'social_campaign_advisor',
            'label' => 'Social campaigns',
            'description' => 'Create social media campaign advice, channel-specific post drafts, CTA ideas, reward angles, and approval-ready campaign projects based on merchant data.',
            'default_enabled' => true,
            'block_types' => ['social_campaign','social_posts','project'],
        ],
    ];
}

function mg_agent_skill_keys(mixed $value = null): array
{
    $catalog = mg_agent_skill_catalog();
    $allowed = array_column($catalog, 'key');
    if ($value === null || $value === '') {
        return array_values(array_filter($allowed, static function (string $key) use ($catalog): bool {
            foreach ($catalog as $skill) {
                if ($skill['key'] === $key) return !empty($skill['default_enabled']);
            }
            return false;
        }));
    }
    if (is_string($value)) {
        $value = preg_split('/[\s,]+/', $value) ?: [];
    }
    if (!is_array($value)) return mg_agent_skill_keys(null);
    $keys = [];
    foreach ($value as $key) {
        $key = strtolower(trim((string)$key));
        if ($key !== '' && in_array($key, $allowed, true) && !in_array($key, $keys, true)) {
            $keys[] = $key;
        }
    }
    return $keys ?: mg_agent_skill_keys(null);
}

function mg_agent_skills_public(array $enabledKeys = []): array
{
    $enabledKeys = $enabledKeys ?: mg_agent_skill_keys(null);
    return array_map(static function (array $skill) use ($enabledKeys): array {
        return [
            'key' => $skill['key'],
            'label' => $skill['label'],
            'description' => $skill['description'],
            'enabled' => in_array($skill['key'], $enabledKeys, true),
            'block_types' => $skill['block_types'],
        ];
    }, mg_agent_skill_catalog());
}

function mg_agent_skill_prompt_context(array $enabledKeys): array
{
    $enabledKeys = mg_agent_skill_keys($enabledKeys);
    $skills = [];
    foreach (mg_agent_skill_catalog() as $skill) {
        if (!in_array($skill['key'], $enabledKeys, true)) continue;
        $skills[] = [
            'key' => $skill['key'],
            'description' => $skill['description'],
            'allowed_block_types' => $skill['block_types'],
        ];
    }
    return $skills;
}

function mg_agent_skill_system_prompt(): string
{
    return <<<'PROMPT'
Agent skill system:
- Skills are modular. Only use the skills listed in enabled_skills for the current request.
- merchant_analysis_charts may return blocks of type chart, metric_grid, forecast, product_opportunity, and project.
- social_campaign_advisor may return blocks of type social_campaign, social_posts, and project.
- Charts and analysis must render inside the chat response as blocks. Do not tell the merchant to go to another page for the chart.
- Use real merchant operating snapshot data when present. If data is incomplete, clearly label the block as a directional estimate.
- Never invent customer-level private data. Use summarized counts, categories, trends, product names, campaign names, and operational patterns.
- Social campaign advice should align with products, rewards, campaigns, and merchant behavior already visible in the operating snapshot.
- Projects are draft/review objects only. They must require approval before campaign launch, message send, product publish, reward issue, claim update, or wallet action.
PROMPT;
}

function mg_agent_soul_prompt(): string
{
    $path = __DIR__ . '/souls/merchant-agent-soul.md';
    if (is_file($path)) {
        $text = trim((string)file_get_contents($path));
        if ($text !== '') return $text;
    }
    return 'Merchant Agent Soul: Be a direct, practical merchant growth analyst. Keep all recommendations advisory and approval-first.';
}

function mg_agent_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $pdo->query('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');
        return $cache[$table] = true;
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_agent_profile_default(int $merchantId): array
{
    return [
        'id' => '',
        'merchant_user_id' => $merchantId,
        'agent_name' => 'Merchant Agent',
        'agent_role' => 'Merchant growth intelligence advisor',
        'agent_tone' => 'direct',
        'soul_version' => 'merchant-agent-soul-v1',
        'updated_at' => null,
    ];
}

function mg_agent_profile(PDO $pdo, int $merchantId): array
{
    if (!mg_agent_table_exists($pdo, 'merchant_agent_profiles')) return mg_agent_profile_default($merchantId);
    try {
        $stmt = $pdo->prepare('SELECT public_id,merchant_user_id,agent_name,agent_role,agent_tone,soul_version,updated_at FROM merchant_agent_profiles WHERE merchant_user_id=? LIMIT 1');
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return mg_agent_profile_default($merchantId);
        return [
            'id' => (string)$row['public_id'],
            'merchant_user_id' => (int)$row['merchant_user_id'],
            'agent_name' => (string)($row['agent_name'] ?: 'Merchant Agent'),
            'agent_role' => (string)($row['agent_role'] ?: 'Merchant growth intelligence advisor'),
            'agent_tone' => (string)($row['agent_tone'] ?: 'direct'),
            'soul_version' => (string)($row['soul_version'] ?: 'merchant-agent-soul-v1'),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    } catch (Throwable) {
        return mg_agent_profile_default($merchantId);
    }
}

function mg_agent_save_profile(PDO $pdo, int $merchantId, array $input): array
{
    if (!mg_agent_table_exists($pdo, 'merchant_agent_profiles')) {
        mg_fail('Run the merchant agent skills SQL migration before saving agent profile settings.', 500);
    }
    $name = mg_ai_chat_clean($input['agent_name'] ?? 'Merchant Agent', 80) ?: 'Merchant Agent';
    $role = mg_ai_chat_clean($input['agent_role'] ?? 'Merchant growth intelligence advisor', 160) ?: 'Merchant growth intelligence advisor';
    $tone = strtolower(mg_ai_chat_clean($input['agent_tone'] ?? 'direct', 40)) ?: 'direct';
    $publicId = mg_ai_chat_uuid();
    $stmt = $pdo->prepare("INSERT INTO merchant_agent_profiles (public_id,merchant_user_id,agent_name,agent_role,agent_tone,soul_version,created_at,updated_at) VALUES (?,?,?,?,?,'merchant-agent-soul-v1',NOW(),NOW()) ON DUPLICATE KEY UPDATE agent_name=VALUES(agent_name),agent_role=VALUES(agent_role),agent_tone=VALUES(agent_tone),soul_version=VALUES(soul_version),updated_at=NOW()");
    $stmt->execute([$publicId, $merchantId, $name, $role, $tone]);
    return mg_agent_profile($pdo, $merchantId);
}

function mg_agent_thread_default(int $merchantId): array
{
    return [
        'id' => '',
        'merchant_user_id' => $merchantId,
        'title' => 'Current chat',
        'status' => 'active',
        'saved_at' => null,
        'archived_at' => null,
        'cleared_at' => null,
        'updated_at' => null,
    ];
}

function mg_agent_thread_row(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'merchant_user_id' => (int)$row['merchant_user_id'],
        'title' => (string)($row['title'] ?: 'Agent chat'),
        'status' => (string)($row['status'] ?: 'active'),
        'saved_at' => $row['saved_at'] ?? null,
        'archived_at' => $row['archived_at'] ?? null,
        'cleared_at' => $row['cleared_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_agent_active_thread(PDO $pdo, int $merchantId): array
{
    if (!mg_agent_table_exists($pdo, 'merchant_agent_threads')) return mg_agent_thread_default($merchantId);
    try {
        $stmt = $pdo->prepare("SELECT * FROM merchant_agent_threads WHERE merchant_user_id=? AND status='active' ORDER BY updated_at DESC,id DESC LIMIT 1");
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) return mg_agent_thread_row($row);
        return mg_agent_create_thread($pdo, $merchantId, ['title' => 'Current chat'], true);
    } catch (Throwable) {
        return mg_agent_thread_default($merchantId);
    }
}

function mg_agent_thread_by_id(PDO $pdo, int $merchantId, string $threadPublicId): array
{
    if ($threadPublicId === '' || !mg_agent_table_exists($pdo, 'merchant_agent_threads')) return mg_agent_active_thread($pdo, $merchantId);
    $stmt = $pdo->prepare('SELECT * FROM merchant_agent_threads WHERE merchant_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$merchantId, $threadPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? mg_agent_thread_row($row) : mg_agent_active_thread($pdo, $merchantId);
}

function mg_agent_threads(PDO $pdo, int $merchantId, int $limit = 12): array
{
    if (!mg_agent_table_exists($pdo, 'merchant_agent_threads')) return [];
    try {
        $limit = max(1, min(30, $limit));
        $stmt = $pdo->prepare("SELECT * FROM merchant_agent_threads WHERE merchant_user_id=? AND status IN ('active','saved') ORDER BY status='active' DESC,updated_at DESC,id DESC LIMIT {$limit}");
        $stmt->execute([$merchantId]);
        return array_map('mg_agent_thread_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_create_thread(PDO $pdo, int $merchantId, array $input = [], bool $makeActive = true): array
{
    if (!mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
        mg_fail('Run the merchant agent skills SQL migration before creating agent chat threads.', 500);
    }
    if ($makeActive) {
        $pdo->prepare("UPDATE merchant_agent_threads SET status=IF(status='active','saved',status),saved_at=IF(status='active',COALESCE(saved_at,NOW()),saved_at),updated_at=NOW() WHERE merchant_user_id=? AND status='active'")->execute([$merchantId]);
    }
    $title = mg_ai_chat_clean($input['title'] ?? 'Current chat', 120) ?: 'Current chat';
    $publicId = mg_ai_chat_uuid();
    $stmt = $pdo->prepare("INSERT INTO merchant_agent_threads (public_id,merchant_user_id,agent_profile_id,title,status,created_at,updated_at) VALUES (?,?,(SELECT id FROM merchant_agent_profiles WHERE merchant_user_id=? LIMIT 1),?,'active',NOW(),NOW())");
    $stmt->execute([$publicId, $merchantId, $merchantId, $title]);
    return mg_agent_thread_by_id($pdo, $merchantId, $publicId);
}

function mg_agent_thread_action(PDO $pdo, int $merchantId, string $threadPublicId, string $action, array $input = []): array
{
    if (!mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
        mg_fail('Run the merchant agent skills SQL migration before updating agent chat threads.', 500);
    }
    $thread = mg_agent_thread_by_id($pdo, $merchantId, $threadPublicId);
    $threadPublicId = $thread['id'];
    if ($threadPublicId === '') mg_fail('Agent chat thread was not found.', 404);
    if ($action === 'save') {
        $pdo->prepare("UPDATE merchant_agent_threads SET status='saved',saved_at=COALESCE(saved_at,NOW()),archived_at=NULL,updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")->execute([$merchantId,$threadPublicId]);
    } elseif ($action === 'archive') {
        $pdo->prepare("UPDATE merchant_agent_threads SET status='archived',archived_at=NOW(),updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")->execute([$merchantId,$threadPublicId]);
        mg_agent_create_thread($pdo, $merchantId, ['title' => 'Current chat'], true);
    } elseif ($action === 'clear') {
        $pdo->prepare("UPDATE merchant_agent_threads SET cleared_at=NOW(),updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")->execute([$merchantId,$threadPublicId]);
    } elseif ($action === 'rename') {
        $title = mg_ai_chat_clean($input['title'] ?? '', 120);
        if ($title === '') mg_fail('Enter a thread title.', 422);
        $pdo->prepare("UPDATE merchant_agent_threads SET title=?,updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")->execute([$title,$merchantId,$threadPublicId]);
    }
    return mg_agent_active_thread($pdo, $merchantId);
}

function mg_agent_chat_normalize_blocks(mixed $blocks): array
{
    if (!is_array($blocks)) return [];
    $out = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) continue;
        $type = strtolower(mg_ai_chat_clean($block['type'] ?? '', 40));
        if (!in_array($type, ['chart','metric_grid','forecast','product_opportunity','social_campaign','social_posts','project','warning','insight'], true)) continue;
        $title = mg_ai_chat_clean($block['title'] ?? '', 160);
        $body = mg_ai_chat_clean($block['body'] ?? $block['summary'] ?? '', 1000);
        $safe = ['type' => $type, 'title' => $title, 'body' => $body];

        if ($type === 'chart' || $type === 'forecast') {
            $chartType = strtolower(mg_ai_chat_clean($block['chart_type'] ?? ($type === 'forecast' ? 'line' : 'bar'), 20));
            if (!in_array($chartType, ['bar','line','pie'], true)) $chartType = 'bar';
            $rows = [];
            foreach (($block['data'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $label = mg_ai_chat_clean($row['label'] ?? $row['name'] ?? $row['product'] ?? $row['period'] ?? '', 80);
                $value = is_numeric($row['value'] ?? null) ? (float)$row['value'] : (is_numeric($row['score'] ?? null) ? (float)$row['score'] : (is_numeric($row['amount'] ?? null) ? (float)$row['amount'] : null));
                if ($label !== '' && $value !== null) $rows[] = ['label' => $label, 'value' => $value];
                if (count($rows) >= 8) break;
            }
            if (!$rows) continue;
            $safe['chart_type'] = $chartType;
            $safe['data'] = $rows;
            $safe['value_prefix'] = mg_ai_chat_clean($block['value_prefix'] ?? '', 8);
            $safe['value_suffix'] = mg_ai_chat_clean($block['value_suffix'] ?? '', 16);
        } elseif ($type === 'metric_grid') {
            $metrics = [];
            foreach (($block['metrics'] ?? $block['data'] ?? []) as $metric) {
                if (!is_array($metric)) continue;
                $label = mg_ai_chat_clean($metric['label'] ?? $metric['name'] ?? '', 80);
                $value = mg_ai_chat_clean($metric['value'] ?? $metric['count'] ?? '', 60);
                if ($label !== '' && $value !== '') $metrics[] = ['label' => $label, 'value' => $value];
                if (count($metrics) >= 6) break;
            }
            if (!$metrics) continue;
            $safe['metrics'] = $metrics;
        } elseif ($type === 'social_campaign' || $type === 'social_posts') {
            $safe['audience'] = mg_ai_chat_clean($block['audience'] ?? '', 140);
            $safe['cta'] = mg_ai_chat_clean($block['cta'] ?? '', 140);
            $posts = [];
            foreach (($block['posts'] ?? []) as $post) {
                if (!is_array($post)) continue;
                $channel = mg_ai_chat_clean($post['channel'] ?? 'Social', 40);
                $copy = mg_ai_chat_clean($post['copy'] ?? $post['body'] ?? '', 900);
                if ($copy !== '') $posts[] = ['channel' => $channel, 'copy' => $copy];
                if (count($posts) >= 4) break;
            }
            $safe['posts'] = $posts;
        } elseif ($type === 'project' || $type === 'product_opportunity') {
            $safe['confidence'] = is_numeric($block['confidence'] ?? null) ? (float)$block['confidence'] : null;
            $safe['estimated_impact'] = mg_ai_chat_clean($block['estimated_impact'] ?? '', 160);
            $safe['review_action_key'] = mg_ai_chat_infer_action_key($block);
            $safe['approval_required'] = true;
        }
        $out[] = $safe;
        if (count($out) >= 6) break;
    }
    return $out;
}

function mg_agent_skill_fallback_blocks(string $message, array $skillKeys, array $context): array
{
    $blocks = [];
    $lower = strtolower($message);
    if (in_array('merchant_analysis_charts', $skillKeys, true) && (str_contains($lower, 'chart') || str_contains($lower, 'graph') || str_contains($lower, 'analy') || str_contains($lower, 'predict'))) {
        $blocks[] = [
            'type' => 'metric_grid',
            'title' => 'Analysis skill ready',
            'body' => 'I can turn merchant product, claim, redemption, campaign, and CRM summaries into chart blocks inside this chat.',
            'metrics' => [
                ['label' => 'Skill', 'value' => 'Analysis'],
                ['label' => 'Output', 'value' => 'Charts'],
                ['label' => 'Mode', 'value' => 'Advisory'],
                ['label' => 'Approval', 'value' => 'Required'],
            ],
        ];
    }
    if (in_array('social_campaign_advisor', $skillKeys, true) && (str_contains($lower, 'social') || str_contains($lower, 'campaign') || str_contains($lower, 'facebook') || str_contains($lower, 'instagram'))) {
        $blocks[] = [
            'type' => 'social_campaign',
            'title' => 'Social campaign skill ready',
            'body' => 'I can create channel-specific posts, campaign angles, CTAs, and review-ready campaign projects from merchant data.',
            'audience' => 'Existing customers and social followers',
            'cta' => 'Claim or send this Microgifter offer',
            'posts' => [],
        ];
    }
    return mg_agent_chat_normalize_blocks($blocks);
}
