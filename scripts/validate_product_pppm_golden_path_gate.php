<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/audit_product_pppm_golden_path.php').' 2>&1';
$output=[];
$exitCode=0;
exec($command,$output,$exitCode);
$raw=trim(implode("\n",$output));
if($exitCode!==0){
    fwrite(STDERR,$raw.PHP_EOL);
    exit($exitCode);
}

try{
    $report=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
}catch(Throwable $error){
    fwrite(STDERR,'Golden path audit did not return valid JSON: '.$error->getMessage().PHP_EOL);
    exit(1);
}
if(!is_array($report)||!is_array($report['checks']??null)){
    fwrite(STDERR,'Golden path audit report is incomplete.'.PHP_EOL);
    exit(1);
}

$checksByKey=[];
foreach($report['checks'] as $check){
    $key=(string)($check['key']??'');
    if($key!=='')$checksByKey[$key]=$check;
}
$passed=static function(string $key)use($checksByKey): bool{
    return ($checksByKey[$key]['status']??null)==='pass';
};
$finding=static function(string $key)use($checksByKey): bool{
    return ($checksByKey[$key]['status']??null)==='finding';
};

$superseded=[];
if($passed('direct_merchant_claim')){
    $superseded[]='purchased_gift_claim_bridge';
}
if($passed('original_issuer_preservation')&&$finding('message_timing_policy')){
    $superseded[]='post_claim_message_recipient';
}

$blocking=[];
foreach($report['checks'] as $check){
    $key=(string)($check['key']??'');
    $severity=(string)($check['severity']??'');
    if(($check['status']??null)!=='finding')continue;
    if(!in_array($severity,['critical','high'],true))continue;
    if(in_array($key,$superseded,true))continue;
    $blocking[]=[
        'key'=>$key,
        'severity'=>$severity,
        'summary'=>(string)($check['summary']??''),
    ];
}

$report['mode']='gating_audit';
$report['gate']=[
    'passed'=>$blocking===[],
    'blocking_count'=>count($blocking),
    'blocking_findings'=>$blocking,
    'superseded_findings'=>$superseded,
    'policy'=>'Critical and high findings fail unless a later canonical behavior check explicitly supersedes the legacy condition.',
];
fwrite(STDOUT,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
exit($blocking===[]?0:1);
