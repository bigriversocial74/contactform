<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/market/public-market-ticker.php';

$public_header_config = is_array($page_manifest['public_header'] ?? null) ? $page_manifest['public_header'] : [];
$public_nav_links = is_array($public_header_config['links'] ?? null) ? $public_header_config['links'] : [];
$public_demo_href = '/learn-more.php';

$filtered_links = [];
foreach ($public_nav_links as $public_header_link) {
    $label = trim((string) ($public_header_link['label'] ?? ''));
    if (strcasecmp($label, 'Book A Demo') === 0 || strcasecmp($label, 'Book Demo') === 0) {
        $public_demo_href = (string) ($public_header_link['href'] ?? $public_demo_href);
        continue;
    }
    $href = (string) ($public_header_link['href'] ?? '');
    if (in_array($href, ['/corporate.php', '/retail.php', '/locations.php', '/campaign.php'], true)) {
        continue;
    }
    $filtered_links[] = $public_header_link;
}
$public_nav_links = $filtered_links;

if (!$user) {
    // One logged-out public nav across every public page. Page-level link overrides are intentionally ignored.
    $public_nav_links = [
        ['label'=>'Explore','href'=>'/discover.php'],
        ['label'=>'Merchant','href'=>'/merchant.php'],
        ['label'=>'Pricing','href'=>'/pricing.php'],
        ['label'=>'Docs','href'=>'/developer-docs.php'],
    ];
}

$market_ticker_items = [];
if (!$user) {
    try {
        $market_ticker_items = mg_public_market_ticker_items(mg_db(), 12, true);
    } catch (Throwable) {
        $market_ticker_items = mg_public_market_ticker_fallback_items();
    }
}
if (!$market_ticker_items && is_array($public_header_config['ticker_items'] ?? null)) {
    $market_ticker_items = $public_header_config['ticker_items'];
}
if ($user && $account_profile_url) {
    array_unshift($market_ticker_items, ['symbol'=>'YOU','name'=>'My Profile','price'=>'Profile','change'=>'OPEN','trend'=>'up','href'=>$account_profile_url]);
}

$show_market_ticker = !$user && ($public_header_config['ticker'] ?? true) !== false && !empty($market_ticker_items);
$show_demo_button = !$user;
?>
<header class="mg-site-header mg-unified-header mg-market-universal-header" data-mg-universal-header data-public-header data-header-theme="market-dark" data-header-variant="<?= $user ? 'logged-in' : 'logged-out' ?>">
  <div class="mg-header-inner nav-inner">
    <div class="mg-header-left">
      <a class="mg-brand brand" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>Microgifter</span></a>
      <?php if ($user && (bool)($public_header_config['search'] ?? false)): ?>
        <form class="mg-public-search" action="/discover.php" method="get" role="search">
          <input type="search" name="q" placeholder="Search Microgifter" aria-label="Search Microgifter" autocomplete="off">
        </form>
      <?php endif; ?>
    </div>

    <?php if ($show_market_ticker): ?>
      <div class="mg-header-market" role="region" aria-label="Local market ticker" data-public-market-ticker>
        <div class="mg-header-market-label">Local Market</div>
        <div class="mg-header-market-track">
          <div class="mg-header-market-marquee">
            <?php for ($tickerPass = 0; $tickerPass < 2; $tickerPass++): ?>
              <div class="mg-header-market-row" <?= $tickerPass === 1 ? 'aria-hidden="true"' : '' ?>>
                <?php foreach ($market_ticker_items as $ticker_item): ?>
                  <?php
                    $tickerHref = (string) ($ticker_item['href'] ?? '/discover.php');
                    $tickerTrend = (string) ($ticker_item['trend'] ?? 'up');
                    $tickerFallback = !empty($ticker_item['is_fallback']);
                  ?>
                  <a class="mg-header-ticker-item<?= $tickerFallback ? ' is-opening-soon' : '' ?>" href="<?= mg_e($tickerHref) ?>">
                    <strong><?= mg_e((string) ($ticker_item['symbol'] ?? 'MG')) ?></strong>
                    <span><?= mg_e((string) ($ticker_item['name'] ?? 'Merchant')) ?></span>
                    <b><?= mg_e((string) ($ticker_item['price'] ?? '—')) ?></b>
                    <em class="is-<?= mg_e($tickerTrend) ?>"><?= mg_e((string) ($ticker_item['change'] ?? 'LIVE')) ?></em>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
      <style>body.mg-discovery-page .mg-discover-stock-ticker{display:none!important}.mg-header-ticker-item.is-opening-soon b{color:#93c5fd}.mg-header-ticker-item.is-opening-soon em{color:#fbbf24}</style>
    <?php endif; ?>

    <?php if ($user): ?>
      <?php
        $show_header_create = true;
        $show_header_signals = true;
        $show_header_cart = true;
        require dirname(__DIR__) . '/header-templates/logged-in.php';
      ?>
    <?php else: ?>
      <div class="mg-header-actions" data-header-template="logged-out-public">
        <?php if ($public_nav_links): ?>
          <nav class="mg-site-nav mg-public-nav" aria-label="Primary navigation">
            <?php foreach ($public_nav_links as $public_header_link): ?>
              <a href="<?= mg_e((string) ($public_header_link['href'] ?? '#')) ?>"><?= mg_e((string) ($public_header_link['label'] ?? 'Learn More')) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
        <?php if ($show_demo_button): ?><a class="mg-public-demo" href="<?= mg_e($public_demo_href) ?>">Book A Demo</a><?php endif; ?>
        <button class="mg-public-menu-toggle" type="button" data-public-menu-trigger aria-label="Open navigation menu" aria-controls="mg-public-mobile-menu" aria-expanded="false"><span></span><span></span><span></span></button>
        <div class="mg-account-menu" data-mg-auth-menu>
          <button class="mg-account-trigger" type="button" data-mg-auth-trigger aria-expanded="false">
            <span class="mg-avatar">A</span>
            <span class="mg-account-copy"><span class="mg-account-name">Account</span><span class="mg-account-role">Guest</span></span>
            <span class="mg-account-caret">⌄</span>
          </button>
          <div class="mg-account-actions">
            <div class="mg-account-menu-head"><span class="mg-account-status-light"></span><span class="mg-account-head-copy"><span class="mg-account-head-name">Account</span><span class="mg-account-head-email">Guest</span></span><span class="mg-account-session-label">SESSION</span></div>
            <a class="mg-account-action" href="/signin.php"><span class="mg-account-index">01</span><span>Sign in</span></a>
            <a class="mg-account-action" href="/signup.php"><span class="mg-account-index">02</span><span>Create account</span></a>
            <a class="mg-account-action mg-account-upgrade" href="/pricing.php"><span class="mg-account-index">UP</span><span>Pricing</span></a>
          </div>
        </div>
        <button class="mg-cart-header-button" type="button" data-cart-trigger aria-label="Open shopping cart" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="19" r="1.5" fill="currentColor"/><circle cx="18" cy="19" r="1.5" fill="currentColor"/></svg><span class="mg-cart-header-badge" data-cart-badge hidden>0</span></button>
      </div>
    <?php endif; ?>
  </div>
</header>

<?php if ((string)($page_manifest['id'] ?? '') === 'home'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const title = document.getElementById('mgHeroTitle');
  const lede = document.querySelector('.mg-v4-lede');
  const nav = document.querySelector('.mg-v4-links');
  if (title) title.textContent = 'The Rewards Layer for Local Commerce.';
  if (lede) lede.textContent = 'Turn promotions, gift certificates, loyalty rewards, and customer engagement into tracked revenue from one simple platform.';
  if (nav && !nav.querySelector('a[href="/pricing.php"]')) {
    const pricing = document.createElement('a');
    pricing.href = '/pricing.php';
    pricing.textContent = 'Pricing';
    const docs = nav.querySelector('a[href="/developer-docs.php"]');
    nav.insertBefore(pricing, docs || null);
  }
});
</script>
<?php endif; ?>

<?php if (!$user): ?>
<div class="mg-public-mobile-menu" id="mg-public-mobile-menu" data-public-mobile-menu hidden aria-hidden="true">
  <button class="mg-public-mobile-backdrop" type="button" data-public-menu-close aria-label="Close navigation menu"></button>
  <aside class="mg-public-mobile-panel" role="dialog" aria-modal="true" aria-labelledby="mg-public-mobile-title" tabindex="-1">
    <div class="mg-public-mobile-head">
      <a class="mg-public-mobile-logo" href="/index.php" id="mg-public-mobile-title"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>Microgifter</span></a>
      <button class="mg-public-mobile-close" type="button" data-public-menu-close aria-label="Close navigation menu">×</button>
    </div>
    <form class="mg-public-mobile-search" action="/discover.php" method="get" role="search">
      <input type="search" name="q" placeholder="Search Microgifter" aria-label="Search Microgifter" autocomplete="off">
    </form>
    <nav class="mg-public-mobile-nav" aria-label="Mobile navigation">
      <?php foreach ($public_nav_links as $public_header_link): ?>
        <a href="<?= mg_e((string) ($public_header_link['href'] ?? '#')) ?>"><?= mg_e((string) ($public_header_link['label'] ?? 'Learn More')) ?></a>
      <?php endforeach; ?>
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
<?php else: ?>
<?php require dirname(__DIR__) . '/header-templates/create-menu.php'; ?>
<?php endif; ?>
