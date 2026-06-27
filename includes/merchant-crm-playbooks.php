<?php
declare(strict_types=1);

function mg_crm_playbook_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 15) | 64);
    $b[8] = chr((ord($b[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function mg_crm_playbook_defs(): array
{
    return [
        'reward_unclaimed_after_3d' => ['key' => 'reward_unclaimed_after_3d', 'title' => 'Reward issued but not claimed', 'trigger' => 'Wallet reward remains issued/viewed after 3 days.', 'automation_level' => 'monitor_and_create_task', 'action_type' => 'create_followup_task', 'default_due_days' => 1, 'recommended_next_action' => 'Message customer and remind them to claim the reward.', 'agentic_ready' => true],
        'claimed_not_redeemed_after_3d' => ['key' => 'claimed_not_redeemed_after_3d', 'title' => 'Reward claimed but not redeemed', 'trigger' => 'Wallet reward is claimed for 3+ days without redemption.', 'automation_level' => 'monitor_and_create_task', 'action_type' => 'create_followup_task', 'default_due_days' => 1, 'recommended_next_action' => 'Invite the customer back in and offer help redeeming.', 'agentic_ready' => true],
        'high_value_inactive_after_30d' => ['key' => 'high_value_inactive_after_30d', 'title' => 'High-value customer inactive', 'trigger' => 'Customer value is above $50 and no engagement for 30+ days.', 'automation_level' => 'monitor_and_recommend', 'action_type' => 'suggest_reward_or_message', 'default_due_days' => 2, 'recommended_next_action' => 'Create a win-back follow-up and consider a VIP reward.', 'agentic_ready' => true],
        'contest_entrant_reward_invite' => ['key' => 'contest_entrant_reward_invite', 'title' => 'Contest entrant reward invite', 'trigger' => 'Contest entrant has no wallet reward yet.', 'automation_level' => 'monitor_and_recommend', 'action_type' => 'suggest_reward_invite', 'default_due_days' => 1, 'recommended_next_action' => 'Send a reward invite or create a follow-up task.', 'agentic_ready' => true],
        'tip_thank_you_followup' => ['key' => 'tip_thank_you_followup', 'title' => 'Tip received thank-you', 'trigger' => 'Tip activity appears in merchant notifications.', 'automation_level' => 'monitor_and_recommend', 'action_type' => 'message_draft', 'default_due_days' => 1, 'recommended_next_action' => 'Send a thank-you message and add a customer note.', 'agentic_ready' => true],
    ];
}

function mg_crm_playbook_contact_filter(PDO $pdo, int $merchantId, array $input, string $alias = 'cc'): array
{
    $where = [];
    $params = [];
    $campaignContactId = strtolower(trim((string)($input['campaign_contact_id'] ?? '')));
    $crmContactId = strtolower(trim((string)($input['contact_id'] ?? $input['crm_contact_id'] ?? '')));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $userId = (int)($input['user_id'] ?? 0);
    if ($campaignContactId !== '') {
        if (preg_match('/^[a-f0-9-]{36}$/i', $campaignContactId) !== 1) return ['where' => ['1=0'], 'params' => []];
        return ['where' => ["{$alias}.public_id=?"], 'params' => [$campaignContactId]];
    }
    if ($crmContactId !== '') {
        if (preg_match('/^[a-f0-9-]{36}$/i', $crmContactId) !== 1) return ['where' => ['1=0'], 'params' => []];
        $stmt = $pdo->prepare('SELECT primary_email,user_id FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([$crmContactId, $merchantId]);
        $crm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$crm) return ['where' => ['1=0'], 'params' => []];
        $sub = [];
        if (!empty($crm['primary_email'])) { $sub[] = "LOWER({$alias}.email)=?"; $params[] = strtolower((string)$crm['primary_email']); }
        if ((int)($crm['user_id'] ?? 0) > 0) { $sub[] = "{$alias}.user_id=?"; $params[] = (int)$crm['user_id']; }
        return ['where' => $sub ? ['(' . implode(' OR ', $sub) . ')'] : ['1=0'], 'params' => $params];
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) { $where[] = "LOWER({$alias}.email)=?"; $params[] = $email; }
    if ($userId > 0) { $where[] = "{$alias}.user_id=?"; $params[] = $userId; }
    return ['where' => $where, 'params' => $params];
}

function mg_crm_playbook_rec(array $def, array $row, string $reason, string $sourceId, ?int $contactDbId = null, ?int $campaignDbId = null): array
{
    $contactId = (string)($row['campaign_contact_public_id'] ?? $row['contact_public_id'] ?? '');
    $contactName = (string)($row['contact_name'] ?? $row['name'] ?? '') ?: 'Customer';
    $contactEmail = (string)($row['contact_email'] ?? $row['email'] ?? '');
    $campaignTitle = (string)($row['campaign_title'] ?? '');
    return [
        'id' => $def['key'] . ':' . $sourceId,
        'playbook_key' => $def['key'],
        'playbook_title' => $def['title'],
        'reason' => $reason,
        'recommended_next_action' => $def['recommended_next_action'],
        'action_type' => $def['action_type'],
        'automation_level' => $def['automation_level'],
        'triggered_by_playbook' => true,
        'source_id' => $sourceId,
        'campaign_contact_id' => $contactId,
        'customer_name' => $contactName,
        'customer_email' => $contactEmail,
        'campaign_title' => $campaignTitle,
        'customer_url' => $contactId !== '' ? '/merchant-customer.php?campaign_contact_id=' . rawurlencode($contactId) . '&tab=followups' : '',
        'message_url' => $contactId !== '' ? '/merchant-crm.php?tab=contacts&action=message&campaign_contact_id=' . rawurlencode($contactId) : '',
        'reward_url' => $contactId !== '' ? '/merchant-crm.php?tab=contacts&action=reward&campaign_contact_id=' . rawurlencode($contactId) : '',
        '_contact_db_id' => $contactDbId,
        '_campaign_db_id' => $campaignDbId,
    ];
}

function mg_crm_playbook_scan(PDO $pdo, int $merchantId, array $input = []): array
{
    $defs = mg_crm_playbook_defs();
    $only = strtolower(trim((string)($input['playbook_key'] ?? '')));
    $limit = max(1, min(75, (int)($input['limit'] ?? 25)));
    $filter = mg_crm_playbook_contact_filter($pdo, $merchantId, $input, 'cc');
    $recs = [];

    if ($only === '' || $only === 'reward_unclaimed_after_3d') {
        $where = ['wi.merchant_user_id=?', "wi.status IN ('issued','viewed')", 'wi.issued_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)'];
        $params = [$merchantId];
        $where = array_merge($where, $filter['where']);
        $params = array_merge($params, $filter['params']);
        $sql = 'SELECT wi.public_id source_public_id,wi.issued_at,cc.id contact_db_id,cc.public_id campaign_contact_public_id,cc.email contact_email,cc.name contact_name,c.id campaign_db_id,c.title campaign_title FROM wallet_items wi INNER JOIN campaign_contacts cc ON cc.id=wi.contact_id AND cc.merchant_user_id=wi.merchant_user_id LEFT JOIN campaigns c ON c.id=wi.campaign_id AND c.merchant_user_id=wi.merchant_user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY wi.issued_at ASC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $recs[] = mg_crm_playbook_rec($defs['reward_unclaimed_after_3d'], $row, 'Reward has not been claimed since ' . (string)$row['issued_at'] . '.', (string)$row['source_public_id'], (int)$row['contact_db_id'], (int)$row['campaign_db_id']);
    }

    if ($only === '' || $only === 'claimed_not_redeemed_after_3d') {
        $where = ['wi.merchant_user_id=?', "wi.status='claimed'", 'wi.claimed_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)'];
        $params = [$merchantId];
        $where = array_merge($where, $filter['where']);
        $params = array_merge($params, $filter['params']);
        $sql = 'SELECT wi.public_id source_public_id,wi.claimed_at,cc.id contact_db_id,cc.public_id campaign_contact_public_id,cc.email contact_email,cc.name contact_name,c.id campaign_db_id,c.title campaign_title FROM wallet_items wi INNER JOIN campaign_contacts cc ON cc.id=wi.contact_id AND cc.merchant_user_id=wi.merchant_user_id LEFT JOIN campaigns c ON c.id=wi.campaign_id AND c.merchant_user_id=wi.merchant_user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY wi.claimed_at ASC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $recs[] = mg_crm_playbook_rec($defs['claimed_not_redeemed_after_3d'], $row, 'Reward was claimed but has not been redeemed since ' . (string)$row['claimed_at'] . '.', (string)$row['source_public_id'], (int)$row['contact_db_id'], (int)$row['campaign_db_id']);
    }

    if ($only === '' || $only === 'contest_entrant_reward_invite') {
        $where = ['cc.merchant_user_id=?', "cc.source='contest_entry'", 'NOT EXISTS (SELECT 1 FROM wallet_items wi WHERE wi.merchant_user_id=cc.merchant_user_id AND wi.contact_id=cc.id LIMIT 1)'];
        $params = [$merchantId];
        $where = array_merge($where, $filter['where']);
        $params = array_merge($params, $filter['params']);
        $sql = 'SELECT cc.id contact_db_id,cc.public_id campaign_contact_public_id,cc.email contact_email,cc.name contact_name,c.id campaign_db_id,c.title campaign_title FROM campaign_contacts cc LEFT JOIN campaigns c ON c.id=cc.campaign_id AND c.merchant_user_id=cc.merchant_user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY cc.created_at DESC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $recs[] = mg_crm_playbook_rec($defs['contest_entrant_reward_invite'], $row, 'Contest entrant has no issued wallet reward yet.', (string)$row['campaign_contact_public_id'], (int)$row['contact_db_id'], (int)$row['campaign_db_id']);
    }

    if ($only === '' || $only === 'high_value_inactive_after_30d') {
        $where = ['mcc.merchant_user_id=?', 'mcc.total_purchase_cents>=5000', 'COALESCE(mcc.last_engaged_at,mcc.last_seen_at,mcc.updated_at) <= DATE_SUB(NOW(), INTERVAL 30 DAY)'];
        $params = [$merchantId];
        if (!empty($filter['where'])) { $where[] = str_replace('cc.', 'cc.', '(' . implode(' AND ', $filter['where']) . ')'); $params = array_merge($params, $filter['params']); }
        $sql = 'SELECT mcc.public_id source_public_id,mcc.display_name contact_name,mcc.primary_email contact_email,cc.id contact_db_id,cc.public_id campaign_contact_public_id,c.id campaign_db_id,c.title campaign_title FROM merchant_crm_contacts mcc LEFT JOIN campaign_contacts cc ON cc.merchant_user_id=mcc.merchant_user_id AND ((mcc.primary_email IS NOT NULL AND cc.email=mcc.primary_email) OR (mcc.user_id IS NOT NULL AND cc.user_id=mcc.user_id)) LEFT JOIN campaigns c ON c.id=cc.campaign_id AND c.merchant_user_id=cc.merchant_user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY mcc.total_purchase_cents DESC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $recs[] = mg_crm_playbook_rec($defs['high_value_inactive_after_30d'], $row, 'High-value customer has not engaged in 30+ days.', (string)$row['source_public_id'], !empty($row['contact_db_id']) ? (int)$row['contact_db_id'] : null, !empty($row['campaign_db_id']) ? (int)$row['campaign_db_id'] : null);
    }

    return array_slice($recs, 0, $limit);
}

function mg_crm_playbook_public_recs(array $recs): array
{
    return array_map(static function (array $rec): array {
        unset($rec['_contact_db_id'], $rec['_campaign_db_id']);
        return $rec;
    }, $recs);
}

function mg_crm_playbook_summary(array $recs): array
{
    $summary = ['total' => count($recs), 'create_followup_task' => 0, 'message_draft' => 0, 'suggest_reward_or_message' => 0, 'suggest_reward_invite' => 0];
    foreach ($recs as $rec) {
        $type = (string)($rec['action_type'] ?? '');
        if (isset($summary[$type])) $summary[$type]++;
    }
    return $summary;
}

function mg_crm_playbook_create_followup(PDO $pdo, int $merchantId, array $rec, array $def): array
{
    $contactDbId = (int)($rec['_contact_db_id'] ?? 0);
    if ($contactDbId <= 0) return ['status' => 'skipped', 'reason' => 'missing_campaign_contact', 'recommendation_id' => $rec['id']];
    $campaignDbId = (int)($rec['_campaign_db_id'] ?? 0) ?: null;
    $sourceId = (string)$rec['source_id'];
    $dupe = $pdo->prepare("SELECT public_id FROM campaign_events WHERE merchant_user_id=? AND contact_id=? AND event_type='crm.followup.created' AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.playbook_key'))=? AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.source_id'))=? LIMIT 1");
    $dupe->execute([$merchantId, $contactDbId, (string)$def['key'], $sourceId]);
    $existing = (string)($dupe->fetchColumn() ?: '');
    if ($existing !== '') return ['status' => 'duplicate', 'event_id' => $existing, 'recommendation_id' => $rec['id']];
    $followupId = mg_crm_playbook_uuid();
    $dueAt = date('Y-m-d', strtotime('+' . (int)$def['default_due_days'] . ' days'));
    $context = ['note' => (string)$def['recommended_next_action'], 'due_at' => $dueAt, 'playbook_key' => (string)$def['key'], 'playbook_title' => (string)$def['title'], 'source_id' => $sourceId, 'recommendation_id' => (string)$rec['id'], 'triggered_by_playbook' => true, 'automation_level' => (string)$def['automation_level'], 'status' => 'open'];
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$followupId, $merchantId, $campaignDbId, $contactDbId, 'crm.followup.created', json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $triggerId = mg_crm_playbook_uuid();
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$triggerId, $merchantId, $campaignDbId, $contactDbId, 'crm.playbook.triggered', json_encode(['playbook_key' => (string)$def['key'], 'followup_id' => $followupId, 'source_id' => $sourceId, 'recommendation_id' => (string)$rec['id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return ['status' => 'created', 'event_id' => $followupId, 'recommendation_id' => $rec['id'], 'due_at' => $dueAt];
}
