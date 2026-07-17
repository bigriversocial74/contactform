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
    $runtime=$read('assets/js/gift-action-center-runtime-v4.js');
    $css=$read('assets/css/gift-action-center-feed-v3.css');
    $runtimeCss=$read('assets/css/gift-action-center-runtime-v4.css');
    $baseCss=$read('assets/css/gift-action-center.css');
    $portal=$read('assets/js/gift-action-center-modal-portal.js');
    $api=$read('api/account/action-center.php');
    $contract=$read('api/account/_action_center_contract.php');
    $adapter=$read('assets/js/action-center-contract-v2.js');
    $inbox=$read('inbox.php');
    $sent=$read('sent.php');
    $claimed=$read('claimed.php');

    $expect(
        str_contains($include,'/assets/css/gift-action-center-feed-v3.css?v=3.2.0')
        && str_contains($include,'/assets/css/gift-action-center-runtime-v4.css?v=4.0.1')
        && str_contains($include,'/assets/js/gift-action-center-runtime-v4.js?v=4.0.0')
        && str_contains($include,'/assets/js/gift-action-center-modal-portal.js?v=1.1.0')
        && str_contains($include,'data-feed-version="4"')
        && !str_contains($include,'gift-action-center-feed-v3.js'),
        'Shared include loads Runtime v4 with cache-busted card and modal assets'
    );

    $expect(
        str_contains($runtime,'mg-gift-row')
        && str_contains($runtime,'mg-gift-card-v3')
        && str_contains($runtime,'mg-gift-card-v3-copy')
        && str_contains($runtime,'mg-gift-card-v3-actions')
        && str_contains($runtime,'mg-action-center-contract-v2'),
        'Runtime v4 renders the canonical Action Center card hierarchy'
    );

    $expect(
        str_contains($runtime,'mg-gift-business-name')
        && str_contains($runtime,"state.folder==='sent'?'To: '+recipientFor(c):'From: '+sender")
        && str_contains($runtime,'messageFor(c)')
        && str_contains($runtime,'merchantFor(c)'),
        'Runtime v4 preserves business, sender or recipient, and gift-description hierarchy'
    );

    $expect(
        str_contains($css,'font-weight:500!important')
        && str_contains($css,'.mg-gift-business-name')
        && str_contains($css,'font-size:13px!important')
        && str_contains($css,'font-size:11px!important')
        && str_contains($css,'.mg-gift-card-message{display:none!important}'),
        'Canonical card presentation keeps regular titles, business emphasis, and compact feed copy'
    );

    $expect(
        str_contains($runtimeCss,'[data-gift-center][data-feed-version="4"] .mg-gift-list>.mg-gift-row{visibility:visible!important}')
        && str_contains($include,'gift-action-center-runtime-v4.css?v=4.0.1')
        && str_contains($css,'.mg-gift-list:not([data-feed-v3-ready])>.mg-gift-row{visibility:hidden}'),
        'Runtime v4 voucher rows cannot inherit the retired Feed v3 hidden loading state'
    );

    $expect(
        str_contains($runtime,"actionButton(c,'send','Regift'")
        && str_contains($runtime,"actionButton(c,'claim','Claim'")
        && str_contains($runtime,"actionButton(c,'follow-up','Follow Up'")
        && str_contains($runtime,"actionButton(c,'message','Message'")
        && str_contains($runtime,"actionButton(c,'tip','Tip'")
        && substr_count($runtime,"actionButton(c,'load','Load'")>=3
        && str_contains($runtime,'capability_reasons'),
        'Inbox, Sent, and Claimed actions use one server-capability-gated Runtime v4 stack'
    );

    $expect(
        str_contains($runtime,'is-sender')
        && str_contains($runtime,'is-time')
        && str_contains($runtime,'is-source')
        && str_contains($runtime,'relativeTime(timestamp)'),
        'Cards retain sender, relative time, and source metadata'
    );

    $expect(
        str_contains($baseCss,'.mg-gift-row{display:grid;grid-template-columns:106px minmax(0,1fr) auto')
        && str_contains($baseCss,'@media(max-width:1100px){.mg-gift-row{grid-template-columns:82px minmax(0,1fr)')
        && str_contains($baseCss,'@media(max-width:760px)')
        && str_contains($baseCss,'.mg-gift-row{grid-template-columns:64px minmax(0,1fr)'),
        'Base Action Center layout keeps desktop, tablet, and mobile voucher rows usable under Runtime v4'
    );

    $expect(
        str_contains($runtime,'function openDrawer(c)')
        && str_contains($runtime,'function voucherMarkup(c)')
        && str_contains($runtime,'posts.map((post,i)=>mediaPostMarkup')
        && str_contains($runtime,".join('')+'</div>'+voucherMarkup(c)"),
        'Load presents media first and the protected voucher underneath'
    );

    $expect(
        str_contains($runtime,'data-gift-source-system')
        && str_contains($runtime,'data-gift-source-label')
        && str_contains($runtime,'data-gift-source-detail')
        && str_contains($runtime,'data-gift-source-reference'),
        'Source metadata remains attached to Runtime v4 rows for the Load experience'
    );

    $expect(
        str_contains($portal,"const cancel = actions && actions.querySelector('.mg-send-exact-secondary,[data-action-modal-close]')")
        && str_contains($portal,'if (cancel) cancel.remove();')
        && str_contains($portal,"actions.dataset.singleAction = 'true'")
        && str_contains($portal,"close.setAttribute('data-action-modal-close', '')"),
        'Regift presentation retains one primary footer action and the canonical header close button'
    );

    $expect(
        str_contains($api,"require_once __DIR__ . '/_action_center_contract.php';")
        && str_contains($api,'mg_action_center_contract_items(')
        && str_contains($contract,'mg_action_center_contract_business_names')
        && str_contains($contract,'FROM merchant_storefronts')
        && str_contains($contract,"\$item['business_name'] = \$business")
        && str_contains($contract,"\$item['merchant_name'] = \$item['business_name']")
        && str_contains($adapter,'merchant_name: text(merchant.name')
        && str_contains($adapter,'business_name: text(merchant.name'),
        'Shared Contract v2 resolves storefront business names before Runtime v4 rendering'
    );

    $expect(
        str_contains($inbox,"require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($sent,"require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($claimed,"require __DIR__ . '/includes/gift-action-center.php'"),
        'Inbox, Sent, and Claimed share the Runtime v4 component'
    );

    $expect(
        !str_contains($runtime,'Microgifter.post(')
        && !str_contains($runtime,"method:'POST'")
        && !str_contains($portal,'Microgifter.post(')
        && !str_contains($portal,"method: 'POST'"),
        'Runtime v4 card rendering and portal presentation add no transaction authority'
    );
}catch(Throwable $error){
    $failures[]=$error->getMessage();
    echo 'FAIL: '.$error->getMessage()."\n";
}

if($failures!==[]){
    fwrite(STDERR,sprintf("Gift Action Center feed compatibility validation failed: %d failure(s), %d pass(es).\n",count($failures),$passes));
    foreach($failures as $failure)fwrite(STDERR," - {$failure}\n");
    exit(1);
}

echo "Gift Action Center feed compatibility validation passed: {$passes} checks.\n";
