<?php
declare(strict_types=1);

if (!empty($GLOBALS['mg_create_menu_rendered'])) {
    return;
}
$GLOBALS['mg_create_menu_rendered'] = true;
?>
<div class="mg-create-menu" id="mg-create-menu" data-create-menu hidden aria-hidden="true">
  <button class="mg-create-menu-backdrop" type="button" data-create-menu-close aria-label="Close create menu"></button>
  <section class="mg-create-menu-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-create-menu-title" tabindex="-1">
    <header class="mg-create-menu-head">
      <div>
        <span>Create</span>
        <h2 id="mg-create-menu-title">What do you want to add?</h2>
        <p>Choose a workspace to start creating.</p>
      </div>
      <button class="mg-create-menu-close" type="button" data-create-menu-close aria-label="Close create menu">×</button>
    </header>
    <div class="mg-create-menu-grid">
      <a href="/build.php" data-create-menu-option="microgift"><span class="mg-create-menu-icon" aria-hidden="true">M</span><strong>Microgift</strong><small>Create a prepaid local gift or offer.</small></a>
      <a href="/merchant-campaigns.php" data-create-menu-option="campaign"><span class="mg-create-menu-icon" aria-hidden="true">C</span><strong>Campaign</strong><small>Create forms, contests, QR drops, and reward automations.</small></a>
      <a href="/merchant-reward-templates.php" data-create-menu-option="agent_offer"><span class="mg-create-menu-icon" aria-hidden="true">R</span><strong>Add Reward</strong><small>Create a merchant reward template customers can earn, claim, or redeem.</small></a>
      <a href="/feed.php" data-create-menu-option="post" aria-controls="mg-post-composer-modal"><span class="mg-create-menu-icon" aria-hidden="true">P</span><strong>Post</strong><small>Publish an update to your public feed.</small></a>
      <a href="/merchant-storefront.php" data-create-menu-option="storefront"><span class="mg-create-menu-icon" aria-hidden="true">F</span><strong>Storefront</strong><small>Configure your public merchant storefront.</small></a>
      <a href="/merchant-locations.php" data-create-menu-option="location"><span class="mg-create-menu-icon" aria-hidden="true">L</span><strong>Add Location</strong><small>Add a merchant claim and redemption location.</small></a>
    </div>
  </section>
</div>
<?php require_once dirname(__DIR__) . '/header-components/post-composer-modal.php'; ?>
