<?php
declare(strict_types=1);
?>
<section class="mg-app-shell mg-agent-drafts-shell">
  <?php require dirname(__DIR__) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-drafts-workspace">
    <header class="mg-drafts-hero">
      <div>
        <span class="mg-drafts-eyebrow">External agent review · Phase 3B</span>
        <h1>Agent drafts</h1>
        <p>Review external-agent drafts, approve the proposal, and separately convert an approved proposal into an inactive native Microgifter draft. Every conversion still requires later manual editing and an additional first-party action before anything can go live.</p>
      </div>
      <div class="mg-conversion-actions">
        <a class="mg-drafts-link" href="/account-agent-automations.php">Automation grants</a>
        <a class="mg-drafts-link" href="/account-ai-connections.php">AI connections</a>
      </div>
    </header>

    <?php if ($notice !== ''): ?><div class="mg-drafts-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="mg-drafts-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>

    <section class="mg-drafts-stats" aria-label="Draft status summary">
      <?php foreach (['pending_review' => 'Pending review','approved' => 'Approved','rejected' => 'Rejected','canceled' => 'Canceled','expired' => 'Expired'] as $key => $label): ?>
        <article><strong><?= (int)($counts[$key] ?? 0) ?></strong><span><?= mg_e($label) ?></span></article>
      <?php endforeach; ?>
    </section>

    <form method="get" class="mg-drafts-filters">
      <label>Type<select name="type"><option value="">All types</option><?php foreach (MG_MCP_DRAFT_TYPES as $type): ?><option value="<?= mg_e($type) ?>"<?= $typeFilter === $type ? ' selected' : '' ?>><?= mg_e(ucfirst($type)) ?></option><?php endforeach; ?></select></label>
      <label>Status<select name="status"><option value="">All statuses</option><?php foreach (MG_MCP_DRAFT_STATUSES as $status): ?><option value="<?= mg_e($status) ?>"<?= $statusFilter === $status ? ' selected' : '' ?>><?= mg_e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label>
      <button type="submit">Filter drafts</button>
    </form>

    <?php if ($drafts === []): ?>
      <section class="mg-drafts-empty"><strong>No agent drafts match this view.</strong><p>Drafts appear here after an authorized external client prepares one for review.</p></section>
    <?php else: ?>
      <section class="mg-drafts-list">
        <?php foreach ($drafts as $draft): $conversion = is_array($draft['conversion'] ?? null) ? $draft['conversion'] : null; ?>
          <article id="draft-<?= mg_e((string)$draft['id']) ?>" class="mg-draft-card is-<?= mg_e((string)$draft['status']) ?>">
            <header>
              <div><span><?= mg_e(strtoupper((string)$draft['type'])) ?></span><h2><?= mg_e((string)$draft['title']) ?></h2><p><?= mg_e((string)$draft['summary']) ?></p></div>
              <strong><?= mg_e(ucwords(str_replace('_', ' ', (string)$draft['status']))) ?></strong>
            </header>
            <dl>
              <div><dt>Client</dt><dd><?= mg_e((string)$draft['client']['name']) ?></dd></div>
              <div><dt>Connection</dt><dd><?= mg_e((string)$draft['connection']['name']) ?></dd></div>
              <div><dt>Risk</dt><dd><?= mg_e((string)$draft['risk_level']) ?></dd></div>
              <div><dt>Review expires</dt><dd><?= mg_e((string)($draft['approval']['expires_at'] ?? 'Not set')) ?></dd></div>
            </dl>
            <details><summary>Review draft details</summary><pre><?= mg_e(json_encode($draft['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre><p><strong>Agent reason:</strong> <?= mg_e((string)$draft['requested_reason']) ?></p></details>

            <?php if ((string)$draft['status'] === 'pending_review'): ?>
              <form method="post" class="mg-draft-decision">
                <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
                <input type="hidden" name="action" value="decision">
                <input type="hidden" name="draft_id" value="<?= mg_e((string)$draft['id']) ?>">
                <label>Decision note<textarea name="reason" maxlength="1000" rows="3" placeholder="Required when rejecting; optional when approving"></textarea></label>
                <div><button type="submit" name="decision" value="reject" class="is-reject">Reject</button><button type="submit" name="decision" value="approve" class="is-approve">Approve draft</button></div>
              </form>
            <?php elseif ((string)$draft['status'] === 'approved' && $conversion === null): ?>
              <section class="mg-conversion-panel">
                <div><span>Step 1 of 2</span><strong>Prepare native conversion</strong><p>This creates conversion evidence only. It does not create a Microgifter object yet.</p></div>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="prepare_conversion"><input type="hidden" name="draft_id" value="<?= mg_e((string)$draft['id']) ?>"><button type="submit">Prepare conversion</button></form>
              </section>
            <?php elseif ((string)$draft['status'] === 'approved' && $conversion !== null && (string)$conversion['status'] === 'prepared'): ?>
              <section class="mg-conversion-panel is-prepared">
                <div><span>Step 2 of 2</span><strong>Create <?= mg_e($conversionLabel($conversion)) ?></strong><p>The new object will remain inactive. A later manual action inside Microgifter is still required.</p></div>
                <div class="mg-conversion-actions">
                  <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="cancel_conversion"><input type="hidden" name="conversion_id" value="<?= mg_e((string)$conversion['id']) ?>"><button class="is-secondary" type="submit">Cancel</button></form>
                  <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="create_native"><input type="hidden" name="conversion_id" value="<?= mg_e((string)$conversion['id']) ?>"><button type="submit">Create inactive draft</button></form>
                </div>
              </section>
            <?php elseif ((string)$draft['status'] === 'approved' && $conversion !== null && in_array((string)$conversion['status'], ['created','opened'], true)): ?>
              <section class="mg-conversion-panel is-created">
                <div><span>Native draft ready</span><strong><?= mg_e(ucfirst($conversionLabel($conversion))) ?></strong><p>ID: <code><?= mg_e((string)($conversion['native_public_id'] ?? '')) ?></code>. This object is still inactive.</p></div>
                <form method="post" action="/account-agent-draft-open.php"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="conversion_id" value="<?= mg_e((string)$conversion['id']) ?>"><button type="submit">Open native draft</button></form>
              </section>
            <?php elseif ((string)$draft['status'] === 'approved' && $conversion !== null && (string)$conversion['status'] === 'canceled'): ?>
              <section class="mg-conversion-panel is-canceled">
                <div><span>Conversion canceled</span><strong>No native draft was created</strong><p>You may prepare this approved draft again.</p></div>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="prepare_conversion"><input type="hidden" name="draft_id" value="<?= mg_e((string)$draft['id']) ?>"><button type="submit">Prepare again</button></form>
              </section>
            <?php else: ?>
              <div class="mg-draft-boundary"><strong>No conversion available</strong><span>No Microgifter action was performed.</span></div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <aside class="mg-drafts-safety"><strong>Two human gates remain</strong><p>Agent approval does not create a native object. Conversion requires a second owner action, and the resulting native draft remains private or inactive until a later first-party action makes it live or executes it.</p></aside>
  </main>
</section>
