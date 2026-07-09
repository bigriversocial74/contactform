<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Watch Video Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css', '/assets/css/public-campaign-polish-v1.css', '/assets/css/listen-watch-media-flow-v1.css'];
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
function mg_watch_reward_load(string $ref): ?array
{
    if ($ref === '') return null;
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name, pp.slug merchant_profile_slug, pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline, pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url, pp.location_label merchant_profile_location, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description, rt.value_amount_cents, rt.currency FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id LEFT JOIN users u ON u.id=c.merchant_user_id LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted') WHERE c.status='active' AND c.campaign_type='watch_video_reward' AND (c.public_id=? OR c.public_slug=?) LIMIT 1");
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
$requiredPercent = (int)($rules['required_percent'] ?? 80);
$merchantName = trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
$avatarUrl = mg_watch_reward_safe_url($campaign['merchant_profile_avatar_url'] ?? null);
$coverUrl = mg_watch_reward_safe_url($campaign['merchant_profile_cover_url'] ?? null);
$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$prefillName = is_array($currentUser) ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? '')) : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$hasVideo = $provider === 'uploaded' ? $uploadedUrl !== '' : $videoId !== '';
?>
<section class="mg-public-campaign mg-public-campaign-v2 mg-watch-reward-page" data-watch-video-reward data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>" data-video-provider="<?= mg_e($provider) ?>" data-video-id="<?= mg_e($videoId) ?>" data-uploaded-video-url="<?= mg_e((string)$uploadedUrl) ?>" data-uploaded-asset-id="<?= mg_e($uploadedAssetId) ?>" data-required-percent="<?= mg_e((string)$requiredPercent) ?>">
  <div class="mg-public-campaign-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(6,15,32,.12),rgba(248,247,242,.94) 82%,#fbfaf6),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-public-campaign-shell">
    <div class="mg-public-campaign-heading"><span class="mg-public-campaign-kicker">Microgifter Campaign</span><span class="mg-public-campaign-eyebrow">Watch Video Reward</span><h1><?= mg_e(trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title']) ?></h1><p><?= mg_e(trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Watch this video and unlock rewards as you reach the milestones.')) ?></p><div class="mg-public-campaign-trust-row"><span>Progress rewards</span><span>Sent to Inbox</span><span>PPPM tracked</span></div></div>
    <aside class="mg-public-campaign-card mg-public-campaign-flow-card">
      <div class="mg-public-campaign-profile-card mg-public-campaign-form-profile"><div class="mg-public-campaign-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_watch_reward_initials($merchantName)) ?></span><?php endif; ?></div><div class="mg-public-campaign-profile-copy"><span class="mg-public-campaign-eyebrow">Video rewards</span><h2><?= mg_e($merchantName) ?></h2><?php if (!empty($campaign['merchant_profile_headline'])): ?><p><?= mg_e((string)$campaign['merchant_profile_headline']) ?></p><?php endif; ?><div class="mg-public-campaign-profile-stats"><span>Inbox delivery</span><span><?= mg_e($requiredPercent) ?>% target</span></div></div></div>
      <?php if (!$hasVideo): ?><div class="mg-public-campaign-result is-visible"><strong>This Watch Video Reward campaign needs a valid YouTube or uploaded video before it can be viewed.</strong></div><?php else: ?>
      <form class="mg-public-campaign-form" data-watch-reward-form novalidate><input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>"><div class="mg-public-campaign-field-grid"><label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" required placeholder="you@example.com" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label class="mg-public-campaign-field-wide">Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label></div><button class="mg-btn mg-btn-primary mg-public-campaign-primary-action" type="submit">Start video rewards <span aria-hidden="true">→</span></button></form>
      <div class="mg-public-campaign-reward mg-public-campaign-reward-tab mg-watch-track-row"><div class="mg-media-art-thumb"><?php if ($mediaImageUrl): ?><img src="<?= mg_e($mediaImageUrl) ?>" alt="<?= mg_e((string)($campaign['title'] ?? 'Video reward')) ?> artwork"><?php else: ?><div class="mg-media-art-placeholder">Video</div><?php endif; ?></div><div><span>Video reward</span><strong><?= mg_e((string)($campaign['title'] ?? 'Video reward')) ?></strong><em><?= mg_e((string)($campaign['reward_template_title'] ?? 'Watch reward')) ?></em></div></div>
      <div class="mg-public-campaign-video" data-watch-video-shell hidden><?php if ($provider === 'uploaded'): ?><video data-watch-uploaded-player controls playsinline preload="metadata" src="<?= mg_e((string)$uploadedUrl) ?>"></video><?php else: ?><div id="mg-watch-video-player"></div><?php endif; ?></div>
      <div class="mg-public-campaign-status" data-watch-reward-status>Enter your info to start watching.</div>
      <div class="mg-campaign-checklist"><span class="mg-eyebrow">Progress</span><ul data-watch-reward-milestones><?php foreach ($milestones as $m): ?><li class="is-warn" data-watch-milestone="<?= mg_e((string)($m['percent'] ?? '')) ?>"><b></b><span><?= mg_e((string)($m['percent'] ?? '')) ?>% — <?= mg_e((string)($m['label'] ?? 'Gift milestone')) ?></span></li><?php endforeach; ?></ul></div><div class="mg-public-campaign-result" data-watch-reward-result></div>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php if ($provider === 'youtube'): ?><script src="https://www.youtube.com/iframe_api" async></script><?php endif; ?>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>