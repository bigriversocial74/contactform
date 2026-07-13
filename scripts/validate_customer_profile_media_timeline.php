<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    'crm' => $root . '/includes/merchant-crm.php',
    'page' => $root . '/merchant-customer.php',
    'timeline_js' => $root . '/assets/js/merchant-customer-timeline-milestones.js',
    'navigation_css' => $root . '/assets/css/merchant-customer-profile-navigation.css',
];

$files = [];
foreach ($targets as $key => $path) {
    $content = file_get_contents($path);
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
    $files[$key] = $content;
}

$checks = [
    'watch heartbeat is identified' => str_contains($files['crm'], "'watch_reward_progress'"),
    'listen heartbeat is identified' => str_contains($files['crm'], "'listen_reward_progress'"),
    'watch first touch becomes started' => str_contains($files['crm'], "'watch_reward_started'"),
    'listen first touch becomes started' => str_contains($files['crm'], "'listen_reward_started'"),
    'existing campaign touch is checked under lock' => str_contains($files['crm'], 'merchant_crm_contact_campaigns') && str_contains($files['crm'], 'FOR UPDATE'),
    'repeat heartbeat skips timeline insert' => str_contains($files['crm'], '$recordEvent = !$isMediaProgress || !$campaignLinkExists'),
    'repeat heartbeat does not increment campaign event count' => str_contains($files['crm'], 'if ($isMediaProgress && $campaignLinkExists)') && str_contains($files['crm'], 'UPDATE merchant_crm_contact_campaigns SET last_event_at=NOW()'),
    'suppression state is returned' => str_contains($files['crm'], "'progress_heartbeat_suppressed'"),
    'back to contacts link is server rendered' => str_contains($files['page'], 'Back to Contacts') && str_contains($files['page'], '/merchant-crm.php?tab=contacts'),
    'timeline compactor is loaded after profile runtime' => strpos($files['page'], 'merchant-customer-profile.js') < strpos($files['page'], 'merchant-customer-timeline-milestones.js'),
    'watch progress history is removed from display' => str_contains($files['timeline_js'], "'watch video reward progress':true"),
    'listen progress history is removed from display' => str_contains($files['timeline_js'], "'listen music reward progress':true"),
    'earned milestone explanation remains visible' => str_contains($files['timeline_js'], 'Earned reward levels, claims, and redemptions will appear here.'),
    'timeline updates are observed after async profile load' => str_contains($files['timeline_js'], 'MutationObserver'),
    'back navigation has keyboard focus styling' => str_contains($files['navigation_css'], ':focus-visible'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Customer profile media timeline validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Customer profile media timeline contract: 15/15 checks passed.' . PHP_EOL;
