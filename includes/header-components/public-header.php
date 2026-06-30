<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/market/public-market-ticker.php';

$public_header_config = is_array($page_manifest['public_header'] ?? null) ? $page_manifest['public_header'] : [];
$public_nav_links = is_array($public_header_config['links'] ?? null) ? $public_header_config['links'] : [];
$public_demo_href = '/learn-more.php';
$public_phone_number = '1-800-269-7433';
$public_phone_href = 'tel:18002697433';
$public_brand_equation = 'DAVE™ — Digital Asset Value Equation';
$public_social_links = is_array($public_header_config['social_links'] ?? null) ? $public_header_config['social_links'] : [
    ['label' => 'LinkedIn', 'short' => 'IN', 'href' => 'https://www.linkedin.com/company/microgifter/'],
    ['label' => 'X', 'short' => 'X', 'href' => 'https://x.com/microgifter'],
    ['label' => 'Instagram', 'short' => 'IG', 'href' => 'https://www.instagram.com/microgifter/'],
];

$filtered_links = [];
foreach ($public_nav_links as $public_header_link) {
    $label = trim((string) ($public_header_link['label'] ?? ''));
    if (strcasecmp($label, 'Book A Demo') === 0 || strcasecmp($label, 'Book Demo') === 0) {
        $public_demo_href = (string) ($public_header_link['href'] ?? $public_demo_href);
        continue;
    }

    $href = (string) ($public_header_link['href'] ?? '');
    if (in_array($href, ['/corporate.php', '/retail.php', '/locations.php', '/campaign.php', '/developer-docs.php', '/merchant.php', '/buy-in.php'], true)) {
        continue;
    }

    $filtered_links[] = $public_header_link;
}
$public_nav_links = $filtered_links;

if (!$user) {
    $public_nav_links = [
        ['label' => 'Explore', 'href' => '/discover.php'],
        ['label' => 'Pricing', 'href' => '/pricing.php'],
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

if (!$market_ticker_items && !$user) {
    $market_ticker_items = mg_public_market_ticker_fallback_items();
}

$show_market_ticker = false; // Temporarily hidden; ticker logic and markup stay in place.
$show_demo_button = !$user;
?>
<header class="mg-site-header mg-unified-header mg-market-universal-header" data-mg-universal-header data-public-header data-header-theme="market-dark" data-header-variant="<?= $user ? 'logged-in' : 'logged-out' ?>">
  <div class="mg-header-inner nav-inner">
    <div class="mg-header-left">
      <div class="mg-brand-stack">
        <a class="mg-brand brand" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>Microgifter</span></a>
      </div>
      <?php if (!$user): ?>
        <div class="mg-header-phone-stack">
          <a class="mg-header-phone" href="<?= mg_e($public_phone_href) ?>" aria-label="Call Microgifter at <?= mg_e($public_phone_number) ?>"><?= mg_e($public_phone_number) ?></a>
          <span class="mg-brand-equation mg-phone-equation"><?= mg_e($public_brand_equation) ?></span>
        </div>
        <?php if ($public_social_links): ?>
          <nav class="mg-header-socials" aria-label="Microgifter social links">
            <?php foreach ($public_social_links as $public_social_link): ?>
              <?php
                $socialHref = trim((string) ($public_social_link['href'] ?? ''));
                $socialLabel = trim((string) ($public_social_link['label'] ?? 'Social'));
                $socialShort = trim((string) ($public_social_link['short'] ?? $socialLabel));
                if ($socialHref === '') continue;
              ?>
              <a class="mg-header-social-link" href="<?= mg_e($socialHref) ?>" target="_blank" rel="noopener noreferrer" aria-label="Microgifter on <?= mg_e($socialLabel) ?>"><?= mg_e($socialShort) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($user && (bool) ($public_header_config['search'] ?? false)): ?>
        <form class="mg-public-search" action="/discover.php" method="get" role="search">
          <input type="search" name="q" placeholder="Search Microgifter" aria-label="Search Microgifter" autocomplete="off">
        </form>
      <?php endif; ?>
    </div>

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
            <a class="mg-account-action" href="/forgot-password.php"><span class="mg-account-index">03</span><span>Reset password</span></a>
          </div>
        </div>
        <button class="mg-public-menu-toggle" type="button" data-public-menu-trigger aria-label="Open navigation menu" aria-controls="mg-public-mobile-menu" aria-expanded="false"><span></span><span></span><span></span></button>
      </div>
    <?php endif; ?>
  </div>
</header>

<?php if ($show_market_ticker): ?>
  <div class="mg-header-market-subbar" data-market-sticky role="region" aria-label="Local market ticker">
    <div class="mg-header-market" data-public-market-ticker>
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
                  $tickerStats = is_array($ticker_item['stats'] ?? null) ? $ticker_item['stats'] : [];
                  $tickerStat = is_array($ticker_item['stat'] ?? null) ? $ticker_item['stat'] : ($tickerStats[0] ?? null);
                  $tickerStatLabel = is_array($tickerStat) ? trim((string) ($tickerStat['label'] ?? '')) : '';
                  $tickerStatValue = is_array($tickerStat) ? trim((string) ($tickerStat['value'] ?? '')) : '';
                  $tickerStatsJson = json_encode($tickerStats, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
                ?>
                <a class="mg-header-ticker-item<?= $tickerFallback ? ' is-opening-soon' : '' ?>" href="<?= mg_e($tickerHref) ?>">
                  <strong><?= mg_e((string) ($ticker_item['symbol'] ?? 'MG')) ?></strong>
                  <span><?= mg_e((string) ($ticker_item['name'] ?? 'Merchant')) ?></span>
                  <b><?= mg_e((string) ($ticker_item['price'] ?? '—')) ?></b>
                  <em class="is-<?= mg_e($tickerTrend) ?>"><?= mg_e((string) ($ticker_item['change'] ?? '●')) ?></em>
                  <?php if ($tickerStatLabel !== '' || $tickerStatValue !== ''): ?>
                    <small class="mg-header-ticker-stat" data-ticker-stats="<?= mg_e($tickerStatsJson) ?>"><i><?= mg_e($tickerStatLabel) ?></i> <?= mg_e($tickerStatValue) ?></small>
                  <?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>
  <style>body.mg-discovery-page .mg-discover-stock-ticker{display:none!important}.mg-header-ticker-item.is-opening-soon b{color:#93c5fd}.mg-header-ticker-item.is-opening-soon em{color:#fbbf24}</style>
<?php endif; ?>

<?php if (!$user): ?>
<div class="mg-public-mobile-menu" id="mg-public-mobile-menu" data-public-mobile-menu hidden aria-hidden="true">
  <button class="mg-public-mobile-backdrop" type="button" data-public-menu-close aria-label="Close navigation menu"></button>
  <aside class="mg-public-mobile-panel" role="dialog" aria-modal="true" aria-labelledby="mg-public-mobile-title" tabindex="-1">
    <div class="mg-public-mobile-head">
      <a class="mg-public-mobile-logo" href="/index.php" id="mg-public-mobile-title"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>Microgifter</span></a>
      <button class="mg-public-mobile-close" type="button" data-public-menu-close aria-label="Close navigation menu">×</button>
    </div>
    <div class="mg-public-mobile-contact">
      <a class="mg-public-mobile-phone" href="<?= mg_e($public_phone_href) ?>" aria-label="Call Microgifter at <?= mg_e($public_phone_number) ?>"><?= mg_e($public_phone_number) ?></a>
      <span class="mg-public-mobile-equation"><?= mg_e($public_brand_equation) ?></span>
      <?php if ($public_social_links): ?>
        <nav class="mg-public-mobile-socials" aria-label="Microgifter social links">
          <?php foreach ($public_social_links as $public_social_link): ?>
            <?php
              $mobileSocialHref = trim((string) ($public_social_link['href'] ?? ''));
              $mobileSocialLabel = trim((string) ($public_social_link['label'] ?? 'Social'));
              $mobileSocialShort = trim((string) ($public_social_link['short'] ?? $mobileSocialLabel));
              if ($mobileSocialHref === '') continue;
            ?>
            <a href="<?= mg_e($mobileSocialHref) ?>" target="_blank" rel="noopener noreferrer" aria-label="Microgifter on <?= mg_e($mobileSocialLabel) ?>"><?= mg_e($mobileSocialShort) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
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
