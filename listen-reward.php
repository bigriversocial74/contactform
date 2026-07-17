<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-media-landing.php';

$page_title = 'Listen Music Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-media-alignment-v1.css',
    '/assets/css/watch-listen-stamp-compact-layout-v1.css?v=1.0.0',
];
$page_scripts = ['/assets/js/public-listen-music-reward.js'];

$bootstrap = mg_campaign_landing_bootstrap('listen_music_reward', $page_title);
$campaign = is_array($bootstrap['campaign'] ?? null) ? $bootstrap['campaign'] : null;
$previewMode = (bool)($bootstrap['preview'] ?? false);
$page_title = (string)($bootstrap['page_title'] ?? $page_title);
$page_meta = is_array($bootstrap['page_meta'] ?? null) ? $bootstrap['page_meta'] : [];
$state = mg_campaign_landing_state($campaign, $previewMode);

require __DIR__ . '/includes/header.php';

if (!$campaign || empty($state['available'])) {
    mg_campaign_landing_render_unavailable(
        'Listen Music Reward',
        'Listen to merchant audio and unlock milestone rewards powered by Microgifter.',
        (string)($state['message'] ?? '')
    );
    require __DIR__ . '/includes/footer.php';
    return;
}

$rules = mg_campaign_landing_rules($campaign);
$provider = in_array((string)($rules['audio_provider'] ?? 'spotify'), ['spotify', 'uploaded'], true)
    ? (string)$rules['audio_provider']
    : 'spotify';
$spotifyId = trim((string)($rules['spotify_track_id'] ?? ''));
$spotifyEmbed = $spotifyId !== '' ? 'https://open.spotify.com/embed/track/' . rawurlencode($spotifyId) : '';
$uploadedUrl = mg_campaign_landing_safe_url($rules['uploaded_audio_url'] ?? null);
$uploadedAssetId = trim((string)($rules['uploaded_asset_id'] ?? ''));
$milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
$requiredPercent = max(1, min(100, (int)($rules['required_percent'] ?? 80)));
$hasAudio = $provider === 'uploaded' ? $uploadedUrl !== null : $spotifyEmbed !== '';

if (!$hasAudio) {
    $state = array_merge($state, [
        'closed' => true,
        'code' => 'media_missing',
        'message' => 'This campaign needs a valid Spotify track or uploaded audio file before participation can begin.',
        'active_status' => 'Audio required',
    ]);
}

$profile = mg_campaign_landing_profile($campaign);
$prefill = mg_campaign_landing_prefill();
$trackTitle = trim((string)($rules['track_title'] ?? '')) ?: (trim((string)($campaign['title'] ?? '')) ?: 'Listen reward');
$artistName = trim((string)($rules['artist_name'] ?? '')) ?: (string)$profile['name'];
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Campaign reward';
$rewardDescription = trim((string)($campaign['reward_template_description'] ?? ''))
    ?: 'Complete the configured listening milestones to unlock rewards in your Microgifter Inbox.';
$rewardValue = mg_campaign_landing_value($campaign);
$campaignImage = mg_campaign_landing_campaign_image($campaign);
$rewardImage = mg_campaign_landing_reward_cover($campaign);
$primaryImage = $campaignImage ?? $rewardImage;
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
    : (!empty($state['closed']) ? (string)$state['active_status'] : 'Enter your info to start listening.');
$joinContext = [
    'kind' => 'listen',
    'campaign' => $campaign,
    'profile' => $profile,
    'state' => $state,
    'preview' => $previewMode,
    'prefill' => $prefill,
];
$cardContext = [
    'kind' => 'listen',
    'campaign' => $campaign,
    'state' => $state,
    'reward_allocations' => $rewardAllocations,
    'merchant_name' => (string)$profile['name'],
    'reward_description' => $rewardDescription,
    'level_percents' => $levelPercents,
    'required_percent' => $requiredPercent,
    'initial_status' => $initialStatus,
];
$waveBars = str_repeat('<i></i>', 96);
?>
<section
  class="mg-rl-page mg-rl-campaign-foundation mg-rl-media mg-rl-listen mg-rl-compact-campaign<?= $previewMode ? ' is-merchant-preview' : '' ?>"
  data-public-campaign-page
  <?= (!$previewMode && empty($state['closed'])) ? 'data-listen-music-reward' : 'data-listen-music-preview' ?>
  data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>"
  data-campaign-state="<?= mg_e((string)$state['code']) ?>"
  data-campaign-preview="<?= $previewMode ? '1' : '0' ?>"
  data-campaign-closed="<?= !empty($state['closed']) ? '1' : '0' ?>"
  data-audio-provider="<?= mg_e($provider) ?>"
  data-spotify-track-id="<?= mg_e($spotifyId) ?>"
  data-uploaded-audio-url="<?= mg_e((string)$uploadedUrl) ?>"
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
          <p>Audio playback is available for review. Customer participation and reward tracking remain disabled until this campaign is active.</p>
        </article>
      <?php endif; ?>

      <?php if ($hasAudio): ?>
        <section class="mg-rl-player mg-rl-media-player" data-listen-audio-shell aria-label="<?= mg_e($trackTitle) ?> audio reward player">
          <div class="mg-rl-track">
            <div class="mg-rl-art">
              <?php if ($primaryImage): ?><img src="<?= mg_e($primaryImage) ?>" alt="<?= mg_e($trackTitle) ?> campaign artwork"><span>Now Playing</span><?php else: ?><div class="mg-rl-art-placeholder">Audio</div><?php endif; ?>
            </div>
            <div class="mg-rl-track-copy">
              <small><?= $campaignImage ? 'Campaign image' : 'Now Playing' ?></small>
              <strong><?= mg_e($trackTitle) ?></strong>
              <em><?= mg_e($artistName) ?></em>
              <div class="mg-rl-wave" aria-hidden="true"><?= $waveBars ?></div>
            </div>
          </div>
          <div class="mg-rl-controls">
            <?php if ($provider === 'uploaded'): ?>
              <audio data-listen-uploaded-player controls preload="metadata" src="<?= mg_e((string)$uploadedUrl) ?>"></audio>
            <?php else: ?>
              <iframe data-listen-spotify-player src="<?= mg_e($spotifyEmbed) ?>" height="152" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
              <button class="mg-rl-btn" type="button" data-listen-spotify-confirm<?= !empty($state['closed']) ? ' disabled aria-disabled="true"' : '' ?>>I listened — check rewards</button>
            <?php endif; ?>
          </div>
        </section>
      <?php else: ?>
        <article class="mg-rl-card mg-rl-media-missing">
          <span class="mg-rl-eyebrow">Audio required</span>
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
    <button class="mg-rl-mobile-toggle" type="button" data-rl-mobile-toggle aria-expanded="false"><i></i><span><strong>Participant Status</strong><small data-listen-reward-status><?= mg_e($initialStatus) ?></small></span><b>Details</b></button>
    <div class="mg-rl-mobile-drawer" data-rl-mobile-drawer hidden>
      <h3>Reward Activity</h3>
      <div class="mg-rl-mobile-drawer-section"><strong>Current status</strong><p data-listen-reward-status><?= mg_e($initialStatus) ?></p></div>
      <div class="mg-rl-mobile-drawer-section"><strong>Listening activity</strong><ul class="mg-rl-list" data-listen-reward-history><li>No listening activity yet.</li></ul></div>
      <div class="mg-rl-mobile-drawer-section"><strong>Issued rewards</strong><ul class="mg-rl-list" data-listen-reward-issue-history><li>No rewards issued yet.</li></ul></div>
      <a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
