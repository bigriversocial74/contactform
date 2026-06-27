<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';
require_once __DIR__ . '/_redemption_finance.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_permission('merchant.gifts.redeem');
$pdo = mg_db();
$merchantUserId = (int)$user['id'];

if ($method === 'GET') {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $stmt = $pdo->prepare('SELECT * FROM redemption_disputes WHERE merchant_user_id=? ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
    $stmt->execute([$merchantUserId]);
    mg_ok(['disputes' => $stmt->fetchAll(PDO::FETCH_ASSOC)], 'Redemption disputes loaded.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$receiptId = trim((string)($input['receipt_id'] ?? ''));
$type = trim((string)($input['dispute_type'] ?? 'merchant_void'));
$reason = trim((string)($input['reason'] ?? 'Merchant requested redemption review.'));
if (!preg_match('/^[0-9a-f-]{36}$/i', $receiptId)) mg_fail('Choose a redemption receipt.', 422);
$stmt = $pdo->prepare('SELECT * FROM scanner_redemption_receipts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
$stmt->execute([$receiptId, $merchantUserId]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receipt) mg_fail('Receipt not found for this merchant.', 404);
$dispute = mg_redemption_finance_open_dispute($pdo, $receipt, $merchantUserId, $type, $reason);
mg_ok(['dispute' => $dispute], 'Redemption dispute opened.');
