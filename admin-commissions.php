<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-auth.php';
$user = mg_require_admin_page_permission('admin.payments.commissions.manage');
$page_title = 'Commission Authority | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-payments.css','/assets/css/commission-authority.css'];
$page_scripts = ['/assets/js/admin-commissions.js'];
$adminActive = 'payments';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app mg-commission-admin" data-admin-commissions>
  <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-commission-page">
      <header class="mg-commission-hero">
        <div>
          <span class="mg-eyebrow">Canonical commerce authority</span>
          <h1>Merchant &amp; Bundle Commissions</h1>
          <p>Set the platform starting rate, manage effective-dated merchant terms, and prepare accepted bundle participant rates. Paid orders retain immutable purchase-time snapshots.</p>
        </div>
        <a class="mg-btn mg-btn-soft" href="/admin-payments.php">Stripe configuration</a>
      </header>

      <div class="mg-commission-status" data-commission-global-status aria-live="polite">Loading commission authority…</div>

      <section class="mg-commission-grid is-top">
        <article class="mg-app-panel mg-commission-panel">
          <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Platform starting rate</span><h2>New merchant baseline</h2><p>New merchant profiles are initialized from this rate and then remain fixed unless an administrator changes their terms.</p></div></div>
          <div class="mg-app-panel-body">
            <form class="mg-merchant-form" data-platform-commission-form>
              <label>Starting commission, basis points
                <input name="starting_commission_bps" type="number" min="0" max="10000" step="1" required>
                <small>1500 = 15%. This is a configurable starting value, never a hard-coded transaction rate.</small>
              </label>
              <label>Change reason<textarea name="reason" maxlength="500" required placeholder="Explain why the platform starting rate is changing."></textarea></label>
              <label>Confirmation<input name="confirmation" autocomplete="off" required placeholder="CONFIRM COMMISSION CHANGE"></label>
              <div class="mg-commission-preview" data-platform-preview></div>
              <button class="mg-btn mg-btn-primary" type="submit">Save platform starting rate</button>
            </form>
          </div>
        </article>

        <article class="mg-app-panel mg-commission-panel">
          <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Resolution policy</span><h2>How a rate is selected</h2></div></div>
          <div class="mg-app-panel-body">
            <ol class="mg-commission-resolution">
              <li><strong>Accepted bundle participant terms</strong><span>Highest priority for bundle components.</span></li>
              <li><strong>Bundle starting rate</strong><span>Used when the bundle is configured to apply one shared rate.</span></li>
              <li><strong>Merchant effective rate</strong><span>Fixed, promotional, contract, or platform-following.</span></li>
              <li><strong>Platform starting fallback</strong><span>Used only when no merchant profile exists.</span></li>
            </ol>
          </div>
        </article>
      </section>

      <section class="mg-app-panel mg-commission-panel">
        <div class="mg-app-panel-head mg-commission-head-row">
          <div><span class="mg-eyebrow">Merchant terms</span><h2>Per-merchant adjustable commission</h2><p>Search a merchant, review their current effective rate, then create a new immutable version.</p></div>
          <label class="mg-commission-search">Search merchants<input type="search" data-merchant-commission-search placeholder="Business name or email"></label>
        </div>
        <div class="mg-app-panel-body mg-commission-merchant-layout">
          <div class="mg-commission-merchant-list" data-merchant-commission-list></div>
          <form class="mg-merchant-form mg-commission-editor" data-merchant-commission-form hidden>
            <input type="hidden" name="merchant_user_id">
            <div class="mg-commission-selected" data-selected-merchant></div>
            <label>Rate mode
              <select name="rate_mode" required>
                <option value="fixed_merchant_rate">Fixed merchant rate</option>
                <option value="contract_rate">Contract rate</option>
                <option value="promotional_rate">Promotional rate</option>
                <option value="follow_platform_default">Follow platform starting rate</option>
              </select>
            </label>
            <label data-merchant-rate-field>Commission, basis points<input name="commission_rate_bps" type="number" min="0" max="10000" step="1"></label>
            <div class="mg-grid-2">
              <label>Effective from<input name="effective_from" type="datetime-local" required></label>
              <label>Effective until <span>(optional)</span><input name="effective_until" type="datetime-local"></label>
            </div>
            <label>Reason<textarea name="reason" maxlength="500" required></textarea></label>
            <label>Confirmation<input name="confirmation" autocomplete="off" required placeholder="CONFIRM COMMISSION CHANGE"></label>
            <div class="mg-commission-preview" data-merchant-preview></div>
            <button class="mg-btn mg-btn-primary" type="submit">Save merchant commission version</button>
          </form>
        </div>
      </section>

      <section class="mg-app-panel mg-commission-panel">
        <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Bundle foundation</span><h2>Bundle commission profile</h2><p>Create a versioned commission policy before the future Bundle Builder publishes merchant participation terms.</p></div></div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form mg-commission-bundle-form" data-bundle-commission-form>
            <label>Bundle reference<input name="bundle_reference" maxlength="190" required placeholder="bundle-public-id or planning reference"></label>
            <label>Commission mode
              <select name="commission_mode" required>
                <option value="merchant_default">Use each merchant’s default rate</option>
                <option value="bundle_starting_rate">Use one starting rate for this bundle</option>
                <option value="custom_participant_rates">Require accepted participant rates</option>
              </select>
            </label>
            <label data-bundle-rate-field hidden>Bundle starting commission, basis points<input name="starting_commission_bps" type="number" min="0" max="10000" step="1"></label>
            <label>Status<select name="status"><option value="draft">Draft</option><option value="locked">Locked for publication</option></select></label>
            <label>Reason<textarea name="reason" maxlength="500" required></textarea></label>
            <label>Confirmation<input name="confirmation" autocomplete="off" required placeholder="CONFIRM COMMISSION CHANGE"></label>
            <button class="mg-btn mg-btn-primary" type="submit">Save bundle commission profile</button>
          </form>
        </div>
      </section>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
