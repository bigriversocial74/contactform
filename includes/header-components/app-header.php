<?php
declare(strict_types=1);

$mg_package_context = is_array($mg_package_context ?? null) ? $mg_package_context : mg_user_package_context(null, mg_current_user());
$can_merchant_nav = (bool) ($can_merchant_nav ?? !empty($mg_package_context['merchant_access']));
$can_create_microgift = (bool) ($can_create_microgift ?? ($can_merchant_nav && mg_package_limit_allows_create($mg_package_context, 'max_microgifts', 0)));
$is_authenticated_user = mg_current_user() !== null;
$can_create_list = (bool) ($can_create_list ?? $is_authenticated_user);
$can_agent_workspace = $is_authenticated_user || $can_merchant_nav || mg_has_permission('agent.workspace.view') || mg_has_permission('agent.manage');
$workspace_agent_tabs = ['agent'];
$is_agent_workspace_header = $header_mode === 'agent' && in_array((string) $agent_tab, $workspace_agent_tabs, true);
$show_header_create = !$is_agent_workspace_header;
$show_header_signals = true;
$show_header_cart = true;
$multiAgentHeaderAgents = [];
$multiAgentSelectedId = strtolower(trim((string) ($_GET['agent_id'] ?? '')));
if ($is_agent_workspace_header && $is_authenticated_user) {
    require_once dirname(__DIR__) . '/multi-agent-workspace-data.php';
    $multiAgentHeaderAgents = mg_multi_agent_open_tabs(mg_multi_agent_active_agents(mg_current_user()));
}
?>
<header class="mg-site-header mg-unified-header" data-mg-universal-header data-header-variant="logged-in">
  <div class="mg-header-inner nav-inner">
    <div class="mg-header-left">
      <button class="mg-mobile-menu-toggle" type="button" data-mobile-sidebar-toggle aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>
      <a class="mg-brand mg-header-mobile-brand" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>Microgifter</span></a>
      <nav class="mg-site-nav" aria-label="Primary navigation">
        <?php if ($header_mode === 'crm'): ?>
          <div class="mg-header-crm-tools">
            <input data-crm-search placeholder="Search leads, email, business, ZIP..." aria-label="Search CRM leads">
            <select data-crm-status-filter aria-label="Filter CRM leads by status"><option value="all">All statuses</option><option value="new">New</option><option value="assigned">Assigned</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="nurture">Nurture</option><option value="converted">Converted</option><option value="closed_lost">Closed lost</option><option value="spam">Spam</option></select>
          </div>
        <?php elseif ($is_agent_workspace_header): ?>
          <div class="mg-header-agent-tools">
            <div class="mg-header-agent-tabs" data-agent-tabs aria-label="Agent workspace tabs">
              <span class="mg-agent-tab-item mg-agent-tab-item-system" data-system-tab="agent"><a class="<?= $multiAgentSelectedId === '' ? 'is-active' : '' ?>" href="/agent.php"><span>Agent</span></a></span>
              <?php foreach ($multiAgentHeaderAgents as $workspaceAgent): ?>
                <?php $isSelected = $multiAgentSelectedId === (string) $workspaceAgent['id']; ?>
                <span class="mg-agent-tab-item mg-agent-tab-item-agent<?= ($workspaceAgent['runtime_status'] ?? '') === 'paused' ? ' is-paused' : '' ?>" data-agent-tab-id="<?= mg_e((string) $workspaceAgent['id']) ?>">
                  <a class="<?= $isSelected ? 'is-active' : '' ?>" href="/agent.php?agent_id=<?= rawurlencode((string) $workspaceAgent['id']) ?>"><i class="mg-agent-tab-status" aria-hidden="true"></i><span><?= mg_e((string) $workspaceAgent['name']) ?></span></a>
                </span>
              <?php endforeach; ?>
              <button class="mg-agent-tab-add" type="button" data-agent-add-tab aria-label="Add an agent" title="Add an agent">+</button>
            </div>
          </div>
        <?php elseif ($header_mode === 'builder'): ?>
          <div class="mg-builder-header-toggle" aria-label="Preview size">
            <div class="mg-builder-device-toggle">
              <button class="is-active" type="button" data-device="desktop" aria-label="Desktop preview">▣</button>
              <button type="button" data-device="mobile" aria-label="Mobile preview">▯</button>
            </div>
          </div>
        <?php endif; ?>
      </nav>
    </div>
    <?php require dirname(__DIR__) . '/header-templates/logged-in.php'; ?>
  </div>
</header>
<style>
html body.mg-app-page.mg-section-agent .mg-header-agent-tabs [data-system-tab="agent"]{display:inline-flex!important;visibility:visible!important;flex:0 0 auto!important}
</style>

<?php
require dirname(__DIR__) . '/header-templates/create-menu.php';
require __DIR__ . '/create-list-extension.php';
?>
