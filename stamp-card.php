<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-landing-foundation.php';
require_once __DIR__ . '/includes/campaign-user-details.php';

$page_title = 'Stamp Card Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-landing-foundation.css',
    '/assets/css/public-campaign-experience-v1.css',
    '/assets/css/loyalty-cards.css',
    '/assets/css/stamp-card-public-layout-fix.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/public-campaign.js',
    '/assets/js/public-stamp-card.js',
    '/assets/js/loyalty-cards.js',
    '/assets/js/stamp-card-public-layout-fix.js?v=1.0.0',
];

$mgCampaignBootstrap = mg_campaign_landing_bootstrap('stamp_card_reward', $page_title);
$campaign = is_array($mgCampaignBootstrap['campaign'] ?? null) ? $mgCampaignBootstrap['campaign'] : null;
$previewMode = (bool)($mgCampaignBootstrap['preview'] ?? false);
$page_title = (string)($mgCampaignBootstrap['page_title'] ?? $page_title);
$page_meta = is_array($mgCampaignBootstrap['page_meta'] ?? null) ? $mgCampaignBootstrap['page_meta'] : [];

require __DIR__ . '/includes/header.php';

$campaignState = mg_campaign_landing_state($campaign, $previewMode);
if (!$campaign || empty($campaignState['available'])) {
    mg_campaign_landing_render_unavailable(
        'Stamp Card',
        'Collect verified merchant visits and unlock a loyalty reward powered by Microgifter.',
        (string)($campaignState['message'] ?? 'Use the stamp-card link from the merchant to open this reward.')
    );
    require __DIR__ . '/includes/footer.php';
    return;
}

$rules = mg_campaign_landing_rules($campaign);
$rawMode = strtolower(trim((string)($rules['mode'] ?? 'verified_stamp_card')));
$modeAliases = ['stamp_card' => 'verified_stamp_card', 'visit_tracker' => 'verified_stamp_card'];
$campaignMode = $modeAliases[$rawMode] ?? $rawMode;
if ($campaignMode !== 'verified_stamp_card') $campaignMode = 'verified_stamp_card';

$profile = mg_campaign_landing_profile($campaign);
$prefill = mg_campaign_landing_prefill();
$backgroundUrl = mg_campaign_landing_background_image($campaign);
$campaignImageUrl = mg_campaign_landing_campaign_image($campaign);
$cardImageUrl = $campaignImageUrl ?? mg_campaign_landing_reward_cover($campaign);
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title'];
$description = trim((string)($campaign['form_description'] ?? ''))
    ?: (trim((string)($campaign['description'] ?? '')) ?: 'Add verified visits to your stamp card and unlock a reward.');
$requiredCount = max(1, min(100, (int)($rules['required_count'] ?? $rules['stamp_required_count'] ?? 5)));
$stampLabel = trim((string)($rules['stamp_label'] ?? 'Visit')) ?: 'Visit';
$cashierRequired = array_key_exists('cashier_verification_required', $rules)
    ? !empty($rules['cashier_verification_required'])
    : true;
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardValue = mg_campaign_landing_value($campaign);
$redemptionInstructions = trim((string)($campaign['redemption_instructions'] ?? ''));
$endsAt = trim((string)($campaign['ends_at'] ?? ''));
$endsTimestamp = $endsAt !== '' ? strtotime($endsAt) : false;
$endsLabel = $endsTimestamp !== false ? date('M j, Y', $endsTimestamp) : '';
$columns = min(5, max(3, $requiredCount));
$closed = !empty($campaignState['closed']);
$pluralStampLabel = $requiredCount === 1 ? $stampLabel : $stampLabel . 's';
$initialUpdate = $previewMode
    ? 'Preview mode: customer submissions are disabled.'
    : ($closed ? (string)($campaignState['message'] ?? 'This campaign is currently closed.') : 'Ready for the next stamp.');

$renderStampJoin = static function (array $context): void {
    $campaign = $context['campaign'];
    $profile = $context['profile'];
    $prefill = $context['prefill'];
    $state = $context['state'];
    $preview = (bool)$context['preview'];
    $closed = (bool)$context['closed'];
    $cashierRequired = (bool)$context['cashier_required'];
    $requiredCount = (int)$context['required_count'];

    mg_campaign_landing_render_profile($profile);
    if ($closed): ?>
      <div class="mg-public-campaign-result is-visible" data-campaign-closed-state="<?= mg_e((string)($state['code'] ?? 'closed')) ?>">
        <strong><?= mg_e((string)($state['message'] ?? 'This campaign is currently closed.')) ?></strong>
      </div>
    <?php else: ?>
      <form class="mg-rl-form" data-campaign-form data-campaign-keep-visible="1" data-stamp-card-form data-submit-endpoint="/api/public/campaigns/stamp-card.php" data-campaign-type="stamp_card_reward"<?= $preview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
        <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
        <input type="hidden" name="campaign_type" value="stamp_card_reward">
        <h3><?= $cashierRequired ? 'Verify this stamp' : 'Add this stamp' ?></h3>
        <p><?= $cashierRequired ? 'Ask the cashier to enter the claim code before the stamp is added.' : 'Submit this visit to update your loyalty progress.' ?></p>
        <?php mg_campaign_render_user_details($prefill); ?>
        <?php if ($cashierRequired): ?><label>Cashier claim code<input name="cashier_code" autocomplete="one-time-code" placeholder="Cashier enters code" maxlength="64" required></label><?php endif; ?>
        <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $preview ? 'button' : 'submit' ?>" data-stamp-card-submit<?= $preview ? ' disabled aria-disabled="true"' : '' ?>><?= $preview ? 'Preview only - activate to publish' : ($cashierRequired ? 'Add verified stamp' : 'Add stamp') ?></button>
        <p class="mg-public-campaign-privacy">Rewards unlock when verified progress reaches <?= mg_e((string)$requiredCount) ?>. Partial progress remains connected to this campaign.</p>
      </form>
    <?php endif;
};
?>
<section class="mg-rl-page mg-rl-campaign-foundation mg-rl-interactive mg-rl-stamp<?= $previewMode ? ' is-merchant-preview' : '' ?><?= $closed ? ' is-campaign-closed' : '' ?>" data-public-campaign-page data-stamp-card-experience data-campaign-state="<?= mg_e((string)$campaignState['code']) ?>" data-campaign-mode="<?= mg_e($campaignMode) ?>"<?= $previewMode ? ' data-merchant-campaign-preview' : '' ?>>
  <div class="mg-rl-bg"<?= $backgroundUrl ? ' style="background-image:url(' . mg_e($backgroundUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <?php if ($previewMode): ?>
        <article class="mg-rl-card mg-rl-preview-banner"><span class="mg-rl-eyebrow">Merchant preview</span><h3><?= mg_e((string)$campaignState['status_label']) ?></h3><p>Customer stamp submissions are disabled until this campaign is active.</p><a class="mg-rl-btn mg-rl-btn-soft" href="/merchant-campaigns.php">Open campaign manager</a></article>
      <?php endif; ?>
      <header class="mg-rl-hero">
        <h1><?= mg_e($headline) ?></h1>
        <p><?= mg_e($description) ?></p>
        <div class="mg-public-campaign-trust-row">
          <span><?= mg_e((string)$requiredCount) ?> <?= mg_e(strtolower($pluralStampLabel)) ?> to unlock</span>
          <span><?= $cashierRequired ? 'Cashier verified' : 'Customer check-in' ?></span>
          <span>Reward sent to Inbox</span>
          <?php if (!$previewMode && !$closed): ?><button class="mg-loyalty-save-toggle" type="button" data-loyalty-save-toggle data-campaign-id="<?= mg_e((string)$campaign['public_id']) ?>" data-saved="false" aria-pressed="false"><span data-loyalty-save-icon aria-hidden="true">☆</span><strong data-loyalty-save-label>Save Card</strong></button><?php endif; ?>
        </div>
      </header>

      <section class="mg-rl-player" aria-label="Stamp card interaction canvas">
        <div class="mg-stamp-stage<?= $cardImageUrl ? ' has-campaign-art' : '' ?>" data-stamp-stage data-required-count="<?= mg_e((string)$requiredCount) ?>" data-stamp-label="<?= mg_e($stampLabel) ?>">
          <?php if ($cardImageUrl): ?>
            <figure class="mg-stamp-campaign-art"><img src="<?= mg_e($cardImageUrl) ?>" alt="<?= mg_e($headline) ?> campaign image"><figcaption><?= $campaignImageUrl ? 'Campaign image' : 'Attached reward image' ?></figcaption></figure>
          <?php endif; ?>
          <div class="mg-stamp-card-visual" style="--stamp-columns:<?= mg_e((string)$columns) ?>;--stamp-progress:0%" data-stamp-card-visual>
            <div class="mg-stamp-card-header"><div><span>Current card</span><strong><span data-stamp-count>0</span> / <span data-stamp-required><?= mg_e((string)$requiredCount) ?></span></strong></div><em class="mg-campaign-canvas-badge" data-stamp-remaining><?= mg_e((string)$requiredCount) ?> remaining</em></div>
            <div class="mg-stamp-grid" data-stamp-grid><?php for ($i = 1; $i <= $requiredCount; $i++): ?><span class="mg-stamp-slot" data-stamp-slot="<?= mg_e((string)$i) ?>"><?= mg_e((string)$i) ?></span><?php endfor; ?></div>
            <div class="mg-stamp-progress"><div class="mg-stamp-progress-top"><span>Verified progress</span><strong data-stamp-progress-copy>0% complete</strong></div><div class="mg-stamp-bar"><span data-stamp-progress-bar></span></div></div>
          </div>
        </div>
      </section>

      <aside class="mg-rl-join mg-rl-join-mobile"><?php $renderStampJoin(['campaign' => $campaign, 'profile' => $profile, 'prefill' => $prefill, 'state' => $campaignState, 'preview' => $previewMode, 'closed' => $closed, 'cashier_required' => $cashierRequired, 'required_count' => $requiredCount]); ?></aside>

      <div class="mg-rl-bottom mg-stamp-summary" data-campaign-foundation-cards>
        <article class="mg-rl-card mg-stamp-summary-card">
          <div class="mg-stamp-summary-grid">
            <section class="mg-stamp-summary-section">
              <span class="mg-rl-eyebrow">Item details</span>
              <h3><?= mg_e($rewardTitle) ?></h3>
              <p><?= mg_e($rewardValue) ?></p>
              <?php if ($redemptionInstructions !== ''): ?><p><?= mg_e($redemptionInstructions) ?></p><?php endif; ?>
              <?php if ($endsLabel !== ''): ?><span class="mg-rl-pill">Ends <?= mg_e($endsLabel) ?></span><?php endif; ?>
            </section>
            <section class="mg-stamp-summary-section">
              <span class="mg-rl-eyebrow">Reward &amp; campaign rules</span>
              <ul class="mg-rl-list">
                <li><strong><?= mg_e((string)$requiredCount) ?> <?= mg_e($pluralStampLabel) ?></strong>Complete the required loyalty progress to unlock the attached reward.</li>
                <li><strong><?= $cashierRequired ? 'Cashier verification' : 'Customer check-in' ?></strong><?= $cashierRequired ? 'A valid merchant claim code is required for each stamp.' : 'The customer can submit each eligible visit directly.' ?></li>
                <li><strong>Inbox delivery</strong>Unlocked rewards are issued into the customer Microgifter Inbox.</li>
                <li><strong>PPPM tracked</strong>Campaign source, ownership, claim, and redemption stay connected.</li>
              </ul>
            </section>
          </div>
          <div class="mg-stamp-summary-updates" data-stamp-results-updates>
            <span class="mg-rl-eyebrow">Messages, results &amp; updates</span>
            <h3 data-stamp-summary-state><?= mg_e((string)($campaignState['active_status'] ?? 'Active and ready')) ?></h3>
            <div class="mg-public-campaign-status" data-campaign-status data-stamp-card-status><?= mg_e($initialUpdate) ?></div>
            <div class="mg-public-campaign-result" data-campaign-result></div>
          </div>
        </article>
      </div>
    </div>

    <aside class="mg-rl-join mg-rl-join-desktop"><?php $renderStampJoin(['campaign' => $campaign, 'profile' => $profile, 'prefill' => $prefill, 'state' => $campaignState, 'preview' => $previewMode, 'closed' => $closed, 'cashier_required' => $cashierRequired, 'required_count' => $requiredCount]); ?></aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
