<?php
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';
$user = mg_refresh_session_user();
if (!$user) { header('Location: /signin.php'); exit; }
$id = trim((string)($_GET['id'] ?? ''));
$row = null; $error = '';
if ($id === '' || !preg_match('/^[0-9a-f-]{36}$/i', $id)) { $error = 'Receipt not found.'; }
else {
  $pdo = mg_db();
  $stmt = $pdo->prepare('SELECT * FROM scanner_redemption_receipts WHERE public_id=? LIMIT 1');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  if (!$row) $error = 'Receipt not found.';
  else {
    $uid = (int)$user['id'];
    $allowed = in_array($uid, [(int)$row['customer_user_id'], (int)$row['sender_user_id'], (int)$row['merchant_user_id'], (int)$row['scanner_user_id']], true) || mg_api_user_has_permission($user, 'admin.audit.view');
    if (!$allowed) $error = 'You do not have permission to view this receipt.';
  }
}
$page_title='Claim Receipt | Microgifter'; $page_section='agent'; $header_mode='agent';
require __DIR__ . '/includes/header.php';
?>
<main class="mg-app-shell" style="padding:42px 18px;background:#f8fbff;min-height:70vh"><aside class="mg-app-sidebar" hidden></aside>
  <section style="max-width:880px;margin:0 auto;background:#fff;border:1px solid #dbeafe;border-radius:24px;padding:28px;box-shadow:0 24px 70px rgba(15,23,42,.1)">
    <?php if ($error): ?>
      <h1><?= mg_e($error) ?></h1><p><a href="/claimed.php">Back to claimed gifts</a></p>
    <?php else: ?>
      <p style="font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:#2563eb;font-weight:900">Microgifter redemption receipt</p>
      <h1>Receipt <?= mg_e((string)$row['public_id']) ?></h1>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:20px">
        <p><strong>Gift</strong><br><?= mg_e((string)$row['gift_public_id']) ?></p>
        <p><strong>Value</strong><br><?= mg_e(strtoupper((string)$row['currency']) . ' ' . number_format(((int)$row['amount_cents'])/100,2)) ?></p>
        <p><strong>Location</strong><br><?= mg_e((string)$row['location_name']) ?></p>
        <p><strong>Claim code</strong><br>Ending <?= mg_e((string)($row['claim_code_last4'] ?: '••••')) ?></p>
        <p><strong>Status</strong><br><?= mg_e((string)$row['status']) ?></p>
        <p><strong>Redeemed at</strong><br><?= mg_e((string)$row['redeemed_at']) ?></p>
      </div>
      <p><a href="/claimed.php">Back to claimed gifts</a></p>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>