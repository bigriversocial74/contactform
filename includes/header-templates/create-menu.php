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

$mgCreateTools = [];
if ($can_create_microgift) {
    $mgCreateTools[] = [
        'key' => 'product', 'option' => 'microgift', 'label' => 'Product', 'href' => '/build.php',
        'description' => 'Create a sellable product, prepaid Microgift, or local offer.',
        'class' => 'is-product',
        'icon' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 7.5h16v12H4z"/><path d="M8 7.5V5.8A2.8 2.8 0 0 1 10.8 3h2.4A2.8 2.8 0 0 1 16 5.8v1.7M4 11h16M9.5 11v2h5v-2"/></svg>',
    ];
}
if ($can_create_campaigns) {
    $mgCreateTools[] = [
        'key' => 'campaign', 'option' => 'campaign', 'label' => 'Campaign', 'href' => '/merchant-campaigns.php',
        'description' => 'Create a form, contest, QR drop, or reward campaign.',
        'class' => 'is-campaign',
        'icon' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 13.5V9.8l12-4.3v12.3z"/><path d="M8 13.2 9.4 20H6.2L5 13.5M18 8.4l2-1.2M18 15l2 1.2"/></svg>',
    ];
}
if ($can_create_rewards) {
    $mgCreateTools[] = [
        'key' => 'reward', 'option' => 'agent_offer', 'label' => 'Reward', 'href' => '/merchant-reward-templates.php',
        'description' => 'Create a reusable reward customers can earn, claim, or redeem.',
        'class' => 'is-reward',
        'icon' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M5 9h14v11H5zM12 9v11M5 13h14"/><path d="M12 9H8.8A2.8 2.8 0 1 1 12 5.5zm0 0h3.2A2.8 2.8 0 1 0 12 5.5z"/></svg>',
    ];
}
if ($can_create_post) {
    $mgCreateTools[] = [
        'key' => 'post', 'option' => 'post', 'label' => 'Post', 'href' => '/feed.php',
        'description' => 'Publish an update, image, video, audio, or link to your public feed.',
        'class' => 'is-post',
        'icon' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 4h16v13H8l-4 3z"/><path d="M8 8h8M8 12h5"/></svg>',
    ];
}
if ($can_manage_storefront) {
    $mgCreateTools[] = [
        'key' => 'storefront', 'option' => 'storefront', 'label' => 'Storefront', 'href' => '/merchant-storefront.php',
        'description' => 'Configure your public merchant storefront and shopping experience.',
        'class' => 'is-storefront',
        'icon' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 9h16l-1.4-5H5.4z"/><path d="M5 9v11h14V9M9 20v-6h6v6"/><path d="M4 9a3 3 0 0 0 5 2.2A3 3 0 0 0 12 12a3 3 0 0 0 3-0.8A3 3 0 0 0 20 9"/></svg>',
    ];
}
if ($can_manage_locations) {
    $mgCreateTools[] = [
        'key' => 'location', 'option' => 'location', 'label' => 'Location', 'href' => '/merchant-locations.php',
        'description' => 'Add a merchant claim, pickup, or redemption location.',
        'class' => 'is-location',
        'icon' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 21s6-5.4 6-11A6 6 0 0 0 6 10c0 5.6 6 11 6 11z"/><circle cx="12" cy="10" r="2.2"/></svg>',
    ];
}

$renderCreateTool = static function (array $tool, string $variant = 'card'): void {
    $target = (string) $tool['key'];
    $classes = $variant === 'rail' ? 'mg-create-center-rail-link' : 'mg-create-center-card';
    ?>
    <a class="<?= mg_e($classes) ?>" href="<?= mg_e((string) $tool['href']) ?>"
       data-create-menu-option="<?= mg_e((string) $tool['option']) ?>"
       data-create-tool-key="<?= mg_e((string) $tool['key']) ?>"
       data-create-inline-target="<?= mg_e($target) ?>"
       aria-controls="mg-create-center-<?= mg_e($target) ?>">
      <span class="mg-create-menu-icon <?= mg_e((string) $tool['class']) ?>" aria-hidden="true"><?= $tool['icon'] ?></span>
      <span class="mg-create-menu-copy"><strong><?= mg_e((string) $tool['label']) ?></strong><?php if ($variant !== 'rail'): ?><small><?= mg_e((string) $tool['description']) ?></small><?php endif; ?></span>
      <?php if ($variant !== 'rail'): ?><span class="mg-create-menu-arrow" aria-hidden="true">→</span><?php endif; ?>
    </a>
    <?php
};
?>
<div class="mg-create-menu" id="mg-create-menu" data-create-menu hidden aria-hidden="true">
  <button class="mg-create-menu-backdrop" type="button" data-create-menu-close aria-label="Close create center"></button>
  <section class="mg-create-menu-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-create-menu-title" aria-describedby="mg-create-menu-description" tabindex="-1">
    <header class="mg-create-menu-head">
      <div class="mg-create-menu-heading">
        <span class="mg-create-menu-eyebrow"><b aria-hidden="true">+</b> Create center</span>
        <h2 id="mg-create-menu-title" data-create-center-title>Create something new</h2>
        <p id="mg-create-menu-description" data-create-center-description>Choose a tool, complete the form, and submit without leaving the current page.</p>
      </div>
      <button class="mg-create-menu-close" type="button" data-create-menu-close aria-label="Close create center">×</button>
    </header>

    <div class="mg-create-center-layout">
      <aside class="mg-create-center-rail" aria-label="Create tools">
        <button class="mg-create-center-home is-active" type="button" data-create-center-home>
          <span aria-hidden="true">⌂</span><strong>All tools</strong>
        </button>
        <nav class="mg-create-center-rail-nav">
          <?php foreach ($mgCreateTools as $tool) $renderCreateTool($tool, 'rail'); ?>
        </nav>
      </aside>

      <main class="mg-create-center-content">
        <section class="mg-create-center-view is-active" data-create-center-view="home">
          <div class="mg-create-center-welcome">
            <span class="mg-create-menu-eyebrow">Fast creation</span>
            <h3>Build and submit from one workspace.</h3>
            <p>Each form saves directly to Microgifter and displays a clear success confirmation before you close the modal.</p>
          </div>
          <div class="mg-create-menu-grid" role="list">
            <?php foreach ($mgCreateTools as $tool) $renderCreateTool($tool); ?>
            <?php if ($mgCreateTools === []): ?>
              <a class="mg-create-center-card" href="/pricing.php" data-create-menu-option="upgrade" role="listitem">
                <span class="mg-create-menu-icon is-upgrade" aria-hidden="true">↗</span>
                <span class="mg-create-menu-copy"><strong>Upgrade</strong><small>Choose a package to unlock merchant creation tools.</small></span>
                <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
              </a>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($can_create_microgift): ?>
        <section class="mg-create-center-view" id="mg-create-center-product" data-create-center-view="product" hidden>
          <div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Product</span><h3>Create a product</h3><p>Create a draft or publish a customer-ready voucher directly from this modal.</p></div><a href="/build.php">Open full builder</a></div>
          <div class="mg-create-inline-success" data-create-inline-success="product" hidden><strong>Product created successfully.</strong><p data-create-success-message></p><div><a href="/merchant-products.php" data-create-success-link>View products</a><button type="button" data-create-inline-reset="product">Create another</button></div></div>
          <form class="mg-create-inline-form" data-create-inline-form="product" enctype="multipart/form-data">
            <div class="mg-create-form-grid mg-create-form-grid-2"><label>Product title<input name="title" maxlength="160" required placeholder="Coffee for two"></label><label>Save mode<select name="save_mode"><option value="draft">Save as draft</option><option value="publish">Publish now</option></select></label></div>
            <label>Description<textarea name="description" maxlength="4000" rows="5" placeholder="Describe what the customer receives and how it can be used."></textarea></label>
            <div class="mg-create-form-grid mg-create-form-grid-3"><label>Value<input name="value_amount" inputmode="decimal" required placeholder="25.00"></label><label>Currency<select name="currency"><option value="USD">USD</option><option value="CAD">CAD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select></label><label>Expiration policy<input name="expiration" maxlength="180" placeholder="No expiration until issued"></label></div>
            <div class="mg-create-form-grid mg-create-form-grid-2"><label>Merchant locations<select name="location_ids[]" multiple size="6" data-create-product-locations><option disabled>Loading active locations…</option></select><small>Your primary location is selected automatically.</small></label><label class="mg-create-upload-field">Product image<input name="product_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><span data-create-product-image-name>Choose a JPG, PNG, WebP, or GIF.</span></label></div>
            <label class="mg-create-check"><input name="all_locations" type="checkbox" value="1">Available at all active merchant locations</label>
            <label>Terms<textarea name="terms" maxlength="4000" rows="3" placeholder="Valid at participating merchant locations."></textarea></label>
            <div class="mg-create-inline-status" data-create-inline-status="product" role="status" aria-live="polite"></div>
            <div class="mg-create-inline-actions"><button class="mg-create-submit" type="submit">Create product</button><button type="button" class="mg-create-secondary" data-create-center-home>Cancel</button></div>
          </form>
        </section>
        <?php endif; ?>

        <?php if ($can_create_campaigns): ?>
        <section class="mg-create-center-view" id="mg-create-center-campaign" data-create-center-view="campaign" hidden>
          <div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Campaign</span><h3>Create a campaign</h3><p>Launch a signup, QR drop, contest, referral, birthday, or agent-offer campaign.</p></div><a href="/merchant-campaigns.php#campaign-create">Open campaign studio</a></div>
          <div class="mg-create-inline-success" data-create-inline-success="campaign" hidden><strong>Campaign saved successfully.</strong><p data-create-success-message></p><div><a href="/merchant-campaigns.php" data-create-success-link>View campaigns</a><button type="button" data-create-inline-reset="campaign">Create another</button></div></div>
          <form class="mg-create-inline-form" data-create-inline-form="campaign">
            <div class="mg-create-form-grid mg-create-form-grid-3"><label>Campaign type<select name="campaign_type"><option value="newsletter_signup">Newsletter signup</option><option value="qr_reward_drop">QR reward drop</option><option value="contest_giveaway">Contest / giveaway</option><option value="referral_reward">Referral reward</option><option value="birthday_vip">Birthday / VIP</option><option value="agent_offer">Agent offer</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="active">Active</option></select></label><label>Reward template<select name="reward_template_id" data-create-campaign-rewards><option value="">No reward attached</option></select></label></div>
            <label>Campaign title<input name="title" maxlength="180" required placeholder="Join the list and get a reward"></label>
            <div class="mg-create-form-grid mg-create-form-grid-2"><label>Public headline<input name="form_headline" maxlength="240" placeholder="Join our rewards list"></label><label>Success message<input name="success_message" maxlength="500" placeholder="Campaign response submitted."></label></div>
            <label>Description<textarea name="description" rows="4" placeholder="Explain the campaign and reward."></textarea></label>
            <label>Public form instructions<textarea name="form_description" rows="3" placeholder="Short instructions shown above the campaign form."></textarea></label>
            <div class="mg-create-form-grid mg-create-form-grid-4"><label>Quantity limit<input name="quantity_limit" type="number" min="1" placeholder="Unlimited"></label><label>Per-user limit<input name="per_user_limit" type="number" min="1" value="1"></label><label>Starts at<input name="starts_at" type="datetime-local"></label><label>Ends at<input name="ends_at" type="datetime-local"></label></div>
            <label class="mg-create-check"><input name="agent_discoverable" type="checkbox" value="1">Make this campaign discoverable by Microgifter agents</label>
            <div class="mg-create-inline-status" data-create-inline-status="campaign" role="status" aria-live="polite"></div>
            <div class="mg-create-inline-actions"><button class="mg-create-submit" type="submit">Save campaign</button><button type="button" class="mg-create-secondary" data-create-center-home>Cancel</button></div>
          </form>
        </section>
        <?php endif; ?>

        <?php if ($can_create_rewards): ?>
        <section class="mg-create-center-view" id="mg-create-center-reward" data-create-center-view="reward" hidden>
          <div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Reward</span><h3>Create a reward</h3><p>Build a reusable reward template for campaigns, claims, and direct distribution.</p></div><a href="/merchant-reward-templates.php#reward-create">Open reward library</a></div>
          <div class="mg-create-inline-success" data-create-inline-success="reward" hidden><strong>Reward created successfully.</strong><p data-create-success-message></p><div><a href="/merchant-reward-templates.php" data-create-success-link>View rewards</a><button type="button" data-create-inline-reset="reward">Create another</button></div></div>
          <form class="mg-create-inline-form" data-create-inline-form="reward" enctype="multipart/form-data">
            <div class="mg-create-form-grid mg-create-form-grid-3"><label>Reward type<select name="reward_type"><option value="dollar_credit">Dollar credit</option><option value="free_item">Free item</option><option value="discount">Discount</option><option value="perk_upgrade">Perk / upgrade</option><option value="event_reward">Event reward</option><option value="audio_pack">Audio pack</option><option value="media_pack">Media pack</option><option value="custom">Custom</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="active">Active</option></select></label><label>Expiration<select name="expiration_rule"><option value="none">No expiration</option><option value="after_issue">After issue</option><option value="after_claim">After claim</option><option value="fixed_date">Fixed date</option><option value="event_date">Event date</option></select></label></div>
            <label>Reward title<input name="title" maxlength="180" required placeholder="Coffee credit"></label>
            <label>Description<textarea name="description" rows="4" placeholder="Explain what the customer receives."></textarea></label>
            <div class="mg-create-form-grid mg-create-form-grid-3"><label>Value amount<input name="value_amount" inputmode="decimal" placeholder="10.00"></label><label>Quantity limit<input name="quantity_limit" type="number" min="1" placeholder="Unlimited"></label><label>Per-user limit<input name="per_user_limit" type="number" min="1" value="1"></label></div>
            <div class="mg-create-form-grid mg-create-form-grid-2"><label>Cover image URL<input name="cover_image_url" type="url" placeholder="https://..."></label><label>Media URLs<textarea name="media_item_urls" rows="3" placeholder="One URL per line for audio or media packs."></textarea></label></div>
            <label>Redemption instructions<textarea name="redemption_instructions" rows="4" placeholder="Show this reward to staff and follow the claim instructions."></textarea></label>
            <div class="mg-create-form-grid mg-create-form-grid-2"><label>Agent summary<input name="agent_summary" placeholder="Short recommendation summary"></label><label>Agent categories<input name="agent_categories" placeholder="coffee, lunch, local rewards"></label></div>
            <label class="mg-create-check"><input name="agent_discoverable" type="checkbox" value="1">Agent-discoverable offer</label>
            <input type="hidden" name="template_id" value=""><input type="hidden" name="media_items_json" value="">
            <div class="mg-create-inline-status" data-create-inline-status="reward" role="status" aria-live="polite"></div>
            <div class="mg-create-inline-actions"><button class="mg-create-submit" type="submit">Save reward</button><button type="button" class="mg-create-secondary" data-create-center-home>Cancel</button></div>
          </form>
        </section>
        <?php endif; ?>

        <?php if ($can_create_post): ?>
        <section class="mg-create-center-view mg-create-center-post" id="mg-create-center-post" data-create-center-view="post" hidden>
          <div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Post</span><h3 id="mg-create-center-post-title">Create a post</h3><p>Publish an update with photos, video, audio, links, or connected Microgifter content.</p></div><a href="/feed.php?tab=mine">Open My Posts</a></div>
          <div class="mg-create-inline-success" data-create-post-success hidden><strong>Post saved successfully.</strong><p data-create-post-success-message></p><div><a href="/feed.php?tab=mine">View My Posts</a><button type="button" data-create-post-reset>Create another</button></div></div>
          <?php
          $post_composer_id_suffix = 'create-center';
          $post_composer_hidden = false;
          $post_composer_embedded = true;
          require dirname(__DIR__) . '/social-feed-composer.php';
          unset($post_composer_id_suffix, $post_composer_hidden, $post_composer_embedded);
          ?>
        </section>
        <?php endif; ?>

        <?php if ($can_manage_storefront): ?>
        <section class="mg-create-center-view" id="mg-create-center-storefront" data-create-center-view="storefront" hidden>
          <div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Storefront</span><h3>Configure your storefront</h3><p>Save the public identity, contact details, theme, and featured products from one form.</p></div><a href="/merchant-storefront.php">Open storefront manager</a></div>
          <div class="mg-create-inline-success" data-create-inline-success="storefront" hidden><strong>Storefront saved successfully.</strong><p data-create-success-message></p><div><a href="/merchant-storefront.php" data-create-success-link>View storefront manager</a><button type="button" data-create-inline-reset="storefront">Continue editing</button></div></div>
          <form class="mg-create-inline-form" data-create-inline-form="storefront">
            <div class="mg-create-form-grid mg-create-form-grid-3"><label>Store name<input name="display_name" maxlength="160" required autocomplete="organization"></label><label>Public address<input name="slug" maxlength="110" required placeholder="your-store"></label><label>Save mode<select name="save_mode"><option value="draft">Save draft</option><option value="publish">Save and publish</option></select></label></div>
            <label>Headline<input name="headline" maxlength="240" placeholder="Local gifts, ready when you need them"></label>
            <label>Description<textarea name="description" maxlength="5000" rows="5" placeholder="Tell customers what your storefront offers."></textarea></label>
            <div class="mg-create-form-grid mg-create-form-grid-4"><label>Contact email<input name="contact_email" type="email" maxlength="190"></label><label>Contact phone<input name="contact_phone" maxlength="80"></label><label>Website<input name="website_url" type="url" maxlength="500" placeholder="https://..."></label><label>Accent color<input name="accent" maxlength="7" value="#2563eb" placeholder="#2563eb"></label></div>
            <fieldset class="mg-create-products-fieldset"><legend>Featured products</legend><p>Select the published products that should appear on the storefront.</p><div data-create-storefront-products><div class="mg-create-loading">Loading published products…</div></div></fieldset>
            <div class="mg-create-inline-status" data-create-inline-status="storefront" role="status" aria-live="polite"></div>
            <div class="mg-create-inline-actions"><button class="mg-create-submit" type="submit">Save storefront</button><button type="button" class="mg-create-secondary" data-create-center-home>Cancel</button></div>
          </form>
        </section>
        <?php endif; ?>

        <?php if ($can_manage_locations): ?>
        <section class="mg-create-center-view" id="mg-create-center-location" data-create-center-view="location" hidden>
          <div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Location</span><h3>Add a merchant location</h3><p>Register a claim, pickup, check-in, or redemption site without leaving this page.</p></div><a href="/merchant-locations.php">Open locations manager</a></div>
          <div class="mg-create-inline-success" data-create-inline-success="location" hidden><strong>Location saved successfully.</strong><p data-create-success-message></p><div><a href="/merchant-locations.php" data-create-success-link>View locations</a><button type="button" data-create-inline-reset="location">Add another</button></div></div>
          <form class="mg-create-inline-form" data-create-inline-form="location" autocomplete="off">
            <div class="mg-create-form-grid mg-create-form-grid-2"><label>Location title<input name="name" maxlength="180" required placeholder="Downtown Phoenix"></label><label>Claim code<input name="claim_code" maxlength="64" pattern="[A-Za-z0-9_-]{4,64}" required autocomplete="new-password" placeholder="PHX-001"><small>Stored securely and cannot be displayed again.</small></label></div>
            <label>Address<input name="address_line1" maxlength="190" required placeholder="123 Main St"></label>
            <div class="mg-create-form-grid mg-create-form-grid-4"><label>Address line 2<input name="address_line2" maxlength="190" placeholder="Suite, floor, unit"></label><label>City<input name="city" maxlength="120" placeholder="Phoenix"></label><label>State / region<input name="region" maxlength="120" placeholder="AZ"></label><label>Postal code<input name="postal_code" maxlength="40" placeholder="85004"></label></div>
            <div class="mg-create-form-grid mg-create-form-grid-4"><label>Phone<input name="phone" maxlength="60" inputmode="tel"></label><label>Country<input name="country_code" maxlength="2" value="US"></label><label>Timezone<input name="timezone" maxlength="120" value="America/Phoenix"></label><label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label></div>
            <div class="mg-create-form-grid mg-create-form-grid-3"><label>Latitude<input name="latitude" inputmode="decimal" placeholder="33.4484"></label><label>Longitude<input name="longitude" inputmode="decimal" placeholder="-112.0740"></label><label>Check-in radius meters<input name="check_in_radius_meters" inputmode="numeric" value="150"></label></div>
            <label class="mg-create-check"><input name="is_primary" type="checkbox" value="1">Set as the primary merchant location</label>
            <div class="mg-create-inline-status" data-create-inline-status="location" role="status" aria-live="polite"></div>
            <div class="mg-create-inline-actions"><button class="mg-create-submit" type="submit">Save location</button><button type="button" class="mg-create-secondary" data-create-center-home>Cancel</button></div>
          </form>
        </section>
        <?php endif; ?>
      </main>
    </div>
  </section>
</div>
<?php require_once dirname(__DIR__) . '/header-components/post-composer-modal.php'; ?>