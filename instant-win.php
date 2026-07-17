<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-landing-foundation.php';
require_once __DIR__ . '/includes/campaign-user-details.php';

$page_title = 'Instant Win Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-landing-foundation.css',
    '/assets/css/public-campaign-experience-v1.css',
    '/assets/css/public-campaign-compact-layout-v2.css?v=1.0.0',
];
$page_scripts = ['/assets/js/public-campaign.js', '/assets/js/public-instant-win.js'];

$mgCampaignBootstrap = mg_campaign_landing_bootstrap('instant_win_reward', $page_title);
$campaign = is_array($mgCampaignBootstrap['campaign'] ?? null) ? $mgCampaignBootstrap['campaign'] : null;
$campaignRef = (string)($mgCampaignBootstrap['campaign_ref'] ?? '');
$previewMode = (bool)($mgCampaignBootstrap['preview'] ?? false);
$page_title = (string)($mgCampaignBootstrap['page_title'] ?? $page_title);
$page_meta = is_array($mgCampaignBootstrap['page_meta'] ?? null) ? $mgCampaignBootstrap['page_meta'] : [];

require __DIR__ . '/includes/header.php';

$campaignState = mg_campaign_landing_state($campaign, $previewMode);
if (!$campaign || empty($campaignState['available'])) {
    mg_campaign_landing_render_unavailable(
        'Instant Win',
        'Play a merchant scratch-card or spin-wheel campaign powered by Microgifter.',
        (string)($campaignState['message'] ?? 'Use the instant-win link from the merchant to open this reward.')
    );
    require __DIR__ . '/includes/footer.php';
    return;
}

$rules = mg_campaign_landing_rules($campaign);
$rawMode = strtolower(trim((string)($rules['play_mode'] ?? $rules['mode'] ?? 'scratch_card')));
$modeAliases = ['scratch_reveal' => 'scratch_card', 'scratch' => 'scratch_card', 'wheel' => 'spin_wheel'];
$playMode = $modeAliases[$rawMode] ?? $rawMode;
if (!in_array($playMode, ['scratch_card', 'spin_wheel'], true)) $playMode = 'scratch_card';

$profile = mg_campaign_landing_profile($campaign);
$prefill = mg_campaign_landing_prefill();
$backgroundUrl = mg_campaign_landing_background_image($campaign);
$campaignImageUrl = mg_campaign_landing_campaign_image($campaign);
$interactionImageUrl = $campaignImageUrl ?? mg_campaign_landing_reward_cover($campaign);
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title'];
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardValue = mg_campaign_landing_value($campaign);
$modeLabel = $playMode === 'spin_wheel' ? 'Spin Wheel' : 'Scratch Card';
$closed = !empty($campaignState['closed']);

$renderInstantJoin = static function (array $context): void {
    $campaign = $context['campaign'];
    $profile = $context['profile'];
    $prefill = $context['prefill'];
    $state = $context['state'];
    $preview = (bool)$context['preview'];
    $closed = (bool)$context['closed'];
    $playMode = (string)$context['play_mode'];
    $modeLabel = (string)$context['mode_label'];

    mg_campaign_landing_render_profile($profile);
    if ($closed): ?>
      <div class="mg-public-campaign-result is-visible" data-campaign-closed-state="<?= mg_e((string)($state['code'] ?? 'closed')) ?>">
        <strong><?= mg_e((string)($state['message'] ?? 'This campaign is currently closed.')) ?></strong>
      </div>
    <?php else: ?>
      <form class="mg-rl-form" data-campaign-form data-campaign-keep-visible="1" data-instant-win-form data-submit-endpoint="/api/public/campaigns/instant-win.php" data-campaign-type="instant_win_reward"<?= $preview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
        <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
        <input type="hidden" name="campaign_type" value="instant_win_reward">
        <input type="hidden" name="entry_reveal_confirmed" value="0">
        <input type="hidden" name="entry_instant_win_mode" value="<?= mg_e($playMode) ?>">
        <h3>Submit your play</h3>
        <p>Complete the <?= mg_e(strtolower($modeLabel)) ?> interaction, then submit one result.</p>
        <?php mg_campaign_render_user_details($prefill); ?>
        <button class="mg-rl-btn mg-rl-btn-soft" type="button" data-instant-win-reveal><?= $playMode === 'spin_wheel' ? 'Spin wheel' : 'Reveal card' ?></button>
        <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $preview ? 'button' : 'submit' ?>"<?= $preview ? ' disabled aria-disabled="true"' : '' ?>><?= $preview ? 'Preview only - activate to publish' : 'Submit instant-win play' ?></button>
        <div class="mg-public-campaign-status" data-campaign-status data-instant-win-status><?= $preview ? 'Preview mode: customer submissions are disabled.' : '' ?></div>
        <p class="mg-public-campaign-privacy">Winning value events create or promote the merchant CRM contact. No-win plays do not create a new contact.</p>
      </form>
      <div class="mg-public-campaign-result" data-campaign-result></div>
    <?php endif;
};
?>
<section class="mg-rl-page mg-rl-campaign-foundation mg-rl-interactive mg-rl-instant mg-rl-compact-campaign<?= $previewMode ? ' is-merchant-preview' : '' ?><?= $closed ? ' is-campaign-closed' : '' ?>" data-public-campaign-page data-instant-win-experience data-campaign-state="<?= mg_e((string)$campaignState['code']) ?>" data-campaign-mode="<?= mg_e($playMode) ?>"<?= $previewMode ? ' data-merchant-campaign-preview' : '' ?>>
  <div class="mg-rl-bg"<?= $backgroundUrl ? ' style="background-image:url(' . mg_e($backgroundUrl) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <section class="mg-rl-player" aria-label="Instant win interaction canvas">
        <div class="mg-instant-stage<?= $playMode === 'spin_wheel' && $interactionImageUrl ? ' has-campaign-art' : '' ?>" data-instant-stage data-mode="<?= mg_e($playMode) ?>">
          <?php if ($playMode === 'spin_wheel' && $interactionImageUrl): ?>
            <figure class="mg-interactive-campaign-art"><img src="<?= mg_e($interactionImageUrl) ?>" alt="<?= mg_e($headline) ?> campaign image"><figcaption><?= $campaignImageUrl ? 'Campaign image' : 'Attached reward image' ?></figcaption></figure>
          <?php endif; ?>
          <button class="mg-instant-card<?= $playMode === 'spin_wheel' ? ' is-wheel-mode' : ' is-scratch-mode' ?>" type="button" data-instant-win-card data-mode="<?= mg_e($playMode) ?>"<?= $closed ? ' disabled aria-disabled="true"' : '' ?>>
            <span class="mg-instant-result-under"><span>Instant result</span><strong><?= mg_e($rewardTitle) ?></strong><em><?= mg_e($rewardValue) ?> · submit to record your play</em></span>
            <?php if ($playMode === 'spin_wheel'): ?>
              <span class="mg-instant-wheel-layer"><span class="mg-instant-wheel-pointer"></span><span class="mg-instant-wheel" aria-hidden="true"></span><span class="mg-instant-wheel-copy"><span>Tap to spin</span><strong>Spin for your reward</strong></span></span>
            <?php else: ?>
              <span class="mg-instant-scratch-layer" data-scratch-image="<?= mg_e($interactionImageUrl ?? '') ?>"><?php if ($interactionImageUrl): ?><img src="<?= mg_e($interactionImageUrl) ?>" alt="<?= mg_e($headline) ?> campaign artwork"><?php endif; ?><canvas data-instant-scratch-canvas aria-hidden="true"></canvas><span class="mg-instant-scratch-copy"><span>Scratch to reveal</span><strong>Swipe the card</strong></span></span>
            <?php endif; ?>
          </button>
        </div>
      </section>

      <?php if ($previewMode): ?>
        <article class="mg-rl-card mg-rl-preview-banner"><span class="mg-rl-eyebrow">Merchant preview</span><h3><?= mg_e((string)$campaignState['status_label']) ?></h3><p>Customer submissions are disabled until this campaign is active.</p><a class="mg-rl-btn mg-rl-btn-soft" href="/merchant-campaigns.php">Open campaign manager</a></article>
      <?php endif; ?>

      <aside class="mg-rl-join mg-rl-join-mobile"><?php $renderInstantJoin(['campaign' => $campaign, 'profile' => $profile, 'prefill' => $prefill, 'state' => $campaignState, 'preview' => $previewMode, 'closed' => $closed, 'play_mode' => $playMode, 'mode_label' => $modeLabel]); ?></aside>

      <?php mg_campaign_landing_render_bottom_cards([
          'campaign' => $campaign,
          'state' => $campaignState,
          'reward_title' => $rewardTitle,
          'reward_value' => $rewardValue,
          'outcome_title' => $modeLabel . ' result',
          'outcome_copy' => 'Complete the interaction and submit once. Winning rewards are issued into Microgifter Inbox.',
      ]); ?>
    </div>

    <aside class="mg-rl-join mg-rl-join-desktop"><?php $renderInstantJoin(['campaign' => $campaign, 'profile' => $profile, 'prefill' => $prefill, 'state' => $campaignState, 'preview' => $previewMode, 'closed' => $closed, 'play_mode' => $playMode, 'mode_label' => $modeLabel]); ?></aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>