<?php
declare(strict_types=1);
?>
<section class="mg-app-panel mg-account-pane is-active mg-admin-dashboard mg-investment-tests" data-account-pane="investment_tests" data-investment-tests>
  <div class="mg-app-panel-head mg-section-head">
    <div>
      <h2>Investment Tests</h2>
      <p>Browser-based controls for testing merchant market formulas, ticker snapshots, and profile investment charts without using bash.</p>
    </div>
    <div class="mg-admin-toolbar">
      <button class="mg-btn mg-btn-ghost" type="button" data-investment-refresh>Refresh</button>
    </div>
  </div>

  <div class="mg-app-panel-body">
    <div class="mg-admin-state" data-investment-state>
      <strong>Ready</strong><span>Choose a merchant slug or run snapshots for all active merchant profiles.</span>
    </div>

    <div class="mg-admin-section-grid">
      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head">
          <div>
            <h3>Single merchant test</h3>
            <p>Load the live v4 investment model, save today’s snapshot, and preview the historical market series.</p>
          </div>
        </header>
        <div class="mg-admin-section-body">
          <form class="mg-investment-test-form" data-investment-single-form>
            <label>
              <span>Merchant slug</span>
              <input type="text" name="slug" placeholder="merchant-slug" autocomplete="off" data-investment-slug>
            </label>
            <label>
              <span>Snapshot date</span>
              <input type="date" name="date" value="<?= mg_e(date('Y-m-d')) ?>" data-investment-date>
            </label>
            <div class="mg-action-row">
              <button class="mg-btn mg-btn-primary" type="submit" data-investment-load>Load model</button>
              <button class="mg-btn mg-btn-soft" type="button" data-investment-save disabled>Save snapshot</button>
              <button class="mg-btn mg-btn-ghost" type="button" data-investment-series disabled>Load series</button>
            </div>
          </form>
          <div class="mg-investment-result-grid" data-investment-result>
            <p class="mg-muted">No merchant loaded yet.</p>
          </div>
        </div>
      </section>

      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head">
          <div>
            <h3>Run active merchant snapshots</h3>
            <p>This runs from the browser: it loads each merchant’s investment model, then saves a snapshot through the admin API.</p>
          </div>
        </header>
        <div class="mg-admin-section-body">
          <div class="mg-investment-batch-controls">
            <label>
              <span>Limit</span>
              <input type="number" min="1" max="200" value="25" data-investment-limit>
            </label>
            <label>
              <span>Filter slug contains</span>
              <input type="text" placeholder="optional" data-investment-filter>
            </label>
            <button class="mg-btn mg-btn-primary" type="button" data-investment-run-all>Run snapshots</button>
          </div>
          <div class="mg-investment-progress" data-investment-progress hidden>
            <progress value="0" max="100" data-investment-progress-bar></progress>
            <span data-investment-progress-label>Waiting…</span>
          </div>
          <div class="mg-investment-log" data-investment-log><p class="mg-muted">Batch log will appear here.</p></div>
        </div>
      </section>

      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head">
          <div>
            <h3>Recent market snapshots</h3>
            <p>Most recent stored ticker snapshots from the merchant market snapshot table.</p>
          </div>
        </header>
        <div class="mg-admin-section-body" data-investment-recent>
          <p class="mg-muted">Loading recent snapshots…</p>
        </div>
      </section>
    </div>
  </div>
</section>
