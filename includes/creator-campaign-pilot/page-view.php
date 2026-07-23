<?php
declare(strict_types=1);

$statusLabel = static fn(string $status): string => ucwords(str_replace('_', ' ', $status));
$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') return 'Not yet';
    $time = strtotime($value);
    return $time === false ? $value : gmdate('M j, Y · g:i A', $time) . ' UTC';
};
$playbookLabel = static function (string $key) use ($playbookCards): string {
    return (string)($playbookCards[$key]['label'] ?? ucwords(str_replace('_', ' ', $key)));
};
?>
<section class="mg-app-shell mg-pilot-shell">
  <?php require dirname(__DIR__) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-pilot-workspace">
    <header class="mg-pilot-hero">
      <div>
        <span class="mg-pilot-eyebrow">Creator Campaign · Phase 14</span>
        <h1>Production pilot cockpit</h1>
        <p>Set up, observe, and safely operate bounded Creator Campaign playbooks from one merchant workspace. Recommendations remain review-only until you deliberately create a separate Phase 13C action request.</p>
        <div class="mg-pilot-hero-links">
          <a href="/account-agent-automations.php">Grants</a>
          <a href="/account-agent-automation-definitions.php">Definitions</a>
          <a href="/account-agent-drafts.php">Review artifacts</a>
          <a href="/account-creator-campaign-actions.php">Action approvals</a>
        </div>
      </div>
      <aside class="mg-pilot-score">
        <span><?= mg_e((string)($workspace['display_name'] ?? 'Merchant workspace')) ?></span>
        <strong><?= (int)($readiness['score'] ?? 0) ?>%</strong>
        <small><?= (int)($readiness['completed'] ?? 0) ?> of <?= (int)($readiness['total'] ?? 0) ?> pilot checks complete</small>
        <?php if ($pilot): ?><em class="is-<?= mg_e((string)$pilot['status']) ?>"><?= mg_e($statusLabel((string)$pilot['status'])) ?></em><?php endif; ?>
      </aside>
    </header>

    <?php if ($notice !== ''): ?><div class="mg-pilot-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="mg-pilot-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>

    <?php if (!$schemaReady || !$pilot): ?>
      <section class="mg-pilot-empty">
        <strong>Phase 14 operator schema is unavailable</strong>
        <p>Import <code>database/20260722_creator_campaign_production_pilot_v14_single_install.sql</code>, then reopen this page.</p>
      </section>
    <?php else: ?>
      <section class="mg-pilot-command-row">
        <article class="mg-pilot-state-card">
          <div>
            <span>Pilot state</span>
            <strong><?= mg_e($statusLabel((string)$pilot['status'])) ?></strong>
            <p><?= !empty($readiness['start_ready']) ? 'Required setup controls are ready.' : 'Complete the required setup controls before starting.' ?></p>
          </div>
          <div class="mg-pilot-state-actions">
            <?php if (in_array((string)$pilot['status'], ['setup','ready'], true)): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="transition"><button type="submit" name="transition" value="start"<?= empty($readiness['start_ready']) ? ' disabled' : '' ?>>Start pilot</button></form>
            <?php elseif ((string)$pilot['status'] === 'active'): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="transition"><button class="is-secondary" type="submit" name="transition" value="pause">Pause pilot</button></form>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="transition"><button type="submit" name="transition" value="complete"<?= empty($readiness['pilot_validated']) ? ' disabled' : '' ?>>Complete pilot</button></form>
            <?php elseif ((string)$pilot['status'] === 'paused'): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="transition"><button type="submit" name="transition" value="resume"<?= empty($readiness['start_ready']) || !empty($pilot['emergency_disabled']) ? ' disabled' : '' ?>>Resume pilot</button></form>
            <?php endif; ?>
          </div>
        </article>

        <article id="pilot-emergency" class="mg-pilot-emergency<?= !empty($pilot['emergency_disabled']) ? ' is-active' : '' ?>">
          <div>
            <span>Emergency control</span>
            <strong><?= !empty($pilot['emergency_disabled']) ? 'STOP ACTIVE' : 'Workspace enabled' ?></strong>
            <p><?= !empty($pilot['emergency_disabled']) ? mg_e((string)($pilot['emergency_reason'] ?? 'New playbook runs are blocked.')) : 'Stops new Phase 13D runs and pauses bounded definitions, triggers, and grants.' ?></p>
          </div>
          <?php if (empty($pilot['emergency_disabled'])): ?>
            <form method="post" onsubmit="return confirm('Activate the Creator Campaign emergency stop? Active bounded definitions and grants will be paused.');">
              <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
              <input type="hidden" name="action" value="emergency_disable">
              <label>Required reason<input name="reason" required minlength="8" maxlength="1000" placeholder="Describe the risk or incident requiring shutdown."></label>
              <button class="is-danger" type="submit">Emergency stop</button>
            </form>
          <?php else: ?>
            <form method="post" onsubmit="return confirm('Clear the emergency stop? Grants and definitions will remain paused.');">
              <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
              <input type="hidden" name="action" value="emergency_clear">
              <label>Required clearance note<input name="reason" required minlength="8" maxlength="1000" placeholder="Document why the workspace is safe to reopen."></label>
              <button type="submit">Clear stop only</button>
            </form>
          <?php endif; ?>
        </article>
      </section>

      <section class="mg-pilot-metrics" aria-label="Pilot telemetry summary">
        <article><strong><?= (int)($readiness['counts']['active_draft_connections'] ?? 0) ?></strong><span>Active AI connections</span></article>
        <article><strong><?= (int)($readiness['counts']['active_playbook_grants'] ?? 0) ?></strong><span>Playbook grants</span></article>
        <article><strong><?= (int)($readiness['counts']['active_definitions'] ?? 0) ?></strong><span>Active definitions</span></article>
        <article><strong><?= (int)($readiness['counts']['successful_runs'] ?? 0) ?></strong><span>Successful runs</span></article>
        <article><strong><?= (int)($readiness['counts']['approved_artifacts'] ?? 0) ?></strong><span>Approved reviews</span></article>
        <article><strong><?= (int)($readiness['counts']['active_action_grants'] ?? 0) ?></strong><span>Action grants</span></article>
      </section>

      <section class="mg-pilot-grid">
        <section class="mg-pilot-panel mg-pilot-readiness">
          <header><div><span>Guided setup</span><h2>Pilot readiness</h2></div><p>Technical checks are calculated from canonical records. Owner attestations are saved below.</p></header>
          <div class="mg-pilot-step-list">
            <?php foreach ((array)$readiness['steps'] as $key => $step): ?>
              <a class="mg-pilot-step<?= !empty($step['complete']) ? ' is-complete' : '' ?>" href="<?= mg_e((string)$step['href']) ?>">
                <i><?= !empty($step['complete']) ? '✓' : '!' ?></i>
                <span><strong><?= mg_e((string)$step['label']) ?></strong><small><?= mg_e((string)$step['detail']) ?></small></span>
                <?php if (!empty($step['required_start'])): ?><em>Required</em><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </section>

        <section id="pilot-checklist" class="mg-pilot-panel mg-pilot-checklist">
          <header><div><span>Merchant onboarding</span><h2>Operator checklist</h2></div><p>Record the named support path and the owner-verified launch controls.</p></header>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
            <input type="hidden" name="action" value="save_profile">
            <label class="mg-pilot-support">Pilot support contact<input name="support_contact" maxlength="255" value="<?= mg_e((string)($pilot['support_contact'] ?? '')) ?>" placeholder="Name, email, Slack channel, or escalation desk"></label>
            <div class="mg-pilot-checks">
              <?php foreach (MG_CREATOR_CAMPAIGN_PILOT_MANUAL_CHECKS as $key => $label): ?>
                <label><input type="checkbox" name="<?= mg_e($key) ?>" value="1"<?= !empty($pilot['checklist'][$key]) ? ' checked' : '' ?>><span><strong><?= mg_e($label) ?></strong><small><?= match ($key) {
                  'deployment_verified' => 'The integration ZIP containing Phase 14 is live on the target server.',
                  'sql_verified' => 'The additive Phase 14 operator tables are present.',
                  'emergency_tested' => 'The owner knows how to stop runs and manually review reactivation.',
                  default => 'A human support path is available during the pilot.',
                } ?></small></span></label>
              <?php endforeach; ?>
            </div>
            <button type="submit">Save pilot checklist</button>
          </form>
        </section>
      </section>

      <section class="mg-pilot-panel mg-pilot-playbooks">
        <header><div><span>Merchant playbook dashboard</span><h2>Bounded assistants</h2></div><p>Each assistant stays manual, grant-bound, definition-bound, and review-only.</p></header>
        <div class="mg-pilot-playbook-grid">
          <?php foreach ($playbookCards as $key => $card): $latest = $card['latest_run']; ?>
            <article class="<?= !empty($card['active']) ? 'is-active' : 'is-inactive' ?>">
              <header><i><?= !empty($card['active']) ? '●' : '○' ?></i><div><strong><?= mg_e((string)$card['label']) ?></strong><span><?= !empty($card['active']) ? 'Ready for scoped MCP runs' : 'Setup required' ?></span></div></header>
              <p><?= mg_e((string)$card['summary']) ?></p>
              <dl>
                <div><dt>Definitions</dt><dd><?= count((array)$card['definitions']) ?></dd></div>
                <div><dt>Last run</dt><dd><?= $latest ? mg_e($statusLabel((string)$latest['status'])) : 'Never' ?></dd></div>
                <div><dt>Last activity</dt><dd><?= $latest ? mg_e($formatDate((string)$latest['created_at'])) : 'Not yet' ?></dd></div>
              </dl>
              <div><a href="/account-agent-automation-definitions.php">Manage definition</a><?php if ($latest && !empty($latest['artifact_public_id'])): ?><a href="/account-agent-drafts.php#draft-<?= mg_e((string)$latest['artifact_public_id']) ?>">Open review</a><?php endif; ?></div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php require __DIR__ . '/page-view-runs.php'; ?>
<?php require __DIR__ . '/page-view-monitoring.php'; ?>
<?php endif; ?>
  </main>
</section>
<script>
(function(document){
  'use strict';
  document.querySelectorAll('[data-pilot-handoff]').forEach(function(form){
    var select=form.querySelector('[data-handoff-tool]');
    var input=form.querySelector('[data-handoff-input]');
    var source=form.querySelector('[data-handoff-seeds]');
    if(!select||!input||!source)return;
    var seeds={};
    try{seeds=JSON.parse(source.textContent||'{}');}catch(error){seeds={};}
    select.addEventListener('change',function(){
      if(!Object.prototype.hasOwnProperty.call(seeds,select.value))return;
      input.value=JSON.stringify(seeds[select.value]||{},null,2);
    });
  });
})(document);
</script>
