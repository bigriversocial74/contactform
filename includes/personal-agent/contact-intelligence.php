<?php
declare(strict_types=1);

function mg_personal_agent_contact_intelligence_schema_ready(PDO $pdo): bool
{
    foreach (['user_agent_action_drafts','user_agent_action_receipts','user_agent_relationship_signals'] as $table) {
        if (!mg_personal_agent_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_personal_agent_contact_intelligence_require_schema(PDO $pdo): void
{
    if (!mg_personal_agent_contact_intelligence_schema_ready($pdo)) {
        throw new RuntimeException('Personal Agent Contact Intelligence v1 database migration is required.');
    }
}

function mg_personal_agent_contact_actions_allowed(): bool
{
    return mg_has_permission('agent.personal.contact_actions')
        || mg_has_permission('agent.personal.use')
        || mg_has_permission('admin.access');
}

function mg_personal_agent_intelligence_normalize(string $value): string
{
    $value = mb_strtolower(trim($value));
    return preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';
}

function mg_personal_agent_intelligence_parse_date(string $value, bool $birthday = false): ?string
{
    $value = trim($value, " \t\n\r\0\x0B.,");
    if ($value === '') return null;
    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $value, $match) === 1) {
        try { return mg_personal_agent_date($match[1]); } catch (Throwable) { return null; }
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) return null;
    $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
    if ($birthday && preg_match('/\b\d{4}\b/', $value) !== 1) {
        $date = $date->setDate(2000, (int)$date->format('m'), (int)$date->format('d'));
    }
    return $date->format('Y-m-d');
}

function mg_personal_agent_intelligence_contact(PDO $pdo, int $userId, string $name): ?array
{
    $needle = mg_personal_agent_intelligence_normalize($name);
    if ($needle === '') return null;
    $contacts = mg_personal_agent_contacts($pdo, $userId, 500);
    $partial = [];
    foreach ($contacts as $contact) {
        $candidate = mg_personal_agent_intelligence_normalize((string)($contact['display_name'] ?? ''));
        $nickname = mg_personal_agent_intelligence_normalize((string)($contact['nickname'] ?? ''));
        if ($candidate === $needle || ($nickname !== '' && $nickname === $needle)) return $contact;
        if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) $partial[] = $contact;
    }
    return count($partial) === 1 ? $partial[0] : null;
}

function mg_personal_agent_intelligence_list(PDO $pdo, int $userId, string $name): ?array
{
    $needle = mg_personal_agent_intelligence_normalize(preg_replace('/\blist\b/i', '', $name) ?? $name);
    if ($needle === '') return null;
    $partial = [];
    foreach (mg_user_contact_lists($pdo, $userId, false) as $list) {
        $candidate = mg_personal_agent_intelligence_normalize((string)($list['name'] ?? ''));
        if ($candidate === $needle) return $list;
        if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) $partial[] = $list;
    }
    return count($partial) === 1 ? $partial[0] : null;
}

function mg_personal_agent_contact_intent(string $message): array
{
    $message = mg_personal_agent_text($message, 2000);
    $lower = mb_strtolower($message);
    if ($message === '') return ['key'=>''];

    if (preg_match('/\bhow many contacts?\b|\bcontact count\b/', $lower) === 1) return ['key'=>'contact_count'];
    if (preg_match('/\bhow many lists?\b|\blist count\b/', $lower) === 1) return ['key'=>'list_count'];
    if (preg_match('/\b(which|what) contacts?.*(missing|without).*(birthday|birth date)|\bmissing birthdays?\b/', $lower) === 1) return ['key'=>'missing_birthdays'];
    if (preg_match('/\b(birthday|birthdays).*(next month|upcoming|soon)|\bwho has a birthday\b/', $lower) === 1) return ['key'=>'upcoming_birthdays'];
    if (preg_match('/\b(signals?|predict|prediction|what should i plan|who should i plan|next gifting opportunity)\b/', $lower) === 1) return ['key'=>'signals'];
    if (preg_match('/\b(show|list|view) (all |my )?contacts?\b/', $lower) === 1) return ['key'=>'show_contacts'];

    if (preg_match('/\b(?:create|make|start) (?:a |new )?(?:contact )?list(?: called| named)?\s+(.+)$/i', $message, $match) === 1) {
        return ['key'=>'create_list','name'=>trim($match[1])];
    }
    if (preg_match('/\b(?:create|add|make) (?:a |new )?contact(?: called| named| for)?\s+(.+)$/i', $message, $match) === 1) {
        $tail = trim($match[1]);
        $birthday = null;
        if (preg_match('/\s+(?:whose |with )?birthday(?: is| on)?\s+(.+)$/i', $tail, $dateMatch) === 1) {
            $birthday = mg_personal_agent_intelligence_parse_date($dateMatch[1], true);
            $tail = trim(substr($tail, 0, -strlen($dateMatch[0])));
        }
        return ['key'=>'create_contact','name'=>$tail,'birthdate'=>$birthday];
    }
    if (preg_match('/\badd\s+(.+?)\s+to\s+(?:my\s+)?(.+?)\s+list\b/i', $message, $match) === 1) {
        return ['key'=>'add_to_list','contact_name'=>trim($match[1]),'list_name'=>trim($match[2])];
    }
    if (preg_match('/\b(?:set|add|update)\s+(.+?)(?:\'s|s)?\s+birthday(?:\s+to|\s+is|\s+on)?\s+(.+)$/i', $message, $match) === 1) {
        return ['key'=>'set_birthday','contact_name'=>trim($match[1]),'birthdate'=>mg_personal_agent_intelligence_parse_date($match[2], true)];
    }
    if (preg_match('/\b(?:add|save)\s+(?:an?\s+)?(?:important\s+)?date\s+for\s+(.+?)\s+(?:called|named)\s+(.+?)\s+on\s+(.+)$/i', $message, $match) === 1) {
        return ['key'=>'create_date','contact_name'=>trim($match[1]),'label'=>trim($match[2]),'event_date'=>mg_personal_agent_intelligence_parse_date($match[3])];
    }
    if (preg_match('/\b(?:schedule|create)\s+(?:a\s+)?(?:gifting\s+)?reminder\s+for\s+(.+?)\s+on\s+(.+)$/i', $message, $match) === 1) {
        return ['key'=>'create_reminder','contact_name'=>trim($match[1]),'remind_at'=>trim($match[2])];
    }
    if (preg_match('/\b(show|open|view)\s+(?:my\s+)?(.+?)\s+list\b/i', $message, $match) === 1) {
        return ['key'=>'show_list','list_name'=>trim($match[2])];
    }
    if (preg_match('/\b(last|recent|previous) gift.*(?:for|to)\s+(.+)$/i', $message, $match) === 1) {
        return ['key'=>'gift_history','contact_name'=>trim($match[2])];
    }
    return ['key'=>''];
}

function mg_personal_agent_contact_intelligence_start(PDO $pdo, int $userId, array $input, string $intent): array
{
    mg_personal_agent_require_schema($pdo);
    $message = mg_personal_agent_text($input['message'] ?? '', 2000);
    $context = mg_personal_agent_resolve_context($pdo,$userId,(string)($input['context_type'] ?? 'none'),(string)($input['context_id'] ?? ''));
    $thread = mg_personal_agent_thread($pdo,$userId,mg_personal_agent_text($input['thread_id'] ?? '',80),$context);
    $publicContext = mg_personal_agent_public_context($context);
    $userMessage = mg_personal_agent_store_message($pdo,$userId,(int)$thread['internal_id'],'user',$message,[],$publicContext);
    $assistant = mg_personal_agent_store_message($pdo,$userId,(int)$thread['internal_id'],'assistant','Reviewing your private contact and occasion data…',[],array_merge($publicContext,['contact_intent'=>$intent]));
    return [
        'thread'=>['id'=>$thread['id'],'title'=>$thread['title'],'internal_id'=>(int)$thread['internal_id']],
        'user_message'=>$userMessage,
        'assistant_message'=>$assistant,
        'context'=>$publicContext,
        'used_ai'=>false,
        'model_key'=>'deterministic_contact_intelligence',
    ];
}

function mg_personal_agent_contact_intelligence_persist(PDO $pdo, int $userId, array &$result, string $body, array $cards, string $intent): void
{
    $messageId = (string)($result['assistant_message']['id'] ?? '');
    $result['assistant_message']['body'] = $body;
    $result['assistant_message']['cards'] = $cards;
    $result['contact_intent'] = $intent;
    if ($messageId === '') return;
    $pdo->prepare("UPDATE user_agent_messages SET body=?,cards_json=?,context_json=JSON_SET(COALESCE(context_json,JSON_OBJECT()),'$.contact_intent',?) WHERE owner_user_id=? AND public_id=? AND role='assistant'")
        ->execute([$body,$cards !== [] ? mg_personal_agent_json_encode($cards) : null,$intent,$userId,$messageId]);
}

function mg_personal_agent_contact_action_draft(PDO $pdo, int $userId, int $threadId, string $actionType, array $payload, array $preview): array
{
    mg_personal_agent_contact_intelligence_require_schema($pdo);
    $idempotency = hash('sha256',$userId . '|' . $actionType . '|' . mg_personal_agent_json_encode($payload));
    $existing = $pdo->prepare("SELECT * FROM user_agent_action_drafts WHERE owner_user_id=? AND idempotency_key=? AND status='pending' AND expires_at>NOW() LIMIT 1");
    $existing->execute([$userId,$idempotency]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $publicId = mg_public_uuid();
        $pdo->prepare("INSERT INTO user_agent_action_drafts (public_id,owner_user_id,thread_id,action_type,payload_json,preview_json,idempotency_key,status,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'pending',DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW(),NOW())")
            ->execute([$publicId,$userId,$threadId ?: null,$actionType,mg_personal_agent_json_encode($payload),mg_personal_agent_json_encode($preview),$idempotency]);
        $stmt = $pdo->prepare('SELECT * FROM user_agent_action_drafts WHERE owner_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$userId,$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) throw new RuntimeException('Unable to prepare the Personal Agent action.');
    return [
        'id'=>(string)$row['public_id'],
        'action_type'=>(string)$row['action_type'],
        'payload'=>mg_personal_agent_json($row['payload_json']),
        'preview'=>mg_personal_agent_json($row['preview_json']),
        'status'=>(string)$row['status'],
        'expires_at'=>(string)$row['expires_at'],
    ];
}

function mg_personal_agent_contact_action_card(array $draft): array
{
    $preview = is_array($draft['preview'] ?? null) ? $draft['preview'] : [];
    return [
        'type'=>'contact_action_review',
        'title'=>(string)($preview['title'] ?? 'Review account action'),
        'body'=>(string)($preview['body'] ?? 'Review the details before saving this change.'),
        'reason'=>'The Personal Agent prepares the action first. Nothing is written until you confirm.',
        'timing'=>'Draft expires in 24 hours.',
        'warning'=>'Confirm the names, dates, list, and reminder timing before saving.',
        'action'=>'confirm_contact_action',
        'action_label'=>(string)($preview['confirm_label'] ?? 'Confirm and save'),
        'risk_level'=>'medium',
        'action_draft_id'=>(string)$draft['id'],
        'action_type'=>(string)$draft['action_type'],
        'fields'=>is_array($preview['fields'] ?? null) ? $preview['fields'] : [],
        'cancel_label'=>'Cancel',
    ];
}

function mg_personal_agent_contact_intelligence_prepare(PDO $pdo, int $userId, int $threadId, array $intent): array
{
    if (!mg_personal_agent_contact_actions_allowed()) throw new RuntimeException('Your account does not have permission to use Personal Agent contact actions.');
    $key = (string)($intent['key'] ?? '');
    if ($key === 'create_list') {
        $name = mg_contact_text($intent['name'] ?? '',160);
        if ($name === '') throw new InvalidArgumentException('Tell me what to name the list.');
        $payload = ['name'=>$name,'list_type'=>'custom','icon_key'=>'people','description'=>'Created with the Personal Gifting Agent.'];
        return mg_personal_agent_contact_action_draft($pdo,$userId,$threadId,'create_list',$payload,[
            'title'=>'Create “'.$name.'” list','body'=>'Create a private gifting list in My Lists.','confirm_label'=>'Create list','fields'=>[['label'=>'List name','value'=>$name],['label'=>'Type','value'=>'Custom']],
        ]);
    }
    if ($key === 'create_contact') {
        $name = mg_contact_text($intent['name'] ?? '',180);
        if ($name === '') throw new InvalidArgumentException('Tell me the contact name.');
        $payload = ['display_name'=>$name,'birthdate'=>$intent['birthdate'] ?? null];
        $fields = [['label'=>'Contact','value'=>$name]];
        if (!empty($payload['birthdate'])) $fields[] = ['label'=>'Birthday','value'=>(string)$payload['birthdate']];
        return mg_personal_agent_contact_action_draft($pdo,$userId,$threadId,'create_contact',$payload,[
            'title'=>'Create contact','body'=>'Add '.$name.' as a private contact.','confirm_label'=>'Create contact','fields'=>$fields,
        ]);
    }
    if ($key === 'add_to_list') {
        $list = mg_personal_agent_intelligence_list($pdo,$userId,(string)$intent['list_name']);
        if (!$list) throw new RuntimeException('I could not find that list. Use its exact name or create it first.');
        $contact = mg_personal_agent_intelligence_contact($pdo,$userId,(string)$intent['contact_name']);
        $payload = ['list_id'=>$list['id'],'list_name'=>$list['name']];
        $actionType = 'add_contact_to_list';
        $contactName = mg_contact_text($intent['contact_name'] ?? '',180);
        if ($contact) {
            $payload['contact_id'] = $contact['id'];
            $payload['contact_type'] = $contact['type'] === 'linked_user' ? 'linked_user' : 'private_contact';
            $contactName = (string)$contact['display_name'];
        } else {
            $actionType = 'create_contact_and_add_to_list';
            $payload['display_name'] = $contactName;
        }
        if ($contactName === '') throw new InvalidArgumentException('Tell me which contact to add.');
        return mg_personal_agent_contact_action_draft($pdo,$userId,$threadId,$actionType,$payload,[
            'title'=>'Add contact to list','body'=>'Add '.$contactName.' to '.$list['name'].'.','confirm_label'=>$actionType === 'create_contact_and_add_to_list' ? 'Create and add' : 'Add to list','fields'=>[['label'=>'Contact','value'=>$contactName],['label'=>'List','value'=>$list['name']]],
        ]);
    }
    if ($key === 'set_birthday') {
        $contact = mg_personal_agent_intelligence_contact($pdo,$userId,(string)$intent['contact_name']);
        if (!$contact || $contact['type'] !== 'contact') throw new RuntimeException('I could not find that private contact.');
        $birthdate = (string)($intent['birthdate'] ?? '');
        if ($birthdate === '') throw new InvalidArgumentException('Tell me the birthday date.');
        return mg_personal_agent_contact_action_draft($pdo,$userId,$threadId,'set_birthday',['contact_id'=>$contact['id'],'birthdate'=>$birthdate],[
            'title'=>'Set birthday','body'=>'Save a birthday for '.$contact['display_name'].'.','confirm_label'=>'Save birthday','fields'=>[['label'=>'Contact','value'=>$contact['display_name']],['label'=>'Birthday','value'=>$birthdate]],
        ]);
    }
    if ($key === 'create_date') {
        $contact = mg_personal_agent_intelligence_contact($pdo,$userId,(string)$intent['contact_name']);
        if (!$contact || $contact['type'] !== 'contact') throw new RuntimeException('I could not find that private contact.');
        if (empty($intent['event_date'])) throw new InvalidArgumentException('Tell me the date to save.');
        $payload = ['contact_id'=>$contact['id'],'label'=>mg_contact_text($intent['label'] ?? 'Important date',160),'event_date'=>$intent['event_date'],'date_type'=>'important_date','repeats_annually'=>true,'reminder_days_before'=>14];
        return mg_personal_agent_contact_action_draft($pdo,$userId,$threadId,'create_date',$payload,[
            'title'=>'Add important date','body'=>'Save '.$payload['label'].' for '.$contact['display_name'].'.','confirm_label'=>'Save date','fields'=>[['label'=>'Contact','value'=>$contact['display_name']],['label'=>'Occasion','value'=>$payload['label']],['label'=>'Date','value'=>$payload['event_date']]],
        ]);
    }
    if ($key === 'create_reminder') {
        $contact = mg_personal_agent_intelligence_contact($pdo,$userId,(string)$intent['contact_name']);
        if (!$contact) throw new RuntimeException('I could not find that contact.');
        try { $remindAt = mg_personal_agent_datetime((string)$intent['remind_at']); } catch (Throwable) { throw new InvalidArgumentException('Tell me a valid reminder date and time.'); }
        $payload = ['context_type'=>$contact['type'],'context_id'=>$contact['id'],'title'=>'Plan a gift for '.$contact['display_name'],'remind_at'=>$remindAt,'reminder_type'=>'gift_planning','notes'=>'Created with the Personal Gifting Agent.'];
        return mg_personal_agent_contact_action_draft($pdo,$userId,$threadId,'create_reminder',$payload,[
            'title'=>'Schedule gifting reminder','body'=>'Remind you to plan a gift for '.$contact['display_name'].'.','confirm_label'=>'Schedule reminder','fields'=>[['label'=>'Contact','value'=>$contact['display_name']],['label'=>'Reminder','value'=>$remindAt.' UTC']],
        ]);
    }
    throw new InvalidArgumentException('That Personal Agent action is not supported yet.');
}

function mg_personal_agent_contact_signal_upsert(PDO $pdo, int $userId, ?int $contactId, array $signal): void
{
    $pdo->prepare("INSERT INTO user_agent_relationship_signals (public_id,owner_user_id,user_contact_id,signal_key,signal_type,title,summary,score,confidence,event_date,status,evidence_json,first_seen_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?, 'active',?,NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE user_contact_id=VALUES(user_contact_id),signal_type=VALUES(signal_type),title=VALUES(title),summary=VALUES(summary),score=VALUES(score),confidence=VALUES(confidence),event_date=VALUES(event_date),status='active',evidence_json=VALUES(evidence_json),last_seen_at=NOW(),resolved_at=NULL,updated_at=NOW()")
        ->execute([mg_public_uuid(),$userId,$contactId,$signal['key'],$signal['type'],$signal['title'],$signal['summary'],$signal['score'],$signal['confidence'],$signal['event_date'] ?? null,mg_personal_agent_json_encode($signal['evidence'] ?? [])]);
}

function mg_personal_agent_contact_signals(PDO $pdo, int $userId, bool $refresh = true): array
{
    if (!mg_personal_agent_contact_intelligence_schema_ready($pdo)) return [];
    if ($refresh) {
        $keys = [];
        $internal = [];
        $stmt = $pdo->prepare('SELECT id,public_id,display_name,birthdate FROM user_contacts WHERE owner_user_id=? AND archived_at IS NULL');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $internal[(string)$row['public_id']] = $row;
        foreach (mg_personal_agent_upcoming_dates($pdo,$userId,90,120) as $event) {
            $days = max(0,(int)$event['days_until']);
            $key = 'occasion:' . (string)$event['id'];
            $keys[] = $key;
            mg_personal_agent_contact_signal_upsert($pdo,$userId,isset($internal[$event['contact_id']]) ? (int)$internal[$event['contact_id']]['id'] : null,[
                'key'=>$key,'type'=>'upcoming_occasion','title'=>$event['contact_name'].' · '.$event['label'],'summary'=>$event['label'].' is in '.$days.' day'.($days===1?'':'s').'.','score'=>max(20,100-$days),'confidence'=>0.95,'event_date'=>$event['event_date'],'evidence'=>['days_until'=>$days,'contact_id'=>$event['contact_id'],'label'=>$event['label']],
            ]);
        }
        foreach ($internal as $publicId=>$row) {
            if (!empty($row['birthdate'])) continue;
            $key = 'missing_birthday:' . $publicId;
            $keys[] = $key;
            mg_personal_agent_contact_signal_upsert($pdo,$userId,(int)$row['id'],[
                'key'=>$key,'type'=>'missing_birthday','title'=>'Birthday missing for '.$row['display_name'],'summary'=>'Adding a birthday enables advance planning and reminders.','score'=>25,'confidence'=>1.0,'event_date'=>null,'evidence'=>['contact_id'=>$publicId],
            ]);
        }
        $active = $pdo->prepare("SELECT signal_key FROM user_agent_relationship_signals WHERE owner_user_id=? AND status='active'");
        $active->execute([$userId]);
        $stale = array_diff(array_map('strval',$active->fetchAll(PDO::FETCH_COLUMN) ?: []),$keys);
        if ($stale !== []) {
            $placeholders = implode(',',array_fill(0,count($stale),'?'));
            $pdo->prepare("UPDATE user_agent_relationship_signals SET status='resolved',resolved_at=NOW(),updated_at=NOW() WHERE owner_user_id=? AND signal_key IN ({$placeholders})")
                ->execute(array_merge([$userId],array_values($stale)));
        }
    }
    $stmt = $pdo->prepare("SELECT s.public_id,s.signal_type,s.title,s.summary,s.score,s.confidence,s.event_date,s.status,s.evidence_json,s.last_seen_at,c.public_id contact_id,c.display_name contact_name FROM user_agent_relationship_signals s LEFT JOIN user_contacts c ON c.id=s.user_contact_id AND c.owner_user_id=s.owner_user_id WHERE s.owner_user_id=? AND s.status='active' ORDER BY s.score DESC,s.event_date IS NULL,s.event_date,s.last_seen_at DESC LIMIT 30");
    $stmt->execute([$userId]);
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'type'=>(string)$row['signal_type'],'title'=>(string)$row['title'],'summary'=>(string)$row['summary'],'score'=>(float)$row['score'],'confidence'=>(float)$row['confidence'],'event_date'=>$row['event_date'] ?: null,'contact_id'=>(string)($row['contact_id'] ?? ''),'contact_name'=>(string)($row['contact_name'] ?? ''),'evidence'=>mg_personal_agent_json($row['evidence_json'] ?? null),'last_seen_at'=>$row['last_seen_at'],
    ],$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_personal_agent_contact_read_response(PDO $pdo, int $userId, array $intent): array
{
    $key = (string)$intent['key'];
    $dashboard = mg_personal_agent_dashboard($pdo,$userId);
    $contacts = $dashboard['contacts'] ?? [];
    $lists = $dashboard['lists'] ?? [];
    if ($key === 'contact_count') return ['body'=>'You have '.count($contacts).' contacts available to your Personal Agent.','cards'=>[]];
    if ($key === 'list_count') return ['body'=>'You have '.count($lists).' active contact list'.(count($lists)===1?'':'s').'.','cards'=>[]];
    if ($key === 'show_contacts') {
        $cards = array_map(static fn(array $contact): array => ['type'=>'contact_result','title'=>$contact['display_name'],'body'=>trim(implode(' · ',array_filter([$contact['relationship'] ?? '',$contact['list_names'] ?? '',$contact['gift_preferences'] ?? $contact['interests'] ?? '']))),'action'=>'open_contact','action_label'=>'Open contacts','url'=>'/agent.php?view=contacts','contact_id'=>$contact['id']],array_slice($contacts,0,8));
        return ['body'=>$cards ? 'Here are the first '.count($cards).' contacts in your private gifting account.' : 'You do not have any contacts yet.','cards'=>$cards];
    }
    if ($key === 'show_list') {
        $list = mg_personal_agent_intelligence_list($pdo,$userId,(string)$intent['list_name']);
        if (!$list) return ['body'=>'I could not find that list. Try the exact list name.','cards'=>[]];
        $loaded = mg_user_contact_list_load($pdo,$userId,(string)$list['id']);
        $members = mg_user_contact_list_members($pdo,$userId,(int)$loaded['id_internal']);
        $cards = array_map(static fn(array $member): array => ['type'=>'contact_result','title'=>$member['display_name'],'body'=>trim(implode(' · ',array_filter([$member['relationship_label'] ?: $member['relationship_type'],$member['gift_preferences'] ?? '']))),'action'=>'open_list','action_label'=>'Open list','url'=>'/list.php?id='.rawurlencode((string)$list['id'])],array_slice($members,0,10));
        return ['body'=>$list['name'].' contains '.count($members).' contact'.(count($members)===1?'':'s').'.','cards'=>$cards];
    }
    if ($key === 'missing_birthdays') {
        $missing = array_values(array_filter($contacts,static fn(array $contact): bool => ($contact['type'] ?? '') === 'contact' && empty($contact['birthdate'])));
        $cards = array_map(static fn(array $contact): array => ['type'=>'contact_result','title'=>$contact['display_name'],'body'=>'No birthday is saved for this contact.','action'=>'open_contact','action_label'=>'Review contact','url'=>'/agent.php?view=contacts','contact_id'=>$contact['id']],array_slice($missing,0,10));
        return ['body'=>$missing ? count($missing).' private contact'.(count($missing)===1?' is':'s are').' missing a birthday.' : 'Every private contact currently has a birthday saved.','cards'=>$cards];
    }
    if ($key === 'upcoming_birthdays') {
        $now = new DateTimeImmutable('today',new DateTimeZone('UTC'));
        $nextMonth = (int)$now->modify('first day of next month')->format('m');
        $birthdays = array_values(array_filter($dashboard['upcoming_dates'] ?? [],static fn(array $event): bool => ($event['type'] ?? '') === 'birthday' && (int)substr((string)$event['event_date'],5,2) === $nextMonth));
        if ($birthdays === []) $birthdays = array_values(array_filter($dashboard['upcoming_dates'] ?? [],static fn(array $event): bool => ($event['type'] ?? '') === 'birthday'));
        $cards = array_map(static fn(array $event): array => ['type'=>'occasion_result','title'=>$event['contact_name'].' · Birthday','body'=>$event['event_date'].' · '.$event['days_until'].' days away','action'=>'open_contact','action_label'=>'Plan for contact','url'=>'/agent.php?view=birthdays','contact_id'=>$event['contact_id']],array_slice($birthdays,0,10));
        return ['body'=>$cards ? 'Here are the upcoming birthdays currently available to your Personal Agent.' : 'No birthdays are available in your current planning horizon.','cards'=>$cards];
    }
    if ($key === 'gift_history') {
        $contact = mg_personal_agent_intelligence_contact($pdo,$userId,(string)$intent['contact_name']);
        if (!$contact) return ['body'=>'I could not find that contact.','cards'=>[]];
        $commerce = mg_personal_agent_commerce_knowledge($pdo,$userId);
        $needle = mg_personal_agent_intelligence_normalize((string)$contact['display_name']);
        $matches = array_values(array_filter($commerce['sent_gifts'] ?? [],static fn(array $gift): bool => mg_personal_agent_intelligence_normalize((string)($gift['recipient_name'] ?? '')) === $needle));
        $cards = array_map(static fn(array $gift): array => ['type'=>'gift_history','title'=>$gift['title'],'body'=>trim(implode(' · ',array_filter([$gift['merchant_name'] ?? '',$gift['status'] ?? '',$gift['sent_at'] ?? '']))),'action'=>'none'],array_slice($matches,0,5));
        return ['body'=>$matches ? 'I found '.count($matches).' sent gift record'.(count($matches)===1?'':'s').' for '.$contact['display_name'].'.' : 'I do not see a sent gift matched to '.$contact['display_name'].' yet.','cards'=>$cards];
    }
    if ($key === 'signals') {
        $signals = mg_personal_agent_contact_signals($pdo,$userId,true);
        $cards = array_map(static fn(array $signal): array => ['type'=>'relationship_signal','title'=>$signal['title'],'body'=>$signal['summary'],'reason'=>'Signal score '.round($signal['score']).' with '.round($signal['confidence']*100).'% confidence.','timing'=>$signal['event_date'] ?: 'Review when convenient','action'=>$signal['contact_id'] !== '' ? 'open_contact' : 'none','action_label'=>'Review contact','url'=>'/agent.php?view='.($signal['type']==='upcoming_occasion'?'birthdays':'contacts'),'contact_id'=>$signal['contact_id']],array_slice($signals,0,8));
        return ['body'=>$cards ? 'I found '.count($signals).' active relationship and occasion signal'.(count($signals)===1?'':'s').'. These are recommendations, not automatic actions.' : 'I do not have enough contact or occasion data to generate a signal yet.','cards'=>$cards];
    }
    return ['body'=>'I could not match that request to your contact intelligence.','cards'=>[]];
}

function mg_personal_agent_chat_with_contact_intelligence(PDO $pdo, int $userId, array $input): array
{
    $intent = mg_personal_agent_contact_intent(mg_personal_agent_text($input['message'] ?? '',2000));
    if (($intent['key'] ?? '') === '' || !mg_personal_agent_contact_intelligence_schema_ready($pdo)) {
        return mg_personal_agent_chat_with_opportunity_attribution($pdo,$userId,$input);
    }
    $result = mg_personal_agent_contact_intelligence_start($pdo,$userId,$input,(string)$intent['key']);
    $actionKeys = ['create_list','create_contact','add_to_list','set_birthday','create_date','create_reminder'];
    try {
        if (in_array($intent['key'],$actionKeys,true)) {
            $draft = mg_personal_agent_contact_intelligence_prepare($pdo,$userId,(int)$result['thread']['internal_id'],$intent);
            $body = 'I prepared this account change for review. Nothing has been saved yet.';
            mg_personal_agent_contact_intelligence_persist($pdo,$userId,$result,$body,[mg_personal_agent_contact_action_card($draft)],(string)$intent['key']);
        } else {
            $response = mg_personal_agent_contact_read_response($pdo,$userId,$intent);
            mg_personal_agent_contact_intelligence_persist($pdo,$userId,$result,(string)$response['body'],$response['cards'] ?? [],(string)$intent['key']);
        }
        mg_audit('user_agent.contact_intelligence_response','user_agent_thread',['thread_id'=>$result['thread']['id'],'intent'=>$intent['key'],'action_draft'=>in_array($intent['key'],$actionKeys,true)],$userId);
    } catch (Throwable $error) {
        mg_personal_agent_contact_intelligence_persist($pdo,$userId,$result,$error->getMessage(),[],(string)$intent['key']);
    }
    unset($result['thread']['internal_id']);
    return $result;
}

function mg_personal_agent_contact_action_receipt(PDO $pdo, int $userId, int $draftId, string $actionType, string $entityType, ?string $entityId, string $summary, array $result): array
{
    $publicId = mg_public_uuid();
    $pdo->prepare('INSERT INTO user_agent_action_receipts (public_id,owner_user_id,action_draft_id,action_type,entity_type,entity_public_id,summary,result_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([$publicId,$userId,$draftId,$actionType,$entityType,$entityId,$summary,$result !== [] ? mg_personal_agent_json_encode($result) : null]);
    return ['id'=>$publicId,'action_type'=>$actionType,'entity_type'=>$entityType,'entity_id'=>$entityId,'summary'=>$summary,'result'=>$result,'created_at'=>gmdate('Y-m-d H:i:s')];
}

function mg_personal_agent_execute_contact_action(PDO $pdo, int $userId, string $draftPublicId, string $decision): array
{
    mg_personal_agent_contact_intelligence_require_schema($pdo);
    if (!mg_personal_agent_contact_actions_allowed()) throw new RuntimeException('Your account does not have permission to confirm this action.');
    if (!in_array($decision,['confirm','cancel'],true)) throw new InvalidArgumentException('Choose confirm or cancel.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM user_agent_action_drafts WHERE owner_user_id=? AND public_id=? FOR UPDATE');
        $stmt->execute([$userId,$draftPublicId]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$draft) throw new RuntimeException('Action draft not found.');
        if ((string)$draft['status'] === 'executed') {
            $pdo->commit();
            return ['draft_id'=>$draftPublicId,'status'=>'executed','result'=>mg_personal_agent_json($draft['result_json'] ?? null)];
        }
        if ((string)$draft['status'] !== 'pending') throw new RuntimeException('This action draft is no longer pending.');
        if (strtotime((string)$draft['expires_at']) <= time()) {
            $pdo->prepare("UPDATE user_agent_action_drafts SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$draft['id']]);
            $pdo->commit();
            throw new RuntimeException('This action draft expired. Ask the Agent to prepare it again.');
        }
        if ($decision === 'cancel') {
            $pdo->prepare("UPDATE user_agent_action_drafts SET status='cancelled',cancelled_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$draft['id']]);
            $pdo->commit();
            mg_audit('user_agent.contact_action_cancelled','user_agent_action_draft',['draft_id'=>$draftPublicId,'action_type'=>$draft['action_type']],$userId);
            return ['draft_id'=>$draftPublicId,'status'=>'cancelled'];
        }
        $pdo->prepare("UPDATE user_agent_action_drafts SET status='confirmed',confirmed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$draft['id']]);
        $payload = mg_personal_agent_json($draft['payload_json']);
        $action = (string)$draft['action_type'];
        $entityType = 'account_action';
        $entityId = null;
        $summary = 'Personal Agent action completed.';
        $result = [];
        if ($action === 'create_list') {
            $result = mg_user_contact_list_create($pdo,$userId,$payload); $entityType='user_contact_list'; $entityId=$result['id']; $summary='Created the “'.$result['name'].'” list.';
        } elseif ($action === 'create_contact') {
            $result = mg_user_contact_create($pdo,$userId,$payload); $entityType='user_contact'; $entityId=$result['id']; $summary='Created contact '.$result['display_name'].'.';
        } elseif ($action === 'create_contact_and_add_to_list') {
            $contact = mg_user_contact_create($pdo,$userId,['display_name'=>$payload['display_name']]);
            $membership = mg_user_contact_add_member($pdo,$userId,(string)$payload['list_id'],'private_contact',(string)$contact['id']);
            $result=['contact'=>$contact,'membership'=>$membership]; $entityType='user_contact'; $entityId=$contact['id']; $summary='Created '.$contact['display_name'].' and added the contact to '.$payload['list_name'].'.';
        } elseif ($action === 'add_contact_to_list') {
            $result = mg_user_contact_add_member($pdo,$userId,(string)$payload['list_id'],(string)$payload['contact_type'],(string)$payload['contact_id']);
            $entityType='user_contact_list_member'; $entityId=$result['membership_id']; $summary='Added the contact to '.$payload['list_name'].'.';
        } elseif ($action === 'set_birthday') {
            $birthdate = mg_personal_agent_date($payload['birthdate'] ?? null);
            if ($birthdate === null) throw new InvalidArgumentException('Birthday is required.');
            $contactId = mg_user_contact_find_private($pdo,$userId,(string)$payload['contact_id']);
            $pdo->prepare('UPDATE user_contacts SET birthdate=?,updated_at=NOW() WHERE id=? AND owner_user_id=?')->execute([$birthdate,$contactId,$userId]);
            $result=['contact_id'=>$payload['contact_id'],'birthdate'=>$birthdate]; $entityType='user_contact'; $entityId=(string)$payload['contact_id']; $summary='Saved the contact birthday.';
            mg_audit('user_contact.birthday_updated','user_contact',['contact_id'=>$entityId],$userId);
        } elseif ($action === 'create_date') {
            $result = mg_personal_agent_create_date($pdo,$userId,$payload); $entityType='user_contact_date'; $entityId=$result['id']; $summary='Saved '.$result['label'].' for '.$result['contact_name'].'.';
        } elseif ($action === 'create_reminder') {
            $result = mg_personal_agent_create_reminder($pdo,$userId,$payload); $entityType='user_gifting_reminder'; $entityId=$result['id']; $summary='Scheduled the gifting reminder.';
        } else {
            throw new RuntimeException('Unsupported Personal Agent action.');
        }
        $receipt = mg_personal_agent_contact_action_receipt($pdo,$userId,(int)$draft['id'],$action,$entityType,$entityId,$summary,$result);
        $pdo->prepare("UPDATE user_agent_action_drafts SET status='executed',executed_at=NOW(),result_json=?,error_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([mg_personal_agent_json_encode(['receipt'=>$receipt,'entity'=>$result]),(int)$draft['id']]);
        $pdo->commit();
        mg_audit('user_agent.contact_action_executed','user_agent_action_receipt',['draft_id'=>$draftPublicId,'receipt_id'=>$receipt['id'],'action_type'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId],$userId);
        mg_event('user_agent.contact_action_executed',['receipt_id'=>$receipt['id'],'action_type'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId],$userId);
        return ['draft_id'=>$draftPublicId,'status'=>'executed','receipt'=>$receipt,'dashboard'=>mg_personal_agent_dashboard($pdo,$userId)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try { $pdo->prepare("UPDATE user_agent_action_drafts SET status=IF(status='pending','failed',status),error_message=?,updated_at=NOW() WHERE owner_user_id=? AND public_id=?")->execute([mb_substr($error->getMessage(),0,1000),$userId,$draftPublicId]); } catch (Throwable) {}
        throw $error;
    }
}
