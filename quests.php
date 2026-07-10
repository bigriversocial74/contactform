<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Explore Loyalty Quests | Microgifter';
$page_section = 'quests';
$header_mode = 'public';
$page_styles = ['/assets/css/loyalty-quest-marketplace.css','/assets/css/loyalty-quest-map.css'];
$page_scripts = ['/assets/js/loyalty-quest-marketplace.js'];
$page_meta = [
    'description' => 'Discover verified local Loyalty Quests from Microgifter merchants and earn rewards for visits, purchases, events, referrals, and community actions.',
    'robots' => 'index, follow',
    'og_title' => 'Explore Microgifter Loyalty Quests',
    'og_description' => 'Find local quests, complete verified actions, and earn merchant rewards through Microgifter.',
];
require __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Microgifter Loyalty Quest Marketplace',
    'description' => $page_meta['description'],
    'mainEntity' => ['@type' => 'ItemList', 'name' => 'Available Loyalty Quests'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<main class="mg-quest-marketplace" data-quest-marketplace>
  <section class="mg-quest-marketplace-hero">
    <div class="mg-quest-marketplace-copy">
      <span class="mg-quest-kicker">Microgifter Loyalty Quests</span>
      <h1>Explore local. Complete quests. Earn real rewards.</h1>
      <p>Discover challenges from restaurants, shops, entertainment venues, community partners, and local businesses using one Microgifter account.</p>
      <form class="mg-quest-search" data-quest-search-form role="search">
        <label><span>Search quests or merchants</span><input name="q" type="search" placeholder="Coffee, downtown, live music…" autocomplete="off"></label>
        <label><span>Location</span><input name="location" placeholder="City or ZIP code" autocomplete="postal-code"></label>
        <button type="submit">Find quests</button>
        <button type="button" class="is-secondary" data-quest-near-me>Use my location</button>
      </form>
    </div>
    <div class="mg-quest-marketplace-summary" aria-label="Marketplace summary">
      <strong data-quest-total>—</strong><span>available quests</span>
      <small>Rewards are issued and managed through Microgifter.</small>
    </div>
  </section>

  <section class="mg-quest-toolbar" aria-label="Quest filters">
    <label>Quest action<select data-quest-action-filter><option value="all">All actions</option><option value="location_visit">Visit a location</option><option value="qr_scan">Scan a QR code</option><option value="purchase">Make a purchase</option><option value="product_purchase">Buy a selected product</option><option value="event_attendance">Attend an event</option><option value="referral">Refer a friend</option><option value="social_action">Social action</option><option value="milestone">Complete a milestone</option><option value="multi_location">Visit multiple locations</option><option value="sequence">Complete a sequence</option></select></label>
    <label>Reward status<select data-quest-reward-filter><option value="all">All rewards</option><option value="available">Reward available</option><option value="limited">Limited availability</option></select></label>
    <label>Sort<select data-quest-sort><option value="featured">Featured</option><option value="nearby">Nearest</option><option value="ending">Ending soon</option><option value="newest">Newest</option></select></label>
    <button type="button" data-quest-clear>Clear filters</button>
  </section>

  <section class="mg-quest-results-shell">
    <div class="mg-quest-results-head">
      <div><span class="mg-quest-kicker">Quest marketplace</span><h2 data-quest-results-title>Available near you</h2></div>
      <div class="mg-quest-results-controls"><p data-quest-status role="status" aria-live="polite">Loading quests…</p><div class="mg-quest-view-switch" role="group" aria-label="Quest view"><button class="is-active" type="button" data-quest-view="list" aria-pressed="true">List</button><button type="button" data-quest-view="map" aria-pressed="false">Map</button></div></div>
    </div>
    <div class="mg-quest-grid" data-quest-results></div>
    <div class="mg-quest-map" data-quest-map hidden aria-label="Quest location map"><div class="mg-quest-map-surface" data-quest-map-surface></div><p data-quest-map-note>Use your location or search a city to compare quest locations.</p></div>
    <button class="mg-quest-load-more" type="button" data-quest-load-more hidden>Load more quests</button>
  </section>

  <section class="mg-quest-merchant-cta">
    <div><span class="mg-quest-kicker">For local businesses</span><h2>Create a Loyalty Quest through Microgifter.</h2><p>Turn visits, purchases, events, referrals, and community actions into measurable customer engagement.</p></div>
    <a href="/signup.php?account_type=merchant">Create merchant account</a>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';
