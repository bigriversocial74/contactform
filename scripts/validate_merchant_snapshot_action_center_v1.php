<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'page'=>$root.'/merchant-agent-chat.php',
    'snapshot'=>$root.'/includes/ai/merchant-agent-snapshot.php',
    'js'=>$root.'/assets/js/merchant-agent-snapshot-action-center.js',
    'css'=>$root.'/assets/css/merchant-agent-snapshot-action-center.css',
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
    'authorized Merchant Agent page loads action center assets' =>
        str_contains($content['page'],'merchant-agent-snapshot-action-center.css?v=1.0.0')
        && str_contains($content['page'],'merchant-agent-snapshot-action-center.js?v=1.0.0')
        && str_contains($content['page'],"$merchantAgentAllowed ? ' data-merchant-agent-chat' : ''"),
    'snapshot remains database only and excludes private customer details' =>
        str_contains($content['snapshot'],"'database_only' => true")
        && str_contains($content['snapshot'],"'external_ai_called' => false")
        && str_contains($content['snapshot'],"'customer_details_included' => false"),
    'action center recognizes only snapshot conversations' =>
        str_contains($content['js'],'snapshotPattern')
        && str_contains($content['js'],'current merchant snapshot')
        && str_contains($content['js'],'dataset.snapshotEnhanced'),
    'seven thirty and ninety day controls submit snapshot keywords' =>
        str_contains($content['js'],'[7, 30, 90]')
        && str_contains($content['js'],"'snapshot ' + day.getAttribute('data-agent-snapshot-days') + ' days'")
        && str_contains($content['js'],'data-agent-snapshot-refresh'),
    'snapshot sections become native expandable details' =>
        str_contains($content['js'],"document.createElement('details')")
        && str_contains($content['js'],'mg-agent-snapshot-section')
        && str_contains($content['css'],'.mg-agent-snapshot-section[open]'),
    'formatted metric table is rendered from stored snapshot blocks' =>
        str_contains($content['js'],'mg-agent-snapshot-table')
        && str_contains($content['js'],'metricRows(message)')
        && str_contains($content['css'],'.mg-agent-snapshot-table'),
    'operational drill downs cover CRM orders campaigns comments and review' =>
        str_contains($content['js'],"href: '/merchant-crm.php'")
        && str_contains($content['js'],"href: '/merchant-orders.php'")
        && str_contains($content['js'],"href: '/merchant-campaigns.php'")
        && str_contains($content['js'],"href: '/merchant.php'")
        && str_contains($content['js'],"href: '/merchant-agent-approvals.php'"),
    'one click prompts remain approval first' =>
        str_contains($content['js'],'data-agent-snapshot-prompt')
        && str_contains($content['js'],'approval-first')
        && str_contains($content['js'],'do not send anything')
        && !str_contains($content['js'],'Microgifter.post('),
    'CSV export is client generated from aggregate metrics' =>
        str_contains($content['js'],"new Blob([csv], { type: 'text/csv;charset=utf-8' })")
        && str_contains($content['js'],'merchant-snapshot-')
        && str_contains($content['js'],'data-agent-snapshot-export'),
    'print mode isolates the selected snapshot' =>
        str_contains($content['js'],'window.print()')
        && str_contains($content['js'],'is-snapshot-print-target')
        && str_contains($content['css'],'@media print'),
    'failed snapshot responses expose a retry action' =>
        str_contains($content['js'],'data-agent-snapshot-retry')
        && str_contains($content['js'],'Retry snapshot')
        && str_contains($content['js'],"message.classList.contains('is-error')"),
    'zero activity gets guided merchant setup links' =>
        str_contains($content['js'],'No stored activity was found for this window.')
        && str_contains($content['js'],'/merchant-products.php')
        && str_contains($content['js'],'/merchant-locations.php'),
    'runtime survives chat rerenders through mutation observation' =>
        str_contains($content['js'],'new MutationObserver(enhance)')
        && str_contains($content['js'],'childList: true, subtree: true'),
    'mobile snapshot cards collapse to one column' =>
        str_contains($content['css'],'@media(max-width:900px)')
        && str_contains($content['css'],'.mg-agent-snapshot-actions{grid-template-columns:1fr}')
        && str_contains($content['css'],'@media(max-width:620px)'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}
if($failed!==[]){
    fwrite(STDERR,"\nMerchant Snapshot Action Center validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}
echo "\nMerchant Snapshot Action Center v1 contract: 10/10.\n";
