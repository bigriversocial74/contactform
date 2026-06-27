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
      <?php if ($can_create_microgift): ?><a href="/build.php" data-create-menu-option="microgift"><span class="mg-create-menu-icon" aria-hidden="true">M</span><strong>Microgift</strong><small>Create a prepaid local gift or offer.</small></a><?php endif; ?>
      <?php if ($can_create_campaigns): ?><a href="/merchant-campaigns.php" data-create-menu-option="campaign"><span class="mg-create-menu-icon" aria-hidden="true">C</span><strong>Campaign</strong><small>Create forms, contests, QR drops, and reward automations.</small></a><?php endif; ?>
      <?php if ($can_create_rewards): ?><a href="/merchant-reward-templates.php" data-create-menu-option="agent_offer"><span class="mg-create-menu-icon" aria-hidden="true">R</span><strong>Add Reward</strong><small>Create a merchant reward template customers can earn, claim, or redeem.</small></a><?php endif; ?>
      <?php if ($can_create_post): ?><a href="/feed.php" data-create-menu-option="post" aria-controls="mg-post-composer-modal"><span class="mg-create-menu-icon" aria-hidden="true">P</span><strong>Post</strong><small>Publish an update to your public feed.</small></a><?php endif; ?>
      <?php if ($can_manage_storefront): ?><a href="/merchant-storefront.php" data-create-menu-option="storefront"><span class="mg-create-menu-icon" aria-hidden="true">F</span><strong>Storefront</strong><small>Configure your public merchant storefront.</small></a><?php endif; ?>
      <?php if ($can_manage_locations): ?><a href="/merchant-locations.php" data-create-menu-option="location"><span class="mg-create-menu-icon" aria-hidden="true">L</span><strong>Add Location</strong><small>Add a merchant claim and redemption location.</small></a><?php endif; ?>
      <?php if (!$can_create_microgift && !$can_create_campaigns && !$can_create_rewards && !$can_manage_storefront && !$can_manage_locations && !$can_create_post): ?><a href="/pricing.php" data-create-menu-option="upgrade"><span class="mg-create-menu-icon" aria-hidden="true">U</span><strong>Upgrade</strong><small>Choose a package to unlock merchant creation tools.</small></a><?php endif; ?>
    </div>
  </section>
</div>
<?php require_once dirname(__DIR__) . '/header-components/post-composer-modal.php'; ?>
