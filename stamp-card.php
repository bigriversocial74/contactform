<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Stamp Card Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/watch-listen-standalone-page.css', '/assets/css/listen-wave-reward-polish-v1.css', '/assets/css/watch-listen-sidebar-rewards-v1.css', '/assets/css/public-stamp-card.css'];
$page_scripts = ['/assets/js/public-campaign.js', '/assets/js/public-stamp-card.js'];

$campaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
$campaign = null;
$errorMessage = '';

function mg_stamp_card_page_safe_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}
function mg_stamp_card_page_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'MG';
    $parts = preg_split('/\s+/u', $name) ?: [];
    return mb_strtoupper(mb_substr((string)($parts[0] ?? 'M'), 0, 1) . (count($parts) > 1 ? mb_substr((string)$parts[count($parts) - 1], 0, 1) : ''));
}
function mg_stamp_card_page_value(array $campaign): string
{
    $type = (string)($campaign['value_type'] ?? '');
    $rewardType = (string)($campaign['reward_type'] ?? '');
    if ($type === 'percent' && ($campaign['value_percent'] ?? null) !== null) return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '% reward';
    if (in_array($type, ['free_item', 'custom'], true) || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) return (string)($campaign['reward_template_title'] ?? 'Reward');
    $cents = (int)($campaign['value_amount_cents'] ?? 0);
    return $cents > 0 ? ((string)($campaign['currency'] ?? 'USD') . ' ' . number_format($cents / 100, 2) . ' value') : 'Reward';
}
function mg_stamp_card_page_rules(array $campaign): array
{
    $decoded = is_string($campaign['rules_json'] ?? null) && trim((string)$campaign['rules_json']) !== '' ? json_decode((string)$campaign['rules_json'], true) : null;
    return is_array($decoded) ? $decoded : [];
}

try {
    if ($campaignRef !== '') {
        $pdo = mg_db();
        $stmt = $pdo->prepare("SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name,
            pp.slug merchant_profile_slug, pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline, pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url, pp.location_label merchant_profile_location,
            rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description, rt.reward_type, rt.value_type, rt.value_amount_cents, rt.value_percent, rt.currency, rt.redemption_instructions, rt.metadata_json reward_metadata_json
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
            LEFT JOIN users u ON u.id=c.merchant_user_id
            LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
            WHERE c.status='active' AND c.campaign_type='stamp_card_reward' AND (c.public_id=? OR c.public_slug=?)
            LIMIT 1");
        $stmt->execute([$campaignRef, $campaignRef]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) mg_security_log('warning', 'public.stamp_card.unavailable', 'Unable to load stamp card campaign.', ['exception_class' => $error::class]);
    $campaign = null;
}

if (!$campaign) $errorMessage = 'Use the stamp-card link from the merchant to open this reward.';
$now = time();
if ($campaign && !empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) $errorMessage = 'This stamp-card campaign has not started yet.';
if ($campaign && !empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) $errorMessage = 'This stamp-card campaign has ended.';
if ($campaign && ($campaign['quantity_limit'] ?? null) !== null && (int)($campaign['issued_count'] ?? 0) >= (int)$campaign['quantity_limit']) $errorMessage = 'This stamp-card reward limit has been reached.';

$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$prefillName = is_array($currentUser) ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? '')) : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$merchantName = $campaign ? (trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'))) : 'Microgifter merchant';
$merchantHeadline = $campaign ? trim((string)($campaign['merchant_profile_headline'] ?? '')) : '';
$avatarUrl = $campaign ? mg_stamp_card_page_safe_url($campaign['merchant_profile_avatar_url'] ?? null) : null;
$coverUrl = $campaign ? mg_stamp_card_page_safe_url($campaign['merchant_profile_cover_url'] ?? null) : null;
$rules = $campaign ? mg_stamp_card_page_rules($campaign) : [];
$requiredCount = max(1, min(100, (int)($rules['required_count'] ?? $rules['stamp_required_count'] ?? 5)));
$stampLabel = trim((string)($rules['stamp_label'] ?? 'Visit')) ?: 'Visit';
$headline = $campaign ? (trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title']) : 'Stamp Card Reward';
$description = $campaign ? (trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Add visits to your stamp card and unlock a reward.')) : 'Add visits to your stamp card and unlock a reward.';
$rewardTitle = $campaign ? (trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward') : 'Microgifter reward';
$rewardDescription = $campaign ? (trim((string)($campaign['reward_template_description'] ?? '')) ?: 'Complete the card to unlock this reward in your Microgifter Inbox.') : 'Complete the card to unlock this reward in your Microgifter Inbox.';
$rewardValue = $campaign ? mg_stamp_card_page_value($campaign) : 'Reward';
$initialStatus = 'Enter your info, then add your stamp.';

require __DIR__ . '/includes/header.php';
if (!$campaign || $errorMessage !== ''): ?>
<section class="mg-rl-page"><div class="mg-rl-wrap"><div class="mg-rl-card"><span class="mg-rl-eyebrow">Stamp Card Reward</span><h1>Campaign not available</h1><p><?= mg_e($errorMessage ?: 'Use the campaign link from the merchant to open the correct stamp card page.') ?></p><a class="mg-rl-btn" href="/discover.php">Explore Microgifter</a></div></div></section>
<?php else: ?>
<section class="mg-rl-page mg-rl-stamp-card" data-stamp-card-reward>
  <div class="mg-rl-bg"<?= $coverUrl ? ' style="background-image:url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <form class="mg-rl-wrap" data-campaign-form data-stamp-card-form data-submit-endpoint="/api/public/campaigns/stamp-card.php" data-campaign-type="stamp_card_reward" data-stamp-required-count="<?= mg_e((string)$requiredCount) ?>" data-stamp-label="<?= mg_e($stampLabel) ?>" novalidate>
    <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
    <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
    <input type="hidden" name="campaign_type" value="stamp_card_reward">
    <div class="mg-rl-left">
      <header class="mg-rl-hero"><h1><?= mg_e($headline) ?></h1><p><?= mg_e($description) ?></p></header>
      <section class="mg-rl-player mg-stamp-card-canvas" aria-label="Stamp card progress">
        <section class="mg-stamp-card-visual" data-stamp-card-visual aria-live="polite">
          <div class="mg-stamp-card-visual-head"><span>Your punch card</span><strong data-stamp-progress-copy>0 of <?= mg_e((string)$requiredCount) ?> <?= mg_e(strtolower($stampLabel)) ?> stamps</strong></div>
          <div class="mg-stamp-card-punch-grid" data-stamp-punch-grid><?php for ($i=1; $i <= $requiredCount; $i++): ?><span class="mg-stamp-punch" data-stamp-index="<?= mg_e((string)$i) ?>"><b><?= mg_e((string)$i) ?></b></span><?php endfor; ?></div>
          <div class="mg-stamp-card-meter"><span data-stamp-meter-fill style="width:0%"></span></div>
          <p data-stamp-progress-message>Submit after each eligible visit. Your progress is tracked for this campaign.</p>
        </section>
      </section>
      <div class="mg-rl-sidebar-stack mg-stamp-card-detail-stack">
        <article class="mg-rl-card"><span class="mg-rl-eyebrow">Campaign Reward</span><h3><?= mg_e($rewardTitle) ?></h3><p><?= mg_e($rewardDescription) ?></p><small><?= mg_e($rewardValue) ?></small></article>
        <article class="mg-rl-card"><span class="mg-rl-eyebrow">Campaign Status</span><h3 data-stamp-card-status><?= mg_e($initialStatus) ?></h3><ul class="mg-rl-list"><li>Unique card per campaign + customer email/contact.</li></ul></article>
      </div>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop">
      <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_stamp_card_page_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?></div></div>
      <div class="mg-rl-form"><h3>Join this campaign</h3><p>Enter your details, then add a stamp to this card.</p><label>Name<input name="name" placeholder="Full Name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" required placeholder="Email Address" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label><button class="mg-rl-btn mg-rl-btn-dark" type="submit" data-stamp-card-submit>Add Stamp / Check Reward</button></div>
      <div class="mg-public-campaign-result" data-campaign-result></div>
      <p class="mg-public-campaign-privacy">Stamp progress is created when this form is submitted, not when the page loads.</p>
    </aside>
  </form>
</section>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>