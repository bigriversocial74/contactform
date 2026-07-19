<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/multi-agent-workspace-data.php';
$multiAgentTemplates = mg_multi_agent_templates();
$selectedAgentId = strtolower(trim((string) ($_GET['agent_id'] ?? '')));
?>
<section class="mg-multi-agent-layer" data-multi-agent-layer hidden>
  <div class="mg-multi-agent-selector" data-multi-agent-selector>
    <header class="mg-multi-agent-selector-head">
      <div><span>Multi-agent workspace</span><h2>Choose a specialized agent</h2><p>Create a focused workspace with its own goal, conversations, memory, tools, and permissions.</p></div>
      <button type="button" data-multi-agent-selector-close aria-label="Close agent selector">×</button>
    </header>
    <div class="mg-multi-agent-filter-row">
      <input type="search" placeholder="Search agents" aria-label="Search available agents" data-multi-agent-search>
      <div class="mg-multi-agent-filter-pills" aria-label="Agent categories"><button class="is-active" type="button" data-agent-filter="all">All</button><button type="button" data-agent-filter="personal">Personal</button><button type="button" data-agent-filter="merchant">Merchant</button><button type="button" data-agent-filter="community">Community</button></div>
    </div>
    <div class="mg-multi-agent-card-grid">
      <?php foreach ($multiAgentTemplates as $templateKey => $template): ?>
        <?php $filter = !empty($template['merchant_required']) ? 'merchant' : (in_array($templateKey, ['community_fundraising','workplace_rewards','creator_merch'], true) ? 'community' : 'personal'); ?>
        <article class="mg-multi-agent-card<?= ($template['status'] ?? '') !== 'active' ? ' is-coming-soon' : '' ?>" data-agent-template-card data-agent-template-key="<?= mg_e($templateKey) ?>" data-agent-filter-group="<?= mg_e($filter) ?>" data-agent-search-text="<?= mg_e(strtolower($template['name'] . ' ' . $template['description'])) ?>">
          <div class="mg-multi-agent-card-icon" aria-hidden="true"><?= mg_e((string) $template['icon']) ?></div>
          <div><span><?= mg_e(ucfirst($filter)) ?></span><h3><?= mg_e((string) $template['name']) ?></h3><p><?= mg_e((string) $template['description']) ?></p></div>
          <?php if (($template['status'] ?? '') === 'active'): ?><button type="button" data-create-agent-template="<?= mg_e($templateKey) ?>">Start agent</button><?php else: ?><strong>Coming soon</strong><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mg-agent-instance-canvas" data-agent-instance-canvas hidden>
  <header class="mg-agent-instance-head"><div><span data-agent-instance-eyebrow>Specialized agent</span><h2 data-agent-instance-name>Agent</h2><p data-agent-instance-description></p></div><button type="button" data-agent-manage-open>Manage</button></header>
  <div class="mg-agent-instance-welcome"><div class="mg-agent-instance-avatar" data-agent-instance-icon>✦</div><div><strong data-agent-instance-welcome></strong><p>This agent keeps its own conversation context while sharing Microgifter’s protected commerce services and account permissions.</p></div></div>
  <div class="mg-agent-instance-prompts" data-agent-instance-prompts></div>
</section>

<div class="mg-agent-manage-modal" data-agent-manage-modal aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mg-agent-manage-title">
  <div class="mg-agent-manage-backdrop" data-agent-manage-close></div>
  <section class="mg-agent-manage-dialog" role="document">
    <header><div><span>Agent controls</span><h2 id="mg-agent-manage-title" data-agent-manage-name>Manage agent</h2><p>Closing a tab does not remove the agent from your sidebar or erase its conversations.</p></div><button type="button" data-agent-manage-close aria-label="Close agent controls">×</button></header>
    <div class="mg-agent-manage-actions">
      <button type="button" data-agent-action="close"><strong>Close tab</strong><span>Remove it from the top tab row. The sidebar entry stays visible.</span></button>
      <button type="button" data-agent-action="pause"><strong>Pause agent</strong><span>Keep its history and configuration while stopping active agent behavior.</span></button>
      <button type="button" data-agent-action="archive"><strong>Archive agent</strong><span>Move it out of the active workspace while preserving its records.</span></button>
      <button class="is-danger" type="button" data-agent-action="delete"><strong>Delete agent</strong><span>Soft-delete the agent after a final confirmation. The default Agent is never affected.</span></button>
    </div>
    <div class="mg-agent-delete-confirm" data-agent-delete-confirm hidden><label>Type <strong>DELETE</strong> to confirm.<input type="text" autocomplete="off" data-agent-delete-confirm-input></label><button class="is-danger" type="button" data-agent-delete-final disabled>Delete permanently</button></div>
    <p class="mg-agent-manage-status" data-agent-manage-status aria-live="polite"></p>
  </section>
</div>

<script type="application/json" id="mg-agent-template-data"><?= json_encode($multiAgentTemplates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mg-selected-agent-id"><?= json_encode($selectedAgentId) ?></script>
