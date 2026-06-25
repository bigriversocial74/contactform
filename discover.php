<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Discover local merchants | Microgifter';
$page_section = 'discover';
$header_mode = 'public';

$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/profile-discovery.css',
];

$page_scripts = [
    '/assets/js/profile-discovery.js',
];

$page_manifest = [
    'id' => 'discover',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-discovery-page',
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'show_cart' => false,
        'cart' => false,
        'ticker' => false,
        'links' => [
            ['label' => 'Feed', 'href' => '/feed.php'],
            ['label' => 'Discover', 'href' => '/discover.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'discover',
        'sections' => [],
    ],
];

$discoverCategories = [
    '' => ['All categories', 'ALL'],
    'restaurant' => ['Restaurants', 'FOOD'],
    'bar' => ['Bars & nightlife', 'BAR'],
    'coffee' => ['Coffee shops', 'CAFE'],
    'event' => ['Events & venues', 'EVENT'],
    'fitness' => ['Fitness & wellness', 'FIT'],
    'retail' => ['Retail', 'SHOP'],
    'service' => ['Local services', 'SERV'],
    'creator' => ['Creators', 'MAKER'],
];
$discoverStates = [
    '' => 'All states', 'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'Washington DC',
];
$discoverTickerItems = [
    ['MGFTR', 'Local market', '$0.842', '▲ 3.21%'],
    ['COF2', 'Coffee for Two', '$18.00', '▲ 4.2%'],
    ['BRNCH', 'Weekend Brunch', '$42.00', '▲ 8.7%'],
    ['CHEF', 'Chef Table', '$150.00', '▲ 12.4%'],
    ['SHOW', 'Venue Night', '$36.00', '▲ 6.1%'],
    ['TACO', 'Food Crawl', '$55.00', '▲ 9.3%'],
];

require __DIR__ . '/includes/header.php';
?>

<style>
:root{--discover-dark:#071225;--discover-muted:#64748b;--discover-border:#dbe5f1;--discover-soft:#f8fafc;--discover-purple:#7c3aed;--discover-teal:#20bfd2;--discover-green:#24d680}body.mg-discovery-page .mg-cart-trigger,body.mg-discovery-page .mg-header-cart,body.mg-discovery-page [data-mg-cart-trigger],body.mg-discovery-page [data-header-cart],body.mg-discovery-page .mg-cart-control{display:none!important}.mg-discovery-shell{min-height:100vh;background:radial-gradient(circle at 88% 8%,rgba(237,233,254,.54),transparent 30%),radial-gradient(circle at 12% 18%,rgba(220,252,231,.34),transparent 26%),linear-gradient(180deg,#fff,#f8fafc 72%,#fff);color:var(--discover-dark)}.mg-discover-stock-ticker{position:sticky;top:0;z-index:900;width:100%;border-top:1px solid rgba(15,23,42,.08);border-bottom:1px solid rgba(15,23,42,.10);background:rgba(255,255,255,.96);box-shadow:0 10px 26px rgba(15,23,42,.08);backdrop-filter:blur(14px);overflow:hidden}.mg-discover-stock-ticker__inner{display:flex;align-items:center;min-height:42px;white-space:nowrap}.mg-discover-stock-label{position:relative;z-index:2;flex:0 0 auto;display:inline-flex;align-items:center;gap:10px;min-height:42px;padding:0 28px;background:linear-gradient(90deg,rgba(255,255,255,.98) 0%,rgba(255,255,255,.98) 82%,rgba(255,255,255,0) 100%);color:#050505;font-size:13px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}.mg-discover-stock-label:before{content:"";width:9px;height:9px;border-radius:999px;background:var(--discover-green);box-shadow:0 0 18px rgba(36,214,128,.55)}.mg-discover-stock-track{flex:1 1 auto;min-width:0;overflow:hidden}.mg-discover-stock-marquee{display:flex;width:max-content;animation:mgDiscoverTickerScroll 34s linear infinite}.mg-discover-stock-row{display:flex;align-items:center;min-width:max-content}.mg-discover-stock-item{display:inline-flex;align-items:center;gap:8px;min-height:42px;padding:0 26px;border-left:1px solid rgba(15,23,42,.08);color:#171717;text-decoration:none;font-size:13px;font-weight:850}.mg-discover-stock-item:hover{background:rgba(124,58,237,.06)}.mg-discover-stock-item strong{color:#050505;font-size:13px;font-weight:950;letter-spacing:.12em}.mg-discover-stock-item span{color:#64748b;font-weight:780}.mg-discover-stock-item b{color:#111827;font-weight:950}.mg-discover-stock-item em{color:var(--discover-green);font-style:normal;font-weight:950}@keyframes mgDiscoverTickerScroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}.mg-discovery-content{position:relative;overflow:hidden;padding:0 0 96px}.mg-discovery-content:before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.42;background:linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);background-size:72px 72px}.mg-discovery-content>*{position:relative;z-index:1}.mg-discovery-layout.mg-container{width:100%!important;max-width:none!important;margin:0!important;padding:0!important}.mg-discovery-layout{display:grid!important;grid-template-columns:20% 80%!important;gap:0!important;align-items:start!important;width:100%!important}.mg-discovery-sidebar{position:sticky!important;top:42px!important;display:grid;gap:18px;width:100%!important;max-width:100%!important;padding:0!important;overflow:hidden!important;z-index:2!important}.mg-discovery-filter-panel,.mg-discovery-main-panel{border:1px solid var(--discover-border);background:rgba(255,255,255,.92);box-shadow:0 24px 70px rgba(15,23,42,.08);backdrop-filter:blur(14px)}.mg-discovery-filter-panel{width:100%!important;max-width:100%!important;border-radius:0!important;border-left:0!important;border-top:0!important;box-shadow:none!important;background:rgba(255,255,255,.96)!important;overflow:hidden!important}.mg-discovery-filter-panel h1,.mg-discovery-filter-panel h2{margin:0;color:var(--discover-dark);letter-spacing:-.045em}.mg-discovery-filter-panel h1{font-size:clamp(24px,1.8vw,31px)!important;line-height:1.05!important}.mg-discovery-filter-panel p{max-width:100%!important;margin:10px 0 0;color:var(--discover-muted);font-size:13px!important;line-height:1.45!important}.mg-discovery-sidebar-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.mg-discovery-sidebar-title span{color:#64748b;font-size:11px;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.mg-discovery-sidebar-title strong{color:var(--discover-dark);font-size:16px;font-weight:950}.mg-discovery-search{display:grid;grid-template-columns:1fr!important;gap:12px;margin-top:0;width:100%!important}.mg-discovery-search label{display:grid;gap:7px;min-width:0!important;max-width:100%!important;color:#071225;font-size:12px;font-weight:900}.mg-discovery-search input,.mg-discovery-search select{width:100%;min-width:0!important;max-width:100%!important;min-height:44px;border:1px solid var(--discover-border);border-radius:14px;background:#fff;color:#071225;font:inherit;font-size:13px;font-weight:760;padding:0 14px;outline:none}.mg-discovery-search input:focus,.mg-discovery-search select:focus{border-color:rgba(124,58,237,.55);box-shadow:0 0 0 4px rgba(124,58,237,.10)}.mg-discovery-filter-actions{display:grid;grid-template-columns:1fr!important;gap:10px}.mg-discovery-filter-actions .mg-btn{width:100%;justify-content:center}.mg-discovery-chip-list{display:grid;gap:8px;max-height:420px;overflow:auto;padding-right:4px}.mg-discovery-chip{width:100%;min-width:0!important;max-width:100%!important;min-height:39px;display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;color:#0f172a;cursor:pointer;font-size:13px;font-weight:850;padding:0 12px;text-align:left}.mg-discovery-chip span{color:#94a3b8;font-size:11px;font-weight:950;letter-spacing:.08em}.mg-discovery-chip:hover,.mg-discovery-chip.is-active{border-color:rgba(124,58,237,.42);background:linear-gradient(135deg,rgba(124,58,237,.08),rgba(32,191,210,.08))}.mg-discovery-main-panel{width:100%!important;max-width:none!important;min-height:620px;padding:38px 42px 64px!important;border:0!important;border-left:1px solid rgba(219,229,241,.9)!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;backdrop-filter:none!important;overflow:visible!important;z-index:1!important}.mg-discovery-main-header,.mg-discovery-section[aria-labelledby="discovery-results-title"] .mg-discovery-heading{display:none!important}.mg-discovery-status{display:none!important;margin:0!important;padding:0!important;border:0!important;height:0!important;min-height:0!important;overflow:hidden!important}.mg-discovery-card-grid{width:100%!important;display:grid;grid-template-columns:repeat(3,minmax(240px,1fr))!important;gap:26px!important}.mg-discovery-card{overflow:hidden;min-width:0!important;min-height:300px;display:flex;flex-direction:column;border:1px solid var(--discover-border);border-radius:24px;background:#fff;box-shadow:0 20px 54px rgba(15,23,42,.08);transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}.mg-discovery-card:hover{transform:translateY(-3px);border-color:rgba(124,58,237,.28);box-shadow:0 30px 70px rgba(15,23,42,.12)}.mg-discovery-card.is-skeleton{min-height:260px;background:linear-gradient(90deg,rgba(226,232,240,.65),rgba(248,250,252,.92),rgba(226,232,240,.65));background-size:220% 100%;animation:mgDiscoverSkeleton 1.4s linear infinite}@keyframes mgDiscoverSkeleton{from{background-position:0 0}to{background-position:-220% 0}}.mg-discovery-card-top{display:flex;align-items:center;gap:14px;padding:22px 22px 8px}.mg-discovery-avatar,.mg-profile-avatar,[data-profile-avatar]{flex:0 0 auto;width:64px;height:64px;display:grid;place-items:center;overflow:hidden;border:4px solid #fff;border-radius:999px;background:linear-gradient(135deg,#ede9fe,#cffafe);color:#071225;font-weight:950;box-shadow:0 10px 26px rgba(15,23,42,.16)}.mg-discovery-avatar img,.mg-profile-avatar img,[data-profile-avatar] img{width:100%;height:100%;display:block;object-fit:cover}.mg-discovery-card h3{margin:0;color:#071225;font-size:18px;line-height:1.1;letter-spacing:-.035em}.mg-discovery-card h3 a{color:inherit;text-decoration:none}.mg-discovery-type{display:inline-flex;margin-top:6px;color:#7c3aed;font-size:11px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.mg-discovery-headline{min-height:48px;margin:12px 22px 0;color:#334155;font-size:14px;line-height:1.45}.mg-discovery-meta{display:flex;flex-wrap:wrap;gap:8px;margin:16px 22px 0}.mg-discovery-meta span{display:inline-flex;align-items:center;min-height:28px;padding:0 10px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:900}.mg-discovery-counts{display:flex;flex-wrap:wrap;gap:10px;margin:18px 22px 0;color:#64748b;font-size:12px;font-weight:800}.mg-discovery-counts strong{color:#071225}.mg-discovery-market-counts{margin-top:12px;padding-top:12px;border-top:1px solid rgba(226,232,240,.9)}.mg-discovery-market-counts span:first-child{color:#7c3aed}.mg-discovery-open{margin:22px;margin-top:auto;align-self:flex-start}.mg-discovery-message{max-width:580px;padding:34px;border:1px solid var(--discover-border);border-radius:24px;background:#fff;box-shadow:0 20px 54px rgba(15,23,42,.08)}.mg-discovery-message h2{margin:0;color:#071225;font-size:28px;letter-spacing:-.04em}.mg-discovery-message p{margin:12px 0 0;color:#64748b}[data-featured-section],[data-storefront-section],[data-recent-section],[data-discovery-pagination]{display:none!important}.mg-discovery-section[aria-labelledby="discovery-results-title"]{display:block!important}.mg-discovery-tabs{display:flex;width:100%;border-bottom:1px solid var(--discover-border);background:#fff}.mg-discovery-tab{flex:1 1 0;min-height:52px;border:0;border-right:1px solid var(--discover-border);background:#f8fafc;color:#64748b;cursor:pointer;font-size:12px;font-weight:950;letter-spacing:.09em;text-transform:uppercase}.mg-discovery-tab:last-child{border-right:0}.mg-discovery-tab.is-active{background:#fff;color:#071225}.mg-discovery-tab-panel{display:none;padding:22px}.mg-discovery-tab-panel.is-active{display:block}@media(max-width:1180px){.mg-discovery-layout{grid-template-columns:260px minmax(0,1fr)!important}.mg-discovery-card-grid{grid-template-columns:repeat(2,minmax(240px,1fr))!important}}@media(max-width:860px){.mg-discovery-layout{grid-template-columns:1fr!important}.mg-discovery-sidebar{position:relative!important;top:auto!important}.mg-discovery-filter-panel{border-right:0!important}.mg-discovery-main-panel{border-left:0!important;padding:24px 20px 54px!important}.mg-discovery-card-grid{grid-template-columns:1fr!important}}@media(max-width:680px){.mg-discover-stock-label{padding:0 16px}.mg-discover-stock-item{padding:0 18px}.mg-discovery-filter-actions{grid-template-columns:1fr}.mg-discovery-main-panel{padding:20px;border-radius:22px}}
</style>

<main class="mg-discovery-shell" data-profile-discovery>
  <div class="mg-discover-stock-ticker" aria-label="Sample local market ticker">
    <div class="mg-discover-stock-ticker__inner">
      <div class="mg-discover-stock-label">Local market</div>
      <div class="mg-discover-stock-track">
        <div class="mg-discover-stock-marquee">
          <?php for ($loop = 0; $loop < 2; $loop++): ?>
            <div class="mg-discover-stock-row"<?= $loop === 1 ? ' aria-hidden="true"' : '' ?>>
              <?php foreach ($discoverTickerItems as $ticker): ?>
                <a class="mg-discover-stock-item" href="/discover.php"><strong><?= mg_e($ticker[0]) ?></strong><span><?= mg_e($ticker[1]) ?></span><b><?= mg_e($ticker[2]) ?></b><em><?= mg_e($ticker[3]) ?></em></a>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="mg-discovery-content">
    <div class="mg-container mg-discovery-layout">
      <aside class="mg-discovery-sidebar" aria-label="Discover local merchants">
        <div class="mg-discovery-filter-panel is-tabbed" data-discovery-sidebar-tabs>
          <div class="mg-discovery-tabs" role="tablist" aria-label="Discover filters">
            <button class="mg-discovery-tab is-active" type="button" id="discover-tab-search" role="tab" aria-selected="true" aria-controls="discover-panel-search" data-discovery-tab="search">Search</button>
            <button class="mg-discovery-tab" type="button" id="discover-tab-states" role="tab" aria-selected="false" aria-controls="discover-panel-states" data-discovery-tab="states">States</button>
          </div>
          <div class="mg-discovery-tab-panel is-active" id="discover-panel-search" role="tabpanel" aria-labelledby="discover-tab-search" data-discovery-panel="search">
            <form class="mg-discovery-search" data-discovery-form role="search">
              <input type="hidden" name="type" value="merchant">
              <label class="mg-discovery-query">Search merchants<input type="search" name="q" maxlength="100" autocomplete="off" placeholder="Name, offer, headline, or location"></label>
              <label>Location<input type="search" name="location" maxlength="100" placeholder="City, state, or region" data-discover-location></label>
              <label>Category<input type="search" name="category" maxlength="60" placeholder="Restaurant, event, fitness..." data-discover-category-input></label>
              <div class="mg-discovery-filter-actions"><button class="mg-btn mg-btn-primary" type="submit">Search</button><button class="mg-btn mg-btn-ghost" type="reset" data-discovery-reset>Reset</button></div>
            </form>
            <div class="mg-discovery-sidebar-title" style="margin-top:20px"><div><span>Browse by</span><strong>Category</strong></div></div>
            <div class="mg-discovery-chip-list" data-discover-category-list>
              <?php foreach ($discoverCategories as $value => $label): ?>
                <button class="mg-discovery-chip" type="button" data-discover-category="<?= mg_e($value) ?>"><?= mg_e($label[0]) ?><span><?= mg_e($label[1]) ?></span></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mg-discovery-tab-panel" id="discover-panel-states" role="tabpanel" aria-labelledby="discover-tab-states" data-discovery-panel="states">
            <div class="mg-discovery-sidebar-title"><div><span>Browse by</span><strong>State</strong></div></div>
            <div class="mg-discovery-chip-list" data-discover-state-list>
              <?php foreach ($discoverStates as $abbr => $name): ?>
                <button class="mg-discovery-chip" type="button" data-discover-state="<?= mg_e($abbr) ?>"><?= mg_e($name) ?><span><?= mg_e($abbr === '' ? 'ALL' : $abbr) ?></span></button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </aside>

      <section class="mg-discovery-main-panel" aria-labelledby="discovery-results-title">
        <section class="mg-discovery-state" data-discovery-loading aria-busy="true"><div class="mg-discovery-card-grid"><?php for ($i = 0; $i < 6; $i++): ?><article class="mg-discovery-card is-skeleton" aria-hidden="true"></article><?php endfor; ?></div></section>
        <section class="mg-discovery-state mg-hidden" data-discovery-error role="alert"><div class="mg-panel mg-discovery-message"><h2>Discovery is temporarily unavailable.</h2><p data-discovery-error-message>We could not load merchant discovery.</p><button class="mg-btn mg-btn-primary" type="button" data-discovery-retry>Try again</button></div></section>
        <section class="mg-discovery-state mg-hidden" data-discovery-empty><div class="mg-panel mg-discovery-message"><h2>No merchant profiles are available yet.</h2><p>Public merchant profiles will appear here when they are active and published.</p></div></section>
        <section class="mg-discovery-state mg-hidden" data-discovery-no-results><div class="mg-panel mg-discovery-message"><h2>No matching merchants.</h2><p>Try a broader state, location, category, or merchant search.</p></div></section>
        <div class="mg-hidden" data-discovery-content>
          <section class="mg-discovery-section mg-hidden" data-featured-section><div class="mg-discovery-card-grid" data-featured-grid></div></section>
          <section class="mg-discovery-section mg-hidden" data-storefront-section><div class="mg-discovery-card-grid" data-storefront-grid></div></section>
          <section class="mg-discovery-section mg-hidden" data-recent-section><div class="mg-discovery-card-grid" data-recent-grid></div></section>
          <section class="mg-discovery-section" aria-labelledby="discovery-results-title"><div class="mg-discovery-heading"><div><span class="mg-kicker">Merchant results</span><h2>Profiles</h2></div><p data-results-summary></p></div><div class="mg-discovery-card-grid" data-results-grid></div><div class="mg-discovery-more mg-hidden" data-discovery-pagination><button class="mg-btn mg-btn-soft" type="button" data-discovery-more>Load more merchants</button></div></section>
        </div>
      </section>
    </div>
  </div>
  <div class="mg-discovery-status" data-discovery-status role="status" aria-live="polite" hidden></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const root = document.querySelector('[data-profile-discovery]');
  if (!root) return;
  const form = root.querySelector('[data-discovery-form]');
  const locationInput = root.querySelector('[data-discover-location]');
  const categoryInput = root.querySelector('[data-discover-category-input]');
  const stateButtons = Array.from(root.querySelectorAll('[data-discover-state]'));
  const categoryButtons = Array.from(root.querySelectorAll('[data-discover-category]'));
  const tabRoot = root.querySelector('[data-discovery-sidebar-tabs]');
  const tabButtons = tabRoot ? Array.from(tabRoot.querySelectorAll('[data-discovery-tab]')) : [];
  const tabPanels = tabRoot ? Array.from(tabRoot.querySelectorAll('[data-discovery-panel]')) : [];
  function activateDiscoverTab(tabName) {
    tabButtons.forEach(function (button) {
      const active = button.getAttribute('data-discovery-tab') === tabName;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    tabPanels.forEach(function (panel) { panel.classList.toggle('is-active', panel.getAttribute('data-discovery-panel') === tabName); });
  }
  tabButtons.forEach(function (button) { button.addEventListener('click', function () { activateDiscoverTab(button.getAttribute('data-discovery-tab') || 'search'); }); });
  function syncActiveButtons() {
    const locationValue = String(locationInput?.value || '').trim().toLowerCase();
    const categoryValue = String(categoryInput?.value || '').trim().toLowerCase();
    stateButtons.forEach(function (button) {
      const value = String(button.getAttribute('data-discover-state') || '').trim().toLowerCase();
      button.classList.toggle('is-active', value === locationValue || (value === '' && locationValue === ''));
    });
    categoryButtons.forEach(function (button) {
      const value = String(button.getAttribute('data-discover-category') || '').trim().toLowerCase();
      button.classList.toggle('is-active', value === categoryValue || (value === '' && categoryValue === ''));
    });
  }
  function submitFilters() {
    syncActiveButtons();
    if (form) form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable:true, bubbles:true }));
  }
  stateButtons.forEach(function (button) { button.addEventListener('click', function () { if (locationInput) locationInput.value = button.getAttribute('data-discover-state') || ''; submitFilters(); }); });
  categoryButtons.forEach(function (button) { button.addEventListener('click', function () { if (categoryInput) categoryInput.value = button.getAttribute('data-discover-category') || ''; submitFilters(); }); });
  form?.addEventListener('reset', function () { window.setTimeout(syncActiveButtons, 0); });
  locationInput?.addEventListener('input', syncActiveButtons);
  categoryInput?.addEventListener('input', syncActiveButtons);
  syncActiveButtons();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
