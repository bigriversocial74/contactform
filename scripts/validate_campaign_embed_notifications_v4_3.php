<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$helper = $read('api/public/campaigns/_merchant_notifications.php');
$page = $read('merchant-campaign-embed-leads.php');
$js = $read('assets/js/campaign-embed-leads.js');
$css = $read('assets/css/campaign-embed-leads.css');
$sqlFiles = glob($root . '/database/*campaign*embed*notification*v4*3*.sql') ?: [];

$assert('Merchant notification helper exists', $helper !== '');
$assert('Helper detects notifications context_json adaptively', str_contains($helper, 'mg_public_campaign_notification_has_column') && str_contains($helper, 'context_json'));
$assert('Helper keeps old notification insert fallback', str_contains($helper, 'INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at)'));
$assert('Helper creates website embed lead notification type', str_contains($helper, 'merchant_campaign_website_embed_lead'));
$assert('Helper uses website embed lead title copy', str_contains($helper, 'New website embed lead from'));
$assert('Helper deep-links to filtered Embed Leads page', str_contains($helper, '/merchant-campaign-embed-leads.php?') && str_contains($helper, 'origin_host'));
$assert('Helper stores attribution context when available', str_contains($helper, 'campaign_embed_notification_v4_3') && str_contains($helper, 'crm_contact_id') && str_contains($helper, 'page_url') && str_contains($helper, 'embed_mode'));
$assert('Helper keeps fallback CRM notification action', str_contains($helper, '/merchant-crm.php?campaign='));
$assert('Helper preserves existing-contact suppression', str_contains($helper, "reason' => 'existing_contact'") || str_contains($helper, 'reason\' => \'existing_contact'));

$assert('Embed Leads page labels notifications v4.3', str_contains($page, 'Campaign Embed Notifications v4.3') && str_contains($page, 'Merchant Follow-Up Signals'));
$assert('Embed Leads page links Notifications', str_contains($page, '/merchant-notifications.php'));
$assert('Embed Leads page includes notification badge target', str_contains($page, 'data-embed-leads-notification-badge'));

$assert('Embed Leads JS renders notification badge', str_contains($js, 'function renderNotificationBadge'));
$assert('Embed Leads JS counts last 24 hours', str_contains($js, '24 * 60 * 60 * 1000'));
$assert('Embed Leads JS links badge to Notifications', str_contains($js, '/merchant-notifications.php'));
$assert('Embed Leads JS calls notification badge render', str_contains($js, 'renderNotificationBadge(data.rows || [])'));

$assert('Embed Leads CSS styles notification badge', str_contains($css, '.mg-embed-leads-notification-badge'));
$assert('No v4.3 SQL migration added', count($sqlFiles) === 0);

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Embed Notifications v4.3 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Embed Notifications v4.3 validation passed.' . PHP_EOL;
