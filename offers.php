<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Agent Offers | Microgifter';
$page_section = 'offers';
$header_mode = 'public';
$page_styles = ['/assets/css/merchant-workspace.css'];
$page_scripts = ['/assets/js/stage12-agent-offers.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-merchant-main" data-stage12-agent-offers>
  <section class="mg-merchant-heading">
    <div>
      <span class="mg-eyebrow">Agent discovery</span>
      <h1>Find local rewards</h1>
      <p>Search agent-discoverable offers, review reward details, and add approved offers to your wallet.</p>
    </div>
  </section>
  <section class="mg-app-panel">
    <div class="mg-app-panel-head"><div><h2>Offer search</h2><p>Search by use case, offer type, merchant, or reward copy.</p></div></div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form" data-offer-search-form>
        <div class="mg-grid-2">
          <label>Search<input name="q" placeholder="pizza, lunch, coffee, birthday, event"></label>
          <label>Reward type<select name="reward_type"><option value="">Any type</option><option value="gift_card">Gift card</option><option value="discount">Discount</option><option value="free_item">Free item</option><option value="experience">Experience</option></select></label>
        </div>
        <button class="mg-btn mg-btn-primary" type="submit">Search offers</button>
      </form>
      <div class="mg-form-status" data-offer-status>Loading agent-discoverable offers.</div>
      <div class="mg-product-list" data-offer-list></div>
    </div>
  </section>
  <section class="mg-app-panel">
    <div class="mg-app-panel-head"><div><h2>Offer detail</h2><p>Select an offer to inspect its wallet and agent metadata.</p></div></div>
    <div class="mg-app-panel-body"><div data-offer-detail class="mg-empty-state"><p>Select an offer.</p></div></div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
