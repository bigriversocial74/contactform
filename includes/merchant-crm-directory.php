<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-crm-search.php';

const MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION = 1;

function mg_merchant_crm_directory_email(mixed $value): string
{
    $email = mb_strtolower(trim((string)$value));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function mg_merchant_crm_directory_identity_maps(PDO $pdo, int $merchantId, array $campaignRows): array
{
    $empty = ['by_user' => [], 'by_email' => []];
    if ($merchantId < 1 || $campaignRows === [] || !mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contacts')) return $empty;

    $userIds = [];
    $emails = [];
    foreach ($campaignRows as $row) {
        if (!is_array($row)) continue;
        $userId = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
        if ($userId > 0) $userIds[$userId] = $userId;
        $email = mg_merchant_crm_directory_email($row['email'] ?? '');
        if ($email !== '') $emails[$email] = $email;
    }
    if ($userIds === [] && $emails === []) return $empty;

    $parts = mg_merchant_crm_search_select_parts($pdo);
    $where = [];
    $params = [$merchantId];
    if ($userIds !== []) {
        $where[] = 'mc.user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')';
        array_push($params, ...array_values($userIds));
    }
    if ($emails !== []) {
        $where[] = 'LOWER(mc.primary_email) IN (' . implode(',', array_fill(0, count($emails), '?')) . ')';
        array_push($params, ...array_values($emails));
    }

    $sql = 'SELECT ' . $parts['select'] . ' FROM merchant_crm_contacts mc' . $parts['joins']
        . ' WHERE mc.merchant_user_id=? AND (' . implode(' OR ', $where) . ')';
    if (mg_merchant_crm_search_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id')) {
        $sql .= ' AND mc.merged_into_contact_id IS NULL';
    }
    $sql .= ' ORDER BY COALESCE(mc.last_engaged_at,mc.last_seen_at) DESC,mc.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $maps = $empty;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $view = mg_merchant_crm_search_row($row);
        $userId = (int)($row['user_id'] ?? 0);
        $email = mg_merchant_crm_directory_email($row['primary_email'] ?? '');
        if ($userId > 0 && !isset($maps['by_user'][$userId])) $maps['by_user'][$userId] = $view;
        if ($email !== '' && !isset($maps['by_email'][$email])) $maps['by_email'][$email] = $view;
    }
    return $maps;
}

function mg_merchant_crm_directory_search_index(array $contact): string
{
    $values = [
        $contact['crm_username'] ?? $contact['username'] ?? '',
        $contact['crm_mention'] ?? $contact['mention'] ?? '',
        $contact['name'] ?? $contact['display_name'] ?? '',
        $contact['email'] ?? '',
        $contact['phone'] ?? '',
        $contact['campaign_title'] ?? '',
        $contact['campaign_type'] ?? '',
        $contact['source'] ?? $contact['source_type'] ?? '',
        $contact['lifecycle_stage'] ?? $contact['stage'] ?? '',
        $contact['crm_status'] ?? $contact['status'] ?? '',
        $contact['result_status'] ?? '',
        $contact['next_best_action'] ?? '',
        $contact['campaign_contact_id'] ?? '',
        $contact['crm_contact_id'] ?? $contact['id'] ?? '',
    ];
    $values = array_values(array_unique(array_filter(array_map(static function (mixed $value): string {
        return mb_strtolower(trim((string)$value));
    }, $values), static fn(string $value): bool => $value !== '')));
    return implode(' ', $values);
}

function mg_merchant_crm_directory_contact(array $contact): array
{
    $contact['directory_contract_version'] = MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION;
    $contact['crm_contact_id'] = (string)($contact['id'] ?? '');
    $contact['crm_username'] = (string)($contact['username'] ?? '');
    $contact['crm_mention'] = (string)($contact['mention'] ?? '');
    $contact['lifecycle_stage'] = (string)($contact['stage'] ?? 'lead');
    $contact['crm_status'] = (string)($contact['status'] ?? 'active');
    $contact['crm_score'] = (int)($contact['score'] ?? 0);
    $contact['crm_score_label'] = (string)($contact['score_label'] ?? '');
    $contact['search_index'] = mg_merchant_crm_directory_search_index($contact);
    return $contact;
}

function mg_merchant_crm_directory_list(PDO $pdo, int $merchantId, string $query = '', int $limit = 250, int $offset = 0): array
{
    $query = mg_merchant_crm_search_query($query);
    $limit = max(1, min(250, $limit));
    $offset = max(0, min(10000, $offset));
    if (!mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contacts')) {
        return ['schema_ready'=>false,'contract_version'=>MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION,'query'=>$query,'contacts'=>[],'total'=>0,'limit'=>$limit,'offset'=>$offset,'has_more'=>false,'next_offset'=>null];
    }

    if ($query !== '') {
        $result = mg_merchant_crm_search($pdo, $merchantId, $query, min(100, $limit), $offset);
        $result['contract_version'] = MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION;
        $result['contacts'] = array_map('mg_merchant_crm_directory_contact', (array)($result['contacts'] ?? []));
        return $result;
    }

    $parts = mg_merchant_crm_search_select_parts($pdo);
    $where = 'mc.merchant_user_id=?';
    if (mg_merchant_crm_search_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id')) $where .= ' AND mc.merged_into_contact_id IS NULL';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM merchant_crm_contacts mc WHERE ' . $where);
    $countStmt->execute([$merchantId]);
    $total = (int)$countStmt->fetchColumn();

    $sql = 'SELECT ' . $parts['select'] . ' FROM merchant_crm_contacts mc' . $parts['joins'] . ' WHERE ' . $where
        . ' ORDER BY COALESCE(mc.last_engaged_at,mc.last_seen_at) DESC,mc.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantId]);
    $contacts = array_map('mg_merchant_crm_search_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    $contacts = array_map('mg_merchant_crm_directory_contact', $contacts);
    return [
        'schema_ready'=>true,
        'contract_version'=>MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION,
        'query'=>'',
        'contacts'=>$contacts,
        'total'=>$total,
        'limit'=>$limit,
        'offset'=>$offset,
        'has_more'=>($offset + count($contacts)) < $total,
        'next_offset'=>($offset + count($contacts)) < $total ? $offset + count($contacts) : null,
    ];
}

function mg_merchant_crm_directory_attach(PDO $pdo, int $merchantId, array $campaignRows, array $contacts): array
{
    $maps = mg_merchant_crm_directory_identity_maps($pdo, $merchantId, $campaignRows);
    foreach ($contacts as $index => &$contact) {
        if (!is_array($contact)) continue;
        $row = is_array($campaignRows[$index] ?? null) ? $campaignRows[$index] : [];
        $userId = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
        $email = mg_merchant_crm_directory_email($row['email'] ?? $contact['email'] ?? '');
        $identity = $userId > 0 ? ($maps['by_user'][$userId] ?? null) : null;
        if (!is_array($identity) && $email !== '') $identity = $maps['by_email'][$email] ?? null;

        $campaignScore = (int)($contact['crm_score'] ?? 0);
        $campaignScoreLabel = (string)($contact['crm_score_label'] ?? '');
        $contact['directory_contract_version'] = MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION;
        $contact['campaign_contact_id'] = (string)($contact['id'] ?? '');
        $contact['campaign_engagement_score'] = $campaignScore;
        $contact['campaign_engagement_label'] = $campaignScoreLabel;
        $contact['crm_contact_id'] = is_array($identity) ? (string)($identity['id'] ?? '') : '';
        $contact['crm_username'] = is_array($identity) ? (string)($identity['username'] ?? '') : '';
        $contact['crm_mention'] = is_array($identity) ? (string)($identity['mention'] ?? '') : '';
        $contact['lifecycle_stage'] = is_array($identity) ? (string)($identity['stage'] ?? 'lead') : 'lead';
        $contact['crm_status'] = is_array($identity) ? (string)($identity['status'] ?? 'active') : 'active';

        if (is_array($identity)) {
            $contact['crm_score'] = (int)($identity['score'] ?? $campaignScore);
            $contact['crm_score_label'] = (string)($identity['score_label'] ?? $campaignScoreLabel);
            $contact['next_best_action'] = (string)($identity['next_best_action'] ?? $contact['next_best_action'] ?? '');
            $contact['customer_profile_url'] = (string)($identity['profile_url'] ?? $contact['customer_profile_url'] ?? '');
            $contact['canonical_contact'] = [
                'id' => (string)($identity['id'] ?? ''),
                'username' => (string)($identity['username'] ?? ''),
                'mention' => (string)($identity['mention'] ?? ''),
                'stage' => (string)($identity['stage'] ?? 'lead'),
                'status' => (string)($identity['status'] ?? 'active'),
                'score' => (int)($identity['score'] ?? 0),
                'score_label' => (string)($identity['score_label'] ?? ''),
                'profile_url' => (string)($identity['profile_url'] ?? ''),
            ];
        } else {
            $contact['canonical_contact'] = null;
        }
        $contact['search_index'] = mg_merchant_crm_directory_search_index($contact);
    }
    unset($contact);
    return $contacts;
}
