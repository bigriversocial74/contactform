<?php
declare(strict_types=1);

require_once __DIR__ . '/_health.php';
require_once __DIR__ . '/_renewals.php';
require_once __DIR__ . '/_purchases.php';
require_once __DIR__ . '/_delivery_failures.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.manage') && !mg_api_user_has_permission($user, 'admin.commerce.manage') && !mg_api_user_has_permission($user, 'admin.settings.manage')) {
    mg_fail('Permission denied.', 403);
}

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = $method === 'POST' ? mg_input() : $_GET;
$action = strtolower(trim((string)($input['action'] ?? 'health')));

function mg_stamp_test_response(string $step, bool $ok, string $message, array $data = []): array
{
    return ['step' => $step, 'ok' => $ok, 'message' => $message, 'data' => $data, 'checked_at' => gmdate('c')];
}

function mg_stamp_test_account_id(array $input): int
{
    $id = max(0, (int)($input['account_user_id'] ?? $input['merchant_user_id'] ?? 0));
    if ($id < 1) mg_fail('Merchant account user ID is required for this test.', 422);
    return $id;
}

try {
    if ($method === 'POST') mg_require_csrf_for_write($input);

    if ($action === 'health') {
        $health = mg_stamp_system_health($pdo);
        mg_ok(mg_stamp_test_response('health', !empty($health['ok']), !empty($health['ok']) ? 'Stamp health is green.' : 'Stamp health needs attention.', $health));
    }

    if ($action === 'bundles') {
        $bundles = mg_stamp_bundle_rows($pdo);
        mg_ok(mg_stamp_test_response('bundles', count($bundles) > 0, count($bundles) > 0 ? 'Stamp bundles loaded.' : 'No active Stamp bundles found.', ['bundles' => $bundles]));
    }

    if ($action === 'balance') {
        $accountUserId = mg_stamp_test_account_id($input);
        mg_ok(mg_stamp_test_response('balance', true, 'Merchant Stamp balance loaded.', mg_stamp_ledger_payload($pdo, $accountUserId)));
    }

    if ($action === 'assign_package') {
        $accountUserId = mg_stamp_test_account_id($input);
        $packageId = strtolower(trim((string)($input['package_id'] ?? 'starter')));
        if (mg_stamp_plan_allowance($packageId) < 1 && $packageId !== 'enterprise') mg_fail('Unknown package for test runner.', 422);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE account_package_assignments SET status='archived',updated_at=NOW() WHERE account_user_id=? AND status='active'")->execute([$accountUserId]);
        $assignmentId = mg_public_uuid();
        $pdo->prepare('INSERT INTO account_package_assignments (public_id,account_user_id,package_id,status,started_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,NOW(),JSON_OBJECT("source","stamp_test_runner"),NOW(),NOW())')
            ->execute([$assignmentId,$accountUserId,$packageId,'active']);
        $pdo->commit();
        mg_ok(mg_stamp_test_response('assign_package', true, 'Package assignment saved for test merchant.', ['assignment_id'=>$assignmentId,'account_user_id'=>$accountUserId,'package_id'=>$packageId,'monthly_stamps_included'=>mg_stamp_plan_allowance($packageId)]), 'Package assignment saved.', 201);
    }

    if ($action === 'renewal_preview') {
        $period = trim((string)($input['period'] ?? ''));
        $result = mg_stamp_monthly_renewal_preview($pdo, $period, 500);
        mg_ok(mg_stamp_test_response('renewal_preview', true, 'Monthly renewal preview generated.', $result));
    }

    if ($action === 'renewal_run') {
        $period = trim((string)($input['period'] ?? ''));
        $pdo->beginTransaction();
        $result = mg_stamp_run_monthly_renewals($pdo, (int)$user['id'], $period, 500, false);
        $pdo->commit();
        mg_ok(mg_stamp_test_response('renewal_run', true, 'Monthly renewal run completed.', $result), 'Monthly renewal run completed.', 201);
    }

    if ($action === 'purchase_sandbox') {
        $accountUserId = mg_stamp_test_account_id($input);
        $bundleKey = trim((string)($input['bundle_key'] ?? 'stamps_1000'));
        $stmt = $pdo->prepare('SELECT * FROM stamp_bundles WHERE bundle_key=? AND status=? LIMIT 1 FOR UPDATE');
        $pdo->beginTransaction();
        $stmt->execute([$bundleKey, 'active']);
        $bundle = $stmt->fetch();
        if (!$bundle) throw new RuntimeException('Test Stamp bundle not found.');
        $purchaseId = mg_public_uuid();
        $checkoutReference = 'stamp-test:' . $purchaseId;
        $idempotencyKey = 'stamp-test-purchase-' . $purchaseId;
        $pdo->prepare('INSERT INTO stamp_purchases (public_id,account_user_id,bundle_id,bundle_key,label_snapshot,stamps_snapshot,price_cents_snapshot,currency_snapshot,status,checkout_reference,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,JSON_OBJECT("source","stamp_test_runner"),NOW(),NOW())')
            ->execute([$purchaseId,$accountUserId,(int)$bundle['id'],(string)$bundle['bundle_key'],(string)$bundle['label'],(int)$bundle['stamps'],(int)$bundle['price_cents'],(string)$bundle['currency'],'checkout_created',$checkoutReference,$idempotencyKey]);
        $purchase = mg_stamp_purchase_load($pdo, $accountUserId, $purchaseId, '', true);
        $result = mg_stamp_purchase_complete($pdo, $purchase, (int)$user['id'], 'sandbox_paid', 'test-runner');
        $pdo->commit();
        mg_ok(mg_stamp_test_response('purchase_sandbox', true, 'Sandbox Stamp purchase created and credited.', $result), 'Sandbox Stamp purchase credited.', 201);
    }

    if ($action === 'test_debit') {
        $accountUserId = mg_stamp_test_account_id($input);
        $actionKey = trim((string)($input['action_key'] ?? 'campaign_feed_send'));
        $quantity = max(1, min(100, (int)($input['quantity'] ?? 1)));
        $sendKey = 'stamp-test-debit-' . mg_public_uuid();
        $pdo->beginTransaction();
        $result = mg_stamp_debit_send($pdo, $accountUserId, (int)$user['id'], $actionKey, $sendKey, ['quantity'=>$quantity,'actor_type'=>'admin','source_type'=>'stamp_test_runner','source_id'=>$sendKey,'reference'=>'browser_test']);
        $pdo->commit();
        mg_ok(mg_stamp_test_response('test_debit', true, 'Test debit recorded.', $result), 'Test debit recorded.', 201);
    }

    if ($action === 'delivery_failure') {
        $accountUserId = mg_stamp_test_account_id($input);
        $entryId = trim((string)($input['entry_id'] ?? $input['stamp_ledger_entry_id'] ?? ''));
        if ($entryId === '') mg_fail('Debit ledger entry ID is required.', 422);
        $pdo->beginTransaction();
        $result = mg_stamp_delivery_failure_void($pdo, $accountUserId, (int)$user['id'], ['entry_id'=>$entryId,'provider'=>'test_runner','event_id'=>'runner-' . $entryId,'failure_code'=>'test_delivery_failure','failure_message'=>'Browser test delivery failure return.']);
        $pdo->commit();
        mg_ok(mg_stamp_test_response('delivery_failure', true, 'Delivery failure return recorded.', $result), 'Delivery failure return recorded.', 201);
    }

    mg_fail('Unknown Stamp test runner action.', 404);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.test_runner_failed','Stamp test runner failed.', ['action'=>$action,'exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage() ?: 'Stamp test runner failed.', 500);
}
