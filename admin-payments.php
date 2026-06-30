<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
require_once __DIR__.'/includes/admin-auth.php';
$user = mg_require_admin_page_permission('admin.settings.manage');
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

  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-payment-admin-page">
      <header class="mg-payment-hero">
        <div class="mg-payment-hero-copy">
          <span class="mg-eyebrow">Platform payment authority</span>
          <h1>Stripe readiness center</h1>
          <p>Configure test and live Stripe credentials, verify webhook readiness, and control the Microgifter platform-share policy from one clean console.</p>
          <div class="mg-payment-hero-actions" aria-label="Payment setup shortcuts">
            <a class="mg-btn mg-btn-soft" href="#stripe-config">Configure Stripe</a>
            <a class="mg-btn mg-btn-ghost" href="#readiness-checks">View readiness</a>
          </div>
        </div>
        <aside class="mg-payment-hero-card" aria-label="Current readiness state">
          <span class="mg-payment-card-label">Current mode status</span>
          <strong class="mg-status-badge" data-payment-readiness>Loading readiness</strong>
          <p data-payment-save-state>Waiting for the payment settings API.</p>
        </aside>
      </header>

      <section class="mg-payment-top-grid" aria-label="Payment setup controls">
        <article class="mg-payment-setup-card mg-payment-credential-setup" data-payment-credential-setup>
          <div class="mg-payment-card-head">
            <span class="mg-payment-step">01</span>
            <div>
              <span class="mg-eyebrow">Server credential setup</span>
              <h2>Encrypted Stripe secret storage</h2>
              <p><code>MG_PAYMENT_CREDENTIAL_KEY</code> is the private Microgifter encryption key used to lock stored Stripe secret and webhook values before they go into the database.</p>
            </div>
          </div>
          <div class="mg-payment-credential-layout">
            <div class="mg-payment-credential-copy">
              <ol>
                <li>Click <strong>Generate safe key</strong>.</li>
                <li>Create <code>api/config.local.php</code> in File Manager.</li>
                <li>Paste the generated config block into that file.</li>
                <li>Refresh this page, then save your Stripe configuration.</li>
              </ol>
              <p class="mg-payment-credential-warning">Keep this file private. <code>api/config.local.php</code> is already ignored by Git, so it should never be committed.</p>
            </div>
            <div class="mg-payment-credential-card">
              <div class="mg-payment-credential-state" data-payment-credential-state>Checking encryption status…</div>
              <div class="mg-payment-button-row">
                <button class="mg-btn mg-btn-soft" type="button" data-payment-key-generate>Generate safe key</button>
                <button class="mg-btn mg-btn-ghost" type="button" data-payment-key-copy disabled>Copy config block</button>
              </div>
              <pre class="mg-payment-key-output" data-payment-key-output>// Click Generate safe key to create a File Manager config block.</pre>
            </div>
          </div>
        </article>

        <article class="mg-payment-setup-card mg-payment-cash-panel">
          <div class="mg-payment-card-head">
            <span class="mg-payment-step">02</span>
            <div>
              <span class="mg-eyebrow">Test payment method</span>
              <h2>Pay with cash</h2>
              <p>Enable a manual cash option for checkout testing without creating a Stripe charge.</p>
            </div>
          </div>
          <form data-admin-cash-payment-form>
            <label class="mg-toggle-switch">
              <input type="checkbox" name="cash_enabled" value="1" data-admin-cash-payment-toggle>
              <span class="mg-toggle-control" aria-hidden="true"></span>
              <span class="mg-toggle-copy"><strong>Cash payments</strong><small>Test/manual method only.</small></span>
            </label>
            <div class="mg-form-status" data-admin-cash-payment-status aria-live="polite"></div>
            <button class="mg-btn mg-btn-soft" type="submit">Save cash option</button>
          </form>
        </article>
      </section>

      <div class="mg-payment-admin-grid">
        <section class="mg-app-panel mg-payment-config-card" id="stripe-config">
          <div class="mg-app-panel-head">
            <div>
              <span class="mg-eyebrow">Stripe configuration</span>
              <h2>Keys, mode, and platform fee</h2>
              <p>Secret fields are write-only. Saved encrypted values are shown as safe hints after reload.</p>
            </div>
          </div>
          <div class="mg-app-panel-body">
            <form class="mg-merchant-form mg-payment-settings-form" data-payment-settings-form novalidate>
              <div class="mg-payment-form-strip">
                <label>Mode
                  <select name="mode" data-payment-mode>
                    <option value="test">Test</option>
                    <option value="live">Live</option>
                  </select>
                </label>
                <label class="mg-toggle-switch mg-stripe-toggle">
                  <input type="checkbox" name="enabled" value="1">
                  <span class="mg-toggle-control" aria-hidden="true"></span>
                  <span class="mg-toggle-copy"><strong>Enable Stripe</strong><small>Processes only when readiness passes.</small></span>
                </label>
              </div>

              <label>Publishable key
                <input name="publishable_key" autocomplete="off" placeholder="pk_test_… or pk_live_…">
                <small>Must match the selected mode.</small>
              </label>

              <?php require __DIR__.'/includes/admin-payment-credential-fields.php'; ?>

              <label>Connect client ID <span>(optional)</span>
                <input name="connect_client_id" autocomplete="off" placeholder="ca_…">
                <small>Leave blank if Connect onboarding is not active yet. This is not the webhook secret.</small>
              </label>

              <div class="mg-grid-2 mg-payment-fee-grid">
                <label>Platform share, basis points
                  <input name="platform_fee_bps" type="number" min="0" max="10000" value="1500" required>
                  <small>1500 = 15%, retained from the payment rather than added to the gift price.</small>
                </label>
                <label>Fixed platform fee, cents
                  <input name="fixed_fee_cents" type="number" min="0" value="0" required>
                </label>
              </div>

              <div class="mg-payment-submit-bar">
                <div class="mg-form-status mg-payment-save-status" data-payment-settings-status aria-live="polite">Ready to save Stripe settings.</div>
                <button class="mg-btn mg-btn-primary mg-payment-save-button" type="submit" data-payment-save-button>
                  <span data-payment-save-label>Save Stripe configuration</span>
                  <span class="mg-payment-save-spinner" aria-hidden="true"></span>
                </button>
              </div>
            </form>
          </div>
        </section>

        <aside class="mg-app-panel mg-payment-readiness-card" id="readiness-checks">
          <div class="mg-app-panel-head">
            <div>
              <span class="mg-eyebrow">Readiness checks</span>
              <h2>Launch requirements</h2>
              <p>Live payments remain blocked until every requirement passes.</p>
            </div>
          </div>
          <div class="mg-app-panel-body">
            <div data-payment-checks><div class="mg-empty-state">Loading checks…</div></div>
            <div class="mg-payment-webhook"><span>Webhook endpoint</span><code data-payment-webhook-url></code></div>
            <div data-payment-connect-counts></div>
          </div>
        </aside>
      </div>
    </section>
  </main>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
