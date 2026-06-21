<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/db.php';

$code = trim((string)($_GET['code'] ?? ''));
$user = mg_current_user();
$linkRequest = null;
$error = '';

if ($code === '' || strlen($code) < 20) {
    $error = 'This account link is invalid or incomplete.';
} else {
    try {
        $stmt = mg_db()->prepare("SELECT dalr.public_id,dalr.external_user_id,dalr.return_url,dalr.state,dalr.status,dalr.expires_at,mda.name AS app_name,mda.environment,mda.status AS app_status,u.display_name AS merchant_name,u.full_name AS merchant_full_name,u.email AS merchant_email FROM developer_app_link_requests dalr INNER JOIN merchant_developer_apps mda ON mda.id=dalr.app_id INNER JOIN users u ON u.id=dalr.merchant_user_id WHERE dalr.link_code_hash=? LIMIT 1");
        $stmt->execute([hash('sha256', $code)]);
        $linkRequest = $stmt->fetch();
        if (!$linkRequest) {
            $error = 'This account link was not found.';
        } elseif ((string)$linkRequest['status'] !== 'pending') {
            $error = 'This account link has already been used or cancelled.';
        } elseif (!empty($linkRequest['expires_at']) && strtotime((string)$linkRequest['expires_at']) < time()) {
            $error = 'This account link has expired.';
        } elseif ((string)$linkRequest['app_status'] !== 'active') {
            $error = 'This developer app is not active.';
        }
    } catch (Throwable $e) {
        $error = 'This account link cannot be loaded right now.';
    }
}

$page_title = 'Connect Account | Microgifter';
$page_section = 'account-link';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
require __DIR__ . '/includes/header.php';
?>
<style>
.mg-link-page{min-height:100vh;background:linear-gradient(180deg,#fff,#f7faff);color:#071225}.mg-link-wrap{width:min(760px,92%);margin:0 auto;padding:84px 0}.mg-link-card{border:1px solid #dce7f4;border-radius:24px;background:#fff;padding:34px;box-shadow:0 22px 60px rgba(15,23,42,.08)}.mg-link-card h1{margin:0;font-size:clamp(34px,5vw,56px);line-height:1;letter-spacing:-.06em}.mg-link-card p{color:#5f7088;font-size:16px;line-height:1.6}.mg-link-facts{display:grid;gap:10px;margin:22px 0}.mg-link-fact{display:flex;justify-content:space-between;gap:16px;border:1px solid #e2ebf6;border-radius:14px;padding:13px;background:#f8fbff}.mg-link-fact span{color:#60728c;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.mg-link-fact strong{font-size:14px;text-align:right}.mg-link-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}.mg-link-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border-radius:12px;border:1px solid #d8e4f4;background:#fff;color:#071225;font-weight:900;text-decoration:none;cursor:pointer}.mg-link-primary{background:#195bd7;color:#fff;border-color:#195bd7}.mg-link-danger{background:#fff;color:#9b1c1c}.mg-link-status{margin-top:14px;color:#53647b;font-size:14px}.mg-link-error{border:1px solid #fecaca;background:#fff7f7;color:#9b1c1c;border-radius:14px;padding:14px;margin-top:18px}@media(max-width:620px){.mg-link-card{padding:24px}.mg-link-fact{display:block}.mg-link-fact strong{text-align:left;display:block;margin-top:5px}}
</style>
<main class="mg-link-page">
 <div class="mg-link-wrap">
  <section class="mg-link-card">
   <?php if($error !== ''): ?>
    <h1>Account link unavailable</h1>
    <div class="mg-link-error"><?= mg_e($error) ?></div>
    <div class="mg-link-actions"><a class="mg-link-btn" href="/index.php">Back to Microgifter</a></div>
   <?php else: ?>
    <h1>Connect your Microgifter account</h1>
    <p><strong><?= mg_e((string)$linkRequest['app_name']) ?></strong> wants permission to send approved Microgift rewards to your Microgifter INBOX.</p>
    <div class="mg-link-facts">
     <div class="mg-link-fact"><span>Developer app</span><strong><?= mg_e((string)$linkRequest['app_name']) ?></strong></div>
     <div class="mg-link-fact"><span>Merchant</span><strong><?= mg_e((string)($linkRequest['merchant_name'] ?: $linkRequest['merchant_full_name'] ?: $linkRequest['merchant_email'])) ?></strong></div>
     <div class="mg-link-fact"><span>External user</span><strong><?= mg_e((string)$linkRequest['external_user_id']) ?></strong></div>
    </div>
    <?php if(!$user): ?>
     <p>Sign in or create a Microgifter account to approve this connection.</p>
     <div class="mg-link-actions"><a class="mg-link-btn mg-link-primary" href="/signin.php?return=<?= rawurlencode('/account-link.php?code='.$code) ?>">Sign in to continue</a></div>
    <?php else: ?>
     <p>You are signed in as <strong><?= mg_e(mg_user_display_name()) ?></strong>. Approving this connection lets the app send rewards to your Microgifter INBOX.</p>
     <form method="post" action="/api/public/v1/account-link-complete.php" class="mg-link-actions">
      <?= mg_csrf_field() ?>
      <input type="hidden" name="code" value="<?= mg_e($code) ?>">
      <button class="mg-link-btn mg-link-primary" name="action" value="approve" type="submit">Approve connection</button>
      <button class="mg-link-btn mg-link-danger" name="action" value="cancel" type="submit">Cancel</button>
     </form>
     <div class="mg-link-status">After approval, you will be redirected back to the developer app.</div>
    <?php endif; ?>
   <?php endif; ?>
  </section>
 </div>
</main>
<?php require __DIR__ . '/includes/footer.php';
