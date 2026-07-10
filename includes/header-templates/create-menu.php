<?php
declare(strict_types=1);

if (!empty($GLOBALS['mg_create_menu_rendered'])) {
    return;
}
$GLOBALS['mg_create_menu_rendered'] = true;

$can_create_microgift = (bool) ($can_create_microgift ?? false);
$can_create_campaigns = (bool) ($can_create_campaigns ?? false);
$can_create_rewards = (bool) ($can_create_rewards ?? false);
$can_manage_storefront = (bool) ($can_manage_storefront ?? false);
$can_manage_locations = (bool) ($can_manage_locations ?? false);
$can_create_post = (bool) ($can_create_post ?? mg_is_authenticated());
?>
<div class="mg-create-menu" id="mg-create-menu" data-create-menu hidden aria-hidden="true">
  <button class="mg-create-menu-backdrop" type="button" data-create-menu-close aria-label="Close create menu"></button>
  <section class="mg-create-menu-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-create-menu-title" aria-describedby="mg-create-menu-description" tabindex="-1">
    <header class="mg-create-menu-head">
      <div class="mg-create-menu-heading">
        <span class="mg-create-menu-eyebrow"><b aria-hidden="true">+</b> Create</span>
        <h2 id="mg-create-menu-title">Create something new</h2>
        <p id="mg-create-menu-description">Choose a workspace and continue with the full editor.</p>
      </div>
      <button class="mg-create-menu-close" type="button" data-create-menu-close aria-label="Close create menu">×</button>
    </header>
    <div class="mg-create-menu-grid" role="list">
      <?php if ($can_create_microgift): ?>
        <a href="/build.php" data-create-menu-option="microgift" role="listitem">
          <span class="mg-create-menu-icon is-product" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 7.5h16v12H4z"/><path d="M8 7.5V5.8A2.8 2.8 0 0 1 10.8 3h2.4A2.8 2.8 0 0 1 16 5.8v1.7M4 11h16M9.5 11v2h5v-2"/></svg></span>
          <span class="mg-create-menu-copy"><strong>Product</strong><small>Create a sellable product, prepaid Microgift, or local offer.</small></span>
          <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
      <?php if ($can_create_campaigns): ?>
        <a href="/merchant-campaigns.php" data-create-menu-option="campaign" role="listitem">
          <span class="mg-create-menu-icon is-campaign" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 13.5V9.8l12-4.3v12.3z"/><path d="M8 13.2 9.4 20H6.2L5 13.5M18 8.4l2-1.2M18 15l2 1.2"/></svg></span>
          <span class="mg-create-menu-copy"><strong>Campaign</strong><small>Create a form, contest, QR drop, or reward campaign.</small></span>
          <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
      <?php if ($can_create_rewards): ?>
        <a href="/merchant-reward-templates.php" data-create-menu-option="agent_offer" role="listitem">
          <span class="mg-create-menu-icon is-reward" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 9h14v11H5zM12 9v11M5 13h14"/><path d="M12 9H8.8A2.8 2.8 0 1 1 12 5.5zm0 0h3.2A2.8 2.8 0 1 0 12 5.5z"/></svg></span>
          <span class="mg-create-menu-copy"><strong>Reward</strong><small>Create a reusable reward customers can earn, claim, or redeem.</small></span>
          <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
      <?php if ($can_create_post): ?>
        <a href="/feed.php" data-create-menu-option="post" aria-controls="mg-post-composer-modal" role="listitem">
          <span class="mg-create-menu-icon is-post" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 4h16v13H8l-4 3z"/><path d="M8 8h8M8 12h5"/></svg></span>
          <span class="mg-create-menu-copy"><strong>Post</strong><small>Publish an update, image, video, or link to your public feed.</small></span>
          <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
      <?php if ($can_manage_storefront): ?>
        <a href="/merchant-storefront.php" data-create-menu-option="storefront" role="listitem">
          <span class="mg-create-menu-icon is-storefront" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 9h16l-1.4-5H5.4z"/><path d="M5 9v11h14V9M9 20v-6h6v6"/><path d="M4 9a3 3 0 0 0 5 2.2A3 3 0 0 0 12 12a3 3 0 0 0 3-0.8A3 3 0 0 0 20 9"/></svg></span>
          <span class="mg-create-menu-copy"><strong>Storefront</strong><small>Configure your public merchant storefront and shopping experience.</small></span>
          <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
      <?php if ($can_manage_locations): ?>
        <a href="/merchant-locations.php" data-create-menu-option="location" role="listitem">
          <span class="mg-create-menu-icon is-location" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 21s6-5.4 6-11A6 6 0 0 0 6 10c0 5.6 6 11 6 11z"/><circle cx="12" cy="10" r="2.2"/></svg></span>
          <span class="mg-create-menu-copy"><strong>Location</strong><small>Add a merchant claim, pickup, or redemption location.</small></span>
          <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
      <?php if (!$can_create_microgift && !$can_create_campaigns && !$can_create_rewards && !$can_manage_storefront && !$can_manage_locations && !$can_create_post): ?>
        <a href="/pricing.php" data-create-menu-option="upgrade" role="listitem">
          <span class="mg-create-menu-icon is-upgrade" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 19 19 5M10 5h9v9"/></svg></span>
          <span class="mg-create-menu-copy"><strong>Upgrade</strong><small>Choose a package to unlock merchant creation tools.</small></span>
          <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php require_once dirname(__DIR__) . '/header-components/post-composer-modal.php'; ?>
