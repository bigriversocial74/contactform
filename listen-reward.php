<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Listen Music Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css', '/assets/css/public-campaign-polish-v1.css'];
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

function mg_listen_reward_load(string $ref): ?array
{
    if ($ref === '') return null;
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name,
        pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline,
        pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url,
        rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description,
        rt.value_amount_cents, rt.currency
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
try {
    $campaign = mg_listen_reward_load($campaignRef);
} catch (Throwable $error) {
    mg_security_log('warning', 'listen_reward.page.unavailable', 'Unable to load listen reward campaign.', ['exception_class' => $error::class]);
    $campaign = null;
}

require __DIR__ . '/includes/header.php';

if (!$campaign) : ?>
<section class="mg-public-campaign mg-public-campaign-empty">
  <div class="mg-public-campaign-shell">
    <div class="mg-public-campaign-card">
      <span class="mg-public-campaign-eyebrow">Listen Music Reward</span>
      <h1>Campaign not available</h1>
      <p>Use the campaign link from the merchant to open the correct listen reward page.</p>
      <a class="mg-btn mg-btn-primary" href="/discover.php">Explore Microgifter</a>
    </div>
  </div>
</section>
<?php else:
$rules = mg_listen_reward_rules($campaign['rules_json'] ?? null);
$provider = in_array((string)($rules['audio_provider'] ?? 'spotify'), ['spotify', 'uploaded'], true) ? (string)$rules['audio_provider'] : 'spotify';
$spotifyId = trim((string)($rules['spotify_track_id'] ?? ''));
$spotifyEmbed = $spotifyId !== '' ? 'https://open.spotify.com/embed/track/' . rawurlencode($spotifyId) : '';
$uploadedUrl = mg_listen_reward_safe_url($rules['uploaded_audio_url'] ?? null, true);
$uploadedAssetId = trim((string)($rules['uploaded_asset_id'] ?? ''));
$milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
$requiredPercent = (int)($rules['required_percent'] ?? 80);
$trackTitle = trim((string)($rules['track_title'] ?? '')) ?: (string)($campaign['title'] ?? 'Listen reward');
$artistName = trim((string)($rules['artist_name'] ?? ''));
$merchantName = trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
$avatarUrl = mg_listen_reward_safe_url($campaign['merchant_profile_avatar_url'] ?? null);
$coverUrl = mg_listen_reward_safe_url($campaign['merchant_profile_cover_url'] ?? null);
$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$isLoggedIn = is_array($currentUser) && !empty($currentUser['id']);
$prefillName = is_array($currentUser) ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? '')) : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$hasAudio = $provider === 'uploaded' ? $uploadedUrl !== '' : $spotifyEmbed !== '';
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title'];
$description = trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Listen to this track and unlock rewards as you reach the milestones.');
$milestoneSummary = count($milestones) ? implode(' · ', array_map(static fn($m): string => (string)($m['percent'] ?? '') . '% gift', $milestones)) : 'Listen progress unlocks the attached reward.';
?>
<section class="mg-public-campaign mg-public-campaign-v2 mg-listen-reward-page" data-listen-music-reward data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>" data-audio-provider="<?= mg_e($provider) ?>" data-spotify-track-id="<?= mg_e($spotifyId) ?>" data-uploaded-audio-url="<?= mg_e((string)$uploadedUrl) ?>" data-uploaded-asset-id="<?= mg_e($uploadedAssetId) ?>" data-required-percent="<?= mg_e((string)$requiredPercent) ?>">
  <div class="mg-public-campaign-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(6,15,32,.12),rgba(248,247,242,.94) 82%,#fbfaf6),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-public-campaign-shell">
    <div class="mg-public-campaign-heading mg-listen-clean-heading">
      <span class="mg-public-campaign-kicker">Microgifter Campaign</span>
      <span class="mg-public-campaign-eyebrow">Listen Music Reward</span>
      <h1><?= mg_e($headline) ?></h1>
      <p><?= mg_e($description) ?></p>
    </div>

    <aside class="mg-public-campaign-card mg-public-campaign-flow-card mg-listen-tab-card">
      <div class="mg-public-campaign-profile-card mg-public-campaign-form-profile">
        <div class="mg-public-campaign-avatar">
          <?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_listen_reward_initials($merchantName)) ?></span><?php endif; ?>
        </div>
        <div class="mg-public-campaign-profile-copy">
          <span class="mg-public-campaign-eyebrow">Music rewards</span>
          <h2><?= mg_e($merchantName) ?></h2>
          <?php if (!empty($campaign['merchant_profile_headline'])): ?><p><?= mg_e((string)$campaign['merchant_profile_headline']) ?></p><?php endif; ?>
          <div class="mg-public-campaign-profile-stats"><span><?= mg_e($provider === 'spotify' ? 'Spotify listen intent' : ($requiredPercent . '% target')) ?></span><span>Inbox + PPPM</span></div>
        </div>
      </div>

      <?php if (!$hasAudio): ?>
        <div class="mg-public-campaign-result is-visible"><strong>This Listen Music Reward campaign needs a valid Spotify track or uploaded audio file before it can be viewed.</strong></div>
      <?php else: ?>
        <nav class="mg-listen-tabs" aria-label="Listen reward sections">
          <button type="button" class="is-active" data-listen-tab-trigger="join" aria-selected="true">1. Join</button>
          <button type="button" data-listen-tab-trigger="media" aria-selected="false">2. Listen</button>
          <button type="button" data-listen-tab-trigger="rewards" aria-selected="false">3. Rewards</button>
        </nav>

        <div class="mg-listen-tab-panel is-active" data-listen-tab-panel="join">
          <?php if (!$isLoggedIn): ?>
            <div class="mg-listen-account-gate">
              <strong>Account recommended for campaign participation</strong>
              <p>Most Microgifter campaigns should require a signed-in account so rewards, Inbox delivery, PPPM tracking, and listening history stay tied to the right customer. For now, you can enter an email below while we finish the account-gated flow.</p>
              <div><a class="mg-btn mg-btn-soft" href="/signin.php">Sign in</a><a class="mg-btn mg-btn-primary" href="/signup.php">Create account</a></div>
            </div>
          <?php endif; ?>
          <form class="mg-public-campaign-form" data-listen-reward-form novalidate>
            <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
            <div class="mg-public-campaign-field-grid">
              <label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label>
              <label>Email<input name="email" type="email" required placeholder="you@example.com" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label>
              <label class="mg-public-campaign-field-wide">Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label>
            </div>
            <button class="mg-btn mg-btn-primary mg-public-campaign-primary-action" type="submit">Start music rewards <span aria-hidden="true">→</span></button>
          </form>

          <div class="mg-public-campaign-step-grid mg-listen-form-steps" aria-label="How listen rewards work">
            <div class="mg-public-campaign-mini-step"><span>1</span><strong>Add your info</strong><small>Use the email you want tied to your Microgifter Inbox.</small></div>
            <div class="mg-public-campaign-mini-step"><span>2</span><strong>Join the campaign</strong><small>Microgifter records the campaign source and reward status.</small></div>
            <div class="mg-public-campaign-mini-step"><span>3</span><strong>Open Inbox</strong><small>Eligible rewards continue through Inbox and PPPM tracking.</small></div>
          </div>
        </div>

        <div class="mg-listen-tab-panel" data-listen-tab-panel="media" hidden>
          <div class="mg-public-campaign-reward mg-public-campaign-reward-tab mg-listen-track-card">
            <span><?= mg_e($artistName ?: 'Track') ?></span>
            <strong><?= mg_e($trackTitle) ?></strong>
            <em><?= mg_e($provider === 'spotify' ? 'Spotify listen-intent reward' : ($requiredPercent . '% listen target')) ?></em>
            <p><?= mg_e($milestoneSummary) ?></p>
            <div class="mg-public-campaign-reward-meta"><span>Listening session</span><span>Reward check</span></div>
          </div>

          <div class="mg-public-campaign-video" data-listen-audio-shell hidden>
            <?php if ($provider === 'uploaded'): ?>
              <audio data-listen-uploaded-player controls preload="metadata" src="<?= mg_e((string)$uploadedUrl) ?>"></audio>
            <?php else: ?>
              <iframe data-listen-spotify-player src="<?= mg_e($spotifyEmbed) ?>" width="100%" height="152" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
              <button class="mg-btn mg-btn-primary" type="button" data-listen-spotify-confirm>I listened — unlock reward</button>
            <?php endif; ?>
          </div>

          <div class="mg-listen-notification-panel">
            <div class="mg-listen-panel-head"><span class="mg-eyebrow">Realtime notifications</span><strong data-listen-reward-status>Enter your info to start listening.</strong></div>
            <ul data-listen-reward-notifications><li>Waiting for listener session.</li></ul>
          </div>

          <div class="mg-listen-history-panel">
            <div class="mg-listen-panel-head"><span class="mg-eyebrow">Listening history</span><strong>Session activity</strong></div>
            <ul data-listen-reward-history><li>No listening activity yet.</li></ul>
          </div>
        </div>

        <div class="mg-listen-tab-panel" data-listen-tab-panel="rewards" hidden>
          <div class="mg-campaign-checklist">
            <span class="mg-eyebrow">Awards / rewards</span>
            <ul data-listen-reward-milestones>
              <?php if ($milestones): foreach ($milestones as $m): ?>
                <li class="is-warn" data-listen-milestone="<?= mg_e((string)($m['percent'] ?? '')) ?>"><b></b><span><?= mg_e((string)($m['percent'] ?? '')) ?>% — <?= mg_e((string)($m['label'] ?? 'Gift milestone')) ?></span></li>
              <?php endforeach; else: ?>
                <li class="is-warn" data-listen-milestone="<?= mg_e((string)$requiredPercent) ?>"><b></b><span><?= mg_e((string)$requiredPercent) ?>% — Attached reward milestone</span></li>
              <?php endif; ?>
            </ul>
          </div>
          <div class="mg-listen-history-panel">
            <div class="mg-listen-panel-head"><span class="mg-eyebrow">Reward history</span><strong>Issued rewards and Inbox status</strong></div>
            <ul data-listen-reward-issue-history><li>No rewards issued yet.</li></ul>
          </div>
          <div class="mg-public-campaign-result" data-listen-reward-result></div>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>
