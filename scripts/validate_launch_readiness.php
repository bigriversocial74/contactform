<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
$_SERVER['REQUEST_METHOD']='CLI';
require_once dirname(__DIR__) . '/api/operations/_public_id.php';
require_once dirname(__DIR__) . '/api/operations/_operations.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/api/payments/_readiness.php';

$pdo=mg_db();
$checks=[];
$record=function(string $key,string $status,string $summary,array $details=[])use(&$checks,$pdo):void{
    if(!in_array($status,['pass','warn','fail'],true))throw new InvalidArgumentException('Invalid readiness status.');
    $checks[]=['key'=>$key,'status'=>$status,'summary'=>$summary,'details'=>$details];
    mg_operations_record_check($pdo,$key,$status,$summary,$details);
};

$record('php_runtime',version_compare(PHP_VERSION,'8.1.0','>=')?'pass':'fail','Supported PHP runtime is active.',['version'=>PHP_VERSION]);
$record('pdo_mysql',in_array('mysql',PDO::getAvailableDrivers(),true)?'pass':'fail','PDO MySQL driver is available.');
try{$pdo->query('SELECT 1');$record('database','pass','Database connectivity is available.');}catch(Throwable $e){$record('database','fail','Database connectivity failed.',['message'=>$e->getMessage()]);}

try{
    $migrationStatus=mg_migration_status($pdo);
    $record(
        'stage_migrations',
        $migrationStatus['ready']?'pass':'fail',
        $migrationStatus['ready']?'The complete canonical migration manifest is satisfied.':'The canonical migration manifest is incomplete or inconsistent.',
        [
            'ordered_count'=>$migrationStatus['ordered_count'],
            'applied_key_count'=>$migrationStatus['applied_key_count'],
            'missing'=>$migrationStatus['missing'],
            'checksum_mismatches'=>$migrationStatus['checksum_mismatches'],
        ]
    );
}catch(Throwable $e){
    $record('stage_migrations','fail','Canonical migration readiness could not be evaluated.',['message'=>$e->getMessage()]);
}

$appEnvironment=strtolower(trim((string)mg_env('MG_APP_ENV','')));
$productionEnvironment=in_array($appEnvironment,['production','prod'],true);
$paymentProvider=mg_payment_provider_key();
$paymentMode=mg_payment_mode();
if($paymentProvider==='stripe'){
    try{
        $paymentReadiness=mg_payment_readiness($pdo,'stripe',$paymentMode);
        $record(
            'stripe_platform',
            $paymentReadiness['ready']?'pass':'fail',
            $paymentReadiness['ready']?'Stripe platform configuration is ready.':'Stripe platform configuration is incomplete.',
            [
                'mode'=>$paymentMode,
                'checks'=>$paymentReadiness['checks'],
                'connected_accounts'=>$paymentReadiness['connected_accounts'],
                'webhook_url'=>$paymentReadiness['webhook_url'],
            ]
        );
        $sellingMerchants=$paymentReadiness['selling_merchants'];
        $record(
            'stripe_selling_merchants',
            (int)$sellingMerchants['blocked']===0?'pass':'fail',
            (int)$sellingMerchants['blocked']===0
                ?'Every merchant with a published product is ready to receive Stripe payments.'
                :'Published-product merchants have incomplete Stripe Connect onboarding.',
            $sellingMerchants
        );
    }catch(Throwable $e){
        $record('stripe_platform','fail','Stripe readiness could not be evaluated.',['message'=>$e->getMessage(),'mode'=>$paymentMode]);
    }
}else{
    $record(
        'payment_provider',
        $productionEnvironment?'fail':'warn',
        $productionEnvironment?'Production launch requires Stripe as the active payment provider.':'The current non-production environment is not using Stripe.',
        ['provider'=>$paymentProvider,'mode'=>$paymentMode,'environment'=>$appEnvironment]
    );
}

$tables=[
    'operational_incidents','deployment_releases','release_gate_results','retention_policies','operational_check_results',
    'demand_signal_orchestrations','demand_signal_orchestration_events','demand_signal_orchestration_attempts','demand_signal_orchestration_incidents',
    'payment_platform_credentials','payment_provider_accounts','payment_webhook_events','payment_intents','checkout_sessions',
];
$missing=[];$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
foreach($tables as $table){$stmt->execute([$table]);if((int)$stmt->fetchColumn()!==1)$missing[]=$table;}
$record('operations_tables',$missing===[]?'pass':'fail','Required Stage 18 and Stripe payment tables are present.',['missing'=>$missing]);

$critical=(int)$pdo->query("SELECT COUNT(*) FROM operational_incidents WHERE severity IN ('sev1','sev2') AND status NOT IN ('resolved','closed')")->fetchColumn();
$record('critical_incidents',$critical===0?'pass':'fail','No unresolved SEV1 or SEV2 incidents.',['count'=>$critical]);
$failedWebhooks=(int)$pdo->query("SELECT COUNT(*) FROM payment_webhook_events WHERE status='failed' AND received_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
$record('payment_webhooks',$failedWebhooks===0?'pass':'warn',$failedWebhooks===0?'No failed payment webhooks in the last 24 hours.':'Failed payment webhooks require review.',['count'=>$failedWebhooks]);
foreach(mg_operations_demand_orchestration_health($pdo) as $check)$record($check['key'],$check['status'],$check['summary'],$check['details']);

$failed=array_values(array_filter($checks,fn(array $check):bool=>$check['status']==='fail'));
$warnings=array_values(array_filter($checks,fn(array $check):bool=>$check['status']==='warn'));
$result=['ready'=>$failed===[],'status'=>$failed!==[]?'fail':($warnings!==[]?'warn':'pass'),'checks'=>$checks];
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
if($failed!==[])fwrite(STDERR,'FAILED: '.implode(', ',array_column($failed,'key')).PHP_EOL);
exit($failed===[]?0:1);
