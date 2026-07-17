<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-landing-foundation.php';
require_once __DIR__ . '/includes/campaign-user-details.php';

$page_title = 'RSVP Event Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-landing-specialized.css',
    '/assets/css/public-campaign-compact-layout-v2.css?v=1.0.0',
];
$page_scripts = ['/assets/js/public-campaign.js', '/assets/js/public-rsvp-event.js'];

$bootstrap = mg_campaign_landing_bootstrap('rsvp_event_reward', $page_title);
$campaign = is_array($bootstrap['campaign'] ?? null) ? $bootstrap['campaign'] : null;
$previewMode = (bool)($bootstrap['preview'] ?? false);
$page_title = (string)($bootstrap['page_title'] ?? $page_title);
$page_meta = is_array($bootstrap['page_meta'] ?? null) ? $bootstrap['page_meta'] : [];
$state = mg_campaign_landing_state($campaign, $previewMode);

function mg_rsvp_event_render_join(array $context): void
{
    $campaign = $context['campaign'];
    $profile = $context['profile'];
    $state = $context['state'];
    $preview = (bool)$context['preview'];
    $prefill = $context['prefill'];
    $attendanceEnabled = (bool)$context['attendance_enabled'];
    ?>
    <?php mg_campaign_landing_render_profile($profile); ?>
    <?php if (!empty($state['closed'])): ?>
      <div class="mg-public-campaign-result is-visible" data-campaign-closed-state><strong><?= mg_e((string)$state['message']) ?></strong></div>
    <?php else: ?>
      <form class="mg-rl-form mg-specialized-form" data-campaign-form data-rsvp-event-form data-submit-endpoint="/api/public/campaigns/rsvp-event.php" data-campaign-type="rsvp_event_reward"<?= $preview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)$campaign['public_id']) ?>">
        <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'])) ?>">
        <input type="hidden" name="campaign_type" value="rsvp_event_reward">
        <h3>Reserve your spot</h3>
        <p>Submit your RSVP now. Confirmed attendance unlocks the configured event reward.</p>
        <?php mg_campaign_render_user_details($prefill); ?>
        <?php if ($attendanceEnabled): ?>
          <label class="mg-specialized-check"><input type="checkbox" data-rsvp-attendance-toggle><span>I am at the event and have the attendance code</span></label>
          <div data-rsvp-attendance-panel hidden><label>Attendance code<input name="entry_attendance_code" maxlength="64" placeholder="Enter event code"></label></div>
        <?php endif; ?>
        <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $preview ? 'button' : 'submit' ?>" data-rsvp-submit<?= $preview ? ' disabled aria-disabled="true"' : '' ?>><?= mg_e($preview ? 'Preview only - activate to publish' : 'Submit RSVP') ?></button>
        <div class="mg-public-campaign-status" data-campaign-status data-rsvp-event-status><?= $preview ? 'Preview mode: customer submissions are disabled.' : '' ?></div>
        <p class="mg-public-campaign-privacy">RSVP information is recorded for merchant event planning. Rewards issue only after attendance confirmation.</p>
      </form>
      <div class="mg-public-campaign-result" data-campaign-result></div>
    <?php endif;
}

require __DIR__ . '/includes/header.php';

if (!$campaign || empty($state['available'])) {
    mg_campaign_landing_render_unavailable(
        'RSVP / Event Attendance Reward',
        'Reserve your spot and unlock an attendance reward powered by Microgifter.',
        (string)($state['message'] ?? '')
    );
    require __DIR__ . '/includes/footer.php';
    return;
}

$rules = mg_campaign_landing_rules($campaign);
$profile = mg_campaign_landing_profile($campaign);
$prefill = mg_campaign_landing_prefill();
$headline = trim((string)($campaign['form_headline'] ?? '')) ?: (trim((string)($campaign['title'] ?? '')) ?: 'RSVP and earn an attendance reward');
$description = trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'RSVP now and claim your reward after attendance is confirmed.');
$eventName = trim((string)($rules['event_name'] ?? $rules['rsvp_event_name'] ?? '')) ?: (trim((string)($campaign['title'] ?? '')) ?: 'Merchant event');
$eventDateRaw = trim((string)($rules['event_date'] ?? $rules['rsvp_event_date'] ?? ''));
$eventDateTimestamp = $eventDateRaw !== '' ? strtotime($eventDateRaw) : false;
$eventDateLabel = $eventDateTimestamp !== false ? date('M j, Y · g:i A', $eventDateTimestamp) : $eventDateRaw;
$attendanceCode = strtoupper(trim((string)($rules['attendance_code'] ?? $rules['rsvp_attendance_code'] ?? '')));
$attendanceEnabled = $attendanceCode !== '';
$rewardTitle = trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward';
$rewardDescription = trim((string)($campaign['reward_template_description'] ?? '')) ?: 'Confirmed attendance unlocks this event reward in Microgifter Inbox.';
$rewardValue = mg_campaign_landing_value($campaign);
$primaryImage = mg_campaign_landing_primary_image($campaign);
$backgroundImage = mg_campaign_landing_background_image($campaign);
$joinContext = [
    'campaign' => $campaign,
    'profile' => $profile,
    'state' => $state,
    'preview' => $previewMode,
    'prefill' => $prefill,
    'attendance_enabled' => $attendanceEnabled,
];
?>
<section class="mg-rl-page mg-rl-campaign-foundation mg-rl-specialized mg-rl-specialized-rsvp mg-rl-compact-campaign<?= $previewMode ? ' is-merchant-preview' : '' ?>" data-public-campaign-page data-campaign-state="<?= mg_e((string)$state['code']) ?>">
  <div class="mg-rl-bg"<?= $backgroundImage ? ' style="background-image:url(' . mg_e($backgroundImage) . ')"' : '' ?>></div>
  <div class="mg-rl-wrap">
    <div class="mg-rl-left">
      <?php if ($previewMode): ?><article class="mg-rl-card mg-specialized-preview"><span class="mg-rl-eyebrow">Merchant preview</span><h3><?= mg_e((string)$state['status_label']) ?></h3><p>Customer RSVPs are disabled until this campaign is active.</p></article><?php endif; ?>
      <header class="mg-rl-hero">
        <h1><?= mg_e($headline) ?></h1>
        <p><?= mg_e($description) ?></p>
        <div class="mg-public-campaign-trust-row"><span>RSVP tracked</span><span><?= $attendanceEnabled ? 'Attendance code enabled' : 'RSVP only' ?></span><span>Reward sent to Inbox</span></div>
      </header>
      <section class="mg-rl-player mg-specialized-canvas" aria-label="RSVP event reward details">
        <div class="mg-specialized-layout">
          <div class="mg-specialized-media"><?php if ($primaryImage): ?><img src="<?= mg_e($primaryImage) ?>" alt="<?= mg_e($eventName) ?> campaign image"><?php else: ?><div class="mg-specialized-placeholder"><span>Event</span><strong>RSVP Reward</strong></div><?php endif; ?></div>
          <div class="mg-specialized-copy"><span class="mg-rl-eyebrow">Upcoming event</span><h2><?= mg_e($eventName) ?></h2><?php if ($eventDateLabel !== ''): ?><p class="mg-specialized-date"><?= mg_e($eventDateLabel) ?></p><?php endif; ?><p><?= mg_e($rewardDescription) ?></p><div class="mg-specialized-metrics"><span><strong>Step 1</strong>Submit RSVP</span><span><strong>Step 2</strong><?= $attendanceEnabled ? 'Confirm attendance' : 'Attend event' ?></span><span><strong><?= mg_e($rewardValue) ?></strong><?= mg_e($rewardTitle) ?></span></div></div>
        </div>
      </section>
      <aside class="mg-rl-join mg-rl-join-mobile"><?php mg_rsvp_event_render_join($joinContext); ?></aside>
      <?php mg_campaign_landing_render_bottom_cards([
          'campaign' => $campaign,
          'state' => $state,
          'reward_title' => $rewardTitle,
          'reward_value' => $rewardValue,
          'outcome_title' => 'Attendance reward',
          'outcome_copy' => $attendanceEnabled ? 'RSVP first, then confirm attendance with the merchant event code to unlock the eligible reward.' : 'RSVP participation is recorded; the merchant has not configured online attendance-code confirmation.',
      ]); ?>
    </div>
    <aside class="mg-rl-join mg-rl-join-desktop"><?php mg_rsvp_event_render_join($joinContext); ?></aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>