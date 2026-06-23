<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Campaign | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/merchant-workspace.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-merchant-main">
  <section class="mg-app-panel" data-public-campaign>
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Microgifter campaign</span><h1 data-campaign-title>Loading campaign…</h1><p data-campaign-description>Checking reward availability.</p></div></div>
    <div class="mg-app-panel-body">
      <div class="mg-empty-state" data-campaign-loading><p>Loading campaign details…</p></div>
      <form class="mg-merchant-form" data-campaign-form hidden>
        <input type="hidden" name="campaign_id" data-campaign-id value="">
        <input type="hidden" name="qr_token" data-campaign-qr-token value="">
        <label>Name<input name="name" placeholder="Your name" maxlength="180"></label>
        <label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255"></label>
        <label>Phone<input name="phone" placeholder="Optional" maxlength="60"></label>
        <div class="mg-form-status" data-campaign-status></div>
        <button class="mg-btn mg-btn-primary" type="submit">Get reward</button>
      </form>
      <div class="mg-empty-state" data-campaign-result hidden></div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
