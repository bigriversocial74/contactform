<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$paths=[
    'scope'=>$root.'/includes/merchant-location-scope.php',
    'merchant_core'=>$root.'/api/merchant/_merchant.php',
    'locations'=>$root.'/api/merchant/locations.php',
    'claims_core'=>$root.'/api/merchant/_claims.php',
    'claim_codes'=>$root.'/api/merchant/claim-codes.php',
    'claim_action'=>$root.'/api/merchant/claim-code-action.php',
    'claim_detail'=>$root.'/api/merchant/claim-detail.php',
    'claim_exception'=>$root.'/api/merchant/claim-exception.php',
    'overview'=>$root.'/api/merchant/overview.php',
    'package_limits'=>$root.'/api/account/package-limits.php',
    'world'=>$root.'/api/world-canvas/_locations.php',
    'scanner_page'=>$root.'/merchant-scanner-settings.php',
    'scanner_settings'=>$root.'/api/merchant/scanner-settings.php',
    'scanner_devices'=>$root.'/api/merchant/scanner-devices.php',
    'redeem_locations'=>$root.'/api/account/action-center-redeem-locations.php',
    'builder'=>$root.'/api/catalog/_publish_distribution.php',
    'agent_context'=>$root.'/includes/ai/merchant-context-builder.php',
    'client'=>$root.'/assets/js/merchant-workspace.js',
    'phpunit'=>$root.'/tests/phpunit/MerchantLocationsPageContractTest.php',
];

$source=[];
foreach($paths as $key=>$path){
    if(!is_file($path)){
        fwrite(STDERR,"Missing required file: {$path}\n");
        exit(1);
    }
    $source[$key]=(string)file_get_contents($path);
}

$checks=[
    'canonical scope makes a valid workspace authoritative' =>
        str_contains($source['scope'],'A valid workspace relationship is authoritative')
        && str_contains($source['scope'],'$workspaceAlias}.id=?')
        && str_contains($source['scope'],'$workspaceAlias}.id IS NULL')
        && str_contains($source['scope'],'$locationAlias}.merchant_user_id=?')
        && !str_contains($source['scope'],'merchant_user_id=? OR'),
    'merchant APIs load the shared location scope' =>
        str_contains($source['merchant_core'],"includes/merchant-location-scope.php"),
    'locations API reads workspace rows and orphan legacy rows with one scope' =>
        str_contains($source['locations'],'mg_merchant_location_scope_context($workspace)')
        && str_contains($source['locations'],"mg_merchant_location_scope_join('ml','location_scope_mw')")
        && str_contains($source['locations'],"mg_merchant_location_scope_condition('ml','location_scope_mw')")
        && !str_contains($source['locations'],'WHERE ml.merchant_user_id=?'),
    'location writes normalize both canonical ownership columns' =>
        str_contains($source['locations'],'SET workspace_id=?,merchant_user_id=?,name=?,location_code=?')
        && str_contains($source['locations'],"'ownership_normalized'=>true")
        && str_contains($source['locations'],'$ownerMerchantId'),
    'location package limits and primary selection use the canonical scope' =>
        str_contains($source['locations'],'mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId)')
        && str_contains($source['locations'],'UPDATE merchant_locations ml')
        && str_contains($source['locations'],"mg_merchant_location_scope_condition('ml','location_scope_mw')"),
    'claim location authorization uses the shared ownership helper' =>
        str_contains($source['claims_core'],'mg_merchant_location_find_by_public_id(')
        && str_contains($source['claims_core'],'mg_claim_code_assert_no_active_duplicate(')
        && str_contains($source['claims_core'],"INNER JOIN merchant_locations ml ON ml.id=mcc.location_id"),
    'claim-code reads and writes trust the owned location instead of stale owner metadata' =>
        str_contains($source['claim_codes'],"mg_merchant_location_scope_condition('ml','location_scope_mw')")
        && str_contains($source['claim_codes'],'$ownerMerchantId')
        && !str_contains($source['claim_codes'],'ml.workspace_id=? AND ml.merchant_user_id=?')
        && str_contains($source['claim_action'],'mg_merchant_location_normalize_ownership('),
    'claim detail and exceptions resolve the workspace owner separately from the actor' =>
        str_contains($source['claim_detail'],'$ownerMerchantId')
        && str_contains($source['claim_detail'],'mg_merchant_location_find_by_id(')
        && str_contains($source['claim_exception'],'$actorUserId')
        && str_contains($source['claim_exception'],'$ownerMerchantId'),
    'dashboard and account package usage report the same location count' =>
        str_contains($source['overview'],'mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId)')
        && str_contains($source['package_limits'],'mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId)'),
    'World Canvas uses the canonical scope for rows lookup and default location' =>
        substr_count($source['world'],"mg_merchant_location_scope_condition('ml','location_scope_mw')")>=3
        && str_contains($source['world'],'mg_world_location_workspace_scope'),
    'scanner settings and devices write owner metadata while preserving actor permissions' =>
        str_contains($source['scanner_page'],'$ownerMerchantId')
        && str_contains($source['scanner_settings'],'$ownerMerchantId')
        && str_contains($source['scanner_devices'],'$actorUserId')
        && str_contains($source['scanner_devices'],'$ownerMerchantId'),
    'customer redemption locations use the same canonical merchant scope' =>
        str_contains($source['redeem_locations'],"mg_merchant_location_scope_join('ml','location_scope_mw')")
        && str_contains($source['redeem_locations'],"mg_merchant_location_scope_condition('ml','location_scope_mw')"),
    'Product Builder continues resolving active workspace locations' =>
        str_contains($source['builder'],'INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id')
        && str_contains($source['builder'],"WHERE mw.merchant_user_id=? AND ml.status='active'"),
    'Merchant Agent context continues reading the canonical workspace location set' =>
        str_contains($source['agent_context'],'FROM merchant_locations WHERE workspace_id = ?'),
    'merchant location UI consumes the canonical endpoint' =>
        str_contains($source['client'],"Microgifter.get('/api/merchant/locations.php')"),
    'PHPUnit protects app-wide ownership parity' =>
        str_contains($source['phpunit'],'testCanonicalLocationScopeIsWorkspaceAuthoritative')
        && str_contains($source['phpunit'],'testLocationsRemainConsistentAcrossApplicationConsumers'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nMerchant location app-wide parity validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nMerchant location app-wide parity v2 contract: 15/15.".PHP_EOL;
