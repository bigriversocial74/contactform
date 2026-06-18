<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__).'/includes/app.php';

$initialize=in_array('--initialize',$argv,true);
$json=in_array('--json',$argv,true);
$skipProbe=in_array('--no-write-probe',$argv,true);

try{
    $status=mg_storage_assert_ready($initialize,!$skipProbe);
    $endpoint=dirname(__DIR__).'/api/public/media.php';
    if(!is_file($endpoint)||!is_readable($endpoint)){
        throw new RuntimeException('Protected media delivery endpoint is unavailable.');
    }
    $payload=[
        'ok'=>true,
        'driver'=>$status['driver'],
        'root'=>$status['root'],
        'persistent'=>$status['persistent'],
        'initialized'=>$status['initialized'],
        'writable'=>$status['writable'],
        'free_bytes'=>$status['free_bytes'],
        'public_endpoint'=>mg_storage_config()['public_endpoint'],
    ];
    if($json){
        echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    }else{
        echo "Persistent media storage is ready.\n";
        echo "Driver: {$payload['driver']}\n";
        echo "Root: {$payload['root']}\n";
        echo "Outside release directory: ".($payload['persistent']?'yes':'no')."\n";
        echo "Writable: ".($payload['writable']?'yes':'no')."\n";
        echo "Public delivery: {$payload['public_endpoint']}\n";
        if(is_int($payload['free_bytes'])||is_float($payload['free_bytes']))echo "Free bytes: {$payload['free_bytes']}\n";
    }
    exit(0);
}catch(Throwable $error){
    $payload=['ok'=>false,'message'=>$error->getMessage()];
    if($json)echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    else fwrite(STDERR,"Persistent media storage check failed: {$error->getMessage()}\n");
    exit(1);
}
