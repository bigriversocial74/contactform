<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'resolver' => $root . '/api/account/_action_center_product_media.php',
    'contract' => $root . '/api/account/_action_center_contract.php',
    'endpoint' => $root . '/api/account/action-center.php',
    'agent_cards' => $root . '/includes/personal-agent/gift-result-cards.php',
    'adapter' => $root . '/assets/js/action-center-contract-v2.js',
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
$contract = $read('contract');
$endpoint = $read('endpoint');
$agentCards = $read('agent_cards');
$adapter = $read('adapter');
$browser = $read('browser');

$checks = [
    'Resolver scopes action records to the signed-in owner' => str_contains($resolver, 'WHERE ac.user_id=? AND ac.public_id IN'),
    'Resolver follows the Microgift instance product link' => str_contains($resolver, 'INNER JOIN microgift_instances i ON i.id=ac.instance_id') && str_contains($resolver, 'LEFT JOIN catalog_products cp ON cp.id=i.product_id'),
    'Resolver selects the exact product-version cover role' => str_contains($resolver, "pva.product_version_id=i.product_version_id") && str_contains($resolver, "pva.role='cover'") && str_contains($resolver, "cover.status='ready'"),
    'Resolver distinguishes exact and current version bases' => str_contains($resolver, "'exact_instance_version'") && str_contains($resolver, "'current_catalog_fallback'"),
    'Resolver does not expose an unpublished product URL' => str_contains($resolver, "'product_url' => $isPublic") && str_contains($resolver, "'product_is_public' => $isPublic"),
    'Contract keeps the gift snapshot separate from linked product data' => str_contains($contract, "'snapshot' => [") && str_contains($contract, "'linked_resource' => $linkedResource"),
    'Contract presentation resolves an exact catalog cover first' => strpos($contract, "catalog_product_version_cover") !== false && strpos($contract, "catalog_product_version_cover") < strpos($contract, "gift.custom_image"),
    'Contract records the selected image source' => str_contains($contract, "'image_source' => $presentation['source']"),
    'Action Center maps all results through the shared contract' => str_contains($endpoint, "require_once __DIR__ . '/_action_center_contract.php';") && str_contains($endpoint, 'mg_action_center_contract_items('),
    'Personal Agent consumes the same server contract' => str_contains($agentCards, "require_once dirname(__DIR__, 2) . '/api/account/_action_center_contract.php';") && str_contains($agentCards, 'mg_action_center_contract_view'),
    'Browser adapter maps presentation image to the existing card view' => str_contains($adapter, 'product_image_url: text(presentation.image_url)') && str_contains($adapter, 'image_source: text(presentation.image_source'),
    'Legacy image renderer receives the adapter product image first' => strpos($browser, 'item.product_image_url') !== false && strpos($browser, 'item.product_image_url') < strpos($browser, 'item.reward_image_url'),
    'Inbox cache-busts the updated image runtime' => str_contains($read('inbox'), 'gift-action-center-reward-images.js?v=1.1.0'),
    'Sent cache-busts the updated image runtime' => str_contains($read('sent'), 'gift-action-center-reward-images.js?v=1.1.0'),
    'Claimed cache-busts the updated image runtime' => str_contains($read('claimed'), 'gift-action-center-reward-images.js?v=1.1.0'),
    'Personal Agent cache-busts gift result cards while preserving the established marker' => str_contains($read('agent'), 'personal-agent-gift-results.js?v=1.0.0&image=1.1.0'),
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
