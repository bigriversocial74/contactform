<?php
declare(strict_types=1);
$isStandaloneCalendar = !empty($designCalendarStandalone);
$calendarMerchantName = trim((string) ($displayName ?? '')) ?: 'Your Business';
?>
<section class="mg-agent-design-mode-panel" data-design-mode-panel="calendar"<?= $isStandaloneCalendar ? '' : ' hidden' ?>>
  <div class="mg-design-calendar"
       data-design-content-calendar
       data-calendar-days="30"
       data-merchant-name="<?= mg_e($calendarMerchantName) ?>">
    <div class="mg-calendar-plan-modal" data-calendar-plan-modal hidden>
      <div class="mg-calendar-plan-backdrop" data-calendar-plan-close></div>
      <section class="mg-calendar-plan-dialog" role="dialog" aria-modal="true" aria-labelledby="calendar-plan-title">
        <header class="mg-calendar-plan-head">
          <div>
            <span class="mg-agent-design-step">Calendar setup</span>
            <h2 id="calendar-plan-title">Build or edit the next 30 days</h2>
            <p>Choose products, posting cadence, formats, layouts, and campaign themes. Regenerating replaces schedule rows in this window while saved creative assets remain intact.</p>
          </div>
          <button type="button" data-calendar-plan-close aria-label="Close calendar setup">×</button>
        </header>

        <form class="mg-design-calendar-form" data-calendar-generator>
          <div class="mg-design-calendar-products">
            <div class="mg-design-calendar-control-head">
              <label class="mg-design-calendar-check-all"><input type="checkbox" data-calendar-select-all><span>Select all products</span></label>
              <span data-calendar-product-count>Loading products…</span>
            </div>
            <div class="mg-design-calendar-product-list" data-calendar-product-list aria-live="polite"></div>
          </div>

          <div class="mg-design-calendar-settings mg-design-calendar-settings-v2">
            <div class="mg-calendar-field-grid">
              <label><span>Start date</span><input type="date" name="start_date" data-calendar-start-date required></label>
              <label><span>Posting frequency</span>
                <select name="frequency" data-calendar-frequency>
                  <option value="daily">Daily</option>
                  <option value="weekdays">Weekdays</option>
                  <option value="three_per_week">Three times per week</option>
                  <option value="twice_per_week">Twice per week</option>
                  <option value="weekly">Weekly</option>
                  <option value="custom">Custom selected weekdays</option>
                </select>
              </label>
              <label><span>Preferred posting time</span><input type="time" name="preferred_time" value="10:00" data-calendar-preferred-time></label>
            </div>

            <fieldset class="mg-calendar-weekday-fieldset">
              <legend>Preferred posting days</legend>
              <div class="mg-calendar-weekday-grid">
                <?php foreach ([[1,'Mon'],[2,'Tue'],[3,'Wed'],[4,'Thu'],[5,'Fri'],[6,'Sat'],[7,'Sun']] as $day): ?>
                  <label><input type="checkbox" name="preferred_weekdays[]" value="<?= $day[0] ?>" data-calendar-weekday><span><?= $day[1] ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <div class="mg-calendar-option-columns">
              <fieldset><legend>Formats</legend>
                <label><input type="checkbox" name="formats[]" value="square" checked><span>Post · 1:1</span></label>
                <label><input type="checkbox" name="formats[]" value="portrait" checked><span>Portrait · 4:5</span></label>
                <label><input type="checkbox" name="formats[]" value="story" checked><span>Story / Reel · 9:16</span></label>
              </fieldset>
              <fieldset><legend>Layouts</legend>
                <label><input type="checkbox" name="layouts[]" value="spotlight" checked><span>Spotlight</span></label>
                <label><input type="checkbox" name="layouts[]" value="split" checked><span>Split Feature</span></label>
                <label><input type="checkbox" name="layouts[]" value="bold" checked><span>Bold Offer</span></label>
              </fieldset>
              <fieldset><legend>Campaign themes</legend>
                <label><input type="checkbox" name="themes[]" value="product_spotlight" checked><span>Product Spotlight</span></label>
                <label><input type="checkbox" name="themes[]" value="gift_idea" checked><span>Gift Idea</span></label>
                <label><input type="checkbox" name="themes[]" value="reward_promotion" checked><span>Reward Promotion</span></label>
                <label><input type="checkbox" name="themes[]" value="merchant_story"><span>Merchant Story</span></label>
                <label><input type="checkbox" name="themes[]" value="customer_review"><span>Customer Review</span></label>
                <label><input type="checkbox" name="themes[]" value="local_support"><span>Local Support</span></label>
              </fieldset>
            </div>

            <div class="mg-calendar-plan-submit">
              <button class="mg-btn mg-btn-primary" type="submit" data-calendar-generate>Build 30-day plan</button>
              <small>Nothing is automatically published to outside networks. Generated artwork is saved only when you explicitly choose Save Creative Asset.</small>
            </div>
          </div>
        </form>
      </section>
    </div>

    <section class="mg-design-calendar-board">
      <header class="mg-design-calendar-toolbar">
        <div class="mg-design-calendar-range-copy">
          <span class="mg-agent-design-step">Scheduled advertising</span>
          <h2 data-calendar-range-label>Next 30 days</h2>
        </div>

        <div class="mg-design-calendar-view-toggle" role="group" aria-label="Calendar display">
          <button type="button" class="is-active" data-calendar-view="grid" aria-pressed="true">Grid</button>
          <button type="button" data-calendar-view="stack" aria-pressed="false">Stacked</button>
          <button type="button" data-calendar-view="side" aria-pressed="false">Side by side</button>
        </div>

        <div class="mg-design-calendar-toolbar-actions">
          <button type="button" class="mg-calendar-edit-plan" data-calendar-plan-open>Edit calendar</button>
          <div class="mg-design-calendar-range-actions">
            <button type="button" data-calendar-range="-30" aria-label="Previous 30 days">←</button>
            <button type="button" data-calendar-today>Today</button>
            <button type="button" data-calendar-range="30" aria-label="Next 30 days">→</button>
          </div>
        </div>
      </header>

      <div class="mg-design-calendar-summary" aria-label="Schedule summary">
        <article><strong data-calendar-count="total">0</strong><span>Scheduled</span></article>
        <article><strong data-calendar-count="planned">0</strong><span>Planned</span></article>
        <article><strong data-calendar-count="downloaded">0</strong><span>Downloaded</span></article>
        <article><strong data-calendar-count="posted">0</strong><span>Posted</span></article>
      </div>

      <details class="mg-calendar-management">
        <summary>Filters and bulk tools</summary>
        <div class="mg-calendar-management-body">
          <section class="mg-calendar-filter-panel" aria-label="Calendar filters">
            <div class="mg-calendar-filter-grid">
              <label><span>Product</span><select data-calendar-filter="product"><option value="">All products</option></select></label>
              <label><span>Format</span><select data-calendar-filter="format"><option value="">All formats</option><option value="square">Square</option><option value="portrait">Portrait</option><option value="story">Story / Reel</option></select></label>
              <label><span>Layout</span><select data-calendar-filter="layout"><option value="">All layouts</option><option value="spotlight">Spotlight</option><option value="split">Split Feature</option><option value="bold">Bold Offer</option></select></label>
              <label><span>Status</span><select data-calendar-filter="status"><option value="">All statuses</option><option value="planned">Planned</option><option value="downloaded">Downloaded</option><option value="posted">Posted</option><option value="skipped">Skipped</option></select></label>
              <label><span>Date from</span><input type="date" data-calendar-filter="date_from"></label>
              <label><span>Date to</span><input type="date" data-calendar-filter="date_to"></label>
            </div>
            <div class="mg-calendar-filter-actions">
              <div data-calendar-active-filters aria-live="polite">No active filters</div>
              <button type="button" class="mg-btn mg-btn-soft" data-calendar-clear-filters>Clear filters</button>
            </div>
          </section>

          <section class="mg-calendar-bulk-panel" data-calendar-bulk-panel aria-label="Bulk schedule actions">
            <label><input type="checkbox" data-calendar-select-visible><span>Select visible</span></label>
            <strong><span data-calendar-selected-count>0</span> selected</strong>
            <select data-calendar-bulk-format><option value="">Change format…</option><option value="square">Square</option><option value="portrait">Portrait</option><option value="story">Story / Reel</option></select>
            <select data-calendar-bulk-layout><option value="">Change layout…</option><option value="spotlight">Spotlight</option><option value="split">Split Feature</option><option value="bold">Bold Offer</option></select>
            <select data-calendar-bulk-status><option value="">Change status…</option><option value="planned">Planned</option><option value="downloaded">Downloaded</option><option value="posted">Posted</option><option value="skipped">Skipped</option></select>
            <button type="button" class="mg-btn mg-btn-soft" data-calendar-bulk-apply>Apply</button>
            <button type="button" class="mg-btn mg-btn-danger" data-calendar-bulk-remove>Remove selected</button>
          </section>
        </div>
      </details>

      <div class="mg-design-calendar-setup" data-calendar-setup hidden>
        <strong>Advertising workflow database setup required</strong>
        <p>Import <code>database/20260716_design_studio_advertising_workflow_v2.sql</code>, then refresh this page.</p>
      </div>
      <div class="mg-design-calendar-empty" data-calendar-empty hidden>
        <strong>No content is scheduled in this 30-day range.</strong>
        <p>Build a 30-day advertising plan or move to another date range.</p>
        <button type="button" class="mg-btn mg-btn-primary" data-calendar-plan-open>Build calendar</button>
      </div>
      <div class="mg-design-calendar-loading" data-calendar-loading hidden>Loading scheduled content…</div>
      <div class="mg-design-calendar-error" data-calendar-error hidden></div>
      <div class="mg-design-calendar-grid-view" data-calendar-grid></div>
      <div class="mg-design-calendar-stack-view" data-calendar-stack hidden></div>
      <div class="mg-design-calendar-side-view" data-calendar-side hidden></div>
      <div class="mg-design-calendar-status" data-calendar-status role="status" aria-live="polite"></div>
    </section>
  </div>
</section>