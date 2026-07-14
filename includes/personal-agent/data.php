<?php
declare(strict_types=1);

function mg_personal_agent_next_occurrence(string $date, bool $annual, DateTimeImmutable $today): ?DateTimeImmutable
{
    try {
        $source = new DateTimeImmutable($date, new DateTimeZone('UTC'));
    } catch (Throwable) {
        return null;
    }
    if (!$annual) return $source->setTime(0, 0);
    $month = (int) $source->format('m');
    $day = (int) $source->format('d');
    $year = (int) $today->format('Y');
    $candidate = DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-' . $day, new DateTimeZone('UTC'));
    if (!$candidate) return null;
    if ($candidate < $today->setTime(0, 0)) {
        $candidate = DateTimeImmutable::createFromFormat('!Y-n-j', ($year + 1) . '-' . $month . '-' . $day, new DateTimeZone('UTC'));
    }
    return $candidate ?: null;
}

function mg_personal_agent_upcoming_dates(PDO $pdo, int $userId, int $horizonDays = 90, int $limit = 100): array
{
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $cutoff = $today->modify('+' . max(1, min(365, $horizonDays)) . ' days');
    $events = [];

    $birthdays = $pdo->prepare("SELECT public_id contact_id,display_name,birthdate,relationship_type,relationship_label
        FROM user_contacts WHERE owner_user_id=? AND archived_at IS NULL AND birthdate IS NOT NULL");
    $birthdays->execute([$userId]);
    foreach ($birthdays->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $next = mg_personal_agent_next_occurrence((string) $row['birthdate'], true, $today);
        if (!$next || $next > $cutoff) continue;
        $events[] = [
            'id' => 'birthday:' . (string) $row['contact_id'],
            'date_id' => '',
            'contact_id' => (string) $row['contact_id'],
            'contact_type' => 'contact',
            'contact_name' => (string) $row['display_name'],
            'type' => 'birthday',
            'label' => 'Birthday',
            'event_date' => $next->format('Y-m-d'),
            'days_until' => (int) $today->diff($next)->format('%a'),
            'repeats_annually' => true,
            'reminder_days_before' => 14,
            'relationship' => trim((string) ($row['relationship_label'] ?: $row['relationship_type'] ?? '')),
        ];
    }

    $dates = $pdo->prepare("SELECT d.public_id date_id,d.date_type,d.label,d.event_date,d.repeats_annually,d.reminder_days_before,
        c.public_id contact_id,c.display_name,c.relationship_type,c.relationship_label
        FROM user_contact_dates d INNER JOIN user_contacts c ON c.id=d.user_contact_id AND c.owner_user_id=d.owner_user_id
        WHERE d.owner_user_id=? AND c.archived_at IS NULL");
    $dates->execute([$userId]);
    foreach ($dates->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $next = mg_personal_agent_next_occurrence((string) $row['event_date'], (bool) $row['repeats_annually'], $today);
        if (!$next || $next < $today || $next > $cutoff) continue;
        $events[] = [
            'id' => (string) $row['date_id'],
            'date_id' => (string) $row['date_id'],
            'contact_id' => (string) $row['contact_id'],
            'contact_type' => 'contact',
            'contact_name' => (string) $row['display_name'],
            'type' => (string) $row['date_type'],
            'label' => (string) $row['label'],
            'event_date' => $next->format('Y-m-d'),
            'days_until' => (int) $today->diff($next)->format('%a'),
            'repeats_annually' => (bool) $row['repeats_annually'],
            'reminder_days_before' => (int) $row['reminder_days_before'],
            'relationship' => trim((string) ($row['relationship_label'] ?: $row['relationship_type'] ?? '')),
        ];
    }

    usort($events, static fn(array $a, array $b): int => [$a['event_date'],$a['contact_name'],$a['label']] <=> [$b['event_date'],$b['contact_name'],$b['label']]);
    return array_slice($events, 0, max(1, min(250, $limit)));
}

function mg_personal_agent_contacts(PDO $pdo, int $userId, int $limit = 250): array
{
    $private = $pdo->prepare("SELECT c.public_id,c.display_name,c.nickname,c.relationship_type,c.relationship_label,c.birthdate,c.interests,c.gift_preferences,
        c.budget_min,c.budget_max,c.city,c.state_region,c.phone_last4,
        GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') list_names
        FROM user_contacts c
        LEFT JOIN user_contact_list_members m ON m.user_contact_id=c.id AND m.owner_user_id=c.owner_user_id
        LEFT JOIN user_contact_lists l ON l.id=m.list_id AND l.owner_user_id=c.owner_user_id AND l.is_archived=0
        WHERE c.owner_user_id=? AND c.archived_at IS NULL
        GROUP BY c.id ORDER BY c.display_name LIMIT " . max(1, min(500, $limit)));
    $private->execute([$userId]);
    $contacts = [];
    foreach ($private->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $contacts[] = [
            'id' => (string) $row['public_id'],
            'type' => 'contact',
            'display_name' => (string) $row['display_name'],
            'nickname' => (string) ($row['nickname'] ?? ''),
            'relationship' => trim((string) ($row['relationship_label'] ?: $row['relationship_type'] ?? '')),
            'birthdate' => $row['birthdate'] ?: null,
            'interests' => (string) ($row['interests'] ?? ''),
            'gift_preferences' => (string) ($row['gift_preferences'] ?? ''),
            'budget_min' => $row['budget_min'] !== null ? (float) $row['budget_min'] : null,
            'budget_max' => $row['budget_max'] !== null ? (float) $row['budget_max'] : null,
            'location' => trim(implode(', ', array_filter([(string) ($row['city'] ?? ''),(string) ($row['state_region'] ?? '')]))),
            'phone_masked' => mg_contact_phone_mask($row['phone_last4'] ?? null),
            'list_names' => (string) ($row['list_names'] ?? ''),
            'avatar_url' => '',
            'profile_url' => '',
        ];
    }

    $linked = $pdo->prepare("SELECT u.public_id,COALESCE(pp.display_name,u.display_name,u.full_name,'Contact') display_name,pp.avatar_url,pp.slug,
        MAX(m.relationship_type) relationship_type,MAX(m.relationship_label) relationship_label,
        GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') list_names
        FROM user_contact_list_members m
        INNER JOIN users u ON u.id=m.contact_user_id AND u.status='active'
        LEFT JOIN public_profiles pp ON pp.user_id=u.id AND pp.status='active'
        INNER JOIN user_contact_lists l ON l.id=m.list_id AND l.owner_user_id=m.owner_user_id AND l.is_archived=0
        WHERE m.owner_user_id=? AND m.contact_user_id IS NOT NULL
        GROUP BY u.id ORDER BY display_name LIMIT " . max(1, min(500, $limit)));
    $linked->execute([$userId]);
    foreach ($linked->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $contacts[] = [
            'id' => (string) $row['public_id'],
            'type' => 'linked_user',
            'display_name' => (string) $row['display_name'],
            'nickname' => '',
            'relationship' => trim((string) ($row['relationship_label'] ?: $row['relationship_type'] ?? '')),
            'birthdate' => null,
            'interests' => '',
            'gift_preferences' => '',
            'budget_min' => null,
            'budget_max' => null,
            'location' => '',
            'phone_masked' => '',
            'list_names' => (string) ($row['list_names'] ?? ''),
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'profile_url' => !empty($row['slug']) ? '/profile.php?u=' . rawurlencode((string) $row['slug']) : '',
        ];
    }
    usort($contacts, static fn(array $a, array $b): int => strcasecmp($a['display_name'], $b['display_name']));
    return array_slice($contacts, 0, max(1, min(500, $limit)));
}

function mg_personal_agent_plans(PDO $pdo, int $userId, string $status = 'all', int $limit = 100): array
{
    mg_personal_agent_require_schema($pdo);
    $allowed = ['all','draft','planned','ready','completed','cancelled'];
    if (!in_array($status, $allowed, true)) $status = 'all';
    $where = $status === 'all' ? '' : ' AND p.status=?';
    $params = [$userId];
    if ($status !== 'all') $params[] = $status;
    $stmt = $pdo->prepare("SELECT p.id internal_id,p.public_id,p.title,p.occasion_type,p.occasion_label,p.target_date,p.budget_min,p.budget_max,p.currency,p.status,
        p.notes,p.recommendation_json,p.source,p.approval_required,p.created_at,p.updated_at,
        l.public_id list_public_id,l.name list_name,c.public_id contact_public_id,c.display_name contact_name,
        u.public_id linked_user_public_id,COALESCE(pp.display_name,u.display_name,u.full_name) linked_user_name
        FROM user_gifting_plans p
        LEFT JOIN user_contact_lists l ON l.id=p.list_id AND l.owner_user_id=p.owner_user_id
        LEFT JOIN user_contacts c ON c.id=p.user_contact_id AND c.owner_user_id=p.owner_user_id
        LEFT JOIN users u ON u.id=p.contact_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=p.contact_user_id
        WHERE p.owner_user_id=?{$where}
        ORDER BY FIELD(p.status,'ready','planned','draft','completed','cancelled'),p.target_date IS NULL,p.target_date,p.updated_at DESC
        LIMIT " . max(1, min(250, $limit)));
    $stmt->execute($params);
    return array_map(static function(array $row): array {
        $type = 'none';
        $id = '';
        $name = '';
        if (!empty($row['contact_public_id'])) { $type='contact'; $id=(string)$row['contact_public_id']; $name=(string)$row['contact_name']; }
        elseif (!empty($row['linked_user_public_id'])) { $type='linked_user'; $id=(string)$row['linked_user_public_id']; $name=(string)$row['linked_user_name']; }
        elseif (!empty($row['list_public_id'])) { $type='list'; $id=(string)$row['list_public_id']; $name=(string)$row['list_name']; }
        return [
            'id'=>(string)$row['public_id'],
            'title'=>(string)$row['title'],
            'occasion_type'=>(string)$row['occasion_type'],
            'occasion_label'=>(string)($row['occasion_label'] ?? ''),
            'target_date'=>$row['target_date'] ?: null,
            'budget_min'=>$row['budget_min'] !== null ? (float)$row['budget_min'] : null,
            'budget_max'=>$row['budget_max'] !== null ? (float)$row['budget_max'] : null,
            'currency'=>(string)$row['currency'],
            'status'=>(string)$row['status'],
            'notes'=>(string)($row['notes'] ?? ''),
            'recommendation'=>mg_personal_agent_json($row['recommendation_json'] ?? null),
            'source'=>(string)$row['source'],
            'approval_required'=>(bool)$row['approval_required'],
            'context'=>['type'=>$type,'id'=>$id,'name'=>$name],
            'created_at'=>$row['created_at'],
            'updated_at'=>$row['updated_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_agent_reminders(PDO $pdo, int $userId, string $status = 'scheduled', int $limit = 100): array
{
    mg_personal_agent_require_schema($pdo);
    $allowed = ['all','scheduled','completed','dismissed','cancelled'];
    if (!in_array($status, $allowed, true)) $status = 'scheduled';
    $where = $status === 'all' ? '' : ' AND r.status=?';
    $params = [$userId];
    if ($status !== 'all') $params[] = $status;
    $stmt = $pdo->prepare("SELECT r.public_id,r.reminder_type,r.title,r.remind_at,r.status,r.notes,r.created_at,r.updated_at,
        p.public_id plan_public_id,p.title plan_title,l.public_id list_public_id,l.name list_name,
        c.public_id contact_public_id,c.display_name contact_name,u.public_id linked_user_public_id,
        COALESCE(pp.display_name,u.display_name,u.full_name) linked_user_name
        FROM user_gifting_reminders r
        LEFT JOIN user_gifting_plans p ON p.id=r.plan_id AND p.owner_user_id=r.owner_user_id
        LEFT JOIN user_contact_lists l ON l.id=r.list_id AND l.owner_user_id=r.owner_user_id
        LEFT JOIN user_contacts c ON c.id=r.user_contact_id AND c.owner_user_id=r.owner_user_id
        LEFT JOIN users u ON u.id=r.contact_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=r.contact_user_id
        WHERE r.owner_user_id=?{$where}
        ORDER BY r.remind_at,r.id LIMIT " . max(1, min(250, $limit)));
    $stmt->execute($params);
    return array_map(static function(array $row): array {
        $context=['type'=>'none','id'=>'','name'=>''];
        if (!empty($row['plan_public_id'])) $context=['type'=>'plan','id'=>(string)$row['plan_public_id'],'name'=>(string)$row['plan_title']];
        elseif (!empty($row['contact_public_id'])) $context=['type'=>'contact','id'=>(string)$row['contact_public_id'],'name'=>(string)$row['contact_name']];
        elseif (!empty($row['linked_user_public_id'])) $context=['type'=>'linked_user','id'=>(string)$row['linked_user_public_id'],'name'=>(string)$row['linked_user_name']];
        elseif (!empty($row['list_public_id'])) $context=['type'=>'list','id'=>(string)$row['list_public_id'],'name'=>(string)$row['list_name']];
        return [
            'id'=>(string)$row['public_id'],
            'type'=>(string)$row['reminder_type'],
            'title'=>(string)$row['title'],
            'remind_at'=>(string)$row['remind_at'],
            'status'=>(string)$row['status'],
            'notes'=>(string)($row['notes'] ?? ''),
            'context'=>$context,
            'created_at'=>$row['created_at'],
            'updated_at'=>$row['updated_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_agent_memory(PDO $pdo, int $userId, bool $includeArchived = false): array
{
    mg_personal_agent_require_schema($pdo);
    $stmt = $pdo->prepare("SELECT public_id,memory_key,category,title,value_json,source,confidence,status,created_at,updated_at
        FROM user_agent_memory WHERE owner_user_id=?" . ($includeArchived ? '' : " AND status='active'") . "
        ORDER BY status,category,title");
    $stmt->execute([$userId]);
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],
        'key'=>(string)$row['memory_key'],
        'category'=>(string)$row['category'],
        'title'=>(string)$row['title'],
        'value'=>mg_personal_agent_json($row['value_json'] ?? null),
        'source'=>(string)$row['source'],
        'confidence'=>(float)$row['confidence'],
        'status'=>(string)$row['status'],
        'created_at'=>$row['created_at'],
        'updated_at'=>$row['updated_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_agent_opportunities(array $events, array $settings): array
{
    if (empty($settings['enable_suggestions'])) return [];
    $out = [];
    foreach ($events as $event) {
        $days = (int) ($event['days_until'] ?? 0);
        $timing = $days <= 3 ? 'Act now' : ($days <= 14 ? 'Plan this week' : 'Start a draft');
        $budgetMin = $settings['default_budget_min'];
        $budgetMax = $settings['default_budget_max'];
        $out[] = [
            'id' => 'opportunity:' . $event['id'],
            'title' => $event['contact_name'] . ' · ' . $event['label'],
            'body' => $event['label'] . ' is in ' . $days . ' day' . ($days === 1 ? '' : 's') . '.',
            'timing' => $timing,
            'event_date' => $event['event_date'],
            'context' => ['type'=>'contact','id'=>$event['contact_id'],'name'=>$event['contact_name']],
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'currency' => $settings['default_currency'],
            'prompt' => 'Help me plan a ' . mb_strtolower((string) $event['label']) . ' gift for ' . $event['contact_name'] . '.',
        ];
        if (count($out) >= 8) break;
    }
    return $out;
}

function mg_personal_agent_dashboard(PDO $pdo, int $userId): array
{
    mg_personal_agent_require_schema($pdo);
    $settings = mg_personal_agent_settings($pdo, $userId);
    $horizon = (int) $settings['suggestion_horizon_days'];
    $events = mg_personal_agent_upcoming_dates($pdo, $userId, max($horizon, 90), 100);
    $contacts = mg_personal_agent_contacts($pdo, $userId, 300);
    $lists = mg_user_contact_lists($pdo, $userId, false);
    $plans = mg_personal_agent_plans($pdo, $userId, 'all', 100);
    $reminders = mg_personal_agent_reminders($pdo, $userId, 'scheduled', 100);
    $memory = mg_personal_agent_memory($pdo, $userId, false);
    $statusCounts = ['draft'=>0,'planned'=>0,'ready'=>0,'completed'=>0,'cancelled'=>0];
    foreach ($plans as $plan) if (isset($statusCounts[$plan['status']])) $statusCounts[$plan['status']]++;
    $dueReminders = array_values(array_filter($reminders, static fn(array $row): bool => strtotime((string)$row['remind_at']) <= time()));
    return [
        'settings'=>$settings,
        'models'=>mg_personal_agent_available_models($pdo),
        'summary'=>[
            'lists'=>count($lists),
            'contacts'=>count($contacts),
            'upcoming_dates'=>count(array_filter($events, static fn(array $event): bool => (int)$event['days_until'] <= $horizon)),
            'draft_plans'=>$statusCounts['draft'] + $statusCounts['planned'] + $statusCounts['ready'],
            'due_reminders'=>count($dueReminders),
            'memory_items'=>count($memory),
        ],
        'lists'=>$lists,
        'contacts'=>$contacts,
        'upcoming_dates'=>$events,
        'opportunities'=>mg_personal_agent_opportunities($events, $settings),
        'plans'=>$plans,
        'reminders'=>$reminders,
        'memory'=>$memory,
    ];
}

