<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_manual_operations.php';

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
    mg_rate_limit('merchant_canvas.customer_crm_update', 'user:' . $merchantUserId, 120, 60);

    $pdo->beginTransaction();
    $session = mg_store_manual_ops_session($pdo, $merchantUserId, $sessionId, true);
    $customerUserId = (int)($session['customer_user_id'] ?? 0);
    if ($customerUserId < 1 || $customerUserId === $merchantUserId) {
        throw new RuntimeException('This Store Canvas session is not connected to a customer account.');
    }

    $crm = mg_store_manual_ops_crm_save(
        $pdo,
        $merchantUserId,
        $customerUserId,
        $merchantUserId,
        $input['notes'] ?? '',
        $input['tags'] ?? [],
        $input['do_not_message'] ?? false
    );

    mg_store_log_event($pdo, $session, 'merchant_crm_updated', 'Merchant updated customer CRM', [
        'tags' => $crm['tags'],
        'do_not_message' => $crm['do_not_message'],
        'updated_by_user_id' => $merchantUserId,
        'source_channel' => 'merchant_canvas',
    ]);
    $pdo->commit();

    mg_ok(['crm' => $crm], 'Customer CRM safeguards saved.');
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = str_contains(strtolower($error->getMessage()), 'setup is incomplete') ? 503 : 400;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant_canvas.customer_crm_update_failed', 'Merchant Canvas customer CRM update failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to save customer CRM safeguards.', 500);
}
