<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

mg_require_method('GET');
$pdo = mg_db();
$q = strtolower(trim((string) ($_GET['q'] ?? '')));
$type = trim((string) ($_GET['reward_type'] ?? ''));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

try {
    $sql = 'SELECT public_id,title,description,reward_type,value_type,value_amount_cents,value_percent,currency,agent_summary,agent_categories_json,agent_use_cases_json,agent_add_to_wallet_allowed,agent_gift_send_allowed,updated_at
            FROM reward_templates
            WHERE status = \'active\' AND agent_discoverable = 1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (LOWER(title) LIKE ? OR LOWER(description) LIKE ? OR LOWER(agent_summary) LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($type !== '') {
        $sql .= ' AND reward_type = ?';
        $params[] = $type;
    }
    $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $offers = array_map(static function(array $row): array {
        return [
            'id' => (string) $row['public_id'],
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'reward_type' => (string) $row['reward_type'],
            'value_type' => (string) $row['value_type'],
            'value_amount_cents' => (int) $row['value_amount_cents'],
            'value_percent' => $row['value_percent'] === null ? null : (float) $row['value_percent'],
            'currency' => (string) $row['currency'],
            'agent_summary' => (string) ($row['agent_summary'] ?? ''),
            'agent_categories' => $row['agent_categories_json'] ? json_decode((string) $row['agent_categories_json'], true) : [],
            'agent_use_cases' => $row['agent_use_cases_json'] ? json_decode((string) $row['agent_use_cases_json'], true) : [],
            'can_add_to_wallet' => (bool) ((int) $row['agent_add_to_wallet_allowed']),
            'can_send_as_gift' => (bool) ((int) $row['agent_gift_send_allowed']),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }, $stmt->fetchAll());
    mg_ok(['offers' => $offers, 'count' => count($offers), 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'public.offers.search_unavailable', 'Agent offer search is unavailable.', ['exception_class' => $error::class]);
    mg_ok(['offers' => [], 'count' => 0, 'schema_ready' => false], 'Offer search unavailable until the Stage 12 schema is installed.');
}
