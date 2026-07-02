<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Campaign Preview | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = [
    '/assets/css/public-campaign-pages.css',
    '/assets/css/merchant-campaign-preview.css',
];
$page_scripts = ['/assets/js/public-campaign.js'];
$user = mg_current_user();
require __DIR__ . '/includes/header.php';

if (!$user || !mg_has_permission('merchant.campaigns.view')): ?>
  <section class="mg-public-campaign mg-public-campaign-empty">
    <div class="mg-public-campaign-shell">
      <div class="mg-public-campaign-card">
        <span class="mg-public-campaign-eyebrow">Merchant preview</span>
        <h1>Sign in required</h1>
        <p>This preview is only available to the merchant account that owns the campaign draft.</p>
        <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
      </div>
    </div>
  </section>
<?php
else:
    $mgCampaignExpectedType = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : null;
    $mgCampaignExpectedType = $mgCampaignExpectedType !== '' ? $mgCampaignExpectedType : null;
    $mgCampaignPageLabel = 'Merchant campaign preview';
    $mgCampaignPageIntro = 'Preview this campaign draft before publishing it to customers.';
    $mgCampaignPreviewMode = true;
    require __DIR__ . '/includes/public-campaign-page.php';
endif;

require __DIR__ . '/includes/footer.php';
