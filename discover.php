<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Explore local merchants | Microgifter';
$page_section = 'discover';
$header_mode = 'public';

$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/profile-discovery.css',
    '/assets/css/profile-discovery-content-cards.css?v=1.0.0',
];

$page_scripts = [
    '/assets/js/profile-discovery.js?v=2.0.0',
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
        'ticker' => true,
        'links' => [
            ['label' => 'Feed', 'href' => '/feed.php'],
            ['label' => 'Explore', 'href' => '/discover.php'],
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
    '' => 'All states',
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
    'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
    'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
    'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
    'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
    'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
    'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
    'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
    'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'Washington DC',
];

$discoverStateCounts = array_fill_keys(array_keys($discoverStates), 0);
$discoverStateLookup = [];
foreach ($discoverStates as $abbr => $name) {
    if ($abbr === '') continue;
    $normalizedName = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? ''));
    $discoverStateLookup[strtolower($abbr)] = $abbr;
    $discoverStateLookup[$normalizedName] = $abbr;
}
$discoverStateLookup['district of columbia'] = 'DC';
$discoverStateLookup['washington d c'] = 'DC';
$discoverStateLookup['washington dc'] = 'DC';

if (!function_exists('mg_discover_state_key')) {
    function mg_discover_state_key(string $region, array $states, array $lookup): string
    {
        $region = trim($region);
        if ($region === '') return '';
        $abbr = strtoupper(preg_replace('/[^a-z]/i', '', $region) ?? '');
        if (strlen($abbr) === 2 && isset($states[$abbr])) return $abbr;
        $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $region) ?? ''));
        return (string)($lookup[$normalized] ?? '');
    }
}

try {
    $stateMerchantIndex = [];
    $allMerchantIndex = [];
    $stmt = mg_db()->query("SELECT DISTINCT ml.workspace_id,ml.region
        FROM merchant_locations ml
        INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id
        WHERE ml.country_code='US'
          AND ml.status='active'
          AND mw.status NOT IN ('suspended','archived')
          AND ml.region IS NOT NULL
          AND TRIM(ml.region)<>''");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $workspaceId = (string)($row['workspace_id'] ?? '');
        $stateKey = mg_discover_state_key((string)($row['region'] ?? ''), $discoverStates, $discoverStateLookup);
        if ($workspaceId === '' || $stateKey === '') continue;
        $stateMerchantIndex[$stateKey][$workspaceId] = true;
        $allMerchantIndex[$workspaceId] = true;
    }

    foreach ($stateMerchantIndex as $stateKey => $merchantIds) {
        if (array_key_exists($stateKey, $discoverStateCounts)) {
            $discoverStateCounts[$stateKey] = count($merchantIds);
        }
    }
    $discoverStateCounts[''] = count($allMerchantIndex);
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'profile.discovery.state_counts_failed', 'Unable to load discovery state merchant counts.', [
            'exception_class' => $error::class,
        ]);
    }
}

require __DIR__ . '/includes/header.php';
?>

<main class="mg-discovery-shell" data-profile-discovery>
  <div class="mg-discovery-content">
    <div class="mg-container mg-discovery-layout">
      <aside class="mg-discovery-sidebar" aria-label="Explore merchant filters">
        <div class="mg-discovery-filter-panel is-tabbed" data-discovery-sidebar-tabs>
          <div class="mg-discovery-tabs" role="tablist" aria-label="Explore filters">
            <button class="mg-discovery-tab is-active" type="button" id="discover-tab-search" role="tab" aria-selected="true" aria-controls="discover-panel-search" data-discovery-tab="search">Search</button>
            <button class="mg-discovery-tab" type="button" id="discover-tab-states" role="tab" aria-selected="false" aria-controls="discover-panel-states" data-discovery-tab="states">States</button>
          </div>

          <div class="mg-discovery-tab-panel is-active" id="discover-panel-search" role="tabpanel" aria-labelledby="discover-tab-search" data-discovery-panel="search">
            <form class="mg-discovery-search" data-discovery-form role="search">
              <input type="hidden" name="type" value="merchant">
              <label class="mg-discovery-query">Search merchants
                <input type="search" name="q" maxlength="100" autocomplete="off" placeholder="Business, profile, offer, or location">
              </label>
              <label>Location
                <input type="search" name="location" maxlength="100" placeholder="City, state, or region" data-discover-location>
              </label>
              <label>Category
                <input type="search" name="category" maxlength="60" placeholder="Restaurant, event, fitness..." data-discover-category-input>
              </label>
              <div class="mg-discovery-filter-actions">
                <button class="mg-btn mg-btn-primary" type="submit">Search</button>
                <button class="mg-btn mg-btn-ghost" type="reset" data-discovery-reset>Reset</button>
              </div>
            </form>

            <div class="mg-discovery-sidebar-title"><div><span>Browse by</span><strong>Category</strong></div></div>
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
                <?php
                  $merchantCount = (int)($discoverStateCounts[$abbr] ?? 0);
                  $merchantCountLabel = number_format($merchantCount) . ' ' . ($merchantCount === 1 ? 'merchant' : 'merchants');
                ?>
                <button class="mg-discovery-chip" type="button" data-discover-state="<?= mg_e($abbr) ?>" data-discover-merchant-count="<?= mg_e((string)$merchantCount) ?>" title="<?= mg_e($merchantCountLabel) ?>" aria-label="<?= mg_e($name . ', ' . $merchantCountLabel) ?>"><?= mg_e($name) ?><span class="mg-discovery-state-count" aria-hidden="true"><?= mg_e(number_format($merchantCount)) ?></span></button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </aside>

      <section class="mg-discovery-main-panel" aria-labelledby="discovery-results-title">
        <header class="mg-explore-header">
          <div class="mg-explore-header-copy">
            <span class="mg-explore-eyebrow">Explore Microgifter</span>
            <h1 id="discovery-results-title">Discover local businesses worth following.</h1>
            <p>Browse merchant profiles, products, active campaigns, and customer reviews without the market-score dashboard.</p>
          </div>
        </header>

        <div class="mg-discovery-sortbar" data-discovery-sortbar>
          <strong>Sort merchants</strong>
          <div class="mg-discovery-sort-actions">
            <button class="mg-discovery-sort-button is-active" type="button" data-discovery-sort="trending">Featured</button>
            <button class="mg-discovery-sort-button" type="button" data-discovery-sort="newest">Newest</button>
            <button class="mg-discovery-sort-button" type="button" data-discovery-sort="active">Most active</button>
          </div>
        </div>

        <section class="mg-discovery-state" data-discovery-loading aria-busy="true">
          <div class="mg-discovery-card-grid"><?php for ($i = 0; $i < 4; $i++): ?><article class="mg-discovery-card is-skeleton" aria-hidden="true"></article><?php endfor; ?></div>
        </section>

        <section class="mg-discovery-state mg-hidden" data-discovery-error role="alert">
          <div class="mg-discovery-message"><h2>Explore is temporarily unavailable.</h2><p data-discovery-error-message>We could not load merchant profiles.</p><button class="mg-btn mg-btn-primary" type="button" data-discovery-retry>Try again</button></div>
        </section>

        <section class="mg-discovery-state mg-hidden" data-discovery-empty>
          <div class="mg-discovery-message"><h2>No merchant profiles are available yet.</h2><p>Published merchant profiles will appear here as the local network grows.</p></div>
        </section>

        <section class="mg-discovery-state mg-hidden" data-discovery-no-results>
          <div class="mg-discovery-message"><h2>No matching merchants.</h2><p>Try a broader state, location, category, or merchant search.</p></div>
        </section>

        <div class="mg-hidden" data-discovery-content>
          <section class="mg-discovery-section" aria-labelledby="discovery-results-title">
            <div class="mg-discovery-heading"><p data-results-summary></p></div>
            <div class="mg-discovery-card-grid" data-results-grid></div>
            <div class="mg-discovery-more mg-hidden" data-discovery-pagination><button class="mg-btn mg-btn-soft" type="button" data-discovery-more>Load more merchants</button></div>
          </section>

          <div class="mg-hidden" aria-hidden="true" data-discovery-legacy-sections>
            <section data-featured-section><div data-featured-grid></div></section>
            <section data-storefront-section><div data-storefront-grid></div></section>
            <section data-recent-section><div data-recent-grid></div></section>
          </div>
        </div>
      </section>
    </div>
  </div>
  <div class="mg-discovery-status" data-discovery-status role="status" aria-live="polite" hidden></div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>