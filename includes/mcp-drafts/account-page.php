<?php
declare(strict_types=1);

$user = mg_require_auth();
$pdo = mg_db();
$notice = '';
$errorMessage = '';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpDraftException('Your session expired. Refresh the page and try again.', 419, 'MCP_DRAFT_CSRF_FAILED');
        }
        $draft = mg_mcp_draft_owner_decide(
            $pdo,
            (int)$user['id'],
            (string)($_POST['draft_id'] ?? ''),
            (string)($_POST['decision'] ?? ''),
            (string)($_POST['reason'] ?? '')
        );
        $notice = (string)$draft['status'] === 'approved'
            ? 'Draft approved for manual Microgifter follow-up. No action was executed.'
            : 'Draft rejected. No action was executed.';
    } catch (MgMcpDraftException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.agent_draft.review_failed', 'Agent draft review failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The draft decision could not be recorded.';
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));
try {
    $drafts = mg_mcp_draft_list_for_owner($pdo, (int)$user['id'], [
        'status' => $statusFilter,
        'type' => $typeFilter,
    ]);
} catch (MgMcpDraftException $error) {
    $drafts = [];
    $errorMessage = $error->getMessage();
}

$counts = array_fill_keys(MG_MCP_DRAFT_STATUSES, 0);
foreach ($drafts as $draft) $counts[(string)$draft['status']] = ($counts[(string)$draft['status']] ?? 0) + 1;

$page_title = 'Agent Drafts | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'agent-drafts';
$can_merchant_nav = true;
$page_body_class = 'mg-agent-drafts-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/mcp-drafts.css?v=20260720-phase3a',
];
require dirname(__DIR__) . '/header.php';
?>
<section class="mg-app-shell mg-agent-drafts-shell">
  <?php require dirname(__DIR__) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-drafts-workspace">
    <header class="mg-drafts-hero">
      <div>
        <span class="mg-drafts-eyebrow">External agent review · Phase 3A</span>
        <h1>Agent drafts</h1>
        <p>Review gift, campaign, reward, and message drafts prepared by an authorized external agent. Approval records your decision only—it never publishes, sends, purchases, schedules, or executes.</p>
      </div>
      <a class="mg-drafts-link" href="/account-ai-connections.php">Manage AI connections</a>
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
        <?php foreach ($drafts as $draft): ?>
          <article class="mg-draft-card is-<?= mg_e((string)$draft['status']) ?>">
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
                <input type="hidden" name="draft_id" value="<?= mg_e((string)$draft['id']) ?>">
                <label>Decision note<textarea name="reason" maxlength="1000" rows="3" placeholder="Required when rejecting; optional when approving"></textarea></label>
                <div><button type="submit" name="decision" value="reject" class="is-reject">Reject</button><button type="submit" name="decision" value="approve" class="is-approve">Approve draft</button></div>
              </form>
            <?php else: ?>
              <div class="mg-draft-boundary"><strong>No execution path</strong><span><?= (string)$draft['status'] === 'approved' ? 'Approved for manual follow-up only.' : 'No Microgifter action was performed.' ?></span></div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <aside class="mg-drafts-safety"><strong>Approval boundary</strong><p>These records are not connected to the Task Agent execution queue or the MCP automation worker queue. A future phase must add a separate, explicitly authorized conversion step before an approved draft can become a live Microgifter object.</p></aside>
  </main>
</section>
<?php require dirname(__DIR__) . '/footer.php'; ?>
