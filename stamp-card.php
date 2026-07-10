<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-types.php';

$page_title = 'Stamp Card Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/public-campaign-experience-v1.css',
    '/assets/css/loyalty-cards.css',
];
$page_scripts = [
    '/assets/js/public-campaign.js',
    '/assets/js/public-stamp-card.js',
    '/assets/js/loyalty-cards.js',
];

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
    return is_array($parts)
        && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        && !empty($parts['host'])
        && !isset($parts['user'], $parts['pass'])
        ? $url
        : null;
}

function mg_stamp_card_page_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'MG';
    $parts = preg_split('/\s+/u', $name) ?: [];
    return mb_strtoupper(
        mb_substr((string)($parts[0] ?? 'M'), 0, 1)
        . (count($parts) > 1 ? mb_substr((string)$parts[count($parts) - 1], 0, 1) : '')
    );
}

function mg_stamp_card_page_value(array $campaign): string
{
    $type = (string)($campaign['value_type'] ?? '');
    $rewardType = (string)($campaign['reward_type'] ?? '');
    if ($type === 'percent' && ($campaign['value_percent'] ?? null) !== null) {
        return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '% reward';
    }
    if (in_array($type, ['free_item', 'custom'], true)
        || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) {
        return (string)($campaign['reward_template_title'] ?? 'Reward');
    }
    $cents = (int)($campaign['value_amount_cents'] ?? 0);
    return $cents > 0
        ? ((string)($campaign['currency'] ?? 'USD') . ' ' . number_format($cents / 100, 2) . ' value')
        : 'Reward';
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
                   pp.slug merchant_profile_slug, pp.display_name merchant_profile_display_name,
                   pp.headline merchant_profile_headline, pp.avatar_url merchant_profile_avatar_url,
                   pp.cover_url merchant_profile_cover_url, pp.location_label merchant_profile_location,
                   rt.public_id reward_template_public_id, rt.title reward_template_title,
                   rt.description reward_template_description, rt.reward_type, rt.value_type,
                   rt.value_amount_cents, rt.value_percent, rt.currency, rt.redemption_instructions,
                   rt.metadata_json reward_metadata_json
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
            LEFT JOIN users u ON u.id = c.merchant_user_id
            LEFT JOIN public_profiles pp ON pp.user_id = c.merchant_user_id
                AND pp.status = 'active'
                AND pp.visibility IN ('public','unlisted')
            WHERE c.status = 'active'
              AND c.campaign_type = 'stamp_card_reward'
              AND (c.public_id = ? OR c.public_slug = ?)
            LIMIT 1");
        $stmt->execute([$campaignRef, $campaignRef]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'public.stamp_card.unavailable', 'Unable to load stamp card campaign.', [
            'exception_class' => $error::class,
        ]);
    }
    $campaign = null;
}

if (!$campaign) $errorMessage = 'Use the stamp-card link from the merchant to open this reward.';
$now = time();
if ($campaign && !empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) {
    $errorMessage = 'This stamp-card campaign has not started yet.';
}
if ($campaign && !empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) {
    $errorMessage = 'This stamp-card campaign has ended.';
}
if ($campaign && ($campaign['quantity_limit'] ?? null) !== null
    && (int)($campaign['issued_count'] ?? 0) >= (int)$campaign['quantity_limit']) {
    $errorMessage = 'This stamp-card reward limit has been reached.';
}

$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$prefillName = is_array($currentUser)
    ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? ''))
    : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$merchantName = $campaign
    ? (trim((string)($campaign['merchant_profile_display_name'] ?? ''))
        ?: (trim((string)($campaign['merchant_user_display_name'] ?? ''))
            ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant')))
    : 'Microgifter merchant';
$merchantHeadline = $campaign ? trim((string)($campaign['merchant_profile_headline'] ?? '')) : '';
$merchantProfileSlug = $campaign ? trim((string)($campaign['merchant_profile_slug'] ?? '')) : '';
$merchantProfileUrl = $merchantProfileSlug !== ''
    ? '/profile.php?slug=' . rawurlencode($merchantProfileSlug)
    : null;
$coverUrl = $campaign ? mg_stamp_card_page_safe_url($campaign['merchant_profile_cover_url'] ?? null) : null;
$avatarUrl = $campaign ? mg_stamp_card_page_safe_url($campaign['merchant_profile_avatar_url'] ?? null) : null;
$headline = $campaign
    ? (trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title'])
    : 'Stamp Card Reward';
$description = $campaign
    ? (trim((string)($campaign['form_description'] ?? ''))
        ?: (trim((string)($campaign['description'] ?? '')) ?: 'Add verified visits to your stamp card and unlock a reward.'))
    : 'Add verified visits to your stamp card and unlock a reward.';
$rules = $campaign ? mg_stamp_card_page_rules($campaign) : [];
$requiredCount = max(1, min(100, (int)($rules['required_count'] ?? $rules['stamp_required_count'] ?? 5)));
$stampLabel = trim((string)($rules['stamp_label'] ?? 'Visit')) ?: 'Visit';
$cashierRequired = array_key_exists('cashier_verification_required', $rules)
    ? !empty($rules['cashier_verification_required'])
    : true;
$rewardTitle = $campaign
    ? (trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward')
    : 'Microgifter reward';
$rewardValue = $campaign ? mg_stamp_card_page_value($campaign) : 'Reward';
$columns = min(5, max(3, $requiredCount));

require __DIR__ . '/includes/header.php';
?>
<section class="mg-rl-page mg-rl-stamp" data-public-campaign-page data-stamp-card-experience>
  <div class="mg-rl-bg"<?= $coverUrl ? ' style="background-image:url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <?php if ($errorMessage !== ''): ?>
      <div class="mg-rl-left">
        <article class="mg-rl-card">
          <span class="mg-rl-eyebrow">Stamp Card</span>
          <h2>Campaign not available</h2>
          <p><?= mg_e($errorMessage) ?></p>
          <a class="mg-rl-btn mg-rl-btn-soft" href="/discover.php">Explore Microgifter</a>
        </article>
      </div>
    <?php else: ?>
      <div class="mg-rl-left">
        <header class="mg-rl-hero">
          <h1><?= mg_e($headline) ?></h1>
          <p><?= mg_e($description) ?></p>
          <div class="mg-public-campaign-trust-row">
            <span><?= mg_e((string)$requiredCount) ?> stamps to unlock</span>
            <span><?= $cashierRequired ? 'Cashier verified' : 'Self check-in' ?></span>
            <span>Reward sent to Inbox</span>
            <button
              class="mg-loyalty-save-toggle"
              type="button"
              data-loyalty-save-toggle
              data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>"
              data-saved="false"
              aria-pressed="false"
            ><span data-loyalty-save-icon aria-hidden="true">☆</span><strong data-loyalty-save-label>Save Card</strong></button>
          </div>
        </header>

        <section class="mg-rl-player" aria-label="Stamp card interaction canvas">
          <div class="mg-stamp-stage" data-stamp-stage data-required-count="<?= mg_e((string)$requiredCount) ?>" data-stamp-label="<?= mg_e($stampLabel) ?>">
            <div class="mg-stamp-card-visual" style="--stamp-columns:<?= mg_e((string)$columns) ?>;--stamp-progress:0%" data-stamp-card-visual>
              <div class="mg-stamp-card-header">
                <div><span>Current card</span><strong><span data-stamp-count>0</span> / <span data-stamp-required><?= mg_e((string)$requiredCount) ?></span></strong></div>
                <em class="mg-campaign-canvas-badge" data-stamp-remaining><?= mg_e((string)$requiredCount) ?> remaining</em>
              </div>
              <div class="mg-stamp-grid" data-stamp-grid>
                <?php for ($i = 1; $i <= $requiredCount; $i++): ?>
                  <span class="mg-stamp-slot" data-stamp-slot="<?= mg_e((string)$i) ?>"><?= mg_e((string)$i) ?></span>
                <?php endfor; ?>
              </div>
              <div class="mg-stamp-progress">
                <div class="mg-stamp-progress-top"><span>Verified progress</span><strong data-stamp-progress-copy>0% complete</strong></div>
                <div class="mg-stamp-bar"><span data-stamp-progress-bar></span></div>
              </div>
            </div>
          </div>
        </section>

        <aside class="mg-rl-join mg-rl-join-mobile">
          <div class="mg-rl-profile">
            <div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_stamp_card_page_initials($merchantName)) ?></span><?php endif; ?></div>
            <div><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?><?php if ($merchantProfileUrl): ?><a class="mg-rl-btn mg-rl-btn-soft" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a><?php endif; ?></div>
          </div>
          <form class="mg-rl-form" data-campaign-form data-campaign-keep-visible="1" data-stamp-card-form data-submit-endpoint="/api/public/campaigns/stamp-card.php" data-campaign-type="stamp_card_reward" novalidate>
            <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
            <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
            <input type="hidden" name="campaign_type" value="stamp_card_reward">
            <h3>Verify this stamp</h3>
            <p>Ask the cashier to enter the claim code before the stamp is added.</p>
            <label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label>
            <label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label>
            <label>Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label>
            <?php if ($cashierRequired): ?><label>Cashier claim code<input name="cashier_code" autocomplete="one-time-code" placeholder="Cashier enters code" maxlength="64" required></label><?php endif; ?>
            <button class="mg-rl-btn mg-rl-btn-dark" type="submit" data-stamp-card-submit>Add verified stamp</button>
            <div class="mg-public-campaign-status" data-campaign-status data-stamp-card-status></div>
          </form>
          <div class="mg-public-campaign-result" data-campaign-result></div>
        </aside>

        <div class="mg-rl-bottom">
          <article class="mg-rl-card"><span class="mg-rl-eyebrow">Reward</span><h3><?= mg_e($rewardTitle) ?></h3><p><?= mg_e($rewardValue) ?></p></article>
          <article class="mg-rl-card"><span class="mg-rl-eyebrow">Verification</span><h3><?= $cashierRequired ? 'Cashier code required' : 'Check-in enabled' ?></h3><p>Official stamps are traceable to the merchant/location verification context.</p></article>
          <article class="mg-rl-card"><span class="mg-rl-eyebrow">CRM Rule</span><h3>First reward creates CRM</h3><p>Partial stamps track campaign progress but do not create merchant CRM contacts by themselves.</p></article>
        </div>
      </div>

      <aside class="mg-rl-join mg-rl-join-desktop">
        <div class="mg-rl-profile">
          <div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_stamp_card_page_initials($merchantName)) ?></span><?php endif; ?></div>
          <div><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?><?php if ($merchantProfileUrl): ?><a class="mg-rl-btn mg-rl-btn-soft" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a><?php endif; ?></div>
        </div>
        <form class="mg-rl-form" data-campaign-form data-campaign-keep-visible="1" data-stamp-card-form data-submit-endpoint="/api/public/campaigns/stamp-card.php" data-campaign-type="stamp_card_reward" novalidate>
          <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
          <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
          <input type="hidden" name="campaign_type" value="stamp_card_reward">
          <h3>Verify this stamp</h3>
          <p>Your card progress is unique to your email for this campaign.</p>
          <label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label>
          <label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label>
          <label>Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label>
          <?php if ($cashierRequired): ?><label>Cashier claim code<input name="cashier_code" autocomplete="one-time-code" placeholder="Cashier enters code" maxlength="64" required></label><?php endif; ?>
          <button class="mg-rl-btn mg-rl-btn-dark" type="submit" data-stamp-card-submit>Add verified stamp</button>
          <div class="mg-public-campaign-status" data-campaign-status data-stamp-card-status></div>
          <p class="mg-public-campaign-privacy">Rewards unlock when verified progress reaches <?= mg_e((string)$requiredCount) ?>.</p>
        </form>
        <div class="mg-public-campaign-result" data-campaign-result></div>
      </aside>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>