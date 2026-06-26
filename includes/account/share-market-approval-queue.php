<?php
declare(strict_types=1);
?>
<style>
.sm-approval{display:grid;gap:18px;margin-top:18px;padding:24px;border:1px solid #dbe5f1;border-radius:24px;background:#fff;box-shadow:0 18px 55px rgba(15,23,42,.06)}.sm-approval *{box-sizing:border-box}.sm-approval-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.sm-approval-head h2{margin:0;color:#050505;font-size:30px;line-height:.98;letter-spacing:-.055em;font-weight:950}.sm-approval-head p{max-width:780px;margin:12px 0 0;color:#475569;font-size:13px;line-height:1.5;font-weight:650}.sm-approval-actions{display:flex;flex-wrap:wrap;gap:8px}.sm-approval-button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 14px;border:1px solid #dbe5f1;border-radius:11px;background:#fff;color:#111827;cursor:pointer;font-size:11px;font-weight:950}.sm-approval-button.primary{background:#050505;color:#fff;border-color:#050505}.sm-approval-button.danger{background:#fff1f2;color:#9f1239;border-color:#fecdd3}.sm-approval-button[disabled]{opacity:.5;cursor:not-allowed}.sm-approval-summary{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:9px}.sm-approval-stat{padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc}.sm-approval-stat span{display:block;color:#64748b;font-size:9px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.sm-approval-stat strong{display:block;margin-top:9px;color:#050505;font-size:24px;line-height:.9;font-weight:950}.sm-approval-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc}.sm-approval-toolbar select{min-height:38px;padding:0 11px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#0f172a;font-size:12px;font-weight:800}.sm-approval-status{color:#64748b;font-size:12px;font-weight:750}.sm-approval-list{display:grid;gap:10px}.sm-approval-empty{padding:24px;border:1px dashed #cbd5e1;border-radius:18px;background:#f8fafc;color:#64748b;text-align:center;font-size:13px;font-weight:750}.sm-approval-card{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(220px,.7fr) auto;gap:14px;align-items:center;padding:16px;border:1px solid #e2e8f0;border-radius:18px;background:#fff}.sm-approval-card.is-escalated{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.08)}.sm-approval-card-main small{display:block;color:#64748b;font-size:9px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.sm-approval-card-main strong{display:block;margin-top:7px;color:#0f172a;font-size:15px;font-weight:950}.sm-approval-card-main p{margin:7px 0 0;color:#64748b;font-size:11px;line-height:1.4;font-weight:650}.sm-approval-card-meta{display:grid;gap:7px}.sm-approval-card-meta span{color:#475569;font-size:11px;font-weight:750}.sm-approval-badge{display:inline-flex;width:max-content;align-items:center;min-height:25px;padding:0 9px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:9px;font-weight:950;letter-spacing:.05em;text-transform:uppercase}.sm-approval-badge.is-approved{background:#dcfce7;color:#166534}.sm-approval-badge.is-rejected,.sm-approval-badge.is-cancelled,.sm-approval-badge.is-expired{background:#fee2e2;color:#991b1b}.sm-approval-badge.is-awaiting-first-approval,.sm-approval-badge.is-awaiting-second-approval{background:#fef3c7;color:#92400e}.sm-approval-modal[hidden]{display:none}.sm-approval-modal{position:fixed;inset:0;z-index:10030;display:grid;place-items:center;padding:20px}.sm-approval-backdrop{position:absolute;inset:0;border:0;background:rgba(2,6,23,.75);backdrop-filter:blur(8px)}.sm-approval-dialog{position:relative;z-index:1;width:min(960px,100%);max-height:92vh;overflow:auto;border-radius:24px;background:#fff;box-shadow:0 30px 100px rgba(2,6,23,.5)}.sm-approval-dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:22px 24px;background:#050505;color:#fff}.sm-approval-dialog-head span{display:block;color:#facc15;font-size:9px;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.sm-approval-dialog-head h3{margin:8px 0 0;color:#fff;font-size:25px;line-height:1;font-weight:950;letter-spacing:-.05em}.sm-approval-close{width:38px;height:38px;border:1px solid rgba(255,255,255,.18);border-radius:12px;background:rgba(255,255,255,.08);color:#fff;cursor:pointer;font-size:22px}.sm-approval-body{display:grid;gap:18px;padding:22px 24px}.sm-approval-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.sm-approval-info{padding:13px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.sm-approval-info span{display:block;color:#64748b;font-size:9px;font-weight:950;text-transform:uppercase}.sm-approval-info strong{display:block;margin-top:7px;overflow-wrap:anywhere;color:#0f172a;font-size:12px;font-weight:900}.sm-approval-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sm-approval-field{display:grid;gap:7px}.sm-approval-field.is-wide{grid-column:1/-1}.sm-approval-field label{color:#334155;font-size:10px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.sm-approval-field input,.sm-approval-field select,.sm-approval-field textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:11px;background:#fff;color:#0f172a;font:inherit;font-size:13px;font-weight:650}.sm-approval-field textarea{min-height:96px;resize:vertical}.sm-approval-confirm{padding:14px;border:1px solid #fecaca;border-radius:16px;background:#fff1f2}.sm-approval-confirm code{display:inline-block;margin-top:7px;padding:5px 8px;border-radius:7px;background:#fff;color:#9f1239;font-weight:950}.sm-approval-timeline{display:grid;gap:8px}.sm-approval-timeline-item{display:grid;grid-template-columns:150px 1fr;gap:12px;padding:12px;border-left:3px solid #cbd5e1;background:#f8fafc;border-radius:0 12px 12px 0}.sm-approval-timeline-item strong{color:#0f172a;font-size:11px;font-weight:950}.sm-approval-timeline-item span{display:block;color:#64748b;font-size:10px;line-height:1.4;font-weight:700}.sm-approval-json{max-height:260px;overflow:auto;margin:0;padding:14px;border-radius:14px;background:#07110b;color:#d1fae5;font:11px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap}.sm-approval-warning{padding:13px;border:1px solid #fde68a;border-radius:14px;background:#fffbeb;color:#854d0e;font-size:11px;line-height:1.45;font-weight:750}.sm-approval-execution{padding:14px;border:1px solid #bfdbfe;border-radius:16px;background:#eff6ff}.sm-approval-execution h4{margin:0 0 10px;color:#0f172a}.sm-approval-feedback{min-height:20px;color:#475569;font-size:12px;font-weight:750}.sm-approval-feedback.is-error{color:#b91c1c}.sm-approval-feedback.is-success{color:#166534}@media(max-width:1100px){.sm-approval-summary{grid-template-columns:repeat(4,minmax(0,1fr))}.sm-approval-card{grid-template-columns:1fr auto}.sm-approval-card-meta{grid-column:1/-1;grid-row:2}}@media(max-width:720px){.sm-approval{padding:18px}.sm-approval-head,.sm-approval-toolbar{display:grid}.sm-approval-summary,.sm-approval-grid,.sm-approval-form{grid-template-columns:1fr 1fr}.sm-approval-card{grid-template-columns:1fr}.sm-approval-card-meta{grid-column:auto;grid-row:auto}.sm-approval-field.is-wide{grid-column:1/-1}}@media(max-width:520px){.sm-approval-summary,.sm-approval-grid,.sm-approval-form{grid-template-columns:1fr}.sm-approval-field.is-wide{grid-column:auto}.sm-approval-dialog-head,.sm-approval-body{padding:18px}.sm-approval-timeline-item{grid-template-columns:1fr}}
</style>

<section class="sm-approval" data-share-approval-root>
  <header class="sm-approval-head">
    <div>
      <h2>Approval Queue &amp; Ledger Review</h2>
      <p>Validated actions enter an append-only review queue. Requesters cannot approve their own actions. Approved requests can now generate a locked execution handoff preview, but no Share Market action can execute from this screen.</p>
    </div>
    <div class="sm-approval-actions">
      <button class="sm-approval-button primary" type="button" data-share-submit-latest disabled>Submit latest validated action</button>
      <button class="sm-approval-button" type="button" data-share-approval-refresh>Refresh queue</button>
    </div>
  </header>

  <section class="sm-approval-summary" aria-label="Approval queue summary">
    <?php foreach (['total'=>'Total','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled','expired'=>'Expired','escalated'=>'Escalated'] as $key => $label): ?>
      <article class="sm-approval-stat"><span><?= mg_e($label) ?></span><strong data-share-summary="<?= mg_e($key) ?>">0</strong></article>
    <?php endforeach; ?>
  </section>

  <div class="sm-approval-toolbar">
    <select data-share-approval-filter aria-label="Filter approval requests">
      <option value="all">All requests</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
      <option value="cancelled">Cancelled</option>
      <option value="expired">Expired</option>
      <option value="escalated">Escalated</option>
    </select>
    <span class="sm-approval-status" data-share-approval-status>Loading approval queue…</span>
  </div>

  <div class="sm-approval-list" data-share-approval-list><div class="sm-approval-empty">Loading approval requests…</div></div>
  <p class="sm-approval-warning"><strong>SQL-backed review:</strong> approval requests and decisions are persisted in the Buy-In SQL tables. Execution prep is dry-run only; balances, pools, treasuries, series, holder positions, and resale listings remain untouched.</p>
</section>

<div class="sm-approval-modal" data-share-request-modal hidden aria-hidden="true">
  <button class="sm-approval-backdrop" type="button" data-share-request-close aria-label="Close approval request"></button>
  <section class="sm-approval-dialog" role="dialog" aria-modal="true" aria-labelledby="sm-request-title">
    <header class="sm-approval-dialog-head"><div><span>Maker step</span><h3 id="sm-request-title">Submit for independent approval</h3></div><button class="sm-approval-close" type="button" data-share-request-close aria-label="Close">×</button></header>
    <form class="sm-approval-body" data-share-request-form>
      <div class="sm-approval-grid">
        <div class="sm-approval-info"><span>Action</span><strong data-share-request-action>—</strong></div>
        <div class="sm-approval-info"><span>Target</span><strong data-share-request-target>—</strong></div>
        <div class="sm-approval-info"><span>Approvals required</span><strong data-share-request-approvals>—</strong></div>
      </div>
      <div class="sm-approval-form">
        <div class="sm-approval-field" data-share-current-balance-field>
          <label for="sm-current-balance">Current balance</label>
          <input id="sm-current-balance" name="current_balance" type="number" min="0" max="1000000000000" step="1" inputmode="numeric" value="0">
        </div>
        <div class="sm-approval-field">
          <label for="sm-request-password">Fresh password verification</label>
          <input id="sm-request-password" name="password" type="password" autocomplete="current-password" required>
        </div>
      </div>
      <p class="sm-approval-warning">The request expires after 24 hours. Submitting creates an approval record only; it does not execute the underlying Share Market action.</p>
      <div class="sm-approval-feedback" data-share-request-feedback aria-live="polite"></div>
      <button class="sm-approval-button primary" type="submit" data-share-request-submit>Create approval request</button>
    </form>
  </section>
</div>

<div class="sm-approval-modal" data-share-review-modal hidden aria-hidden="true">
  <button class="sm-approval-backdrop" type="button" data-share-review-close aria-label="Close approval review"></button>
  <section class="sm-approval-dialog" role="dialog" aria-modal="true" aria-labelledby="sm-review-title">
    <header class="sm-approval-dialog-head"><div><span>Checker step</span><h3 id="sm-review-title" data-share-review-title>Approval request</h3></div><button class="sm-approval-close" type="button" data-share-review-close aria-label="Close">×</button></header>
    <div class="sm-approval-body">
      <div class="sm-approval-grid" data-share-review-grid></div>
      <div><h4>Projected balance impact</h4><div class="sm-approval-grid" data-share-projection-grid></div></div>
      <div class="sm-approval-execution">
        <h4>Locked execution prep</h4>
        <div class="sm-approval-grid" data-share-execution-grid><div class="sm-approval-info"><span>Status</span><strong>Load preview after approval review.</strong></div></div>
        <div class="sm-approval-actions"><button class="sm-approval-button" type="button" data-share-execution-preview>Load execution preview</button><button class="sm-approval-button danger" type="button" data-share-execution-runner>Open locked runner</button></div>
        <form data-share-execution-form hidden>
          <input type="hidden" name="request_id">
          <div class="sm-approval-field sm-approval-confirm"><label for="sm-execution-confirmation">Locked runner confirmation</label><span>Type:</span><code>EXECUTION LOCKED</code><input id="sm-execution-confirmation" name="confirmation" autocomplete="off" spellcheck="false" required></div>
          <button class="sm-approval-button danger" type="submit">Invoke locked runner stub</button>
        </form>
        <div class="sm-approval-feedback" data-share-execution-feedback aria-live="polite"></div>
        <details><summary><strong>Execution preview JSON</strong></summary><pre class="sm-approval-json" data-share-execution-json>{}</pre></details>
      </div>
      <div><h4>Immutable timeline</h4><div class="sm-approval-timeline" data-share-review-timeline></div></div>
      <details><summary><strong>Validated manifest</strong></summary><pre class="sm-approval-json" data-share-review-json></pre></details>
      <form data-share-decision-form hidden>
        <input type="hidden" name="request_id">
        <input type="hidden" name="decision">
        <div class="sm-approval-form">
          <div class="sm-approval-field is-wide"><label for="sm-decision-note">Decision note</label><textarea id="sm-decision-note" name="note" maxlength="1000" required></textarea></div>
          <div class="sm-approval-field"><label for="sm-decision-password">Fresh password verification</label><input id="sm-decision-password" name="password" type="password" autocomplete="current-password" required></div>
          <div class="sm-approval-field sm-approval-confirm"><label for="sm-decision-confirmation">Typed confirmation</label><span>Type:</span><code data-share-decision-phrase>—</code><input id="sm-decision-confirmation" name="confirmation" autocomplete="off" spellcheck="false" required></div>
        </div>
        <div class="sm-approval-feedback" data-share-decision-feedback aria-live="polite"></div>
        <div class="sm-approval-actions"><button class="sm-approval-button primary" type="submit" data-share-decision-submit>Record decision</button><button class="sm-approval-button" type="button" data-share-decision-cancel>Cancel</button></div>
      </form>
      <div class="sm-approval-actions" data-share-review-actions></div>
    </div>
  </section>
</div>
