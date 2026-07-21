<?php
declare(strict_types=1);
?>
<section class="mg-app-shell mg-automation-shell">
  <?php require dirname(__DIR__) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-automation-workspace">
    <header class="mg-automation-hero">
      <div>
        <span class="mg-automation-eyebrow">MCP Phase 4C · scheduled simulation controls</span>
        <h1>Automation schedules</h1>
        <p>Authorize fixed or recurring simulation triggers, define due times, and manually evaluate due work. This builds the schedule control plane without activating a background scheduler or any external-effect action.</p>
      </div>
      <nav class="mg-automation-hero-actions" aria-label="Agent MCP automation workspaces">
        <a href="/account-agent-automations.php">Automation grants</a>
        <a href="/account-agent-automation-definitions.php">Definitions</a>
        <a href="/account-agent-drafts.php">Agent drafts</a>
      </nav>
    </header>

    <aside class="mg-automation-runtime-boundary">
      <strong>Manual due evaluation only</strong>
      <p>No cron job, queue, worker, Node.js service, runtime key, or scheduler is enabled. Due triggers fire only when the signed-in owner presses “Evaluate due simulations.” Every result remains proposed and creates zero action receipts.</p>
    </aside>

    <?php if ($notice !== ''): ?><div class="mg-automation-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="mg-automation-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>

    <section class="mg-automation-stats" aria-label="Scheduled simulation summary">
      <article><strong><?= count($schedules) ?></strong><span>Schedules</span></article>
      <article><strong><?= $activeScheduleCount ?></strong><span>Active</span></article>
      <article><strong><?= $dueCount ?></strong><span>Due now</span></article>
      <article><strong><?= count($runs) ?></strong><span>Scheduled simulations</span></article>
      <article><strong>0</strong><span>Action receipts</span></article>
    </section>

    <section class="mg-schedule-evaluate">
      <div><span class="mg-automation-eyebrow">Owner-operated evaluator</span><h2>Evaluate due simulations</h2><p>Checks up to ten due fixed or recurring triggers, revalidates every grant and policy boundary, records proposed actions, then advances or expires the trigger.</p></div>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="evaluate_due"><button type="submit"<?= $dueCount < 1 ? ' disabled' : '' ?>>Evaluate <?= $dueCount ?> due simulation<?= $dueCount === 1 ? '' : 's' ?></button></form>
    </section>

    <details class="mg-automation-create" open>
      <summary><span>1. Authorize schedule types</span><small>Authority is explicit and always retains manual simulation.</small></summary>
      <div class="mg-schedule-grid">
        <?php if (!$schemaReady || $grants === []): ?>
          <div class="mg-automation-empty"><strong>No automation grants</strong><p>Create a bounded grant before authorizing simulation schedules.</p><a href="/account-agent-automations.php">Manage grants</a></div>
        <?php else: foreach ($grants as $grant): $triggerTypes=(array)($grant['trigger_types'] ?? []); ?>
          <article class="mg-schedule-authority-card">
            <header><div><strong><?= mg_e((string)$grant['label']) ?></strong><span><?= mg_e(ucfirst((string)$grant['status'])) ?> · <?= mg_e((string)$grant['maximum_operation_class']) ?></span></div><code><?= mg_e((string)$grant['id']) ?></code></header>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="update_schedule_authority"><input type="hidden" name="grant_id" value="<?= mg_e((string)$grant['id']) ?>">
              <label><input type="checkbox" checked disabled> Manual simulation</label>
              <label><input type="checkbox" name="fixed_schedule" value="1"<?= in_array('fixed_schedule',$triggerTypes,true)?' checked':'' ?>> One-time fixed simulation</label>
              <label><input type="checkbox" name="recurring_schedule" value="1"<?= in_array('recurring_schedule',$triggerTypes,true)?' checked':'' ?>> Recurring simulation</label>
              <input name="reason" minlength="5" maxlength="255" required value="Owner-managed Phase 4C schedule authority">
              <button type="submit"<?= in_array((string)$grant['status'],['expired','revoked'],true)?' disabled':'' ?>>Save schedule authority</button>
            </form>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </details>

    <details class="mg-automation-create"<?= $schedules === [] ? ' open' : '' ?>>
      <summary><span>2. Configure a simulation schedule</span><small>One active fixed or recurring schedule per definition.</small></summary>
      <?php if ($schedulableDefinitions === []): ?>
        <div class="mg-automation-empty"><strong>No active definitions</strong><p>Create and activate a simulation definition first.</p><a href="/account-agent-automation-definitions.php">Manage definitions</a></div>
      <?php else: ?>
        <form method="post" class="mg-automation-form mg-schedule-form">
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="configure_schedule">
          <section><header><span>1</span><div><h2>Definition and trigger</h2><p>The selected definition must belong to an active grant that authorizes the chosen trigger type.</p></div></header>
            <div class="mg-automation-form-grid">
              <label>Active definition<select name="automation_id" required><option value="">Select definition</option><?php foreach($schedulableDefinitions as $definition): ?><option value="<?= mg_e((string)$definition['id']) ?>"><?= mg_e((string)$definition['name']) ?> · <?= mg_e((string)$definition['playbook_key']) ?></option><?php endforeach; ?></select></label>
              <label>Schedule type<select name="trigger_type" required><option value="fixed_schedule">One-time fixed simulation</option><option value="recurring_schedule">Recurring simulation</option></select></label>
              <label>Timezone<select name="timezone" required><?php foreach(MG_MCP_AUTOMATION_SCHEDULE_TIMEZONES as $timezone): ?><option value="<?= mg_e($timezone) ?>"<?= $timezone==='America/Phoenix'?' selected':'' ?>><?= mg_e($timezone) ?></option><?php endforeach; ?></select></label>
              <label>First due date and time<input type="datetime-local" name="first_due_at" required></label>
              <label>Recurring interval<select name="interval_seconds"><option value="3600">Every hour</option><option value="21600">Every 6 hours</option><option value="43200">Every 12 hours</option><option value="86400" selected>Every day</option><option value="604800">Every 7 days</option></select></label>
            </div>
          </section>
          <footer><div><strong>Phase 4C boundary</strong><span>Due record only · owner evaluates manually · simulation actions remain proposed</span></div><button type="submit">Save simulation schedule</button></footer>
        </form>
      <?php endif; ?>
    </details>

    <section class="mg-automation-list-section">
      <header><div><span class="mg-automation-eyebrow">Trigger records</span><h2>Configured schedules</h2></div><p>Active due dates are durable, but nothing checks them in the background.</p></header>
      <?php if ($schedules === []): ?><div class="mg-automation-empty"><strong>No schedules configured</strong><p>Authorize a schedule type and configure one above.</p></div><?php else: ?><div class="mg-schedule-list">
        <?php foreach($schedules as $schedule): $config=(array)$schedule['configuration']; ?>
          <article class="mg-schedule-card is-<?= mg_e((string)$schedule['status']) ?>">
            <header><div><span><?= mg_e(strtoupper(str_replace('_',' ',(string)$schedule['type']))) ?></span><h3><?= mg_e((string)$schedule['automation']['name']) ?></h3></div><strong><?= mg_e(ucfirst((string)$schedule['status'])) ?></strong></header>
            <dl><div><dt>Next due UTC</dt><dd><?= mg_e((string)($schedule['next_due_at'] ?? 'Not scheduled')) ?></dd></div><div><dt>Timezone</dt><dd><?= mg_e((string)($config['timezone'] ?? $schedule['automation']['timezone'])) ?></dd></div><div><dt>Last fired</dt><dd><?= mg_e((string)($schedule['last_fired_at'] ?? 'Never')) ?></dd></div><div><dt>Fire count</dt><dd><?= (int)$schedule['fire_count'] ?></dd></div><div><dt>Scheduler</dt><dd>Disabled</dd></div><div><dt>Execution</dt><dd>Disabled</dd></div></dl>
            <form method="post" class="mg-schedule-remove"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="remove_schedule"><input type="hidden" name="automation_id" value="<?= mg_e((string)$schedule['automation']['id']) ?>"><input name="reason" minlength="5" maxlength="255" required value="Owner removed Phase 4C simulation schedule"><button type="submit">Pause and remove schedule</button></form>
          </article>
        <?php endforeach; ?>
      </div><?php endif; ?>
    </section>

    <section class="mg-automation-list-section">
      <header><div><span class="mg-automation-eyebrow">Durable evidence</span><h2>Scheduled simulation history</h2></div><p>Each entry proves a due trigger was evaluated under current authority. Receipts remain zero.</p></header>
      <?php if($runs===[]): ?><div class="mg-automation-empty"><strong>No scheduled simulations yet</strong><p>When a trigger becomes due, use the evaluator above.</p></div><?php else: ?><div class="mg-simulation-history">
        <?php foreach($runs as $run): ?><article><header><div><strong><?= mg_e((string)$run['automation']['name']) ?></strong><span><?= mg_e((string)$run['trigger']['type']) ?></span></div><em><?= mg_e((string)$run['status']) ?></em></header><dl><div><dt>Scheduled</dt><dd><?= mg_e((string)($run['scheduled_at'] ?? '—')) ?></dd></div><div><dt>Completed</dt><dd><?= mg_e((string)($run['completed_at'] ?? '—')) ?></dd></div><div><dt>Proposed actions</dt><dd><?= (int)$run['action_count'] ?></dd></div><div><dt>Receipts</dt><dd><?= (int)($run['summary']['action_receipts_created'] ?? 0) ?></dd></div></dl><p>Owner-evaluated simulation only; no scheduler and no execution attempt.</p></article><?php endforeach; ?>
      </div><?php endif; ?>
    </section>

    <aside class="mg-automation-safety"><strong>No background scheduler exists in Phase 4C</strong><p>Fixed and recurring trigger records are preparation for a later VPS worker phase. Current PHP hosting can store and manually evaluate them, but cannot autonomously publish, send, purchase, issue, activate, fulfill, charge, or enqueue external-effect work.</p></aside>
  </main>
</section>
