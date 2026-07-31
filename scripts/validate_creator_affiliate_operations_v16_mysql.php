<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

$root=dirname(__DIR__);
$config=require $root.'/api/config.php';
$db=$config['db']??null;
if(!is_array($db))throw new RuntimeException('Database configuration is unavailable.');

$dsn=sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    (string)($db['host']??'127.0.0.1'),
    (string)(getenv('MG_DB_PORT')?:'3306'),
    (string)($db['name']??''),
    (string)($db['charset']??'utf8mb4')
);
$pdo=new PDO($dsn,(string)($db['user']??''),(string)($db['pass']??''),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);

require_once $root.'/includes/creator-campaigns.php';

$checks=[];
$add=static function(string $name,bool $passed,string $detail='')use(&$checks):void{
    $checks[]=['name'=>$name,'passed'=>$passed,'detail'=>$detail];
};

$add('Operations schema installed',mg_creator_campaign_operations_installed($pdo));

$policy=mg_creator_campaign_operations_default_policy('USD');
$participants=mg_creator_campaign_operations_payout_readiness($pdo,PHP_INT_MAX,'USD',$policy);
$add('Hold-aware readiness query executes',is_array($participants));
$add('Empty workspace returns no participants',$participants===[]);

$policies=mg_creator_campaign_operations_creator_policy_views($pdo,PHP_INT_MAX);
$add('Merchant-labeled policy query executes',is_array($policies));
$add('Unknown Creator returns no policies',$policies===[]);

$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['passed']));
foreach($checks as $check){
    echo ($check['passed']?'PASS':'FAIL').' | '.$check['name'].($check['detail']!==''?' | '.$check['detail']:'').PHP_EOL;
}
echo 'SCORE '.(count($checks)-count($failed)).'/'.count($checks).PHP_EOL;
exit($failed===[]?0:1);
