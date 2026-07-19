<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Local Gift Bundles | Microgifter';
$page_section='explore';
$page_styles=['/assets/css/bundle-storefront-v4.css'];
$page_scripts=['/assets/js/bundle-storefront-v4.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-bundle-storefront" data-bundle-catalog>
  <section class="mg-bundle-hero">
    <div>
      <span class="mg-bundle-eyebrow">The Future of Gifting Starts Local</span>
      <h1>Give more than one local experience.</h1>
      <p>Discover curated product and experience bundles from independent merchants, then send the entire collection as one thoughtful gift.</p>
    </div>
    <form class="mg-bundle-search" data-bundle-search>
      <label for="bundle-search">Search bundles</label>
      <div><input id="bundle-search" name="q" type="search" placeholder="Food, wellness, music, local experiences…"><button type="submit">Search</button></div>
    </form>
  </section>
  <section class="mg-bundle-section">
    <div class="mg-bundle-section-head"><div><span>Curated locally</span><h2>Featured gift bundles</h2></div><p>One purchase. Multiple merchants. A complete local gifting experience.</p></div>
    <div class="mg-bundle-grid" data-bundle-results aria-live="polite"><div class="mg-bundle-empty">Loading bundles…</div></div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
