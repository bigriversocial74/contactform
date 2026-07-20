<?php
declare(strict_types=1);

function mg_task_agent_memory_sensitive(string $text): bool
{
    return preg_match('/\b(password|passcode|api[_ -]?key|access[_ -]?token|refresh[_ -]?token|secret|claim[_ -]?code|card[_ -]?number|cvv|cvc|ssn|social security|routing[_ -]?number|bank[_ -]?account|private[_ -]?key|street[_ -]?address|email address|phone number)\b/i', $text) === 1;
}

function mg_task_agent_memory_scalar(mixed $value): string|int|float|bool|null
{
    if ($value === null || is_bool($value) || is_int($value) || is_float($value)) return $value;
    if (!is_string($value)) throw new InvalidArgumentException('Agent memory values must be text or simple structured data.');
    $value = trim(mb_substr($value, 0, 1500));
    if ($value === '') return '';
    if (mg_task_agent_memory_sensitive($value)) throw new InvalidArgumentException('Sensitive credentials and private contact details cannot be stored in Agent Memory.');
    return $value;
}

function mg_task_agent_memory_sanitize(mixed $value, int $depth = 0): mixed
{
    if ($depth > 3) throw new InvalidArgumentException('Agent memory is too deeply nested.');
    if (!is_array($value)) return mg_task_agent_memory_scalar($value);
    $clean = [];
    foreach (array_slice($value, 0, 30, true) as $key => $item) {
        $safeKey = is_int($key) ? $key : trim(mb_substr((string)$key, 0, 80));
        if (is_string($safeKey) && mg_task_agent_memory_sensitive($safeKey)) {
            throw new InvalidArgumentException('Sensitive fields cannot be stored in Agent Memory.');
        }
        $clean[$safeKey] = mg_task_agent_memory_sanitize($item, $depth + 1);
    }
    return $clean;
}

function mg_task_agent_memory_list(PDO $pdo, int $userId, int $agentId, int $limit = 50): array
{
    $stmt = $pdo->prepare('SELECT public_id,memory_key,category,title,value_json,source,confidence,updated_at FROM multi_agent_memory WHERE owner_user_id=? AND agent_id=? AND status=\'active\' ORDER BY updated_at DESC,id DESC LIMIT '.max(1,min(100,$limit)));
    $stmt->execute([$userId,$agentId]);
    return array_map(static function(array $row): array {
        $decoded = json_decode((string)$row['value_json'], true);
        $row['value'] = mg_task_agent_memory_sanitize($decoded);
        unset($row['value_json']);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_memory_save(PDO $pdo, int $userId, int $agentId, array $input): array
{
    $title = trim(mb_substr((string)($input['title'] ?? ''), 0, 190));
    if ($title === '' || mg_task_agent_memory_sensitive($title)) throw new InvalidArgumentException('A safe memory title is required.');
    $key = trim(mb_substr((string)($input['memory_key'] ?? ''), 0, 160));
    if ($key === '') $key = 'memory.'.substr(hash('sha256', mb_strtolower($title)), 0, 24);
    if (mg_task_agent_memory_sensitive($key)) throw new InvalidArgumentException('Sensitive fields cannot be stored in Agent Memory.');
    $category = trim(mb_substr((string)($input['category'] ?? 'preference'), 0, 64)) ?: 'preference';
    if (!in_array($category, ['preference','budget','timing','merchant','category','relationship','instruction','gifting_style','onboarding'], true)) $category = 'preference';
    $value = mg_task_agent_memory_sanitize($input['value'] ?? '');
    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO multi_agent_memory(public_id,agent_id,owner_user_id,memory_key,category,title,value_json,source,confidence,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'user',1.000,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE category=VALUES(category),title=VALUES(title),value_json=VALUES(value_json),source='user',confidence=1.000,status='active',updated_at=NOW()")
        ->execute([$publicId,$agentId,$userId,$key,$category,$title,json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    mg_audit('multi_agent.memory_saved','agent',['agent_id'=>$agentId,'memory_key'=>$key,'used_ai'=>false],$userId);
    foreach (mg_task_agent_memory_list($pdo,$userId,$agentId,100) as $memory) if ($memory['memory_key'] === $key) return $memory;
    throw new RuntimeException('Unable to load saved Agent Memory.');
}

function mg_task_agent_memory_archive(PDO $pdo, int $userId, int $agentId, string $publicId): void
{
    $stmt=$pdo->prepare("UPDATE multi_agent_memory SET status='archived',updated_at=NOW() WHERE public_id=? AND owner_user_id=? AND agent_id=? AND status='active'");
    $stmt->execute([$publicId,$userId,$agentId]);
    if ($stmt->rowCount() < 1) throw new RuntimeException('Agent Memory item not found.');
    mg_audit('multi_agent.memory_archived','agent',['agent_id'=>$agentId,'memory_id'=>$publicId,'used_ai'=>false],$userId);
}

function mg_task_agent_memory_search(array $items, string $query, int $limit = 8): array
{
    $terms = array_values(array_filter(preg_split('/[^a-z0-9]+/i', mb_strtolower($query)) ?: [], static fn(string $term): bool => mb_strlen($term) >= 3));
    if (!$terms) return array_slice($items,0,$limit);
    $scored=[];
    foreach ($items as $item) {
        $haystack=mb_strtolower((string)($item['title']??'').' '.(string)($item['category']??'').' '.json_encode($item['value']??'',JSON_UNESCAPED_UNICODE));
        $score=0;foreach($terms as $term) if(str_contains($haystack,$term))$score++;
        if($score>0)$scored[]=['score'=>$score,'item'=>$item];
    }
    usort($scored,static fn(array $a,array $b):int=>$b['score']<=>$a['score']);
    return array_slice(array_column($scored,'item'),0,$limit);
}

function mg_task_agent_memory_system_response(string $message, array $items): ?array
{
    $text=mb_strtolower(trim($message));
    if (!preg_match('/\b(remember|memory|memories|preference|preferences|budget|budgets|instruction|instructions)\b/u',$text)) return null;
    if (preg_match('/\b(show|list|what|recall|find|search)\b/u',$text)) {
        $matches=mg_task_agent_memory_search($items,$message,10);
        if(!$matches)return ['reply'=>'This agent has no matching saved memory.','cards'=>[],'system_intent'=>'memory_search'];
        $lines=array_map(static fn(array $item):string=>(string)$item['title'].' — '.(is_string($item['value']??null)?(string)$item['value']:json_encode($item['value']??'',JSON_UNESCAPED_UNICODE)),$matches);
        return ['reply'=>"Here is the matching memory saved only for this agent:\n\n".implode("\n",$lines),'cards'=>[],'system_intent'=>'memory_search'];
    }
    return null;
}

function mg_task_agent_memory_for_model(array $items): array
{
    return array_map(static fn(array $item):array=>[
        'category'=>(string)($item['category']??'preference'),
        'title'=>(string)($item['title']??''),
        'value'=>mg_task_agent_memory_sanitize($item['value']??null),
        'source'=>(string)($item['source']??'user'),
    ],array_slice($items,0,20));
}
