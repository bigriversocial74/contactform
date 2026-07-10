<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Stamp Card Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css', '/assets/css/public-campaign-polish-v1.css', '/assets/css/public-stamp-card.css'];
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
            WHERE c.status = 'active' AND c.campaign_type = 'stamp_card_reward' AND (c.public_id = ? OR c.public_slug = ?)
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
$merchantLocation = $campaign ? trim((string)($campaign['merchant_profile_location'] ?? '')) : '';
$merchantProfileSlug = $campaign ? trim((string)($campaign['merchant_profile_slug'] ?? '')) : '';
$merchantProfileUrl = $merchantProfileSlug !== '' ? '/profile.php?slug=' . rawurlencode($merchantProfileSlug) : null;
$coverUrl = $campaign ? mg_stamp_card_page_safe_url($campaign['merchant_profile_cover_url'] ?? null) : null;
$avatarUrl = $campaign ? mg_stamp_card_page_safe_url($campaign['merchant_profile_avatar_url'] ?? null) : null;
$headline = $campaign ? (trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title']) : 'Stamp Card Reward';
$description = $campaign ? (trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Add visits to your stamp card and unlock a reward.')) : 'Add visits to your stamp card and unlock a reward.';
$rules = $campaign ? mg_stamp_card_page_rules($campaign) : [];
$requiredCount = max(1, min(100, (int)($rules['required_count'] ?? $rules['stamp_required_count'] ?? 5)));
$stampLabel = trim((string)($rules['stamp_label'] ?? 'Visit')) ?: 'Visit';
$rewardTitle = $campaign ? (trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward') : 'Microgifter reward';
$rewardValue = $campaign ? mg_stamp_card_page_value($campaign) : 'Reward';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-public-campaign mg-public-campaign-v2" data-public-campaign-page>
  <div class="mg-public-campaign-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(6,15,32,.08),rgba(248,247,242,.92) 82%,#fbfaf6),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-public-campaign-shell">
    <div class="mg-public-campaign-heading">
      <span class="mg-public-campaign-kicker">Microgifter Campaign</span>
      <span class="mg-public-campaign-eyebrow">Stamp Card / Visit Tracker</span>
      <h1><?= mg_e($headline) ?></h1>
      <p><?= mg_e($description) ?></p>
      <div class="mg-public-campaign-trust-row"><span><?= mg_e((string)$requiredCount) ?> stamps to unlock</span><span>Unique card per customer</span><span>Reward sent to Inbox</span></div>
    </div>
    <aside class="mg-public-campaign-card mg-public-campaign-flow-card">
      <?php if ($errorMessage !== ''): ?>
        <div class="mg-public-campaign-result is-visible"><strong><?= mg_e($errorMessage) ?></strong></div>
      <?php else: ?>
        <form class="mg-public-campaign-form" data-campaign-form data-stamp-card-form data-submit-endpoint="/api/public/campaigns/stamp-card.php" data-campaign-type="stamp_card_reward" data-stamp-required-count="<?= mg_e((string)$requiredCount) ?>" data-stamp-label="<?= mg_e($stampLabel) ?>" novalidate>
          <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
          <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
          <input type="hidden" name="campaign_type" value="stamp_card_reward">
          <div class="mg-public-campaign-profile-card mg-public-campaign-form-profile"><div class="mg-public-campaign-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_stamp_card_page_initials($merchantName)) ?></span><?php endif; ?></div><div class="mg-public-campaign-profile-copy"><span class="mg-public-campaign-eyebrow">Stamp Card</span><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?><div class="mg-public-campaign-profile-stats"><?php if ($merchantLocation !== ''): ?><span><?= mg_e($merchantLocation) ?></span><?php endif; ?><span><?= mg_e($stampLabel) ?> stamps</span><span>Inbox unlock</span></div></div><?php if ($merchantProfileUrl): ?><div class="mg-public-campaign-profile-actions"><a class="mg-btn mg-btn-soft" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a></div><?php endif; ?></div>
          <section class="mg-stamp-card-visual" data-stamp-card-visual aria-live="polite">
            <div class="mg-stamp-card-visual-head"><span>Your punch card</span><strong data-stamp-progress-copy>0 of <?= mg_e((string)$requiredCount) ?> <?= mg_e(strtolower($stampLabel)) ?> stamps</strong></div>
            <div class="mg-stamp-card-punch-grid" data-stamp-punch-grid>
              <?php for ($i = 1; $i <= $requiredCount; $i++): ?><span class="mg-stamp-punch" data-stamp-index="<?= mg_e((string)$i) ?>"><b><?= mg_e((string)$i) ?></b></span><?php endfor; ?>
            </div>
            <div class="mg-stamp-card-meter"><span data-stamp-meter-fill style="width:0%"></span></div>
            <p data-stamp-progress-message>Submit your email after each eligible visit. Your progress is tracked for this campaign.</p>
          </section>
          <div class="mg-public-campaign-step-grid" aria-label="How stamp card rewards work"><div class="mg-public-campaign-mini-step"><span>1</span><strong>Add a stamp</strong><small>Submit after each eligible visit or purchase.</small></div><div class="mg-public-campaign-mini-step"><span>2</span><strong>Track progress</strong><small>Microgifter keeps a card for this campaign and email/contact.</small></div><div class="mg-public-campaign-mini-step"><span>3</span><strong>Unlock reward</strong><small>When your card reaches <?= mg_e((string)$requiredCount) ?>, the reward goes to Inbox.</small></div></div>
          <div class="mg-public-campaign-reward mg-public-campaign-reward-tab"><span>Stamp goal</span><strong><?= mg_e((string)$requiredCount) ?> <?= mg_e(strtolower($stampLabel)) ?> stamps</strong><em><?= mg_e($rewardTitle) ?> · <?= mg_e($rewardValue) ?></em><div class="mg-public-campaign-reward-meta"><span>Progress tracked</span><span>Reward unlock checked</span></div></div>
          <div class="mg-public-campaign-field-grid"><label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label class="mg-public-campaign-field-wide">Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label></div>
          <button class="mg-btn mg-btn-primary mg-public-campaign-primary-action" type="submit" data-stamp-card-submit>Add stamp / check reward <span aria-hidden="true">→</span></button>
          <div class="mg-public-campaign-status" data-campaign-status data-stamp-card-status></div><p class="mg-public-campaign-privacy">Your stamp progress is recorded for this merchant campaign and used to determine reward eligibility.</p>
        </form>
        <div class="mg-public-campaign-result" data-campaign-result></div>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>