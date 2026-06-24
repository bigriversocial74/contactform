<?php
declare(strict_types=1);
$public_header_config = is_array($page_manifest['public_header'] ?? null) ? $page_manifest['public_header'] : [];
$public_nav_links = is_array($public_header_config['links'] ?? null) ? $public_header_config['links'] : [];
$public_page_id = (string) ($page_manifest['id'] ?? '');
$public_demo_href = '/learn-more.php';

$filtered_links = [];
foreach ($public_nav_links as $public_header_link) {
    $label = trim((string) ($public_header_link['label'] ?? ''));
    if (strcasecmp($label, 'Book A Demo') === 0 || strcasecmp($label, 'Book Demo') === 0) {
        $public_demo_href = (string) ($public_header_link['href'] ?? $public_demo_href);
        continue;
    }
    $href = (string) ($public_header_link['href'] ?? '');
    if (in_array($href, ['/corporate.php', '/retail.php', '/locations.php'], true)) {
        continue;
    }
    $filtered_links[] = $public_header_link;
}
$public_nav_links = $filtered_links;

$uses_standard_public_header = !$user;

if ($uses_standard_public_header) {
    $public_nav_links = [
        ['label'=>'Explore','href'=>'/discover.php'],
        ['label'=>'Campaigns','href'=>'/campaign.php'],
        ['label'=>'Merchant','href'=>'/merchant.php'],
        ['label'=>'Docs','href'=>'/developer-docs.php'],
    ];
}

$market_ticker_items = is_array($public_header_config['ticker_items'] ?? null) ? $public_header_config['ticker_items'] : [];
if (!$market_ticker_items) {
    $market_ticker_items = [
        ['symbol'=>'MGFTR','name'=>'Microgifter','price'=>'$0.842','change'=>'▲ 3.21%','trend'=>'up','href'=>'/profile.php?slug=microgifter'],
        ['symbol'=>'COF2','name'=>'Coffee for Two','price'=>'$18.00','change'=>'▲ 4.2%','trend'=>'up','href'=>'/profile.php?slug=coffee-for-two'],
        ['symbol'=>'BRNCH','name'=>'Weekend Brunch Drop','price'=>'$42.00','change'=>'▲ 8.7%','trend'=>'up','href'=>'/profile.php?slug=weekend-brunch-drop'],
        ['symbol'=>'CHEF','name'=>'Chef Table Access','price'=>'$150.00','change'=>'▲ 12.4%','trend'=>'up','href'=>'/profile.php?slug=chef-table-access'],
        ['symbol'=>'SHOW','name'=>'Venue Night Pass','price'=>'$36.00','change'=>'▲ 6.1%','trend'=>'up','href'=>'/profile.php?slug=venue-night-pass'],
        ['symbol'=>'TACO','name'=>'Local Food Crawl','price'=>'$55.00','change'=>'▼ 1.8%','trend'=>'down','href'=>'/profile.php?slug=local-food-crawl'],
        ['symbol'=>'VIPX','name'=>'Limited VIP Experience','price'=>'$225.00','change'=>'▲ 15.9%','trend'=>'up','href'=>'/profile.php?slug=limited-vip-experience'],
    ];
}
if ($user && $account_profile_url) {
    array_unshift($market_ticker_items, ['symbol'=>'YOU','name'=>'My Profile','price'=>'Profile','change'=>'OPEN','trend'=>'up','href'=>$account_profile_url]);
}

$show_home_search = $uses_standard_public_header;
$show_public_search = $show_home_search || (bool)($public_header_config['search'] ?? false) || (bool)$user;
$show_demo_button = !$user;

if (!$user && $public_page_id === 'home'):
?>
<style>
  .mg-index-public-header{
    position:fixed!important;
    top:0!important;
    left:0!important;
    right:0!important;
    z-index:1000!important;
    padding:10px 0!important;
    background:transparent!important;
    border:0!important;
    box-shadow:none!important;
    pointer-events:none!important;
  }
  .mg-index-header-inner{
    width:min(1180px,calc(100% - 40px));
    min-height:66px;
    margin:0 auto;
    padding:0 16px 0 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:28px;
    border:1px solid rgba(18,18,18,.065);
    border-radius:9px;
    background:rgba(248,248,245,.82);
    box-shadow:0 18px 54px rgba(0,0,0,.055);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);
    pointer-events:auto;
  }
  .mg-index-brand{
    display:inline-flex;
    align-items:center;
    flex:0 0 auto;
    text-decoration:none;
  }
  .mg-index-brand img{
    width:148px;
    max-width:100%;
    height:auto;
    display:block;
  }
  .mg-index-header-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:28px;
    min-width:0;
  }
  .mg-index-nav{
    display:flex;
    align-items:center;
    gap:26px;
  }
  .mg-index-nav a{
    color:#111!important;
    text-decoration:none!important;
    font-size:13px;
    font-weight:720;
    line-height:1;
    letter-spacing:-.02em;
    white-space:nowrap;
  }
  .mg-index-nav a:not(:last-child)::after{
    content:"+";
    display:inline-block;
    margin-left:26px;
    color:#111;
    font-size:12px;
    font-weight:800;
    opacity:.55;
  }
  .mg-index-cta{
    min-height:40px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:0 20px;
    border:1px solid rgba(0,0,0,.065);
    border-radius:7px;
    background:#fff;
    color:#101010!important;
    box-shadow:0 12px 30px rgba(0,0,0,.06);
    text-decoration:none!important;
    font-size:13px;
    font-weight:820;
    letter-spacing:-.02em;
    white-space:nowrap;
  }
  .mg-index-menu-toggle{
    width:42px;
    height:40px;
    display:none;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:4px;
    border:1px solid rgba(0,0,0,.08);
    border-radius:7px;
    background:#fff;
    color:#111;
  }
  .mg-index-menu-toggle span{
    width:16px;
    height:2px;
    display:block;
    border-radius:999px;
    background:currentColor;
  }
  .mg-index-mobile-menu{
    position:fixed;
    inset:0;
    z-index:1001;
  }
  .mg-index-mobile-menu[hidden]{display:none!important;}
  .mg-index-mobile-backdrop{
    position:absolute;
    inset:0;
    border:0;
    background:rgba(0,0,0,.36);
  }
  .mg-index-mobile-panel{
    position:absolute;
    top:12px;
    left:12px;
    right:12px;
    padding:18px;
    border:1px solid rgba(0,0,0,.08);
    border-radius:14px;
    background:#f8f8f5;
    box-shadow:0 24px 80px rgba(0,0,0,.22);
  }
  .mg-index-mobile-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin-bottom:18px;
  }
  .mg-index-mobile-head img{width:138px;height:auto;display:block;}
  .mg-index-mobile-close{
    width:38px;
    height:38px;
    border:1px solid rgba(0,0,0,.08);
    border-radius:8px;
    background:#fff;
    color:#111;
    font-size:24px;
    line-height:1;
  }
  .mg-index-mobile-nav{
    display:grid;
    gap:10px;
  }
  .mg-index-mobile-nav a{
    min-height:46px;
    display:flex;
    align-items:center;
    padding:0 14px;
    border:1px solid rgba(0,0,0,.06);
    border-radius:9px;
    background:#fff;
    color:#111;
    text-decoration:none;
    font-size:14px;
    font-weight:760;
  }
  @media(max-width:820px){
    .mg-index-header-inner{width:calc(100% - 24px);min-height:60px;padding:0 12px;gap:14px;}
    .mg-index-brand img{width:126px;}
    .mg-index-nav{display:none;}
    .mg-index-header-actions{gap:10px;}
    .mg-index-cta{min-height:38px;padding:0 14px;font-size:12px;}
    .mg-index-menu-toggle{display:inline-flex;}
  }
  @media(max-width:420px){
    .mg-index-brand img{width:116px;}
    .mg-index-cta{padding:0 12px;}
  }
</style>
<header class="mg-site-header mg-index-public-header" data-public-header data-header-variant="logged-out-index">
  <div class="mg-index-header-inner">
    <a class="mg-index-brand" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"></a>
    <div class="mg-index-header-actions">
      <nav class="mg-index-nav" aria-label="Primary navigation">
        <a href="#platform">Platform</a>
        <a href="#growth">API</a>
        <a href="/developer-docs.php">Docs</a>
      </nav>
      <a class="mg-index-cta" href="/signup.php">Create Account</a>
      <button class="mg-index-menu-toggle" type="button" data-index-menu-trigger aria-label="Open navigation menu" aria-controls="mg-index-mobile-menu" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>
<div class="mg-index-mobile-menu" id="mg-index-mobile-menu" data-index-mobile-menu hidden aria-hidden="true">
  <button class="mg-index-mobile-backdrop" type="button" data-index-menu-close aria-label="Close navigation menu"></button>
  <aside class="mg-index-mobile-panel" role="dialog" aria-modal="true" aria-labelledby="mg-index-mobile-title" tabindex="-1">
    <div class="mg-index-mobile-head">
      <a href="/index.php" id="mg-index-mobile-title"><img src="/images/logo_main_drk.png" alt="Microgifter"></a>
      <button class="mg-index-mobile-close" type="button" data-index-menu-close aria-label="Close navigation menu">×</button>
    </div>
    <nav class="mg-index-mobile-nav" aria-label="Mobile navigation">
      <a href="#platform">Platform</a>
      <a href="#growth">API</a>
      <a href="/developer-docs.php">Docs</a>
      <a href="/signup.php">Create Account</a>
    </nav>
  </aside>
</div>
<script>
(() => {
  if (window.__mgIndexPublicHeaderBound) return;
  window.__mgIndexPublicHeaderBound = true;
  const menu = () => document.querySelector('[data-index-mobile-menu]');
  const triggers = () => Array.from(document.querySelectorAll('[data-index-menu-trigger]'));
  const setOpen = (open) => {
    const panel = menu();
    if (!panel) return;
    triggers().forEach((trigger) => trigger.setAttribute('aria-expanded', open ? 'true' : 'false'));
    panel.hidden = !open;
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('mg-index-mobile-menu-open', open);
  };
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-index-menu-trigger]')) {
      event.preventDefault();
      const panel = menu();
      setOpen(!(panel && !panel.hidden));
      return;
    }
    if (event.target.closest('[data-index-menu-close]') || event.target.closest('.mg-index-mobile-nav a')) setOpen(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });
})();
</script>
<?php
return;
endif;
?>
<header class="mg-site-header mg-unified-header mg-market-universal-header" data-mg-universal-header data-public-header data-header-theme="market-dark" data-header-variant="<?= $user ? 'logged-in' : 'logged-out' ?>">
  <?php if ($public_page_id === 'home'): ?><span hidden>Turn future demand into present-day revenue</span><?php endif; ?>
  <div class="mg-header-inner nav-inner">
    <div class="mg-header-left">
      <a class="mg-brand brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
      <?php if ($show_public_search && $user): ?>
        <form class="mg-public-search" action="/discover.php" method="get" role="search">
          <input type="search" name="q" placeholder="Search Microgifter" aria-label="Search Microgifter" autocomplete="off">
        </form>
      <?php endif; ?>
    </div>

    <div class="mg-header-market" role="region" aria-label="Experience market ticker">
      <div class="mg-header-market-label">Experience Market</div>
      <div class="mg-header-market-track">
        <div class="mg-header-market-marquee">
          <?php for ($tickerPass = 0; $tickerPass < 2; $tickerPass++): ?>
            <div class="mg-header-market-row" <?= $tickerPass === 1 ? 'aria-hidden="true"' : '' ?>>
              <?php foreach ($market_ticker_items as $ticker_item): ?>
                <?php
                  $tickerHref = (string) ($ticker_item['href'] ?? '/discover.php');
                  $tickerTrend = (string) ($ticker_item['trend'] ?? 'up');
                ?>
                <a class="mg-header-ticker-item" href="<?= mg_e($tickerHref) ?>">
                  <strong><?= mg_e((string) ($ticker_item['symbol'] ?? 'MG')) ?></strong>
                  <span><?= mg_e((string) ($ticker_item['name'] ?? 'Experience')) ?></span>
                  <b><?= mg_e((string) ($ticker_item['price'] ?? '—')) ?></b>
                  <em class="is-<?= mg_e($tickerTrend) ?>"><?= mg_e((string) ($ticker_item['change'] ?? 'OPEN')) ?></em>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="mg-header-actions" data-header-template="<?= $user ? 'logged-in-public' : 'logged-out-public' ?>">
      <?php if (!$user && $public_nav_links): ?>
        <nav class="mg-site-nav mg-public-nav" aria-label="Primary navigation">
          <?php foreach ($public_nav_links as $public_header_link): ?>
            <a href="<?= mg_e((string) ($public_header_link['href'] ?? '#')) ?>"><?= mg_e((string) ($public_header_link['label'] ?? 'Learn More')) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <?php if ($show_demo_button): ?><a class="mg-public-demo" href="<?= mg_e($public_demo_href) ?>">Book A Demo</a><?php endif; ?>
      <?php if ($user): ?>
        <a class="mg-header-create" href="/build.php" data-header-create aria-label="Create">+</a>
        <div class="mg-header-signal" data-header-signal="notifications"><button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="System notifications"><svg viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0-6 6v3.6L4.7 15a1 1 0 0 0 .88 1.5h12.84A1 1 0 0 0 19.3 15L18 12.6V9a6 6 0 0 0-6-6Z" fill="currentColor"/></svg><span class="mg-header-badge" data-notification-badge hidden>0</span></button></div>
        <div class="mg-header-signal" data-header-signal="messages"><button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="Messages"><svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 3v-3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" fill="currentColor"/></svg><span class="mg-header-badge" data-message-badge hidden>0</span></button></div>
      <?php else: ?>
        <button class="mg-public-menu-toggle" type="button" data-public-menu-trigger aria-label="Open navigation menu" aria-controls="mg-public-mobile-menu" aria-expanded="false"><span></span><span></span><span></span></button>
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
<?php if (!$user): ?>
<div class="mg-public-mobile-menu" id="mg-public-mobile-menu" data-public-mobile-menu hidden aria-hidden="true">
  <button class="mg-public-mobile-backdrop" type="button" data-public-menu-close aria-label="Close navigation menu"></button>
  <aside class="mg-public-mobile-panel" role="dialog" aria-modal="true" aria-labelledby="mg-public-mobile-title" tabindex="-1">
    <div class="mg-public-mobile-head">
      <a class="mg-public-mobile-logo" href="/index.php" id="mg-public-mobile-title">Microgifter</a>
      <button class="mg-public-mobile-close" type="button" data-public-menu-close aria-label="Close navigation menu">×</button>
    </div>
    <form class="mg-public-mobile-search" action="/discover.php" method="get" role="search">
      <input type="search" name="q" placeholder="Search Microgifter" aria-label="Search Microgifter" autocomplete="off">
    </form>
    <nav class="mg-public-mobile-nav" aria-label="Mobile navigation">
      <?php foreach ($public_nav_links as $public_header_link): ?>
        <a href="<?= mg_e((string) ($public_header_link['href'] ?? '#')) ?>"><?= mg_e((string) ($public_header_link['label'] ?? 'Learn More')) ?></a>
      <?php endforeach; ?>
      <a href="/discover.php">Discover</a>
      <a href="<?= mg_e($public_demo_href) ?>">Book A Demo</a>
    </nav>
    <div class="mg-public-mobile-auth">
      <a href="/signin.php">Sign In</a>
      <a href="/signup.php">Create Account</a>
    </div>
  </aside>
</div>
<script>
(() => {
  if (window.__mgPublicMobileMenuBound) return;
  window.__mgPublicMobileMenuBound = true;
  const getMenu = () => document.querySelector('[data-public-mobile-menu]');
  const getTriggers = () => Array.from(document.querySelectorAll('[data-public-menu-trigger]'));
  const setOpen = (open) => {
    const menu = getMenu();
    if (!menu) return;
    getTriggers().forEach((trigger) => trigger.setAttribute('aria-expanded', open ? 'true' : 'false'));
    document.body.classList.toggle('mg-public-mobile-menu-open', open);
    if (open) {
      menu.hidden = false;
      menu.setAttribute('aria-hidden', 'false');
      requestAnimationFrame(() => {
        menu.classList.add('is-open');
        const panel = menu.querySelector('.mg-public-mobile-panel');
        if (panel) panel.focus({ preventScroll:true });
      });
      return;
    }
    menu.classList.remove('is-open');
    menu.setAttribute('aria-hidden', 'true');
    window.setTimeout(() => {
      if (!menu.classList.contains('is-open')) menu.hidden = true;
    }, 220);
  };
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-public-menu-trigger]')) {
      event.preventDefault();
      const menu = getMenu();
      setOpen(!(menu && menu.classList.contains('is-open')));
      return;
    }
    if (event.target.closest('[data-public-menu-close]')) {
      event.preventDefault();
      setOpen(false);
      return;
    }
    const link = event.target.closest('.mg-public-mobile-menu a');
    if (link) setOpen(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });
})();
</script>
<?php endif; ?>
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
