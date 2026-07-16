<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'page'=>$root.'/merchant-locations.php',
    'view'=>$root.'/includes/merchant-locations-view.php',
    'scope'=>$root.'/includes/merchant-location-scope.php',
    'api'=>$root.'/api/merchant/locations.php',
    'builder'=>$root.'/api/catalog/_publish_distribution.php',
    'css'=>$root.'/assets/css/merchant-locations-redemption.css',
    'js'=>$root.'/assets/js/merchant-locations-tabs.js',
    'navigation'=>$root.'/includes/merchant-navigation.php',
];

$content=[];
foreach($files as $key=>$path){
    if(!is_file($path)){
        fwrite(STDERR,"Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key]=(string)file_get_contents($path);
}

$checks=[
    'merchant locations route keeps shared merchant workspace' =>
        str_contains($content['page'],"$merchantView='locations'")
        && str_contains($content['page'],'includes/merchant-workspace.php')
        && str_contains($content['page'],'merchant-locations-redemption.css'),
    'all five location metrics remain in one horizontal row' =>
        substr_count($content['view'],'data-location-kpi-')===5
        && str_contains($content['css'],'grid-template-columns:repeat(5,minmax(180px,1fr))')
        && str_contains($content['css'],'overflow-x:auto')
        && !str_contains($content['css'],'grid-template-columns:repeat(3'),
    'top tab bar and header add button are removed' =>
        !str_contains($content['view'],'mg-locations-commandbar')
        && !str_contains($content['view'],'mg-locations-tabs')
        && !str_contains($content['view'],'data-location-tab='),
    'right readiness and quick-action sidebar are removed' =>
        !str_contains($content['view'],'<aside')
        && !str_contains($content['view'],'locations-readiness')
        && !str_contains($content['css'],'.mg-locations-side'),
    'location list and editor remain available in the main workspace' =>
        str_contains($content['view'],'id="locations-list-panel"')
        && str_contains($content['view'],'id="location-editor-panel"')
        && str_contains($content['view'],'data-location-list')
        && str_contains($content['view'],'data-location-form')
        && str_contains($content['view'],'data-location-open-add'),
    'merchant locations use workspace authority with orphan-row fallback' =>
        str_contains($content['scope'],'A valid workspace relationship is authoritative')
        && str_contains($content['scope'],'$workspaceAlias}.id IS NULL')
        && str_contains($content['scope'],'$locationAlias}.merchant_user_id=?')
        && str_contains($content['api'],"mg_merchant_location_scope_condition('ml','location_scope_mw')")
        && !str_contains($content['api'],'WHERE ml.merchant_user_id=?'),
    'legacy location edits normalize workspace and merchant ownership' =>
        str_contains($content['api'],'SET workspace_id=?,merchant_user_id=?,name=?,location_code=?')
        && str_contains($content['api'],"'ownership_normalized'=>true")
        && str_contains($content['api'],'$ownerMerchantId'),
    'location codes package counts and primary state share one scope' =>
        str_contains($content['api'],'mg_merchant_unique_location_code(')
        && str_contains($content['api'],'mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId)')
        && str_contains($content['api'],'UPDATE merchant_locations ml')
        && str_contains($content['api'],"mg_merchant_location_scope_condition('ml','location_scope_mw')"),
    'Product Builder remains aligned with active workspace-owned locations' =>
        str_contains($content['builder'],'INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id')
        && str_contains($content['builder'],"WHERE mw.merchant_user_id=? AND ml.status='active'"),
    'add and edit interactions scroll to the always-visible editor' =>
        str_contains($content['js'],"closest('[data-location-open-add]')")
        && str_contains($content['js'],"closest('[data-location]')")
        && str_contains($content['js'],'scrollIntoView')
        && !str_contains($content['js'],'activatePanel'),
    'shared merchant menu keeps Locations under Business Settings' =>
        str_contains($content['navigation'],"'locations' => ['Locations', 'Stores and claim scope', '/merchant-locations.php', 'Business Settings']"),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nMerchant locations display/layout validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nMerchant locations display/layout contract: 10/10.".PHP_EOL;
