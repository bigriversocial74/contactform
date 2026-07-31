<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pagePath = $root . '/investor-access.php';
$errors = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$errors, &$checks): void {
    $checks++;
    if (!$condition) $errors[] = $message;
};

$assert(is_file($pagePath), 'investor-access.php must exist.');
$page = is_file($pagePath) ? (string) file_get_contents($pagePath) : '';

$assert(str_contains($page, "require __DIR__ . '/includes/personal-agent-sidebar.php'"), 'Investor Access must use the unified customer sidebar.');
$assert(!str_contains($page, "require __DIR__ . '/includes/account-sidebar.php'"), 'Investor Access must not mount the retired commerce sidebar.');
$assert(str_contains($page, '/assets/css/personal-agent-chat-history.css?v=1.2.0'), 'Unified sidebar CSS dependency must be loaded.');
$assert(str_contains($page, '/assets/js/personal-agent-chat-history.js?v=1.2.0'), 'Unified sidebar runtime must be loaded.');
$assert(str_contains($page, "\$agent_sidebar_mode = 'personal';"), 'Investor Access must explicitly use personal sidebar mode.');
$assert(str_contains($page, "\$use_inbox_sidebar = true;"), 'Investor Access must retain the unified customer-shell contract.');
$assert(str_contains($page, '<section class="mg-app-shell mg-account-app">'), 'Investor Access must use the modern app shell.');
$assert(str_contains($page, '<main class="mg-app-workspace">'), 'Investor Access content must remain in the app workspace.');
$assert(str_contains($page, 'data-investor-access'), 'Investor Access runtime root must remain present.');
$assert(str_contains($page, 'data-csrf-token'), 'Investor Access CSRF payload must remain present.');
$assert(str_contains($page, 'data-access-form'), 'Investor application form must remain present.');
$assert(str_contains($page, 'data-access-submit'), 'Investor application submit action must remain present.');
$assert(str_contains($page, 'data-access-withdraw'), 'Investor application withdrawal action must remain present.');
$assert(str_contains($page, '/assets/css/investment-system-v1.css?v=1.0.0'), 'Investment page styling must remain loaded.');
$assert(str_contains($page, '/assets/js/investor-access-v1.js?v=1.0.0'), 'Investor Access runtime must remain loaded.');

if ($errors) {
    fwrite(STDERR, "Investor Access layout repair validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Investor Access layout repair validation passed ({$checks} assertions).\n";
