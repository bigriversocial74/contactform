<?php
declare(strict_types=1);
?>
<article class="mg-app-panel mg-agent-policy-panel" data-agent-policy-control>
  <div class="mg-app-panel-head">
    <div>
      <span class="mg-eyebrow">Merchant agent policy layer</span>
      <h2>Agent Control Center</h2>
      <p>Manage memory, policy guardrails, approval rules, and the audit trail for the merchant agent.</p>
    </div>
    <div class="mg-crm-tab-actions">
      <button class="mg-btn mg-btn-secondary" type="button" data-agent-policy-refresh>Refresh policy</button>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-policy-save>Save policy</button>
    </div>
  </div>
  <div class="mg-agent-policy-grid">
    <section class="mg-agent-policy-card">
      <h3>Policy Rules</h3>
      <div class="mg-agent-policy-form">
        <label><input type="checkbox" data-policy-field="enabled"> Agent enabled</label>
        <label><input type="checkbox" data-policy-field="memory_learning_enabled"> Memory learning enabled</label>
        <label><input type="checkbox" data-policy-field="auto_defer_high_risk"> Auto-defer high risk</label>
        <label><input type="checkbox" data-policy-field="admin_review_high_risk"> Escalate high risk to admin review</label>
        <label>Maximum risk level<select data-policy-field="max_risk_level"><option value="low">Low only</option><option value="medium">Medium or lower</option><option value="high">High allowed with review</option></select></label>
        <label>Minimum confidence<input type="number" min="0" max="100" step="5" data-policy-field="min_confidence_percent"></label>
        <label>Policy note<textarea data-policy-field="note" maxlength="500"></textarea></label>
      </div>
    </section>
    <section class="mg-agent-policy-card">
      <h3>Allowed Actions</h3>
      <p>Only selected action keys can be recommended by the agent.</p>
      <div class="mg-agent-policy-actions" data-policy-allowed-actions></div>
    </section>
    <section class="mg-agent-policy-card">
      <h3>Avoid Action Types</h3>
      <p>Actions marked here are de-prioritized in future recommendations.</p>
      <div class="mg-agent-policy-actions" data-policy-avoid-actions></div>
    </section>
    <section class="mg-agent-policy-card">
      <h3>Memory Controls</h3>
      <div class="mg-agent-policy-memory-actions">
        <button class="mg-btn mg-btn-secondary" type="button" data-agent-memory-control="pause_learning">Pause learning</button>
        <button class="mg-btn mg-btn-secondary" type="button" data-agent-memory-control="resume_learning">Resume learning</button>
        <button class="mg-btn mg-btn-ghost" type="button" data-agent-memory-control="reset_memory">Reset memory view</button>
      </div>
      <div class="mg-agent-policy-memory-list" data-policy-memory-list><div class="mg-empty-state"><strong>Loading memory controls…</strong></div></div>
    </section>
  </div>
  <section class="mg-agent-policy-audit">
    <div class="mg-app-panel-head is-compact"><div><h3>Policy / Memory Audit Trail</h3><p>Every control change is logged to campaign events.</p></div></div>
    <div class="mg-agent-policy-audit-list" data-policy-audit-list><div class="mg-empty-state"><strong>Loading audit trail…</strong></div></div>
  </section>
  <p class="mg-form-status" data-agent-policy-status role="status"></p>
</article>
