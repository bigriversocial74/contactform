<?php
declare(strict_types=1);
?>
<section class="mg-agent-messages-page" data-merchant-agent-messages>
  <header class="mg-agent-messages-hero">
    <div>
      <span class="mg-eyebrow">Merchant-controlled customer communication</span>
      <h1>Agent Message Draft Outbox</h1>
      <p>Review, edit, approve, send, discard, or convert agent-written customer messages into follow-up tasks before any customer communication is finalized.</p>
    </div>
    <div class="mg-agent-messages-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php">Execution Center</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review Queue</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-message-refresh>Refresh outbox</button>
    </div>
  </header>

  <section class="mg-agent-messages-kpis" aria-label="Agent message draft summary">
    <article><strong data-message-total>—</strong><span>Total drafts</span></article>
    <article><strong data-message-draft>—</strong><span>Draft</span></article>
    <article><strong data-message-approved>—</strong><span>Approved</span></article>
    <article><strong data-message-sent>—</strong><span>Sent</span></article>
    <article><strong data-message-discarded>—</strong><span>Discarded</span></article>
  </section>

  <section class="mg-agent-messages-grid">
    <article class="mg-app-panel mg-agent-messages-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Outbox Filters</h2>
          <p>Focus on draft, edited, approved, sent, discarded, or follow-up converted messages.</p>
        </div>
      </div>
      <div class="mg-agent-message-filters" data-agent-message-filters>
        <button type="button" class="is-active" data-message-filter="all">All</button>
        <button type="button" data-message-filter="draft">Draft</button>
        <button type="button" data-message-filter="edited">Edited</button>
        <button type="button" data-message-filter="approved">Approved</button>
        <button type="button" data-message-filter="sent">Sent</button>
        <button type="button" data-message-filter="discarded">Discarded</button>
        <button type="button" data-message-filter="followup_created">Follow-up created</button>
      </div>
    </article>

    <article class="mg-app-panel mg-agent-messages-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Communication Guardrails</h2>
          <p>Agents can draft messages, but merchants control edits, final approval, delivery marking, and follow-up conversion.</p>
        </div>
      </div>
      <div class="mg-agent-message-model">
        <span>Drafted by agent</span>
        <span>Edited by merchant</span>
        <span>Approved by merchant</span>
        <span>Logged to CRM</span>
      </div>
    </article>
  </section>

  <article class="mg-app-panel mg-agent-messages-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Draft Messages</h2>
        <p>Each draft shows the customer, campaign source, message body, guardrail applied, and the available merchant-controlled actions.</p>
      </div>
      <p class="mg-form-status" data-agent-message-status></p>
    </div>
    <div class="mg-agent-message-list" data-agent-message-list>
      <div class="mg-empty-state"><strong>Loading agent message drafts…</strong></div>
    </div>
  </article>
</section>
