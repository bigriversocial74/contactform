<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';
require_once dirname(__DIR__, 3) . '/includes/public-donations-feature.php';

mg_require_method('GET');
$pdo = mg_db();
$campaignRef = strtolower(trim((string) ($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? '')));
$token = trim((string) ($_GET['token'] ?? $_GET['qr_token'] ?? ''));

if ($campaignRef === '' && $token === '') {
    mg_fail('Campaign not found.', 404);
}

try {
    $sql = 'SELECT c.public_id,c.public_slug,c.qr_code_token,c.merchant_user_id,c.campaign_type,c.title,c.description,c.form_headline,c.form_description,c.success_message,c.status,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.per_user_limit,
                   rt.public_id reward_template_id,rt.title reward_template_title,rt.description reward_template_description,rt.reward_type,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,rt.redemption_instructions,rt.expires_at
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
            WHERE c.status = \'active\'
              AND ((? <> \'\' AND (c.public_id = ? OR c.public_slug = ?)) OR (? <> \'\' AND c.qr_code_token = ?))
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$campaignRef, $campaignRef, $campaignRef, $token, $token]);
    $row = $stmt->fetch();
    if (!$row) mg_fail('Campaign not found.', 404);
    $campaignType = (string)$row['campaign_type'];
    if ($campaignType === 'public_donation' && !mg_public_donations_is_enabled_for((int)$row['merchant_user_id'], mg_current_user())) mg_fail('Campaign not found.', 404);
    if (!mg_campaign_type_public_enabled($campaignType)) mg_fail('Campaign not found.', 404);

    $now = time();
    if (!empty($row['starts_at']) && strtotime((string) $row['starts_at']) > $now) mg_fail('Campaign has not started yet.', 409);
    if (!empty($row['ends_at']) && strtotime((string) $row['ends_at']) < $now) mg_fail('Campaign has ended.', 409);
    if (mg_campaign_type_public_transactional($campaignType) && $row['quantity_limit'] !== null && (int) $row['issued_count'] >= (int) $row['quantity_limit']) mg_fail('Campaign reward limit has been reached.', 409);

    // Stage 12C validation markers retained while submit routing is registry-driven:
    // qr_reward_drop, contest_giveaway, survey_feedback_reward.
    $submitEndpoint = mg_campaign_type_submit_endpoint($campaignType);

    mg_ok(['campaign' => [
        'id' => (string) $row['public_id'],
        'slug' => $row['public_slug'] ?? null,
        'campaign_type' => $campaignType,
        'public_transactional' => mg_campaign_type_public_transactional($campaignType),
        'public_mode' => mg_campaign_type_public_mode($campaignType),
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'form_headline' => (string) ($row['form_headline'] ?? $row['title']),
        'form_description' => (string) ($row['form_description'] ?? ''),
        'success_message' => (string) ($row['success_message'] ?? 'Reward issued.'),
        'submit_endpoint' => $submitEndpoint,
        'qr_token_required' => $row['campaign_type'] === 'qr_reward_drop',
        'reward' => [
            'id' => $row['reward_template_id'] ?? null,
            'title' => $row['reward_template_title'] ?? null,
            'description' => $row['reward_template_description'] ?? null,
            'reward_type' => $row['reward_type'] ?? null,
            'value_type' => $row['value_type'] ?? null,
            'value_amount_cents' => $row['value_amount_cents'] === null ? null : (int) $row['value_amount_cents'],
            'value_percent' => $row['value_percent'] === null ? null : (float) $row['value_percent'],
            'currency' => $row['currency'] ?? 'USD',
            'redemption_instructions' => $row['redemption_instructions'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
        ],
    ], 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'public.campaign.detail_unavailable', 'Unable to load public campaign.', ['exception_class' => $error::class]);
    mg_fail('Campaign not available.', 404);
}
