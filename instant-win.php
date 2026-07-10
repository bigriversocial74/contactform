<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Instant Win Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/watch-listen-standalone-page.css', '/assets/css/listen-wave-reward-polish-v1.css', '/assets/css/watch-listen-sidebar-rewards-v1.css', '/assets/css/public-instant-win.css'];
$page_scripts = ['/assets/js/public-campaign.js', '/assets/js/public-instant-win.js'];

$campaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
$campaign = null;
$errorMessage = '';

function mg_instant_win_page_safe_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}
function mg_instant_win_page_rules(mixed $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}
function mg_instant_win_page_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'MG';
    $parts = preg_split('/\s+/u', $name) ?: [];
    return mb_strtoupper(mb_substr((string)($parts[0] ?? 'M'), 0, 1) . (count($parts) > 1 ? mb_substr((string)$parts[count($parts)-1], 0, 1) : ''));
}
function mg_instant_win_page_value(array $campaign): string
{
    $type = (string)($campaign['value_type'] ?? '');
    $rewardType = (string)($campaign['reward_type'] ?? '');
    if ($type === 'percent' && ($campaign['value_percent'] ?? null) !== null) return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '% reward';
    if (in_array($type, ['free_item','custom'], true) || in_array($rewardType, ['free_item','perk_upgrade','event_reward','custom'], true)) return (string)($campaign['reward_template_title'] ?? 'Reward');
    $cents = (int)($campaign['value_amount_cents'] ?? 0);
    return $cents > 0 ? ((string)($campaign['currency'] ?? 'USD') . ' ' . number_format($cents / 100, 2) . ' value') : 'Reward';
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
            WHERE c.status='active' AND c.campaign_type='instant_win_reward' AND (c.public_id=? OR c.public_slug=?)
            LIMIT 1");
        $stmt->execute([$campaignRef, $campaignRef]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) mg_security_log('warning', 'public.instant_win.unavailable', 'Unable to load instant win campaign.', ['exception_class'=>$error::class]);
    $campaign = null;
}

if (!$campaign) $errorMessage = 'Use the instant-win link from the merchant to open this reward.';
$now = time();
if ($campaign && !empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) $errorMessage = 'This instant-win campaign has not started yet.';
if ($campaign && !empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) $errorMessage = 'This instant-win campaign has ended.';
if ($campaign && ($campaign['quantity_limit'] ?? null) !== null && (int)($campaign['issued_count'] ?? 0) >= (int)$campaign['quantity_limit']) $errorMessage = 'This instant-win reward limit has been reached.';

$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$prefillName = is_array($currentUser) ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? '')) : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$rules = $campaign ? mg_instant_win_page_rules($campaign['rules_json'] ?? null) : [];
$merchantName = $campaign ? (trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'))) : 'Microgifter merchant';
$merchantHeadline = $campaign ? trim((string)($campaign['merchant_profile_headline'] ?? '')) : '';
$avatarUrl = $campaign ? mg_instant_win_page_safe_url($campaign['merchant_profile_avatar_url'] ?? null) : null;
$coverUrl = $campaign ? (mg_instant_win_page_safe_url($campaign['merchant_profile_cover_url'] ?? null) ?: mg_instant_win_page_safe_url($rules['scratch_image_url'] ?? $rules['media_image_url'] ?? '')) : null;
$scratchImageUrl = mg_instant_win_page_safe_url($rules['scratch_image_url'] ?? $rules['media_image_url'] ?? '');
$headline = $campaign ? (trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title']) : 'Scratch to win';
$description = $campaign ? (trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Enter your info, scratch the card, and reveal the result.')) : 'Enter your info, scratch the card, and reveal the result.';
$rewardTitle = $campaign ? (trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward') : 'Microgifter reward';
$rewardDescription = $campaign ? (trim((string)($campaign['reward_template_description'] ?? '')) ?: 'Scratch and submit the play to see if this reward lands in your Microgifter Inbox.') : 'Scratch and submit the play to see if this reward lands in your Microgifter Inbox.';
$rewardValue = $campaign ? mg_instant_win_page_value($campaign) : 'Reward';
$initialStatus = 'Enter your info, then scratch the card.';

require __DIR__ . '/includes/header.php';
if (!$campaign || $errorMessage !== ''): ?>
<section class="mg-rl-page"><div class="mg-rl-wrap"><div class="mg-rl-card"><span class="mg-rl-eyebrow">Instant Win Reward</span><h1>Campaign not available</h1><p><?= mg_e($errorMessage ?: 'Use the campaign link from the merchant to open the correct instant win page.') ?></p><a class="mg-rl-btn" href="/discover.php">Explore Microgifter</a></div></div></section>
<?php else: ?>
<section class="mg-rl-page mg-rl-instant-win" data-instant-win-reward>
  <div class="mg-rl-bg"<?= $coverUrl ? ' style="background-image:url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <form class="mg-rl-wrap" data-campaign-form data-instant-win-form data-submit-endpoint="/api/public/campaigns/instant-win.php" data-campaign-type="instant_win_reward" novalidate>
    <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
    <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
    <input type="hidden" name="campaign_type" value="instant_win_reward">
    <input type="hidden" name="entry_reveal_confirmed" value="0">
    <div class="mg-rl-left">
      <header class="mg-rl-hero"><h1><?= mg_e($headline) ?></h1><p><?= mg_e($description) ?></p></header>
      <section class="mg-rl-player mg-instant-win-canvas" aria-label="Instant win scratch card">
        <button class="mg-public-campaign-reward mg-public-campaign-reward-tab mg-instant-win-card" type="button" data-instant-win-card data-scratch-image="<?= mg_e($scratchImageUrl ?? '') ?>"><span data-instant-win-prompt>Scratch card</span><strong data-instant-win-title>Swipe or tap to reveal</strong><em><?= mg_e($rewardTitle) ?> · <?= mg_e($rewardValue) ?></em><div class="mg-public-campaign-reward-meta"><span>Play tracked after submit</span><span>Winner reward to Inbox</span></div><small class="mg-instant-win-mobile-hint">Swipe your finger or drag your mouse over the image.</small></button>
      </section>
      <div class="mg-rl-sidebar-stack mg-instant-win-detail-stack">
        <article class="mg-rl-card"><span class="mg-rl-eyebrow">Campaign Reward</span><h3><?= mg_e($rewardTitle) ?></h3><p><?= mg_e($rewardDescription) ?></p><small><?= mg_e($rewardValue) ?></small></article>
        <article class="mg-rl-card"><span class="mg-rl-eyebrow">Campaign Status</span><h3 data-instant-win-status><?= mg_e($initialStatus) ?></h3><ul class="mg-rl-list" data-instant-win-history><li>No play recorded yet.</li></ul></article>
      </div>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop">
      <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_instant_win_page_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?></div></div>
      <div class="mg-rl-form"><h3>Join this campaign</h3><p>Enter your details, scratch the card, then submit the play.</p><label>Name<input name="name" placeholder="Full Name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" required placeholder="Email Address" maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" maxlength="60" placeholder="Optional"></label><button class="mg-rl-btn mg-rl-btn-soft" type="button" data-instant-win-reveal>Reveal card</button><button class="mg-rl-btn mg-rl-btn-dark" type="submit">Submit Instant Win Play</button></div>
      <div class="mg-public-campaign-result" data-campaign-result></div>
      <p class="mg-public-campaign-privacy">CRM contact is created when the play is submitted, not when the page loads.</p>
    </aside>
  </form>
</section>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>