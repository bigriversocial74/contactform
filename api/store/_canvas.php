<?php
/**
 * Microgifter Agent Store Canvas helpers.
 *
 * This file powers the customer Store Presence system and the merchant-only
 * canvas dashboard. Customers enter from merchant feed posts. Merchants see
 * customer avatars, CRM context, and direct message actions from the canvas.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

const MG_STORE_ACTIVE_STATUSES = ['entered','active','idle'];
const MG_STORE_IDLE_MINUTES = 10;
const MG_STORE_EXPIRE_MINUTES = 60;

function mg_store_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return false;
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_store_canvas_schema_ready(PDO $pdo): bool
{
    foreach (['mg_store_sessions','mg_store_session_events','mg_customer_store_history','mg_agent_messages'] as $table) {
        if (!mg_store_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_store_require_schema(PDO $pdo): void
{
    if (!mg_store_canvas_schema_ready($pdo)) {
        mg_fail('Store Canvas tables are not installed. Run database/stage_20_agent_store_canvas.sql first.', 503);
    }
}

function mg_store_safe_public_id(mixed $value, string $label = 'Record'): string
{
    $publicId = strtolower(trim((string)$value));
    if ($publicId === '' || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) {
        throw new InvalidArgumentException($label . ' is required.');
    }
    return $publicId;
}

function mg_store_text(mixed $value, int $max, string $label): string
{
    $text = trim((string)$value);
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    if ($text === '') throw new InvalidArgumentException($label . ' is required.');
    if (mb_strlen($text) > $max) throw new InvalidArgumentException($label . ' is too long.');
    return $text;
}

function mg_store_avatar_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 600 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme']) || !in_array(strtolower((string)$parts['scheme']), ['http','https'], true)) return null;
    return $url;
}

function mg_store_user_is_merchant(PDO $pdo, int $userId, ?string $profileType = null): bool
{
    $type = strtolower(trim((string)$profileType));
    if (in_array($type, ['merchant','business'], true)) return true;

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug IN ('merchant','admin','super_admin') LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn()) return true;
    } catch (Throwable) {}

    try {
        if (mg_store_table_exists($pdo, 'merchant_storefronts')) {
            $stmt = $pdo->prepare("SELECT 1 FROM merchant_storefronts WHERE merchant_user_id=? AND status IN ('draft','published','paused') LIMIT 1");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn()) return true;
        }
    } catch (Throwable) {}

    return false;
}

function mg_store_load_post_target(PDO $pdo, string $postPublicId): array
{
    $postPublicId = mg_store_safe_public_id($postPublicId, 'Post');
    $stmt = $pdo->prepare(
        "SELECT fp.id feed_post_id,fp.public_id post_public_id,fp.created_by_user_id merchant_user_id,
                fp.headline,fp.status,fp.moderation_status,fp.visibility,
                pp.public_id merchant_profile_public_id,pp.slug merchant_slug,pp.display_name merchant_name,pp.avatar_url merchant_avatar_url,pp.profile_type merchant_profile_type,
                u.status merchant_user_status
         FROM feed_posts fp
         INNER JOIN users u ON u.id=fp.created_by_user_id
         INNER JOIN public_profiles pp ON pp.user_id=fp.created_by_user_id
         WHERE fp.public_id=? LIMIT 1"
    );
    $stmt->execute([$postPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string)$row['merchant_user_status'] !== 'active') throw new RuntimeException('Merchant post is not available.');
    if ((string)$row['status'] !== 'published' || in_array((string)$row['moderation_status'], ['hidden','removed'], true)) throw new RuntimeException('Merchant post is not available.');
    if (!mg_store_user_is_merchant($pdo, (int)$row['merchant_user_id'], (string)$row['merchant_profile_type'])) {
        throw new RuntimeException('This post is not connected to a merchant store.');
    }
    return $row;
}

function mg_store_expire_stale_sessions(PDO $pdo, ?int $merchantUserId = null): void
{
    if (!mg_store_canvas_schema_ready($pdo)) return;
    try {
        $params = [];
        $where = "active_key IS NOT NULL AND status IN ('entered','active','idle') AND last_active_at < DATE_SUB(NOW(), INTERVAL " . MG_STORE_EXPIRE_MINUTES . " MINUTE)";
        if ($merchantUserId !== null) {
            $where .= ' AND merchant_user_id=?';
            $params[] = $merchantUserId;
        }
        $stmt = $pdo->prepare("SELECT * FROM mg_store_sessions WHERE {$where} LIMIT 200");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $session) {
            mg_store_close_session_row($pdo, $session, 'timeout');
        }

        $idleWhere = "active_key IS NOT NULL AND status='active' AND last_active_at < DATE_SUB(NOW(), INTERVAL " . MG_STORE_IDLE_MINUTES . " MINUTE)";
        $idleParams = [];
        if ($merchantUserId !== null) {
            $idleWhere .= ' AND merchant_user_id=?';
            $idleParams[] = $merchantUserId;
        }
        $idle = $pdo->prepare("UPDATE mg_store_sessions SET status='idle',updated_at=NOW() WHERE {$idleWhere}");
        $idle->execute($idleParams);
    } catch (Throwable $error) {
        mg_security_log('error', 'store_canvas.expire_failed', 'Store session expiry failed.', ['exception'=>$error->getMessage()], null);
    }
}

function mg_store_active_session_for_customer(PDO $pdo, int $customerUserId, bool $forUpdate = false): ?array
{
    mg_store_expire_stale_sessions($pdo, null);
    $sql = "SELECT s.*,mp.display_name merchant_name,mp.avatar_url merchant_avatar_url,mp.slug merchant_slug,mp.profile_type merchant_profile_type,
                   cp.display_name customer_name,cp.avatar_url customer_avatar_url,cp.slug customer_slug,
                   fp.public_id source_post_public_id,fp.headline source_post_headline
            FROM mg_store_sessions s
            LEFT JOIN public_profiles mp ON mp.user_id=s.merchant_user_id
            LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id
            LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id
            WHERE s.customer_user_id=? AND s.active_key=? AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL
            ORDER BY s.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customerUserId, $customerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_store_project_session(?array $session): ?array
{
    if (!$session) return null;
    $enteredAt = (string)($session['entered_at'] ?? '');
    $seconds = 0;
    if ($enteredAt !== '') {
        $start = strtotime($enteredAt);
        if ($start !== false) $seconds = max(0, time() - $start);
    }
    return [
        'id' => (string)$session['public_id'],
        'status' => (string)$session['status'],
        'merchant' => [
            'name' => (string)($session['merchant_name'] ?: 'Merchant Store'),
            'slug' => (string)($session['merchant_slug'] ?? ''),
            'avatar_url' => mg_store_avatar_url($session['merchant_avatar_url'] ?? null),
        ],
        'customer' => [
            'name' => (string)($session['customer_name'] ?: 'Customer'),
            'slug' => (string)($session['customer_slug'] ?? ''),
            'avatar_url' => mg_store_avatar_url($session['customer_avatar_url'] ?? null),
        ],
        'source_post' => [
            'id' => $session['source_post_public_id'] !== null ? (string)$session['source_post_public_id'] : null,
            'headline' => $session['source_post_headline'] !== null ? (string)$session['source_post_headline'] : null,
        ],
        'entered_at' => $enteredAt,
        'last_active_at' => (string)($session['last_active_at'] ?? ''),
        'seconds_inside' => $seconds,
    ];
}

function mg_store_log_event(PDO $pdo, array $session, string $type, ?string $label = null, array $data = []): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO mg_store_session_events (public_id,store_session_id,customer_user_id,merchant_user_id,event_type,event_label,event_data_json,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())'
        );
        $stmt->execute([
            mg_public_uuid(),
            (int)$session['id'],
            (int)$session['customer_user_id'],
            (int)$session['merchant_user_id'],
            mb_substr($type, 0, 80),
            $label !== null ? mb_substr($label, 0, 180) : null,
            $data ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        ]);
    } catch (Throwable $error) {
        mg_security_log('error', 'store_canvas.event_failed', 'Store session event write failed.', ['event_type'=>$type,'exception'=>$error->getMessage()], (int)($session['customer_user_id'] ?? 0));
    }
}

function mg_store_write_history(PDO $pdo, array $session): void
{
    try {
        $counts = ['message_received'=>0,'received_reward'=>0,'claimed_reward'=>0,'viewed_product'=>0,'sent_gift'=>0];
        $stmt = $pdo->prepare('SELECT event_type,COUNT(*) total FROM mg_store_session_events WHERE store_session_id=? GROUP BY event_type');
        $stmt->execute([(int)$session['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string)$row['event_type'];
            if (array_key_exists($type, $counts)) $counts[$type] = (int)$row['total'];
        }
        $summary = 'Entered merchant store';
        $merchantName = '';
        try {
            $merchant = $pdo->prepare('SELECT display_name FROM public_profiles WHERE user_id=? LIMIT 1');
            $merchant->execute([(int)$session['merchant_user_id']]);
            $merchantName = (string)($merchant->fetchColumn() ?: '');
        } catch (Throwable) {}
        if ($merchantName !== '') $summary = 'Visited ' . mb_substr($merchantName, 0, 140);

        $upsert = $pdo->prepare(
            "INSERT INTO mg_customer_store_history
             (public_id,customer_user_id,merchant_user_id,store_session_id,source_feed_post_id,summary,started_at,ended_at,duration_seconds,messages_received_count,rewards_received_count,rewards_claimed_count,products_viewed_count,gifts_sent_count,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,TIMESTAMPDIFF(SECOND,?,COALESCE(?,NOW())),?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE ended_at=VALUES(ended_at),duration_seconds=VALUES(duration_seconds),messages_received_count=VALUES(messages_received_count),rewards_received_count=VALUES(rewards_received_count),rewards_claimed_count=VALUES(rewards_claimed_count),products_viewed_count=VALUES(products_viewed_count),gifts_sent_count=VALUES(gifts_sent_count),metadata_json=VALUES(metadata_json),updated_at=NOW()"
        );
        $endedAt = $session['exited_at'] ?? date('Y-m-d H:i:s');
        $metadata = ['exit_reason'=>$session['exit_reason'] ?? null,'session_status'=>$session['status'] ?? null];
        $upsert->execute([
            mg_public_uuid(),
            (int)$session['customer_user_id'],
            (int)$session['merchant_user_id'],
            (int)$session['id'],
            $session['source_feed_post_id'] !== null ? (int)$session['source_feed_post_id'] : null,
            $summary,
            (string)$session['entered_at'],
            $endedAt,
            (string)$session['entered_at'],
            $endedAt,
            $counts['message_received'],
            $counts['received_reward'],
            $counts['claimed_reward'],
            $counts['viewed_product'],
            $counts['sent_gift'],
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    } catch (Throwable $error) {
        mg_security_log('error', 'store_canvas.history_failed', 'Store session history write failed.', ['exception'=>$error->getMessage()], (int)($session['customer_user_id'] ?? 0));
    }
}

function mg_store_close_session_row(PDO $pdo, array $session, string $reason): array
{
    $reason = in_array($reason, ['manual','switch_store','timeout','merchant_removed','blocked','system'], true) ? $reason : 'system';
    $status = $reason === 'timeout' ? 'expired' : ($reason === 'blocked' ? 'blocked' : 'exited');
    $stmt = $pdo->prepare('UPDATE mg_store_sessions SET status=?,active_key=NULL,exited_at=COALESCE(exited_at,NOW()),exit_reason=?,updated_at=NOW() WHERE id=?');
    $stmt->execute([$status, $reason, (int)$session['id']]);
    $session['status'] = $status;
    $session['active_key'] = null;
    $session['exited_at'] = $session['exited_at'] ?: date('Y-m-d H:i:s');
    $session['exit_reason'] = $reason;
    mg_store_log_event($pdo, $session, 'exited_store', 'Exited store', ['reason'=>$reason]);
    mg_store_write_history($pdo, $session);
    return $session;
}

function mg_store_feed_status_for_post(PDO $pdo, ?int $viewerId, string $postPublicId): array
{
    $target = mg_store_load_post_target($pdo, $postPublicId);
    $base = [
        'entry_enabled' => true,
        'post_id' => (string)$target['post_public_id'],
        'target_merchant' => [
            'user_id' => (int)$target['merchant_user_id'],
            'name' => (string)($target['merchant_name'] ?: 'Merchant Store'),
            'slug' => (string)$target['merchant_slug'],
            'avatar_url' => mg_store_avatar_url($target['merchant_avatar_url'] ?? null),
        ],
        'active_session' => null,
        'state' => 'none',
        'label' => 'Enter Store',
        'notice' => null,
    ];

    if ($viewerId === null || $viewerId < 1) {
        $base['state'] = 'login_required';
        $base['label'] = 'Login to Enter Store';
        return $base;
    }
    if ($viewerId === (int)$target['merchant_user_id']) {
        $base['state'] = 'owner';
        $base['label'] = 'Merchant Canvas';
        $base['notice'] = 'This is your merchant post.';
        return $base;
    }
    if (!mg_store_canvas_schema_ready($pdo)) {
        $base['state'] = 'schema_missing';
        $base['label'] = 'Store Canvas Setup Needed';
        $base['notice'] = 'Store Canvas tables need to be installed before customers can enter merchant stores.';
        return $base;
    }

    $active = mg_store_active_session_for_customer($pdo, $viewerId);
    $base['active_session'] = mg_store_project_session($active);
    if (!$active) return $base;
    if ((int)$active['merchant_user_id'] === (int)$target['merchant_user_id']) {
        $base['state'] = 'inside_this';
        $base['label'] = 'Inside Store';
        $base['notice'] = 'Your avatar is currently shopping inside this merchant location.';
        return $base;
    }
    $base['state'] = 'inside_other';
    $base['label'] = 'Switch Store';
    $base['notice'] = 'You are currently shopping inside another merchant store.';
    return $base;
}

function mg_store_enter_post(PDO $pdo, int $customerUserId, string $postPublicId, bool $switchStore): array
{
    mg_store_require_schema($pdo);
    $target = mg_store_load_post_target($pdo, $postPublicId);
    if ($customerUserId === (int)$target['merchant_user_id']) throw new RuntimeException('You cannot enter your own merchant store from your post.');

    $pdo->beginTransaction();
    try {
        $active = mg_store_active_session_for_customer($pdo, $customerUserId, true);
        if ($active && (int)$active['merchant_user_id'] !== (int)$target['merchant_user_id'] && !$switchStore) {
            $pdo->commit();
            return [
                'requires_confirmation' => true,
                'current' => mg_store_project_session($active),
                'target_merchant' => [
                    'name' => (string)($target['merchant_name'] ?: 'Merchant Store'),
                    'slug' => (string)$target['merchant_slug'],
                ],
            ];
        }
        if ($active && (int)$active['merchant_user_id'] === (int)$target['merchant_user_id']) {
            $pdo->prepare("UPDATE mg_store_sessions SET status='active',last_active_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$active['id']]);
            $active['status'] = 'active';
            $active['last_active_at'] = date('Y-m-d H:i:s');
            mg_store_log_event($pdo, $active, 'store_session_resumed', 'Store session resumed', ['post_id'=>(string)$target['post_public_id']]);
            $pdo->commit();
            return ['requires_confirmation'=>false,'session'=>mg_store_project_session($active)];
        }
        if ($active) {
            mg_store_close_session_row($pdo, $active, 'switch_store');
        }

        $publicId = mg_public_uuid();
        $metadata = ['entered_from'=>'feed_post','source_post_public_id'=>(string)$target['post_public_id'],'source_post_headline'=>$target['headline'] !== null ? (string)$target['headline'] : null];
        $insert = $pdo->prepare(
            "INSERT INTO mg_store_sessions
             (public_id,customer_user_id,merchant_user_id,source_feed_post_id,status,active_key,entered_at,last_active_at,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?, 'entered', ?, NOW(), NOW(), ?, NOW(), NOW())"
        );
        $insert->execute([
            $publicId,
            $customerUserId,
            (int)$target['merchant_user_id'],
            (int)$target['feed_post_id'],
            $customerUserId,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $stmt = $pdo->prepare(
            "SELECT s.*,mp.display_name merchant_name,mp.avatar_url merchant_avatar_url,mp.slug merchant_slug,mp.profile_type merchant_profile_type,
                    cp.display_name customer_name,cp.avatar_url customer_avatar_url,cp.slug customer_slug,
                    fp.public_id source_post_public_id,fp.headline source_post_headline
             FROM mg_store_sessions s
             LEFT JOIN public_profiles mp ON mp.user_id=s.merchant_user_id
             LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id
             LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id
             WHERE s.public_id=? LIMIT 1"
        );
        $stmt->execute([$publicId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) throw new RuntimeException('Unable to start store session.');
        mg_store_log_event($pdo, $session, 'entered_store', 'Entered store from feed post', ['post_id'=>(string)$target['post_public_id']]);
        $pdo->commit();
        mg_event('store_canvas.customer_entered', ['session_id'=>$publicId,'post_id'=>(string)$target['post_public_id'],'merchant_user_id'=>(int)$target['merchant_user_id']], $customerUserId);
        return ['requires_confirmation'=>false,'session'=>mg_store_project_session($session)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_store_exit_for_customer(PDO $pdo, int $customerUserId, string $reason = 'manual'): ?array
{
    mg_store_require_schema($pdo);
    $pdo->beginTransaction();
    try {
        $active = mg_store_active_session_for_customer($pdo, $customerUserId, true);
        if (!$active) {
            $pdo->commit();
            return null;
        }
        $closed = mg_store_close_session_row($pdo, $active, $reason);
        $pdo->commit();
        mg_event('store_canvas.customer_exited', ['session_id'=>(string)$closed['public_id'],'merchant_user_id'=>(int)$closed['merchant_user_id'],'reason'=>$reason], $customerUserId);
        return $closed;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_store_heartbeat(PDO $pdo, int $customerUserId): ?array
{
    mg_store_require_schema($pdo);
    $active = mg_store_active_session_for_customer($pdo, $customerUserId);
    if (!$active) return null;
    $pdo->prepare("UPDATE mg_store_sessions SET status='active',last_active_at=NOW(),updated_at=NOW() WHERE id=? AND active_key=?")->execute([(int)$active['id'], $customerUserId]);
    $active['status'] = 'active';
    $active['last_active_at'] = date('Y-m-d H:i:s');
    return $active;
}

function mg_store_active_customers_for_merchant(PDO $pdo, int $merchantUserId): array
{
    mg_store_require_schema($pdo);
    mg_store_expire_stale_sessions($pdo, $merchantUserId);
    $stmt = $pdo->prepare(
        "SELECT s.*,cp.display_name customer_name,cp.avatar_url customer_avatar_url,cp.slug customer_slug,cp.profile_type customer_profile_type,
                fp.public_id source_post_public_id,fp.headline source_post_headline,
                TIMESTAMPDIFF(SECOND,s.entered_at,NOW()) seconds_inside,
                TIMESTAMPDIFF(SECOND,s.last_active_at,NOW()) seconds_since_active,
                (SELECT event_type FROM mg_store_session_events e WHERE e.store_session_id=s.id ORDER BY e.id DESC LIMIT 1) last_event_type,
                (SELECT event_label FROM mg_store_session_events e WHERE e.store_session_id=s.id ORDER BY e.id DESC LIMIT 1) last_event_label
         FROM mg_store_sessions s
         LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id
         LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id
         WHERE s.merchant_user_id=? AND s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL
         ORDER BY s.last_active_at DESC,s.id DESC"
    );
    $stmt->execute([$merchantUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'session_id' => (string)$row['public_id'],
            'status' => (string)$row['status'],
            'customer' => [
                'name' => (string)($row['customer_name'] ?: 'Customer'),
                'slug' => (string)($row['customer_slug'] ?? ''),
                'avatar_url' => mg_store_avatar_url($row['customer_avatar_url'] ?? null),
                'profile_type' => (string)($row['customer_profile_type'] ?: 'customer'),
            ],
            'source_post' => [
                'id' => $row['source_post_public_id'] !== null ? (string)$row['source_post_public_id'] : null,
                'headline' => $row['source_post_headline'] !== null ? (string)$row['source_post_headline'] : null,
            ],
            'seconds_inside' => (int)$row['seconds_inside'],
            'seconds_since_active' => (int)$row['seconds_since_active'],
            'last_event' => [
                'type' => $row['last_event_type'] !== null ? (string)$row['last_event_type'] : null,
                'label' => $row['last_event_label'] !== null ? (string)$row['last_event_label'] : null,
            ],
        ];
    }
    return $items;
}

function mg_store_customer_crm(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    mg_store_require_schema($pdo);
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $stmt = $pdo->prepare(
        "SELECT s.*,cp.display_name customer_name,cp.avatar_url customer_avatar_url,cp.slug customer_slug,cp.profile_type customer_profile_type,
                fp.public_id source_post_public_id,fp.headline source_post_headline
         FROM mg_store_sessions s
         LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id
         LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id
         WHERE s.public_id=? AND s.merchant_user_id=? LIMIT 1"
    );
    $stmt->execute([$sessionPublicId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Customer session is not available.');

    $stats = ['visit_count'=>0,'messages_sent'=>0,'rewards_received'=>0,'rewards_claimed'=>0,'gifts_sent'=>0];
    $visit = $pdo->prepare('SELECT COUNT(*) FROM mg_store_sessions WHERE customer_user_id=? AND merchant_user_id=?');
    $visit->execute([(int)$session['customer_user_id'], $merchantUserId]);
    $stats['visit_count'] = (int)$visit->fetchColumn();
    $messages = $pdo->prepare('SELECT COUNT(*) FROM mg_agent_messages WHERE recipient_user_id=? AND merchant_user_id=?');
    $messages->execute([(int)$session['customer_user_id'], $merchantUserId]);
    $stats['messages_sent'] = (int)$messages->fetchColumn();

    $eventStats = $pdo->prepare('SELECT event_type,COUNT(*) total FROM mg_store_session_events WHERE customer_user_id=? AND merchant_user_id=? GROUP BY event_type');
    $eventStats->execute([(int)$session['customer_user_id'], $merchantUserId]);
    foreach ($eventStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string)$row['event_type'];
        if ($type === 'received_reward') $stats['rewards_received'] = (int)$row['total'];
        if ($type === 'claimed_reward') $stats['rewards_claimed'] = (int)$row['total'];
        if ($type === 'sent_gift') $stats['gifts_sent'] = (int)$row['total'];
    }

    $events = [];
    $eventsStmt = $pdo->prepare('SELECT event_type,event_label,created_at FROM mg_store_session_events WHERE store_session_id=? ORDER BY id DESC LIMIT 20');
    $eventsStmt->execute([(int)$session['id']]);
    foreach ($eventsStmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $events[] = ['type'=>(string)$event['event_type'],'label'=>$event['event_label'] !== null ? (string)$event['event_label'] : null,'created_at'=>(string)$event['created_at']];
    }

    return [
        'session' => mg_store_project_session($session),
        'customer' => [
            'name' => (string)($session['customer_name'] ?: 'Customer'),
            'slug' => (string)($session['customer_slug'] ?? ''),
            'avatar_url' => mg_store_avatar_url($session['customer_avatar_url'] ?? null),
            'profile_type' => (string)($session['customer_profile_type'] ?: 'customer'),
            'account_status' => 'In system',
        ],
        'stats' => $stats,
        'events' => $events,
        'actions' => ['send_direct_message'=>true,'send_reward'=>false,'invite_campaign'=>false,'remove_from_store'=>false],
    ];
}

function mg_store_send_direct_message(PDO $pdo, int $merchantUserId, string $sessionPublicId, string $body): array
{
    mg_store_require_schema($pdo);
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $body = mg_store_text($body, 1000, 'Message');
    $stmt = $pdo->prepare('SELECT * FROM mg_store_sessions WHERE public_id=? AND merchant_user_id=? AND active_key IS NOT NULL AND status IN (\'entered\',\'active\',\'idle\') LIMIT 1');
    $stmt->execute([$sessionPublicId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Active customer session is not available.');

    $merchantLabel = 'Merchant';
    try {
        $merchant = $pdo->prepare('SELECT display_name FROM public_profiles WHERE user_id=? LIMIT 1');
        $merchant->execute([$merchantUserId]);
        $merchantLabel = trim((string)($merchant->fetchColumn() ?: 'Merchant')) ?: 'Merchant';
    } catch (Throwable) {}

    $publicId = mg_public_uuid();
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            "INSERT INTO mg_agent_messages
             (public_id,store_session_id,sender_user_id,recipient_user_id,merchant_user_id,sender_role,message_type,subject,body,status,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,'merchant','direct',? ,?,'sent',?,NOW(),NOW())"
        );
        $insert->execute([
            $publicId,
            (int)$session['id'],
            $merchantUserId,
            (int)$session['customer_user_id'],
            $merchantUserId,
            'Message from ' . mb_substr($merchantLabel, 0, 120),
            $body,
            json_encode(['source'=>'merchant_canvas','store_session_id'=>(string)$session['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        mg_store_log_event($pdo, $session, 'message_received', 'Merchant sent direct message', ['message_id'=>$publicId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        mg_create_notification($pdo, (int)$session['customer_user_id'], 'store_direct_message', 'New message from ' . $merchantLabel, mb_substr($body, 0, 240), '/inbox.php', ['actor_user_id'=>$merchantUserId,'merchant_user_id'=>$merchantUserId,'store_session_id'=>(string)$session['public_id'],'message_id'=>$publicId]);
    } catch (Throwable $error) {
        mg_security_log('error', 'store_canvas.message_notification_failed', 'Store canvas message notification failed.', ['exception'=>$error->getMessage()], $merchantUserId);
    }

    mg_event('store_canvas.direct_message_sent', ['message_id'=>$publicId,'session_id'=>(string)$session['public_id'],'customer_user_id'=>(int)$session['customer_user_id']], $merchantUserId);
    return ['id'=>$publicId,'body'=>$body,'created_at'=>date('Y-m-d H:i:s')];
}
