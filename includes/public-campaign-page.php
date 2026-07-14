<?php
declare(strict_types=1);

require_once __DIR__ . '/campaign-landing-foundation.php';
require_once __DIR__ . '/campaign-user-details.php';

$mgCampaignExpectedType = $mgCampaignExpectedType ?? null;
$mgCampaignPageLabel = $mgCampaignPageLabel ?? 'Microgifter campaign';
$mgCampaignPageIntro = $mgCampaignPageIntro ?? 'Claim or join a merchant reward campaign powered by Microgifter.';
$mgCampaignPreviewMode = (bool)($mgCampaignPreviewMode ?? mg_campaign_landing_preview_requested());
$mgCampaignRef = isset($mgCampaignRef) ? strtolower(trim((string)$mgCampaignRef)) : mg_campaign_landing_request_ref();
$mgCampaignToken = isset($mgCampaignToken) ? trim((string)$mgCampaignToken) : mg_campaign_landing_request_token();

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

/* Compatibility wrappers for older campaign page includes. */
function mg_public_campaign_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    return mg_campaign_landing_safe_url($value, $allowRelative);
}

function mg_public_campaign_initials(string $name): string
{
    return mg_campaign_landing_initials($name);
}

function mg_public_campaign_reward_metadata(array $campaign): array
{
    return mg_campaign_landing_reward_metadata($campaign);
}

function mg_public_campaign_reward_cover(array $campaign): ?string
{
    return mg_campaign_landing_reward_cover($campaign);
}

function mg_public_campaign_value(array $campaign): string
{
    return mg_campaign_landing_value($campaign);
}

function mg_public_campaign_preview_user_id(): ?int
{
    return mg_campaign_landing_preview_user_id();
}

function mg_public_campaign_load(?string $expectedType, string $campaignRef, string $token, bool $previewMode = false): ?array
{
    return mg_campaign_landing_load($expectedType, $campaignRef, $token, $previewMode);
}

function mg_public_campaign_unavailable(string $label, string $intro): void
{
    mg_campaign_landing_render_unavailable($label, $intro);
}

function mg_public_campaign_render_join_form(array $context): void
{
    $campaignType = (string)$context['campaign_type'];
    $campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : [];
    $preview = (bool)($context['preview'] ?? false);
    $state = is_array($context['state'] ?? null) ? $context['state'] : [];
    $closed = (bool)($state['closed'] ?? false);
    $profile = is_array($context['profile'] ?? null) ? $context['profile'] : [];
    $typeLabel = (string)$context['type_label'];
    $outcomeTitle = (string)$context['outcome_title'];
    $submitEndpoint = (string)$context['submit_endpoint'];
    $submitLabel = (string)$context['submit_label'];
    $prefill = is_array($context['prefill'] ?? null) ? $context['prefill'] : [];
    $campaignToken = (string)($context['campaign_token'] ?? '');

    mg_campaign_landing_render_profile($profile);

    if ($closed): ?>
      <div class="mg-public-campaign-result is-visible" data-campaign-closed-state="<?= mg_e((string)($state['code'] ?? 'closed')) ?>">
        <strong><?= mg_e((string)($state['message'] ?? 'This campaign is currently closed.')) ?></strong>
      </div>
    <?php else: ?>
      <form class="mg-rl-form" data-campaign-form data-submit-endpoint="<?= mg_e($submitEndpoint) ?>" data-campaign-type="<?= mg_e($campaignType) ?>"<?= $preview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)($campaign['public_id'] ?? '')) ?>">
        <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'] ?? '')) ?>">
        <input type="hidden" name="campaign_type" value="<?= mg_e($campaignType) ?>">
        <?php if ($campaignType === 'qr_reward_drop'): ?>
          <input type="hidden" name="qr_token" value="<?= mg_e($campaignToken !== '' ? $campaignToken : (string)($campaign['qr_code_token'] ?? '')) ?>">
        <?php endif; ?>
        <h3><?= mg_e($typeLabel) ?></h3>
        <p><?= mg_e($outcomeTitle) ?> · delivered through Microgifter Inbox.</p>
        <?php mg_campaign_render_user_details($prefill); ?>
        <?php if ($campaignType === 'contest_giveaway'): ?>
          <label>Entry note <span>(optional)</span><textarea name="entry_note" placeholder="Optional note for this contest"></textarea></label>
        <?php elseif ($campaignType === 'referral_reward'): ?>
          <label>Referral note <span>(optional)</span><textarea name="entry_note" placeholder="Who referred you or who should we contact?"></textarea></label>
        <?php elseif ($campaignType === 'birthday_vip'): ?>
          <label>Birthday month<input name="entry_note" placeholder="Example: March"></label>
        <?php elseif ($campaignType === 'agent_offer'): ?>
          <label>What are you looking for?<textarea name="entry_note" placeholder="Tell the merchant what kind of reward or offer interests you."></textarea></label>
        <?php endif; ?>
        <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $preview ? 'button' : 'submit' ?>"<?= $preview ? ' disabled aria-disabled="true"' : '' ?>><?= mg_e($preview ? 'Preview only - activate to publish' : $submitLabel) ?></button>
        <div class="mg-public-campaign-status" data-campaign-status><?= $preview ? 'Preview mode: customer submissions are disabled.' : '' ?></div>
        <p class="mg-public-campaign-privacy">We respect your privacy. Unsubscribe anytime.</p>
      </form>
      <div class="mg-public-campaign-result" data-campaign-result></div>
    <?php endif;
}

if (!isset($mgCampaignLoadAttempted) || !$mgCampaignLoadAttempted) {
    $mgCampaignLoadAttempted = true;
    try {
        $mgCampaign = mg_campaign_landing_load($mgCampaignExpectedType, $mgCampaignRef, $mgCampaignToken, $mgCampaignPreviewMode);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'public.campaign_page.unavailable', 'Unable to load public campaign page.', [
                'exception_class' => $error::class,
            ]);
        }
        $mgCampaign = null;
    }
}

$campaignState = mg_campaign_landing_state(is_array($mgCampaign ?? null) ? $mgCampaign : null, $mgCampaignPreviewMode);
if (empty($campaignState['available']) || !is_array($mgCampaign ?? null)) {
    mg_campaign_landing_render_unavailable(
        (string)$mgCampaignPageLabel,
        (string)$mgCampaignPageIntro,
        (string)($campaignState['message'] ?? '')
    );
    return;
}

$campaignType = (string)$mgCampaign['campaign_type'];
$typeLabel = mg_public_campaign_type_label($campaignType);
[$outcomeTitle, $outcomeCopy] = mg_public_campaign_outcome_copy($campaignType);
$campaignSteps = mg_public_campaign_steps($campaignType);
$headline = trim((string)($mgCampaign['form_headline'] ?? '')) ?: (string)$mgCampaign['title'];
$description = trim((string)($mgCampaign['form_description'] ?? ''))
    ?: (trim((string)($mgCampaign['description'] ?? '')) ?: 'Enter your information to engage with this Microgifter campaign.');
$rewardTitle = trim((string)($mgCampaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardDescription = trim((string)($mgCampaign['reward_template_description'] ?? '')) ?: trim((string)($mgCampaign['description'] ?? ''));
$rewardValue = mg_campaign_landing_value($mgCampaign);
$campaignImageUrl = mg_campaign_landing_campaign_image($mgCampaign);
$rewardCoverUrl = $campaignImageUrl ?? mg_campaign_landing_reward_cover($mgCampaign);
$backgroundUrl = mg_campaign_landing_background_image($mgCampaign);
$submitEndpoint = mg_public_campaign_endpoint($campaignType);
$submitLabel = mg_public_campaign_submit_label($campaignType);
$profile = mg_campaign_landing_profile($mgCampaign);
$prefill = mg_campaign_landing_prefill();
$joinContext = [
    'campaign_type' => $campaignType,
    'campaign' => $mgCampaign,
    'preview' => $mgCampaignPreviewMode,
    'state' => $campaignState,
    'profile' => $profile,
    'type_label' => $typeLabel,
    'outcome_title' => $outcomeTitle,
    'submit_endpoint' => $submitEndpoint,
    'submit_label' => $submitLabel,
    'prefill' => $prefill,
    'campaign_token' => $mgCampaignToken,
];
$campaignClass = 'mg-rl-simple-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($campaignType));
?>
<section class="mg-rl-page mg-rl-campaign-foundation mg-rl-simple-campaign <?= mg_e($campaignClass) ?><?= $mgCampaignPreviewMode ? ' is-merchant-preview' : '' ?>" data-public-campaign-page data-campaign-state="<?= mg_e((string)$campaignState['code']) ?>"<?= $mgCampaignPreviewMode ? ' data-merchant-campaign-preview' : '' ?>>
  <div class="mg-rl-bg"<?= $backgroundUrl ? ' style="background-image:url(' . mg_e($backgroundUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <?php if ($mgCampaignPreviewMode): ?>
        <article class="mg-rl-card mg-rl-preview-banner">
          <span class="mg-rl-eyebrow">Merchant preview</span>
          <h3><?= mg_e((string)$campaignState['status_label']) ?></h3>
          <p>This draft is only visible to the merchant owner. Customer submissions are disabled until the campaign is active.</p>
          <a class="mg-rl-btn mg-rl-btn-soft" href="/merchant-ad-manager.php">Open campaign manager</a>
        </article>
      <?php endif; ?>
      <header class="mg-rl-hero">
        <h1><?= mg_e($headline) ?></h1>
        <p><?= mg_e($description) ?></p>
        <div class="mg-public-campaign-trust-row"><span><?= mg_e($typeLabel) ?></span><span>Reward sent to Inbox</span><span>PPPM tracked</span></div>
      </header>
      <section class="mg-rl-player mg-rl-simple-reward-canvas" aria-label="<?= mg_e($typeLabel) ?> reward canvas">
        <div class="mg-rl-track">
          <div class="mg-rl-art">
            <?php if ($rewardCoverUrl): ?><img src="<?= mg_e($rewardCoverUrl) ?>" alt="<?= mg_e($rewardTitle) ?> campaign image"><?php else: ?><div class="mg-rl-art-placeholder">Reward</div><?php endif; ?>
            <span><?= mg_e($outcomeTitle) ?></span>
          </div>
          <div class="mg-rl-track-copy">
            <small><?= $campaignImageUrl ? 'Campaign image' : 'Attached reward' ?></small>
            <strong><?= mg_e($rewardTitle) ?></strong>
            <em><?= mg_e($rewardValue) ?></em>
            <?php if ($rewardDescription !== ''): ?><p class="mg-rl-reward-copy"><?= mg_e($rewardDescription) ?></p><?php endif; ?>
            <ul class="mg-rl-list mg-rl-simple-steps"><?php foreach ($campaignSteps as $step): ?><li><strong><?= mg_e((string)$step['title']) ?></strong><?= mg_e((string)$step['copy']) ?></li><?php endforeach; ?></ul>
          </div>
        </div>
      </section>
      <aside class="mg-rl-join mg-rl-join-mobile"><?php mg_public_campaign_render_join_form($joinContext); ?></aside>
      <?php mg_campaign_landing_render_bottom_cards([
          'hidden' => $campaignType === 'newsletter_signup',
          'campaign' => $mgCampaign,
          'state' => $campaignState,
          'reward_title' => $rewardTitle,
          'reward_value' => $rewardValue,
          'outcome_title' => $outcomeTitle,
          'outcome_copy' => $outcomeCopy,
      ]); ?>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop"><?php mg_public_campaign_render_join_form($joinContext); ?></aside>
  </div>
</section>