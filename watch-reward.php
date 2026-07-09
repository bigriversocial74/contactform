<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Watch Video Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css', '/assets/css/public-campaign-polish-v1.css', '/assets/css/listen-watch-media-flow-v1.css', '/assets/css/watch-listen-centering-fix-v1.css', '/assets/css/watch-listen-centering-fix-v2.css'];
$page_scripts = ['/assets/js/public-watch-video-reward.js'];

function mg_watch_reward_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}
function mg_watch_reward_initials(string $name): string { $parts = preg_split('/\s+/u', trim($name)) ?: []; return mb_strtoupper(mb_substr((string)($parts[0] ?? 'M'), 0, 1) . (count($parts) > 1 ? mb_substr((string)$parts[count($parts)-1], 0, 1) : 'G')); }
function mg_watch_reward_rules(mixed $json): array { $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null; return is_array($decoded) ? $decoded : []; }
function mg_watch_reward_template_image(mixed $json): ?string
{
    $metadata = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    if (!is_array($metadata)) return null;
    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    return mg_watch_reward_safe_url($metadata['reward_image_url'] ?? $pack['cover_image_url'] ?? null, true);
}
function mg_watch_reward_load(string $ref): ?array
{
    if ($ref === '') return null;
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name, pp.slug merchant_profile_slug, pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline, pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url, pp.location_label merchant_profile_location, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description, rt.value_amount_cents, rt.currency, rt.metadata_json reward_template_metadata_json FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id LEFT JOIN users u ON u.id=c.merchant_user_id LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted') WHERE c.status='active' AND c.campaign_type='watch_video_reward' AND (c.public_id=? OR c.public_slug=?) LIMIT 1");
    $stmt->execute([$ref, $ref]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$campaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
try { $campaign = mg_watch_reward_load($campaignRef); } catch (Throwable $error) { mg_security_log('warning', 'watch_reward.page.unavailable', 'Unable to load watch reward campaign.', ['exception_class'=>$error::class]); $campaign = null; }
require __DIR__ . '/includes/header.php';
if (!$campaign) : ?>
<section class="mg-public-campaign mg-public-campaign-empty"><div class="mg-public-campaign-shell"><div class="mg-public-campaign-card"><span class="mg-public-campaign-eyebrow">Watch Video Reward</span><h1>Campaign not available</h1><p>Use the campaign link from the merchant to open the correct watch reward page.</p><a class="mg-btn mg-btn-primary" href="/discover.php">Explore Microgifter</a></div></div></section>
<?php else:
$rules = mg_watch_reward_rules($campaign['rules_json'] ?? null);
$provider = in_array((string)($rules['video_provider'] ?? 'youtube'), ['youtube','uploaded'], true) ? (string)$rules['video_provider'] : 'youtube';
$videoId = trim((string)($rules['youtube_video_id'] ?? ''));
$uploadedUrl = mg_watch_reward_safe_url($rules['uploaded_video_url'] ?? null, true);
$uploadedAssetId = trim((string)($rules['uploaded_asset_id'] ?? ''));
$mediaImageUrl = mg_watch_reward_safe_url($rules['media_image_url'] ?? null, true);
$milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
$requiredPercent = max(1, min(100, (int)($rules['required_percent'] ?? 80)));
$rewardImageUrl = mg_watch_reward_template_image($campaign['reward_template_metadata_json'] ?? null) ?: $mediaImageUrl;
$merchantName = trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
$avatarUrl = mg_watch_reward_safe_url($campaign['merchant_profile_avatar_url'] ?? null);
$coverUrl = mg_watch_reward_safe_url($campaign['merchant_profile_cover_url'] ?? null) ?: $mediaImageUrl;
$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$isLoggedIn = is_array($currentUser) && !empty($currentUser['id']);
$prefillName = is_array($currentUser) ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? '')) : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$hasVideo = $provider === 'uploaded' ? $uploadedUrl !== '' : $videoId !== '';
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: 'Watch to unlock rewards';
$description = trim((string)($campaign['form_description'] ?? '')) ?: 'Enter your info, watch the video, and unlock rewards based on watch progress.';
$videoTitle = trim((string)($campaign['title'] ?? 'Video reward')) ?: 'Video reward';
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Campaign reward';
$rewardDescription = trim((string)($campaign['reward_template_description'] ?? '')) ?: 'Complete the watch milestone to unlock this reward in your Microgifter Inbox.';
$rewardValueCents = max(0, (int)($campaign['value_amount_cents'] ?? 0));
$rewardCurrency = strtoupper(trim((string)($campaign['currency'] ?? 'USD')) ?: 'USD');
$rewardValue = $rewardValueCents > 0 ? '$' . number_format($rewardValueCents / 100, 2) . ' ' . $rewardCurrency : 'Reward';
$firstMilestone = $milestones[0]['percent'] ?? $requiredPercent;
?>
<section class="mg-public-campaign mg-public-campaign-v2 mg-watch-reward-page mg-media-reward-landing mg-media-reward-watch" data-watch-video-reward data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>" data-video-provider="<?= mg_e($provider) ?>" data-video-id="<?= mg_e($videoId) ?>" data-uploaded-video-url="<?= mg_e((string)$uploadedUrl) ?>" data-uploaded-asset-id="<?= mg_e($uploadedAssetId) ?>" data-required-percent="<?= mg_e((string)$requiredPercent) ?>">
  <div class="mg-public-campaign-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(255,248,240,.32),rgba(248,250,252,.86) 58%,#f8fafc 100%),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-public-campaign-shell mg-media-reward-shell">
    <div class="mg-public-campaign-heading mg-watch-clean-heading mg-media-reward-hero"><span class="mg-media-reward-mode">Watch Reward</span><h1><?= mg_e($headline) ?></h1><p><?= mg_e($description) ?></p></div>
    <main class="mg-media-reward-left-column">
      <?php if (!$hasVideo): ?><div class="mg-public-campaign-result is-visible"><strong>This Watch Video Reward campaign needs a valid YouTube or uploaded video before it can be viewed.</strong></div><?php else: ?>
        <section class="mg-media-stage-card mg-media-stage-card-watch" aria-label="Watch media reward player"><div class="mg-watch-track-row mg-media-stage-summary"><div class="mg-media-art-thumb"><?php if ($mediaImageUrl): ?><img src="<?= mg_e($mediaImageUrl) ?>" alt="<?= mg_e($videoTitle) ?> artwork"><?php else: ?><div class="mg-media-art-placeholder">Video</div><?php endif; ?></div><div><span>Now Playing</span><strong><?= mg_e($videoTitle) ?></strong><em><?= mg_e((string)($campaign['reward_template_title'] ?? 'Watch reward')) ?></em></div></div><div class="mg-public-campaign-video mg-media-stage-player" data-watch-video-shell><?php if ($provider === 'uploaded'): ?><video data-watch-uploaded-player controls playsinline preload="metadata" src="<?= mg_e((string)$uploadedUrl) ?>"></video><?php else: ?><div id="mg-watch-video-player"></div><?php endif; ?></div></section>
        <section class="mg-media-reward-bottom-grid">
          <article class="mg-media-dashboard-card mg-media-reward-offer-card"><div class="mg-media-card-head"><span class="mg-media-icon">🎁</span><div><span class="mg-eyebrow">Reward</span><h2><?= mg_e($rewardTitle) ?></h2></div></div><div class="mg-media-reward-offer-body"><div class="mg-media-reward-image"><?php if ($rewardImageUrl): ?><img src="<?= mg_e($rewardImageUrl) ?>" alt="<?= mg_e($rewardTitle) ?> reward image"><?php else: ?><span>Reward</span><?php endif; ?></div><div><strong><?= mg_e($rewardValue) ?></strong><p><?= mg_e($rewardDescription) ?></p><small>Unlock target: <?= mg_e((string)$firstMilestone) ?>% watch progress</small></div></div><div class="mg-public-campaign-result" data-watch-reward-result></div></article>
          <article class="mg-media-dashboard-card mg-listen-history-panel"><div class="mg-listen-panel-head"><span class="mg-eyebrow">Reward history</span><strong>Issued rewards and Inbox status</strong></div><ul data-watch-reward-issue-history><li>No rewards issued yet.</li></ul></article>
          <article class="mg-media-dashboard-card mg-listen-history-panel"><div class="mg-listen-panel-head"><span class="mg-eyebrow">Watch history</span><strong>Session activity</strong></div><ul data-watch-reward-history><li>No watch activity yet.</li></ul></article>
        </section>
      <?php endif; ?>
    </main>
    <aside class="mg-public-campaign-card mg-media-join-card">
      <div class="mg-public-campaign-profile-card mg-public-campaign-form-profile"><div class="mg-public-campaign-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_watch_reward_initials($merchantName)) ?></span><?php endif; ?></div><div class="mg-public-campaign-profile-copy"><h2><?= mg_e($merchantName) ?></h2><?php if (!empty($campaign['merchant_profile_headline'])): ?><p><?= mg_e((string)$campaign['merchant_profile_headline']) ?></p><?php endif; ?></div></div>
      <form class="mg-public-campaign-form mg-media-join-form" data-watch-reward-form novalidate><input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>"><h3>Join this campaign</h3><p>Enter your details to start tracking watch progress.</p><div class="mg-public-campaign-field-grid"><label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" required placeholder="you@example.com" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label class="mg-public-campaign-field-wide">Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label></div><button class="mg-btn mg-btn-primary mg-public-campaign-primary-action" type="submit">Join Campaign</button></form>
      <?php if (!$isLoggedIn): ?><div class="mg-media-account-note"><strong>Account recommended</strong><p>Sign in to connect Inbox delivery, reward history, PPPM tracking, and future follow-up.</p><div><a class="mg-btn mg-btn-soft" href="/signin.php">Sign in</a><a class="mg-btn mg-btn-primary" href="/signup.php">Create account</a></div></div><?php endif; ?>
      <div class="mg-public-campaign-status mg-listen-join-status" data-watch-reward-status>Enter your info to start watching.</div>
      <div class="mg-listen-notification-panel mg-media-notification-panel"><div class="mg-listen-panel-head"><span class="mg-eyebrow">Campaign notice</span><strong>Reward and Inbox access</strong></div><ul data-watch-reward-notifications><li>Waiting for watcher session.</li></ul><a class="mg-btn mg-btn-soft" href="/inbox.php">Open Microgifter Inbox</a></div>
    </aside>
  </div>
</section>
<?php if ($provider === 'youtube'): ?><script src="https://www.youtube.com/iframe_api" async></script><?php endif; ?>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>
