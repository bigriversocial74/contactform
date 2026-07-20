<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__) . '/bundles/_provider_reconciliation.php';

$pdo = mg_db();
$user = mg_authenticated_user();
if (!$user || (int)($user['id'] ?? 0) < 1) mg_fail('Sign in to continue.', 401);
if (!mg_admin_permission_user_has($user, 'commerce.manage') && !mg_admin_permission_user_has($user, 'admin')) mg_fail('Admin commerce access is required.', 403);
$actorId = (int)$user['id'];
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'POST' ? mg_input() : [];
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? 'summary')));

function mg_bundle_provider_schema(PDO $pdo): void
{
    foreach (['gift_bundle_settlement_transfers', 'gift_bundle_provider_events', 'gift_bundle_component_settlements'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Product Bundle provider reconciliation schema is not installed.');
    }
}

function mg_bundle_provider_summary(PDO $pdo): array
{
    $counts = $pdo->query("SELECT transfer_status,COUNT(*) total,COALESCE(SUM(amount_cents),0) amount_cents FROM gift_bundle_settlement_transfers GROUP BY transfer_status ORDER BY transfer_status")
        ->fetchAll(PDO::FETCH_ASSOC);
    $items = $pdo->query("SELECT t.public_id,t.provider_transfer_reference,t.amount_cents,t.currency,t.transfer_status,t.dispatch_attempt_count,t.next_dispatch_at,t.failure_code,t.failure_message,t.last_reconciled_at,t.created_at,
        s.public_id settlement_public_id,s.readiness_status,COALESCE(ms.display_name,u.display_name,u.email) merchant_name
        FROM gift_bundle_settlement_transfers t
        INNER JOIN gift_bundle_component_settlements s ON s.id=t.settlement_id
        INNER JOIN users u ON u.id=t.merchant_user_id
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
        ORDER BY FIELD(t.transfer_status,'failed','submitted','created','succeeded','reversed','cancelled'),t.created_at DESC LIMIT 500")
        ->fetchAll(PDO::FETCH_ASSOC);
    $events = $pdo->query("SELECT provider_event_reference,event_type,provider_transfer_reference,processing_status,failure_message,received_at,processed_at FROM gift_bundle_provider_events ORDER BY received_at DESC LIMIT 100")
        ->fetchAll(PDO::FETCH_ASSOC);
    return [
        'counts' => $counts,
        'items' => $items,
        'events' => $events,
        'dispatch_enabled' => mg_bundle_provider_dispatch_enabled(),
        'live_dispatch_enabled' => mg_bundle_provider_live_enabled(),
        'payment_mode' => mg_payment_mode(),
    ];
}

try {
    mg_bundle_provider_schema($pdo);
    if ($method === 'GET' && $action === 'summary') mg_ok(mg_bundle_provider_summary($pdo));
    if ($method === 'POST' && $action === 'reconcile') {
        mg_require_csrf_for_write($input);
        $publicId = trim((string)($input['transfer_id'] ?? ''));
        if ($publicId === '') throw new InvalidArgumentException('Transfer ID is required.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM gift_bundle_settlement_transfers WHERE public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$publicId]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transfer) throw new InvalidArgumentException('Transfer was not found.');
        if (trim((string)($transfer['provider_transfer_reference'] ?? '')) === '') throw new InvalidArgumentException('Transfer has not been dispatched to Stripe.');
        $pdo->commit();
        $provider = mg_bundle_provider_reconcile_reference($pdo, $transfer);
        mg_ok(['reconciled' => true, 'provider_transfer_reference' => $provider['id'] ?? null]);
    }
    mg_fail('Unsupported provider reconciliation operation.', 405);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error, 'bundle.provider.reconciliation.failure', 'Unable to process provider reconciliation.', 500, [], $actorId);
}
