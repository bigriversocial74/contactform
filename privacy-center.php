<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Privacy & Data Center | Microgifter';
$page_section='account';
$header_mode='account';
$page_body_class='mg-privacy-center-page';
$page_styles=['/assets/css/privacy-center-v1.css?v=1.0.0'];
$page_scripts=['/assets/js/privacy-center-v1.js?v=1.0.0'];
$user=mg_current_user();
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app" data-privacy-center data-authenticated="<?= $user?'true':'false' ?>">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-privacy-center-main">
    <?php if (!$user): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-head"><div><span class="mg-privacy-eyebrow">Privacy &amp; Data Center</span><h1>Sign in to manage your data.</h1><p>Submit a verified privacy request, review its status, or contact Microgifter Privacy.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php?return_to=%2Fprivacy-center.php">Sign in</a></div></section>
    <?php else: ?>
      <header class="mg-privacy-hero">
        <div><span class="mg-privacy-eyebrow">Privacy &amp; Data Center</span><h1>Control your account and personal data.</h1><p>Review Microgifter’s retention approach and submit a verified account-erasure request. Merchant-controlled CRM records are routed to the applicable merchant for review while Microgifter removes or anonymizes platform data.</p></div>
        <div class="mg-privacy-links"><a href="/privacy.php">Privacy Policy</a><a href="/cookies.php">Cookie Settings</a><a href="mailto:admin@microgifter.com">Contact Privacy</a></div>
      </header>

      <section class="mg-privacy-grid">
        <article class="mg-privacy-card">
          <span class="mg-privacy-card-kicker">Current status</span>
          <h2>Privacy request</h2>
          <div data-privacy-request-status><p class="mg-muted">Loading request status…</p></div>
        </article>
        <article class="mg-privacy-card">
          <span class="mg-privacy-card-kicker">What happens</span>
          <h2>Controlled closure, not a blind database delete</h2>
          <ol class="mg-privacy-steps"><li><strong>Identity verification</strong><span>Your current password confirms account ownership.</span></li><li><strong>Immediate restriction</strong><span>Sign-in, sessions, recovery credentials, public profiles, agents, and new activity are stopped.</span></li><li><strong>Controller review</strong><span>Merchant-owned CRM records are sent to the applicable merchant review queue.</span></li><li><strong>Erase or anonymize</strong><span>Private data is deleted. Required commerce and gift evidence is retained only in minimized, anonymized form.</span></li></ol>
        </article>
      </section>

      <section class="mg-privacy-danger" data-privacy-delete-panel>
        <header><div><span class="mg-privacy-card-kicker">Account erasure</span><h2>Close and delete my account</h2><p>This action immediately signs you out and disables the account. After the applicable review/grace period, direct identifiers are irreversibly erased or anonymized unless a legal hold or required retention exception applies.</p></div><span class="mg-privacy-danger-badge">Irreversible after finalization</span></header>
        <form data-privacy-delete-form>
          <label><span>Where do you live?</span><select name="jurisdiction" required><option value="">Choose jurisdiction</option><option value="eu_eea">European Union / EEA</option><option value="uk">United Kingdom</option><option value="california">California</option><option value="other_us">Other United States</option><option value="other">Other / not listed</option></select></label>
          <label><span>Current password</span><input type="password" name="password" autocomplete="current-password" minlength="1" required></label>
          <label><span>Type DELETE to confirm</span><input type="text" name="confirmation" autocomplete="off" pattern="DELETE" required></label>
          <label class="mg-privacy-check"><input type="checkbox" name="understood" value="1" required><span>I understand that Microgifter will immediately disable this account, revoke all sessions, stop active automations, and begin the governed deletion process.</span></label>
          <div class="mg-privacy-notice" data-privacy-notice role="status" aria-live="polite"></div>
          <button class="mg-btn mg-btn-danger" type="submit">Close account and begin deletion</button>
        </form>
      </section>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
