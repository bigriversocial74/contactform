<?php declare(strict_types=1); ?>
      <section id="onboarding-step-7" class="mg-onboarding-step-card">
        <header><i>7</i><div><span>Guided first campaign</span><h2>Create or select the pilot campaign</h2><p>The guided create action uses the canonical Creator Campaign draft and builder services. It does not publish the campaign.</p></div></header>
        <?php if ($selectedCampaign): ?>
          <article class="mg-onboarding-selected-campaign">
            <div><span>Selected campaign</span><strong><?= mg_e((string)$selectedCampaign['title']) ?></strong><small><?= mg_e(strtoupper((string)$selectedCampaign['status'])) ?> · <?= mg_e((string)$selectedCampaign['public_id']) ?></small></div>
            <div class="mg-onboarding-actions"><a class="mg-btn mg-btn-primary" href="/merchant-creator-campaign-builder.php?campaign=<?= rawurlencode((string)$selectedCampaign['public_id']) ?>">Open builder</a><a class="mg-btn mg-btn-soft" href="/merchant-creator-campaign-detail.php?campaign=<?= rawurlencode((string)$selectedCampaign['public_id']) ?>">Campaign detail</a></div>
          </article>
        <?php endif; ?>
        <div class="mg-onboarding-campaign-columns">
          <form method="post" class="mg-onboarding-form">
            <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="create_first_campaign"><input type="hidden" name="return_step" value="7">
            <h3>Create guided draft</h3>
            <label>Campaign title<input name="campaign_title" required maxlength="180" placeholder="Phoenix Local Favorites Pilot"></label>
            <label>Objective<input name="campaign_objective" required maxlength="180" placeholder="Drive attributed local gift purchases"></label>
            <label>Category<input name="campaign_category" required maxlength="100" value="<?= mg_e((string)($business['business_category'] ?? '')) ?>"></label>
            <label>Description<textarea name="campaign_description" required rows="4" maxlength="16000"><?= mg_e((string)($business['brand_description'] ?? '')) ?></textarea></label>
            <button type="submit"<?= $selectedCampaign ? ' disabled' : '' ?>>Create canonical draft</button>
            <?php if ($selectedCampaign): ?><small>A first campaign is already selected. Use the existing-campaign selector to replace it.</small><?php endif; ?>
          </form>
          <form method="post" class="mg-onboarding-form">
            <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="select_first_campaign"><input type="hidden" name="return_step" value="7">
            <h3>Select an existing campaign</h3>
            <label>Campaign<select name="campaign_id" required><option value="">Choose a campaign</option><?php foreach ($campaigns as $campaign): ?><option value="<?= mg_e((string)$campaign['public_id']) ?>"<?= $selectedCampaign && (int)$selectedCampaign['id'] === (int)$campaign['id'] ? ' selected' : '' ?>><?= mg_e((string)$campaign['title']) ?> · <?= mg_e(strtoupper((string)$campaign['status'])) ?></option><?php endforeach; ?></select></label>
            <button type="submit">Use selected campaign</button>
          </form>
        </div>
        <?php if ($selectedCampaign): $campaignChecks = (array)($readiness['campaign']['checks'] ?? []); $campaignQuery = rawurlencode((string)$selectedCampaign['public_id']); ?>
          <div class="mg-onboarding-domain-grid">
            <?php foreach ([
              'builder_ready'=>['Builder foundation','/merchant-creator-campaign-builder.php?campaign=' . $campaignQuery],
              'product_attached'=>['Products','/merchant-creator-campaign-builder.php?campaign=' . $campaignQuery],
              'deliverable_defined'=>['Deliverables','/merchant-creator-deliverables.php?campaign=' . $campaignQuery],
              'compensation_active'=>['Compensation','/merchant-creator-compensation.php?campaign=' . $campaignQuery],
              'budget_configured'=>['Budget','/merchant-creator-budgets.php?campaign=' . $campaignQuery],
              'tracking_configured'=>['Tracking & attribution','/merchant-creator-tracking.php?campaign=' . $campaignQuery],
              'agreement_service_ready'=>['Rights, terms & agreements','/merchant-creator-participation.php?campaign=' . $campaignQuery],
              'automatic_acceptance_disabled'=>['Manual Creator approval','/merchant-creator-campaign-builder.php?campaign=' . $campaignQuery],
            ] as $key=>[$label,$href]): ?>
              <a class="<?= !empty($campaignChecks[$key]) ? 'is-ready' : 'is-blocked' ?>" href="<?= mg_e($href) ?>"><i><?= !empty($campaignChecks[$key]) ? '✓' : '!' ?></i><span><strong><?= mg_e($label) ?></strong><small><?= !empty($campaignChecks[$key]) ? 'Ready' : 'Complete setup' ?></small></span></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section id="onboarding-step-8" class="mg-onboarding-step-card">
        <header><i>8</i><div><span>Evidence-based launch check</span><h2>Production smoke test</h2><p>Run a read-only validation across onboarding, product, campaign, operator, and safety records. The test writes only a durable receipt and event evidence.</p></div></header>
        <form method="post" class="mg-onboarding-smoke-action">
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="run_smoke_test"><input type="hidden" name="return_step" value="8">
          <div><strong><?= $latestSmoke ? mg_e(ucfirst((string)$latestSmoke['status'])) . ' · ' . (int)$latestSmoke['score'] . '%' : 'Not run' ?></strong><small><?= $latestSmoke ? mg_e($formatDate((string)$latestSmoke['created_at'])) . (empty($readiness['latest_smoke_test_current']) ? ' · State changed; run again' : '') : 'Complete Steps 1–7, then run the launch validation.' ?></small></div>
          <button type="submit">Run production smoke test</button>
        </form>
        <?php if ($latestSmoke): ?>
          <div class="mg-onboarding-smoke-checks">
            <?php foreach ((array)$latestSmoke['checks'] as $check): ?><article class="<?= !empty($check['ok']) ? 'is-pass' : 'is-fail' ?>"><i><?= !empty($check['ok']) ? '✓' : '!' ?></i><div><strong><?= mg_e((string)$check['label']) ?></strong><p><?= mg_e((string)$check['detail']) ?></p></div></article><?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section id="onboarding-step-9" class="mg-onboarding-step-card mg-onboarding-launch-card">
        <header><i>9</i><div><span>Merchant launch dashboard</span><h2>Activate onboarding</h2><p>Activation confirms the merchant launch process is ready. It does not publish the campaign, approve Creators, send messages, alter earnings, issue payouts, or enable automation.</p></div></header>
        <div class="mg-onboarding-launch-summary">
          <article><span>Setup</span><strong><?= !empty($readiness['setup_ready']) ? 'Ready' : 'Incomplete' ?></strong></article>
          <article><span>Smoke test</span><strong><?= !empty($readiness['current_passing_smoke_test']) ? 'Passed · current' : ($latestSmoke ? 'Run again' : 'Not run') ?></strong></article>
          <article><span>Emergency control</span><strong><?= !empty($readiness['pilot_emergency_clear']) ? 'Clear' : 'Stop active' ?></strong></article>
          <article><span>Activation</span><strong><?= mg_e(mg_creator_campaign_onboarding_status_label((string)$onboarding['status'])) ?></strong></article>
        </div>
        <?php if (!in_array((string)$onboarding['status'], ['active','completed'], true)): ?>
          <form method="post" class="mg-onboarding-launch-action" onsubmit="return confirm('Activate Creator Campaign merchant onboarding? This will not publish the campaign.');">
            <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="activate_onboarding"><input type="hidden" name="return_step" value="9">
            <button type="submit"<?= empty($readiness['launch_ready']) ? ' disabled' : '' ?>>Activate merchant onboarding</button>
          </form>
        <?php elseif ((string)$onboarding['status'] === 'active'): ?>
          <form method="post" class="mg-onboarding-launch-action">
            <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="complete_onboarding"><input type="hidden" name="return_step" value="9">
            <button type="submit">Mark onboarding complete</button>
          </form>
        <?php else: ?><div class="mg-onboarding-complete">✓ Creator Campaign merchant onboarding is complete.</div><?php endif; ?>
      </section>

      <section class="mg-onboarding-step-card mg-onboarding-evidence">
        <header><i>•</i><div><span>Durable evidence</span><h2>Receipts and onboarding activity</h2><p>Smoke tests and launch activation are append-only evidence. Merchant onboarding events document each saved step.</p></div></header>
        <div class="mg-onboarding-evidence-columns">
          <div><h3>Receipts</h3><?php if ($receipts === []): ?><p>No receipts yet.</p><?php else: ?><?php foreach ($receipts as $receipt): ?><article><strong><?= mg_e(ucwords(str_replace('_',' ',(string)$receipt['receipt_type']))) ?> · <?= mg_e(ucfirst((string)$receipt['status'])) ?></strong><small><?= (int)$receipt['score'] ?>% · <?= mg_e($formatDate((string)$receipt['created_at'])) ?></small></article><?php endforeach; ?><?php endif; ?></div>
          <div><h3>Activity</h3><?php if ($events === []): ?><p>No onboarding activity yet.</p><?php else: ?><?php foreach (array_slice($events,0,12) as $event): ?><article><strong><?= mg_e(ucwords(str_replace(['creator_campaign.onboarding.','_','.'],['',' ',' '],(string)$event['event_type']))) ?></strong><small><?= mg_e($formatDate((string)$event['created_at'])) ?></small></article><?php endforeach; ?><?php endif; ?></div>
        </div>
      </section>
