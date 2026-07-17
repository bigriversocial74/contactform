<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static fn(string $path): string => is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
$runtime=$read('assets/js/gift-action-center-runtime-v4.js');
$include=$read('includes/gift-action-center.php');
$config=$read('config/frontend-contracts.php');
$pages=['inbox'=>$read('inbox.php'),'sent'=>$read('sent.php'),'claimed'=>$read('claimed.php')];

$checks=[
    'Shared markup loads one Action Center list runtime' =>
        str_contains($include,'gift-action-center-runtime-v4.js?v=4.0.0')
        && !str_contains($include,'gift-action-center-feed-v3.js')
        && !str_contains($include,'gift-action-center-pagination.js'),
    'Shared markup declares Contract v2 and runtime v4' =>
        str_contains($include,'data-feed-version="4"')
        && str_contains($include,'data-contract-version="2"'),
    'Routes do not load the retired list controller' =>
        array_reduce($pages,static fn(bool $ok,string $page): bool => $ok
            && !str_contains($page,'/assets/js/gift-action-center.js')
            && !str_contains($page,'gift-action-center-load-envelope.js')
            && !str_contains($page,'gift-action-center-claim-click.js'),true),
    'Inbox keeps specialized claim and regift flows' =>
        str_contains($pages['inbox'],'gift-action-center-claim-modal.js')
        && str_contains($pages['inbox'],'gift-action-center-send-modal.js')
        && str_contains($pages['inbox'],'gift-action-center-regift-submit.js'),
    'Runtime consumes raw Contract v2 data' =>
        str_contains($runtime,'Microgifter.api')
        && str_contains($runtime,'contract_version:2')
        && str_contains($runtime,'MicrogifterActionCenterRuntime'),
    'Runtime avoids raw metadata parsing' =>
        !str_contains($runtime,'metadata_json')
        && !str_contains($runtime,'instance_metadata_json')
        && !str_contains($runtime,'JSON.parse'),
    'Runtime owns API cursor pagination' =>
        str_contains($runtime,'&cursor=')
        && str_contains($runtime,'data-gift-load-more')
        && str_contains($runtime,'load(false)'),
    'Runtime uses server-projected capabilities' =>
        str_contains($runtime,'parts(c).capabilities[n]')
        && str_contains($runtime,'capability_reasons')
        && str_contains($runtime,"actionButton(c,'send','Regift'")
        && str_contains($runtime,"actionButton(c,'follow-up','Follow Up'"),
    'Runtime renders contract media before the protected voucher' =>
        ($media=strpos($runtime,'mg-pppm-post-stack'))!==false
        && ($voucher=strpos($runtime,'Protected voucher'))!==false
        && $media<$voucher
        && str_contains($runtime,'p.media.posts'),
    'Frontend stable contract points to runtime v4' =>
        str_contains($config,"'path' => 'assets/js/gift-action-center-runtime-v4.js'")
        && str_contains($config,"'gift-action-center-feed-v3.js'")
        && str_contains($config,"'gift-action-center-pagination.js'"),
];

$failed=[];
foreach($checks as $label=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$label.PHP_EOL;
    if(!$passed)$failed[]=$label;
}
if($failed!==[]){
    fwrite(STDERR,'Action Center runtime v4 validation failed: '.implode('; ',$failed).PHP_EOL);
    exit(1);
}
echo 'Action Center runtime v4: '.count($checks).'/'.count($checks).".\n";
