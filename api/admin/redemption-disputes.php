<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/merchant/_redemption_finance.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_permission('admin.audit.view');
$pdo = mg_db();

if ($method === 'GET') {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $stmt = $pdo->query('SELECT d.*, l.settlement_status, l.merchant_net_cents FROM redemption_disputes d LEFT JOIN redemption_settlement_ledger l ON l.receipt_public_id=d.receipt_public_id ORDER BY d.created_at DESC, d.id DESC LIMIT ' . $limit);
    mg_ok(['disputes' => $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []], 'Admin redemption disputes loaded.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$disputeId = trim((string)($input['dispute_id'] ?? ''));
$status = trim((string)($input['status'] ?? 'admin_review'));
$note = trim((string)($input['admin_notes'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $disputeId)) mg_fail('Choose a dispute.', 422);
$stmt = $pdo->prepare('SELECT * FROM redemption_disputes WHERE public_id=? LIMIT 1');
$stmt->execute([$disputeId]);
$dispute = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dispute) mg_fail('Dispute not found.', 404);
$result = mg_redemption_finance_apply_dispute_status($pdo, $dispute, (int)$user['id'], $status, $note);
mg_ok(['dispute' => $result], 'Redemption dispute updated.');
