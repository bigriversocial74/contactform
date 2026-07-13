<?php
declare(strict_types=1);

function mg_crm_identity_uuid(): string
{
    if (function_exists('mg_merchant_crm_uuid')) return mg_merchant_crm_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_crm_identity_json(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_crm_identity_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_crm_identity_column_exists(PDO $pdo, string $table, string $column): bool
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

function mg_crm_identity_schema_ready(PDO $pdo): bool
{
    return mg_crm_identity_table_exists($pdo, 'merchant_crm_contacts')
        && mg_crm_identity_table_exists($pdo, 'merchant_crm_contact_aliases')
        && mg_crm_identity_table_exists($pdo, 'merchant_crm_contact_merges')
        && mg_crm_identity_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id')
        && mg_crm_identity_column_exists($pdo, 'merchant_crm_contacts', 'merged_at')
        && mg_crm_identity_column_exists($pdo, 'merchant_crm_contacts', 'merge_reason');
}

function mg_crm_identity_normalize_email(mixed $value): string
{
    $email = strtolower(trim((string)$value));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function mg_crm_identity_normalize_phone(mixed $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value) ?: '';
    if (strlen($digits) === 11 && str_starts_with($digits, '1')) $digits = substr($digits, 1);
    if (strlen($digits) < 7 || preg_match('/^0+$/', $digits) === 1) return '';
    return $digits;
}

function mg_crm_identity_stage_rank(string $stage): int
{
    return ['inactive'=>0,'lead'=>10,'follower'=>20,'prospect'=>30,'supporter'=>40,'redeemer'=>50,'customer'=>60,'custom'=>15][$stage] ?? 0;
}

function mg_crm_identity_best_stage(array $rows): string
{
    $best = 'lead';
    foreach ($rows as $row) {
        $candidate = (string)($row['lifecycle_stage'] ?? 'lead');
        if (mg_crm_identity_stage_rank($candidate) > mg_crm_identity_stage_rank($best)) $best = $candidate;
    }
    return $best;
}

function mg_crm_identity_resolve_contact(PDO $pdo, int $merchantId, array $contact, bool $forUpdate = false): array
{
    if (!mg_crm_identity_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id')) return $contact;
    $seen = [];
    $current = $contact;
    for ($hop = 0; $hop < 12; $hop++) {
        $id = (int)($current['id'] ?? 0);
        if ($id < 1 || isset($seen[$id])) break;
        $seen[$id] = true;
        $nextId = (int)($current['merged_into_contact_id'] ?? 0);
        if ($nextId < 1) break;
        $sql = 'SELECT * FROM merchant_crm_contacts WHERE id=? AND merchant_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nextId, $merchantId]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$next) break;
        $current = $next;
    }
    return $current;
}

function mg_crm_identity_alias_contact(PDO $pdo, int $merchantId, ?int $userId, ?string $email, ?string $phone, bool $forUpdate = false): ?array
{
    if (!mg_crm_identity_table_exists($pdo, 'merchant_crm_contact_aliases')) return null;
    $lookups = [];
    if ($userId && $userId > 0) $lookups[] = ['user_id', (string)$userId];
    $normalizedEmail = mg_crm_identity_normalize_email($email);
    if ($normalizedEmail !== '') $lookups[] = ['email', $normalizedEmail];
    $normalizedPhone = mg_crm_identity_normalize_phone($phone);
    if ($normalizedPhone !== '') $lookups[] = ['phone', $normalizedPhone];
    foreach ($lookups as [$type, $value]) {
        $sql = 'SELECT c.* FROM merchant_crm_contact_aliases a INNER JOIN merchant_crm_contacts c ON c.id=a.canonical_contact_id WHERE a.merchant_user_id=? AND a.alias_type=? AND a.normalized_value=? AND c.merchant_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$merchantId, $type, $value, $merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return mg_crm_identity_resolve_contact($pdo, $merchantId, $row, $forUpdate);
    }
    return null;
}

function mg_crm_identity_contact_score(array $row): int
{
    $score = 0;
    if ((int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0) > 0) $score += 1000;
    if ((int)($row['user_id'] ?? 0) > 0) $score += 250;
    if (mg_crm_identity_normalize_email($row['primary_email'] ?? '') !== '') $score += 80;
    if (mg_crm_identity_normalize_phone($row['primary_phone'] ?? '') !== '') $score += 40;
    $score += min(250, (int)($row['event_count'] ?? 0) * 3);
    $score += min(200, (int)($row['campaign_count'] ?? 0) * 12);
    $score += min(100, (int)($row['note_count'] ?? 0) * 5);
    $score += min(100, (int)($row['total_rewards_claimed'] ?? 0) * 10);
    $score += min(100, (int)($row['total_rewards_redeemed'] ?? 0) * 15);
    if ((string)($row['crm_status'] ?? 'active') === 'active') $score += 30;
    return $score;
}

function mg_crm_identity_duplicate_rows(PDO $pdo, int $merchantId): array
{
    $sql = "SELECT c.*,COALESCE(c.user_id,email_user.id) resolved_user_id,
                   COUNT(DISTINCT e.id) event_count,
                   COUNT(DISTINCT cc.id) campaign_count,
                   COUNT(DISTINCT n.id) note_count
            FROM merchant_crm_contacts c
            LEFT JOIN users email_user ON c.user_id IS NULL AND c.primary_email IS NOT NULL AND LOWER(email_user.email)=LOWER(c.primary_email)
            LEFT JOIN merchant_crm_contact_events e ON e.crm_contact_id=c.id
            LEFT JOIN merchant_crm_contact_campaigns cc ON cc.crm_contact_id=c.id
            LEFT JOIN merchant_crm_notes n ON n.crm_contact_id=c.id
            WHERE c.merchant_user_id=?
              AND c.crm_status<>'archived'
              AND c.merged_into_contact_id IS NULL
            GROUP BY c.id,email_user.id
            ORDER BY c.updated_at DESC,c.id DESC
            LIMIT 1500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_crm_identity_signal_components(array $rows): array
{
    $parent = [];
    $rank = [];
    $buckets = ['account'=>[], 'email'=>[], 'phone'=>[]];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $parent[$id] = $id;
        $rank[$id] = 0;
        $account = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
        if ($account > 0) $buckets['account'][(string)$account][] = $id;
        $email = mg_crm_identity_normalize_email($row['primary_email'] ?? '');
        if ($email !== '') $buckets['email'][$email][] = $id;
        $phone = mg_crm_identity_normalize_phone($row['primary_phone'] ?? '');
        if ($phone !== '') $buckets['phone'][$phone][] = $id;
    }

    $find = function (int $id) use (&$find, &$parent): int {
        if ($parent[$id] !== $id) $parent[$id] = $find($parent[$id]);
        return $parent[$id];
    };
    $union = function (int $a, int $b) use (&$parent, &$rank, $find): void {
        $ra = $find($a);
        $rb = $find($b);
        if ($ra === $rb) return;
        if ($rank[$ra] < $rank[$rb]) [$ra, $rb] = [$rb, $ra];
        $parent[$rb] = $ra;
        if ($rank[$ra] === $rank[$rb]) $rank[$ra]++;
    };

    foreach ($buckets as $type => $values) {
        foreach ($values as $value => $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) continue;
            $first = $ids[0];
            foreach (array_slice($ids, 1) as $id) $union($first, $id);
        }
    }

    $groups = [];
    foreach ($rows as $row) {
        $root = $find((int)$row['id']);
        $groups[$root][] = $row;
    }

    return ['groups'=>$groups, 'buckets'=>$buckets];
}

function mg_crm_identity_duplicate_groups(PDO $pdo, int $merchantId): array
{
    if (!mg_crm_identity_schema_ready($pdo)) {
        return [
            'schema_ready'=>false,
            'migration'=>'crm_contact_identity_duplicate_management_v1.sql',
            'groups'=>[],
            'summary'=>['active_contacts'=>0,'duplicate_groups'=>0,'duplicate_contacts'=>0,'merge_ready_groups'=>0,'blocked_groups'=>0,'merged_profiles'=>0],
        ];
    }

    $rows = mg_crm_identity_duplicate_rows($pdo, $merchantId);
    $components = mg_crm_identity_signal_components($rows);
    $groups = [];
    $duplicateContactIds = [];

    foreach ($components['groups'] as $componentRows) {
        if (count($componentRows) < 2) continue;
        $memberIds = array_map(static fn(array $row): int => (int)$row['id'], $componentRows);
        $memberSet = array_fill_keys($memberIds, true);
        $signals = [];
        foreach ($components['buckets'] as $type => $values) {
            foreach ($values as $value => $ids) {
                $matches = array_values(array_filter(array_unique($ids), static fn(int $id): bool => isset($memberSet[$id])));
                if (count($matches) > 1) $signals[] = ['type'=>$type,'value'=>$value,'member_count'=>count($matches)];
            }
        }

        $resolvedAccounts = [];
        foreach ($componentRows as $row) {
            $account = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
            if ($account > 0) $resolvedAccounts[$account] = true;
        }
        $blockedReason = count($resolvedAccounts) > 1 ? 'Contacts resolve to different Microgifter accounts and cannot be merged.' : '';
        $matchTypes = array_values(array_unique(array_column($signals, 'type')));
        $confidence = in_array('account', $matchTypes, true) ? 100 : (in_array('email', $matchTypes, true) ? 96 : 72);
        if (count($matchTypes) > 1) $confidence = min(100, $confidence + 4);
        $confidenceLabel = $confidence >= 95 ? 'Strong identity match' : 'Review phone match';

        usort($componentRows, static function (array $a, array $b): int {
            $scoreCompare = mg_crm_identity_contact_score($b) <=> mg_crm_identity_contact_score($a);
            if ($scoreCompare !== 0) return $scoreCompare;
            return (int)$a['id'] <=> (int)$b['id'];
        });
        $suggested = (string)$componentRows[0]['public_id'];
        $members = [];
        foreach ($componentRows as $row) {
            $duplicateContactIds[(int)$row['id']] = true;
            $members[] = [
                'id'=>(string)$row['public_id'],
                'name'=>(string)($row['display_name'] ?: $row['primary_email'] ?: 'Unnamed contact'),
                'email'=>(string)($row['primary_email'] ?? ''),
                'phone'=>(string)($row['primary_phone'] ?? ''),
                'user_id'=>(int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0),
                'account_linked'=>(int)($row['user_id'] ?? 0) > 0,
                'stage'=>(string)($row['lifecycle_stage'] ?? 'lead'),
                'status'=>(string)($row['crm_status'] ?? 'active'),
                'event_count'=>(int)($row['event_count'] ?? 0),
                'campaign_count'=>(int)($row['campaign_count'] ?? 0),
                'note_count'=>(int)($row['note_count'] ?? 0),
                'first_seen_at'=>$row['first_seen_at'] ?? null,
                'last_seen_at'=>$row['last_seen_at'] ?? null,
                'identity_score'=>mg_crm_identity_contact_score($row),
                'suggested_canonical'=>(string)$row['public_id'] === $suggested,
                'profile_url'=>'/merchant-customer.php?contact_id=' . rawurlencode((string)$row['public_id']),
            ];
        }
        $groups[] = [
            'id'=>substr(hash('sha256', implode('|', array_map(static fn(array $member): string => $member['id'], $members))), 0, 20),
            'confidence_score'=>$confidence,
            'confidence_label'=>$confidenceLabel,
            'match_types'=>$matchTypes,
            'signals'=>$signals,
            'member_count'=>count($members),
            'suggested_canonical_id'=>$suggested,
            'merge_allowed'=>$blockedReason === '',
            'blocked_reason'=>$blockedReason,
            'members'=>$members,
        ];
    }

    usort($groups, static fn(array $a, array $b): int => ($b['confidence_score'] <=> $a['confidence_score']) ?: ($b['member_count'] <=> $a['member_count']));
    $mergedProfiles = 0;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM merchant_crm_contacts WHERE merchant_user_id=? AND merged_into_contact_id IS NOT NULL');
        $stmt->execute([$merchantId]);
        $mergedProfiles = (int)$stmt->fetchColumn();
    } catch (Throwable) {}

    return [
        'schema_ready'=>true,
        'migration'=>'crm_contact_identity_duplicate_management_v1.sql',
        'groups'=>$groups,
        'summary'=>[
            'active_contacts'=>count($rows),
            'duplicate_groups'=>count($groups),
            'duplicate_contacts'=>count($duplicateContactIds),
            'merge_ready_groups'=>count(array_filter($groups, static fn(array $group): bool => (bool)$group['merge_allowed'])),
            'blocked_groups'=>count(array_filter($groups, static fn(array $group): bool => !(bool)$group['merge_allowed'])),
            'merged_profiles'=>$mergedProfiles,
        ],
    ];
}

function mg_crm_identity_alias_values(array $row): array
{
    $aliases = [['public_id', (string)$row['public_id'], strtolower((string)$row['public_id'])]];
    $email = mg_crm_identity_normalize_email($row['primary_email'] ?? '');
    if ($email !== '') $aliases[] = ['email', (string)$row['primary_email'], $email];
    $phone = mg_crm_identity_normalize_phone($row['primary_phone'] ?? '');
    if ($phone !== '') $aliases[] = ['phone', (string)$row['primary_phone'], $phone];
    $userId = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
    if ($userId > 0) $aliases[] = ['user_id', (string)$userId, (string)$userId];
    return $aliases;
}

function mg_crm_identity_selected_connected(array $rows): array
{
    $components = mg_crm_identity_signal_components($rows);
    $nonEmpty = array_values(array_filter($components['groups'], static fn(array $group): bool => $group !== []));
    $types = [];
    foreach ($components['buckets'] as $type => $values) {
        foreach ($values as $ids) if (count(array_unique($ids)) > 1) $types[$type] = true;
    }
    return ['connected'=>count($nonEmpty) === 1,'match_types'=>array_keys($types)];
}

function mg_crm_identity_merge_contacts(PDO $pdo, int $merchantId, int $actorUserId, string $canonicalPublicId, array $sourcePublicIds, string $reason = ''): array
{
    if (!mg_crm_identity_schema_ready($pdo)) throw new RuntimeException('CRM identity schema is not installed.');
    $canonicalPublicId = strtolower(trim($canonicalPublicId));
    $sourcePublicIds = array_values(array_unique(array_filter(array_map(static fn($value): string => strtolower(trim((string)$value)), $sourcePublicIds))));
    $sourcePublicIds = array_values(array_filter($sourcePublicIds, static fn(string $id): bool => $id !== $canonicalPublicId));
    if (preg_match('/^[a-f0-9-]{36}$/', $canonicalPublicId) !== 1 || $sourcePublicIds === []) throw new InvalidArgumentException('Choose one canonical profile and at least one duplicate profile.');
    foreach ($sourcePublicIds as $id) if (preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) throw new InvalidArgumentException('One or more duplicate profile ids are invalid.');
    if (count($sourcePublicIds) > 20) throw new InvalidArgumentException('A maximum of 20 duplicate profiles can be merged at once.');
    $reason = mb_substr(trim($reason), 0, 500);

    $allPublicIds = array_merge([$canonicalPublicId], $sourcePublicIds);
    $placeholders = implode(',', array_fill(0, count($allPublicIds), '?'));
    $params = array_merge([$merchantId], $allPublicIds);

    $pdo->beginTransaction();
    try {
        $sql = "SELECT c.*,COALESCE(c.user_id,email_user.id) resolved_user_id
                FROM merchant_crm_contacts c
                LEFT JOIN users email_user ON c.user_id IS NULL AND c.primary_email IS NOT NULL AND LOWER(email_user.email)=LOWER(c.primary_email)
                WHERE c.merchant_user_id=? AND c.public_id IN ($placeholders)
                ORDER BY c.id FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== count($allPublicIds)) throw new RuntimeException('One or more CRM profiles were not found for this merchant.');
        $byPublic = [];
        foreach ($rows as $row) $byPublic[(string)$row['public_id']] = $row;
        $canonical = $byPublic[$canonicalPublicId] ?? null;
        if (!$canonical || (int)($canonical['merged_into_contact_id'] ?? 0) > 0 || (string)($canonical['crm_status'] ?? '') === 'archived') throw new RuntimeException('The selected canonical profile is not active.');
        $sources = [];
        foreach ($sourcePublicIds as $sourceId) {
            $source = $byPublic[$sourceId] ?? null;
            if (!$source || (int)($source['merged_into_contact_id'] ?? 0) > 0 || (string)($source['crm_status'] ?? '') === 'archived') throw new RuntimeException('One or more duplicate profiles have already been merged or archived.');
            $sources[] = $source;
        }

        $connectivity = mg_crm_identity_selected_connected(array_merge([$canonical], $sources));
        if (!$connectivity['connected']) throw new RuntimeException('The selected profiles do not share a verified account, email, or phone identity signal.');
        $resolvedAccounts = [];
        foreach (array_merge([$canonical], $sources) as $row) {
            $account = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
            if ($account > 0) $resolvedAccounts[$account] = true;
        }
        if (count($resolvedAccounts) > 1) throw new RuntimeException('Profiles linked to different Microgifter accounts cannot be merged.');

        $batchId = mg_crm_identity_uuid();
        $canonicalId = (int)$canonical['id'];
        $canonicalBefore = $canonical;
        $allRows = array_merge([$canonical], $sources);
        $tags = [];
        $metadata = mg_crm_identity_json($canonical['metadata_json'] ?? null);
        $mergedSourceIds = is_array($metadata['merged_source_public_ids'] ?? null) ? $metadata['merged_source_public_ids'] : [];
        foreach ($allRows as $row) {
            foreach (mg_crm_identity_json($row['tags_json'] ?? null) as $tag) {
                $tag = trim((string)$tag);
                if ($tag !== '') $tags[strtolower($tag)] = $tag;
            }
            if ((string)$row['public_id'] !== $canonicalPublicId) $mergedSourceIds[] = (string)$row['public_id'];
        }
        $metadata['identity_version'] = 1;
        $metadata['last_merge_batch_public_id'] = $batchId;
        $metadata['merged_source_public_ids'] = array_values(array_unique($mergedSourceIds));

        $chosenUserId = (int)($canonical['user_id'] ?? 0);
        if ($chosenUserId < 1 && $resolvedAccounts) $chosenUserId = (int)array_key_first($resolvedAccounts);
        $chosenEmail = mg_crm_identity_normalize_email($canonical['primary_email'] ?? '');
        if ($chosenEmail === '') {
            foreach ($sources as $source) {
                $candidate = mg_crm_identity_normalize_email($source['primary_email'] ?? '');
                if ($candidate !== '') { $chosenEmail = $candidate; break; }
            }
        }
        $chosenPhone = trim((string)($canonical['primary_phone'] ?? ''));
        if (mg_crm_identity_normalize_phone($chosenPhone) === '') {
            foreach ($sources as $source) {
                if (mg_crm_identity_normalize_phone($source['primary_phone'] ?? '') !== '') { $chosenPhone = trim((string)$source['primary_phone']); break; }
            }
        }
        $chosenName = trim((string)($canonical['display_name'] ?? ''));
        if ($chosenName === '' || strtolower($chosenName) === 'customer') {
            foreach ($sources as $source) {
                $candidate = trim((string)($source['display_name'] ?? ''));
                if ($candidate !== '' && strtolower($candidate) !== 'customer') { $chosenName = $candidate; break; }
            }
        }

        $firstSeen = null;
        $lastSeen = null;
        $lastEngaged = null;
        $dateColumns = ['last_followed_at','last_purchased_at','last_reward_issued_at','last_reward_claimed_at','last_reward_redeemed_at'];
        $latestDates = array_fill_keys($dateColumns, null);
        $totals = ['total_purchase_cents'=>0,'total_rewards_issued'=>0,'total_rewards_claimed'=>0,'total_rewards_redeemed'=>0];
        foreach ($allRows as $row) {
            $first = strtotime((string)($row['first_seen_at'] ?? '')) ?: 0;
            if ($first > 0 && ($firstSeen === null || $first < $firstSeen)) $firstSeen = $first;
            $last = strtotime((string)($row['last_seen_at'] ?? '')) ?: 0;
            if ($last > 0 && ($lastSeen === null || $last > $lastSeen)) $lastSeen = $last;
            $engaged = strtotime((string)($row['last_engaged_at'] ?? '')) ?: 0;
            if ($engaged > 0 && ($lastEngaged === null || $engaged > $lastEngaged)) $lastEngaged = $engaged;
            foreach ($dateColumns as $column) {
                $time = strtotime((string)($row[$column] ?? '')) ?: 0;
                if ($time > 0 && ($latestDates[$column] === null || $time > $latestDates[$column])) $latestDates[$column] = $time;
            }
            foreach ($totals as $column => $unused) $totals[$column] += max(0, (int)($row[$column] ?? 0));
        }

        $aliasStmt = $pdo->prepare("INSERT INTO merchant_crm_contact_aliases (public_id,merchant_user_id,canonical_contact_id,source_contact_id,alias_type,alias_value,normalized_value,created_at) VALUES (?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE canonical_contact_id=VALUES(canonical_contact_id),source_contact_id=COALESCE(VALUES(source_contact_id),source_contact_id)");
        foreach ($allRows as $row) {
            $sourceId = (string)$row['public_id'] === $canonicalPublicId ? null : (int)$row['id'];
            foreach (mg_crm_identity_alias_values($row) as [$type, $value, $normalized]) {
                $aliasStmt->execute([mg_crm_identity_uuid(),$merchantId,$canonicalId,$sourceId,$type,mb_substr($value,0,255),mb_substr($normalized,0,255)]);
            }
        }

        $matchType = implode('+', $connectivity['match_types']) ?: 'manual_review';
        $confidence = in_array('account', $connectivity['match_types'], true) ? 100 : (in_array('email', $connectivity['match_types'], true) ? 96 : 72);
        if (count($connectivity['match_types']) > 1) $confidence = min(100, $confidence + 4);
        $movedTotals = ['events'=>0,'campaigns'=>0,'notes'=>0,'sources'=>count($sources)];

        foreach ($sources as $source) {
            $sourceId = (int)$source['id'];
            $counts = [];
            foreach (['merchant_crm_contact_events'=>'events','merchant_crm_contact_campaigns'=>'campaigns','merchant_crm_notes'=>'notes'] as $table => $label) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE crm_contact_id=?");
                $countStmt->execute([$sourceId]);
                $counts[$label] = (int)$countStmt->fetchColumn();
                $movedTotals[$label] += $counts[$label];
            }

            $campaignStmt = $pdo->prepare('SELECT campaign_id,campaign_type,first_event_at,last_event_at,event_count,metadata_json,created_at,updated_at FROM merchant_crm_contact_campaigns WHERE crm_contact_id=? ORDER BY id FOR UPDATE');
            $campaignStmt->execute([$sourceId]);
            $upsertCampaign = $pdo->prepare("INSERT INTO merchant_crm_contact_campaigns (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,first_event_at,last_event_at,event_count,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE first_event_at=LEAST(first_event_at,VALUES(first_event_at)),last_event_at=GREATEST(last_event_at,VALUES(last_event_at)),event_count=event_count+VALUES(event_count),metadata_json=COALESCE(VALUES(metadata_json),metadata_json),updated_at=GREATEST(updated_at,VALUES(updated_at))");
            foreach ($campaignStmt->fetchAll(PDO::FETCH_ASSOC) as $campaign) {
                $upsertCampaign->execute([mg_crm_identity_uuid(),$merchantId,$canonicalId,(int)$campaign['campaign_id'],(string)$campaign['campaign_type'],$campaign['first_event_at'],$campaign['last_event_at'],(int)$campaign['event_count'],$campaign['metadata_json'],$campaign['created_at'],$campaign['updated_at']]);
            }
            $pdo->prepare('DELETE FROM merchant_crm_contact_campaigns WHERE crm_contact_id=?')->execute([$sourceId]);
            $pdo->prepare('UPDATE merchant_crm_contact_events SET crm_contact_id=? WHERE crm_contact_id=?')->execute([$canonicalId,$sourceId]);
            $pdo->prepare('UPDATE merchant_crm_notes SET crm_contact_id=? WHERE crm_contact_id=?')->execute([$canonicalId,$sourceId]);

            $pdo->prepare("UPDATE merchant_crm_contacts SET user_id=NULL,primary_email=NULL,primary_phone=NULL,crm_status='archived',merged_into_contact_id=?,merged_at=NOW(),merge_reason=?,updated_at=NOW() WHERE id=? AND merchant_user_id=?")
                ->execute([$canonicalId,$reason !== '' ? $reason : 'Merged through CRM Contact Identity v1',$sourceId,$merchantId]);

            $pdo->prepare('INSERT INTO merchant_crm_contact_merges (public_id,merge_batch_public_id,merchant_user_id,canonical_contact_id,source_contact_id,actor_user_id,match_type,confidence_score,reason,canonical_before_json,source_before_json,moved_counts_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
                ->execute([mg_crm_identity_uuid(),$batchId,$merchantId,$canonicalId,$sourceId,$actorUserId,$matchType,$confidence,$reason !== '' ? $reason : null,json_encode($canonicalBefore,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode($source,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode($counts,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        }

        $formatDate = static fn(?int $time): ?string => $time ? date('Y-m-d H:i:s', $time) : null;
        $update = $pdo->prepare('UPDATE merchant_crm_contacts SET user_id=?,primary_email=?,primary_phone=?,display_name=?,lifecycle_stage=?,crm_status=\'active\',first_seen_at=?,last_seen_at=?,last_engaged_at=?,last_followed_at=?,last_purchased_at=?,last_reward_issued_at=?,last_reward_claimed_at=?,last_reward_redeemed_at=?,total_purchase_cents=?,total_rewards_issued=?,total_rewards_claimed=?,total_rewards_redeemed=?,tags_json=?,metadata_json=?,merged_into_contact_id=NULL,merged_at=NULL,merge_reason=NULL,updated_at=NOW() WHERE id=? AND merchant_user_id=?');
        $update->execute([
            $chosenUserId > 0 ? $chosenUserId : null,
            $chosenEmail !== '' ? $chosenEmail : null,
            $chosenPhone !== '' ? $chosenPhone : null,
            $chosenName !== '' ? $chosenName : null,
            mg_crm_identity_best_stage($allRows),
            $formatDate($firstSeen) ?? date('Y-m-d H:i:s'),
            $formatDate($lastSeen) ?? date('Y-m-d H:i:s'),
            $formatDate($lastEngaged),
            $formatDate($latestDates['last_followed_at']),
            $formatDate($latestDates['last_purchased_at']),
            $formatDate($latestDates['last_reward_issued_at']),
            $formatDate($latestDates['last_reward_claimed_at']),
            $formatDate($latestDates['last_reward_redeemed_at']),
            $totals['total_purchase_cents'],
            $totals['total_rewards_issued'],
            $totals['total_rewards_claimed'],
            $totals['total_rewards_redeemed'],
            json_encode(array_values($tags),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            $canonicalId,
            $merchantId,
        ]);

        $eventMetadata = ['merge_batch_public_id'=>$batchId,'source_contact_public_ids'=>$sourcePublicIds,'moved'=>$movedTotals,'match_type'=>$matchType,'confidence_score'=>$confidence];
        $pdo->prepare("INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,'merchant_crm','crm_contact_merged','merchant_crm_identity',?,?,?,?,?,NULL,?,NOW())")
            ->execute([mg_crm_identity_uuid(),$merchantId,$canonicalId,$batchId,$chosenUserId > 0 ? $chosenUserId : null,$chosenEmail !== '' ? $chosenEmail : null,$chosenPhone !== '' ? $chosenPhone : null,$chosenName !== '' ? $chosenName : null,json_encode($eventMetadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);

        $pdo->commit();
        return [
            'merge_batch_id'=>$batchId,
            'canonical_contact_id'=>$canonicalPublicId,
            'merged_source_contact_ids'=>$sourcePublicIds,
            'moved'=>$movedTotals,
            'match_type'=>$matchType,
            'confidence_score'=>$confidence,
            'profile_url'=>'/merchant-customer.php?contact_id=' . rawurlencode($canonicalPublicId),
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
