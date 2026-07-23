<?php
declare(strict_types=1);
?>
      <?php if ($issues !== []): ?>
        <section class="mg-pilot-panel mg-pilot-issues">
          <header><div><span>Attention required</span><h2>Health warnings</h2></div><p>Warnings are derived from connection, grant, definition, run, artifact, and emergency state.</p></header>
          <div>
            <?php foreach ($issues as $issue): ?>
              <a class="is-<?= mg_e((string)$issue['severity']) ?>" href="<?= mg_e((string)$issue['href']) ?>"><i></i><span><strong><?= mg_e(strtoupper((string)$issue['severity'])) ?></strong><?= mg_e((string)$issue['message']) ?></span></a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <section id="pilot-runs" class="mg-pilot-panel mg-pilot-runs">
        <header><div><span>Durable evidence</span><h2>Playbook run history</h2></div><p>Inspect the run, review artifact, receipt, and any recovery decision without exposing an autonomous retry.</p></header>
        <?php if ($runs === []): ?>
          <div class="mg-pilot-empty"><strong>No Phase 13D runs yet</strong><p>Activate a matching definition, then invoke the exact scoped playbook from the connected agent.</p></div>
        <?php else: ?>
          <div class="mg-pilot-run-list">
            <?php foreach ($runs as $run): $terminalFailure = in_array((string)$run['status'], ['failed','dead_lettered','partially_succeeded'], true); ?>
              <article id="run-<?= mg_e((string)$run['public_id']) ?>" class="is-<?= mg_e((string)$run['status']) ?>">
                <header><div><span><?= mg_e($playbookLabel((string)$run['playbook_key'])) ?></span><strong><?= mg_e((string)$run['automation_name']) ?></strong></div><em><?= mg_e($statusLabel((string)$run['status'])) ?></em></header>
                <dl>
                  <div><dt>Created</dt><dd><?= mg_e($formatDate((string)$run['created_at'])) ?></dd></div>
                  <div><dt>Artifact</dt><dd><?= mg_e((string)($run['artifact_status'] ?? 'None')) ?></dd></div>
                  <div><dt>Receipt</dt><dd><?= mg_e((string)($run['receipt_status'] ?? 'None')) ?></dd></div>
                  <div><dt>Attempts</dt><dd><?= (int)$run['attempt'] ?> / <?= (int)$run['maximum_attempts'] ?></dd></div>
                </dl>
                <?php if (!empty($run['error_message'])): ?><p class="mg-pilot-run-error"><strong><?= mg_e((string)$run['error_code']) ?></strong><?= mg_e((string)$run['error_message']) ?></p><?php endif; ?>
                <div class="mg-pilot-run-actions">
                  <?php if (!empty($run['artifact_public_id'])): ?><a href="/account-agent-drafts.php#draft-<?= mg_e((string)$run['artifact_public_id']) ?>">Open artifact</a><?php endif; ?>
                  <details><summary>Evidence IDs</summary><code>Run <?= mg_e((string)$run['public_id']) ?></code><code>Receipt <?= mg_e((string)($run['receipt_public_id'] ?? 'None')) ?></code></details>
                </div>
                <?php if ($terminalFailure): ?>
                  <form method="post" class="mg-pilot-recovery">
                    <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
                    <input type="hidden" name="action" value="acknowledge_run">
                    <input type="hidden" name="run_id" value="<?= mg_e((string)$run['public_id']) ?>">
                    <label>Recovery decision<select name="resolution" required><option value="review_configuration">Review configuration</option><option value="retry_external">Ask the external agent to retry</option><option value="pause_definition">Pause the definition</option><option value="no_retry">Do not retry</option><option value="resolved">Resolved outside the run</option></select></label>
                    <label>Operator note<textarea name="note" required minlength="5" maxlength="2000" rows="2"></textarea></label>
                    <button type="submit">Record recovery</button>
                  </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section id="pilot-artifacts" class="mg-pilot-panel mg-pilot-artifacts">
        <header><div><span>Owner review and handoff</span><h2>Recent recommendations</h2></div><p>Approved artifacts may prepare a separate Phase 13C request. The request still requires owner approval and a second execution step.</p></header>
        <?php if ($artifacts === []): ?>
          <div class="mg-pilot-empty"><strong>No playbook artifacts yet</strong><p>Artifacts appear after a successful bounded playbook run.</p></div>
        <?php else: ?>
          <div class="mg-pilot-artifact-list">
            <?php foreach ($artifacts as $artifact):
              $payload = (array)$artifact['payload'];
              $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
              $playbookKey = mg_creator_campaign_pilot_artifact_playbook($payload);
              $options = (array)($artifact['action_options'] ?? []);
              $firstTool = (string)(array_key_first($options) ?? '');
              $firstSeed = $firstTool !== '' ? (array)$options[$firstTool]['seed'] : [];
              $serverRecommendation = mg_creator_campaign_pilot_first_text($output, [['assessment','server_recommendation'],['assessment','agent_recommendation']]);
            ?>
              <article id="artifact-<?= mg_e((string)$artifact['id']) ?>" class="is-<?= mg_e((string)$artifact['status']) ?>">
                <header><div><span><?= mg_e($playbookLabel($playbookKey)) ?></span><strong><?= mg_e((string)$artifact['title']) ?></strong><p><?= mg_e((string)$artifact['summary']) ?></p></div><em><?= mg_e($statusLabel((string)$artifact['status'])) ?></em></header>
                <dl>
                  <div><dt>Recommendation</dt><dd><?= mg_e($serverRecommendation !== '' ? $statusLabel($serverRecommendation) : 'Review report') ?></dd></div>
                  <div><dt>Risk</dt><dd><?= mg_e((string)$artifact['risk_level']) ?></dd></div>
                  <div><dt>Created</dt><dd><?= mg_e($formatDate((string)$artifact['created_at'])) ?></dd></div>
                  <div><dt>Review expires</dt><dd><?= mg_e($formatDate((string)($artifact['approval']['expires_at'] ?? ''))) ?></dd></div>
                </dl>
                <details><summary>Structured recommendation evidence</summary><pre><?= mg_e(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre></details>

                <?php if ((string)$artifact['status'] !== 'approved'): ?>
                  <div class="mg-pilot-review-boundary"><strong>Owner review required</strong><span>Approve or reject this artifact in Agent Drafts before preparing any action request.</span><a href="/account-agent-drafts.php#draft-<?= mg_e((string)$artifact['id']) ?>">Open Agent Drafts</a></div>
                <?php elseif ((string)$pilot['status'] !== 'active'): ?>
                  <div class="mg-pilot-review-boundary"><strong>Pilot is not active</strong><span>Start or resume the pilot before preparing a Phase 13C request.</span></div>
                <?php elseif (!empty($pilot['emergency_disabled'])): ?>
                  <div class="mg-pilot-review-boundary"><strong>Emergency stop active</strong><span>New action-request handoffs are blocked.</span></div>
                <?php elseif ($options === []): ?>
                  <div class="mg-pilot-review-boundary"><strong>Review evidence complete</strong><span>This playbook output has no supported Phase 13C follow-up.</span></div>
                <?php elseif ($actionGrants === []): ?>
                  <div class="mg-pilot-review-boundary"><strong>Approval-gated grant required</strong><span>Create and activate a Phase 13C action grant before preparing a request.</span><a href="/account-agent-automations.php">Manage grants</a></div>
                <?php else: ?>
                  <form method="post" class="mg-pilot-handoff" data-pilot-handoff>
                    <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
                    <input type="hidden" name="action" value="prepare_action_request">
                    <input type="hidden" name="draft_id" value="<?= mg_e((string)$artifact['id']) ?>">
                    <header><span>Separate Phase 13C handoff</span><strong>Prepare an owner-approved action request</strong><p>This does not approve or execute the action.</p></header>
                    <div class="mg-pilot-handoff-grid">
                      <label>Recommended action<select name="tool_name" required data-handoff-tool><?php foreach ($options as $tool => $option): ?><option value="<?= mg_e($tool) ?>"><?= mg_e((string)$option['label']) ?> · <?= mg_e((string)$option['contract']['risk']) ?> risk</option><?php endforeach; ?></select></label>
                      <label>Approval-gated grant<select name="grant_id" required><option value="">Select grant</option><?php foreach ($actionGrants as $grant): ?><option value="<?= mg_e((string)$grant['public_id']) ?>"><?= mg_e((string)$grant['connection_name']) ?> · <?= mg_e((string)$grant['risk_ceiling']) ?> ceiling</option><?php endforeach; ?></select></label>
                    </div>
                    <label>Canonical action input<textarea name="action_input_json" rows="8" required data-handoff-input><?= mg_e(json_encode($firstSeed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></textarea><small>Canonical IDs and recommendation details are seeded from the approved artifact. Review every value.</small></label>
                    <label>Why should this request enter owner approval?<textarea name="requested_reason" required minlength="8" maxlength="1000" rows="3"><?= mg_e('Prepared from approved Phase 14 ' . $playbookLabel($playbookKey) . ' recommendation. Owner approval and separate execution are still required.') ?></textarea></label>
                    <script type="application/json" data-handoff-seeds><?= json_encode(array_map(static fn(array $option): array => (array)$option['seed'], $options), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
                    <button type="submit">Create waiting-for-approval request</button>
                  </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
