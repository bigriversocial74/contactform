<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/multi-agent-workspace-data.php';
$multiAgentTemplates = mg_multi_agent_templates();
$selectedAgentId = strtolower(trim((string) ($_GET['agent_id'] ?? '')));
$openAgentSettings = $selectedAgentId !== '' && (string) ($_GET['settings'] ?? '') === '1';
?>
<section class="mg-multi-agent-layer" data-multi-agent-layer hidden>
  <div class="mg-multi-agent-selector" data-multi-agent-selector>
    <header class="mg-multi-agent-selector-head">
      <div><span>Multi-agent workspace</span><h2>Choose an agent</h2><p>Create a chat agent or a focused task agent with its own conversations, memory, tools, and permissions.</p></div>
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

<section class="mg-agent-instance-canvas" data-agent-instance-canvas data-open-agent-settings="<?= $openAgentSettings ? 'true' : 'false' ?>" hidden>
  <button class="mg-agent-canvas-manage" type="button" data-agent-manage-open aria-label="Manage this agent" title="Manage agent">••• <span>Manage agent</span></button>
  <div class="mg-agent-runtime-layout">
    <main class="mg-agent-runtime-main">
      <div class="mg-agent-instance-welcome mg-agent-chat-landing">
        <div class="mg-agent-instance-avatar" data-agent-instance-icon>✦</div>
        <div class="mg-agent-chat-landing-copy">
          <span data-agent-instance-eyebrow>Agent</span>
          <strong data-agent-instance-name>Agent</strong>
          <p data-agent-instance-description></p>
          <p data-agent-instance-welcome></p>
        </div>
        <div class="mg-agent-chat-landing-actions" aria-label="Agent options">
          <button type="button" data-agent-new-thread>New conversation</button>
          <button type="button" data-agent-settings-open>Settings</button>
        </div>
      </div>
      <div class="mg-agent-instance-prompts" data-agent-instance-prompts></div>
      <section class="mg-agent-chat-history" aria-label="Agent conversation history">
        <div class="mg-agent-chat-history-head"><span>Conversation history</span><button type="button" data-agent-new-thread>New conversation</button></div>
        <nav data-agent-thread-list></nav>
      </section>
      <div class="mg-agent-runtime-messages" data-agent-runtime-messages aria-live="polite"></div>
      <form class="mg-agent-runtime-composer" data-agent-runtime-composer>
        <textarea name="message" rows="2" maxlength="3000" placeholder="Message this agent…" required></textarea>
        <button type="submit">Send</button>
        <small data-agent-runtime-status></small>
      </form>
    </main>

    <aside class="mg-agent-runtime-rail" aria-label="Agent memory">
      <section class="mg-agent-memory-panel"><span>Agent memory</span><div data-agent-memory-list><p>No saved memory yet.</p></div></section>
    </aside>
  </div>

  <div class="mg-agent-settings-modal" data-agent-settings-modal aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mg-agent-settings-title">
    <div class="mg-agent-settings-backdrop" data-agent-settings-close></div>
    <section class="mg-agent-settings-dialog" role="document">
      <header><div><span>Agent settings</span><h2 id="mg-agent-settings-title">Edit agent defaults</h2><p data-agent-onboarding-copy>Update the saved defaults used by this agent.</p></div><button type="button" data-agent-settings-close aria-label="Close agent settings">×</button></header>
      <form data-agent-onboarding-form>
        <label>Primary goal<input name="primary_goal" maxlength="220" placeholder="What should this agent help you accomplish?"></label>
        <label>Default budget or limits<input name="budget_guidance" maxlength="160" placeholder="Example: $25–$75 per occasion"></label>
        <label>Important preferences<textarea name="preferences" rows="4" maxlength="1000" placeholder="Interests, restrictions, location, tone, or campaign rules"></textarea></label>
        <div class="mg-agent-settings-actions">
          <button type="submit" name="settings_action" value="save">Save</button>
          <button type="submit" name="settings_action" value="apply">Save and apply</button>
        </div>
        <small data-agent-onboarding-status></small>
      </form>
    </section>
  </div>
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
