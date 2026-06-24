<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery_failures.php';
mg_require_method('POST');
$input = mg_input();
$token = trim((string)($input['webhook_token'] ?? $_SERVER['HTTP_X_MICROGIFTER_WEBHOOK_TOKEN'] ?? ''));
$expected = trim((string)(getenv('MICROGIFTER_DELIVERY_WEBHOOK_TOKEN') ?: ''));
if ($expected !== '' && !hash_equals($expected, $token)) mg_fail('Invalid webhook token.', 403);
$accountUserId = max(1, (int)($input['account_user_id'] ?? 0));
if ($accountUserId < 1) mg_fail('account_user_id is required.', 422);
$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $result = mg_stamp_delivery_failure_void($pdo, $accountUserId, 0, $input);
    $pdo->commit();
    mg_audit('stamps.provider_delivery_failure_void', 'stamp_ledger', ['entry_id'=>$result['entry']['entry_id'] ?? null, 'account_user_id'=>$accountUserId, 'provider'=>$input['provider'] ?? null], null);
    mg_ok($result, $result['idempotent'] ? 'Delivery failure already processed.' : 'Delivery failure processed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.provider_delivery_failure_failed','Unable to process provider delivery failure.', ['exception_class'=>$error::class], null);
    mg_fail('Unable to process provider delivery failure.', 500);
}
