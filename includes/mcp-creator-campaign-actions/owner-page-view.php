<?php
declare(strict_types=1);
?>
<section class="mg-app-shell mg-cc-actions-shell">
  <?php require dirname(__DIR__).'/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-cc-actions-workspace">
    <header class="mg-cc-actions-hero">
      <div><span>Creator Campaign MCP · Phase 13C</span><h1>Canonical action approvals</h1><p>External agents may request actions, but only you can approve them and only a second explicit owner action can execute them through Microgifter’s native Creator Campaign services.</p></div>
      <nav><a href="/account-agent-automations.php">Automation grants</a><a href="/account-agent-drafts.php">Agent drafts</a><a href="/account-ai-connections.php">AI connections</a></nav>
    </header>
    <aside class="mg-cc-actions-boundary"><strong>Two-step owner gate</strong><p>Approve records your decision. Execute is a separate final action that rechecks the connection, client, grant, scope, workspace, risk, limits, concurrency, target, optimistic lock, and current native state.</p></aside>
    <?php if($notice!==''):?><div class="mg-cc-actions-alert is-success"><?=mg_e($notice)?></div><?php endif;?>
    <?php if($errorMessage!==''):?><div class="mg-cc-actions-alert is-error"><?=mg_e($errorMessage)?></div><?php endif;?>
    <section class="mg-cc-actions-stats" aria-label="Action status summary">
      <article><strong><?=count($actions)?></strong><span>Visible actions</span></article><article><strong><?= (int)($counts['waiting_for_approval']??0) ?></strong><span>Waiting</span></article><article><strong><?= (int)($counts['approved']??0) ?></strong><span>Approved</span></article><article><strong><?= (int)($counts['succeeded']??0) ?></strong><span>Succeeded</span></article><article><strong><?= (int)($counts['failed']??0) ?></strong><span>Failed</span></article>
    </section>
    <form method="get" class="mg-cc-actions-filter"><label>Status<select name="status"><option value="">All statuses</option><?php foreach(MG_MCP_CREATOR_CAMPAIGN_ACTION_STATUSES as $status):?><option value="<?=mg_e($status)?>"<?=$statusFilter===$status?' selected':''?>><?=mg_e(ucwords(str_replace('_',' ',$status)))?></option><?php endforeach;?></select></label><button type="submit">Filter</button></form>
    <?php if(!$schemaReady):?><section class="mg-cc-actions-empty"><strong>Phase 13C schema unavailable</strong><p>Import the required SQL before reviewing canonical action requests.</p></section>
    <?php elseif($actions===[]):?><section class="mg-cc-actions-empty"><strong>No actions match this view</strong><p>Requests appear here only after an authorized approval-gated connection uses an allowed Creator Campaign action tool under an active owner grant.</p></section>
    <?php else:?><section class="mg-cc-actions-list">
      <?php foreach($actions as $item):$input=(array)$item['input'];$target=(array)($input['_target']??[]);$approval=(array)$item['approval'];$receipt=is_array($item['receipt']??null)?$item['receipt']:null;?>
      <article class="mg-cc-action-card is-<?=mg_e((string)$item['status'])?>">
        <header><div><span><?=mg_e(strtoupper((string)$item['risk']))?> RISK · <?=mg_e((string)($target['type']??'creator campaign'))?></span><h2><?=mg_e((string)$item['tool'])?></h2><p><?=mg_e((string)$approval['requested_reason'])?></p></div><strong><?=mg_e(ucwords(str_replace('_',' ',(string)$item['status'])))?></strong></header>
        <dl><div><dt>Action ID</dt><dd><code><?=mg_e((string)$item['id'])?></code></dd></div><div><dt>Target</dt><dd><?=mg_e((string)($target['id']??'Not resolved'))?></dd></div><div><dt>Campaign</dt><dd><?=mg_e((string)($target['campaign_id']??'Not applicable'))?></dd></div><div><dt>Grant</dt><dd><?=mg_e((string)$item['grant']['id'])?></dd></div><div><dt>Client</dt><dd><?=mg_e((string)$item['connection']['client'])?></dd></div><div><dt>Approval expires</dt><dd><?=mg_e((string)$approval['expires_at'])?></dd></div></dl>
        <details><summary>Review exact sanitized input and evidence</summary><pre><?=mg_e(json_encode($input,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}')?></pre><p><strong>Fresh-state token:</strong> <code><?=mg_e((string)($item['fresh_state_token']??''))?></code></p></details>
        <?php if((string)$item['status']==='waiting_for_approval'):?>
          <form method="post" class="mg-cc-action-decision"><input type="hidden" name="csrf_token" value="<?=mg_e(mg_csrf_token())?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="action_id" value="<?=mg_e((string)$item['id'])?>"><label>Required owner decision reason<textarea required name="reason" minlength="5" maxlength="1000" rows="3" placeholder="Document why this action should or should not proceed."></textarea></label><div><button class="is-reject" type="submit" name="decision" value="reject">Reject</button><button type="submit" name="decision" value="approve">Approve request</button></div></form>
        <?php elseif((string)$item['status']==='approved'):?>
          <section class="mg-cc-action-execute"><div><span>Approval recorded</span><strong>Canonical effect has not happened</strong><p>Execution will revalidate current authority and call <code><?=mg_e((string)(mg_mcp_creator_campaign_action_contract((string)$item['tool'])['native_service']??''))?></code>.</p></div><form method="post" onsubmit="return confirm('Execute this approved Creator Campaign action now?');"><input type="hidden" name="csrf_token" value="<?=mg_e(mg_csrf_token())?>"><input type="hidden" name="action" value="execute"><input type="hidden" name="action_id" value="<?=mg_e((string)$item['id'])?>"><label><input required type="checkbox" name="confirm_execute" value="1"> I confirm this separate execution step.</label><button type="submit">Execute approved action</button></form></section>
        <?php elseif($receipt!==null):?>
          <section class="mg-cc-action-receipt"><div><span>Action receipt</span><strong><?=mg_e(ucfirst((string)$receipt['status']))?></strong><p><?=mg_e((string)($receipt['canonical_service']??''))?> · <?=mg_e((string)($receipt['canonical_action']??''))?></p></div><dl><div><dt>Reference</dt><dd><?=mg_e((string)($receipt['result_reference_type']??''))?> <?=mg_e((string)($receipt['result_reference_public_id']??''))?></dd></div><div><dt>Completed</dt><dd><?=mg_e((string)($receipt['completed_at']??'Pending'))?></dd></div></dl></section>
        <?php else:?><div class="mg-cc-action-terminal"><strong>No canonical effect available</strong><span><?=mg_e((string)($item['error']['message']??'The request is terminal.'))?></span></div><?php endif;?>
      </article>
      <?php endforeach;?>
    </section><?php endif;?>
    <aside class="mg-cc-actions-safety"><strong>Financial and legal controls remain bounded</strong><p>Payout recording creates an internal Microgifter record only. No payment provider, bank account, transfer, agreement acceptance, or external publication can be performed directly by the MCP client.</p></aside>
  </main>
</section>
