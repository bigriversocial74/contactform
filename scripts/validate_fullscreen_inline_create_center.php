<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$paths=[
    'menu'=>$root.'/includes/header-templates/create-menu.php',
    'post_modal'=>$root.'/includes/header-components/post-composer-modal.php',
    'controller'=>$root.'/assets/js/create-center-inline.js',
    'storefront_guard'=>$root.'/assets/js/create-center-storefront-preserve.js',
    'post_success'=>$root.'/assets/js/create-center-post-success.js',
    'create_css'=>$root.'/assets/css/create-center-inline.css',
    'post_css'=>$root.'/assets/css/post-composer-modal.css',
    'post_controller'=>$root.'/assets/js/global-post-composer.js',
];

$content=[];
foreach($paths as $key=>$path){
    if(!is_file($path)){
        fwrite(STDERR,"Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key]=(string)file_get_contents($path);
}

$checks=[
    'create center uses a true full-screen workspace' =>
        str_contains($content['create_css'],'width:100vw!important')
        && str_contains($content['create_css'],'height:100dvh!important')
        && str_contains($content['create_css'],'grid-template-columns:240px minmax(0,1fr)'),
    'create center keeps the upper-right X close control' =>
        str_contains($content['menu'],'data-create-menu-close aria-label="Close create center">×</button>')
        && str_contains($content['create_css'],'.mg-create-menu-close'),
    'all five merchant tools use inline forms instead of page-only navigation' =>
        substr_count($content['menu'],'data-create-inline-form=')===5
        && str_contains($content['menu'],'data-create-inline-form="product"')
        && str_contains($content['menu'],'data-create-inline-form="campaign"')
        && str_contains($content['menu'],'data-create-inline-form="reward"')
        && str_contains($content['menu'],'data-create-inline-form="storefront"')
        && str_contains($content['menu'],'data-create-inline-form="location"'),
    'each merchant inline form has a dedicated success confirmation' =>
        substr_count($content['menu'],'data-create-inline-success=')===5
        && substr_count($content['menu'],'data-create-success-message')===5
        && substr_count($content['menu'],'data-create-inline-reset=')===5,
    'product form saves and publishes through the existing catalog runtime' =>
        str_contains($content['controller'],"MG.post('/api/catalog/builder-draft.php'")
        && str_contains($content['controller'],"MG.api('/api/catalog/upload.php'")
        && str_contains($content['controller'],"MG.get('/api/catalog/builder-draft.php')"),
    'campaign and reward forms submit through production merchant endpoints' =>
        str_contains($content['controller'],"MG.post('/api/merchant/campaigns.php'")
        && str_contains($content['controller'],"MG.post('/api/merchant/reward-templates.php'")
        && str_contains($content['controller'],"MG.get('/api/merchant/reward-templates.php?status=active')"),
    'storefront form preserves current logo and cover assets during inline saves' =>
        str_contains($content['storefront_guard'],'currentRevision.logo_asset_public_id')
        && str_contains($content['storefront_guard'],'currentRevision.cover_asset_public_id')
        && str_contains($content['storefront_guard'],"MG.post('/api/merchant/storefront.php'")
        && str_contains($content['post_modal'],'/assets/js/create-center-storefront-preserve.js'),
    'location form submits directly to the protected merchant location endpoint' =>
        str_contains($content['controller'],"MG.post('/api/merchant/locations.php'")
        && str_contains($content['menu'],'name="claim_code"')
        && str_contains($content['menu'],'name="address_line1"'),
    'post composer matches the full-screen create center and keeps an upper-right X' =>
        str_contains($content['post_modal'],'class="mg-post-composer-x"')
        && str_contains($content['post_css'],'width:100vw')
        && str_contains($content['post_css'],'height:100dvh')
        && str_contains($content['post_css'],'.mg-post-composer-x'),
    'post submission remains direct and reports a persistent success confirmation' =>
        str_contains($content['post_controller'],"MG.post('/api/social/posts.php'")
        && str_contains($content['post_success'],'mg-create-post-success-toast')
        && str_contains($content['post_success'],'published|saved as a draft')
        && str_contains($content['post_modal'],'/assets/js/create-center-post-success.js'),
    'inline navigation prevents the old page-link behavior for modal tools' =>
        str_contains($content['controller'],"event.stopImmediatePropagation()")
        && str_contains($content['controller'],"showView(inline.dataset.createInlineTarget)")
        && str_contains($content['menu'],'data-create-inline-target="product"'),
    'large responsive form controls remain usable on desktop and mobile' =>
        str_contains($content['create_css'],'min-height:54px')
        && str_contains($content['create_css'],'.mg-create-form-grid-4')
        && str_contains($content['create_css'],'@media(max-width:820px)')
        && str_contains($content['post_css'],'min-height:190px'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nFullscreen inline create center validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nFullscreen inline create center contract: 12/12.\n";
