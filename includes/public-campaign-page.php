<?php
declare(strict_types=1);

$mgCampaignExpectedType = $mgCampaignExpectedType ?? null;
$mgCampaignPageLabel = $mgCampaignPageLabel ?? 'Microgifter campaign';
$mgCampaignPageIntro = $mgCampaignPageIntro ?? 'Claim or join a merchant reward campaign powered by Microgifter.';
$mgCampaignPreviewMode = (bool)($mgCampaignPreviewMode ?? false);
$mgCampaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
$mgCampaignToken = trim((string)($_GET['token'] ?? $_GET['qr_token'] ?? ''));

function mg_public_campaign_type_label(string $type): string
{
    return match ($type) {
        'newsletter_signup' => 'Newsletter signup',
        'contest_giveaway' => 'Contest / giveaway',
        'qr_reward_drop' => 'QR reward drop',
        'referral_reward' => 'Referral reward',
        'birthday_vip' => 'Birthday / VIP reward',
        'agent_offer' => 'Agent offer',
        default => 'Campaign',
    };
}

function mg_public_campaign_endpoint(string $type): string
{
    return match ($type) {
        'newsletter_signup' => '/api/public/campaigns/signup.php',
        'contest_giveaway' => '/api/public/campaigns/contest-entry.php',
        'qr_reward_drop' => '/api/public/campaigns/qr-pickup.php',
        default => '/api/public/campaigns/engage.php',
    };
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
    <section class="mg-public-campaign mg-public-campaign-empty"><div class="mg-public-campaign-shell"><div class="mg-public-campaign-card"><span class="mg-public-campaign-eyebrow"><?= mg_e($label) ?></span><h1>Campaign not available</h1><p><?= mg_e($intro) ?></p><p class="mg-public-campaign-note">Use the campaign link or QR code from the merchant to open the correct page.</p><a class="mg-btn mg-btn-primary" href="/discover.php">Explore Microgifter</a></div></div></section>
    <?php
}

try { $mgCampaign = mg_public_campaign_load($mgCampaignExpectedType, $mgCampaignRef, $mgCampaignToken, $mgCampaignPreviewMode); }
catch (Throwable $error) { mg_security_log('warning', 'public.campaign_page.unavailable', 'Unable to load public campaign page.', ['exception_class' => $error::class]); $mgCampaign = null; }

if (!$mgCampaign) { mg_public_campaign_unavailable((string)$mgCampaignPageLabel, (string)$mgCampaignPageIntro); return; }

$campaignType = (string)$mgCampaign['campaign_type'];
$typeLabel = mg_public_campaign_type_label($campaignType);
$headline = trim((string)($mgCampaign['form_headline'] ?? '')) ?: (string)$mgCampaign['title'];
$description = trim((string)($mgCampaign['form_description'] ?? '')) ?: (trim((string)($mgCampaign['description'] ?? '')) ?: 'Enter your information below to engage with this Microgifter campaign.');
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
?>
<section class="mg-public-campaign mg-public-campaign-v2<?= $mgCampaignPreviewMode ? ' is-merchant-preview' : '' ?>" data-public-campaign-page<?= $mgCampaignPreviewMode ? ' data-merchant-campaign-preview' : '' ?>>
  <div class="mg-public-campaign-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(6,15,32,.08),rgba(248,247,242,.92) 82%,#fbfaf6),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
  <div class="mg-public-campaign-shell">
    <?php if ($mgCampaignPreviewMode): ?>
      <div class="mg-public-campaign-preview-banner"><span>Merchant preview</span><strong><?= mg_e($statusLabel) ?></strong><p>This draft is only visible to the merchant owner. Customer submissions are disabled until the campaign is active.</p><a class="mg-btn mg-btn-soft" href="/merchant-ad-manager.php">Open campaign manager</a></div>
    <?php endif; ?>
    <div class="mg-public-campaign-heading"><h1><?= mg_e($headline) ?></h1><p><?= mg_e($description) ?></p></div>
    <aside class="mg-public-campaign-card mg-public-campaign-flow-card">
      <?php if ($isClosed): ?>
        <div class="mg-public-campaign-profile-card mg-public-campaign-form-profile"><div class="mg-public-campaign-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_public_campaign_initials($merchantName)) ?></span><?php endif; ?></div><div class="mg-public-campaign-profile-copy"><span class="mg-public-campaign-eyebrow"><?= mg_e($typeLabel) ?></span><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?></div><?php if ($merchantProfileUrl): ?><div class="mg-public-campaign-profile-actions"><a class="mg-btn mg-btn-soft" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a></div><?php endif; ?></div>
        <div class="mg-public-campaign-result is-visible"><strong><?= mg_e($closedMessage) ?></strong></div>
      <?php else: ?>
        <form class="mg-public-campaign-form" data-campaign-form data-public-campaign-tabs data-submit-endpoint="<?= mg_e($submitEndpoint) ?>" data-campaign-type="<?= mg_e($campaignType) ?>"<?= $mgCampaignPreviewMode ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
          <input type="hidden" name="campaign_id" value="<?= mg_e((string)$mgCampaign['public_id']) ?>"><input type="hidden" name="campaign" value="<?= mg_e((string)($mgCampaign['public_slug'] ?? $mgCampaign['public_id'])) ?>"><input type="hidden" name="campaign_type" value="<?= mg_e($campaignType) ?>">
          <?php if ($campaignType === 'qr_reward_drop'): ?><input type="hidden" name="qr_token" value="<?= mg_e($mgCampaignToken !== '' ? $mgCampaignToken : (string)($mgCampaign['qr_code_token'] ?? '')) ?>"><?php endif; ?>
          <div class="mg-public-campaign-profile-card mg-public-campaign-form-profile"><div class="mg-public-campaign-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e(mg_public_campaign_initials($merchantName)) ?></span><?php endif; ?></div><div class="mg-public-campaign-profile-copy"><span class="mg-public-campaign-eyebrow"><?= mg_e($typeLabel) ?></span><h2><?= mg_e($merchantName) ?></h2><?php if ($merchantHeadline !== ''): ?><p><?= mg_e($merchantHeadline) ?></p><?php endif; ?><?php if ($merchantLocation !== ''): ?><div class="mg-public-campaign-profile-stats"><span><?= mg_e($merchantLocation) ?></span></div><?php endif; ?></div><?php if ($merchantProfileUrl): ?><div class="mg-public-campaign-profile-actions"><a class="mg-btn mg-btn-soft" href="<?= mg_e($merchantProfileUrl) ?>">View profile</a></div><?php endif; ?></div>
          <div class="mg-public-campaign-tabs" role="tablist" aria-label="<?= mg_e($typeLabel) ?> steps"><button type="button" class="mg-public-campaign-tab is-active" id="mg-campaign-tab-info" role="tab" aria-selected="true" aria-controls="mg-campaign-panel-info" data-campaign-tab="info"><span>1</span>Your Info</button><button type="button" class="mg-public-campaign-tab" id="mg-campaign-tab-reward" role="tab" aria-selected="false" aria-controls="mg-campaign-panel-reward" data-campaign-tab="reward"><span>2</span>Your Reward</button></div>
          <div class="mg-public-campaign-panel is-active" id="mg-campaign-panel-info" role="tabpanel" aria-labelledby="mg-campaign-tab-info" data-campaign-panel="info"><div class="mg-public-campaign-field-grid"><label>Name<input name="name" placeholder="Your name" maxlength="180" value="<?= mg_e($prefillName) ?>"></label><label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e($prefillEmail) ?>"></label><label class="mg-public-campaign-field-wide">Phone <span>(optional)</span><input name="phone" placeholder="Optional" maxlength="60"></label><?php if ($campaignType === 'contest_giveaway'): ?><label class="mg-public-campaign-field-wide">Entry note<textarea name="entry_note" placeholder="Optional note for this contest"></textarea></label><?php endif; ?><?php if ($campaignType === 'referral_reward'): ?><label class="mg-public-campaign-field-wide">Referral note<textarea name="entry_note" placeholder="Who referred you or who should we contact?"></textarea></label><?php endif; ?><?php if ($campaignType === 'birthday_vip'): ?><label class="mg-public-campaign-field-wide">Birthday month<input name="entry_note" placeholder="Example: March"></label><?php endif; ?><?php if ($campaignType === 'agent_offer'): ?><label class="mg-public-campaign-field-wide">What are you looking for?<textarea name="entry_note" placeholder="Tell the merchant what kind of reward or offer interests you."></textarea></label><?php endif; ?></div><button class="mg-btn mg-btn-primary mg-public-campaign-primary-action" type="button" data-campaign-next-tab="reward">Continue to reward <span aria-hidden="true">→</span></button></div>
          <div class="mg-public-campaign-panel" id="mg-campaign-panel-reward" role="tabpanel" aria-labelledby="mg-campaign-tab-reward" data-campaign-panel="reward" hidden><div class="mg-public-campaign-reward mg-public-campaign-reward-tab"><?php if ($rewardCoverUrl): ?><img class="mg-public-campaign-reward-cover" src="<?= mg_e($rewardCoverUrl) ?>" alt="<?= mg_e($rewardTitle) ?> cover image"><?php endif; ?><span>Attached reward</span><strong><?= mg_e($rewardTitle) ?></strong><em><?= mg_e($rewardValue) ?></em><?php if (!$rewardCoverUrl && $rewardDescription !== ''): ?><p><?= mg_e($rewardDescription) ?></p><?php endif; ?><?php if (!$rewardCoverUrl && !empty($mgCampaign['redemption_instructions'])): ?><small><?= mg_e((string)$mgCampaign['redemption_instructions']) ?></small><?php endif; ?><?php if (!empty($mgCampaign['ends_at'])): ?><small>Ends <?= mg_e(date('M j, Y', strtotime((string)$mgCampaign['ends_at']))) ?></small><?php endif; ?></div><button class="mg-btn mg-btn-primary mg-public-campaign-primary-action" type="<?= $mgCampaignPreviewMode ? 'button' : 'submit' ?>"<?= $mgCampaignPreviewMode ? ' disabled aria-disabled="true"' : '' ?>><?= mg_e($mgCampaignPreviewMode ? 'Preview only - activate to publish' : $submitLabel) ?> <span aria-hidden="true">→</span></button><button class="mg-public-campaign-back" type="button" data-campaign-next-tab="info">Back to info</button></div>
          <div class="mg-public-campaign-status" data-campaign-status><?= $mgCampaignPreviewMode ? 'Preview mode: customer submissions are disabled.' : '' ?></div><p class="mg-public-campaign-privacy">We respect your privacy. Unsubscribe anytime.</p>
        </form>
        <div class="mg-public-campaign-result" data-campaign-result></div>
      <?php endif; ?>
    </aside>
  </div>
</section>
