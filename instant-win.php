<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Instant Win Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css', '/assets/css/public-campaign-polish-v1.css', '/assets/css/public-instant-win.css'];
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

function mg_instant_win_page_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
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
$rules = $campaign ? mg_instant_win_page_rules($campaign['rules_json'] ?? null) : [];
$scratchImageUrl = mg_instant_win_page_safe_url($rules['scratch_image_url'] ?? $rules['media_image_url'] ?? '');
$headline = $campaign ? (trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title']) : 'Instant Win Reward';
$description = $campaign ? (trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Scratch the card and reveal your instant win result.')) : 'Scratch the card and reveal your instant win result.';
$rewardTitle = $campaign ? (trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward') : 'Microgifter reward';
$rewardValue = $campaign ? mg_instant_win_page_value($campaign) : 'Reward';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-public-campaign mg-public-campaign-v2" data-public-campaign-page>
  <div class="mg-public-campaign-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(6,15,32,.08),rgba(248,247,242,.92) 82%,#fbfaf6),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-public-campaign-shell">
    <div class="mg-public-campaign-heading">
      <span class="mg-public-campaign-kicker">Microgifter Campaign</span>
      <span class="mg-public-campaign-eyebrow">Spin / Scratch Instant Win</span>
      <h1><?= mg_e($headline) ?></h1>
      <p><?= mg_e($description) ?></p>
      <div class="mg-public-campaign-trust-row"><span>Scratch reveal</span><span>CRM play tracked</span><span>Winners sent to Inbox</span></div>
    </div>
    <aside class="mg-public-campaign-card mg-public-campaign-flow-card">
      <?php if ($errorMessage !== ''): ?>
        <div class="mg-public-campaign-result is-visible"><strong><?= mg_e($errorMessage) ?></strong></div>
      <?php else: ?>
        <form class="mg-public-campaign-form" data-campaign-form data-instant-win-form data-submit-endpoint="/api/public/campaigns/instant-win.php" data-campaign-type="instant_win_reward" novalidate>
          <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
          <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
          <input type="hidden" name="campaign_type" value="instant_win_reward">
          <input type="hidden" name="entry_reveal_confirmed" value="0">
          <div class="mg-public-campaign-profile-card mg-public-campaign-form-profile"><div class="mg-public-campaign-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_instant_win_page_initials($merchantName)) ?></span><?php endif; ?></div><div class="mg-public-campaign-profile-copy"><span class="mg-public-campaign-eyebrow">Instant Win</span><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?><div class="mg-public-campaign-profile-stats"><?php if ($merchantLocation !== ''): ?><span><?= mg_e($merchantLocation) ?></span><?php endif; ?><span>Scratch reveal</span><span>Inbox delivery</span></div></div><?php if ($merchantProfileUrl): ?><div class="mg-public-campaign-profile-actions"><a class="mg-btn mg-btn-soft" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a></div><?php endif; ?></div>
          <button class="mg-public-campaign-reward mg-public-campaign-reward-tab mg-instant-win-card" type="button" data-instant-win-card data-scratch-image="<?= mg_e($scratchImageUrl ?? '') ?>"><span data-instant-win-prompt>Scratch card</span><strong data-instant-win-title>Swipe or tap to reveal</strong><em><?= mg_e($rewardTitle) ?> · <?= mg_e($rewardValue) ?></em><div class="mg-public-campaign-reward-meta"><span>Play tracked</span><span>Winner reward to Inbox</span></div><small class="mg-instant-win-mobile-hint">Mobile friendly: swipe your finger over the image.</small></button>
          <div class="mg-public-campaign-field-grid"><label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label class="mg-public-campaign-field-wide">Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label></div>
          <div class="mg-action-row"><button class="mg-btn mg-btn-soft" type="button" data-instant-win-reveal>Reveal card</button><button class="mg-btn mg-btn-primary mg-public-campaign-primary-action" type="submit">Submit instant-win play <span aria-hidden="true">→</span></button></div>
          <div class="mg-public-campaign-status" data-campaign-status data-instant-win-status></div><p class="mg-public-campaign-privacy">Every play is recorded for merchant campaign analytics. Winners receive the reward through Microgifter Inbox.</p>
        </form>
        <div class="mg-public-campaign-result" data-campaign-result></div>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
