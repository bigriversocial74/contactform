<?php
declare(strict_types=1);

function mg_personal_agent_create_plan(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_require_schema($pdo);
    $context = mg_personal_agent_resolve_context(
        $pdo,
        $userId,
        (string)($input['context_type'] ?? 'none'),
        (string)($input['context_id'] ?? '')
    );
    $title = mg_personal_agent_text($input['title'] ?? '', 190);
    if ($title === '') throw new InvalidArgumentException('Plan title is required.');
    $occasionType = mg_personal_agent_text($input['occasion_type'] ?? 'general', 64) ?: 'general';
    $occasionLabel = mg_personal_agent_nullable_text($input['occasion_label'] ?? null, 160);
    $targetDate = mg_personal_agent_date($input['target_date'] ?? null);
    $budgetMin = mg_personal_agent_decimal($input['budget_min'] ?? null);
    $budgetMax = mg_personal_agent_decimal($input['budget_max'] ?? null);
    if ($budgetMin !== null && $budgetMax !== null && $budgetMin > $budgetMax) throw new InvalidArgumentException('Minimum budget cannot exceed maximum budget.');
    $currency = mg_personal_agent_currency($input['currency'] ?? 'USD');
    $notes = mg_personal_agent_nullable_text($input['notes'] ?? null, 5000);
    $source = mg_personal_agent_text($input['source'] ?? 'manual', 30);
    if (!in_array($source, ['manual','agent','important_date','list'], true)) $source = 'manual';
    $recommendation = is_array($input['recommendation'] ?? null) ? $input['recommendation'] : [];
    $ids = $context['internal'] ?? [];
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare("INSERT INTO user_gifting_plans
        (public_id,owner_user_id,list_id,user_contact_id,contact_user_id,title,occasion_type,occasion_label,target_date,budget_min,budget_max,currency,status,notes,recommendation_json,source,approval_required,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'draft',?,?,?,1,NOW(),NOW())");
    $stmt->execute([
        $publicId,$userId,$ids['list_id'] ?? null,$ids['user_contact_id'] ?? null,$ids['contact_user_id'] ?? null,
        $title,$occasionType,$occasionLabel,$targetDate,$budgetMin,$budgetMax,$currency,$notes,
        $recommendation ? mg_personal_agent_json_encode($recommendation) : null,$source
    ]);
    mg_audit('user_gifting_plan.created', 'user_gifting_plan', ['plan_id'=>$publicId,'source'=>$source,'context_type'=>$context['type']], $userId);
    mg_event('user_gifting_plan.created', ['plan_id'=>$publicId,'source'=>$source,'context_type'=>$context['type']], $userId);
    $plans = mg_personal_agent_plans($pdo, $userId, 'all', 250);
    foreach ($plans as $plan) if ($plan['id'] === $publicId) return $plan;
    throw new RuntimeException('Unable to load the created gifting plan.');
}

function mg_personal_agent_update_plan_status(PDO $pdo, int $userId, string $publicId, string $status): array
{
    $allowed = ['draft','planned','ready','completed','cancelled'];
    if (!in_array($status, $allowed, true)) throw new InvalidArgumentException('Invalid plan status.');
    $stmt = $pdo->prepare("UPDATE user_gifting_plans SET status=?,completed_at=IF(?='completed',NOW(),NULL),
        cancelled_at=IF(?='cancelled',NOW(),NULL),updated_at=NOW() WHERE owner_user_id=? AND public_id=?");
    $stmt->execute([$status,$status,$status,$userId,$publicId]);
    if ($stmt->rowCount() < 1) {
        $exists = $pdo->prepare('SELECT 1 FROM user_gifting_plans WHERE owner_user_id=? AND public_id=?');
        $exists->execute([$userId,$publicId]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Gifting plan not found.');
    }
    mg_audit('user_gifting_plan.status_updated', 'user_gifting_plan', ['plan_id'=>$publicId,'status'=>$status], $userId);
    foreach (mg_personal_agent_plans($pdo, $userId, 'all', 250) as $plan) if ($plan['id'] === $publicId) return $plan;
    throw new RuntimeException('Gifting plan not found.');
}

function mg_personal_agent_create_reminder(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_require_schema($pdo);
    $context = mg_personal_agent_resolve_context($pdo,$userId,(string)($input['context_type'] ?? 'none'),(string)($input['context_id'] ?? ''));
    $title = mg_personal_agent_text($input['title'] ?? '', 190);
    if ($title === '') throw new InvalidArgumentException('Reminder title is required.');
    $remindAt = mg_personal_agent_datetime($input['remind_at'] ?? '');
    $type = mg_personal_agent_text($input['reminder_type'] ?? 'gift_planning', 64) ?: 'gift_planning';
    $notes = mg_personal_agent_nullable_text($input['notes'] ?? null, 2000);
    $ids = $context['internal'] ?? [];
    $planId = ($context['type'] ?? '') === 'plan' ? ($ids['plan_id'] ?? null) : null;
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare("INSERT INTO user_gifting_reminders
        (public_id,owner_user_id,plan_id,list_id,user_contact_id,contact_user_id,reminder_type,title,remind_at,status,delivery_channel,notes,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,'scheduled','in_app',?,NOW(),NOW())");
    $stmt->execute([$publicId,$userId,$planId,$ids['list_id'] ?? null,$ids['user_contact_id'] ?? null,$ids['contact_user_id'] ?? null,$type,$title,$remindAt,$notes]);
    mg_audit('user_gifting_reminder.created', 'user_gifting_reminder', ['reminder_id'=>$publicId,'context_type'=>$context['type']], $userId);
    foreach (mg_personal_agent_reminders($pdo,$userId,'all',250) as $row) if ($row['id'] === $publicId) return $row;
    throw new RuntimeException('Unable to load the created reminder.');
}

function mg_personal_agent_update_reminder_status(PDO $pdo, int $userId, string $publicId, string $status): array
{
    $allowed = ['scheduled','completed','dismissed','cancelled'];
    if (!in_array($status,$allowed,true)) throw new InvalidArgumentException('Invalid reminder status.');
    $stmt = $pdo->prepare("UPDATE user_gifting_reminders SET status=?,completed_at=IF(?='completed',NOW(),NULL),
        dismissed_at=IF(?='dismissed',NOW(),NULL),updated_at=NOW() WHERE owner_user_id=? AND public_id=?");
    $stmt->execute([$status,$status,$status,$userId,$publicId]);
    if ($stmt->rowCount() < 1) {
        $exists=$pdo->prepare('SELECT 1 FROM user_gifting_reminders WHERE owner_user_id=? AND public_id=?');
        $exists->execute([$userId,$publicId]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Reminder not found.');
    }
    mg_audit('user_gifting_reminder.status_updated','user_gifting_reminder',['reminder_id'=>$publicId,'status'=>$status],$userId);
    foreach (mg_personal_agent_reminders($pdo,$userId,'all',250) as $row) if ($row['id'] === $publicId) return $row;
    throw new RuntimeException('Reminder not found.');
}

function mg_personal_agent_create_date(PDO $pdo, int $userId, array $input): array
{
    $contact = mg_personal_agent_resolve_context($pdo,$userId,'contact',(string)($input['contact_id'] ?? ''));
    $label = mg_personal_agent_text($input['label'] ?? '', 160);
    if ($label === '') throw new InvalidArgumentException('Date label is required.');
    $eventDate = mg_personal_agent_date($input['event_date'] ?? null);
    if ($eventDate === null) throw new InvalidArgumentException('Event date is required.');
    $dateType = mg_personal_agent_text($input['date_type'] ?? 'important_date',64) ?: 'important_date';
    $annual = filter_var($input['repeats_annually'] ?? true,FILTER_VALIDATE_BOOLEAN);
    $days = max(0,min(365,(int)($input['reminder_days_before'] ?? 14)));
    $publicId=mg_public_uuid();
    $stmt=$pdo->prepare("INSERT INTO user_contact_dates
        (public_id,owner_user_id,user_contact_id,date_type,label,event_date,repeats_annually,reminder_days_before,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([$publicId,$userId,$contact['internal']['user_contact_id'],$dateType,$label,$eventDate,$annual?1:0,$days]);
    mg_audit('user_contact_date.created','user_contact_date',['date_id'=>$publicId,'contact_id'=>$contact['id']],$userId);
    return ['id'=>$publicId,'contact_id'=>$contact['id'],'contact_name'=>$contact['name'],'type'=>$dateType,'label'=>$label,'event_date'=>$eventDate,'repeats_annually'=>$annual,'reminder_days_before'=>$days];
}

function mg_personal_agent_memory_key(string $title): string
{
    $key = mb_strtolower($title);
    $key = preg_replace('/[^a-z0-9]+/u','_',$key) ?? '';
    return trim(mb_substr($key,0,140),'_') ?: 'memory_' . substr(hash('sha256',$title),0,12);
}

function mg_personal_agent_save_memory(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_require_schema($pdo);
    $title=mg_personal_agent_text($input['title'] ?? '',190);
    $value=mg_personal_agent_text($input['value'] ?? '',1500);
    if ($title==='' || $value==='') throw new InvalidArgumentException('Memory title and value are required.');
    $category=mg_personal_agent_text($input['category'] ?? 'preference',64) ?: 'preference';
    $allowed=['preference','budget','timing','merchant','category','relationship','instruction','gifting_style'];
    if (!in_array($category,$allowed,true)) $category='preference';
    $key=mg_personal_agent_text($input['memory_key'] ?? '',160);
    if ($key==='') $key=mg_personal_agent_memory_key($title);
    if (preg_match('/(password|token|claim.?code|phone|email|street.?address|card.?number|ssn)/i',$key.' '.$title)===1) {
        throw new InvalidArgumentException('Sensitive credentials and private contact details cannot be stored in Agent Memory.');
    }
    $publicId=mg_public_uuid();
    $payload=['text'=>$value];
    $stmt=$pdo->prepare("INSERT INTO user_agent_memory
        (public_id,owner_user_id,memory_key,category,title,value_json,source,confidence,status,created_at,updated_at)
        VALUES (?,?,?,?,?,?,'user',1.000,'active',NOW(),NOW())
        ON DUPLICATE KEY UPDATE category=VALUES(category),title=VALUES(title),value_json=VALUES(value_json),
        source='user',confidence=1.000,status='active',updated_at=NOW()");
    $stmt->execute([$publicId,$userId,$key,$category,$title,mg_personal_agent_json_encode($payload)]);
    mg_audit('user_agent.memory_saved','user_agent_memory',['memory_key'=>$key,'category'=>$category],$userId);
    foreach (mg_personal_agent_memory($pdo,$userId,true) as $memory) if ($memory['key']===$key) return $memory;
    throw new RuntimeException('Unable to load Agent Memory.');
}

function mg_personal_agent_archive_memory(PDO $pdo, int $userId, string $publicId): array
{
    $stmt=$pdo->prepare("UPDATE user_agent_memory SET status='archived',updated_at=NOW() WHERE owner_user_id=? AND public_id=?");
    $stmt->execute([$userId,$publicId]);
    if ($stmt->rowCount()<1) throw new RuntimeException('Agent Memory item not found.');
    mg_audit('user_agent.memory_archived','user_agent_memory',['memory_id'=>$publicId],$userId);
    foreach (mg_personal_agent_memory($pdo,$userId,true) as $memory) if ($memory['id']===$publicId) return $memory;
    throw new RuntimeException('Agent Memory item not found.');
}

