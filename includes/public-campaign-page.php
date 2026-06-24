<?php
declare(strict_types=1);

$mgCampaignExpectedType = $mgCampaignExpectedType ?? null;
$mgCampaignPageLabel = $mgCampaignPageLabel ?? 'Microgifter campaign';
$mgCampaignPageIntro = $mgCampaignPageIntro ?? 'Claim or join a merchant reward campaign powered by Microgifter.';
$mgCampaignRef = strtolower(trim((string) ($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
$mgCampaignToken = trim((string) ($_GET['token'] ?? $_GET['qr_token'] ?? ''));

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
        'referral_reward', 'birthday_vip', 'agent_offer' => '/api/public/campaigns/engage.php',
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

function mg_public_campaign_value(array $campaign): string
{
    $type = (string) ($campaign['value_type'] ?? '');
    $rewardType = (string) ($campaign['reward_type'] ?? '');
    if ($type === 'percent' && $campaign['value_percent'] !== null) {
        return rtrim(rtrim(number_format((float) $campaign['value_percent'], 2), '0'), '.') . '% reward';
    }
    if (in_array($type, ['free_item', 'custom'], true) || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) {
        return (string) ($campaign['reward_template_title'] ?? 'Reward');
    }
    $cents = (int) ($campaign['value_amount_cents'] ?? 0);
    if ($cents <= 0) return 'Reward';
    $currency = (string) ($campaign['currency'] ?? 'USD');
    return $currency . ' ' . number_format($cents / 100, 2) . ' value';
}

function mg_public_campaign_load(?string $expectedType, string $campaignRef, string $token): ?array
{
    if ($campaignRef === '' && $token === '') return null;
    $pdo = mg_db();
    $sql = 'SELECT c.*, u.display_name merchant_label,
                   rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description,
                   rt.reward_type, rt.value_type, rt.value_amount_cents, rt.value_percent, rt.currency, rt.redemption_instructions,
                   rt.expiration_rule, rt.expiration_days, rt.expires_at reward_expires_at
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
            LEFT JOIN users u ON u.id = c.merchant_user_id
            WHERE c.status = \'active\'
              AND ((? <> \'\' AND (c.public_id = ? OR c.public_slug = ?)) OR (? <> \'\' AND c.qr_code_token = ?))';
    $params = [$campaignRef, $campaignRef, $campaignRef, $token, $token];
    if ($expectedType !== null && $expectedType !== '') {
        $sql .= ' AND c.campaign_type = ?';
        $params[] = $expectedType;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    return $campaign ?: null;
}

function mg_public_campaign_unavailable(string $label, string $intro): void
{
    ?>
    <section class="mg-public-campaign mg-public-campaign-empty">
      <div class="mg-public-campaign-shell">
        <div class="mg-public-campaign-card">
          <span class="mg-public-campaign-eyebrow"><?= mg_e($label) ?></span>
          <h1>Campaign not available</h1>
          <p><?= mg_e($intro) ?></p>
          <p class="mg-public-campaign-note">Use the campaign link or QR code from the merchant to open the correct page.</p>
          <a class="mg-btn mg-btn-primary" href="/discover.php">Explore Microgifter</a>
        </div>
      </div>
    </section>
    <?php
}

try {
    $mgCampaign = mg_public_campaign_load($mgCampaignExpectedType, $mgCampaignRef, $mgCampaignToken);
} catch (Throwable $error) {
    mg_security_log('warning', 'public.campaign_page.unavailable', 'Unable to load public campaign page.', ['exception_class' => $error::class]);
    $mgCampaign = null;
}

if (!$mgCampaign) {
    mg_public_campaign_unavailable((string) $mgCampaignPageLabel, (string) $mgCampaignPageIntro);
    return;
}

$campaignType = (string) $mgCampaign['campaign_type'];
$typeLabel = mg_public_campaign_type_label($campaignType);
$headline = trim((string) ($mgCampaign['form_headline'] ?? '')) ?: (string) $mgCampaign['title'];
$description = trim((string) ($mgCampaign['form_description'] ?? '')) ?: (trim((string) ($mgCampaign['description'] ?? '')) ?: 'Enter your information below to engage with this Microgifter campaign.');
$rewardTitle = trim((string) ($mgCampaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardDescription = trim((string) ($mgCampaign['reward_template_description'] ?? '')) ?: trim((string) ($mgCampaign['description'] ?? ''));
$rewardValue = mg_public_campaign_value($mgCampaign);
$submitEndpoint = mg_public_campaign_endpoint($campaignType);
$submitLabel = mg_public_campaign_submit_label($campaignType);
$now = time();
$isClosed = false;
$closedMessage = '';
if (!empty($mgCampaign['starts_at']) && strtotime((string) $mgCampaign['starts_at']) > $now) {
    $isClosed = true;
    $closedMessage = 'This campaign has not started yet.';
}
if (!empty($mgCampaign['ends_at']) && strtotime((string) $mgCampaign['ends_at']) < $now) {
    $isClosed = true;
    $closedMessage = 'This campaign has ended.';
}
if ($mgCampaign['quantity_limit'] !== null && (int) $mgCampaign['issued_count'] >= (int) $mgCampaign['quantity_limit']) {
    $isClosed = true;
    $closedMessage = 'This campaign reward limit has been reached.';
}
?>
<section class="mg-public-campaign" data-public-campaign-page>
  <div class="mg-public-campaign-shell">
    <div class="mg-public-campaign-hero">
      <span class="mg-public-campaign-eyebrow"><?= mg_e($typeLabel) ?></span>
      <h1><?= mg_e($headline) ?></h1>
      <p><?= mg_e($description) ?></p>
      <div class="mg-public-campaign-meta">
        <span><?= mg_e((string) ($mgCampaign['merchant_label'] ?? 'Microgifter merchant')) ?></span>
        <span><?= mg_e($rewardValue) ?></span>
        <?php if (!empty($mgCampaign['ends_at'])): ?><span>Ends <?= mg_e(date('M j, Y', strtotime((string) $mgCampaign['ends_at']))) ?></span><?php endif; ?>
      </div>
    </div>
    <aside class="mg-public-campaign-card">
      <div class="mg-public-campaign-reward">
        <span>Reward</span>
        <strong><?= mg_e($rewardTitle) ?></strong>
        <?php if ($rewardDescription !== ''): ?><p><?= mg_e($rewardDescription) ?></p><?php endif; ?>
        <?php if (!empty($mgCampaign['redemption_instructions'])): ?><small><?= mg_e((string) $mgCampaign['redemption_instructions']) ?></small><?php endif; ?>
      </div>
      <?php if ($isClosed): ?>
        <div class="mg-public-campaign-result is-visible"><strong><?= mg_e($closedMessage) ?></strong></div>
      <?php else: ?>
        <form class="mg-public-campaign-form" data-campaign-form data-submit-endpoint="<?= mg_e($submitEndpoint) ?>" data-campaign-type="<?= mg_e($campaignType) ?>">
          <input type="hidden" name="campaign_id" value="<?= mg_e((string) $mgCampaign['public_id']) ?>">
          <input type="hidden" name="campaign" value="<?= mg_e((string) ($mgCampaign['public_slug'] ?? $mgCampaign['public_id'])) ?>">
          <?php if ($campaignType === 'qr_reward_drop'): ?><input type="hidden" name="qr_token" value="<?= mg_e($mgCampaignToken !== '' ? $mgCampaignToken : (string) ($mgCampaign['qr_code_token'] ?? '')) ?>"><?php endif; ?>
          <label>Name<input name="name" placeholder="Your name" maxlength="180"></label>
          <label>Email<input name="email" type="email" placeholder="you@example.com" required maxlength="255"></label>
          <label>Phone<input name="phone" placeholder="Optional" maxlength="60"></label>
          <?php if ($campaignType === 'contest_giveaway'): ?>
            <label>Entry note<textarea name="entry_note" placeholder="Optional note for this contest"></textarea></label>
          <?php elseif ($campaignType === 'referral_reward'): ?>
            <label>Referral note<textarea name="entry_note" placeholder="Who referred you or who should we contact?"></textarea></label>
          <?php elseif ($campaignType === 'birthday_vip'): ?>
            <label>Birthday month<input name="entry_note" placeholder="Example: March"></label>
          <?php elseif ($campaignType === 'agent_offer'): ?>
            <label>What are you looking for?<textarea name="entry_note" placeholder="Tell the merchant what kind of reward or offer interests you."></textarea></label>
          <?php endif; ?>
          <div class="mg-public-campaign-status" data-campaign-status></div>
          <button class="mg-btn mg-btn-primary" type="submit"><?= mg_e($submitLabel) ?></button>
        </form>
        <div class="mg-public-campaign-result" data-campaign-result></div>
      <?php endif; ?>
    </aside>
  </div>
</section>
