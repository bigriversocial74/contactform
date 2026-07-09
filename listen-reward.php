<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Listen Music Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/watch-listen-standalone-page.css'];
$page_scripts = ['/assets/js/public-listen-music-reward.js'];

function mg_listen_reward_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 800 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}
function mg_listen_reward_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    return mb_strtoupper(mb_substr((string)($parts[0] ?? 'M'), 0, 1) . (count($parts) > 1 ? mb_substr((string)$parts[count($parts) - 1], 0, 1) : 'G'));
}
function mg_listen_reward_rules(mixed $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}
function mg_listen_reward_template_image(mixed $json): ?string
{
    $metadata = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    if (!is_array($metadata)) return null;
    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    return mg_listen_reward_safe_url($metadata['reward_image_url'] ?? $pack['cover_image_url'] ?? null, true);
}
function mg_listen_reward_load(string $ref): ?array
{
    if ($ref === '') return null;
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name,
        pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline,
        pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url,
        rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description,
        rt.value_amount_cents, rt.currency, rt.metadata_json reward_template_metadata_json
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        LEFT JOIN users u ON u.id=c.merchant_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
        WHERE c.status='active' AND c.campaign_type='listen_music_reward' AND (c.public_id=? OR c.public_slug=?)
        LIMIT 1");
    $stmt->execute([$ref, $ref]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$campaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
try { $campaign = mg_listen_reward_load($campaignRef); } catch (Throwable $error) { mg_security_log('warning', 'listen_reward.page.unavailable', 'Unable to load listen reward campaign.', ['exception_class' => $error::class]); $campaign = null; }
require __DIR__ . '/includes/header.php';

if (!$campaign) : ?>
<section class="mg-rl-page"><div class="mg-rl-wrap"><div class="mg-rl-card"><span class="mg-rl-eyebrow">Listen Music Reward</span><h1>Campaign not available</h1><p>Use the campaign link from the merchant to open the correct listen reward page.</p><a class="mg-rl-btn" href="/discover.php">Explore Microgifter</a></div></div></section>
<?php else:
$rules = mg_listen_reward_rules($campaign['rules_json'] ?? null);
$provider = in_array((string)($rules['audio_provider'] ?? 'spotify'), ['spotify', 'uploaded'], true) ? (string)$rules['audio_provider'] : 'spotify';
$spotifyId = trim((string)($rules['spotify_track_id'] ?? ''));
$spotifyEmbed = $spotifyId !== '' ? 'https://open.spotify.com/embed/track/' . rawurlencode($spotifyId) : '';
$uploadedUrl = mg_listen_reward_safe_url($rules['uploaded_audio_url'] ?? null, true);
$uploadedAssetId = trim((string)($rules['uploaded_asset_id'] ?? ''));
$milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
$requiredPercent = max(1, min(100, (int)($rules['required_percent'] ?? 80)));
$trackTitle = trim((string)($rules['track_title'] ?? '')) ?: (string)($campaign['title'] ?? 'Listen reward');
$artistName = trim((string)($rules['artist_name'] ?? ''));
$mediaImageUrl = mg_listen_reward_safe_url($rules['media_image_url'] ?? null, true);
$rewardImageUrl = mg_listen_reward_template_image($campaign['reward_template_metadata_json'] ?? null) ?: $mediaImageUrl;
$merchantName = trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
$avatarUrl = mg_listen_reward_safe_url($campaign['merchant_profile_avatar_url'] ?? null);
$coverUrl = mg_listen_reward_safe_url($campaign['merchant_profile_cover_url'] ?? null) ?: $mediaImageUrl;
$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$isLoggedIn = is_array($currentUser) && !empty($currentUser['id']);
$prefillName = is_array($currentUser) ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? '')) : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: 'Listen to unlock rewards';
$description = trim((string)($campaign['form_description'] ?? '')) ?: 'Enter your info, listen to the track, and unlock rewards based on listen progress.';
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Campaign reward';
$rewardDescription = trim((string)($campaign['reward_template_description'] ?? '')) ?: 'Complete the listening milestone to unlock this reward in your Microgifter Inbox.';
$rewardValueCents = max(0, (int)($campaign['value_amount_cents'] ?? 0));
$rewardCurrency = strtoupper(trim((string)($campaign['currency'] ?? 'USD')) ?: 'USD');
$rewardValue = $rewardValueCents > 0 ? '$' . number_format($rewardValueCents / 100, 2) . ' ' . $rewardCurrency : 'Reward';
$firstMilestone = $milestones[0]['percent'] ?? $requiredPercent;
$hasAudio = $provider === 'uploaded' ? $uploadedUrl !== '' : $spotifyEmbed !== '';
$waveBars = str_repeat('<i></i>', 42);
?>
<section class="mg-rl-page mg-rl-listen" data-listen-music-reward data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>" data-audio-provider="<?= mg_e($provider) ?>" data-spotify-track-id="<?= mg_e($spotifyId) ?>" data-uploaded-audio-url="<?= mg_e((string)$uploadedUrl) ?>" data-uploaded-asset-id="<?= mg_e($uploadedAssetId) ?>" data-required-percent="<?= mg_e((string)$requiredPercent) ?>">
  <div class="mg-rl-bg"<?= $coverUrl ? ' style="background-image:url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <header class="mg-rl-hero"><span class="mg-rl-kicker">Listen Reward</span><?php if ($headline === 'Listen to unlock rewards'): ?><h1><span>Listen to</span><span class="mg-rl-blue">unlock rewards</span></h1><?php else: ?><h1><?= mg_e($headline) ?></h1><?php endif; ?><p><?= mg_e($description) ?></p></header>
      <?php if (!$hasAudio): ?><div class="mg-rl-card"><strong>This Listen Music Reward campaign needs a valid Spotify track or uploaded audio file before it can be viewed.</strong></div><?php else: ?>
      <section class="mg-rl-player" data-listen-audio-shell>
        <div class="mg-rl-track"><div class="mg-rl-art"><?php if ($mediaImageUrl): ?><img src="<?= mg_e($mediaImageUrl) ?>" alt="<?= mg_e($trackTitle) ?> artwork"><span>Now Playing</span><?php else: ?><div class="mg-rl-art-placeholder">Audio</div><?php endif; ?></div><div class="mg-rl-track-copy"><small>Now Playing</small><strong><?= mg_e($trackTitle) ?></strong><em><?= mg_e($artistName ?: $merchantName) ?></em><div class="mg-rl-wave" aria-hidden="true"><?= $waveBars ?></div></div></div>
        <div class="mg-rl-controls"><?php if ($provider === 'uploaded'): ?><audio data-listen-uploaded-player controls preload="metadata" src="<?= mg_e((string)$uploadedUrl) ?>"></audio><?php else: ?><iframe data-listen-spotify-player src="<?= mg_e($spotifyEmbed) ?>" height="152" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe><button class="mg-rl-btn" type="button" data-listen-spotify-confirm>I listened — check rewards</button><?php endif; ?></div>
      </section>
      <section class="mg-rl-bottom">
        <article class="mg-rl-card"><div class="mg-rl-card-head"><span class="mg-rl-icon">📈</span><h2>Your Stats</h2></div><span class="mg-rl-big">0</span><p>Points Earned <span class="mg-rl-pill">Start listening</span></p><div class="mg-rl-stat-grid"><div class="mg-rl-stat"><b>0</b><span>Tracks</span></div><div class="mg-rl-stat"><b>0</b><span>Rewards</span></div><div class="mg-rl-stat"><b><?= mg_e((string)$requiredPercent) ?>%</b><span>Target</span></div></div></article>
        <article class="mg-rl-card"><div class="mg-rl-card-head"><span class="mg-rl-icon">🎁</span><div><span class="mg-rl-eyebrow">Reward History</span><h3>Issued rewards and Inbox status</h3></div></div><ul class="mg-rl-list" data-listen-reward-issue-history><li>No rewards issued yet.</li></ul><div class="mg-public-campaign-result" data-listen-reward-result></div></article>
        <article class="mg-rl-card"><div class="mg-rl-card-head"><span class="mg-rl-icon">🎯</span><div><span class="mg-rl-eyebrow">Listening Progress</span><h3>Complete milestones to unlock rewards.</h3></div></div><div class="mg-rl-progress-row"><div class="mg-rl-step is-on"><span class="mg-rl-dot">✓</span><b>Join</b></div><div class="mg-rl-step"><span class="mg-rl-dot">▶</span><b>Listen</b></div><div class="mg-rl-step is-active"><span class="mg-rl-dot">🎁</span><b><?= mg_e((string)$firstMilestone) ?>%</b></div><div class="mg-rl-step"><span class="mg-rl-dot">✓</span><b>Claim</b></div></div><div class="mg-rl-bar"><span></span></div><ul class="mg-rl-list" data-listen-reward-history><li>No listening activity yet.</li></ul></article>
      </section>
      <?php endif; ?>
    </div>
    <aside class="mg-rl-join">
      <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_listen_reward_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if (!empty($campaign['merchant_profile_headline'])): ?><p><?= mg_e((string)$campaign['merchant_profile_headline']) ?></p><?php endif; ?></div></div>
      <div class="mg-rl-tabs" aria-label="Campaign steps"><span>1. Join</span><span>2. Listen</span><span>3. Rewards</span></div>
      <form class="mg-rl-form" data-listen-reward-form novalidate><input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>"><h3>Join this campaign</h3><p>Enter your details to get started.</p><label>Name<input name="name" placeholder="Full Name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" required placeholder="Email Address" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label><button class="mg-rl-btn mg-rl-btn-dark" type="submit">Join Campaign</button></form>
      <div class="mg-public-campaign-status" data-listen-reward-status>Enter your info to start listening.</div>
      <div class="mg-rl-inbox"><span class="mg-rl-inbox-icon">✉</span><div><h3>Reward &amp; Inbox Access</h3><p>Gift activity appears here when a milestone reward is sent. Open the Microgifter Inbox to view, manage, claim, redeem, or continue PPPM tracking.</p><a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a></div></div>
      <div class="mg-rl-notice"><div><strong>Campaign Notice</strong><ul data-listen-reward-notifications><li>Waiting for listener session.</li></ul></div><a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">View Inbox</a></div>
    </aside>
  </div>
</section>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>
