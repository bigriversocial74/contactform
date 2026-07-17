<?php
declare(strict_types=1);

function mg_merchant_crm_search_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $table = preg_replace('/[^a-z0-9_]/i', '', $table) ?: '';
    if ($table === '') return false;
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        return $cache[$table] = true;
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_merchant_crm_search_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->execute([$table, $column]);
        return $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_merchant_crm_search_query(mixed $value): string
{
    $query = trim((string)$value);
    $query = preg_replace('/^@+/', '', $query) ?? $query;
    $query = preg_replace('/\s+/', ' ', $query) ?? $query;
    return mb_substr(trim($query), 0, 120);
}

function mg_merchant_crm_search_like(string $query): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($query)) . '%';
}

function mg_merchant_crm_search_handle(array $row): string
{
    $slug = strtolower(trim((string)($row['profile_slug'] ?? '')));
    if ($slug !== '' && preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $slug) === 1) return $slug;
    $publicId = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)($row['public_id'] ?? '')) ?: 'contact');
    return 'crm-' . substr($publicId, 0, 10);
}

function mg_merchant_crm_search_score(array $row): array
{
    $stage = strtolower((string)($row['lifecycle_stage'] ?? 'lead'));
    $weights = ['lead'=>15,'follower'=>22,'prospect'=>35,'customer'=>65,'supporter'=>72,'redeemer'=>78,'inactive'=>8,'custom'=>20];
    $score = $weights[$stage] ?? 15;
    $lastActivity = strtotime((string)($row['last_engaged_at'] ?? $row['last_seen_at'] ?? '')) ?: 0;
    if ($lastActivity > 0) {
        $ageDays = max(0, (int)floor((time() - $lastActivity) / 86400));
        if ($ageDays <= 7) $score += 18;
        elseif ($ageDays <= 30) $score += 12;
        elseif ($ageDays <= 90) $score += 6;
        elseif ($ageDays > 180) $score -= 8;
    }
    $score += min(18, (int)floor(((int)($row['total_purchase_cents'] ?? 0)) / 2500) * 3);
    $score += min(10, (int)($row['total_rewards_claimed'] ?? 0) * 3);
    $score += min(12, (int)($row['total_rewards_redeemed'] ?? 0) * 4);
    if ((string)($row['crm_status'] ?? 'active') !== 'active') $score -= 12;
    $score = max(0, min(100, $score));
    $label = $score >= 75 ? 'high_intent' : ($score >= 50 ? 'engaged' : ($score >= 30 ? 'warming' : 'cold'));
    return ['score'=>$score,'label'=>$label];
}

function mg_merchant_crm_search_next_action(array $row, array $score): string
{
    if ((string)($row['crm_status'] ?? 'active') === 'blocked') return 'Review blocked contact';
    if ((int)($row['total_rewards_redeemed'] ?? 0) > 0) return 'Ask for feedback or referral';
    if ((int)($row['total_rewards_claimed'] ?? 0) > 0) return 'Follow up on redemption';
    if ((int)($row['total_rewards_issued'] ?? 0) > 0) return 'Check reward claim status';
    if ((int)($score['score'] ?? 0) >= 75) return 'Prioritize a personal follow-up';
    if (!empty($row['user_id'])) return 'Send a CRM message';
    return 'Invite this contact to create an account';
}

function mg_merchant_crm_search_select_parts(PDO $pdo): array
{
    $hasProfiles = mg_merchant_crm_search_table_exists($pdo, 'public_profiles');
    $hasUsers = mg_merchant_crm_search_table_exists($pdo, 'users');
    $hasCampaignLinks = mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contact_campaigns') && mg_merchant_crm_search_table_exists($pdo, 'campaigns');
    $hasCampaignContacts = mg_merchant_crm_search_table_exists($pdo, 'campaign_contacts');

    $select = "mc.public_id,mc.user_id,mc.primary_email,mc.primary_phone,mc.display_name,mc.lifecycle_stage,mc.crm_status,mc.last_campaign_type,mc.last_source_type,mc.last_seen_at,mc.last_engaged_at,mc.total_purchase_cents,mc.total_rewards_issued,mc.total_rewards_claimed,mc.total_rewards_redeemed,mc.tags_json";
    $joins = '';
    if ($hasProfiles) {
        $select .= ",pp.slug profile_slug,pp.display_name profile_display_name,pp.avatar_url profile_avatar_url,pp.status profile_status,pp.visibility profile_visibility";
        $joins .= ' LEFT JOIN public_profiles pp ON pp.user_id=mc.user_id';
    } else {
        $select .= ",'' profile_slug,'' profile_display_name,'' profile_avatar_url,'' profile_status,'' profile_visibility";
    }
    if ($hasUsers) {
        $select .= ',u.email_verified_at';
        $joins .= ' LEFT JOIN users u ON u.id=mc.user_id';
    } else {
        $select .= ',NULL email_verified_at';
    }
    if ($hasCampaignLinks) {
        $select .= ",(SELECT c.title FROM merchant_crm_contact_campaigns mcc INNER JOIN campaigns c ON c.id=mcc.campaign_id WHERE mcc.merchant_user_id=mc.merchant_user_id AND mcc.crm_contact_id=mc.id ORDER BY mcc.last_event_at DESC,mcc.id DESC LIMIT 1) campaign_title";
    } else {
        $select .= ",'' campaign_title";
    }
    if ($hasCampaignContacts) {
        $select .= ",(SELECT cc.public_id FROM campaign_contacts cc WHERE cc.merchant_user_id=mc.merchant_user_id AND ((mc.user_id IS NOT NULL AND cc.user_id=mc.user_id) OR (mc.primary_email IS NOT NULL AND LOWER(cc.email)=LOWER(mc.primary_email))) ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 1) campaign_contact_public_id";
    } else {
        $select .= ",'' campaign_contact_public_id";
    }
    return ['select'=>$select,'joins'=>$joins,'has_profiles'=>$hasProfiles];
}

function mg_merchant_crm_search_row(array $row): array
{
    $score = mg_merchant_crm_search_score($row);
    $handle = mg_merchant_crm_search_handle($row);
    $id = (string)($row['public_id'] ?? '');
    $campaignContactId = (string)($row['campaign_contact_public_id'] ?? '');
    $profileUrl = '/merchant-customer.php?crm_contact_id=' . rawurlencode($id);
    $crmBase = $campaignContactId !== '' ? '/merchant-crm.php?contact=' . rawurlencode($campaignContactId) : $profileUrl;
    $name = trim((string)($row['display_name'] ?? '')) ?: trim((string)($row['profile_display_name'] ?? '')) ?: trim((string)($row['primary_email'] ?? '')) ?: 'Unnamed contact';
    return [
        'id'=>$id,
        'username'=>$handle,
        'mention'=>'@' . $handle,
        'name'=>$name,
        'email'=>(string)($row['primary_email'] ?? ''),
        'phone'=>(string)($row['primary_phone'] ?? ''),
        'avatar_url'=>(string)($row['profile_avatar_url'] ?? ''),
        'stage'=>(string)($row['lifecycle_stage'] ?? 'lead'),
        'status'=>(string)($row['crm_status'] ?? 'active'),
        'campaign_title'=>(string)($row['campaign_title'] ?? ''),
        'campaign_type'=>(string)($row['last_campaign_type'] ?? ''),
        'source'=>(string)($row['last_source_type'] ?? ''),
        'last_activity_at'=>$row['last_engaged_at'] ?? $row['last_seen_at'] ?? null,
        'has_account'=>(int)($row['user_id'] ?? 0) > 0,
        'email_verified'=>!empty($row['email_verified_at']),
        'score'=>(int)$score['score'],
        'score_label'=>(string)$score['label'],
        'next_best_action'=>mg_merchant_crm_search_next_action($row, $score),
        'profile_url'=>$profileUrl,
        'crm_url'=>$crmBase,
        'timeline_url'=>$campaignContactId !== '' ? $crmBase . '&action=timeline' : $profileUrl,
        'message_url'=>$campaignContactId !== '' ? $crmBase . '&action=message' : $profileUrl,
        'reward_url'=>$campaignContactId !== '' ? $crmBase . '&action=reward' : $profileUrl,
    ];
}

function mg_merchant_crm_search(PDO $pdo, int $merchantId, string $query, int $limit = 20, int $offset = 0): array
{
    $query = mg_merchant_crm_search_query($query);
    $limit = max(1, min(100, $limit));
    $offset = max(0, min(10000, $offset));
    if ($query === '') return ['schema_ready'=>true,'query'=>'','contacts'=>[],'total'=>0,'limit'=>$limit,'offset'=>$offset,'has_more'=>false];
    if (!mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contacts')) {
        return ['schema_ready'=>false,'query'=>$query,'contacts'=>[],'total'=>0,'limit'=>$limit,'offset'=>$offset,'has_more'=>false];
    }

    $parts = mg_merchant_crm_search_select_parts($pdo);
    $like = mg_merchant_crm_search_like($query);
    $conditions = [
        "LOWER(COALESCE(mc.display_name,'')) LIKE ? ESCAPE '\\\\'",
        "LOWER(COALESCE(mc.primary_email,'')) LIKE ? ESCAPE '\\\\'",
        "LOWER(COALESCE(mc.primary_phone,'')) LIKE ? ESCAPE '\\\\'",
        "LOWER(COALESCE(mc.lifecycle_stage,'')) LIKE ? ESCAPE '\\\\'",
        "LOWER(COALESCE(mc.crm_status,'')) LIKE ? ESCAPE '\\\\'",
        "LOWER(COALESCE(mc.last_campaign_type,'')) LIKE ? ESCAPE '\\\\'",
        "LOWER(COALESCE(mc.last_source_type,'')) LIKE ? ESCAPE '\\\\'",
        "LOWER(COALESCE(mc.public_id,'')) LIKE ? ESCAPE '\\\\'",
    ];
    if (!empty($parts['has_profiles'])) $conditions[] = "LOWER(COALESCE(pp.slug,'')) LIKE ? ESCAPE '\\\\'";
    $params = array_fill(0, count($conditions), $like);
    $where = 'mc.merchant_user_id=? AND (' . implode(' OR ', $conditions) . ')';
    array_unshift($params, $merchantId);
    if (mg_merchant_crm_search_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id')) $where .= ' AND mc.merged_into_contact_id IS NULL';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM merchant_crm_contacts mc' . $parts['joins'] . ' WHERE ' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $order = "CASE WHEN LOWER(COALESCE(" . (!empty($parts['has_profiles']) ? "pp.slug" : "''") . ",''))=? THEN 0 WHEN LOWER(COALESCE(mc.display_name,''))=? THEN 1 WHEN LOWER(COALESCE(mc.primary_email,''))=? THEN 2 WHEN LOWER(COALESCE(" . (!empty($parts['has_profiles']) ? "pp.slug" : "''") . ",'')) LIKE ? THEN 3 WHEN LOWER(COALESCE(mc.display_name,'')) LIKE ? THEN 4 ELSE 5 END";
    $prefix = mb_strtolower($query) . '%';
    $dataParams = array_merge($params, [mb_strtolower($query), mb_strtolower($query), mb_strtolower($query), $prefix, $prefix]);
    $sql = 'SELECT ' . $parts['select'] . ' FROM merchant_crm_contacts mc' . $parts['joins'] . ' WHERE ' . $where . ' ORDER BY ' . $order . ',COALESCE(mc.last_engaged_at,mc.last_seen_at) DESC,mc.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dataParams);
    $contacts = array_map('mg_merchant_crm_search_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    return [
        'schema_ready'=>true,
        'query'=>$query,
        'contacts'=>$contacts,
        'total'=>$total,
        'limit'=>$limit,
        'offset'=>$offset,
        'has_more'=>($offset + count($contacts)) < $total,
        'next_offset'=>($offset + count($contacts)) < $total ? $offset + count($contacts) : null,
    ];
}

function mg_merchant_crm_search_contacts_by_ids(PDO $pdo, int $merchantId, array $publicIds): array
{
    $publicIds = array_values(array_unique(array_filter(array_map(static fn($value): string => strtolower(trim((string)$value)), $publicIds), static fn(string $value): bool => preg_match('/^[a-f0-9-]{36}$/', $value) === 1)));
    $publicIds = array_slice($publicIds, 0, 8);
    if ($publicIds === [] || !mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contacts')) return [];
    $parts = mg_merchant_crm_search_select_parts($pdo);
    $where = 'mc.merchant_user_id=? AND mc.public_id IN (' . implode(',', array_fill(0, count($publicIds), '?')) . ')';
    if (mg_merchant_crm_search_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id')) $where .= ' AND mc.merged_into_contact_id IS NULL';
    $stmt = $pdo->prepare('SELECT ' . $parts['select'] . ' FROM merchant_crm_contacts mc' . $parts['joins'] . ' WHERE ' . $where . ' ORDER BY COALESCE(mc.last_engaged_at,mc.last_seen_at) DESC,mc.id DESC');
    $stmt->execute(array_merge([$merchantId], $publicIds));
    return array_map('mg_merchant_crm_search_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
}
