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
    $include=$read('includes/gift-action-center.php');
    $feed=$read('assets/js/gift-action-center-feed-v3.js');
    $css=$read('assets/css/gift-action-center-feed-v3.css');
    $load=$read('assets/js/gift-envelope-presentation.js');
    $source=$read('assets/js/gift-source-metadata.js');
    $api=$read('api/account/action-center.php');
    $inbox=$read('inbox.php');
    $sent=$read('sent.php');
    $claimed=$read('claimed.php');

    $expect(
        str_contains($include,'/assets/css/gift-action-center-feed-v3.css')
        && str_contains($include,'/assets/js/gift-action-center-feed-v3.js')
        && str_contains($include,'data-feed-version="3"')
        && !str_contains($include,'gift-action-center-feed-v2')
        && !is_file($root.'/assets/js/gift-action-center-feed-v2.js')
        && !is_file($root.'/assets/css/gift-action-center-feed-v2.css'),
        'Shared include loads only the rebuilt feed v3 component'
    );

    $expect(
        str_contains($feed,'row.innerHTML =')
        && str_contains($feed,'mg-gift-card-v3-copy')
        && str_contains($feed,'mg-gift-card-v3-actions')
        && str_contains($feed,"row.className = 'mg-gift-row mg-gift-card-v3'"),
        'Feed v3 replaces the row structure instead of decorating the legacy card'
    );

    $expect(
        !str_contains($feed,'mg-gift-status')
        && !str_contains($feed,'badgeLabel(')
        && str_contains($feed,'mg-gift-business-name')
        && str_contains($feed,"'<span>Sent from '"),
        'Source badges are removed and business/sender hierarchy is rendered directly'
    );

    $expect(
        str_contains($feed,"actionButton('send', 'Regift')")
        && str_contains($feed,"actionButton('claim', 'Claim')")
        && str_contains($feed,"actionButton('follow-up', 'Follow Up'")
        && str_contains($feed,"actionButton('message', 'Message')")
        && str_contains($feed,"actionButton('tip', 'Tip'")
        && substr_count($feed,"actionButton('load', 'Load')")>=3
        && !str_contains($feed,'is-primary'),
        'Inbox, Sent, and Claimed actions use one neutral rebuilt action stack'
    );

    $expect(
        str_contains($feed,'is-sender')
        && str_contains($feed,'is-time')
        && str_contains($feed,'is-views')
        && str_contains($feed,'relativeTime(value)'),
        'Cards keep only sender, relative time, and views metadata'
    );

    $expect(
        str_contains($css,'grid-template-columns:72px minmax(0,1fr) 112px')
        && str_contains($css,'grid-template-columns:52px minmax(0,1fr) 78px')
        && str_contains($css,'grid-column:3')
        && !str_contains($css,'grid-column:1/-1'),
        'Desktop and mobile both use image, content, and right-side action columns'
    );

    $expect(
        str_contains($css,'.mg-gift-center-workspace{padding-left:0!important;padding-right:0!important}')
        && str_contains($css,'.mg-gift-feed-column{padding:0}')
        && str_contains($css,'.mg-gift-list{gap:8px;padding:8px 0 14px}')
        && str_contains($css,'font-size:8px'),
        'Mobile feed uses full width with smaller action labels'
    );

    $expect(
        str_contains($load,'window.MicrogifterGiftFeedV3')
        && !str_contains($load,'window.MicrogifterGiftFeedV2')
        && str_contains($load,"detail('Business'")
        && str_contains($load,"detail('Sent From'")
        && str_contains($load,"detail('Source'")
        && str_contains($load,"detail('Source Detail'")
        && str_contains($load,"detail('Source Reference'"),
        'Load owns business, sender, and complete source metadata through the v3 controller'
    );

    $expect(
        str_contains($source,"row.dataset.giftSourceSystem = source.system")
        && str_contains($source,"row.dataset.giftSourceLabel = source.label")
        && !str_contains($source,"innerHTML = 'Source:"),
        'Source metadata remains off-card and is attached only for Load'
    );

    $expect(
        str_contains($api,'mg_action_center_apply_business_names')
        && str_contains($api,'FROM merchant_storefronts')
        && str_contains($api,"\$item['business_name']=\$business")
        && str_contains($api,"\$item['merchant_name']=\$business"),
        'Action Center API resolves storefront business names before rendering'
    );

    $expect(
        str_contains($inbox,"require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($sent,"require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($claimed,"require __DIR__ . '/includes/gift-action-center.php'"),
        'Inbox, Sent, and Claimed share the rebuilt component'
    );

    $expect(
        !str_contains($feed,'Microgifter.post(')
        && !str_contains($feed,"method: 'POST'")
        && !str_contains($load,'Microgifter.post(')
        && !str_contains($load,"method: 'POST'"),
        'Feed rebuild adds no transaction or mutation authority'
    );
}catch(Throwable $error){
    $failures[]=$error->getMessage();
    echo 'FAIL: '.$error->getMessage()."\n";
}

if($failures!==[]){
    fwrite(STDERR,sprintf("Gift Action Center feed v3 validation failed: %d failure(s), %d pass(es).\n",count($failures),$passes));
    foreach($failures as $failure)fwrite(STDERR," - {$failure}\n");
    exit(1);
}

echo "Gift Action Center feed v3 validation passed: {$passes} checks.\n";
