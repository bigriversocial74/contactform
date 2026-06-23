<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'images/reward_saved_limited_coffee_for_two.png',
    'tests/browser/fixtures/stage12i-discovery-wallet-fixture.json',
    'tests/browser/stage12-discovery-wallet-path.spec.js',
    'package.json',
];
$ok = true;
$exists = [];
foreach ($required as $file) {
    $exists[$file] = is_file($root . '/' . $file);
    $ok = $ok && $exists[$file];
}
$fixturePath = $root . '/tests/browser/fixtures/stage12i-discovery-wallet-fixture.json';
$fixture = is_file($fixturePath) ? json_decode((string) file_get_contents($fixturePath), true) : null;
$spec = is_file($root . '/tests/browser/stage12-discovery-wallet-path.spec.js') ? (string) file_get_contents($root . '/tests/browser/stage12-discovery-wallet-path.spec.js') : '';
$package = is_file($root . '/package.json') ? (string) file_get_contents($root . '/package.json') : '';
$checks = [
    'fixture_json_valid' => is_array($fixture),
    'fixture_image_path' => is_array($fixture) && (($fixture['offer']['image_path'] ?? '') === '/images/reward_saved_limited_coffee_for_two.png'),
    'fixture_offer_wallet_campaign' => is_array($fixture) && isset($fixture['offer']['id'], $fixture['wallet_item']['id'], $fixture['campaign']['id']),
    'browser_spec_mocks_offer_search' => str_contains($spec, '/api/public/offers/search.php'),
    'browser_spec_mocks_feedback' => str_contains($spec, '/api/public/offers/feedback.php') && str_contains($spec, 'add_success'),
    'browser_spec_checks_missing_image' => str_contains($spec, 'fixture.offer.image_path') && str_contains($spec, 'expect(image.status()).toBe(200)'),
    'package_script_added' => str_contains($package, 'test:browser:stage12'),
];
foreach ($checks as $pass) { $ok = $ok && $pass; }
echo json_encode(['ok' => $ok, 'exists' => $exists, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
