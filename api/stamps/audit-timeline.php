<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');

$purchaseId = trim((string)($_GET['purchase_id'] ?? $_GET['id'] ?? ''));
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);

function mg_stamp_timeline_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_stamp_timeline_json(?string $json): array
{
    if ($json === null || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_stamp_timeline_add(array &$events, string $type, string $label, ?string $timestamp, array $detail = [], string $severity = 'info'): void
{
    if ($timestamp === null || trim($timestamp) === '') return;
    $events[] = [
        'type'=>$type,
        'label'=>$label,
        'timestamp'=>$timestamp,
        'severity'=>$severity,
        'detail'=>$detail,
    ];
}

function mg_stamp_timeline_sort(array $events): array
{
    usort($events, static function(array $a, array $b): int {
        $at = strtotime((string)($a['timestamp'] ?? '')) ?: 0;
        $bt = strtotime((string)($b['timestamp'] ?? '')) ?: 0;
        if ($at === $bt) return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        return $at <=> $bt;
    });
    return $events;
}

$pdo = mg_db();
try {
    $purchase = mg_stamp_purchase_load_any($pdo, $purchaseId, false);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id']);
    $events = [];

    mg_stamp_timeline_add($events, 'purchase_created', 'Stamp purchase created', $purchase['created_at'] ?? null, [
        'purchase_id'=>(string)$purchase['public_id'],
        'account_user_id'=>(int)$purchase['account_user_id'],
        'bundle_key'=>(string)$purchase['bundle_key'],
        'stamps'=>(int)$purchase['stamps_snapshot'],
        'amount_cents'=>(int)$purchase['price_cents_snapshot'],
        'currency'=>(string)$purchase['currency_snapshot'],
        'status'=>(string)$purchase['status'],
    ], 'info');
    mg_stamp_timeline_add($events, 'purchase_updated', 'Stamp purchase last updated', $purchase['updated_at'] ?? null, ['status'=>(string)$purchase['status']], 'info');
    mg_stamp_timeline_add($events, 'purchase_paid', 'Stamp purchase marked paid', $purchase['paid_at'] ?? null, ['provider_reference'=>(string)($intent['provider_intent_reference'] ?? '')], 'success');
    mg_stamp_timeline_add($events, 'purchase_credited', 'Stamp ledger credit attached', $purchase['credited_at'] ?? null, ['ledger_entry_id'=>(string)($purchase['credited_ledger_entry_public_id'] ?? '')], 'success');

    if ($intent) {
        mg_stamp_timeline_add($events, 'payment_intent_created', 'Payment intent created', $intent['created_at'] ?? null, [
            'payment_intent_id'=>(string)($intent['public_id'] ?? ''),
            'provider_key'=>(string)($intent['provider_key'] ?? ''),
            'provider_intent_reference'=>(string)($intent['provider_intent_reference'] ?? ''),
            'amount_cents'=>(int)($intent['amount_cents'] ?? 0),
            'currency'=>(string)($intent['currency'] ?? ''),
            'status'=>(string)($intent['status'] ?? ''),
            'source_type'=>(string)($intent['source_type'] ?? ''),
            'source_reference'=>(string)($intent['source_reference'] ?? ''),
        ], 'info');
        mg_stamp_timeline_add($events, 'payment_intent_updated', 'Payment intent last updated', $intent['updated_at'] ?? null, [
            'status'=>(string)($intent['status'] ?? ''),
            'provider_intent_reference'=>(string)($intent['provider_intent_reference'] ?? ''),
            'failure_code'=>(string)($intent['failure_code'] ?? ''),
            'failure_message'=>(string)($intent['failure_message'] ?? ''),
        ], in_array((string)($intent['status'] ?? ''), ['failed','cancelled'], true) ? 'error' : 'info');
        mg_stamp_timeline_add($events, 'payment_authorized', 'Payment authorized', $intent['authorized_at'] ?? null, ['provider_intent_reference'=>(string)($intent['provider_intent_reference'] ?? '')], 'success');
        mg_stamp_timeline_add($events, 'payment_captured', 'Payment captured', $intent['captured_at'] ?? null, ['provider_intent_reference'=>(string)($intent['provider_intent_reference'] ?? '')], 'success');
    }

    if ($intent && mg_stamp_timeline_table_exists($pdo, 'checkout_sessions')) {
        $stmt = $pdo->prepare('SELECT * FROM checkout_sessions WHERE payment_intent_id=? ORDER BY created_at ASC,id ASC LIMIT 20');
        $stmt->execute([(int)$intent['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $session) {
            mg_stamp_timeline_add($events, 'checkout_session_created', 'Checkout session created', $session['created_at'] ?? null, [
                'checkout_session_id'=>(string)($session['public_id'] ?? ''),
                'provider_key'=>(string)($session['provider_key'] ?? ''),
                'provider_session_reference'=>(string)($session['provider_session_reference'] ?? ''),
                'status'=>(string)($session['status'] ?? ''),
                'expires_at'=>$session['expires_at'] ?? null,
            ], 'info');
            mg_stamp_timeline_add($events, 'checkout_session_completed', 'Checkout session completed', $session['completed_at'] ?? null, [
                'checkout_session_id'=>(string)($session['public_id'] ?? ''),
                'provider_session_reference'=>(string)($session['provider_session_reference'] ?? ''),
                'status'=>(string)($session['status'] ?? ''),
            ], 'success');
            mg_stamp_timeline_add($events, 'checkout_session_updated', 'Checkout session last updated', $session['updated_at'] ?? null, [
                'status'=>(string)($session['status'] ?? ''),
                'provider_session_reference'=>(string)($session['provider_session_reference'] ?? ''),
            ], (string)($session['status'] ?? '') === 'failed' ? 'error' : 'info');
        }
    }

    if (mg_stamp_timeline_table_exists($pdo, 'payment_webhook_events')) {
        $providerReference = trim((string)($intent['provider_intent_reference'] ?? ''));
        $likePurchase = '%' . (string)$purchase['public_id'] . '%';
        $likeProvider = $providerReference !== '' ? '%' . $providerReference . '%' : $likePurchase;
        $stmt = $pdo->prepare('SELECT * FROM payment_webhook_events WHERE payload_json LIKE ? OR payload_json LIKE ? ORDER BY received_at ASC,id ASC LIMIT 50');
        $stmt->execute([$likePurchase, $likeProvider]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $webhook) {
            $payload = mg_stamp_timeline_json((string)($webhook['payload_json'] ?? ''));
            mg_stamp_timeline_add($events, 'webhook_received', 'Webhook received: ' . (string)$webhook['event_type'], $webhook['received_at'] ?? null, [
                'provider_key'=>(string)$webhook['provider_key'],
                'provider_event_id'=>(string)$webhook['provider_event_id'],
                'event_type'=>(string)$webhook['event_type'],
                'status'=>(string)$webhook['status'],
                'signature_valid'=>(int)($webhook['signature_valid'] ?? 0) === 1,
                'object_id'=>(string)($payload['data']['object']['id'] ?? ''),
                'failure_message'=>(string)($webhook['failure_message'] ?? ''),
            ], in_array((string)$webhook['status'], ['failed'], true) ? 'error' : ((string)$webhook['status'] === 'processed' ? 'success' : 'warning'));
            mg_stamp_timeline_add($events, 'webhook_processed', 'Webhook processed: ' . (string)$webhook['event_type'], $webhook['processed_at'] ?? null, [
                'provider_event_id'=>(string)$webhook['provider_event_id'],
                'status'=>(string)$webhook['status'],
            ], (string)$webhook['status'] === 'processed' ? 'success' : 'warning');
        }
    }

    if (mg_stamp_timeline_table_exists($pdo, 'stamp_ledger_entries')) {
        $ledgerId = trim((string)($purchase['credited_ledger_entry_public_id'] ?? ''));
        $likePurchase = '%' . (string)$purchase['public_id'] . '%';
        $stmt = $pdo->prepare('SELECT * FROM stamp_ledger_entries WHERE public_id=? OR source_id=? OR metadata_json LIKE ? ORDER BY created_at ASC,id ASC LIMIT 20');
        $stmt->execute([$ledgerId, (string)$purchase['public_id'], $likePurchase]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ledger) {
            mg_stamp_timeline_add($events, 'ledger_entry', 'Stamp ledger entry posted', $ledger['created_at'] ?? null, [
                'ledger_entry_id'=>(string)($ledger['public_id'] ?? ''),
                'entry_type'=>(string)($ledger['entry_type'] ?? ''),
                'delta'=>(int)($ledger['delta'] ?? 0),
                'balance_after'=>(int)($ledger['balance_after'] ?? 0),
                'source_type'=>(string)($ledger['source_type'] ?? ''),
                'source_id'=>(string)($ledger['source_id'] ?? ''),
                'reason_code'=>(string)($ledger['reason_code'] ?? ''),
            ], 'success');
        }
    }

    if (mg_stamp_timeline_table_exists($pdo, 'audit_logs')) {
        $likePurchase = '%' . (string)$purchase['public_id'] . '%';
        $stmt = $pdo->prepare("SELECT user_id,action,entity_type,metadata_json,created_at FROM audit_logs WHERE metadata_json LIKE ? AND (action LIKE 'stamps.%' OR entity_type IN ('stamp_purchase','payment_webhook_event')) ORDER BY created_at ASC,id ASC LIMIT 80");
        $stmt->execute([$likePurchase]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $audit) {
            $meta = mg_stamp_timeline_json((string)($audit['metadata_json'] ?? ''));
            mg_stamp_timeline_add($events, 'audit_log', 'Audit: ' . (string)$audit['action'], $audit['created_at'] ?? null, [
                'actor_user_id'=>isset($audit['user_id']) ? (int)$audit['user_id'] : null,
                'action'=>(string)$audit['action'],
                'entity_type'=>(string)$audit['entity_type'],
                'metadata'=>$meta,
            ], str_contains((string)$audit['action'], 'failed') ? 'error' : 'info');
        }
    }

    mg_ok([
        'purchase'=>[
            'id'=>(string)$purchase['public_id'],
            'account_user_id'=>(int)$purchase['account_user_id'],
            'status'=>(string)$purchase['status'],
            'credited_ledger_entry_id'=>(string)($purchase['credited_ledger_entry_public_id'] ?? ''),
        ],
        'payment_intent'=>$intent,
        'timeline'=>mg_stamp_timeline_sort($events),
        'count'=>count($events),
        'read_only'=>true,
    ], 'Stamp audit timeline loaded.');
} catch (RuntimeException $error) {
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 409;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error','stamps.audit_timeline_failed','Stamp audit timeline failed.', ['purchase_id'=>$purchaseId,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load Stamp audit timeline.', 500);
}
