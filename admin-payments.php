<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.settings.manage');

$page_title = 'Payment Settings | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = [
    '/assets/css/admin-shell.css',
    '/assets/css/admin-payments.css',
    '/assets/css/admin-payments-cleanup.css',
];
$page_scripts = ['/assets/js/admin-payments.js'];
$adminActive = 'payments';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app" data-admin-payments>
  <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>

  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-payment-admin-page">
      <header class="mg-payment-page-head">
        <div>
          <span class="mg-eyebrow">Commerce</span>
          <h1>Payment settings</h1>
          <p>Manage payment methods, Stripe credentials, and launch status.</p>
        </div>
        <strong class="mg-status-badge" data-payment-global-status>Loading</strong>
      </header>

      <nav class="mg-payment-admin-tabs" role="tablist" aria-label="Payment administration sections">
        <button class="is-active" type="button" role="tab" aria-selected="true" data-admin-payment-tab="methods">Payment Methods</button>
        <button type="button" role="tab" aria-selected="false" data-admin-payment-tab="stripe">Stripe Configuration</button>
        <button type="button" role="tab" aria-selected="false" data-admin-payment-tab="secrets">Secret Storage</button>
        <button type="button" role="tab" aria-selected="false" data-admin-payment-tab="readiness">Readiness</button>
      </nav>

      <section class="mg-payment-admin-section is-active" data-admin-payment-page="methods">
        <div class="mg-payment-method-admin-grid">
          <article class="mg-payment-setup-card mg-payment-method-admin-card">
            <div class="mg-payment-card-head">
              <span class="mg-payment-method-mark">$</span>
              <div>
                <h2>Cash payments</h2>
                <p>Manual checkout option for testing and in-person collection.</p>
              </div>
            </div>
            <form data-admin-cash-payment-form>
              <label class="mg-toggle-switch">
                <input type="checkbox" name="cash_enabled" value="1" data-admin-cash-payment-toggle>
                <span class="mg-toggle-control" aria-hidden="true"></span>
                <span class="mg-toggle-copy"><strong>Enable cash</strong></span>
              </label>
              <div class="mg-form-status" data-admin-cash-payment-status aria-live="polite"></div>
              <button class="mg-btn mg-btn-soft" type="submit">Save</button>
            </form>
          </article>

          <article class="mg-payment-setup-card mg-payment-method-admin-card">
            <div class="mg-payment-card-head">
              <span class="mg-payment-method-mark is-stripe">S</span>
              <div>
                <h2>Stripe payments</h2>
                <p>Card payments for the currently selected Test or Live mode.</p>
              </div>
            </div>
            <div class="mg-payment-method-admin-form">
              <label class="mg-toggle-switch">
                <input type="checkbox" value="1" data-admin-stripe-payment-toggle>
                <span class="mg-toggle-control" aria-hidden="true"></span>
                <span class="mg-toggle-copy"><strong>Enable Stripe</strong></span>
              </label>
              <div class="mg-form-status" data-admin-stripe-payment-status aria-live="polite"></div>
              <button class="mg-btn mg-btn-soft" type="button" data-admin-stripe-payment-save>Save</button>
            </div>
          </article>
        </div>
      </section>

      <section class="mg-payment-admin-section" data-admin-payment-page="stripe" hidden>
        <section class="mg-app-panel mg-payment-config-card">
          <div class="mg-app-panel-head">
            <div>
              <h2>Stripe configuration</h2>
              <p>Configure the active mode, public key, Connect ID, and platform fee.</p>
            </div>
          </div>
          <div class="mg-app-panel-body">
            <form id="stripe-payment-form" class="mg-payment-settings-form" data-payment-settings-form novalidate>
              <input type="hidden" name="enabled" value="0">

              <div class="mg-payment-form-strip">
                <label>Configuration mode
                  <select name="mode" data-payment-mode>
                    <option value="test">Test</option>
                    <option value="live">Live</option>
                  </select>
                </label>
                <div class="mg-payment-config-state">
                  <span>Stripe availability</span>
                  <strong data-payment-config-enabled>Loading</strong>
                </div>
              </div>

              <div class="mg-payment-field-grid">
                <label>Publishable key
                  <input name="publishable_key" autocomplete="off" placeholder="pk_live_… or pk_test_…">
                </label>

                <label>Connect client ID <span>(optional)</span>
                  <input name="connect_client_id" autocomplete="off" placeholder="ca_…">
                </label>

                <label>Platform share
                  <div class="mg-payment-input-suffix">
                    <input name="platform_fee_bps" type="number" min="0" max="10000" value="1500" required>
                    <span>basis points</span>
                  </div>
                </label>

                <label>Fixed platform fee
                  <div class="mg-payment-input-suffix">
                    <input name="fixed_fee_cents" type="number" min="0" value="0" required>
                    <span>cents</span>
                  </div>
                </label>
              </div>

              <div class="mg-payment-mode-warning" data-payment-mode-warning hidden aria-live="polite"></div>

              <div class="mg-payment-submit-bar">
                <div class="mg-form-status mg-payment-save-status" data-payment-settings-status aria-live="polite">Ready.</div>
                <button class="mg-btn mg-btn-primary mg-payment-save-button" type="submit" data-payment-save-button>
                  <span data-payment-save-label>Save Stripe configuration</span>
                  <span class="mg-payment-save-spinner" aria-hidden="true"></span>
                </button>
              </div>
            </form>
          </div>
        </section>
      </section>

      <section class="mg-payment-admin-section" data-admin-payment-page="secrets" hidden>
        <div class="mg-payment-secret-tab-grid">
          <article class="mg-payment-setup-card mg-payment-credential-setup" data-payment-credential-setup>
            <div class="mg-payment-card-head">
              <span class="mg-payment-step">01</span>
              <div>
                <h2>Encrypted Stripe secret storage</h2>
                <p>Server encryption protects Stripe API and webhook secrets stored in the database.</p>
              </div>
            </div>

            <div class="mg-payment-credential-state" data-payment-credential-state>Checking encryption status…</div>

            <div class="mg-payment-button-row">
              <button class="mg-btn mg-btn-soft" type="button" data-payment-key-generate>Generate config block</button>
              <button class="mg-btn mg-btn-ghost" type="button" data-payment-key-copy disabled>Copy</button>
            </div>

            <pre class="mg-payment-key-output" data-payment-key-output hidden></pre>
          </article>

          <article class="mg-payment-setup-card mg-payment-secret-card">
            <div class="mg-payment-card-head">
              <span class="mg-payment-step">02</span>
              <div>
                <h2>Saved Stripe credentials</h2>
                <p><strong data-payment-secret-mode>Live</strong> database record</p>
              </div>
            </div>

            <?php require __DIR__ . '/includes/admin-payment-credential-fields.php'; ?>

            <div class="mg-form-status" data-payment-secret-save-status aria-live="polite">Saved values are shown as masked references.</div>

            <button class="mg-btn mg-btn-primary" type="submit" form="stripe-payment-form" data-payment-secret-save>
              Save secret changes
            </button>
          </article>
        </div>
      </section>

      <section class="mg-payment-admin-section" data-admin-payment-page="readiness" hidden>
        <section class="mg-app-panel mg-payment-readiness-card">
          <div class="mg-app-panel-head">
            <div>
              <h2>Stripe readiness</h2>
              <p>Requirements for the selected Test or Live mode.</p>
            </div>
            <strong class="mg-status-badge" data-payment-readiness>Loading</strong>
          </div>
          <div class="mg-app-panel-body">
            <div data-payment-checks><div class="mg-empty-state">Loading checks…</div></div>
            <div class="mg-payment-readiness-meta">
              <div class="mg-payment-webhook"><span>Webhook endpoint</span><code data-payment-webhook-url></code></div>
              <div data-payment-connect-counts></div>
            </div>
          </div>
        </section>
      </section>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
