<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/admin/_dashboard.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_ad_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_ad_keys(mixed $value,array &$keys): void
{
    if(!is_array($value))return;
    foreach($value as $key=>$child){$keys[]=(string)$key;mg_ad_keys($child,$keys);}
}

$pdo=mg_db();
$runId='admindashboard'.bin2hex(random_bytes(5));
$summary=array_fill_keys([
    'super_admin_access','permission_partitioning','platform_aggregation','operations_aggregation',
    'safe_recent_records','window_bounds','no_private_data','read_side_effect_free','stable_reads','bounded_queries',
],false);

$pdo->beginTransaction();
try{
    $adminId=mg_it_user($pdo,$runId.'-admin@example.test','Admin Dashboard Operator');
    $now=gmdate('Y-m-d H:i:s');

    mg_it_insert($pdo,'operational_alerts',[
        'public_id'=>mg_public_uuid(),'merchant_user_id'=>null,'user_id'=>$adminId,'alert_type'=>'validation.alert',
        'severity'=>'critical','status'=>'open','title'=>'Validation alert','body'=>'Safe operational summary.',
        'action_url'=>'/account-admin.php','created_at'=>$now,'updated_at'=>$now,
    ]);
    mg_it_insert($pdo,'security_logs',[
        'severity'=>'warning','event_type'=>'validation.security','user_id'=>$adminId,'request_id'=>'validation-'.$runId,
        'message'=>'Safe security summary.','context_json'=>json_encode(['secret'=>'never-public'],JSON_THROW_ON_ERROR),
        'created_at'=>$now,
    ]);
    mg_it_insert($pdo,'audit_logs',[
        'user_id'=>$adminId,'action'=>'validation.admin.read','entity_type'=>'dashboard',
        'metadata_json'=>json_encode(['provider_reference'=>'never-public'],JSON_THROW_ON_ERROR),'created_at'=>$now,
    ]);
    mg_it_insert($pdo,'operational_check_results',[
        'public_id'=>mg_public_uuid(),'check_key'=>'validation.readiness','check_scope'=>'platform','status'=>'fail',
        'summary'=>'Validation readiness summary.','details_json'=>json_encode(['private'=>'never-public'],JSON_THROW_ON_ERROR),
        'checked_at'=>$now,'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600),'created_at'=>$now,
    ]);
    mg_it_insert($pdo,'operational_incidents',[
        'public_id'=>mg_public_uuid(),'incident_key'=>'validation-'.$runId,'title'=>'Validation incident','severity'=>'sev2',
        'status'=>'investigating','service_key'=>'admin-dashboard','summary'=>'Validation incident summary.',
        'opened_by_user_id'=>$adminId,'opened_at'=>$now,'metadata_json'=>json_encode(['private'=>'never-public'],JSON_THROW_ON_ERROR),
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    mg_it_insert($pdo,'deployment_releases',[
        'public_id'=>mg_public_uuid(),'release_version'=>'validation-'.$runId,'git_commit_sha'=>str_repeat('a',40),
        'environment'=>'staging','status'=>'validating','rollback_plan_json'=>json_encode(['steps'=>['restore']],JSON_THROW_ON_ERROR),
        'created_at'=>$now,'updated_at'=>$now,
    ]);

    $super=['id'=>$adminId,'roles'=>['super_admin'],'permissions'=>[]];
    $full=mg_admin_dashboard_read($pdo,$super,['window_days'=>30]);
    $queryCount=(int)$full['meta']['query_count'];
    mg_ad_assert($full['access']['super_admin']===true&&$full['access']['dashboard']===true,'Super admin access failed.');
    $summary['super_admin_access']=true;

    mg_ad_assert(is_array($full['platform'])&&(int)$full['platform']['users_total']>=1,'Platform aggregation failed.');
    mg_ad_assert((int)$full['platform']['users_new']>=1,'Windowed user aggregation failed.');
    $summary['platform_aggregation']=true;

    mg_ad_assert((int)$full['operations']['critical_alerts']>=1,'Critical alert aggregation failed.');
    mg_ad_assert((int)$full['operations']['security_warnings']>=1,'Security warning aggregation failed.');
    mg_ad_assert((int)$full['operations']['open_incidents']>=1,'Incident aggregation failed.');
    mg_ad_assert((int)$full['operations']['failed_checks']>=1,'Operational check aggregation failed.');
    $summary['operations_aggregation']=true;

    mg_ad_assert(($full['alerts'][0]['title']??null)==='Validation alert','Recent alert projection failed.');
    mg_ad_assert(($full['security'][0]['event_type']??null)==='validation.security','Security projection failed.');
    mg_ad_assert(($full['audit'][0]['action']??null)==='validation.admin.read','Audit projection failed.');
    mg_ad_assert(($full['checks'][0]['key']??null)==='validation.readiness','Check projection failed.');
    mg_ad_assert(($full['incidents'][0]['key']??null)==='validation-'.$runId,'Incident projection failed.');
    mg_ad_assert(($full['release']['version']??null)==='validation-'.$runId,'Release projection failed.');
    $summary['safe_recent_records']=true;

    $auditOnly=mg_admin_dashboard_read($pdo,['id'=>$adminId,'roles'=>[],'permissions'=>['admin.audit.view']],['window_days'=>30]);
    mg_ad_assert($auditOnly['access']['audit']===true&&$auditOnly['platform']===null&&$auditOnly['commerce']===null&&$auditOnly['security']===[],'Audit-only partition failed.');
    $securityOnly=mg_admin_dashboard_read($pdo,['id'=>$adminId,'roles'=>[],'permissions'=>['security.logs.view']],['window_days'=>30]);
    mg_ad_assert($securityOnly['access']['security']===true&&count($securityOnly['security'])>=1&&$securityOnly['audit']===[],'Security-only partition failed.');
    try{mg_admin_dashboard_read($pdo,['id'=>$adminId,'roles'=>[],'permissions'=>[]]);throw new RuntimeException('Ordinary user read succeeded.');}catch(RuntimeException $error){mg_ad_assert($error->getMessage()==='Permission denied.','Unexpected denial behavior.');}
    $summary['permission_partitioning']=true;

    mg_ad_assert(mg_admin_dashboard_window_days(1)===7&&mg_admin_dashboard_window_days(999)===90&&mg_admin_dashboard_window_days(30)===30,'Window bounds failed.');
    $summary['window_bounds']=true;

    $keys=[];mg_ad_keys($full,$keys);
    foreach(['metadata_json','context_json','details_json','rollback_plan_json','provider_reference','provider_customer_id','provider_payment_method_ref','password_hash','session_hash'] as $forbidden){
        mg_ad_assert(!in_array($forbidden,$keys,true),'Forbidden key leaked: '.$forbidden);
    }
    $encoded=json_encode($full,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    mg_ad_assert(!str_contains($encoded,'never-public'),'Private payload value leaked.');
    $summary['no_private_data']=true;

    $tables=['audit_logs','events','security_logs','user_sessions'];$before=[];
    foreach($tables as $table)$before[$table]=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM `'.$table.'`');
    $repeat=mg_admin_dashboard_read($pdo,$super,['window_days'=>30]);
    foreach($tables as $table)mg_ad_assert($before[$table]===(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM `'.$table.'`'),'Read side effect in '.$table.'.');
    $summary['read_side_effect_free']=true;

    foreach(['platform','commerce','operations','alerts','security','audit','checks','incidents','release','shortcuts'] as $section)mg_ad_assert($full[$section]===$repeat[$section],'Repeated read changed section '.$section.'.');
    $summary['stable_reads']=true;
    mg_ad_assert($queryCount<=10&&(int)$repeat['meta']['query_count']===$queryCount,'Query count was unbounded or unstable.');
    $summary['bounded_queries']=true;

    $pdo->rollBack();
    echo json_encode($summary+['suite'=>'admin_dashboard_foundation','queries_per_full_read'=>$queryCount],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}
