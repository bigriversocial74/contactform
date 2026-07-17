<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-landing-foundation.php';
require_once __DIR__ . '/includes/campaign-user-details.php';

$page_title = 'Survey Feedback Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-landing-specialized.css',
    '/assets/css/public-campaign-compact-layout-v2.css?v=1.0.0',
];
$page_scripts = ['/assets/js/public-campaign.js'];

$bootstrap = mg_campaign_landing_bootstrap('survey_feedback_reward', $page_title);
$campaign = is_array($bootstrap['campaign'] ?? null) ? $bootstrap['campaign'] : null;
$campaignRef = (string)($bootstrap['campaign_ref'] ?? '');
$previewMode = (bool)($bootstrap['preview'] ?? false);
$page_title = (string)($bootstrap['page_title'] ?? $page_title);
$page_meta = is_array($bootstrap['page_meta'] ?? null) ? $bootstrap['page_meta'] : [];
$state = mg_campaign_landing_state($campaign, $previewMode);

function mg_survey_feedback_render_join(array $context): void
{
    $campaign = $context['campaign'];
    $profile = $context['profile'];
    $state = $context['state'];
    $preview = (bool)$context['preview'];
    $prompt = (string)$context['prompt'];
    $ratingRequired = (bool)$context['rating_required'];
    $feedbackRequired = (bool)$context['feedback_required'];
    $prefill = $context['prefill'];
    ?>
    <?php mg_campaign_landing_render_profile($profile); ?>
    <?php if (!empty($state['closed'])): ?>
      <div class="mg-public-campaign-result is-visible" data-campaign-closed-state><strong><?= mg_e((string)$state['message']) ?></strong></div>
    <?php else: ?>
      <form class="mg-rl-form mg-specialized-form" data-campaign-form data-submit-endpoint="/api/public/campaigns/survey-feedback.php" data-campaign-type="survey_feedback_reward"<?= $preview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
        <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
        <input type="hidden" name="campaign_type" value="survey_feedback_reward">
        <h3>Share your feedback</h3>
        <p>Your response is connected to this campaign and any eligible reward is delivered through Microgifter Inbox.</p>
        <?php mg_campaign_render_user_details($prefill); ?>
        <label><?= mg_e($prompt) ?><?= $feedbackRequired ? '' : ' ' ?><span><?= $feedbackRequired ? '' : '(optional)' ?></span><textarea name="entry_feedback" minlength="3" maxlength="1200" placeholder="Share a short note with the merchant."<?= $feedbackRequired ? ' required' : '' ?>></textarea></label>
        <label>Rating<?= $ratingRequired ? '' : ' ' ?><span><?= $ratingRequired ? '' : '(optional)' ?></span><select name="entry_rating"<?= $ratingRequired ? ' required' : '' ?>><option value="">Choose rating</option><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Okay</option><option value="2">2 - Needs work</option><option value="1">1 - Poor</option></select></label>
        <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $preview ? 'button' : 'submit' ?>"<?= $preview ? ' disabled aria-disabled="true"' : '' ?>><?= mg_e($preview ? 'Preview only - activate to publish' : 'Submit feedback and claim reward') ?></button>
        <div class="mg-public-campaign-status" data-campaign-status><?= $preview ? 'Preview mode: customer submissions are disabled.' : '' ?></div>
        <p class="mg-public-campaign-privacy">Your feedback is shared with the merchant for service improvement and campaign follow-up.</p>
      </form>
      <div class="mg-public-campaign-result" data-campaign-result></div>
    <?php endif;
}

require __DIR__ . '/includes/header.php';

if (!$campaign || empty($state['available'])) {
    mg_campaign_landing_render_unavailable(
        'Survey / Feedback Reward',
        'Share feedback and unlock a merchant reward powered by Microgifter.',
        (string)($state['message'] ?? '')
    );
    require __DIR__ . '/includes/footer.php';
    return;
}

$rules = mg_campaign_landing_rules($campaign);
$profile = mg_campaign_landing_profile($campaign);
$prefill = mg_campaign_landing_prefill();
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: (trim((string)($campaign['title'] ?? '')) ?: 'Share feedback and get a reward');
$description = trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Answer a quick feedback question and receive a Microgifter reward.');
$prompt = trim((string)($rules['prompt'] ?? '')) ?: 'How was your experience?';
$ratingRequired = !array_key_exists('rating_required', $rules) || !empty($rules['rating_required']);
$feedbackRequired = !array_key_exists('feedback_required', $rules) || !empty($rules['feedback_required']);
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardDescription = trim((string)($campaign['reward_template_description'] ?? '')) ?: 'Submit the configured feedback response to unlock this reward.';
$rewardValue = mg_campaign_landing_value($campaign);
$primaryImage = mg_campaign_landing_primary_image($campaign);
$backgroundImage = mg_campaign_landing_background_image($campaign);
$joinContext = compact('campaign', 'profile', 'state', 'previewMode', 'prompt', 'ratingRequired', 'feedbackRequired', 'prefill');
$joinContext['preview'] = $previewMode;
$joinContext['rating_required'] = $ratingRequired;
$joinContext['feedback_required'] = $feedbackRequired;
?>
<section class="mg-rl-page mg-rl-campaign-foundation mg-rl-specialized mg-rl-specialized-survey mg-rl-compact-campaign<?= $previewMode ? ' is-merchant-preview' : '' ?>" data-public-campaign-page data-campaign-state="<?= mg_e((string)$state['code']) ?>">
  <div class="mg-rl-bg"<?= $backgroundImage ? ' style="background-image:url(' . mg_e($backgroundImage) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <?php if ($previewMode): ?><article class="mg-rl-card mg-specialized-preview"><span class="mg-rl-eyebrow">Merchant preview</span><h3><?= mg_e((string)$state['status_label']) ?></h3><p>Customer submissions are disabled until this campaign is active.</p></article><?php endif; ?>
      <header class="mg-rl-hero">
        <h1><?= mg_e($headline) ?></h1>
        <p><?= mg_e($description) ?></p>
        <div class="mg-public-campaign-trust-row"><span>Feedback captured</span><span>Reward sent to Inbox</span><span>CRM follow-up ready</span></div>
      </header>
      <section class="mg-rl-player mg-specialized-canvas" aria-label="Survey feedback reward details">
        <div class="mg-specialized-layout">
          <div class="mg-specialized-media"><?php if ($primaryImage): ?><img src="<?= mg_e($primaryImage) ?>" alt="<?= mg_e($rewardTitle) ?> campaign image"><?php else: ?><div class="mg-specialized-placeholder"><span>Survey</span><strong>Feedback Reward</strong></div><?php endif; ?></div>
          <div class="mg-specialized-copy"><span class="mg-rl-eyebrow">Survey prompt</span><h2><?= mg_e($prompt) ?></h2><p><?= mg_e($rewardDescription) ?></p><div class="mg-specialized-metrics"><span><strong><?= $ratingRequired ? 'Required' : 'Optional' ?></strong>Rating</span><span><strong><?= $feedbackRequired ? 'Required' : 'Optional' ?></strong>Written feedback</span><span><strong><?= mg_e($rewardValue) ?></strong><?= mg_e($rewardTitle) ?></span></div></div>
        </div>
      </section>
      <aside class="mg-rl-join mg-rl-join-mobile"><?php mg_survey_feedback_render_join($joinContext); ?></aside>
      <?php mg_campaign_landing_render_bottom_cards([
          'campaign' => $campaign,
          'state' => $state,
          'reward_title' => $rewardTitle,
          'reward_value' => $rewardValue,
          'outcome_title' => 'Feedback reward',
          'outcome_copy' => 'Complete the configured survey fields and the eligible reward is routed through Microgifter Inbox.',
      ]); ?>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop"><?php mg_survey_feedback_render_join($joinContext); ?></aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>