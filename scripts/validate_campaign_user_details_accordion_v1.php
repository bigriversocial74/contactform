<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failures = [];

function cua_file(string $path): string
{
    global $root, $failures;
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        $failures[] = "Missing required file: {$path}";
        return '';
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        $failures[] = "Unable to read required file: {$path}";
        return '';
    }
    return $content;
}

function cua_has(string $path, string $needle, string $label): void
{
    global $checks, $failures;
    $checks++;
    $content = cua_file($path);
    if ($content === '' || !str_contains($content, $needle)) {
        $failures[] = "{$label}: {$path} is missing {$needle}";
    }
}

function cua_not_has(string $path, string $needle, string $label): void
{
    global $checks, $failures;
    $checks++;
    $content = cua_file($path);
    if ($content !== '' && str_contains($content, $needle)) {
        $failures[] = "{$label}: {$path} must not contain {$needle}";
    }
}

function cua_count(string $path, string $needle, int $expected, string $label): void
{
    global $checks, $failures;
    $checks++;
    $content = cua_file($path);
    $actual = $content === '' ? 0 : substr_count($content, $needle);
    if ($actual !== $expected) {
        $failures[] = "{$label}: {$path} expected {$expected} occurrence(s) of {$needle}, found {$actual}";
    }
}

$renderFiles = [
    'includes/public-campaign-page.php',
    'includes/campaign-media-landing.php',
    'instant-win.php',
    'stamp-card.php',
    'survey-feedback.php',
    'check-in-reward.php',
    'rsvp-event.php',
];

cua_has('includes/campaign-user-details.php', 'function mg_campaign_user_details_context', 'Shared contact context');
cua_has('includes/campaign-user-details.php', 'function mg_campaign_render_user_details', 'Shared contact renderer');
cua_has('includes/campaign-user-details.php', "(int)(\$user['id'] ?? 0) > 0", 'Authenticated-session detection');
cua_has('includes/campaign-user-details.php', 'data-user-authenticated="<?= $loggedIn ? \'1\' : \'0\' ?>"', 'Authenticated state marker');
cua_has('includes/campaign-user-details.php', "<?= \$loggedIn ? '' : ' open' ?>", 'Guest-open authenticated-closed default');
cua_has('includes/campaign-user-details.php', 'autocomplete="email"', 'Email autocomplete');
cua_has('includes/campaign-user-details.php', 'autocomplete="tel"', 'Phone autocomplete');

foreach ($renderFiles as $path) {
    cua_has($path, "campaign-user-details.php", "Shared helper include for {$path}");
    cua_has($path, 'mg_campaign_render_user_details($prefill)', "Shared accordion usage for {$path}");
    cua_not_has($path, '<label>Name<input name="name"', "No duplicate direct contact fields for {$path}");
}

cua_has('assets/css/campaign-landing-foundation.css', '.mg-campaign-user-details', 'Accordion styling');
cua_has('assets/css/campaign-landing-foundation.css', '.mg-campaign-user-details[open]', 'Open accordion state');
cua_has('assets/css/campaign-landing-foundation.css', ':has(> .mg-rl-join-desktop .mg-campaign-user-details[open])', 'Open-details bottom alignment');
cua_has('assets/css/campaign-landing-foundation.css', 'align-self: end !important', 'Bottom alignment rule');
cua_has('assets/css/campaign-landing-foundation.css', 'align-self: start !important', 'Closed-details top alignment rule');
cua_has('assets/css/campaign-landing-foundation.css', '.mg-stamp-summary-card', 'Single Stamp summary card styling');
cua_has('assets/css/campaign-landing-foundation.css', '.mg-stamp-summary-updates', 'Stamp results and updates styling');

cua_has('assets/js/public-campaign.js', 'function revealInvalidControl', 'Invalid-control reveal helper');
cua_has('assets/js/public-campaign.js', "invalid.closest('[data-campaign-user-details]')", 'Invalid contact accordion lookup');
cua_count('assets/js/public-campaign.js', 'revealInvalidControl(invalid);', 2, 'Both validation paths reveal collapsed details');

cua_not_has('stamp-card.php', 'mg_campaign_landing_render_bottom_cards([', 'Stamp no longer uses three generic cards');
cua_has('stamp-card.php', 'class="mg-rl-bottom mg-stamp-summary"', 'Stamp single full-width summary');
cua_count('stamp-card.php', 'class="mg-rl-card mg-stamp-summary-card"', 1, 'Exactly one Stamp summary card');
cua_has('stamp-card.php', 'Item details', 'Stamp item details section');
cua_has('stamp-card.php', 'Reward &amp; campaign rules', 'Stamp campaign rules section');
cua_has('stamp-card.php', 'Messages, results &amp; updates', 'Stamp combined results section');
cua_count('stamp-card.php', 'data-campaign-result', 1, 'One centralized Stamp campaign result');
cua_count('stamp-card.php', 'data-stamp-card-status', 1, 'One centralized Stamp status');
cua_has('stamp-card.php', 'data-stamp-summary-state', 'Stamp live summary state');
cua_has('assets/js/public-stamp-card.js', "page.querySelector('[data-stamp-summary-state]')", 'Stamp runtime targets combined summary');

if ($failures !== []) {
    fwrite(STDERR, "Campaign User Details Accordion v1 validation failed:\n- " . implode("\n- ", array_values(array_unique($failures))) . "\n");
    exit(1);
}

echo "Campaign User Details Accordion v1 validation passed ({$checks} checks).\n";
