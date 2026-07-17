<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'query' => 'api/account/_action_center.php',
    'wallet' => 'api/account/_action_center_wallet.php',
    'resolver' => 'api/account/_action_center_product_media.php',
    'contract' => 'api/account/_action_center_contract.php',
    'list' => 'api/account/action-center.php',
    'detail' => 'api/account/action-center-detail.php',
    'media' => 'api/account/action-center-product-media.php',
    'adapter' => 'assets/js/action-center-contract-v2.js',
    'feed' => 'assets/js/gift-action-center-feed-v3.js',
    'media_view' => 'assets/js/gift-product-media-view.js',
    'include' => 'includes/gift-action-center.php',
    'agent' => 'includes/personal-agent/gift-result-cards.php',
    'docs' => 'docs/action-center-contract-v2.md',
];

$source = [];
foreach ($paths as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException("Missing {$key} file: {$relative}");
    $source[$key] = (string) file_get_contents($path);
}

$checks = [
    'Contract declares version 2' => str_contains($source['contract'], 'MG_ACTION_CENTER_CONTRACT_VERSION = 2'),
    'Contract separates immutable gift snapshots' => str_contains($source['contract'], "'gift' => [") && str_contains($source['contract'], "'snapshot' => [") && str_contains($source['contract'], "'title_source' => 'gift_snapshot'"),
    'Contract separates live linked resources' => str_contains($source['contract'], "'linked_resource' => \$linkedResource") && str_contains($source['contract'], "'version_basis' => \$productBasis"),
    'Contract includes authoritative presentation' => str_contains($source['contract'], "'presentation' => [") && str_contains($source['contract'], "'image_source' => \$presentation['source']"),
    'Contract includes source, participants, merchant, location, and redemption' => str_contains($source['contract'], "'source' => \$source") && str_contains($source['contract'], "'participants' => [") && str_contains($source['contract'], "'merchant' => [") && str_contains($source['contract'], "'location' => [") && str_contains($source['contract'], "'redemption' => ["),
    'Contract includes capability values and reasons' => str_contains($source['contract'], "'capabilities' => \$capabilityContract['values']") && str_contains($source['contract'], "'capability_reasons' => \$capabilityContract['reasons']"),
    'Public contract does not publish raw metadata' => !str_contains($source['contract'], "'metadata_json' =>") && !str_contains($source['contract'], "'instance_metadata_json' =>"),
    'Public contract does not publish internal database IDs' => !str_contains($source['contract'], "'internal_id' =>") && !str_contains($source['contract'], "'merchant_user_id' =>") && !str_contains($source['contract'], "'owner_user_id' =>"),
    'Canonical read query selects immutable snapshot and source fields' => str_contains($source['query'], 'i.title_snapshot') && str_contains($source['query'], 'i.description_snapshot') && str_contains($source['query'], 'i.source_type instance_source_type') && str_contains($source['query'], 'i.source_reference instance_source_reference'),
    'Canonical search covers linked product title and slug' => str_contains($source['query'], 'catalog_products cp_search') && str_contains($source['query'], 'cp_search.slug LIKE ?') && str_contains($source['query'], "COALESCE(cpv_search.title,'') LIKE ?"),
    'Canonical owner scope is preserved' => str_contains($source['query'], 'ac.user_id=? AND ac.public_id=?') && str_contains($source['query'], 'ac.archived_at IS NULL'),
    'Resolver uses exact issued product version first' => str_contains($source['resolver'], 'pva.product_version_id=i.product_version_id') && str_contains($source['resolver'], "'exact_instance_version'"),
    'Resolver labels current catalog fallback explicitly' => str_contains($source['resolver'], "'current_catalog_fallback'"),
    'Resolver keeps unpublished navigation private' => str_contains($source['resolver'], "'product_url' => \$isPublic") && str_contains($source['resolver'], "'product_is_public' => \$isPublic"),
    'List endpoint returns only Contract v2 items' => str_contains($source['list'], 'mg_action_center_contract_items(') && str_contains($source['list'], "'contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION"),
    'Detail endpoint supports canonical and wallet items' => str_contains($source['detail'], 'mg_action_center_detail(') && str_contains($source['detail'], 'mg_ac_wallet_action_id(') && str_contains($source['detail'], 'mg_ac_wallet_public_item('),
    'Detail endpoint uses the shared formatter' => str_contains($source['detail'], 'mg_action_center_contract_items('),
    'Media endpoint returns the shared nested identity' => str_contains($source['media'], "'gift' => \$contract['gift']") && str_contains($source['media'], "'presentation' => \$contract['presentation']") && str_contains($source['media'], "'linked_resource' => \$contract['linked_resource']"),
    'Media endpoint supports wallet and canonical IDs in batches' => str_contains($source['media'], 'mg_ac_wallet_action_id(') && str_contains($source['media'], 'mg_action_center_select_sql()') && str_contains($source['media'], 'mg_ac_wallet_select_sql()'),
    'Media endpoint batches catalog version assets' => str_contains($source['media'], 'catalog_product_version_assets') && str_contains($source['media'], 'WHERE cpv.public_id IN'),
    'Browser adapter is the single Contract v2 view adapter' => str_contains($source['adapter'], 'const VERSION = 2') && str_contains($source['adapter'], 'function view(item)') && str_contains($source['adapter'], 'function mediaView(item)'),
    'Browser adapter does not parse raw metadata' => !str_contains($source['adapter'], 'JSON.parse') && !str_contains($source['adapter'], 'metadata_json'),
    'Browser adapter translates list responses before legacy UI code' => str_contains($source['adapter'], 'normalizeResponse') && str_contains($source['adapter'], '__actionCenterContractV2Wrapped'),
    'Action Center loads the adapter before supporting scripts' => strpos($source['include'], 'action-center-contract-v2.js') !== false && strpos($source['include'], 'action-center-contract-v2.js') < strpos($source['include'], 'gift-action-center-feed-v3.js'),
    'Feed consumes the adapter for direct fetch responses' => str_contains($source['feed'], 'window.MicrogifterActionCenterContract') && str_contains($source['feed'], 'adapter.view(item)'),
    'Feed renders server-authoritative capability gates' => str_contains($source['feed'], '!item.can_send') && str_contains($source['feed'], '!item.can_claim') && str_contains($source['feed'], '!item.can_message') && str_contains($source['feed'], '!item.can_tip'),
    'Media view consumes the nested media adapter' => str_contains($source['media_view'], 'adapter.mediaView') && str_contains($source['media_view'], 'data.items[id]'),
    'Personal Agent uses the same server formatter and PHP view adapter' => str_contains($source['agent'], "_action_center_contract.php") && str_contains($source['agent'], 'mg_action_center_contract_items(') && str_contains($source['agent'], 'mg_action_center_contract_view'),
    'Read endpoints do not create lifecycle mutations' => !str_contains($source['list'], 'INSERT INTO') && !str_contains($source['list'], 'UPDATE microgift_instances') && !str_contains($source['detail'], 'INSERT INTO') && !str_contains($source['media'], 'UPDATE microgift_instances'),
    'Contract documentation defines snapshot, linked-resource, presentation, and mutation boundaries' => str_contains($source['docs'], 'gift.snapshot') && str_contains($source['docs'], 'linked_resource') && str_contains($source['docs'], 'presentation') && str_contains($source['docs'], 'Mutation boundary'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Action Center Contract v2 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Action Center Contract v2 validation passed (' . count($checks) . ' checks).' . PHP_EOL;
