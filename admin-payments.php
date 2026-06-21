<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
if(!mg_has_role('super_admin')&&!mg_has_permission('admin.settings.manage')){http_response_code(403);exit('Forbidden');}
$page_title='Stripe Payment Settings | Microgifter';
$page_section='account';
$header_mode='account';
$page_styles=['/assets/css/admin-shell.css','/assets/css/admin-payments.css'];
$page_scripts=['/assets/js/admin-payments.js'];
$adminActive='payments';
require __DIR__.'/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app" data-admin-payments>
  <?php require __DIR__.'/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-payment-admin-page">
      <header class="mg-payment-admin-head">
        <div><span class="mg-eyebrow">Platform payment authority</span><h1>Stripe settings &amp; readiness</h1><p>Review test and live configuration, set the Microgifter platform share, and verify webhook and Connect readiness.</p></div>
        <span class="mg-status-badge" data-payment-readiness>Loading readiness</span>
      </header>
      <section class="mg-payment-security-notice"><strong>Protected credentials</strong><p>Server environment values take priority. Database credentials are encrypted with <code>MG_PAYMENT_CREDENTIAL_KEY</code>, and stored secret values are never returned to the browser.</p></section>
      <div class="mg-payment-admin-grid">
        <section class="mg-app-panel">
          <div class="mg-app-panel-head"><div><h2>Stripe configuration</h2><p>Configure credentials and the platform-share policy for the selected mode.</p></div></div>
          <div class="mg-app-panel-body">
            <form class="mg-merchant-form" data-payment-settings-form>
              <div class="mg-grid-2">
                <label>Mode<select name="mode" data-payment-mode><option value="test">Test</option><option value="live">Live</option></select></label>
                <label class="mg-toggle-switch mg-stripe-toggle"><input type="checkbox" name="enabled" value="1"><span class="mg-toggle-control" aria-hidden="true"></span><span class="mg-toggle-copy"><strong>Enable Stripe</strong><small>Allow this mode to process Stripe payments when readiness passes.</small></span></label>
              </div>
              <label>Publishable key<input name="publishable_key" autocomplete="off" placeholder="pk_test_… or pk_live_…"></label>
              <?php require __DIR__.'/includes/admin-payment-credential-fields.php'; ?>
              <label>Connect client ID <span>(optional)</span><input name="connect_client_id" autocomplete="off" placeholder="ca_…"></label>
              <div class="mg-grid-2">
                <label>Platform share, basis points<input name="platform_fee_bps" type="number" min="0" max="10000" value="1500" required><small>1500 = 15%, retained from the payment rather than added to the gift price.</small></label>
                <label>Fixed platform fee, cents<input name="fixed_fee_cents" type="number" min="0" value="0" required></label>
              </div>
              <div class="mg-form-status" data-payment-settings-status></div>
              <button class="mg-btn mg-btn-primary" type="submit">Save Stripe configuration</button>
            </form>
          </div>
        </section>
        <section class="mg-app-panel">
          <div class="mg-app-panel-head"><div><h2>Readiness checks</h2><p>Live payments remain blocked until every requirement passes.</p></div></div>
          <div class="mg-app-panel-body">
            <div data-payment-checks><div class="mg-empty-state">Loading checks…</div></div>
            <div class="mg-payment-webhook"><span>Webhook endpoint</span><code data-payment-webhook-url></code></div>
            <div data-payment-connect-counts></div>
          </div>
        </section>
      </div>
    </section>
  </div>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>