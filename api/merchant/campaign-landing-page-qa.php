<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

function mg_clpqa_base_url(): string
{
    $base = rtrim((string)(defined('MG_APP_URL') ? MG_APP_URL : ''), '/');
    if ($base !== '') return $base;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function mg_clpqa_public_url(array $campaign, array $definition, string $base): string
{
    if (empty($definition['public_enabled'])) return '';
    $path = (string)($definition['public_path'] ?? '');
    if ($path === '') return '';
    $type = (string)($campaign['campaign_type'] ?? '');
    if ($type === 'qr_reward_drop' && !empty($campaign['qr_code_token'])) {
        return $base . $path . '?token=' . rawurlencode((string)$campaign['qr_code_token']);
    }
    $ref = trim((string)($campaign['public_slug'] ?? '')) !== '' ? (string)$campaign['public_slug'] : (string)$campaign['public_id'];
    return $base . $path . '?campaign=' . rawurlencode($ref);
}

function mg_clpqa_expected_fields(string $type): array
{
    $fields = ['name', 'email', 'phone'];
    if ($type === 'contest_giveaway') $fields[] = 'entry_note';
    if ($type === 'referral_reward') $fields[] = 'referral_note';
    if ($type === 'birthday_vip') $fields[] = 'birthday_month';
    if ($type === 'agent_offer') $fields[] = 'offer_interest';
    if ($type === 'qr_reward_drop') $fields[] = 'qr_token';
    return $fields;
}

function mg_clpqa_check(string $key, string $label, bool $pass, string $detail = ''): array
{
    return ['key' => $key, 'label' => $label, 'pass' => $pass, 'detail' => $detail];
}

function mg_clpqa_row(array $campaign, string $base): array
{
    $type = (string)$campaign['campaign_type'];
    $definition = mg_campaign_type_get($type) ?? [];
    $publicEnabled = !empty($definition['public_enabled']);
    $internalOnly = !empty($definition['internal_only']);
    $publicPath = (string)($definition['public_path'] ?? '');
    $submitEndpoint = (string)($definition['submit_endpoint'] ?? '');
    $publicUrl = mg_clpqa_public_url($campaign, $definition, $base);
    $rewardAttached = trim((string)($campaign['reward_template_public_id'] ?? '')) !== '';
    $rewardActive = (string)($campaign['reward_template_status'] ?? '') === 'active';
    $status = (string)($campaign['status'] ?? 'draft');
    $qrToken = trim((string)($campaign['qr_code_token'] ?? ''));
    $checks = [];
    $checks[] = mg_clpqa_check('campaign_type_registered', 'Campaign type is registered', $definition !== [], $type);
    $checks[] = mg_clpqa_check('public_enabled', 'Public landing page enabled', $publicEnabled || $internalOnly, $internalOnly ? 'Internal-only type should not expose public form.' : $publicPath);
    if ($publicEnabled) {
        $checks[] = mg_clpqa_check('status_active', 'Campaign is active', $status === 'active', $status);
        $checks[] = mg_clpqa_check('reward_attached', 'Reward template attached', $rewardAttached, (string)($campaign['reward_template_title'] ?? 'No template'));
        $checks[] = mg_clpqa_check('reward_active', 'Reward template is active', $rewardActive, (string)($campaign['reward_template_status'] ?? 'missing'));
        $checks[] = mg_clpqa_check('public_path', 'Landing page route is defined', $publicPath !== '', $publicPath);
        $checks[] = mg_clpqa_check('public_url', 'Public URL is generated', $publicUrl !== '', $publicUrl);
        $checks[] = mg_clpqa_check('submit_endpoint', 'Submit endpoint is defined', $submitEndpoint !== '', $submitEndpoint);
        if ($type === 'qr_reward_drop') {
            $checks[] = mg_clpqa_check('qr_token', 'QR token exists', $qrToken !== '', $qrToken !== '' ? 'QR token ready.' : 'Create/save QR drop to generate token.');
        }
    } else {
        $checks[] = mg_clpqa_check('internal_blocked', 'Internal-only campaign has no public URL', $publicUrl === '', $internalOnly ? 'Blocked from public customer form.' : 'No public page configured.');
    }
    $ready = true;
    foreach ($checks as $check) if (empty($check['pass'])) $ready = false;
    return [
        'id' => (string)$campaign['public_id'],
        'title' => (string)$campaign['title'],
        'campaign_type' => $type,
        'campaign_type_label' => (string)($definition['label'] ?? mg_campaign_type_label($type)),
        'status' => $status,
        'public_enabled' => $publicEnabled,
        'internal_only' => $internalOnly,
        'public_path' => $publicPath,
        'public_url' => $publicUrl,
        'qr_url' => $type === 'qr_reward_drop' && $qrToken !== '' ? $base . $publicPath . '?token=' . rawurlencode($qrToken) : null,
        'qr_token' => $qrToken !== '' ? $qrToken : null,
        'submit_endpoint' => $submitEndpoint,
        'expected_fields' => mg_clpqa_expected_fields($type),
        'reward_template' => [
            'id' => $campaign['reward_template_public_id'] ?? null,
            'title' => $campaign['reward_template_title'] ?? null,
            'status' => $campaign['reward_template_status'] ?? null,
        ],
        'checks' => $checks,
        'ready' => $ready,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    $stmt = $pdo->prepare('SELECT c.public_id,c.public_slug,c.qr_code_token,c.campaign_type,c.title,c.status,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.status reward_template_status
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        WHERE c.merchant_user_id = ? AND c.status <> \'archived\'
        ORDER BY FIELD(c.status,\'active\',\'draft\',\'paused\',\'ended\'), c.updated_at DESC, c.id DESC
        LIMIT 200');
    $stmt->execute([$merchantId]);
    $base = mg_clpqa_base_url();
    $rows = array_map(static fn(array $row): array => mg_clpqa_row($row, $base), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    $totals = ['total' => count($rows), 'public' => 0, 'ready' => 0, 'needs_attention' => 0, 'internal' => 0];
    foreach ($rows as $row) {
        if (!empty($row['public_enabled'])) $totals['public']++;
        if (!empty($row['internal_only'])) $totals['internal']++;
        if (!empty($row['ready'])) $totals['ready']++; else $totals['needs_attention']++;
    }
    mg_ok(['landing_page_qa' => $rows, 'totals' => $totals, 'campaign_types' => mg_campaign_type_options(true), 'schema_ready' => true], 'Campaign landing page QA loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_landing_page_qa.unavailable', 'Campaign landing page QA unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok(['landing_page_qa' => [], 'totals' => ['total'=>0,'public'=>0,'ready'=>0,'needs_attention'=>0,'internal'=>0], 'campaign_types' => mg_campaign_type_options(true), 'schema_ready' => false], 'Campaign landing page QA unavailable until campaign schema is installed.');
}
