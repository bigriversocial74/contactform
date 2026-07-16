<?php declare(strict_types=1); ?>
<section class="mg-agent-design-mode-panel" data-design-mode-panel="calendar" hidden>
  <div class="mg-design-calendar" data-design-content-calendar data-calendar-days="30">
    <header class="mg-design-calendar-hero">
      <div>
        <span class="mg-agent-design-step">Manual publishing planner</span>
        <h2>30-day content calendar</h2>
        <p>Plan product content across a real calendar, open the matching creative, and download the correct post format for manual publishing on other sites.</p>
      </div>
      <div class="mg-design-calendar-view-toggle" role="group" aria-label="Calendar display">
        <button type="button" class="is-active" data-calendar-view="grid" aria-pressed="true">Grid</button>
        <button type="button" data-calendar-view="stack" aria-pressed="false">Stacked</button>
      </div>
    </header>

    <section class="mg-design-calendar-builder" aria-labelledby="calendar-builder-heading">
      <div class="mg-design-calendar-builder-copy">
        <span class="mg-agent-design-step">Schedule builder</span>
        <h3 id="calendar-builder-heading">Choose products for the next 30 days</h3>
        <p>Products and formats rotate through the schedule. Every date remains editable after the plan is created.</p>
      </div>

      <form class="mg-design-calendar-form" data-calendar-generator>
        <div class="mg-design-calendar-products">
          <div class="mg-design-calendar-control-head">
            <label class="mg-design-calendar-check-all">
              <input type="checkbox" data-calendar-select-all>
              <span>Select all products</span>
            </label>
            <span data-calendar-product-count>Loading products…</span>
          </div>
          <div class="mg-design-calendar-product-list" data-calendar-product-list aria-live="polite"></div>
        </div>

        <div class="mg-design-calendar-settings">
          <label>
            <span>Start date</span>
            <input type="date" name="start_date" data-calendar-start-date required>
          </label>

          <fieldset>
            <legend>Post formats to rotate</legend>
            <label><input type="checkbox" name="formats[]" value="square" checked><span>Post · 1:1</span></label>
            <label><input type="checkbox" name="formats[]" value="portrait" checked><span>Portrait · 4:5</span></label>
            <label><input type="checkbox" name="formats[]" value="story" checked><span>Reel / Story · 9:16</span></label>
          </fieldset>

          <button class="mg-btn mg-btn-primary" type="submit" data-calendar-generate>Build 30-day plan</button>
          <small>This creates a planning calendar only. Nothing is automatically published to outside networks.</small>
        </div>
      </form>
    </section>

    <section class="mg-design-calendar-board">
      <header class="mg-design-calendar-toolbar">
        <div>
          <span class="mg-agent-design-step">Scheduled content</span>
          <h3 data-calendar-range-label>Next 30 days</h3>
        </div>

        <div class="mg-design-calendar-range-actions">
          <button type="button" data-calendar-range="-30" aria-label="Previous 30 days">←</button>
          <button type="button" data-calendar-today>Today</button>
          <button type="button" data-calendar-range="30" aria-label="Next 30 days">→</button>
        </div>
      </header>

      <div class="mg-design-calendar-summary" aria-label="Schedule summary">
        <article><strong data-calendar-count="total">0</strong><span>Scheduled</span></article>
        <article><strong data-calendar-count="planned">0</strong><span>Planned</span></article>
        <article><strong data-calendar-count="downloaded">0</strong><span>Downloaded</span></article>
        <article><strong data-calendar-count="posted">0</strong><span>Posted</span></article>
      </div>

      <div class="mg-design-calendar-setup" data-calendar-setup hidden>
        <strong>Calendar database setup required</strong>
        <p>Import <code>database/20260716_design_studio_content_calendar.sql</code>, then refresh this page.</p>
      </div>

      <div class="mg-design-calendar-empty" data-calendar-empty hidden>
        <strong>No content is scheduled in this 30-day range.</strong>
        <p>Select merchant products above and build a plan, or move to another date range.</p>
      </div>

      <div class="mg-design-calendar-grid-view" data-calendar-grid></div>
      <div class="mg-design-calendar-stack-view" data-calendar-stack hidden></div>

      <div class="mg-design-calendar-status" data-calendar-status role="status" aria-live="polite"></div>
    </section>
  </div>
</section>
