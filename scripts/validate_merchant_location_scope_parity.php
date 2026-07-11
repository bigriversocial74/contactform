<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$paths=[
    'api'=>$root.'/api/merchant/locations.php',
    'builder'=>$root.'/api/catalog/_publish_distribution.php',
    'client'=>$root.'/assets/js/merchant-workspace.js',
    'test'=>$root.'/tests/phpunit/MerchantLocationsPageContractTest.php',
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
    'location API reads workspace-owned rows shown by product builder' =>
        str_contains($source['api'],'LEFT JOIN merchant_workspaces scope_mw ON scope_mw.id=ml.workspace_id')
        && str_contains($source['api'],'WHERE (ml.merchant_user_id=? OR scope_mw.merchant_user_id=?)')
        && str_contains($source['api'],'$stmt->execute([$merchantId,$merchantId])'),
    'product builder still resolves active locations from merchant workspace ownership' =>
        str_contains($source['builder'],'INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id')
        && str_contains($source['builder'],'WHERE mw.merchant_user_id=? AND ml.status=\'active\''),
    'location editor loads the canonical merchant locations endpoint' =>
        str_contains($source['client'],"Microgifter.get('/api/merchant/locations.php')"),
    'legacy direct-owner rows remain visible during ownership transition' =>
        str_contains($source['api'],'ml.merchant_user_id=? OR scope_mw.merchant_user_id=?'),
    'existing location edits accept either canonical ownership path' =>
        str_contains($source['api'],'AND (ml.merchant_user_id=? OR scope_mw.merchant_user_id=?)')
        && str_contains($source['api'],'$existing->execute([$locationId,$merchantId,$merchantId])'),
    'editing a legacy row normalizes both workspace and merchant ownership' =>
        str_contains($source['api'],'SET workspace_id=?,merchant_user_id=?,name=?,location_code=?'),
    'package location counts use the same dual ownership scope' =>
        str_contains($source['api'],"AND ml.status<>'archived'")
        && substr_count($source['api'],'LEFT JOIN merchant_workspaces scope_mw ON scope_mw.id=ml.workspace_id')>=4,
    'location code uniqueness includes workspace-linked legacy rows' =>
        str_contains($source['api'],'AND ml.location_code=?')
        && str_contains($source['api'],'$stmt->execute([$merchantId,$merchantId,$candidate,$excludePublicId])'),
    'claim readiness is tied to the owned location row rather than stale owner metadata' =>
        str_contains($source['api'],'WHERE mcc.location_id=ml.id')
        && !str_contains($source['api'],'WHERE mcc.merchant_user_id=ml.merchant_user_id'),
    'contract test protects builder and locations-page scope parity' =>
        str_contains($source['test'],'testMerchantLocationReadAndWriteScopeMatchesProductBuilderOwnership')
        && str_contains($source['test'],'testClaimCodeStatusUsesOwnedLocationInsteadOfStaleMerchantColumn'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nMerchant location scope parity validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nMerchant location scope parity contract: 10/10.".PHP_EOL;
