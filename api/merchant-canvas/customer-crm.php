<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_merchant_canvas_crm_metadata(array $session): array
{
    $raw = (string)($session['metadata_json'] ?? '');
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_merchant_canvas_customer_crm_core(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');

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
    if (!$session) {
        throw new RuntimeException('Customer session is not available.');
    }

    $metadata = mg_merchant_canvas_crm_metadata($session);
    $isTest = !empty($metadata['test_canvas_avatar']) || (($metadata['source'] ?? '') === 'merchant_canvas_test_seed');
    $customerName = trim((string)($session['customer_name'] ?? ''));
    if ($customerName === '') {
        $customerName = trim((string)($metadata['customer_name'] ?? '')) ?: 'Customer';
    }
    $customerAvatar = mg_store_avatar_url($session['customer_avatar_url'] ?? null) ?: mg_store_avatar_url($metadata['customer_avatar_url'] ?? null);
    $customerProfileType = (string)($session['customer_profile_type'] ?: ($isTest ? 'test_customer' : 'customer'));
    $sessionProjection = mg_store_project_session($session);
    if (is_array($sessionProjection) && empty($sessionProjection['source_post']['headline']) && isset($metadata['source_label'])) {
        $sessionProjection['source_post']['headline'] = (string)$metadata['source_label'];
    }

    $stats = ['visit_count'=>0,'messages_sent'=>0,'rewards_received'=>0,'rewards_claimed'=>0,'gifts_sent'=>0];
    $visit = $pdo->prepare('SELECT COUNT(*) FROM mg_store_sessions WHERE customer_user_id=? AND merchant_user_id=?');
    $visit->execute([(int)$session['customer_user_id'], $merchantUserId]);
    $stats['visit_count'] = (int)$visit->fetchColumn();

    if (mg_store_canvas_table_exists($pdo, 'mg_agent_messages')) {
        $messages = $pdo->prepare('SELECT COUNT(*) FROM mg_agent_messages WHERE recipient_user_id=? AND merchant_user_id=?');
        $messages->execute([(int)$session['customer_user_id'], $merchantUserId]);
        $stats['messages_sent'] = (int)$messages->fetchColumn();
    }

    $eventStats = $pdo->prepare('SELECT event_type,COUNT(*) total FROM mg_store_session_events WHERE customer_user_id=? AND merchant_user_id=? GROUP BY event_type');
    $eventStats->execute([(int)$session['customer_user_id'], $merchantUserId]);
    foreach ($eventStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string)$row['event_type'];
        if ($type === 'message_received') $stats['messages_sent'] = max($stats['messages_sent'], (int)$row['total']);
        if ($type === 'received_reward') $stats['rewards_received'] = (int)$row['total'];
        if ($type === 'claimed_reward') $stats['rewards_claimed'] = (int)$row['total'];
        if ($type === 'sent_gift') $stats['gifts_sent'] = (int)$row['total'];
    }

    $events = [];
    $eventsStmt = $pdo->prepare('SELECT event_type,event_label,created_at FROM mg_store_session_events WHERE store_session_id=? ORDER BY id DESC LIMIT 20');
    $eventsStmt->execute([(int)$session['id']]);
    foreach ($eventsStmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $events[] = [
            'type' => (string)$event['event_type'],
            'label' => $event['event_label'] !== null ? (string)$event['event_label'] : null,
            'created_at' => (string)$event['created_at'],
        ];
    }

    return [
        'session' => $sessionProjection,
        'customer' => [
            'name' => $customerName,
            'slug' => (string)($session['customer_slug'] ?? ''),
            'avatar_url' => $customerAvatar,
            'profile_type' => $customerProfileType,
            'account_status' => $isTest ? 'Test avatar' : 'In system',
        ],
        'stats' => $stats,
        'events' => $events,
        'actions' => ['send_direct_message'=>true,'send_reward'=>false,'invite_campaign'=>false,'remove_from_store'=>false],
    ];
}

try {
    $sessionId = mg_store_safe_public_id($_GET['session_id'] ?? '', 'Store session');
    mg_rate_limit('merchant_canvas.customer_crm', 'user:' . (int)$user['id'], 240, 60);
    mg_ok(mg_merchant_canvas_customer_crm_core($pdo, (int)$user['id'], $sessionId));
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.customer_crm_failed', 'Merchant canvas customer CRM failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load customer CRM.', 500);
}
