<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'resolver' => $root . '/api/account/_action_center_product_media.php',
    'endpoint' => $root . '/api/account/action-center.php',
    'agent_cards' => $root . '/includes/personal-agent/gift-result-cards.php',
    'browser' => $root . '/assets/js/gift-action-center-reward-images.js',
    'inbox' => $root . '/inbox.php',
    'sent' => $root . '/sent.php',
    'claimed' => $root . '/claimed.php',
    'agent' => $root . '/agent.php',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) throw new RuntimeException("Missing {$label} file: {$path}");
}

$read = static fn(string $key): string => (string) file_get_contents($files[$key]);
$resolver = $read('resolver');
$endpoint = $read('endpoint');
$agentCards = $read('agent_cards');
$browser = $read('browser');

$checks = [
    'Resolver scopes action records to the signed-in owner' => str_contains($resolver, 'WHERE ac.user_id=? AND ac.public_id IN'),
    'Resolver follows the Microgift instance product link' => str_contains($resolver, 'INNER JOIN microgift_instances i ON i.id=ac.instance_id') && str_contains($resolver, 'LEFT JOIN catalog_products cp ON cp.id=i.product_id'),
    'Resolver selects the product-version cover role' => str_contains($resolver, "pva.role='cover'") && str_contains($resolver, "cover.status='ready'"),
    'Resolver exposes the public product image URL' => str_contains($resolver, "'product_image_url'") && str_contains($resolver, "/api/public/media.php?asset="),
    'Resolver exposes product and version references' => str_contains($resolver, "'product_id'") && str_contains($resolver, "'product_version_id'") && str_contains($resolver, "'product_url'"),
    'Action Center attaches product media after wallet merge' => str_contains($endpoint, "require_once __DIR__ . '/_action_center_product_media.php';") && str_contains($endpoint, 'mg_action_center_attach_product_media($pdo,$userId,$page[\'items\'])'),
    'Personal Agent hydrates account gifts with the same resolver' => str_contains($agentCards, "require_once dirname(__DIR__, 2) . '/api/account/_action_center_product_media.php';") && str_contains($agentCards, 'return mg_action_center_attach_product_media($pdo, $userId, $items);'),
    'Personal Agent puts product images ahead of reward artwork' => strpos($agentCards, "'product_image_url'") !== false && strpos($agentCards, "'product_image_url'") < strpos($agentCards, "'reward_image_url'"),
    'Browser renderer puts product images ahead of reward artwork' => strpos($browser, 'item.product_image_url') !== false && strpos($browser, 'item.product_image_url') < strpos($browser, 'item.reward_image_url'),
    'Browser renderer keeps intentional gift images before campaign artwork' => strpos($browser, 'item.custom_gift_image_url') !== false && strpos($browser, 'item.custom_gift_image_url') < strpos($browser, 'item.reward_image_url'),
    'Merchant avatar remains last-resort browser fallback' => strpos($browser, 'item.merchant_avatar_url') > strpos($browser, 'item.reward_image_url'),
    'Inbox cache-busts the updated image runtime' => str_contains($read('inbox'), 'gift-action-center-reward-images.js?v=1.1.0'),
    'Sent cache-busts the updated image runtime' => str_contains($read('sent'), 'gift-action-center-reward-images.js?v=1.1.0'),
    'Claimed cache-busts the updated image runtime' => str_contains($read('claimed'), 'gift-action-center-reward-images.js?v=1.1.0'),
    'Personal Agent cache-busts gift result cards while preserving the established contract marker' => str_contains($read('agent'), 'personal-agent-gift-results.js?v=1.0.0&image=1.1.0'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Gift product image consistency contract failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Gift product image consistency contract passed (' . count($checks) . ' checks).' . PHP_EOL;
