<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-media-landing.php';

$page_title = 'Watch Video Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-media-alignment-v1.css',
    '/assets/css/watch-listen-stamp-compact-layout-v1.css?v=1.0.0',
];
$page_scripts = ['/assets/js/public-watch-video-reward.js'];

$bootstrap = mg_campaign_landing_bootstrap('watch_video_reward', $page_title);
$campaign = is_array($bootstrap['campaign'] ?? null) ? $bootstrap['campaign'] : null;
$previewMode = (bool)($bootstrap['preview'] ?? false);
$page_title = (string)($bootstrap['page_title'] ?? $page_title);
$page_meta = is_array($bootstrap['page_meta'] ?? null) ? $bootstrap['page_meta'] : [];
$state = mg_campaign_landing_state($campaign, $previewMode);

require __DIR__ . '/includes/header.php';

if (!$campaign || empty($state['available'])) {
    mg_campaign_landing_render_unavailable(
        'Watch Video Reward',
        'Watch merchant media and unlock milestone rewards powered by Microgifter.',
        (string)($state['message'] ?? '')
    );
    require __DIR__ . '/includes/footer.php';
    return;
}

$rules = mg_campaign_landing_rules($campaign);
$provider = in_array((string)($rules['video_provider'] ?? 'youtube'), ['youtube', 'uploaded'], true)
    ? (string)$rules['video_provider']
    : 'youtube';
$videoId = trim((string)($rules['youtube_video_id'] ?? ''));
$uploadedUrl = mg_campaign_landing_safe_url($rules['uploaded_video_url'] ?? null);
$uploadedAssetId = trim((string)($rules['uploaded_asset_id'] ?? ''));
$milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
$requiredPercent = max(1, min(100, (int)($rules['required_percent'] ?? 80)));
$hasVideo = $provider === 'uploaded' ? $uploadedUrl !== null : $videoId !== '';

if (!$hasVideo) {
    $state = array_merge($state, [
        'closed' => true,
        'code' => 'media_missing',
        'message' => 'This campaign needs a valid YouTube or uploaded video before participation can begin.',
        'active_status' => 'Video required',
    ]);
}

$profile = mg_campaign_landing_profile($campaign);
$prefill = mg_campaign_landing_prefill();
$videoTitle = trim((string)($campaign['title'] ?? '')) ?: 'Video reward';
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Campaign reward';
$rewardDescription = trim((string)($campaign['reward_template_description'] ?? ''))
    ?: 'Complete the configured watch milestones to unlock rewards in your Microgifter Inbox.';
$rewardValue = mg_campaign_landing_value($campaign);
$campaignImage = mg_campaign_landing_campaign_image($campaign);
$rewardImage = mg_campaign_landing_reward_cover($campaign);
$posterImage = $campaignImage ?? $rewardImage;
$backgroundImage = mg_campaign_landing_background_image($campaign);
$levelPercents = mg_campaign_media_level_percents($milestones, $requiredPercent);
$rewardAllocations = mg_campaign_media_allocations($milestones, [
    'title' => $rewardTitle,
    'value' => $rewardValue,
    'image' => $rewardImage,
    'levels' => $levelPercents,
    'required_percent' => $requiredPercent,
    'currency' => strtoupper(trim((string)($campaign['currency'] ?? 'USD')) ?: 'USD'),
]);
$initialStatus = $previewMode
    ? 'Merchant preview — reward tracking is disabled.'
    : (!empty($state['closed']) ? (string)$state['active_status'] : 'Enter your info to start watching.');
$joinContext = [
    'kind' => 'watch',
    'campaign' => $campaign,
    'profile' => $profile,
    'state' => $state,
    'preview' => $previewMode,
    'prefill' => $prefill,
];
$cardContext = [
    'kind' => 'watch',
    'campaign' => $campaign,
    'state' => $state,
    'reward_allocations' => $rewardAllocations,
    'merchant_name' => (string)$profile['name'],
    'reward_description' => $rewardDescription,
    'level_percents' => $levelPercents,
    'required_percent' => $requiredPercent,
    'initial_status' => $initialStatus,
];
?>
<section
  class="mg-rl-page mg-rl-campaign-foundation mg-rl-media mg-rl-watch mg-rl-compact-campaign<?= $previewMode ? ' is-merchant-preview' : '' ?>"
  data-public-campaign-page
  <?= (!$previewMode && empty($state['closed'])) ? 'data-watch-video-reward' : 'data-watch-video-preview' ?>
  data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>"
  data-campaign-state="<?= mg_e((string)$state['code']) ?>"
  data-campaign-preview="<?= $previewMode ? '1' : '0' ?>"
  data-campaign-closed="<?= !empty($state['closed']) ? '1' : '0' ?>"
  data-video-provider="<?= mg_e($provider) ?>"
  data-video-id="<?= mg_e($videoId) ?>"
  data-uploaded-video-url="<?= mg_e((string)$uploadedUrl) ?>"
  data-uploaded-asset-id="<?= mg_e($uploadedAssetId) ?>"
  data-required-percent="<?= mg_e((string)$requiredPercent) ?>"
>
  <div class="mg-rl-bg"<?= $backgroundImage ? ' style="background-image:url(' . mg_e($backgroundImage) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <?php if ($previewMode): ?>
        <article class="mg-rl-card mg-rl-media-preview">
          <span class="mg-rl-eyebrow">Merchant preview</span>
          <h3><?= mg_e((string)$state['status_label']) ?></h3>
          <p>Video playback is available for review. Customer participation and reward tracking remain disabled until this campaign is active.</p>
        </article>
      <?php endif; ?>

      <?php if ($hasVideo): ?>
        <section class="mg-rl-player mg-rl-media-player" data-watch-video-shell aria-label="<?= mg_e($videoTitle) ?> video reward player">
          <div class="mg-rl-video-shell<?= $posterImage ? ' has-campaign-poster' : '' ?>">
            <?php if ($provider === 'uploaded'): ?>
              <video data-watch-uploaded-player controls playsinline preload="metadata" src="<?= mg_e((string)$uploadedUrl) ?>"<?= $posterImage ? ' poster="' . mg_e($posterImage) . '"' : '' ?>></video>
            <?php elseif ($previewMode || !empty($state['closed'])): ?>
              <iframe src="https://www.youtube.com/embed/<?= mg_e(rawurlencode($videoId)) ?>?rel=0&amp;modestbranding=1" title="<?= mg_e($videoTitle) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <?php else: ?>
              <div id="mg-watch-video-player"></div>
            <?php endif; ?>
            <?php if ($previewMode || !empty($state['closed'])): ?>
              <div class="mg-rl-video-overlay"<?= $posterImage ? ' style="background-image:linear-gradient(90deg,rgba(2,6,23,.52),rgba(2,6,23,.10)),url(' . mg_e($posterImage) . ')"' : '' ?>>
                <span class="mg-rl-play">▶</span>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php else: ?>
        <article class="mg-rl-card mg-rl-media-missing">
          <span class="mg-rl-eyebrow">Video required</span>
          <h3>Connect campaign media</h3>
          <p><?= mg_e((string)$state['message']) ?></p>
        </article>
      <?php endif; ?>

      <aside class="mg-rl-join mg-rl-join-mobile"><?php mg_campaign_media_render_join($joinContext); ?></aside>
      <?php mg_campaign_media_render_cards($cardContext); ?>
    </div>

    <aside class="mg-rl-join mg-rl-join-desktop"><?php mg_campaign_media_render_join($joinContext); ?></aside>
  </div>

  <div class="mg-rl-mobile-dock" data-rl-mobile-dock>
    <button class="mg-rl-mobile-toggle" type="button" data-rl-mobile-toggle aria-expanded="false"><i></i><span><strong>Participant Status</strong><small data-watch-reward-status><?= mg_e($initialStatus) ?></small></span><b>Details</b></button>
    <div class="mg-rl-mobile-drawer" data-rl-mobile-drawer hidden>
      <h3>Reward Activity</h3>
      <div class="mg-rl-mobile-drawer-section"><strong>Current status</strong><p data-watch-reward-status><?= mg_e($initialStatus) ?></p></div>
      <div class="mg-rl-mobile-drawer-section"><strong>Watch activity</strong><ul class="mg-rl-list" data-watch-reward-history><li>No watch activity yet.</li></ul></div>
      <div class="mg-rl-mobile-drawer-section"><strong>Issued rewards</strong><ul class="mg-rl-list" data-watch-reward-issue-history><li>No rewards issued yet.</li></ul></div>
      <a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a>
    </div>
  </div>
</section>
<?php if ($provider === 'youtube' && $hasVideo && !$previewMode && empty($state['closed'])): ?><script src="https://www.youtube.com/iframe_api" async></script><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
