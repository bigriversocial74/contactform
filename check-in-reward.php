<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-landing-foundation.php';
require_once __DIR__ . '/includes/campaign-user-details.php';

$page_title = 'Check-In Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-landing-specialized.css',
];
$page_scripts = ['/assets/js/public-campaign.js', '/assets/js/public-check-in-reward.js'];

$bootstrap = mg_campaign_landing_bootstrap('check_in_reward', $page_title);
$campaign = is_array($bootstrap['campaign'] ?? null) ? $bootstrap['campaign'] : null;
$previewMode = (bool)($bootstrap['preview'] ?? false);
$page_title = (string)($bootstrap['page_title'] ?? $page_title);
$page_meta = is_array($bootstrap['page_meta'] ?? null) ? $bootstrap['page_meta'] : [];
$state = mg_campaign_landing_state($campaign, $previewMode);

function mg_check_in_render_join(array $context): void
{
    $campaign = $context['campaign'];
    $profile = $context['profile'];
    $state = $context['state'];
    $preview = (bool)$context['preview'];
    $locationRequired = (bool)$context['location_required'];
    $prefill = $context['prefill'];
    $radius = (int)$context['radius'];
    ?>
    <?php mg_campaign_landing_render_profile($profile); ?>
    <?php if (!empty($state['closed'])): ?>
      <div class="mg-public-campaign-result is-visible" data-campaign-closed-state><strong><?= mg_e((string)$state['message']) ?></strong></div>
    <?php else: ?>
      <form class="mg-rl-form mg-specialized-form" data-campaign-form data-check-in-reward-form data-location-required="<?= $locationRequired ? '1' : '0' ?>" data-submit-endpoint="/api/public/campaigns/check-in.php" data-campaign-type="check_in_reward"<?= $preview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
        <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
        <input type="hidden" name="campaign_type" value="check_in_reward">
        <input type="hidden" name="entry_latitude">
        <input type="hidden" name="entry_longitude">
        <input type="hidden" name="entry_accuracy_meters">
        <input type="hidden" name="entry_location_permission" value="pending">
        <h3>Check in at this location</h3>
        <p><?= $locationRequired ? 'Capture your location, then submit within the merchant check-in radius.' : 'Location capture is optional for this campaign.' ?></p>
        <?php mg_campaign_render_user_details($prefill); ?>
        <div class="mg-specialized-action-row">
          <button class="mg-rl-btn mg-rl-btn-soft" type="button" data-check-in-geolocate><?= $locationRequired ? 'Use my location' : 'Add location (optional)' ?></button>
          <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $preview ? 'button' : 'submit' ?>"<?= $preview ? ' disabled aria-disabled="true"' : '' ?>><?= mg_e($preview ? 'Preview only - activate to publish' : 'Check in and claim reward') ?></button>
        </div>
        <div class="mg-public-campaign-status" data-campaign-status data-check-in-status><?= $preview ? 'Preview mode: customer submissions are disabled.' : 'Configured radius: ' . mg_e((string)$radius) . ' meters.' ?></div>
        <p class="mg-public-campaign-privacy">Location is used only for this campaign check-in and nearest-location verification.</p>
      </form>
      <div class="mg-public-campaign-result" data-campaign-result></div>
    <?php endif;
}

require __DIR__ . '/includes/header.php';

if (!$campaign || empty($state['available'])) {
    mg_campaign_landing_render_unavailable(
        'Check-In Reward',
        'Check in near a merchant location and unlock a reward powered by Microgifter.',
        (string)($state['message'] ?? '')
    );
    require __DIR__ . '/includes/footer.php';
    return;
}

$rules = mg_campaign_landing_rules($campaign);
$profile = mg_campaign_landing_profile($campaign);
$prefill = mg_campaign_landing_prefill();
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: (trim((string)($campaign['title'] ?? '')) ?: 'Check in and get a reward');
$description = trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Use your browser location to verify you are near a registered merchant location.');
$radius = max(25, min(5000, (int)($rules['radius_meters'] ?? 150)));
$locationRequired = !array_key_exists('location_required', $rules) || !empty($rules['location_required']);
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardDescription = trim((string)($campaign['reward_template_description'] ?? '')) ?: 'Complete the location check-in to unlock this merchant reward.';
$rewardValue = mg_campaign_landing_value($campaign);
$primaryImage = mg_campaign_landing_primary_image($campaign);
$backgroundImage = mg_campaign_landing_background_image($campaign);
$joinContext = [
    'campaign' => $campaign,
    'profile' => $profile,
    'state' => $state,
    'preview' => $previewMode,
    'location_required' => $locationRequired,
    'prefill' => $prefill,
    'radius' => $radius,
];
?>
<section class="mg-rl-page mg-rl-campaign-foundation mg-rl-specialized mg-rl-specialized-checkin<?= $previewMode ? ' is-merchant-preview' : '' ?>" data-public-campaign-page data-campaign-state="<?= mg_e((string)$state['code']) ?>">
  <div class="mg-rl-bg"<?= $backgroundImage ? ' style="background-image:url(' . mg_e($backgroundImage) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <?php if ($previewMode): ?><article class="mg-rl-card mg-specialized-preview"><span class="mg-rl-eyebrow">Merchant preview</span><h3><?= mg_e((string)$state['status_label']) ?></h3><p>Customer check-ins are disabled until this campaign is active.</p></article><?php endif; ?>
      <header class="mg-rl-hero">
        <h1><?= mg_e($headline) ?></h1>
        <p><?= mg_e($description) ?></p>
        <div class="mg-public-campaign-trust-row"><span>Browser location match</span><span><?= mg_e((string)$radius) ?>m campaign radius</span><span>Reward sent to Inbox</span></div>
      </header>
      <section class="mg-rl-player mg-specialized-canvas" aria-label="Check-in reward details">
        <div class="mg-specialized-layout">
          <div class="mg-specialized-media"><?php if ($primaryImage): ?><img src="<?= mg_e($primaryImage) ?>" alt="<?= mg_e($rewardTitle) ?> campaign image"><?php else: ?><div class="mg-specialized-placeholder"><span>Location</span><strong>Check-In Reward</strong></div><?php endif; ?></div>
          <div class="mg-specialized-copy"><span class="mg-rl-eyebrow">Location verification</span><h2><?= mg_e((string)$radius) ?> meter campaign radius</h2><p><?= mg_e($rewardDescription) ?></p><div class="mg-specialized-metrics"><span><strong><?= $locationRequired ? 'Required' : 'Optional' ?></strong>Browser location</span><span><strong>Nearest match</strong>Merchant location</span><span><strong><?= mg_e($rewardValue) ?></strong><?= mg_e($rewardTitle) ?></span></div></div>
        </div>
      </section>
      <aside class="mg-rl-join mg-rl-join-mobile"><?php mg_check_in_render_join($joinContext); ?></aside>
      <?php mg_campaign_landing_render_bottom_cards([
          'campaign' => $campaign,
          'state' => $state,
          'reward_title' => $rewardTitle,
          'reward_value' => $rewardValue,
          'outcome_title' => 'Verified check-in reward',
          'outcome_copy' => $locationRequired ? 'A browser location match within the configured campaign radius unlocks the eligible reward.' : 'This campaign allows participation without a required location match.',
      ]); ?>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop"><?php mg_check_in_render_join($joinContext); ?></aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>