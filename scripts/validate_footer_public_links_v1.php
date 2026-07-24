<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

$page = $read('creator-campaigns-overview.php');
$routes = $read('config/security-route-policy.php');
$cookies = $read('cookies.php');
$consent = $read('includes/cookie-consent.php');
$footerRuntime = $read('assets/js/footer-public-links-v1.js');

$check(str_contains($page, "'id' => 'creator-campaigns-overview'"), 'Public creator campaigns page manifest is present.');
$check(str_contains($page, 'Merchant & Creator Campaigns'), 'Public creator campaigns page title is present.');
$check(str_contains($routes, "'creator-campaigns-overview.php'"), 'Public route policy includes creator campaigns overview.');
$check(str_contains($cookies, 'Cookie Settings controls on this page'), 'Cookie Policy directs settings access to the policy page.');
$check(!str_contains($cookies, 'or in the site footer'), 'Cookie Policy no longer directs users to footer settings.');
$check(str_contains($consent, '/assets/js/footer-public-links-v1.js?v=1.0.0'), 'Footer navigation runtime is loaded.');
$check(str_contains($footerRuntime, "'/creator-campaigns-overview.php'"), 'Footer runtime adds creator campaigns overview.');
$check(str_contains($footerRuntime, "'/pitch-deck.php'"), 'Footer runtime adds Pitch Deck.');
foreach (['/index.php','/pricing.php','/investors.php','/mcp-server.php','/signin.php'] as $href) {
    $check(str_contains($footerRuntime, "'{$href}'"), "Footer runtime removes {$href} from the bottom row.");
}
$check(str_contains($footerRuntime, '.mg-footer-cookie-settings'), 'Footer runtime removes the Cookie Settings control.');

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "[FAIL] {$failure}\n");
    exit(1);
}

echo "Footer public links v1 contract passed.\n";
