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

try {
    $merchantUserId = (int)$user['id'];
    $sessionId = mg_store_safe_public_id($input['session_id'] ?? '', 'Store session');
    $note = trim((string)($input['note'] ?? ''));
    if (mb_strlen($note) > 1000) throw new InvalidArgumentException('Note is too long.');
    mg_rate_limit('merchant_canvas.callback', 'user:' . $merchantUserId, 90, 60);
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');

    $stmt = $pdo->prepare('SELECT * FROM mg_store_sessions WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$sessionId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Customer session is not available.');

    mg_store_log_event($pdo, $session, 'callback_marked', 'Marked for merchant callback', ['source_system'=>'store_canvas','note'=>$note ?: null]);
    mg_event('store_canvas.callback_marked', ['session_id'=>$sessionId,'customer_user_id'=>(int)$session['customer_user_id']], $merchantUserId);
    mg_ok(['session_id'=>$sessionId], 'Customer callback marked.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.callback_failed', 'Store Canvas callback failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to mark callback.', 500);
}
