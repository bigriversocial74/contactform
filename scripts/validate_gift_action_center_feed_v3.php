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
    $portal=$read('assets/js/gift-action-center-modal-portal.js');
    $load=$read('assets/js/gift-envelope-presentation.js');
    $source=$read('assets/js/gift-source-metadata.js');
    $api=$read('api/account/action-center.php');
    $contract=$read('api/account/_action_center_contract.php');
    $adapter=$read('assets/js/action-center-contract-v2.js');
    $inbox=$read('inbox.php');
    $sent=$read('sent.php');
    $claimed=$read('claimed.php');

    $expect(
        str_contains($include,'/assets/css/gift-action-center-feed-v3.css?v=3.2.0')
        && str_contains($include,'/assets/js/gift-action-center-feed-v3.js?v=3.1.0')
        && str_contains($include,'/assets/js/gift-action-center-modal-portal.js?v=1.1.0')
        && str_contains($include,'data-feed-version="3"')
        && !str_contains($include,'gift-action-center-feed-v2')
        && !is_file($root.'/assets/js/gift-action-center-feed-v2.js')
        && !is_file($root.'/assets/css/gift-action-center-feed-v2.css'),
        'Shared include loads cache-busted feed and modal assets'
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
        str_contains($css,'font-weight:500!important')
        && str_contains($css,'.mg-gift-business-name')
        && str_contains($css,'font-size:13px!important')
        && str_contains($css,'font-size:11px!important')
        && str_contains($css,'.mg-gift-card-message{display:none!important}'),
        'Card title is regular weight, business name is larger, and description is removed from the feed'
    );

    $expect(
        str_contains($portal,"const cancel = actions && actions.querySelector('.mg-send-exact-secondary,[data-action-modal-close]')")
        && str_contains($portal,'if (cancel) cancel.remove();')
        && str_contains($portal,"actions.dataset.singleAction = 'true'")
        && !str_contains($portal,"cancel.textContent = 'Cancel'")
        && str_contains($portal,"close.setAttribute('data-action-modal-close', '')"),
        'Regift removes the footer Cancel control while retaining the canonical header close button'
    );

    $expect(
        str_contains($feed,"actionButton('send', 'Regift', !item.can_send")
        && str_contains($feed,"actionButton('claim', 'Claim', !item.can_claim")
        && str_contains($feed,"actionButton('follow-up', 'Follow Up', !item.can_follow_up")
        && str_contains($feed,"actionButton('message', 'Message', !item.can_message")
        && str_contains($feed,"actionButton('tip', 'Tip', !item.can_tip")
        && substr_count($feed,"actionButton('load', 'Load', !item.can_load")>=3
        && str_contains($feed,'capability_reasons')
        && !str_contains($feed,'is-primary'),
        'Inbox, Sent, and Claimed actions use one neutral server-capability-gated action stack'
    );

    $expect(
        str_contains($feed,'is-sender')
        && str_contains($feed,'is-time')
        && str_contains($feed,'is-views')
        && str_contains($feed,'relativeTime(value)'),
        'Cards keep only sender, relative time, and views metadata'
    );

    $expect(
        str_contains($css,'grid-template-columns:72px minmax(0,1fr) 112px!important')
        && str_contains($css,'@media(max-width:1100px)')
        && str_contains($css,'grid-template-columns:60px minmax(0,1fr) 88px!important')
        && str_contains($css,'grid-template-columns:50px minmax(0,1fr) 74px!important')
        && str_contains($css,'grid-column:3!important')
        && str_contains($css,'grid-row:1!important')
        && !str_contains($css,'grid-column:1/-1'),
        'Desktop, tablet, and mobile force image, content, and right-side action columns'
    );

    $expect(
        str_contains($css,'[data-gift-center][data-feed-version="3"] .mg-gift-feed-column{padding:0 2px!important}')
        && str_contains($css,'padding:9px 4px 9px 6px!important')
        && str_contains($css,'width:74px!important')
        && str_contains($css,'font-size:7.5px!important'),
        'Mobile feed minimizes side gutters and compacts the right action stack'
    );

    $expect(
        str_contains($css,'display:grid!important')
        && str_contains($css,'flex:none!important')
        && str_contains($css,'min-width:0!important')
        && str_contains($css,'justify-content:stretch!important'),
        'Feed v3 overrides the legacy responsive bottom-row action rules'
    );

    $expect(
        str_contains($load,'window.MicrogifterGiftFeedV3')
        && !str_contains($load,'window.MicrogifterGiftFeedV2')
        && str_contains($load,"detail('Business'")
        && str_contains($load,"detail('Sent From'")
        && str_contains($load,"detail('Source'")
        && str_contains($load,"detail('Source Detail'")
        && str_contains($load,"detail('Source Reference'")
        && str_contains($load,'item.message'),
        'Load retains business, sender, source, and gift-description details'
    );

    $expect(
        str_contains($source,"row.dataset.giftSourceSystem = source.system")
        && str_contains($source,"row.dataset.giftSourceLabel = source.label")
        && !str_contains($source,"innerHTML = 'Source:"),
        'Source metadata remains off-card and is attached only for Load'
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
        'Shared Contract v2 resolves storefront business names before feed rendering'
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
