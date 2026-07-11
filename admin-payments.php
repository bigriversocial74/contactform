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
      <nav class="mg-payment-admin-tabs" role="tablist" aria-label="Payment administration sections">
        <button class="is-active" type="button" role="tab" aria-selected="true" data-admin-payment-tab="methods">Payment Methods</button>
        <button type="button" role="tab" aria-selected="false" data-admin-payment-tab="stripe">Stripe Configuration</button>
        <button type="button" role="tab" aria-selected="false" data-admin-payment-tab="readiness">Readiness</button>
      </nav>

      <section class="mg-payment-admin-section is-active" data-admin-payment-page="methods">
        <div class="mg-payment-method-admin-grid">
          <article class="mg-payment-setup-card mg-payment-method-admin-card">
            <div class="mg-payment-card-head">
              <span class="mg-payment-method-mark">$</span>
              <div>
                <span class="mg-eyebrow">Platform method</span>
                <h2>Cash payments</h2>
                <p>Enable a manual cash option for checkout testing without creating a Stripe charge.</p>
              </div>
            </div>
            <form data-admin-cash-payment-form>
              <label class="mg-toggle-switch">
                <input type="checkbox" name="cash_enabled" value="1" data-admin-cash-payment-toggle>
                <span class="mg-toggle-control" aria-hidden="true"></span>
                <span class="mg-toggle-copy"><strong>Enable cash</strong><small>Global test/manual method.</small></span>
              </label>
              <div class="mg-form-status" data-admin-cash-payment-status aria-live="polite"></div>
              <button class="mg-btn mg-btn-soft" type="submit">Save cash option</button>
            </form>
          </article>

          <article class="mg-payment-setup-card mg-payment-method-admin-card">
            <div class="mg-payment-card-head">
              <span class="mg-payment-method-mark is-stripe">S</span>
              <div>
                <span class="mg-eyebrow">Platform method</span>
                <h2>Stripe payments</h2>
                <p>Control whether the current Stripe mode is globally available. Merchant onboarding will be connected in the next integration PR.</p>
              </div>
            </div>
            <div class="mg-payment-method-admin-form">
              <label class="mg-toggle-switch">
                <input type="checkbox" value="1" data-admin-stripe-payment-toggle>
                <span class="mg-toggle-control" aria-hidden="true"></span>
                <span class="mg-toggle-copy"><strong>Enable Stripe</strong><small>Current configured mode only.</small></span>
              </label>
              <div class="mg-form-status" data-admin-stripe-payment-status aria-live="polite"></div>
              <button class="mg-btn mg-btn-soft" type="button" data-admin-stripe-payment-save>Save Stripe option</button>
            </div>
          </article>
        </div>
      </section>

      <section class="mg-payment-admin-section" data-admin-payment-page="stripe" hidden>
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
              <input type="hidden" name="enabled" value="0">
              <div class="mg-payment-form-strip">
                <label>Mode
                  <select name="mode" data-payment-mode>
                    <option value="test">Test</option>
                    <option value="live">Live</option>
                  </select>
                </label>
                <div class="mg-payment-config-state">
                  <span>Method availability</span>
                  <strong data-payment-config-enabled>Loading</strong>
                </div>
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
      </section>

      <section class="mg-payment-admin-section" data-admin-payment-page="readiness" hidden>
        <section class="mg-app-panel mg-payment-readiness-card" id="readiness-checks">
          <div class="mg-app-panel-head">
            <div>
              <span class="mg-eyebrow">Readiness checks</span>
              <h2>Launch requirements</h2>
              <p>Live payments remain blocked until every requirement passes.</p>
            </div>
            <strong class="mg-status-badge" data-payment-readiness>Loading readiness</strong>
          </div>
          <div class="mg-app-panel-body">
            <div class="mg-form-status" data-payment-save-state>Waiting for the payment settings API.</div>
            <div data-payment-checks><div class="mg-empty-state">Loading checks…</div></div>
            <div class="mg-payment-webhook"><span>Webhook endpoint</span><code data-payment-webhook-url></code></div>
            <div data-payment-connect-counts></div>
          </div>
        </section>
      </section>
    </section>
  </main>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
