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
    (string)($db['port']??mg_env('MG_DB_PORT','3306')),
    (string)($db['name']??''),
    (string)($db['charset']??'utf8mb4')
);
$pdo=new PDO($dsn,(string)($db['user']??''),(string)($db['pass']??''),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);

require_once $root.'/includes/creator-campaigns.php';

$lockName='microgifter_creator_affiliate_reconciliation_v16';
$lock=$pdo->prepare('SELECT GET_LOCK(?,5)');
$lock->execute([$lockName]);
if((int)$lock->fetchColumn()!==1){
    fwrite(STDERR,"Creator affiliate reconciliation is already running.\n");
    exit(2);
}

$summary=[
    'ok'=>true,
    'started_at'=>gmdate('c'),
    'workspaces_scanned'=>0,
    'cases_detected'=>0,
    'detector_errors'=>[],
    'workspace_failures'=>[],
    'fatal_error'=>null,
];
$exitCode=1;

try{
    if(!mg_creator_campaign_operations_installed($pdo)){
        throw new RuntimeException('Creator affiliate operations schema is incomplete.');
    }

    $workspaceIds=$pdo->query('SELECT DISTINCT workspace_id FROM creator_campaigns ORDER BY workspace_id')->fetchAll(PDO::FETCH_COLUMN)?:[];
    foreach($workspaceIds as $workspaceIdRaw){
        $workspaceId=(int)$workspaceIdRaw;
        if($workspaceId<1)continue;
        try{
            $result=mg_creator_campaign_operations_scan_workspace($pdo,$workspaceId);
            $summary['workspaces_scanned']++;
            $summary['cases_detected']+=(int)($result['detected']??0);
            foreach($result['errors']??[] as $error){
                $summary['detector_errors'][]=['workspace_id'=>$workspaceId]+(is_array($error)?$error:['message'=>(string)$error]);
            }
        }catch(Throwable $e){
            $summary['workspace_failures'][]=[
                'workspace_id'=>$workspaceId,
                'exception_class'=>$e::class,
                'message'=>$e->getMessage(),
            ];
        }
    }

    $summary['ok']=$summary['workspace_failures']===[]&&$summary['detector_errors']===[];
    $exitCode=$summary['ok']?0:1;
}catch(Throwable $e){
    $summary['ok']=false;
    $summary['fatal_error']=[
        'exception_class'=>$e::class,
        'message'=>$e->getMessage(),
    ];
    $exitCode=1;
}finally{
    $summary['completed_at']=gmdate('c');
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    try{
        $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }catch(Throwable){
        // Closing the PDO connection releases the connection-scoped advisory lock.
    }
}

exit($exitCode);
