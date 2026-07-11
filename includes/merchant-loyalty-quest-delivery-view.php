<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-lqd-heading">
  <div>
    <span class="mg-eyebrow">Loyalty Quest delivery</span>
    <h1>Invite participants and verify every delivery.</h1>
    <p>Send permission-aware Loyalty Quest invitations, review transactional delivery history, and retry failed email jobs without exposing reward or claim secrets.</p>
  </div>
  <div class="mg-lqd-heading-actions">
    <a class="mg-btn mg-btn-secondary" href="/notification-preferences.php">Notification preferences</a>
    <button class="mg-btn mg-btn-primary" type="button" data-lqd-refresh>Refresh delivery</button>
  </div>
</section>

<div class="mg-lqd-kpis" data-lqd-kpis aria-live="polite"></div>

<div class="mg-lqd-grid">
  <section class="mg-app-panel mg-lqd-invite-panel">
    <div class="mg-app-panel-head">
      <div>
        <span class="mg-eyebrow">Participant invitations</span>
        <h2>Send a Loyalty Quest invitation</h2>
        <p>Only active Loyalty Quests and deliverable campaign contacts can be selected.</p>
      </div>
      <span class="mg-status-badge" data-lqd-invite-status>Choose a quest</span>
    </div>
    <div class="mg-app-panel-body">
      <form data-lqd-invite-form>
        <label class="mg-lqd-field">Loyalty Quest
          <select name="campaign_id" data-lqd-campaign required>
            <option value="">Loading active quests…</option>
          </select>
        </label>
        <div class="mg-lqd-contact-toolbar">
          <label class="mg-lqd-check"><input type="checkbox" data-lqd-select-all> Select all deliverable contacts</label>
          <span data-lqd-selected-count>0 selected</span>
        </div>
        <div class="mg-lqd-contact-list" data-lqd-contact-list aria-live="polite">
          <div class="mg-empty-state"><p>Choose a Loyalty Quest to load its contacts.</p></div>
        </div>
        <div class="mg-form-status" data-lqd-invite-message role="status"></div>
        <button class="mg-btn mg-btn-primary" type="submit" data-lqd-send disabled>Queue invitations</button>
      </form>
    </div>
  </section>

  <section class="mg-app-panel mg-lqd-history-panel">
    <div class="mg-app-panel-head">
      <div>
        <span class="mg-eyebrow">Transactional evidence</span>
        <h2>Delivery history</h2>
        <p>Queued, retried, delivered, failed, suppressed, and dead-letter jobs.</p>
      </div>
      <label class="mg-lqd-filter">Status
        <select data-lqd-status-filter>
          <option value="all">All</option>
          <option value="queued">Queued</option>
          <option value="processing">Processing</option>
          <option value="retrying">Retrying</option>
          <option value="delivered">Delivered</option>
          <option value="failed">Failed</option>
          <option value="dead_letter">Dead letter</option>
          <option value="suppressed">Suppressed</option>
        </select>
      </label>
    </div>
    <div class="mg-app-panel-body">
      <div class="mg-lqd-history" data-lqd-history aria-live="polite">
        <div class="mg-empty-state"><p>Loading delivery history…</p></div>
      </div>
    </div>
  </section>
</div>
