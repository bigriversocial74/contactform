<?php
declare(strict_types=1);

require_once __DIR__ . '/_conversations.php';

function mg_world_reward_drops_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'world_reward_drops') && mg_world_canvas_table($pdo, 'world_reward_drop_claims');
}

function mg_world_reward_drops_require(PDO $pdo): void
{
    if (!mg_world_reward_drops_ready($pdo)) throw new RuntimeException('World Canvas reward drop tables are not installed yet.');
}

function mg_world_reward_drop_text(mixed $value, int $max, string $label, bool $required = true): string
{
    $text = trim((string)$value);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    if ($required && $text === '') throw new InvalidArgumentException($label . ' is required.');
    return mb_substr($text, 0, $max);
}

function mg_world_reward_drop_claim_code(): string
{
    return 'WDR-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
}

function mg_world_reward_drop_user_can_create(PDO $pdo, array $user): bool
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) return false;
    $profileType = '';
    try {
        $stmt = $pdo->prepare('SELECT profile_type FROM public_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);
        $profileType = (string)($stmt->fetchColumn() ?: '');
    } catch (Throwable) {}
    return mg_store_user_is_merchant($pdo, $userId, $profileType);
}

function mg_world_reward_drop_conversation(PDO $pdo, mixed $conversationPublicId): ?array
{
    $id = trim((string)$conversationPublicId);
    if ($id === '') return null;
    try {
        return mg_world_conversation_load_by_public_id($pdo, $id);
    } catch (Throwable) {
        return null;
    }
}

function mg_world_reward_drop_project(PDO $pdo, array $drop, int $viewerUserId): array
{
    $claimedByViewer = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_reward_drop_claims WHERE reward_drop_id=? AND user_id=? AND status IN ('claimed','redeemed')", [(int)$drop['id'], $viewerUserId]) > 0;
    $claims = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_reward_drop_claims WHERE reward_drop_id=? AND status IN ('claimed','redeemed')", [(int)$drop['id']]);
    return [
        'id' => (string)$drop['public_id'],
        'title' => (string)$drop['title'],
        'reward_label' => (string)$drop['reward_label'],
        'reward_description' => (string)($drop['reward_description'] ?? ''),
        'status' => (string)$drop['status'],
        'quantity_total' => (int)$drop['quantity_total'],
        'quantity_remaining' => (int)$drop['quantity_remaining'],
        'claims' => $claims,
        'claimed_by_viewer' => $claimedByViewer,
        'merchant_user_id' => (int)$drop['merchant_user_id'],
        'owned' => (int)$drop['merchant_user_id'] === $viewerUserId,
        'expires_at' => (string)($drop['expires_at'] ?? ''),
        'created_at' => (string)$drop['created_at'],
    ];
}

function mg_world_reward_drop_list(PDO $pdo, array $user, array $input): array
{
    mg_world_reward_drops_require($pdo);
    $viewerUserId = (int)($user['id'] ?? 0);
    $conversation = mg_world_reward_drop_conversation($pdo, $input['conversation_id'] ?? '');
    $clusterKey = mg_world_conversation_clean_text($input['cluster_key'] ?? ($conversation['cluster_key'] ?? ''), 220);
    $params = [];
    $where = "status IN ('active','paused','exhausted')";
    if ($conversation) {
        $where .= ' AND conversation_id=?';
        $params[] = (int)$conversation['id'];
    } elseif ($clusterKey !== '') {
        $where .= ' AND cluster_key_hash=?';
        $params[] = hash('sha256', $clusterKey);
    } else {
        throw new InvalidArgumentException('Conversation or cluster key is required.');
    }
    $rows = mg_world_canvas_rows($pdo, "SELECT * FROM world_reward_drops WHERE {$where} ORDER BY id DESC LIMIT 20", $params);
    return array_map(fn(array $row): array => mg_world_reward_drop_project($pdo, $row, $viewerUserId), $rows);
}

function mg_world_reward_drop_create(PDO $pdo, array $user, array $input): array
{
    mg_world_reward_drops_require($pdo);
    if (!mg_world_reward_drop_user_can_create($pdo, $user)) throw new RuntimeException('Only merchant accounts can create World Canvas reward drops.');
    $creatorId = (int)$user['id'];
    $conversation = mg_world_reward_drop_conversation($pdo, $input['conversation_id'] ?? '');
    $clusterKey = mg_world_conversation_cluster_key($input['cluster_key'] ?? ($conversation['cluster_key'] ?? ''));
    $title = mg_world_reward_drop_text($input['title'] ?? 'World Canvas reward drop', 180, 'Drop title');
    $rewardLabel = mg_world_reward_drop_text($input['reward_label'] ?? '', 180, 'Reward label');
    $description = mg_world_reward_drop_text($input['reward_description'] ?? '', 1200, 'Reward description', false);
    $quantity = max(1, min(500, (int)($input['quantity_total'] ?? 10)));
    $locationKey = mg_world_reward_drop_text($input['location_key'] ?? ($conversation['location_key'] ?? ''), 160, 'Location key', false);
    $expiresHours = max(1, min(168, (int)($input['expires_hours'] ?? 48)));
    $metadata = [
        'source' => 'world_canvas_reward_drop',
        'created_from' => $conversation ? 'conversation' : 'cluster',
        'created_at_utc' => gmdate('c'),
    ];

    $stmt = $pdo->prepare("INSERT INTO world_reward_drops (public_id,conversation_id,cluster_key_hash,cluster_key,location_key,merchant_user_id,created_by_user_id,title,reward_label,reward_description,quantity_total,quantity_remaining,claim_limit_per_user,status,expires_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,'active',DATE_ADD(NOW(), INTERVAL {$expiresHours} HOUR),?,NOW(),NOW())");
    $stmt->execute([
        mg_public_uuid(),
        $conversation ? (int)$conversation['id'] : null,
        hash('sha256', $clusterKey),
        $clusterKey,
        $locationKey !== '' ? $locationKey : null,
        $creatorId,
        $creatorId,
        $title,
        $rewardLabel,
        $description !== '' ? $description : null,
        $quantity,
        $quantity,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    $drop = mg_world_canvas_rows($pdo, 'SELECT * FROM world_reward_drops WHERE id=?', [(int)$pdo->lastInsertId()])[0] ?? null;
    if (!$drop) throw new RuntimeException('Unable to create reward drop.');

    if ($conversation) {
        $body = 'Reward drop added: ' . $rewardLabel . ' · ' . $quantity . ' available.';
        $pdo->prepare("INSERT INTO world_conversation_messages (public_id,conversation_id,member_id,user_id,identity_mode,sender_label,message_body,message_type,status,metadata_json,created_at) VALUES (?,?,?,?, 'merchant_owned','Reward Drop',?,'reward_drop','visible',?,NOW())")->execute([
            mg_public_uuid(),
            (int)$conversation['id'],
            null,
            $creatorId,
            $body,
            json_encode(['reward_drop_id'=>(string)$drop['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $messageCount = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_conversation_messages WHERE conversation_id=? AND status='visible'", [(int)$conversation['id']]);
        $pdo->prepare('UPDATE world_conversations SET message_count=?,last_message_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$messageCount, (int)$conversation['id']]);
    }

    return mg_world_reward_drop_project($pdo, $drop, $creatorId);
}

function mg_world_reward_drop_claim(PDO $pdo, array $user, array $input): array
{
    mg_world_reward_drops_require($pdo);
    $viewerUserId = (int)($user['id'] ?? 0);
    $dropPublicId = trim((string)($input['drop_id'] ?? ''));
    if ($dropPublicId === '') throw new InvalidArgumentException('Reward drop is required.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM world_reward_drops WHERE public_id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$dropPublicId]);
        $drop = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$drop) throw new RuntimeException('Reward drop was not found.');
        if ((string)$drop['status'] !== 'active') throw new RuntimeException('This reward drop is not active.');
        if ((int)$drop['quantity_remaining'] < 1) throw new RuntimeException('This reward drop is exhausted.');
        if (!empty($drop['expires_at']) && strtotime((string)$drop['expires_at']) !== false && strtotime((string)$drop['expires_at']) < time()) {
            $pdo->prepare("UPDATE world_reward_drops SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$drop['id']]);
            throw new RuntimeException('This reward drop has expired.');
        }
        $existing = mg_world_canvas_rows($pdo, "SELECT * FROM world_reward_drop_claims WHERE reward_drop_id=? AND user_id=? AND status IN ('claimed','redeemed') LIMIT 1", [(int)$drop['id'], $viewerUserId])[0] ?? null;
        if ($existing) {
            $pdo->commit();
            return ['drop' => mg_world_reward_drop_project($pdo, $drop, $viewerUserId), 'claim' => ['id'=>(string)$existing['public_id'], 'claim_code'=>(string)$existing['claim_code'], 'status'=>(string)$existing['status']]];
        }
        $session = mg_world_conversation_load_active_session($pdo, $viewerUserId);
        $claimCode = mg_world_reward_drop_claim_code();
        $stmt = $pdo->prepare("INSERT INTO world_reward_drop_claims (public_id,reward_drop_id,user_id,store_session_id,claim_code,status,claimed_at,metadata_json,created_at) VALUES (?,?,?,?,?,'claimed',NOW(),?,NOW())");
        $stmt->execute([
            mg_public_uuid(),
            (int)$drop['id'],
            $viewerUserId,
            $session ? (int)$session['id'] : null,
            $claimCode,
            json_encode(['source'=>'world_canvas_reward_drop'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $pdo->prepare("UPDATE world_reward_drops SET quantity_remaining=quantity_remaining-1,status=IF(quantity_remaining-1<=0,'exhausted',status),updated_at=NOW() WHERE id=?")->execute([(int)$drop['id']]);
        if ($session) mg_store_log_event($pdo, $session, 'world_reward_drop_claimed', 'World reward drop claimed', ['drop_id'=>(string)$drop['public_id'], 'claim_code'=>$claimCode]);
        $drop = mg_world_canvas_rows($pdo, 'SELECT * FROM world_reward_drops WHERE id=?', [(int)$drop['id']])[0] ?? $drop;
        $claim = mg_world_canvas_rows($pdo, 'SELECT * FROM world_reward_drop_claims WHERE claim_code=? LIMIT 1', [$claimCode])[0] ?? null;
        $pdo->commit();
        return ['drop' => mg_world_reward_drop_project($pdo, $drop, $viewerUserId), 'claim' => ['id'=>(string)($claim['public_id'] ?? ''), 'claim_code'=>$claimCode, 'status'=>'claimed']];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
