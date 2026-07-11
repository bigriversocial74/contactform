<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'agent'=>$root.'/includes/agent-sidebar.php',
    'accountSidebar'=>$root.'/includes/account-sidebar.php',
    'myQuests'=>$root.'/my-quests.php',
    'subscriptions'=>$root.'/account-subscriptions.php',
    'notifications'=>$root.'/notifications.php',
    'feed'=>$root.'/feed.php',
    'account'=>$root.'/account.php',
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
    'My Quests is a first-class customer sidebar destination' =>
        str_contains($content['agent'],"'my-quests' => [")
        && str_contains($content['agent'],"'label' => 'My Quests'")
        && str_contains($content['agent'],"'href' => '/my-quests.php'")
        && str_contains($content['agent'],"'visible' => \$user !== null"),
    'My Quests supports both participant active keys' =>
        str_contains($content['agent'],"\$agentSidebarActive === 'my-quests'")
        && str_contains($content['agent'],"\$agentSidebarActive === 'loyalty_quests'"),
    'legacy account navigation also exposes My Quests' =>
        str_contains($content['accountSidebar'],"'my-quests' => ['Gifts', 'My Quests'")
        && str_contains($content['accountSidebar'],"'/my-quests.php'"),
    'all requested pages explicitly opt into the inbox sidebar' =>
        str_contains($content['myQuests'],'$use_inbox_sidebar = true;')
        && str_contains($content['subscriptions'],'$use_inbox_sidebar = true;')
        && str_contains($content['notifications'],'$use_inbox_sidebar = true;')
        && str_contains($content['feed'],'$use_inbox_sidebar = true;')
        && str_contains($content['account'],'$use_inbox_sidebar = basename('),
    'all requested pages mount the shared agent sidebar' =>
        str_contains($content['myQuests'],"includes/agent-sidebar.php")
        && str_contains($content['subscriptions'],"includes/agent-sidebar.php")
        && str_contains($content['notifications'],"includes/agent-sidebar.php")
        && str_contains($content['feed'],"includes/agent-sidebar.php")
        && str_contains($content['account'],"includes/agent-sidebar.php"),
    'central script fallback covers every requested route' =>
        str_contains($content['agent'],'$inboxSidebarScripts = [')
        && str_contains($content['agent'],"'my-quests.php'")
        && str_contains($content['agent'],"'account-subscriptions.php'")
        && str_contains($content['agent'],"'notifications.php'")
        && str_contains($content['agent'],"'feed.php'")
        && str_contains($content['agent'],"'account.php'"),
    'inbox sidebar filter remains isolated from merchant admin navigation' =>
        str_contains($content['agent'],'if (!$isMerchantAdminSidebar && ($useInboxSidebar || in_array($agentSidebarActive, $reducedInboxSidebarPages, true)))')
        && str_contains($content['agent'],"str_starts_with(\$currentSidebarScript, 'merchant-')"),
    'inbox sidebar removes only the established extra navigation items' =>
        str_contains($content['agent'],"['feed-following', 'merchant_crm', 'ads-manager']")
        && str_contains($content['agent'],"\$appSidebarNav['training-lab'] = ['visible' => false]"),
    'all feed views keep the main feed destination active' =>
        str_contains($content['agent'],"str_starts_with(\$agentSidebarActive, 'feed-')")
        && str_contains($content['feed'],"\$agent_tab = 'feed-' . \$feedView"),
    'account wrappers are not automatically forced into the direct account sidebar contract' =>
        str_contains($content['account'],"basename((string) (\$_SERVER['SCRIPT_NAME'] ?? '')) === 'account.php'")
        && !str_contains($content['account'],'$use_inbox_sidebar = true;'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nCustomer inbox sidebar validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nCustomer inbox sidebar contract: 10/10.".PHP_EOL;
