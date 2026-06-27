<?php
declare(strict_types=1);

/**
 * Store Canvas customer-entry runtime helpers.
 *
 * These functions use the robust Stage 20 schema detector so customer entry
 * from public feed posts does not falsely fail when the tables exist in the
 * active database but SHOW TABLES prepared probes are unreliable.
 */
require_once __DIR__ . '/_canvas_schema.php';

function mg_store_runtime_core_tables(): array
{
    return ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'];
}

function mg_store_runtime_schema_ready(PDO $pdo): bool
{
    return mg_store_canvas_missing_tables($pdo, mg_store_runtime_core_tables()) === [];
}

function mg_store_runtime_require_schema(PDO $pdo): void
{
    mg_store_canvas_require_tables($pdo, mg_store_runtime_core_tables(), 'Store Canvas customer entry');
}

function mg_store_runtime_expire_stale_sessions(PDO $pdo, ?int $merchantUserId = null): void
{
    if (!mg_store_runtime_schema_ready($pdo)) return;
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
        mg_security_log('error', 'store_canvas.runtime_expire_failed', 'Store runtime session expiry failed.', ['exception'=>$error->getMessage()], null);
    }
}

function mg_store_runtime_active_session_for_customer(PDO $pdo, int $customerUserId, bool $forUpdate = false): ?array
{
    mg_store_runtime_expire_stale_sessions($pdo, null);
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

function mg_store_runtime_feed_status_for_post(PDO $pdo, ?int $viewerId, string $postPublicId): array
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
        'notice' => 'Enter this merchant store with your customer avatar.',
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
    if (!mg_store_runtime_schema_ready($pdo)) {
        $base['state'] = 'schema_missing';
        $base['label'] = 'Store Canvas Setup Needed';
        $base['notice'] = 'Store Canvas tables need to be installed before customers can enter merchant stores.';
        return $base;
    }

    $active = mg_store_runtime_active_session_for_customer($pdo, $viewerId);
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

function mg_store_runtime_enter_post(PDO $pdo, int $customerUserId, string $postPublicId, bool $switchStore): array
{
    mg_store_runtime_require_schema($pdo);
    $target = mg_store_load_post_target($pdo, $postPublicId);
    if ($customerUserId === (int)$target['merchant_user_id']) throw new RuntimeException('You cannot enter your own merchant store from your post.');

    $pdo->beginTransaction();
    try {
        $active = mg_store_runtime_active_session_for_customer($pdo, $customerUserId, true);
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
        $metadata = [
            'entered_from'=>'feed_post',
            'source_post_public_id'=>(string)$target['post_public_id'],
            'source_post_headline'=>$target['headline'] !== null ? (string)$target['headline'] : null,
            'entry_runtime'=>'store_canvas_runtime_v2',
        ];
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
        mg_store_log_event($pdo, $session, 'entered_store', 'Entered store from feed post', ['post_id'=>(string)$target['post_public_id'],'entry_runtime'=>'store_canvas_runtime_v2']);
        $pdo->commit();
        mg_event('store_canvas.customer_entered', ['session_id'=>$publicId,'post_id'=>(string)$target['post_public_id'],'merchant_user_id'=>(int)$target['merchant_user_id']], $customerUserId);
        return ['requires_confirmation'=>false,'session'=>mg_store_project_session($session)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_store_runtime_exit_for_customer(PDO $pdo, int $customerUserId, string $reason = 'manual'): ?array
{
    mg_store_runtime_require_schema($pdo);
    $pdo->beginTransaction();
    try {
        $active = mg_store_runtime_active_session_for_customer($pdo, $customerUserId, true);
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

function mg_store_runtime_heartbeat(PDO $pdo, int $customerUserId): ?array
{
    mg_store_runtime_require_schema($pdo);
    $active = mg_store_runtime_active_session_for_customer($pdo, $customerUserId);
    if (!$active) return null;
    $pdo->prepare("UPDATE mg_store_sessions SET status='active',last_active_at=NOW(),updated_at=NOW() WHERE id=? AND active_key=?")->execute([(int)$active['id'], $customerUserId]);
    $active['status'] = 'active';
    $active['last_active_at'] = date('Y-m-d H:i:s');
    return $active;
}
