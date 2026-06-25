<?php
declare(strict_types=1);

$shareMarketAdminStats = [
    ['label' => 'Platform Shares', 'value' => '0', 'detail' => 'Total master pool capacity', 'tone' => 'neutral'],
    ['label' => 'Available Pool', 'value' => '0', 'detail' => 'Unallocated platform inventory', 'tone' => 'neutral'],
    ['label' => 'Allocated Credits', 'value' => '0', 'detail' => 'Merchant treasury credits', 'tone' => 'neutral'],
    ['label' => 'Live Series', 'value' => '0', 'detail' => 'Approved public markets', 'tone' => 'neutral'],
    ['label' => 'Circulating Shares', 'value' => '0', 'detail' => 'Fan-held market shares', 'tone' => 'neutral'],
    ['label' => 'Risk Flags', 'value' => '0', 'detail' => 'Open review items', 'tone' => 'safe'],
];

$platformControls = [
    ['title' => 'Create master pool', 'detail' => 'Initialize the Microgifter-controlled platform share pool.', 'status' => 'Locked until schema'],
    ['title' => 'Add shares / mint credits', 'detail' => 'Increase platform capacity with an append-only ledger event.', 'status' => 'Requires approval'],
    ['title' => 'Burn shares', 'detail' => 'Permanently remove pool inventory or retired supply.', 'status' => 'Irreversible'],
    ['title' => 'Pause global pool', 'detail' => 'Stop new issuance without freezing existing ownership.', 'status' => 'Safety control'],
    ['title' => 'Freeze platform pool', 'detail' => 'Emergency lock on all share movement.', 'status' => 'Super admin'],
    ['title' => 'Publish proof hash', 'detail' => 'Future blockchain / audit proof anchoring.', 'status' => 'Future-ready'],
];

$participantStates = ['not_enrolled', 'interested', 'under_review', 'approved', 'active', 'paused', 'suspended', 'rejected', 'closed'];
$seriesStates = ['draft', 'submitted', 'approved', 'live', 'paused', 'frozen', 'closed', 'rejected', 'archived'];
$holderStates = ['active', 'listed', 'pending_transfer', 'pending_redemption', 'redeemed', 'burned', 'frozen', 'locked', 'reversed'];
$ledgerEvents = ['platform_pool_created', 'platform_pool_minted', 'platform_pool_burned', 'merchant_enrollment_approved', 'merchant_enrollment_paused', 'share_credits_allocated', 'share_credits_burned', 'series_approved', 'series_paused', 'series_resumed', 'holder_shares_frozen', 'holder_shares_released', 'redemption_item_approved', 'resale_disabled', 'dave_score_recalculated', 'dave_score_locked'];

$adminTabs = ['Overview', 'Platform Pool', 'Participants', 'Treasuries', 'Series', 'Holders', 'Redemptions', 'Resale', 'DAVE™ Scores', 'Risk', 'Audit Ledger', 'Settings'];
?>
<style>
.sm-admin,.sm-admin *{box-sizing:border-box}.sm-admin{--ink:#070707;--muted:#64748b;--line:#dbe5f1;--card:#fff;--soft:#f8fafc;--gold:#d9a735;--red:#dc2626;--green:#16a34a;--blue:#2563eb;display:grid;gap:18px;color:#111827;font-family:Inter,"Helvetica Neue",Arial,sans-serif}.sm-admin-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,.45fr);gap:20px;padding:30px;border:1px solid var(--line);border-radius:28px;background:radial-gradient(circle at 80% 20%,rgba(217,167,53,.26),transparent 30%),linear-gradient(135deg,#fff,#f8fafc);box-shadow:0 22px 70px rgba(15,23,42,.08)}.sm-admin-kicker{display:inline-flex;align-items:center;min-height:26px;width:max-content;padding:0 11px;border-radius:999px;background:#050505;color:#fff;font-size:10px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}.sm-admin-hero h1{max-width:760px;margin:18px 0 0;color:#050505;font-size:clamp(34px,3.4vw,58px);line-height:.94;letter-spacing:-.07em;font-weight:950}.sm-admin-hero p{max-width:780px;margin:16px 0 0;color:#334155;font-size:15px;line-height:1.5;font-weight:600}.sm-admin-alert{align-self:start;padding:18px;border:1px solid #fed7aa;border-radius:20px;background:#fff7ed;color:#7c2d12}.sm-admin-alert strong{display:block;font-size:14px;font-weight:950}.sm-admin-alert span{display:block;margin-top:8px;font-size:12px;line-height:1.45;font-weight:700}.sm-admin-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}.sm-admin-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:12px;border:1px solid var(--line);background:#fff;color:#111827;text-decoration:none!important;font-size:12px;font-weight:950}.sm-admin-btn.primary{background:#050505;color:#fff!important;border-color:#050505}.sm-admin-btn.danger{background:#fff1f2;color:#9f1239!important;border-color:#fecdd3}.sm-admin-tabs{display:flex;gap:8px;overflow:auto;padding:8px;border:1px solid var(--line);border-radius:18px;background:#fff}.sm-admin-tabs a{white-space:nowrap;padding:10px 12px;border-radius:12px;color:#475569;text-decoration:none!important;font-size:11px;font-weight:900}.sm-admin-tabs a.is-active,.sm-admin-tabs a:hover{background:#050505;color:#fff}.sm-admin-stat-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.sm-admin-stat{min-height:112px;padding:15px;border:1px solid var(--line);border-radius:18px;background:#fff}.sm-admin-stat span{display:block;color:#64748b;font-size:10px;font-weight:950;letter-spacing:.1em;text-transform:uppercase}.sm-admin-stat strong{display:block;margin-top:14px;color:#050505;font-size:30px;line-height:.9;font-weight:950;letter-spacing:-.055em}.sm-admin-stat small{display:block;margin-top:9px;color:#64748b;font-size:11px;line-height:1.3;font-weight:700}.sm-admin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.sm-admin-panel{padding:22px;border:1px solid var(--line);border-radius:22px;background:#fff;box-shadow:0 14px 42px rgba(15,23,42,.04)}.sm-admin-panel h2,.sm-admin-panel h3{margin:0;color:#050505;letter-spacing:-.055em;font-weight:950}.sm-admin-panel h2{font-size:27px;line-height:.98}.sm-admin-panel h3{font-size:21px;line-height:1}.sm-admin-panel p{margin:13px 0 0;color:#475569;font-size:13px;line-height:1.45;font-weight:600}.sm-admin-wide{display:grid;grid-template-columns:minmax(0,.92fr) minmax(360px,1.08fr);gap:12px}.sm-admin-control-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:18px}.sm-admin-control{display:grid;gap:8px;padding:15px;border:1px solid #e5edf7;border-radius:16px;background:#f8fafc}.sm-admin-control strong{color:#0f172a;font-size:13px;font-weight:950}.sm-admin-control span{color:#475569;font-size:12px;line-height:1.38;font-weight:650}.sm-admin-pill{display:inline-flex;width:max-content;align-items:center;min-height:24px;padding:0 9px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.06em}.sm-admin-pill.danger{background:#fee2e2;color:#991b1b}.sm-admin-pill.safe{background:#dcfce7;color:#166534}.sm-admin-table{display:grid;margin-top:18px;border:1px solid #e5edf7;border-radius:18px;overflow:hidden}.sm-admin-row{display:grid;grid-template-columns:minmax(150px,.8fr) minmax(0,1.2fr) 130px;gap:12px;align-items:center;min-height:58px;padding:12px 14px;border-top:1px solid #e5edf7;background:#fff}.sm-admin-row:first-child{border-top:0}.sm-admin-row span{display:block;color:#64748b;font-size:10px;font-weight:950;letter-spacing:.1em;text-transform:uppercase}.sm-admin-row strong{display:block;margin-top:4px;color:#111827;font-size:13px;font-weight:950}.sm-admin-row em{display:inline-flex;align-items:center;justify-content:center;min-height:28px;padding:0 10px;border-radius:999px;background:#f8f1df;color:#8a650c;font-style:normal;font-size:10px;font-weight:950}.sm-admin-dark{background:linear-gradient(135deg,#050505,#171717 60%,#050505);color:#fff}.sm-admin-dark h2,.sm-admin-dark h3{color:#fff}.sm-admin-dark p{color:rgba(255,255,255,.72)}.sm-admin-dark .sm-admin-control{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.14)}.sm-admin-dark .sm-admin-control strong{color:#fff}.sm-admin-dark .sm-admin-control span{color:rgba(255,255,255,.65)}.sm-admin-final{padding:24px;border:1px solid var(--line);border-radius:22px;background:#fff;text-align:center}.sm-admin-final h2{max-width:780px;margin:0 auto;color:#050505;font-size:clamp(28px,3vw,42px);line-height:.98;letter-spacing:-.065em;font-weight:950}.sm-admin-final p{max-width:760px;margin:15px auto 0;color:#475569;font-size:14px;line-height:1.5;font-weight:600}@media(max-width:1180px){.sm-admin-stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.sm-admin-grid{grid-template-columns:1fr 1fr}.sm-admin-hero,.sm-admin-wide{grid-template-columns:1fr}}@media(max-width:720px){.sm-admin-hero,.sm-admin-panel,.sm-admin-final{padding:20px}.sm-admin-stat-grid,.sm-admin-grid,.sm-admin-control-grid{grid-template-columns:1fr}.sm-admin-row{grid-template-columns:1fr}}
</style>

<section class="mg-app-panel mg-account-pane is-active sm-admin" data-account-pane="share-market-admin">
  <section class="sm-admin-hero">
    <div>
      <span class="sm-admin-kicker">Share Market Admin</span>
      <h1>Control the full DAVE™ share system before anything goes live.</h1>
      <p>This is the admin command center for Microgifter's optional Share Market program: platform pool, participant approval, merchant treasuries, market series, holder controls, redemption, resale, DAVE™ scores, risk, and the audit ledger.</p>
      <div class="sm-admin-actions"><a class="sm-admin-btn primary" href="#platform-pool">Review Platform Pool</a><a class="sm-admin-btn" href="#audit-ledger">Open Audit Ledger</a><a class="sm-admin-btn danger" href="#emergency-controls">Emergency Controls</a></div>
    </div>
    <aside class="sm-admin-alert"><strong>Schema pending</strong><span>The admin UI is intentionally database-light until the final SQL file is uploaded. All dangerous controls are mapped as ledger/event workflows, not direct balance edits.</span></aside>
  </section>

  <nav class="sm-admin-tabs" aria-label="Share Market Admin sections">
    <?php foreach ($adminTabs as $i => $tab): ?><a class="<?= $i === 0 ? 'is-active' : '' ?>" href="#<?= mg_e(strtolower(str_replace(['™', ' '], ['', '-'], $tab))) ?>"><?= mg_e($tab) ?></a><?php endforeach; ?>
  </nav>

  <section class="sm-admin-stat-grid" aria-label="Share Market Admin overview metrics">
    <?php foreach ($shareMarketAdminStats as $stat): ?>
      <article class="sm-admin-stat"><span><?= mg_e($stat['label']) ?></span><strong><?= mg_e($stat['value']) ?></strong><small><?= mg_e($stat['detail']) ?></small></article>
    <?php endforeach; ?>
  </section>

  <section class="sm-admin-wide" id="platform-pool">
    <article class="sm-admin-panel sm-admin-dark">
      <span class="sm-admin-kicker">Platform Pool</span>
      <h2>Microgifter controls master supply. Merchants receive credits. Fans hold market shares.</h2>
      <p>The platform pool is the root of trust. Admins should never directly edit balances. Every create, mint, burn, pause, freeze, allocation, redemption, or reversal must become a ledger event.</p>
      <div class="sm-admin-control-grid">
        <?php foreach ($platformControls as $control): ?>
          <div class="sm-admin-control"><strong><?= mg_e($control['title']) ?></strong><span><?= mg_e($control['detail']) ?></span><em class="sm-admin-pill <?= str_contains(strtolower($control['status']), 'irreversible') || str_contains(strtolower($control['status']), 'super') ? 'danger' : '' ?>"><?= mg_e($control['status']) ?></em></div>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="sm-admin-panel" id="participants">
      <h2>Participant approval states</h2>
      <p>Participation stays optional. Admin approval is required before a merchant can buy credits, assign supply, activate redemption, or launch public resale.</p>
      <div class="sm-admin-table">
        <?php foreach ($participantStates as $i => $state): ?>
          <div class="sm-admin-row"><div><span>State</span><strong><?= mg_e($state) ?></strong></div><div><span>Control meaning</span><strong><?= mg_e(match ($state) { 'not_enrolled' => 'Default account state', 'interested' => 'Merchant requested information', 'under_review' => 'Admin review in progress', 'approved' => 'Allowed to buy/hold credits', 'active' => 'Program enabled', 'paused' => 'Temporary stop', 'suspended' => 'Security or policy stop', 'rejected' => 'Not eligible', default => 'Program ended' }) ?></strong></div><em><?= $i < 3 ? 'Intake' : ($i < 5 ? 'Allowed' : 'Controlled') ?></em></div>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <section class="sm-admin-grid" id="treasuries">
    <article class="sm-admin-panel"><h3>Merchant treasury controls</h3><p>Allocate credits, reverse mistaken allocations, freeze treasury movement, burn unused credits, lock assigned credits, and inspect every event.</p><div class="sm-admin-table"><div class="sm-admin-row"><div><span>Action</span><strong>Allocate credits</strong></div><div><span>Rule</span><strong>Admin-approved ledger credit</strong></div><em>Ledger</em></div><div class="sm-admin-row"><div><span>Action</span><strong>Freeze treasury</strong></div><div><span>Rule</span><strong>Stops assignment and market launch</strong></div><em>Safety</em></div><div class="sm-admin-row"><div><span>Action</span><strong>Burn unused credits</strong></div><div><span>Rule</span><strong>Only unassigned credits can burn</strong></div><em>Supply</em></div></div></article>
    <article class="sm-admin-panel" id="series"><h3>Series review controls</h3><p>Draft, submitted, approved, live, paused, frozen, closed, rejected, and archived series states keep the system governed.</p><div class="sm-admin-table"><?php foreach ($seriesStates as $state): ?><div class="sm-admin-row"><div><span>Series state</span><strong><?= mg_e($state) ?></strong></div><div><span>Required guardrail</span><strong><?= mg_e(in_array($state, ['approved','live'], true) ? 'Admin approval required' : 'State transition ledger event') ?></strong></div><em><?= in_array($state, ['paused','frozen','rejected'], true) ? 'Control' : 'Flow' ?></em></div><?php endforeach; ?></div></article>
    <article class="sm-admin-panel" id="holders"><h3>Holder share controls</h3><p>Admin can freeze, reverse, burn redeemed shares, transfer back to treasury, lock resale, or lock redemption — never delete ownership records.</p><div class="sm-admin-table"><?php foreach ($holderStates as $state): ?><div class="sm-admin-row"><div><span>Holder state</span><strong><?= mg_e($state) ?></strong></div><div><span>Record policy</span><strong>Append-only ownership history</strong></div><em><?= in_array($state, ['burned','frozen','locked','reversed'], true) ? 'Admin' : 'Market' ?></em></div><?php endforeach; ?></div></article>
  </section>

  <section class="sm-admin-wide" id="dave-scores">
    <article class="sm-admin-panel">
      <h2>DAVE™ score admin</h2>
      <p>Admins should see DAVE™ scoring inputs, confidence, movement, and anomaly flags. Manual override should be rare, reason-coded, time-boxed, and double-approved.</p>
      <div class="sm-admin-table">
        <div class="sm-admin-row"><div><span>Attention</span><strong>Followers, visits, engagement</strong></div><div><span>Admin action</span><strong>Flag spike anomaly</strong></div><em>Signal</em></div>
        <div class="sm-admin-row"><div><span>Action</span><strong>Clicks, scans, signups, claims</strong></div><div><span>Admin action</span><strong>Recalculate score</strong></div><em>Signal</em></div>
        <div class="sm-admin-row"><div><span>Redemption</span><strong>Tickets, gifts, merch, VIP claims</strong></div><div><span>Admin action</span><strong>Lock score during review</strong></div><em>Utility</em></div>
        <div class="sm-admin-row"><div><span>Commerce</span><strong>Sales, volume, redemption value</strong></div><div><span>Admin action</span><strong>Add internal note</strong></div><em>Value</em></div>
        <div class="sm-admin-row"><div><span>Risk</span><strong>Opt-outs, failed delivery, expired rewards</strong></div><div><span>Admin action</span><strong>Require manual approval</strong></div><em>Risk</em></div>
      </div>
    </article>

    <article class="sm-admin-panel" id="risk">
      <h2>Risk dashboard</h2>
      <p>Risk controls should be available before money, resale, or redemption is live. The admin needs a fast path to pause resale, pause redemption, freeze a series, or escalate.</p>
      <div class="sm-admin-control-grid"><div class="sm-admin-control"><strong>Unusual price movement</strong><span>Floor or last-sale movement outside expected range.</span><em class="sm-admin-pill danger">Freeze series</em></div><div class="sm-admin-control"><strong>Holder concentration</strong><span>One account owns too much supply.</span><em class="sm-admin-pill danger">Review holder</em></div><div class="sm-admin-control"><strong>Rapid resale loop</strong><span>Potential wash-trading or circular trades.</span><em class="sm-admin-pill danger">Pause resale</em></div><div class="sm-admin-control"><strong>Low utility quality</strong><span>Series lacks meaningful redemption options.</span><em class="sm-admin-pill">Request catalog</em></div></div>
    </article>
  </section>

  <section class="sm-admin-wide" id="audit-ledger">
    <article class="sm-admin-panel sm-admin-dark">
      <span class="sm-admin-kicker">Audit Ledger</span>
      <h2>Every admin action becomes a signed event.</h2>
      <p>Minimum record: actor, role, target type, target ID, event type, old state, new state, amount, reason code, note, IP, user agent, previous hash, payload hash, and timestamp.</p>
      <div class="sm-admin-control-grid">
        <?php foreach (array_slice($ledgerEvents, 0, 8) as $event): ?><div class="sm-admin-control"><strong><?= mg_e($event) ?></strong><span>Append-only admin/share-market event.</span><em class="sm-admin-pill safe">Audited</em></div><?php endforeach; ?>
      </div>
    </article>

    <article class="sm-admin-panel" id="settings">
      <h2>Global settings</h2>
      <p>These settings should control the whole program before live series are allowed.</p>
      <div class="sm-admin-table"><div class="sm-admin-row"><div><span>Program enabled</span><strong>Global on/off</strong></div><div><span>Default</span><strong>Off until schema + tests</strong></div><em>Global</em></div><div class="sm-admin-row"><div><span>Approval required</span><strong>Force admin review</strong></div><div><span>Default</span><strong>Always required</strong></div><em>Safety</em></div><div class="sm-admin-row"><div><span>DAVE™ minimum score</span><strong>Launch threshold</strong></div><div><span>Default</span><strong>Manual review first</strong></div><em>Quality</em></div><div class="sm-admin-row"><div><span>Redemption policy</span><strong>Burn / lock / treasury return</strong></div><div><span>Default</span><strong>Burn or lock</strong></div><em>Utility</em></div></div>
    </article>
  </section>

  <section class="sm-admin-final" id="emergency-controls">
    <h2>Build rule: pause, freeze, burn, and reverse are different controls.</h2>
    <p>Pause is temporary. Freeze is a security lock. Burn is permanent supply removal. Reverse is an append-only correction event. The SQL layer should enforce those differences instead of letting admins edit balances directly.</p>
  </section>
</section>
