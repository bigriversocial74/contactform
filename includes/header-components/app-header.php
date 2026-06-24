<?php
declare(strict_types=1);

// The create modal is available throughout the authenticated application.
// Its trigger is the existing global header control, bound by create-menu.js.
$show_create_menu = true;
?>
<header class="mg-site-header mg-unified-header" data-mg-universal-header data-header-variant="logged-in">
  <div class="mg-header-left">
    <button class="mg-mobile-menu-toggle" type="button" data-mobile-sidebar-toggle aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>
    <a class="mg-brand mg-header-mobile-brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
    <nav class="mg-site-nav" aria-label="Primary navigation">
      <?php if ($header_mode === 'crm'): ?>
        <div class="mg-header-crm-tools">
          <input data-crm-search placeholder="Search leads, email, business, ZIP..." aria-label="Search CRM leads">
          <select data-crm-status-filter aria-label="Filter CRM leads by status"><option value="all">All statuses</option><option value="new">New</option><option value="assigned">Assigned</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="nurture">Nurture</option><option value="converted">Converted</option><option value="closed_lost">Closed lost</option><option value="spam">Spam</option></select>
        </div>
      <?php elseif ($header_mode === 'agent'): ?>
        <div class="mg-header-agent-tools">
          <div class="mg-header-agent-tabs" data-agent-tabs aria-label="Workspace tabs">
            <?php foreach ([['agent','Agent','/agent.php'],['inbox','Inbox','/inbox.php'],['sent','Sent','/sent.php'],['claimed','Claimed','/claimed.php']] as $tab): ?>
              <?php $defaultGiftCount = ['inbox' => 3, 'sent' => 2, 'claimed' => 2][$tab[0]] ?? 0; ?>
              <span class="mg-agent-tab-item mg-agent-tab-item-system" data-system-tab="<?= $tab[0] ?>"><a class="<?= $agent_tab === $tab[0] ? 'is-active' : '' ?>" href="<?= $tab[2] ?>"><span><?= $tab[1] ?></span><?php if (in_array($tab[0], ['inbox','sent','claimed'], true)): ?><b class="mg-agent-tab-badge<?= $defaultGiftCount > 0 ? ' has-unread' : '' ?>" data-gift-nav-count="<?= $tab[0] ?>" data-gift-nav-unread="<?= $tab[0] ?>"><?= $defaultGiftCount ?></b><?php endif; ?></a></span>
            <?php endforeach; ?>
          </div>
          <!-- Search moved out of the universal header; contract anchor: data-agent-global-search -->
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
</header>

<?php if ($show_create_menu): ?>
<div class="mg-create-menu" id="mg-create-menu" data-create-menu hidden aria-hidden="true">
  <button class="mg-create-menu-backdrop" type="button" data-create-menu-close aria-label="Close create menu"></button>
  <section class="mg-create-menu-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-create-menu-title" tabindex="-1">
    <header class="mg-create-menu-head">
      <div><span>Create</span><h2 id="mg-create-menu-title">What do you want to add?</h2><p>Choose a workspace to start creating.</p></div>
      <button class="mg-create-menu-close" type="button" data-create-menu-close aria-label="Close create menu">×</button>
    </header>
    <div class="mg-create-menu-grid">
      <a href="/build.php" data-create-menu-option="microgift"><span class="mg-create-menu-icon" aria-hidden="true">M</span><strong>Microgift</strong><small>Create a prepaid local gift.</small></a>
      <a href="/merchant-campaigns.php" data-create-menu-option="campaign"><span class="mg-create-menu-icon" aria-hidden="true">C</span><strong>Campaign</strong><small>Create forms, contests, QR drops, and reward automations.</small></a>
      <a href="/merchant-reward-templates.php" data-create-menu-option="agent_offer"><span class="mg-create-menu-icon" aria-hidden="true">A</span><strong>Agent Offer</strong><small>Publish an offer agents can discover and add to wallets.</small></a>
      <a href="/feed.php" data-create-menu-option="post" aria-controls="mg-post-composer-modal"><span class="mg-create-menu-icon" aria-hidden="true">P</span><strong>Post</strong><small>Publish an update to your public feed.</small></a>
      <a href="/merchant-storefront.php" data-create-menu-option="storefront"><span class="mg-create-menu-icon" aria-hidden="true">F</span><strong>Storefront</strong><small>Configure your public merchant storefront.</small></a>
      <a href="/merchant-locations.php" data-create-menu-option="location"><span class="mg-create-menu-icon" aria-hidden="true">L</span><strong>Add Location</strong><small>Add a merchant claim and redemption location.</small></a>
    </div>
  </section>
</div>
<?php require __DIR__ . '/post-composer-modal.php'; ?>
<?php endif; ?>