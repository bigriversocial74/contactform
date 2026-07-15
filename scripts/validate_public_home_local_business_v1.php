<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$failures=[];
$passes=0;

$read=static function(string $path)use($root):string{
    $full=$root.'/'.ltrim($path,'/');
    if(!is_file($full))throw new RuntimeException('Missing required file: '.$path);
    $content=file_get_contents($full);
    if(!is_string($content))throw new RuntimeException('Unable to read required file: '.$path);
    return $content;
};

$expect=static function(bool $condition,string $label)use(&$failures,&$passes):void{
    if($condition){$passes++;echo "PASS: {$label}\n";return;}
    $failures[]=$label;
    echo "FAIL: {$label}\n";
};

try{
    $index=$read('merchant-landing.php');
    $css=$read('assets/css/homepage-local-business-v1.css');
    $header=$read('includes/header-components/public-header.php');
    $heroJpegPath=$root.'/assets/images/public-home-merchant-hero.jpg';
    $heroJpeg=$read('assets/images/public-home-merchant-hero.jpg');
    $heroDimensions=@getimagesize($heroJpegPath);

    $expect(
        str_contains($index,"'/assets/css/homepage-local-business-v1.css?v=1.0.0'")
        && !str_contains($index,"'/assets/css/homepage-drm.css'")
        && !str_contains($index,"'/assets/css/homepage-hero-search.css'"),
        'Merchant landing replaces the legacy black and gold DRM presentation'
    );

    $expect(
        str_contains($index,'/assets/images/public-home-merchant-hero.jpg?v=2.0.0')
        && !str_contains($index,'/assets/images/public-home-merchant-hero.svg')
        && str_contains($index,'Local coffee shop owner using Microgifter')
        && strlen($heroJpeg)>10000
        && str_starts_with($heroJpeg,"\xFF\xD8\xFF")
        && str_ends_with($heroJpeg,"\xFF\xD9")
        && is_array($heroDimensions)
        && ($heroDimensions[0]??0)===360
        && ($heroDimensions[1]??0)===240
        && ($heroDimensions[2]??0)===IMAGETYPE_JPEG,
        'Hero uses the committed raster cafe photograph with valid JPEG data and dimensions'
    );

    $expect(
        str_contains($index,'Drive Traffic.')
        && str_contains($index,'Build Loyalty.')
        && str_contains($index,'Reward Customers.')
        && str_contains($index,'For local businesses'),
        'Hero communicates the approved traffic, loyalty, and rewards hierarchy'
    );

    $expect(
        str_contains($index,'mg-lb-benefit-rail')
        && str_contains($index,'Drive More Traffic')
        && str_contains($index,'Build Loyalty')
        && str_contains($index,'Increase Revenue')
        && str_contains($index,'Simple to Operate'),
        'Hero includes the four-outcome navy feature rail'
    );

    foreach([
        'id="businesses"',
        'id="how-it-works"',
        'id="rewards"',
        'mg-lb-use-cases',
        'mg-lb-proof',
        'mg-lb-final',
    ] as $needle){
        $expect(str_contains($index,$needle),'Merchant landing includes section contract: '.$needle);
    }

    $expect(
        str_contains($index,'Social Gifting')
        && str_contains($index,'Merchant CRM')
        && str_contains($index,'Campaigns & Offers')
        && str_contains($index,'Claim & Redemption')
        && str_contains($index,'Automated Commerce'),
        'Platform section represents Microgifter core merchant capabilities'
    );

    $expect(
        str_contains($index,'Campaign → Claim')
        && str_contains($index,'/images/desktop_bg_main_v10.png')
        && str_contains($index,'/images/mobile_bg_main.png'),
        'Workflow section retains real product previews and the Campaign to Claim narrative'
    );

    $expect(
        str_contains($index,'href="/signup.php"')
        && str_contains($index,'href="/learn-more.php"')
        && str_contains($index,'href="/discover.php"'),
        'Merchant landing conversion paths connect account creation, sales, and discovery'
    );

    $expect(
        str_contains($css,'grid-template-columns:minmax(0,44%) minmax(0,56%)')
        && str_contains($css,'background:linear-gradient(135deg,#0a1a31,#112b48)')
        && str_contains($css,'--mg-lb-green:#177a2e'),
        'Desktop design follows the white, navy, and green reference system'
    );

    $expect(
        str_contains($css,'body[data-page-id="index"] .mg-unified-header .mg-public-demo,')
        && str_contains($css,'body[data-page-id="index"] .mg-public-mobile-nav>a[href="/learn-more.php"]:last-child{display:none!important}'),
        'Merchant landing removes the Book A Demo header action on desktop and mobile'
    );

    $expect(
        str_contains($css,'.mg-lb-hero{position:relative;padding:0')
        && str_contains($css,".mg-lb-hero-main{\n  position:relative;\n  z-index:2;\n  width:100%;")
        && str_contains($css,'border-left:0;')
        && str_contains($css,'border-right:0;')
        && str_contains($css,'border-radius:0;'),
        'Merchant landing hero is full width with no top or side spacing'
    );

    $expect(
        str_contains($css,'@media(max-width:980px)')
        && str_contains($css,'@media(max-width:720px)')
        && str_contains($css,'@media(max-width:420px)')
        && str_contains($css,'.mg-lb-feature-grid{grid-template-columns:1fr}')
        && str_contains($css,'.mg-lb-benefit-rail{width:calc(100% - 24px);grid-template-columns:1fr'),
        'Merchant landing has dedicated tablet, mobile, and narrow-phone layouts'
    );

    $expect(
        str_contains($css,'@media(prefers-reduced-motion:reduce)')
        && str_contains($index,'aria-labelledby=')
        && str_contains($index,'aria-label='),
        'Merchant landing preserves reduced-motion and semantic accessibility support'
    );

    $expect(
        str_contains($header,"if (!\$user)")
        && str_contains($header,'href="/signin.php"')
        && str_contains($header,'href="/signup.php"'),
        'Shared logged-out header remains the authentication authority'
    );

    $expect(
        !str_contains($index,'Microgifter.post(')
        && !str_contains($index,'fetch(')
        && !str_contains($index,'INSERT INTO')
        && !str_contains($index,'UPDATE '),
        'Public merchant landing adds no transaction or database mutation authority'
    );
}catch(Throwable $error){
    $failures[]=$error->getMessage();
    echo 'FAIL: '.$error->getMessage()."\n";
}

if($failures!==[]){
    fwrite(STDERR,sprintf("Public merchant landing local-business validation failed: %d failure(s), %d pass(es).\n",count($failures),$passes));
    foreach($failures as $failure)fwrite(STDERR," - {$failure}\n");
    exit(1);
}

echo "Public merchant landing local-business validation passed: {$passes} checks.\n";
