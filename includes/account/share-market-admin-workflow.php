<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/share-market/admin-actions.php';

$shareMarketActionDefinitions = mg_share_market_admin_action_definitions();
$shareMarketReasonCodes = mg_share_market_admin_reason_codes();
$shareMarketUiDefinitions = [];
foreach ($shareMarketActionDefinitions as $actionKey => $definition) {
    $shareMarketUiDefinitions[$actionKey] = [
        'key' => $actionKey,
        'label' => $definition['label'],
        'event_type' => $definition['event_type'],
        'target_type' => $definition['target_type'],
        'default_target_id' => $definition['default_target_id'],
        'risk' => $definition['risk'],
        'confirmation' => $definition['confirmation'],
        'amount_required' => $definition['amount_required'],
        'current_state_required' => $definition['current_state_required'],
        'allowed_from_states' => $definition['allowed_from_states'],
        'next_state' => $definition['next_state'],
        'required_approvals' => $definition['required_approvals'],
        'super_admin_required' => $definition['super_admin_required'],
        'note_required' => $definition['note_required'],
        'description' => $definition['description'],
    ];
}
?>
<style>
.sm-workflow{display:grid;gap:18px;margin-top:18px;padding:24px;border:1px solid #dbe5f1;border-radius:24px;background:linear-gradient(145deg,#fff,#f8fafc);box-shadow:0 18px 55px rgba(15,23,42,.06)}.sm-workflow *{box-sizing:border-box}.sm-workflow-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.sm-workflow-head h2{margin:0;color:#050505;font-size:30px;line-height:.98;letter-spacing:-.055em;font-weight:950}.sm-workflow-head p{max-width:760px;margin:12px 0 0;color:#475569;font-size:13px;line-height:1.5;font-weight:650}.sm-workflow-state{display:inline-flex;align-items:center;min-height:30px;padding:0 11px;border:1px solid #fed7aa;border-radius:999px;background:#fff7ed;color:#9a3412;font-size:10px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}.sm-workflow-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.sm-workflow-action{display:grid;grid-template-rows:auto auto 1fr auto;gap:9px;min-height:190px;padding:16px;border:1px solid #e2e8f0;border-radius:18px;background:#fff}.sm-workflow-action.is-critical{border-color:#fecaca;background:#fffafa}.sm-workflow-action.is-high{border-color:#fed7aa;background:#fffdf8}.sm-workflow-action small{color:#64748b;font-size:9px;font-weight:950;letter-spacing:.1em;text-transform:uppercase}.sm-workflow-action strong{color:#0f172a;font-size:14px;line-height:1.15;font-weight:950}.sm-workflow-action p{margin:0;color:#64748b;font-size:11px;line-height:1.42;font-weight:650}.sm-workflow-action footer{display:flex;align-items:center;justify-content:space-between;gap:8px}.sm-workflow-badge{display:inline-flex;align-items:center;min-height:24px;padding:0 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:9px;font-weight:950;text-transform:uppercase}.sm-workflow-badge.is-critical{background:#fee2e2;color:#991b1b}.sm-workflow-badge.is-high{background:#ffedd5;color:#9a3412}.sm-workflow-open{min-height:34px;padding:0 11px;border:0;border-radius:10px;background:#050505;color:#fff;cursor:pointer;font-size:10px;font-weight:950}.sm-workflow-open:hover{transform:translateY(-1px)}.sm-workflow-modal[hidden]{display:none}.sm-workflow-modal{position:fixed;inset:0;z-index:10020;display:grid;place-items:center;padding:20px}.sm-workflow-backdrop{position:absolute;inset:0;border:0;background:rgba(2,6,23,.72);backdrop-filter:blur(8px)}.sm-workflow-dialog{position:relative;z-index:1;width:min(920px,100%);max-height:min(92vh,900px);overflow:auto;border:1px solid rgba(255,255,255,.16);border-radius:24px;background:#fff;box-shadow:0 30px 100px rgba(2,6,23,.45)}.sm-workflow-dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid #e2e8f0;background:#050505;color:#fff}.sm-workflow-dialog-head span{display:block;color:#facc15;font-size:9px;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.sm-workflow-dialog-head h3{margin:8px 0 0;color:#fff;font-size:25px;line-height:1;font-weight:950;letter-spacing:-.05em}.sm-workflow-close{width:38px;height:38px;border:1px solid rgba(255,255,255,.18);border-radius:12px;background:rgba(255,255,255,.08);color:#fff;cursor:pointer;font-size:22px}.sm-workflow-form{display:grid;gap:16px;padding:22px 24px}.sm-workflow-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.sm-workflow-summary div{padding:12px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.sm-workflow-summary span{display:block;color:#64748b;font-size:9px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.sm-workflow-summary strong{display:block;margin-top:7px;color:#0f172a;font-size:12px;font-weight:950}.sm-workflow-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sm-workflow-field{display:grid;gap:7px}.sm-workflow-field.is-wide{grid-column:1/-1}.sm-workflow-field label{color:#334155;font-size:10px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.sm-workflow-field input,.sm-workflow-field select,.sm-workflow-field textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:11px;background:#fff;color:#0f172a;font:inherit;font-size:13px;font-weight:650}.sm-workflow-field textarea{min-height:96px;resize:vertical}.sm-workflow-field input:focus,.sm-workflow-field select:focus,.sm-workflow-field textarea:focus{outline:3px solid rgba(37,99,235,.13);border-color:#2563eb}.sm-workflow-confirm{padding:14px;border:1px solid #fecaca;border-radius:16px;background:#fff1f2}.sm-workflow-confirm code{display:inline-block;margin-top:7px;padding:5px 8px;border-radius:7px;background:#fff;color:#9f1239;font-weight:950}.sm-workflow-status{min-height:20px;color:#475569;font-size:12px;font-weight:750}.sm-workflow-status.is-error{color:#b91c1c}.sm-workflow-status.is-success{color:#166534}.sm-workflow-submit{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border:0;border-radius:12px;background:#050505;color:#fff;cursor:pointer;font-size:12px;font-weight:950}.sm-workflow-submit[disabled]{opacity:.55;cursor:not-allowed}.sm-workflow-preview[hidden]{display:none}.sm-workflow-preview{display:grid;gap:12px;padding:18px;border:1px solid #bbf7d0;border-radius:18px;background:#f0fdf4}.sm-workflow-preview h4{margin:0;color:#14532d;font-size:17px;font-weight:950}.sm-workflow-preview-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.sm-workflow-preview-grid div{padding:10px;border:1px solid #dcfce7;border-radius:12px;background:#fff}.sm-workflow-preview-grid span{display:block;color:#64748b;font-size:9px;font-weight:950;text-transform:uppercase}.sm-workflow-preview-grid strong{display:block;margin-top:6px;overflow-wrap:anywhere;color:#0f172a;font-size:11px;font-weight:900}.sm-workflow-manifest{max-height:240px;overflow:auto;margin:0;padding:14px;border-radius:14px;background:#07110b;color:#d1fae5;font:11px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap}.sm-workflow-warning{padding:13px;border:1px solid #fde68a;border-radius:14px;background:#fffbeb;color:#854d0e;font-size:11px;line-height:1.45;font-weight:750}@media(max-width:1180px){.sm-workflow-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:820px){.sm-workflow-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sm-workflow-summary,.sm-workflow-preview-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.sm-workflow{padding:18px}.sm-workflow-head{display:grid}.sm-workflow-grid,.sm-workflow-fields,.sm-workflow-summary,.sm-workflow-preview-grid{grid-template-columns:1fr}.sm-workflow-field.is-wide{grid-column:auto}.sm-workflow-dialog{border-radius:18px}.sm-workflow-dialog-head,.sm-workflow-form{padding:18px}}
</style>

<section class="sm-workflow" data-share-market-workflow>
  <header class="sm-workflow-head">
    <div>
      <h2>Guarded Action Console</h2>
      <p>Prepare and validate a ledger action before the database layer exists. Every action enforces a target, reason code, typed confirmation, state rules, approval count, and immutable event manifest. Validation never changes balances or states.</p>
    </div>
    <span class="sm-workflow-state">Dry run only · mutations locked</span>
  </header>

  <div class="sm-workflow-grid">
    <?php foreach ($shareMarketUiDefinitions as $actionKey => $definition): ?>
      <article class="sm-workflow-action is-<?= mg_e((string)$definition['risk']) ?>">
        <small><?= mg_e((string)$definition['target_type']) ?> · <?= mg_e((string)$definition['event_type']) ?></small>
        <strong><?= mg_e((string)$definition['label']) ?></strong>
        <p><?= mg_e((string)$definition['description']) ?></p>
        <footer>
          <span class="sm-workflow-badge is-<?= mg_e((string)$definition['risk']) ?>"><?= mg_e((string)$definition['risk']) ?> · <?= mg_e((string)$definition['required_approvals']) ?> approval<?= (int)$definition['required_approvals'] === 1 ? '' : 's' ?></span>
          <button class="sm-workflow-open" type="button" data-share-action="<?= mg_e($actionKey) ?>">Prepare</button>
        </footer>
      </article>
    <?php endforeach; ?>
  </div>

  <p class="sm-workflow-warning"><strong>No execution endpoint exists in this phase.</strong> The console produces a validated, hashable event manifest only. Actual mint, burn, pause, freeze, allocation, approval, and reversal operations remain impossible until the final SQL schema and transaction layer are installed.</p>
</section>

<div class="sm-workflow-modal" data-share-action-modal hidden aria-hidden="true">
  <button class="sm-workflow-backdrop" type="button" data-share-action-close aria-label="Close Share Market action console"></button>
  <section class="sm-workflow-dialog" role="dialog" aria-modal="true" aria-labelledby="sm-workflow-title">
    <header class="sm-workflow-dialog-head">
      <div><span>Validated ledger action</span><h3 id="sm-workflow-title" data-share-action-title>Prepare action</h3></div>
      <button class="sm-workflow-close" type="button" data-share-action-close aria-label="Close">×</button>
    </header>
    <form class="sm-workflow-form" data-share-action-form novalidate>
      <input type="hidden" name="action" value="">
      <div class="sm-workflow-summary">
        <div><span>Event type</span><strong data-share-event-type>—</strong></div>
        <div><span>Risk</span><strong data-share-risk>—</strong></div>
        <div><span>Approvals</span><strong data-share-approvals>—</strong></div>
        <div><span>Execution</span><strong>Locked</strong></div>
      </div>
      <div class="sm-workflow-fields">
        <div class="sm-workflow-field is-wide">
          <label for="sm-action-select">Admin action</label>
          <select id="sm-action-select" name="action_select" data-share-action-select>
            <?php foreach ($shareMarketUiDefinitions as $actionKey => $definition): ?><option value="<?= mg_e($actionKey) ?>"><?= mg_e((string)$definition['label']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="sm-workflow-field">
          <label for="sm-target-id">Target identifier</label>
          <input id="sm-target-id" name="target_id" maxlength="120" autocomplete="off" required placeholder="platform-master or public ID">
        </div>
        <div class="sm-workflow-field" data-share-amount-field>
          <label for="sm-amount">Share / credit amount</label>
          <input id="sm-amount" name="amount" type="number" min="1" max="1000000000000" step="1" inputmode="numeric">
        </div>
        <div class="sm-workflow-field" data-share-state-field>
          <label for="sm-current-state">Current state</label>
          <select id="sm-current-state" name="current_state" data-share-current-state></select>
        </div>
        <div class="sm-workflow-field">
          <label for="sm-reason-code">Reason code</label>
          <select id="sm-reason-code" name="reason_code" required>
            <option value="">Select reason</option>
            <?php foreach ($shareMarketReasonCodes as $reasonCode): ?><option value="<?= mg_e($reasonCode) ?>"><?= mg_e(ucwords(str_replace('_', ' ', $reasonCode))) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="sm-workflow-field is-wide">
          <label for="sm-admin-note">Internal admin note</label>
          <textarea id="sm-admin-note" name="admin_note" maxlength="1000" placeholder="Document the business, security, or operational reason."></textarea>
        </div>
        <div class="sm-workflow-field is-wide sm-workflow-confirm">
          <label for="sm-confirmation">Typed confirmation</label>
          <span>Type the exact phrase:</span>
          <code data-share-confirmation-phrase>—</code>
          <input id="sm-confirmation" name="confirmation" autocomplete="off" spellcheck="false" required>
        </div>
      </div>
      <div class="sm-workflow-status" data-share-action-status aria-live="polite"></div>
      <button class="sm-workflow-submit" type="submit" data-share-action-submit>Validate dry-run manifest</button>
      <section class="sm-workflow-preview" data-share-action-preview hidden>
        <h4>Validated — no mutation performed</h4>
        <div class="sm-workflow-preview-grid">
          <div><span>Manifest ID</span><strong data-share-preview-id>—</strong></div>
          <div><span>Event</span><strong data-share-preview-event>—</strong></div>
          <div><span>Target</span><strong data-share-preview-target>—</strong></div>
          <div><span>State transition</span><strong data-share-preview-state>—</strong></div>
          <div><span>Approvals</span><strong data-share-preview-approvals>—</strong></div>
          <div><span>Payload hash</span><strong data-share-preview-hash>—</strong></div>
        </div>
        <pre class="sm-workflow-manifest" data-share-manifest-json></pre>
      </section>
    </form>
  </section>
</div>

<script type="application/json" id="mg-share-market-action-definitions"><?= json_encode($shareMarketUiDefinitions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
