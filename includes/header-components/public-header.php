<?php
declare(strict_types=1);
$public_header_config = is_array($page_manifest['public_header'] ?? null) ? $page_manifest['public_header'] : [];
$public_nav_links = is_array($public_header_config['links'] ?? null) ? $public_header_config['links'] : [];
$public_page_id = (string) ($page_manifest['id'] ?? '');
$public_demo_href = '/learn-more.php';

$filtered_links = [];
foreach ($public_nav_links as $public_header_link) {
    $label = trim((string) ($public_header_link['label'] ?? ''));
    if (strcasecmp($label, 'Book A Demo') === 0) {
        $public_demo_href = (string) ($public_header_link['href'] ?? $public_demo_href);
        continue;
    }
    $filtered_links[] = $public_header_link;
}
$public_nav_links = $filtered_links;

if (!$user && in_array($public_page_id, ['home','index'], true)) {
    $public_nav_links = [
        ['label'=>'Corporate Gifting','href'=>'/corporate.php'],
        ['label'=>'Retail Subscriptions','href'=>'/retail.php'],
        ['label'=>'Locations','href'=>'/locations.php'],
    ];
}
$show_home_search = !$user && in_array($public_page_id, ['home','index'], true);
$show_public_search = $show_home_search || (bool)($public_header_config['search'] ?? false) || (bool)$user;
$show_demo_button = !$user;
?>
<header class="mg-site-header mg-unified-header" data-mg-universal-header data-public-header data-header-variant="<?= $user ? 'logged-in' : 'logged-out' ?>">
  <?php if ($public_page_id === 'home'): ?><span hidden>Turn future demand into present-day revenue</span><?php endif; ?>
  <div class="mg-header-inner nav-inner">
    <div class="mg-header-left">
      <a class="mg-brand brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
      <?php if ($show_public_search): ?>
        <form class="mg-public-search" action="/discover.php" method="get" role="search">
          <input type="search" name="q" placeholder="Search Microgifter" aria-label="Search Microgifter" autocomplete="off">
        </form>
      <?php endif; ?>
      <?php if (!$user && $public_nav_links): ?>
        <nav class="mg-site-nav mg-public-nav" aria-label="Primary navigation">
          <?php foreach ($public_nav_links as $public_header_link): ?>
            <a href="<?= mg_e((string) ($public_header_link['href'] ?? '#')) ?>"><?= mg_e((string) ($public_header_link['label'] ?? 'Learn More')) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>

    <div class="mg-header-actions" data-header-template="<?= $user ? 'logged-in-public' : 'logged-out-public' ?>">
      <?php if ($show_demo_button): ?><a class="mg-public-demo" href="<?= mg_e($public_demo_href) ?>">Book A Demo</a><?php endif; ?>
      <?php if ($user): ?>
        <a class="mg-header-create" href="/build.php" data-header-create aria-label="Create">+</a>
        <div class="mg-header-signal" data-header-signal="notifications"><button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="System notifications"><svg viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0-6 6v3.6L4.7 15a1 1 0 0 0 .88 1.5h12.84A1 1 0 0 0 19.3 15L18 12.6V9a6 6 0 0 0-6-6Z" fill="currentColor"/></svg><span class="mg-header-badge" data-notification-badge hidden>0</span></button></div>
        <div class="mg-header-signal" data-header-signal="messages"><button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="Messages"><svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 3v-3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" fill="currentColor"/></svg><span class="mg-header-badge" data-message-badge hidden>0</span></button></div>
      <?php endif; ?>
      <div class="mg-account-menu" data-mg-auth-menu>
        <button class="mg-account-trigger" type="button" data-mg-auth-trigger aria-expanded="false">
          <span class="mg-avatar"><?= mg_e($user ? $display_initial : 'A') ?></span>
          <span class="mg-account-copy">
            <span class="mg-account-name"><?= mg_e($user ? $display_name : 'Account') ?></span>
            <span class="mg-account-role"><?= mg_e($user ? (string) ($user_roles[0] ?? 'member') : 'Guest') ?></span>
          </span>
          <span class="mg-account-caret">⌄</span>
        </button>

        <div class="mg-account-actions">
          <div class="mg-account-menu-head">
            <span class="mg-account-status-light"></span>
            <span class="mg-account-head-copy">
              <span class="mg-account-head-name"><?= mg_e($user ? $display_name : 'Account') ?></span>
              <span class="mg-account-head-email"><?= mg_e($user ? $display_email : 'Guest') ?></span>
            </span>
            <span class="mg-account-session-label">SESSION</span>
          </div>

          <?php if ($user): ?>
            <?php $menuIndex = 1; ?>
            <a class="mg-account-action" href="/inbox.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>IN/OUT Box</span></a>
            <a class="mg-account-action" href="/feed.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Feed</span></a>
            <?php if ($account_profile_url): ?><a class="mg-account-action" href="<?= mg_e($account_profile_url) ?>"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Profile</span></a><?php endif; ?>
            <?php if ($account_storefront_url): ?><a class="mg-account-action" href="<?= mg_e($account_storefront_url) ?>"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Storefront</span></a><?php endif; ?>
            <a class="mg-account-action" href="/account.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Profile Settings</span></a>
            <a class="mg-account-action" href="/account-commerce.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Commerce center</span></a>
            <a class="mg-account-action" href="/merchant.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Merchant Dashboard</span></a>
            <?php if ($can_sales_crm): ?><a class="mg-account-action" href="/sales-crm.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>CRM dashboard</span></a><?php endif; ?>
            <?php if ($can_admin_dashboard): ?><a class="mg-account-action" href="/account-admin.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Admin dashboard</span></a><?php endif; ?>
            <button class="mg-account-action mg-account-logout" type="button" data-auth-logout><span class="mg-account-index">00</span><span>Sign out</span></button>
          <?php else: ?>
            <a class="mg-account-action" href="/signin.php"><span class="mg-account-index">01</span><span>Sign in</span></a>
            <a class="mg-account-action" href="/signup.php"><span class="mg-account-index">02</span><span>Create account</span></a>
          <?php endif; ?>
        </div>
      </div>
      <button class="mg-cart-header-button" type="button" data-cart-trigger aria-label="Open shopping cart" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="19" r="1.5" fill="currentColor"/><circle cx="18" cy="19" r="1.5" fill="currentColor"/></svg><span class="mg-cart-header-badge" data-cart-badge hidden>0</span></button>
    </div>
  </div>
</header>
<?php if ($user): ?>
<div class="mg-create-menu" id="mg-create-menu" data-create-menu hidden aria-hidden="true">
  <button class="mg-create-menu-backdrop" type="button" data-create-menu-close aria-label="Close create menu"></button>
  <section class="mg-create-menu-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-create-menu-title" tabindex="-1">
    <header class="mg-create-menu-head">
      <div><span>Create</span><h2 id="mg-create-menu-title">What do you want to add?</h2><p>Choose a workspace to start creating.</p></div>
      <button class="mg-create-menu-close" type="button" data-create-menu-close aria-label="Close create menu">×</button>
    </header>
    <div class="mg-create-menu-grid">
      <a href="/build.php" data-create-menu-option="microgift"><span class="mg-create-menu-icon" aria-hidden="true">M</span><strong>Microgift</strong><small>Create a prepaid local gift or offer.</small></a>
      <a href="/feed.php" data-create-menu-option="post" aria-controls="mg-post-composer-modal"><span class="mg-create-menu-icon" aria-hidden="true">P</span><strong>Post</strong><small>Publish an update to your public feed.</small></a>
      <a href="/account-subscriptions.php" data-create-menu-option="subscription"><span class="mg-create-menu-icon" aria-hidden="true">S</span><strong>Subscription</strong><small>Create or manage a recurring membership.</small></a>
      <a href="/merchant-storefront.php" data-create-menu-option="storefront"><span class="mg-create-menu-icon" aria-hidden="true">F</span><strong>Storefront</strong><small>Configure your public merchant storefront.</small></a>
      <a href="/agent.php" data-create-menu-option="agent"><span class="mg-create-menu-icon" aria-hidden="true">A</span><strong>Agent</strong><small>Create or open an automated gifting agent.</small></a>
      <a href="/merchant-locations.php" data-create-menu-option="location"><span class="mg-create-menu-icon" aria-hidden="true">L</span><strong>Add Location</strong><small>Add a merchant claim and redemption location.</small></a>
    </div>
  </section>
</div>
<?php require __DIR__ . '/post-composer-modal.php'; ?>
<?php endif; ?>