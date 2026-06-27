<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_merchant_canvas_required_tables_missing(PDO $pdo): array
{
    return mg_store_canvas_missing_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history']);
}

function mg_merchant_canvas_active_customers(PDO $pdo, int $merchantUserId): array
{
    try {
        $pdo->prepare(
            "UPDATE mg_store_sessions
             SET status='idle',updated_at=NOW()
             WHERE merchant_user_id=?
               AND active_key IS NOT NULL
               AND status='active'
               AND last_active_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        )->execute([$merchantUserId]);
    } catch (Throwable $error) {
        mg_security_log('error', 'merchant_canvas.idle_update_failed', 'Store Canvas idle update failed.', ['exception_class'=>$error::class], $merchantUserId);
    }

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

try {
    mg_rate_limit('merchant_canvas.active_users', 'user:' . (int)$user['id'], 240, 60);

    $missing = mg_merchant_canvas_required_tables_missing($pdo);
    if ($missing !== []) {
        mg_fail('Store Canvas setup is incomplete. Missing: ' . implode(', ', $missing) . '. Run database/stage_20_agent_store_canvas.sql on the active database.', 503);
    }

    $customers = mg_merchant_canvas_active_customers($pdo, (int)$user['id']);
    mg_ok([
        'customers' => $customers,
        'summary' => [
            'active_customers' => count($customers),
            'agent_status' => 'Watching store canvas',
            'message_enabled' => true,
            'audit_mirror_enabled' => mg_store_canvas_table_exists($pdo, 'mg_agent_messages'),
        ],
    ]);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.active_users_failed', 'Merchant canvas active users failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load active customers.', 500);
}
