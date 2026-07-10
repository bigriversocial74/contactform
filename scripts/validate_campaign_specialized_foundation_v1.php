<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'survey-feedback.php',
    'check-in-reward.php',
    'rsvp-event.php',
    'includes/campaign-landing-foundation.php',
    'includes/header.php',
    'assets/css/campaign-landing-specialized.css',
    'assets/js/public-campaign.js',
    'assets/js/public-check-in-reward.js',
    'assets/js/public-rsvp-event.js',
    'assets/js/stage12-survey-feedback-reward.js',
    'assets/js/stage12-check-in-reward.js',
    'assets/js/stage12-rsvp-event-reward.js',
    'api/merchant/campaigns.php',
    'api/merchant/campaigns-core.php',
    'api/public/campaigns/survey-feedback.php',
    'api/public/campaigns/check-in.php',
    'api/public/campaigns/rsvp-event.php',
];

$checks = [];
$ok = true;
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $checks['file:' . $path] = $exists;
    $ok = $ok && $exists;
}

$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';
$survey = $read('survey-feedback.php');
$checkIn = $read('check-in-reward.php');
$rsvp = $read('rsvp-event.php');
$header = $read('includes/header.php');
$css = $read('assets/css/campaign-landing-specialized.css');
$merchantRoute = $read('api/merchant/campaigns.php');
$merchantCore = $read('api/merchant/campaigns-core.php');
$surveyApi = $read('api/public/campaigns/survey-feedback.php');
$checkInApi = $read('api/public/campaigns/check-in.php');
$rsvpApi = $read('api/public/campaigns/rsvp-event.php');
$surveyBuilder = $read('assets/js/stage12-survey-feedback-reward.js');
$checkInBuilder = $read('assets/js/stage12-check-in-reward.js');
$rsvpBuilder = $read('assets/js/stage12-rsvp-event-reward.js');

foreach ([
    'survey' => [$survey, 'survey_feedback_reward', '/api/public/campaigns/survey-feedback.php'],
    'check_in' => [$checkIn, 'check_in_reward', '/api/public/campaigns/check-in.php'],
    'rsvp' => [$rsvp, 'rsvp_event_reward', '/api/public/campaigns/rsvp-event.php'],
] as $name => [$page, $type, $endpoint]) {
    $checks[$name . ':foundation'] = str_contains($page, "mg_campaign_landing_bootstrap('" . $type . "'")
        && str_contains($page, 'mg_campaign_landing_state(')
        && str_contains($page, 'mg_campaign_landing_render_profile(')
        && str_contains($page, 'mg_campaign_landing_render_bottom_cards(');
    $checks[$name . ':canonical_shell'] = str_contains($page, 'mg-rl-campaign-foundation')
        && str_contains($page, 'mg-rl-specialized')
        && str_contains($page, 'watch-listen-standalone-page.css')
        && str_contains($page, 'campaign-landing-specialized.css');
    $checks[$name . ':endpoint'] = str_contains($page, $endpoint) && str_contains($page, 'data-campaign-form');
    $checks[$name . ':no_legacy_css'] = !str_contains($page, 'public-campaign-pages.css')
        && !str_contains($page, 'public-campaign-polish-v1.css')
        && !str_contains($page, 'mg-public-campaign-v2');
}

$checks['header:no_legacy_campaign_allowlist'] = !str_contains($header, 'legacy_campaign_layout_pages')
    && !str_contains($header, 'public-campaign-unified-layout-v2.css');
$checks['styles:scoped'] = str_contains($css, '.mg-rl-specialized')
    && !str_contains($css, 'body{')
    && !str_contains($css, 'html,body');
$checks['merchant:split_controller'] = str_contains($merchantRoute, "require __DIR__ . '/campaigns-core.php'")
    && str_contains($merchantCore, 'INSERT INTO campaigns')
    && str_contains($merchantCore, 'UPDATE campaigns');
$checks['merchant:survey_rules'] = str_contains($merchantRoute, "'survey_feedback_reward'")
    && str_contains($merchantRoute, "'rating_required'")
    && str_contains($merchantRoute, "'feedback_required'")
    && str_contains($merchantRoute, "'prompt'");
$checks['merchant:check_in_rules'] = str_contains($merchantRoute, "'check_in_reward'")
    && str_contains($merchantRoute, "'radius_meters'")
    && str_contains($merchantRoute, "'location_required'");
$checks['merchant:rsvp_rules'] = str_contains($merchantRoute, "'rsvp_event_reward'")
    && str_contains($merchantRoute, "'event_name'")
    && str_contains($merchantRoute, "'event_date'")
    && str_contains($merchantRoute, "'attendance_code'");
$checks['survey_api:rules_aware'] = str_contains($surveyApi, 'mg_public_campaign_engage_preprocess_input')
    && str_contains($surveyApi, '$ratingRequired')
    && str_contains($surveyApi, '$feedbackRequired');
$checks['check_in_api:rules_aware'] = str_contains($checkInApi, "'radius_meters'")
    && str_contains($checkInApi, "'location_required'")
    && str_contains($checkInApi, 'campaign_location_optional');
$checks['rsvp_api:rules_aware'] = str_contains($rsvpApi, 'mg_rsvp_event_attendance_code')
    && str_contains($rsvpApi, 'mg_rsvp_event_date')
    && str_contains($rsvpApi, 'mg_rsvp_event_name');
$checks['builder:survey'] = str_contains($surveyBuilder, 'survey_prompt')
    && str_contains($surveyBuilder, 'survey_rating_required')
    && str_contains($surveyBuilder, 'survey_feedback_required');
$checks['builder:check_in'] = str_contains($checkInBuilder, 'check_in_radius_meters')
    && str_contains($checkInBuilder, 'check_in_location_required');
$checks['builder:rsvp'] = str_contains($rsvpBuilder, 'rsvp_event_name')
    && str_contains($rsvpBuilder, 'rsvp_event_date')
    && str_contains($rsvpBuilder, 'rsvp_attendance_code');

foreach ($checks as $passed) $ok = $ok && $passed;

echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
