<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$rewardId = strtolower(trim((string)($_GET['reward'] ?? $_GET['id'] ?? '')));
$rewardId = preg_replace('/[^a-f0-9-]+/','',$rewardId) ?? '';
$page_title = 'Reward Details | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'wallet';
$page_styles = ['/assets/css/reward-wallet-experience.css'];
$page_scripts = ['/assets/js/reward-wallet-experience.js'];
$page_meta = ['description'=>'View claim, redemption, merchant, and support details for a Microgifter reward.','robots'=>'noindex, nofollow'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-reward-wallet-shell">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-reward-detail-page" data-reward-wallet data-reward-id="<?= mg_e($rewardId) ?>">
    <div class="mg-reward-detail-back"><a href="/wallet.php">← Back to wallet</a></div>
    <div class="mg-form-status" data-wallet-status role="status" aria-live="polite">Loading reward…</div>
    <section class="mg-reward-detail-standalone" data-reward-detail></section>
  </main>
</section>
<dialog class="mg-reward-dialog" data-support-dialog><form class="mg-reward-dialog-card" data-support-form><button type="button" data-support-dialog-close aria-label="Close support form">×</button><span class="mg-eyebrow">Reward Support</span><h2>Tell us what went wrong.</h2><input type="hidden" name="reward_id"><label>Issue type<select name="category"><option value="claim_code">Claim code</option><option value="merchant_redemption">Merchant redemption</option><option value="reward_missing">Reward missing</option><option value="expired_reward">Expired reward</option><option value="wrong_reward">Wrong reward</option><option value="regift">Regift</option><option value="other">Other</option></select></label><label>Subject<input name="subject" required maxlength="180"></label><label>Message<textarea name="message" required minlength="10" maxlength="5000" rows="6"></textarea></label><div class="mg-form-status" data-support-status role="status" aria-live="polite"></div><button class="mg-btn mg-btn-primary" type="submit">Create support case</button></form></dialog>
<?php require __DIR__ . '/includes/footer.php';
