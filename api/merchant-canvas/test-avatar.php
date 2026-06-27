<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_canvas_test_avatar_name(mixed $value): string
{
    $name = trim((string)$value);
    $name = preg_replace('/\s+/u', ' ', $name) ?? '';
    if ($name === '') {
        $pool = ['Avery Reed','Jordan Miles','Taylor Brooks','Morgan Lane','Casey Wright','Riley Stone'];
        $name = $pool[random_int(0, count($pool) - 1)];
    }
    return mb_substr($name, 0, 120);
}

function mg_canvas_test_avatar_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '') {
        return null;
    }
    return mg_store_avatar_url($url);
}

try {
    mg_rate_limit('merchant_canvas.test_avatar', 'user:' . (int)$user['id'], 60, 60);
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');

    $merchantUserId = (int)$user['id'];
    $action = strtolower(trim((string)($input['action'] ?? 'add')));

    if ($action === 'clear') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM mg_store_sessions
                 WHERE merchant_user_id=?
                   AND active_key IS NOT NULL
                   AND status IN ('entered','active','idle')
                   AND metadata_json LIKE '%merchant_canvas_test_seed%'
                 LIMIT 100 FOR UPDATE"
            );
            $stmt->execute([$merchantUserId]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sessions as $session) {
                mg_store_close_session_row($pdo, $session, 'system');
            }
            $pdo->commit();
            mg_ok(['closed' => count($sessions)], 'Test avatars cleared.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return;
    }

    $name = mg_canvas_test_avatar_name($input['name'] ?? '');
    $avatarUrl = mg_canvas_test_avatar_url($input['avatar_url'] ?? '');
    $customerUserId = (int)(time() * 1000 + random_int(100, 999));
    $publicId = mg_public_uuid();
    $metadata = [
        'source' => 'merchant_canvas_test_seed',
        'test_canvas_avatar' => true,
        'customer_name' => $name,
        'customer_avatar_url' => $avatarUrl,
        'source_label' => 'Seeded test avatar',
        'created_by_user_id' => $merchantUserId,
    ];

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            "INSERT INTO mg_store_sessions
             (public_id,customer_user_id,merchant_user_id,source_feed_post_id,status,active_key,entered_at,last_active_at,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?, 'active', ?, NOW(), NOW(), ?, NOW(), NOW())"
        );
        $insert->execute([
            $publicId,
            $customerUserId,
            $merchantUserId,
            null,
            $customerUserId,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $sessionId = (int)$pdo->lastInsertId();
        $session = [
            'id' => $sessionId,
            'public_id' => $publicId,
            'customer_user_id' => $customerUserId,
            'merchant_user_id' => $merchantUserId,
            'source_feed_post_id' => null,
            'status' => 'active',
            'active_key' => $customerUserId,
            'entered_at' => date('Y-m-d H:i:s'),
            'last_active_at' => date('Y-m-d H:i:s'),
        ];
        mg_store_log_event($pdo, $session, 'entered_store', 'Test avatar entered Store Canvas', ['source'=>'merchant_canvas_test_seed']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_event('store_canvas.test_avatar_created', ['session_id'=>$publicId,'customer_name'=>$name], $merchantUserId);
    mg_ok([
        'session_id' => $publicId,
        'customer' => [
            'name' => $name,
            'avatar_url' => $avatarUrl,
            'profile_type' => 'test_customer',
        ],
    ], 'Test avatar added to the Store Canvas.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.test_avatar_failed', 'Merchant canvas test avatar action failed.', ['exception_class'=>$error::class,'exception'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to update test avatars.', 500);
}
