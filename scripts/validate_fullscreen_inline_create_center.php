<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$paths=[
    'menu'=>$root.'/includes/header-templates/create-menu.php',
    'runtime'=>$root.'/includes/header-components/post-composer-modal.php',
    'composer'=>$root.'/includes/social-feed-composer.php',
    'controller'=>$root.'/assets/js/create-center-inline.js',
    'post_controller'=>$root.'/assets/js/create-center-post-inline.js',
    'storefront_guard'=>$root.'/assets/js/create-center-storefront-preserve.js',
    'create_css'=>$root.'/assets/css/create-center-inline.css',
    'mobile_post_css'=>$root.'/assets/css/create-center-mobile-post-unified.css',
    'menu_controller'=>$root.'/assets/js/create-menu.js',
    'manage_css'=>$root.'/assets/css/create-center-manage-actions.css',
    'manage_js'=>$root.'/assets/js/create-center-manage-actions.js',
    'list_extension'=>$root.'/includes/header-components/create-list-extension.php',
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
    'all five merchant tools retain direct inline forms' =>
        substr_count($content['menu'],'data-create-inline-form=')===5
        && str_contains($content['menu'],'data-create-inline-form="product"')
        && str_contains($content['menu'],'data-create-inline-form="campaign"')
        && str_contains($content['menu'],'data-create-inline-form="reward"')
        && str_contains($content['menu'],'data-create-inline-form="storefront"')
        && str_contains($content['menu'],'data-create-inline-form="location"'),
    'each merchant inline form retains a dedicated success confirmation' =>
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
        && str_contains($content['runtime'],'/assets/js/create-center-storefront-preserve.js'),
    'location form submits directly to the protected merchant location endpoint' =>
        str_contains($content['controller'],"MG.post('/api/merchant/locations.php'")
        && str_contains($content['menu'],'name="claim_code"')
        && str_contains($content['menu'],'name="address_line1"'),
    'post composer is embedded as the sixth create-center view' =>
        str_contains($content['menu'],'id="mg-create-center-post" data-create-center-view="post"')
        && str_contains($content['menu'],'data-create-inline-target="<?= mg_e($target) ?>"')
        && str_contains($content['composer'],'mg-create-inline-post-composer')
        && !str_contains($content['runtime'],'data-global-post-composer'),
    'post submission remains direct and reports an inline success confirmation' =>
        str_contains($content['post_controller'],"MG.post('/api/social/posts.php'")
        && str_contains($content['post_controller'],'data-create-post-success')
        && str_contains($content['menu'],'data-create-post-success')
        && str_contains($content['runtime'],'/assets/js/create-center-post-inline.js'),
    'mobile removes the horizontal tool icon row and footer cancel actions' =>
        str_contains($content['mobile_post_css'],'@media(max-width:820px)')
        && str_contains($content['mobile_post_css'],'.mg-create-center-rail')
        && str_contains($content['mobile_post_css'],'display:none!important')
        && str_contains($content['mobile_post_css'],'.mg-create-inline-actions>.mg-create-secondary[data-create-center-home]'),
    'large responsive form controls remain usable on desktop and mobile' =>
        str_contains($content['create_css'],'min-height:54px')
        && str_contains($content['create_css'],'.mg-create-form-grid-4')
        && str_contains($content['mobile_post_css'],'.mg-create-center-post .mg-feed-upload-grid')
        && str_contains($content['menu_controller'],'input:not([disabled]),select:not([disabled]),textarea:not([disabled])'),
    'home cards remove the duplicate intro and expose separate manage destinations' =>
        str_contains($content['manage_css'],'.mg-create-center-welcome{display:none!important}')
        && str_contains($content['manage_js'],'intro.remove()')
        && str_contains($content['manage_js'],"product: '/merchant-products.php'")
        && str_contains($content['manage_js'],"campaign: '/merchant-campaigns.php'")
        && str_contains($content['manage_js'],"reward: '/merchant-reward-templates.php'")
        && str_contains($content['manage_js'],"post: '/feed.php?view=mine'")
        && str_contains($content['manage_js'],"storefront: '/merchant-storefront.php'")
        && str_contains($content['manage_js'],"location: '/merchant-locations.php'")
        && str_contains($content['manage_js'],"list: '/lists.php'")
        && str_contains($content['manage_js'],"document.createElement('article')")
        && str_contains($content['manage_js'],"document.createElement('button')")
        && str_contains($content['manage_js'],"document.createElement('a')")
        && str_contains($content['manage_js'],'card.replaceWith(shell)')
        && str_contains($content['list_extension'],'create-center-manage-actions.js?v=1.0.0'),
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

$total=count($checks);
echo "\nFullscreen inline create center contract: {$total}/{$total}.\n";
