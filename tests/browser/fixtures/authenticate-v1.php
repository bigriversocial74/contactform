<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/app.php';

$environment=(string)mg_env('MG_APP_ENV','');
$browserAuthEnabled=(string)mg_env('MG_TEST_SKIP_AUTHENTICATED','')==='1';
$remoteAddress=(string)($_SERVER['REMOTE_ADDR']??'');
$isLoopback=in_array($remoteAddress,['127.0.0.1','::1'],true);

if($environment!=='testing'||!$browserAuthEnabled||!$isLoopback){
    http_response_code(404);
    exit('Not found.');
}

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
session_regenerate_id(true);
$_SESSION['mg_user']=[
    'id'=>999998,
    'public_id'=>'99999999-9999-4999-8999-999999999998',
    'display_name'=>'V1 Browser Customer',
    'email'=>'v1-browser@example.test',
    'roles'=>['customer'],
    'permissions'=>[],
];

$targets=[
    'product'=>'/product.php?id=11111111-1111-4111-8111-111111111111&p=release-smoke',
    'cart'=>'/cart.php',
];
$target=strtolower(trim((string)($_GET['target']??'product')));
$destination=$targets[$target]??$targets['product'];
header('Cache-Control: private, no-store, max-age=0');
header('Location: '.$destination,true,302);
exit;
