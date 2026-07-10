<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Instant Win Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/watch-listen-standalone-page.css', '/assets/css/public-campaign-experience-v1.css'];
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
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}

function mg_instant_win_page_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'MG';
    $parts = preg_split('/\s+/u', $name) ?: [];
    return mb_strtoupper(mb_substr((string)($parts[0] ?? 'M'), 0, 1) . (count($parts) > 1 ? mb_substr((string)$parts[count($parts) - 1], 0, 1) : ''));
}

function mg_instant_win_page_value(array $campaign): string
{
    $type = (string)($campaign['value_type'] ?? '');
    $rewardType = (string)($campaign['reward_type'] ?? '');
    if ($type === 'percent' && ($campaign['value_percent'] ?? null) !== null) return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '% reward';
    if (in_array($type, ['free_item', 'custom'], true) || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) return (string)($campaign['reward_template_title'] ?? 'Reward');
    $cents = (int)($campaign['value_amount_cents'] ?? 0);
    return $cents > 0 ? ((string)($campaign['currency'] ?? 'USD') . ' ' . number_format($cents / 100, 2) . ' value') : 'Reward';
}

function mg_instant_win_page_rules(array $campaign): array
{
    if (!is_string($campaign['rules_json'] ?? null) || trim((string)$campaign['rules_json']) === '') return [];
    $decoded = json_decode((string)$campaign['rules_json'], true);
    return is_array($decoded) ? $decoded : [];
}

try {
    if ($campaignRef !== '') {
        $pdo = mg_db();
        $stmt = $pdo->prepare("SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name,
                   pp.slug merchant_profile_slug, pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline, pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url, pp.location_label merchant_profile_location,
                   rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description, rt.reward_type, rt.value_type, rt.value_amount_cents, rt.value_percent, rt.currency, rt.redemption_instructions, rt.metadata_json reward_metadata_json
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
            LEFT JOIN users u ON u.id = c.merchant_user_id
            LEFT JOIN public_profiles pp ON pp.user_id = c.merchant_user_id AND pp.status = 'active' AND pp.visibility IN ('public','unlisted')
            WHERE c.status = 'active' AND c.campaign_type = 'instant_win_reward' AND (c.public_id = ? OR c.public_slug = ?)
            LIMIT 1");
        $stmt->execute([$campaignRef, $campaignRef]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) mg_security_log('warning', 'public.instant_win.unavailable', 'Unable to load instant win campaign.', ['exception_class' => $error::class]);
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
$merchantName = $campaign ? (trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'))) : 'Microgifter merchant';
$merchantHeadline = $campaign ? trim((string)($campaign['merchant_profile_headline'] ?? '')) : '';
$merchantLocation = $campaign ? trim((string)($campaign['merchant_profile_location'] ?? '')) : '';
$merchantProfileSlug = $campaign ? trim((string)($campaign['merchant_profile_slug'] ?? '')) : '';
$merchantProfileUrl = $merchantProfileSlug !== '' ? '/profile.php?slug=' . rawurlencode($merchantProfileSlug) : null;
$coverUrl = $campaign ? mg_instant_win_page_safe_url($campaign['merchant_profile_cover_url'] ?? null) : null;
$avatarUrl = $campaign ? mg_instant_win_page_safe_url($campaign['merchant_profile_avatar_url'] ?? null) : null;
$rules = $campaign ? mg_instant_win_page_rules($campaign) : [];
$playMode = in_array((string)($rules['play_mode'] ?? $rules['mode'] ?? 'scratch_card'), ['spin_wheel', 'scratch_card'], true) ? (string)($rules['play_mode'] ?? $rules['mode'] ?? 'scratch_card') : 'scratch_card';
$oddsPercent = max(0, min(100, (int)($rules['odds_percent'] ?? $rules['instant_win_odds_percent'] ?? 100)));
$scratchImageUrl = mg_instant_win_page_safe_url($rules['media_image_url'] ?? $rules['scratch_image_url'] ?? null);
$headline = $campaign ? (trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title']) : 'Instant Win Reward';
$description = $campaign ? (trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Scratch the card or spin the wheel and reveal your instant win result.')) : 'Scratch the card or spin the wheel and reveal your instant win result.';
$rewardTitle = $campaign ? (trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward') : 'Microgifter reward';
$rewardValue = $campaign ? mg_instant_win_page_value($campaign) : 'Reward';
$modeLabel = $playMode === 'spin_wheel' ? 'Spin Wheel' : 'Scratch Card';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-rl-page mg-rl-instant" data-public-campaign-page data-instant-win-experience>
  <div class="mg-rl-bg"<?= $coverUrl ? ' style="background-image:url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <?php if ($errorMessage !== ''): ?>
      <div class="mg-rl-left"><article class="mg-rl-card"><span class="mg-rl-eyebrow">Instant Win</span><h2>Campaign not available</h2><p><?= mg_e($errorMessage) ?></p><a class="mg-rl-btn mg-rl-btn-soft" href="/discover.php">Explore Microgifter</a></article></div>
    <?php else: ?>
      <div class="mg-rl-left">
        <header class="mg-rl-hero"><h1><?= mg_e($headline) ?></h1><p><?= mg_e($description) ?></p><div class="mg-public-campaign-trust-row"><span><?= mg_e($modeLabel) ?></span><span><?= mg_e((string)$oddsPercent) ?>% configured odds</span><span>Winner reward to Inbox</span></div></header>
        <section class="mg-rl-player" aria-label="Instant win interaction canvas">
          <div class="mg-instant-stage" data-instant-stage data-mode="<?= mg_e($playMode) ?>">
            <button class="mg-instant-card<?= $playMode === 'spin_wheel' ? ' is-wheel-mode' : ' is-scratch-mode' ?>" type="button" data-instant-win-card data-mode="<?= mg_e($playMode) ?>">
              <span class="mg-instant-result-under"><span>Instant result</span><strong><?= mg_e($rewardTitle) ?></strong><em><?= mg_e($rewardValue) ?> · submit to record your play</em></span>
              <?php if ($playMode === 'spin_wheel'): ?>
                <span class="mg-instant-wheel-layer"><span class="mg-instant-wheel-pointer"></span><span class="mg-instant-wheel" aria-hidden="true"></span><span class="mg-instant-wheel-copy"><span>Tap to spin</span><strong>Spin for your reward</strong></span></span>
              <?php else: ?>
                <span class="mg-instant-scratch-layer" data-scratch-image="<?= mg_e($scratchImageUrl ?? '') ?>"><?php if ($scratchImageUrl): ?><img src="<?= mg_e($scratchImageUrl) ?>" alt="Scratch-off artwork"><?php endif; ?><canvas data-instant-scratch-canvas aria-hidden="true"></canvas><span class="mg-instant-scratch-copy"><span>Scratch to reveal</span><strong>Swipe the card</strong></span></span>
              <?php endif; ?>
            </button>
          </div>
        </section>
        <aside class="mg-rl-join mg-rl-join-mobile">
          <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_instant_win_page_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?></div></div>
          <form class="mg-rl-form" data-campaign-form data-campaign-keep-visible="1" data-instant-win-form data-submit-endpoint="/api/public/campaigns/instant-win.php" data-campaign-type="instant_win_reward" novalidate>
            <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>"><input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>"><input type="hidden" name="campaign_type" value="instant_win_reward"><input type="hidden" name="entry_reveal_confirmed" value="0"><input type="hidden" name="entry_instant_win_mode" value="<?= mg_e($playMode) ?>">
            <h3>Submit your play</h3><p>Complete the interaction first, then submit your result.</p>
            <label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label>
            <button class="mg-rl-btn mg-rl-btn-soft" type="button" data-instant-win-reveal><?= $playMode === 'spin_wheel' ? 'Spin wheel' : 'Reveal card' ?></button><button class="mg-rl-btn mg-rl-btn-dark" type="submit">Submit instant-win play</button><div class="mg-public-campaign-status" data-campaign-status data-instant-win-status></div>
          </form><div class="mg-public-campaign-result" data-campaign-result></div>
        </aside>
        <div class="mg-rl-bottom"><article class="mg-rl-card"><span class="mg-rl-eyebrow">Reward</span><h3><?= mg_e($rewardTitle) ?></h3><p><?= mg_e($rewardValue) ?></p></article><article class="mg-rl-card"><span class="mg-rl-eyebrow">Status</span><h3 data-instant-experience-state>Ready to play</h3><p>No CRM contact is created for no-win plays unless a contact already exists.</p></article><article class="mg-rl-card"><span class="mg-rl-eyebrow">History</span><h3>Play tracked</h3><p>Winning value events create or promote the merchant CRM contact.</p></article></div>
      </div>
      <aside class="mg-rl-join mg-rl-join-desktop">
        <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_instant_win_page_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?><?php if ($merchantProfileUrl): ?><a class="mg-rl-btn mg-rl-btn-soft" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a><?php endif; ?></div></div>
        <form class="mg-rl-form" data-campaign-form data-campaign-keep-visible="1" data-instant-win-form data-submit-endpoint="/api/public/campaigns/instant-win.php" data-campaign-type="instant_win_reward" novalidate>
          <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>"><input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>"><input type="hidden" name="campaign_type" value="instant_win_reward"><input type="hidden" name="entry_reveal_confirmed" value="0"><input type="hidden" name="entry_instant_win_mode" value="<?= mg_e($playMode) ?>">
          <h3>Submit your play</h3><p>Complete the interaction first, then submit your result.</p>
          <label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label>
          <button class="mg-rl-btn mg-rl-btn-soft" type="button" data-instant-win-reveal><?= $playMode === 'spin_wheel' ? 'Spin wheel' : 'Reveal card' ?></button><button class="mg-rl-btn mg-rl-btn-dark" type="submit">Submit instant-win play</button><div class="mg-public-campaign-status" data-campaign-status data-instant-win-status></div><p class="mg-public-campaign-privacy">Winning reward issuance creates the merchant CRM contact.</p>
        </form><div class="mg-public-campaign-result" data-campaign-result></div>
      </aside>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>