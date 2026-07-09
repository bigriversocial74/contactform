<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Watch Video Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/watch-listen-standalone-page.css', '/assets/css/listen-wave-reward-polish-v1.css', '/assets/css/watch-listen-sidebar-rewards-v1.css'];
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
function mg_watch_reward_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    return mb_strtoupper(mb_substr((string)($parts[0] ?? 'M'), 0, 1) . (count($parts) > 1 ? mb_substr((string)$parts[count($parts)-1], 0, 1) : 'G'));
}
function mg_watch_reward_rules(mixed $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}
function mg_watch_reward_template_image(mixed $json): ?string
{
    $metadata = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    if (!is_array($metadata)) return null;
    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    return mg_watch_reward_safe_url($metadata['reward_image_url'] ?? $pack['reward_image_url'] ?? null, true);
}
function mg_watch_reward_pick(array $source, array $keys): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $source)) continue;
        $value = trim((string)$source[$key]);
        if ($value !== '') return $value;
    }
    return '';
}
function mg_watch_reward_money(mixed $cents, string $currency): string
{
    if (!is_numeric($cents)) return '';
    $amount = (int)$cents;
    return $amount > 0 ? '$' . number_format($amount / 100, 2) . ' ' . $currency : '';
}
function mg_watch_reward_level_label(array $percents): string
{
    $clean = [];
    foreach ($percents as $percent) {
        $percent = max(1, min(100, (int)$percent));
        $clean[$percent] = $percent . '%';
    }
    ksort($clean, SORT_NUMERIC);
    return implode(', ', array_values($clean));
}
function mg_watch_reward_allocations(array $milestones, array $defaults): array
{
    $groups = [];
    foreach ($milestones as $milestone) {
        if (!is_array($milestone)) continue;
        $percent = max(1, min(100, (int)($milestone['percent'] ?? $defaults['required_percent'] ?? 100)));
        $title = mg_watch_reward_pick($milestone, ['reward_title', 'reward_name', 'reward_template_title', 'product_name', 'gift_name', 'title']) ?: (string)$defaults['title'];
        $value = mg_watch_reward_money($milestone['value_amount_cents'] ?? $milestone['reward_value_amount_cents'] ?? $milestone['value_cents'] ?? null, (string)$defaults['currency']);
        $value = $value ?: (mg_watch_reward_pick($milestone, ['reward_value', 'value_label', 'display_value', 'value']) ?: (string)$defaults['value']);
        $image = mg_watch_reward_safe_url($milestone['reward_image_url'] ?? $milestone['reward_image'] ?? $milestone['gift_image_url'] ?? null, true) ?: ($defaults['image'] ?? null);
        $key = sha1($title . '|' . $value . '|' . (string)$image);
        if (!isset($groups[$key])) {
            $groups[$key] = ['title' => $title, 'value' => $value, 'image' => $image, 'levels' => []];
        }
        $groups[$key]['levels'][] = $percent;
    }
    if (!$groups) {
        $groups[] = [
            'title' => (string)$defaults['title'],
            'value' => (string)$defaults['value'],
            'image' => $defaults['image'] ?? null,
            'levels' => $defaults['levels'] ?? [(int)$defaults['required_percent']],
        ];
    }
    return array_values($groups);
}
function mg_watch_reward_sidebar_cards(array $rewardAllocations, string $merchantName, string $rewardDescription, array $levelPercents, int $requiredPercent, string $initialStatus): void
{
    ?>
    <div class="mg-rl-sidebar-stack" data-watch-reward-sidebar>
      <article class="mg-rl-card mg-rl-reward-info">
        <span class="mg-rl-eyebrow">Campaign Reward</span>
        <div class="mg-rl-reward-carousel">
          <div class="mg-rl-reward-stack <?= count($rewardAllocations) > 1 ? 'has-multiple' : '' ?>">
            <?php foreach ($rewardAllocations as $allocation): ?>
              <div class="mg-rl-reward-item <?= !empty($allocation['image']) ? 'has-image' : 'is-text-only' ?>">
                <?php if (!empty($allocation['image'])): ?><img class="mg-rl-reward-image" src="<?= mg_e((string)$allocation['image']) ?>" alt="<?= mg_e((string)$allocation['title']) ?> reward image"><?php endif; ?>
                <span class="mg-rl-reward-copy">
                  <strong class="mg-rl-reward-business"><?= mg_e($merchantName) ?></strong>
                  <b class="mg-rl-reward-name"><?= mg_e((string)$allocation['title']) ?></b>
                  <small class="mg-rl-reward-value"><?= mg_e((string)$allocation['value']) ?></small>
                  <small class="mg-rl-reward-levels">Reward level<?= count($allocation['levels']) > 1 ? 's' : '' ?>: <?= mg_e(mg_watch_reward_level_label($allocation['levels'])) ?></small>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <small class="mg-rl-carousel-hint">Scroll to view all rewards</small>
        </div>
        <p><?= mg_e($rewardDescription) ?></p>
      </article>
      <article class="mg-rl-card mg-rl-levels">
        <span class="mg-rl-eyebrow">Reward Levels</span>
        <h3>Watch progress unlocks reward milestones.</h3>
        <div class="mg-rl-progress-row"><?php foreach ($levelPercents as $percent): ?><div class="mg-rl-step <?= $percent <= $requiredPercent ? 'is-active' : '' ?>"><span class="mg-rl-dot"><?= mg_e((string)$percent) ?>%</span><b><?= mg_e((string)$percent) ?>%</b></div><?php endforeach; ?></div>
        <div class="mg-rl-bar"><span style="width:<?= mg_e((string)$requiredPercent) ?>%"></span></div>
      </article>
      <article class="mg-rl-card mg-rl-status-card">
        <span class="mg-rl-eyebrow">Campaign Status</span>
        <h3 data-watch-reward-status><?= mg_e($initialStatus) ?></h3>
        <ul class="mg-rl-list" data-watch-reward-history><li>No watch activity yet.</li></ul>
        <ul class="mg-rl-list" data-watch-reward-issue-history><li>No rewards issued yet.</li></ul>
      </article>
    </div>
    <?php
}
function mg_watch_reward_load(string $ref): ?array
{
    if ($ref === '') return null;
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name,
        pp.slug merchant_profile_slug, pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline,
        pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url, pp.location_label merchant_profile_location,
        rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description,
        rt.value_amount_cents, rt.currency, rt.metadata_json reward_template_metadata_json
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        LEFT JOIN users u ON u.id=c.merchant_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
        WHERE c.status='active' AND c.campaign_type='watch_video_reward' AND (c.public_id=? OR c.public_slug=?)
        LIMIT 1");
    $stmt->execute([$ref, $ref]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$campaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
try { $campaign = mg_watch_reward_load($campaignRef); } catch (Throwable $error) { mg_security_log('warning', 'watch_reward.page.unavailable', 'Unable to load watch reward campaign.', ['exception_class'=>$error::class]); $campaign = null; }
require __DIR__ . '/includes/header.php';

if (!$campaign) : ?>
<section class="mg-rl-page"><div class="mg-rl-wrap"><div class="mg-rl-card"><span class="mg-rl-eyebrow">Watch Video Reward</span><h1>Campaign not available</h1><p>Use the campaign link from the merchant to open the correct watch reward page.</p><a class="mg-rl-btn" href="/discover.php">Explore Microgifter</a></div></div></section>
<?php else:
$rules = mg_watch_reward_rules($campaign['rules_json'] ?? null);
$provider = in_array((string)($rules['video_provider'] ?? 'youtube'), ['youtube','uploaded'], true) ? (string)$rules['video_provider'] : 'youtube';
$videoId = trim((string)($rules['youtube_video_id'] ?? ''));
$uploadedUrl = mg_watch_reward_safe_url($rules['uploaded_video_url'] ?? null, true);
$uploadedAssetId = trim((string)($rules['uploaded_asset_id'] ?? ''));
$mediaImageUrl = mg_watch_reward_safe_url($rules['media_image_url'] ?? null, true);
$milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
$requiredPercent = max(1, min(100, (int)($rules['required_percent'] ?? 80)));
$rewardImageUrl = mg_watch_reward_template_image($campaign['reward_template_metadata_json'] ?? null);
$merchantName = trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
$avatarUrl = mg_watch_reward_safe_url($campaign['merchant_profile_avatar_url'] ?? null);
$coverUrl = mg_watch_reward_safe_url($campaign['merchant_profile_cover_url'] ?? null) ?: $mediaImageUrl;
$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
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
$initialStatus = 'Enter your info to start watching.';
$levelPercents = [];
foreach ($milestones as $milestone) {
    if (is_array($milestone) && isset($milestone['percent'])) $levelPercents[] = max(1, min(100, (int)$milestone['percent']));
}
if (!$levelPercents) $levelPercents[] = $requiredPercent;
$levelPercents = array_slice(array_values(array_unique($levelPercents)), 0, 4);
$rewardAllocations = mg_watch_reward_allocations($milestones, [
    'title' => $rewardTitle,
    'value' => $rewardValue,
    'image' => $rewardImageUrl,
    'levels' => $levelPercents,
    'required_percent' => $requiredPercent,
    'currency' => $rewardCurrency,
]);
?>
<section class="mg-rl-page mg-rl-watch" data-watch-video-reward data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>" data-video-provider="<?= mg_e($provider) ?>" data-video-id="<?= mg_e($videoId) ?>" data-uploaded-video-url="<?= mg_e((string)$uploadedUrl) ?>" data-uploaded-asset-id="<?= mg_e($uploadedAssetId) ?>" data-required-percent="<?= mg_e((string)$requiredPercent) ?>">
  <div class="mg-rl-bg"<?= $coverUrl ? ' style="background-image:url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <header class="mg-rl-hero"><?php if ($headline === 'Watch to unlock rewards'): ?><h1><span>Watch to</span><span class="mg-rl-blue">unlock rewards</span></h1><?php else: ?><h1><?= mg_e($headline) ?></h1><?php endif; ?><p><?= mg_e($description) ?></p></header>
      <?php if (!$hasVideo): ?><div class="mg-rl-card"><strong>This Watch Video Reward campaign needs a valid YouTube or uploaded video before it can be viewed.</strong></div><?php else: ?>
      <section class="mg-rl-player" data-watch-video-shell aria-label="<?= mg_e($videoTitle) ?> video reward player">
        <div class="mg-rl-video-shell"><?php if ($provider === 'uploaded'): ?><video data-watch-uploaded-player controls playsinline preload="metadata" src="<?= mg_e((string)$uploadedUrl) ?>"></video><?php else: ?><div id="mg-watch-video-player"></div><?php endif; ?><div class="mg-rl-video-overlay"><span class="mg-rl-play">▶</span></div></div>
      </section>
      <aside class="mg-rl-join mg-rl-join-mobile">
        <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_watch_reward_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if (!empty($campaign['merchant_profile_headline'])): ?><p><?= mg_e((string)$campaign['merchant_profile_headline']) ?></p><?php endif; ?></div></div>
        <form class="mg-rl-form" data-watch-reward-form novalidate><input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>"><h3>Join this campaign</h3><p>Enter your details to get started.</p><label>Name<input name="name" placeholder="Full Name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" required placeholder="Email Address" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label><button class="mg-rl-btn mg-rl-btn-dark" type="submit">Start Watching &amp; Join Campaign</button></form>
        <div class="mg-public-campaign-result" data-watch-reward-result></div>
        <?php mg_watch_reward_sidebar_cards($rewardAllocations, $merchantName, $rewardDescription, $levelPercents, $requiredPercent, $initialStatus); ?>
      </aside>
      <?php endif; ?>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop">
      <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_watch_reward_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if (!empty($campaign['merchant_profile_headline'])): ?><p><?= mg_e((string)$campaign['merchant_profile_headline']) ?></p><?php endif; ?></div></div>
      <form class="mg-rl-form" data-watch-reward-form novalidate><input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>"><h3>Join this campaign</h3><p>Enter your details to get started.</p><label>Name<input name="name" placeholder="Full Name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" required placeholder="Email Address" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label><button class="mg-rl-btn mg-rl-btn-dark" type="submit">Start Watching &amp; Join Campaign</button></form>
      <div class="mg-public-campaign-result" data-watch-reward-result></div>
      <?php if ($hasVideo) mg_watch_reward_sidebar_cards($rewardAllocations, $merchantName, $rewardDescription, $levelPercents, $requiredPercent, $initialStatus); ?>
    </aside>
  </div>
  <div class="mg-rl-mobile-dock" data-rl-mobile-dock><button class="mg-rl-mobile-toggle" type="button" data-rl-mobile-toggle aria-expanded="false"><i></i><span><strong>Participant Status</strong><small data-watch-reward-status><?= mg_e($initialStatus) ?></small></span><b>Details</b></button><div class="mg-rl-mobile-drawer" data-rl-mobile-drawer hidden><h3>Reward Activity</h3><div class="mg-rl-mobile-drawer-section"><strong>Current status</strong><p data-watch-reward-status><?= mg_e($initialStatus) ?></p></div><div class="mg-rl-mobile-drawer-section"><strong>Watch activity</strong><ul class="mg-rl-list" data-watch-reward-history><li>No watch activity yet.</li></ul></div><div class="mg-rl-mobile-drawer-section"><strong>Issued rewards</strong><ul class="mg-rl-list" data-watch-reward-issue-history><li>No rewards issued yet.</li></ul></div><a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a></div></div>
</section>
<?php if ($provider === 'youtube' && $hasVideo): ?><script src="https://www.youtube.com/iframe_api" async></script><?php endif; ?>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>
