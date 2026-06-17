<?php
declare(strict_types=1);
?>
<header class="mg-site-header mg-unified-header" data-mg-universal-header data-header-variant="logged-in">
  <div class="mg-header-left">
    <button class="mg-mobile-menu-toggle" type="button" data-mobile-sidebar-toggle aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>
    <a class="mg-brand mg-header-mobile-brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
    <nav class="mg-site-nav" aria-label="Primary navigation">
      <?php if ($header_mode === 'crm'): ?>
        <div class="mg-header-crm-tools"><input data-crm-search placeholder="Search leads, email, business, ZIP..." aria-label="Search CRM leads"><select data-crm-status-filter aria-label="Filter CRM leads by status"><option value="all">All statuses</option><option value="new">New</option><option value="assigned">Assigned</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="nurture">Nurture</option><option value="converted">Converted</option><option value="closed_lost">Closed lost</option><option value="spam">Spam</option></select></div>
      <?php elseif ($header_mode === 'agent'): ?>
        <div class="mg-header-agent-tools">
          <div class="mg-header-agent-tabs" data-agent-tabs aria-label="Workspace tabs">
            <?php foreach ([['agent','Agent','/agent.php'],['inbox','Inbox','/inbox.php'],['sent','Sent','/sent.php'],['claimed','Claimed','/claimed.php']] as $tab): ?>
              <?php $defaultGiftCount = $tab[0] === 'inbox' ? 1 : 0; $defaultGiftCount = ['inbox' => 3, 'sent' => 2, 'claimed' => 2][$tab[0]] ?? $defaultGiftCount; ?>
              <span class="mg-agent-tab-item mg-agent-tab-item-system" data-system-tab="<?= $tab[0] ?>"><a class="<?= $agent_tab === $tab[0] ? 'is-active' : '' ?>" href="<?= $tab[2] ?>"><span><?= $tab[1] ?></span><?php if (in_array($tab[0], ['inbox','sent','claimed'], true)): ?><b class="mg-agent-tab-badge<?= $defaultGiftCount > 0 ? ' has-unread' : '' ?>" data-gift-nav-count="<?= $tab[0] ?>" data-gift-nav-unread="<?= $tab[0] ?>"><?= $defaultGiftCount ?></b><?php endif; ?></a></span>
            <?php endforeach; ?>
          </div>
          <div class="mg-header-agent-search"><input type="search" placeholder="Search agents, products, gifts, claims…" aria-label="Search agent workspace" data-agent-global-search></div>
          <a class="mg-header-product-create" href="/build.php" data-product-header-create aria-label="Add product">+</a>
        </div>
      <?php elseif ($header_mode === 'account'): ?>
        <div class="mg-header-agent-tools mg-header-account-tools"><div class="mg-header-agent-search"><input type="search" placeholder="Search account, activity, messages, settings…" aria-label="Search account workspace"></div></div>
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
</header>
