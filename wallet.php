<?php
declare(strict_types=1);
if (isset($_GET['classic'])) { require __DIR__ . '/wallet-classic.php'; exit; }
require_once __DIR__ . '/includes/app.php';
$page_title = 'My Reward Wallet | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'wallet';
$page_styles = ['/assets/css/reward-wallet-experience.css'];
$page_scripts = ['/assets/js/reward-wallet-experience.js'];
$page_meta = ['description'=>'View, claim, redeem, and get support for rewards in your Microgifter wallet.','robots'=>'noindex, nofollow'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-reward-wallet-shell">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-reward-wallet-page" data-reward-wallet>
    <section class="mg-reward-wallet-hero">
      <div><span class="mg-eyebrow">Microgifter Wallet</span><h1>Your rewards, ready when you are.</h1><p>Open reward details, generate a secure claim code, track redemption, and contact the merchant without leaving your wallet.</p></div>
      <div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="/quests.php">Explore Loyalty Quests</a><a class="mg-btn mg-btn-ghost" href="/wallet.php?classic=1">Classic wallet</a></div>
    </section>
    <section class="mg-campaign-kpis" aria-label="Wallet metrics"><article><span>Available</span><strong data-wallet-kpi-available>—</strong><small>Ready to claim</small></article><article><span>Claimed</span><strong data-wallet-kpi-claimed>—</strong><small>Waiting for redemption</small></article><article><span>Redeemed</span><strong data-wallet-kpi-redeemed>—</strong><small>Completed rewards</small></article><article><span>Expired</span><strong data-wallet-kpi-expired>—</strong><small>No longer available</small></article></section>
    <section class="mg-app-panel mg-reward-wallet-command">
      <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Reward Library</span><h2>Wallet items</h2><p>Search by reward, merchant, or campaign and filter by lifecycle state.</p></div></div>
      <div class="mg-app-panel-body">
        <form class="mg-reward-wallet-toolbar" data-wallet-filters role="search"><label><span>Search</span><input name="q" type="search" maxlength="180" placeholder="Reward, merchant, or campaign"></label><label><span>Status</span><select name="state" data-wallet-state><option value="all">All rewards</option><option value="available">Available</option><option value="claimed">Claimed</option><option value="redeemed">Redeemed</option><option value="expired">Expired</option><option value="cancelled">Cancelled</option></select></label><button class="mg-btn mg-btn-primary" type="submit">Apply</button><button class="mg-btn mg-btn-ghost" type="button" data-wallet-clear>Clear</button></form>
        <div class="mg-form-status" data-wallet-status role="status" aria-live="polite">Loading rewards…</div>
        <div class="mg-reward-wallet-list" data-wallet-list></div>
        <button class="mg-btn mg-btn-soft mg-wallet-load-more" type="button" data-wallet-load-more hidden>Load more</button>
      </div>
    </section>
  </main>
</section>
<dialog class="mg-reward-dialog" data-reward-dialog><div class="mg-reward-dialog-card"><button type="button" data-reward-dialog-close aria-label="Close reward details">×</button><div data-reward-detail></div></div></dialog>
<dialog class="mg-reward-dialog" data-support-dialog><form class="mg-reward-dialog-card" data-support-form><button type="button" data-support-dialog-close aria-label="Close support form">×</button><span class="mg-eyebrow">Reward Support</span><h2>Tell us what went wrong.</h2><input type="hidden" name="reward_id"><label>Issue type<select name="category"><option value="claim_code">Claim code</option><option value="merchant_redemption">Merchant redemption</option><option value="reward_missing">Reward missing</option><option value="expired_reward">Expired reward</option><option value="wrong_reward">Wrong reward</option><option value="regift">Regift</option><option value="other">Other</option></select></label><label>Subject<input name="subject" required maxlength="180"></label><label>Message<textarea name="message" required minlength="10" maxlength="5000" rows="6"></textarea></label><div class="mg-form-status" data-support-status role="status" aria-live="polite"></div><button class="mg-btn mg-btn-primary" type="submit">Create support case</button></form></dialog>
<?php require __DIR__ . '/includes/footer.php';
