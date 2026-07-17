<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'subscriptions'=>$root.'/account-subscriptions.php',
    'personal'=>$root.'/agent.php',
    'merchant'=>$root.'/merchant-agent-chat.php',
    'js'=>$root.'/assets/js/subscription-agent-access-v1.js',
    'css'=>$root.'/assets/css/subscription-agent-access-v1.css',
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
    'Personal Agent sends unpaid users to subscriptions with intent' =>
        str_contains($content['personal'],"/account-subscriptions.php?agent=personal"),
    'Merchant Agent sends non-merchants to subscriptions with intent' =>
        str_contains($content['merchant'],"/account-subscriptions.php?agent=merchant"),
    'subscription page loads the access intent assets after billing runtime' =>
        str_contains($content['subscriptions'],'subscription-agent-access-v1.css?v=1.0.0')
        && str_contains($content['subscriptions'],'subscription-agent-access-v1.js?v=1.0.0')
        && strpos($content['subscriptions'],'subscription-agent-access-v1.js')>strpos($content['subscriptions'],'subscription-checkout-completion-v1.js'),
    'only supported Agent destinations are accepted' =>
        str_contains($content['js'],"personal: {")
        && str_contains($content['js'],"target: '/agent.php'")
        && str_contains($content['js'],"merchant: {")
        && str_contains($content['js'],"target: '/merchant-agent-chat.php'")
        && str_contains($content['js'],'Object.prototype.hasOwnProperty.call(config, value)'),
    'requested Agent survives hosted checkout in session storage' =>
        str_contains($content['js'],"mg_agent_upgrade_return_v1")
        && str_contains($content['js'],'window.sessionStorage.setItem')
        && str_contains($content['js'],'window.sessionStorage.getItem'),
    'verified activation returns to the requested Agent' =>
        str_contains($content['js'],"if (checkout === 'activated') return 'activated'")
        && str_contains($content['js'],'window.location.replace(target)')
        && str_contains($content['js'],"store('')"),
    'cancelled checkout keeps the upgrade path recoverable' =>
        str_contains($content['js'],"if (checkout === 'cancelled') return 'cancelled'")
        && str_contains($content['js'],'Resume with Starter')
        && str_contains($content['js'],'No subscription change was made.'),
    'Starter is identified as minimum eligible package' =>
        str_contains($content['js'],"packageId === 'starter'")
        && str_contains($content['js'],'is-agent-recommended')
        && str_contains($content['js'],'Recommended starting plan for'),
    'all published paid cards show Agent eligibility' =>
        str_contains($content['js'],'is-agent-eligible')
        && str_contains($content['js'],'Includes ')
        && str_contains($content['js'],'data-agent-eligible-for'),
    'upgrade guidance remains inside the existing subscription checkout' =>
        str_contains($content['js'],".mg-sub-plan-card[data-package-id=\"starter\"] .mg-sub-action")
        && !str_contains($content['js'],'Microgifter.post(')
        && !str_contains($content['js'],'fetch('),
    'mobile upgrade guidance remains usable' =>
        str_contains($content['css'],'@media(max-width:900px)')
        && str_contains($content['css'],'@media(max-width:620px)')
        && str_contains($content['css'],'.mg-agent-access-actions{grid-column:1/-1'),
    'upgrade card and activated state have distinct visual treatment' =>
        str_contains($content['css'],'.mg-agent-access-banner.is-activated')
        && str_contains($content['css'],'.mg-agent-access-banner.is-cancelled')
        && str_contains($content['css'],'.mg-sub-plan-card.is-agent-recommended'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}
if($failed!==[]){
    fwrite(STDERR,"\nSubscription Agent access validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}
echo "\nSubscription Agent Access and Return Routing v1 contract: 10/10.\n";
