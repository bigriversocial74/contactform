<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

function mg_offer_detail_json(?string $json): array
{
    if ($json === null || $json === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_offer_detail_value(array $row): string
{
    if ((string) ($row['value_type'] ?? '') === 'percent') return rtrim(rtrim(number_format((float) ($row['value_percent'] ?? 0), 2), '0'), '.') . '%';
    $cents = (int) ($row['value_amount_cents'] ?? 0);
    return (string) ($row['currency'] ?? 'USD') . ' ' . number_format($cents / 100, 2);
}

mg_require_method('GET');
$pdo = mg_db();
$offerId = strtolower(trim((string) ($_GET['offer_id'] ?? $_GET['id'] ?? '')));
if ($offerId === '' || strlen($offerId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $offerId)) {
    mg_fail('Invalid offer.', 422);
}

try {
    $stmt = $pdo->prepare('SELECT rt.*, u.display_name merchant_label
        FROM reward_templates rt
        LEFT JOIN users u ON u.id = rt.merchant_user_id
        WHERE rt.public_id = ? AND rt.status = \'active\' AND rt.agent_discoverable = 1
        LIMIT 1');
    $stmt->execute([$offerId]);
    $row = $stmt->fetch();
    if (!$row) mg_fail('Offer not found.', 404);

    $relatedStmt = $pdo->prepare('SELECT public_id,title,reward_type,value_type,value_amount_cents,value_percent,currency,agent_summary
        FROM reward_templates
        WHERE status = \'active\' AND agent_discoverable = 1 AND merchant_user_id = ? AND public_id <> ?
        ORDER BY updated_at DESC LIMIT 6');
    $relatedStmt->execute([(int) $row['merchant_user_id'], $offerId]);
    $related = array_map(static fn(array $r): array => [
        'id' => (string) $r['public_id'],
        'title' => (string) $r['title'],
        'reward_type' => (string) $r['reward_type'],
        'value_type' => (string) $r['value_type'],
        'value_amount_cents' => (int) $r['value_amount_cents'],
        'value_percent' => $r['value_percent'] === null ? null : (float) $r['value_percent'],
        'currency' => (string) $r['currency'],
        'agent_summary' => (string) ($r['agent_summary'] ?? ''),
    ], $relatedStmt->fetchAll());

    mg_ok(['offer' => [
        'id' => (string) $row['public_id'],
        'merchant_user_id' => (int) $row['merchant_user_id'],
        'merchant_label' => $row['merchant_label'] ?? 'Local merchant',
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'reward_type' => (string) $row['reward_type'],
        'value_type' => (string) $row['value_type'],
        'value_amount_cents' => (int) $row['value_amount_cents'],
        'value_percent' => $row['value_percent'] === null ? null : (float) $row['value_percent'],
        'currency' => (string) $row['currency'],
        'display_value' => mg_offer_detail_value($row),
        'agent_summary' => (string) ($row['agent_summary'] ?? ''),
        'agent_categories' => mg_offer_detail_json($row['agent_categories_json'] ?? null),
        'agent_use_cases' => mg_offer_detail_json($row['agent_use_cases_json'] ?? null),
        'can_add_to_wallet' => (bool) ((int) $row['agent_add_to_wallet_allowed']),
        'can_send_as_gift' => (bool) ((int) $row['agent_gift_send_allowed']),
        'redemption_instructions' => $row['redemption_instructions'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'related_offers' => $related,
    ], 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'public.offers.detail_unavailable', 'Agent offer detail is unavailable.', ['exception_class' => $error::class]);
    mg_fail('Offer detail unavailable.', 500);
}
