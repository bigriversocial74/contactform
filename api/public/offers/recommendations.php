<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

function mg_offer_rec_terms(string $q): array
{
    $words = preg_split('/[^a-z0-9]+/i', strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = ['the','and','for','with','near','local','offer','reward','gift','card','deal'];
    return array_values(array_unique(array_filter($words, static fn(string $w): bool => strlen($w) > 2 && !in_array($w, $stop, true))));
}

function mg_offer_rec_json(?string $json): array
{
    if ($json === null || $json === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

mg_require_method('GET');
$pdo = mg_db();
$q = trim((string) ($_GET['q'] ?? $_GET['intent'] ?? ''));
$useCase = strtolower(trim((string) ($_GET['use_case'] ?? '')));
$type = strtolower(trim((string) ($_GET['reward_type'] ?? '')));
$limit = min(20, max(1, (int) ($_GET['limit'] ?? 10)));
$terms = mg_offer_rec_terms($q . ' ' . $useCase);

try {
    $sql = 'SELECT rt.public_id,rt.title,rt.description,rt.reward_type,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,rt.agent_summary,rt.agent_categories_json,rt.agent_use_cases_json,rt.agent_add_to_wallet_allowed,rt.agent_gift_send_allowed,rt.updated_at,u.display_name merchant_label,
            COUNT(DISTINCT wi.id) wallet_add_count,
            COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) claim_count,
            COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) completion_count
            FROM reward_templates rt
            LEFT JOIN users u ON u.id = rt.merchant_user_id
            LEFT JOIN wallet_items wi ON wi.reward_template_id = rt.id
            WHERE rt.status = \'active\' AND rt.agent_discoverable = 1';
    $params = [];
    if ($type !== '') {
        $sql .= ' AND rt.reward_type = ?';
        $params[] = $type;
    }
    $sql .= ' GROUP BY rt.id ORDER BY completion_count DESC, claim_count DESC, wallet_add_count DESC, rt.updated_at DESC LIMIT 80';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $recommendations = [];
    foreach ($rows as $row) {
        $haystack = strtolower(implode(' ', [
            $row['title'] ?? '', $row['description'] ?? '', $row['agent_summary'] ?? '',
            json_encode(mg_offer_rec_json($row['agent_categories_json'] ?? null)),
            json_encode(mg_offer_rec_json($row['agent_use_cases_json'] ?? null)),
        ]));
        $score = ((int) $row['completion_count'] * 6) + ((int) $row['claim_count'] * 3) + ((int) $row['wallet_add_count'] * 2);
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) $score += 10;
        }
        if ($q !== '' && $score === 0) continue;
        $recommendations[] = [
            'id' => (string) $row['public_id'],
            'title' => (string) $row['title'],
            'merchant_label' => $row['merchant_label'] ?? 'Local merchant',
            'description' => (string) ($row['description'] ?? ''),
            'reward_type' => (string) $row['reward_type'],
            'value_type' => (string) $row['value_type'],
            'value_amount_cents' => (int) $row['value_amount_cents'],
            'value_percent' => $row['value_percent'] === null ? null : (float) $row['value_percent'],
            'currency' => (string) $row['currency'],
            'agent_summary' => (string) ($row['agent_summary'] ?? ''),
            'agent_categories' => mg_offer_rec_json($row['agent_categories_json'] ?? null),
            'agent_use_cases' => mg_offer_rec_json($row['agent_use_cases_json'] ?? null),
            'can_add_to_wallet' => (bool) ((int) $row['agent_add_to_wallet_allowed']),
            'can_send_as_gift' => (bool) ((int) $row['agent_gift_send_allowed']),
            'wallet_add_count' => (int) $row['wallet_add_count'],
            'claim_count' => (int) $row['claim_count'],
            'completion_count' => (int) $row['completion_count'],
            'recommendation_score' => $score,
            'reason' => $terms ? 'Matched intent and prior wallet activity.' : 'Ranked by wallet and completion activity.',
        ];
    }
    usort($recommendations, static fn(array $a, array $b): int => $b['recommendation_score'] <=> $a['recommendation_score']);
    $recommendations = array_slice($recommendations, 0, $limit);
    mg_ok(['recommendations' => $recommendations, 'count' => count($recommendations), 'terms' => $terms, 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'public.offers.recommendations_unavailable', 'Offer recommendations unavailable.', ['exception_class' => $error::class]);
    mg_ok(['recommendations' => [], 'count' => 0, 'terms' => $terms, 'schema_ready' => false], 'Offer recommendations unavailable until the Stage 12 schema is installed.');
}
