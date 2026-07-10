<?php
declare(strict_types=1);
require_once __DIR__ . '/campaign-types.php';

$mgCampaignExpectedType = $mgCampaignExpectedType ?? null;
$mgCampaignPageLabel = $mgCampaignPageLabel ?? 'Microgifter campaign';
$mgCampaignPageIntro = $mgCampaignPageIntro ?? 'Claim or join a merchant reward campaign powered by Microgifter.';
$mgCampaignPreviewMode = (bool)($mgCampaignPreviewMode ?? false);
$mgCampaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
$mgCampaignToken = trim((string)($_GET['token'] ?? $_GET['qr_token'] ?? ''));

function mg_public_campaign_type_label(string $type): string
{
    return mg_campaign_type_label($type);
}

function mg_public_campaign_endpoint(string $type): string
{
    $endpoint = mg_campaign_type_submit_endpoint($type);
    return $endpoint !== '' ? $endpoint : '/api/public/campaigns/engage.php';
}

function mg_public_campaign_submit_label(string $type): string
{
    return match ($type) {
        'newsletter_signup' => 'Join and claim reward',
        'contest_giveaway' => 'Enter contest',
        'qr_reward_drop' => 'Claim QR reward',
        'referral_reward' => 'Join referral campaign',
        'birthday_vip' => 'Join birthday rewards',
        'agent_offer' => 'Add offer interest',
        default => 'Submit',
    };
}

function mg_public_campaign_outcome_copy(string $type): array
{
    return match ($type) {
        'newsletter_signup' => ['Signup reward', 'Join the merchant list and receive your reward in Microgifter Inbox.'],
        'contest_giveaway' => ['Contest entry', 'Your entry is tracked and eligible rewards route through Microgifter Inbox.'],
        'qr_reward_drop' => ['QR claim', 'This QR reward is verified and managed through the Microgifter Inbox flow.'],
        'referral_reward' => ['Referral reward', 'Referral activity and rewards stay connected to the Inbox / PPPM system.'],
        'birthday_vip' => ['Birthday VIP', 'Join the VIP list and receive future merchant rewards in your Inbox.'],
        'agent_offer' => ['Offer interest', 'Your request is captured for merchant follow-up and reward routing.'],
        default => ['Campaign reward', 'Submit once and Microgifter routes eligible rewards into the Inbox flow.'],
    };
}

function mg_public_campaign_steps(string $type): array
{
    $verb = match ($type) {
        'contest_giveaway' => 'Enter',
        'qr_reward_drop' => 'Claim',
        'referral_reward' => 'Share',
        'birthday_vip' => 'Join',
        'agent_offer' => 'Request',
        default => 'Join',
    };
    return [
        ['title' => 'Add your info', 'copy' => 'Use the email you want tied to your Microgifter Inbox.'],
        ['title' => $verb . ' campaign', 'copy' => 'Microgifter records the campaign source and reward status.'],
        ['title' => 'Open Inbox', 'copy' => 'Eligible rewards continue through Inbox and PPPM tracking.'],
    ];
}

function mg_public_campaign_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}

function mg_public_campaign_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'MG';
    $parts = preg_split('/\s+/u', $name) ?: [];
    return mb_strtoupper(mb_substr((string)($parts[0] ?? 'M'), 0, 1) . (count($parts) > 1 ? mb_substr((string)$parts[count($parts) - 1], 0, 1) : ''));
}

function mg_public_campaign_reward_metadata(array $campaign): array
{
    $json = (string)($campaign['reward_metadata_json'] ?? '');
    $decoded = $json !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_public_campaign_reward_cover(array $campaign): ?string
{
    $metadata = mg_public_campaign_reward_metadata($campaign);
    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    return mg_public_campaign_safe_url($pack['cover_image_url'] ?? null);
}

function mg_public_campaign_value(array $campaign): string
{
    $type = (string)($campaign['value_type'] ?? '');
    $rewardType = (string)($campaign['reward_type'] ?? '');
    if (in_array($rewardType, ['audio_pack','media_pack'], true)) return $rewardType === 'audio_pack' ? 'Audio pack' : 'Media pack';
    if ($type === 'percent' && ($campaign['value_percent'] ?? null) !== null) return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '% reward';
    if (in_array($type, ['free_item', 'custom'], true) || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) return (string)($campaign['reward_template_title'] ?? 'Reward');
    $cents = (int)($campaign['value_amount_cents'] ?? 0);
    return $cents > 0 ? ((string)($campaign['currency'] ?? 'USD') . ' ' . number_format($cents / 100, 2) . ' value') : 'Reward';
}

function mg_public_campaign_preview_user_id(): ?int
{
    if (!function_exists('mg_current_user') || !function_exists('mg_has_permission')) return null;
    if (!mg_has_permission('merchant.campaigns.view')) return null;
    $user = mg_current_user();
    $id = (int)($user['id'] ?? 0);
    return $id > 0 ? $id : null;
}

function mg_public_campaign_load(?string $expectedType, string $campaignRef, string $token, bool $previewMode = false): ?array
{
    if ($campaignRef === '' && $token === '') return null;
    $pdo = mg_db();
    $previewUserId = $previewMode ? mg_public_campaign_preview_user_id() : null;
    if ($previewMode && !$previewUserId) return null;

    $sql = "SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name,
                   pp.public_id merchant_profile_public_id, pp.slug merchant_profile_slug, pp.display_name merchant_profile_display_name,
                   pp.headline merchant_profile_headline, pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url,
                   pp.location_label merchant_profile_location,
                   rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description,
                   rt.reward_type, rt.value_type, rt.value_amount_cents, rt.value_percent, rt.currency, rt.redemption_instructions,
                   rt.expiration_rule, rt.expiration_days, rt.expires_at reward_expires_at, rt.metadata_json reward_metadata_json
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
            LEFT JOIN users u ON u.id = c.merchant_user_id
            LEFT JOIN public_profiles pp ON pp.user_id = c.merchant_user_id AND pp.status = 'active' AND pp.visibility IN ('public','unlisted')
            WHERE ((? <> '' AND (c.public_id = ? OR c.public_slug = ?)) OR (? <> '' AND c.qr_code_token = ?))";
    $params = [$campaignRef, $campaignRef, $campaignRef, $token, $token];
    if ($previewMode) {
        $sql .= ' AND c.merchant_user_id = ?';
        $params[] = $previewUserId;
    } else {
        $sql .= " AND c.status = 'active'";
    }
    if ($expectedType !== null && $expectedType !== '') { $sql .= ' AND c.campaign_type = ?'; $params[] = $expectedType; }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    return $campaign ?: null;
}

function mg_public_campaign_unavailable(string $label, string $intro): void
{
    ?>
    <section class="mg-rl-page mg-rl-simple-campaign"><div class="mg-rl-bg"></div><div class="mg-rl-wrap"><div class="mg-rl-left"><article class="mg-rl-card"><span class="mg-rl-eyebrow"><?= mg_e($label) ?></span><h2>Campaign not available</h2><p><?= mg_e($intro) ?></p><p>Use the campaign link or QR code from the merchant to open the correct page.</p><a class="mg-rl-btn mg-rl-btn-soft" href="/discover.php">Explore Microgifter</a></article></div></div></section>
    <?php
}

function mg_public_campaign_render_join_form(array $ctx): void
{
    $campaignType = (string)$ctx['campaign_type'];
    $mgCampaign = $ctx['campaign'];
    $isPreview = (bool)$ctx['preview'];
    $isClosed = (bool)$ctx['closed'];
    $closedMessage = (string)$ctx['closed_message'];
    $merchantName = (string)$ctx['merchant_name'];
    $merchantHeadline = (string)$ctx['merchant_headline'];
    $merchantLocation = (string)$ctx['merchant_location'];
    $merchantProfileUrl = $ctx['merchant_profile_url'];
    $avatarUrl = $ctx['avatar_url'];
    $typeLabel = (string)$ctx['type_label'];
    $outcomeTitle = (string)$ctx['outcome_title'];
    $submitEndpoint = (string)$ctx['submit_endpoint'];
    $submitLabel = (string)$ctx['submit_label'];
    $prefillName = (string)$ctx['prefill_name'];
    $prefillEmail = (string)$ctx['prefill_email'];
    $campaignToken = (string)$ctx['campaign_token'];
    ?>
    <div class="mg-rl-profile"><div class="mg-rl-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_public_campaign_initials($merchantName)) ?></span><?php endif; ?></div><div><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?><?php if ($merchantLocation !== ''): ?><p><?= mg_e($merchantLocation) ?></p><?php endif; ?><?php if ($merchantProfileUrl): ?><a class="mg-rl-btn mg-rl-btn-soft mg-rl-profile-link" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a><?php endif; ?></div></div>
    <?php if ($isClosed): ?>
      <div class="mg-public-campaign-result is-visible"><strong><?= mg_e($closedMessage) ?></strong></div>
    <?php else: ?>
      <form class="mg-rl-form" data-campaign-form data-submit-endpoint="<?= mg_e($submitEndpoint) ?>" data-campaign-type="<?= mg_e($campaignType) ?>"<?= $isPreview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)$mgCampaign['public_id']) ?>"><input type="hidden" name="campaign" value="<?= mg_e((string)($mgCampaign['public_slug'] ?? $mgCampaign['public_id'])) ?>"><input type="hidden" name="campaign_type" value="<?= mg_e($campaignType) ?>"><?php if ($campaignType === 'qr_reward_drop'): ?><input type="hidden" name="qr_token" value="<?= mg_e($campaignToken !== '' ? $campaignToken : (string)($mgCampaign['qr_code_token'] ?? '')) ?>"><?php endif; ?>
        <h3><?= mg_e($typeLabel) ?></h3><p><?= mg_e($outcomeTitle) ?> · delivered through Microgifter Inbox.</p>
        <label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label>Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label>
        <?php if ($campaignType === 'contest_giveaway'): ?><label>Entry note <span>(optional)</span><textarea name="entry_note" placeholder="Optional note for this contest"></textarea></label><?php endif; ?><?php if ($campaignType === 'referral_reward'): ?><label>Referral note <span>(optional)</span><textarea name="entry_note" placeholder="Who referred you or who should we contact?"></textarea></label><?php endif; ?><?php if ($campaignType === 'birthday_vip'): ?><label>Birthday month<input name="entry_note" placeholder="Example: March"></label><?php endif; ?><?php if ($campaignType === 'agent_offer'): ?><label>What are you looking for?<textarea name="entry_note" placeholder="Tell the merchant what kind of reward or offer interests you."></textarea></label><?php endif; ?>
        <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $isPreview ? 'button' : 'submit' ?>"<?= $isPreview ? ' disabled aria-disabled="true"' : '' ?>><?= mg_e($isPreview ? 'Preview only - activate to publish' : $submitLabel) ?></button><div class="mg-public-campaign-status" data-campaign-status><?= $isPreview ? 'Preview mode: customer submissions are disabled.' : '' ?></div><p class="mg-public-campaign-privacy">We respect your privacy. Unsubscribe anytime.</p>
      </form><div class="mg-public-campaign-result" data-campaign-result></div>
    <?php endif;
}

try { $mgCampaign = mg_public_campaign_load($mgCampaignExpectedType, $mgCampaignRef, $mgCampaignToken, $mgCampaignPreviewMode); }
catch (Throwable $error) { mg_security_log('warning', 'public.campaign_page.unavailable', 'Unable to load public campaign page.', ['exception_class' => $error::class]); $mgCampaign = null; }

if (!$mgCampaign || !mg_campaign_type_public_enabled((string)($mgCampaign['campaign_type'] ?? ''))) { mg_public_campaign_unavailable((string)$mgCampaignPageLabel, (string)$mgCampaignPageIntro); return; }

$campaignType = (string)$mgCampaign['campaign_type'];
$typeLabel = mg_public_campaign_type_label($campaignType);
[$outcomeTitle, $outcomeCopy] = mg_public_campaign_outcome_copy($campaignType);
$campaignSteps = mg_public_campaign_steps($campaignType);
$headline = trim((string)($mgCampaign['form_headline'] ?? '')) ?: (string)$mgCampaign['title'];
$description = trim((string)($mgCampaign['form_description'] ?? '')) ?: (trim((string)($mgCampaign['description'] ?? '')) ?: 'Enter your information to engage with this Microgifter campaign.');
$rewardTitle = trim((string)($mgCampaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardDescription = trim((string)($mgCampaign['reward_template_description'] ?? '')) ?: trim((string)($mgCampaign['description'] ?? ''));
$rewardValue = mg_public_campaign_value($mgCampaign);
$rewardCoverUrl = mg_public_campaign_reward_cover($mgCampaign);
$submitEndpoint = mg_public_campaign_endpoint($campaignType);
$submitLabel = mg_public_campaign_submit_label($campaignType);
$merchantName = trim((string)($mgCampaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($mgCampaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($mgCampaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
$merchantHeadline = trim((string)($mgCampaign['merchant_profile_headline'] ?? ''));
$merchantLocation = trim((string)($mgCampaign['merchant_profile_location'] ?? ''));
$merchantProfileSlug = trim((string)($mgCampaign['merchant_profile_slug'] ?? ''));
$merchantProfileUrl = $merchantProfileSlug !== '' ? '/profile.php?slug=' . rawurlencode($merchantProfileSlug) : null;
$coverUrl = mg_public_campaign_safe_url($mgCampaign['merchant_profile_cover_url'] ?? null);
$avatarUrl = mg_public_campaign_safe_url($mgCampaign['merchant_profile_avatar_url'] ?? null);
$currentUser = function_exists('mg_current_user') ? mg_current_user() : null;
$prefillName = is_array($currentUser) ? trim((string)($currentUser['display_name'] ?? $currentUser['full_name'] ?? '')) : '';
$prefillEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';
$now = time();
$isClosed = false;
$closedMessage = '';
if (!$mgCampaignPreviewMode) {
    if (!empty($mgCampaign['starts_at']) && strtotime((string)$mgCampaign['starts_at']) > $now) { $isClosed = true; $closedMessage = 'This campaign has not started yet.'; }
    if (!empty($mgCampaign['ends_at']) && strtotime((string)$mgCampaign['ends_at']) < $now) { $isClosed = true; $closedMessage = 'This campaign has ended.'; }
    if (($mgCampaign['quantity_limit'] ?? null) !== null && (int)($mgCampaign['issued_count'] ?? 0) >= (int)$mgCampaign['quantity_limit']) { $isClosed = true; $closedMessage = 'This campaign reward limit has been reached.'; }
}
$statusLabel = strtoupper(str_replace('_', ' ', (string)($mgCampaign['status'] ?? 'draft')));
$activeStatus = $isClosed ? $closedMessage : ($mgCampaignPreviewMode ? 'Merchant preview' : 'Active and ready');
$joinContext = ['campaign_type' => $campaignType, 'campaign' => $mgCampaign, 'preview' => $mgCampaignPreviewMode, 'closed' => $isClosed, 'closed_message' => $closedMessage, 'merchant_name' => $merchantName, 'merchant_headline' => $merchantHeadline, 'merchant_location' => $merchantLocation, 'merchant_profile_url' => $merchantProfileUrl, 'avatar_url' => $avatarUrl, 'type_label' => $typeLabel, 'outcome_title' => $outcomeTitle, 'submit_endpoint' => $submitEndpoint, 'submit_label' => $submitLabel, 'prefill_name' => $prefillName, 'prefill_email' => $prefillEmail, 'campaign_token' => $mgCampaignToken];
$campaignClass = 'mg-rl-simple-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($campaignType));
?>
<section class="mg-rl-page mg-rl-simple-campaign <?= mg_e($campaignClass) ?><?= $mgCampaignPreviewMode ? ' is-merchant-preview' : '' ?>" data-public-campaign-page<?= $mgCampaignPreviewMode ? ' data-merchant-campaign-preview' : '' ?>>
  <div class="mg-rl-bg"<?= $coverUrl ? ' style="background-image:url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <?php if ($mgCampaignPreviewMode): ?><article class="mg-rl-card mg-rl-preview-banner"><span class="mg-rl-eyebrow">Merchant preview</span><h3><?= mg_e($statusLabel) ?></h3><p>This draft is only visible to the merchant owner. Customer submissions are disabled until the campaign is active.</p><a class="mg-rl-btn mg-rl-btn-soft" href="/merchant-ad-manager.php">Open campaign manager</a></article><?php endif; ?>
      <header class="mg-rl-hero"><h1><?= mg_e($headline) ?></h1><p><?= mg_e($description) ?></p><div class="mg-public-campaign-trust-row"><span><?= mg_e($typeLabel) ?></span><span>Reward sent to Inbox</span><span>PPPM tracked</span></div></header>
      <section class="mg-rl-player mg-rl-simple-reward-canvas" aria-label="<?= mg_e($typeLabel) ?> reward canvas"><div class="mg-rl-track"><div class="mg-rl-art"><?php if ($rewardCoverUrl): ?><img src="<?= mg_e($rewardCoverUrl) ?>" alt="<?= mg_e($rewardTitle) ?> cover image"><?php else: ?><div class="mg-rl-art-placeholder">Reward</div><?php endif; ?><span><?= mg_e($outcomeTitle) ?></span></div><div class="mg-rl-track-copy"><small>Attached reward</small><strong><?= mg_e($rewardTitle) ?></strong><em><?= mg_e($rewardValue) ?></em><?php if ($rewardDescription !== ''): ?><p class="mg-rl-reward-copy"><?= mg_e($rewardDescription) ?></p><?php endif; ?><ul class="mg-rl-list mg-rl-simple-steps"><?php foreach ($campaignSteps as $step): ?><li><strong><?= mg_e((string)$step['title']) ?></strong><?= mg_e((string)$step['copy']) ?></li><?php endforeach; ?></ul></div></div></section>
      <aside class="mg-rl-join mg-rl-join-mobile"><?php mg_public_campaign_render_join_form($joinContext); ?></aside>
      <div class="mg-rl-bottom"><article class="mg-rl-card"><span class="mg-rl-eyebrow">Reward Info</span><h3><?= mg_e($rewardTitle) ?></h3><p><?= mg_e($rewardValue) ?></p><?php if (!empty($mgCampaign['redemption_instructions'])): ?><p><?= mg_e((string)$mgCampaign['redemption_instructions']) ?></p><?php endif; ?><?php if (!empty($mgCampaign['ends_at'])): ?><span class="mg-rl-pill">Ends <?= mg_e(date('M j, Y', strtotime((string)$mgCampaign['ends_at']))) ?></span><?php endif; ?></article><article class="mg-rl-card"><span class="mg-rl-eyebrow">Reward Levels</span><h3><?= mg_e($outcomeTitle) ?></h3><ul class="mg-rl-list"><li><strong>Level 1</strong><?= mg_e($outcomeCopy) ?></li><li><strong>Inbox delivery</strong>Eligible rewards are issued into the customer Microgifter Inbox.</li><li><strong>PPPM tracked</strong>Campaign source and reward lifecycle stay connected.</li></ul></article><article class="mg-rl-card"><span class="mg-rl-eyebrow">Active Status &amp; Updates</span><h3><?= mg_e($activeStatus) ?></h3><ul class="mg-rl-list"><li><strong>Campaign status</strong><?= mg_e($statusLabel) ?></li><li><strong>Updates</strong>Submit the form to record the campaign action and show reward status.</li><li><strong>CRM rule</strong>First issued reward or purchased value creates/promotes the merchant CRM contact.</li></ul></article></div>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop"><?php mg_public_campaign_render_join_form($joinContext); ?></aside>
  </div>
</section>
