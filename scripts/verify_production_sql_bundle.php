<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$options=getopt('', ['output::']);
$outputPath=isset($options['output'])&&is_string($options['output'])?trim($options['output']):'';
$config=require dirname(__DIR__).'/api/config.php';
$db=$config['db'];
if(($user=getenv('MG_MIGRATION_DB_USER'))!==false&&$user!=='')$db['user']=$user;
if(($pass=getenv('MG_MIGRATION_DB_PASS'))!==false)$db['pass']=$pass;
$database=(string)($db['name']??'');
if($database===''||preg_match('/^[A-Za-z0-9_]+$/',$database)!==1)throw new RuntimeException('A safe MG_DB_NAME is required.');

$pdo=new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s',$db['host'],$database,$db['charset']),
    (string)$db['user'],(string)$db['pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
);
$scalar=static function(string $sql,array $params=[])use($pdo):mixed{$stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();};
$columnCount=static fn(string $table,string $column):int=>(int)$scalar('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$column]);
$indexCount=static fn(string $table,string $index):int=>(int)$scalar('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$index]);

$checks=[
    'migration_markers'=>(int)$scalar("SELECT COUNT(*) FROM schema_migrations WHERE migration_key IN ('stage_v1c_checkout_session_intent_authority','stage_v1d_transfer_conversations','stage_v1f_stripe_payments','stage_v1_release_trigger_portability')")===4,
    'checkout_intent_column'=>$columnCount('checkout_sessions','payment_intent_id')===1,
    'checkout_intent_index'=>$indexCount('checkout_sessions','idx_checkout_sessions_payment_intent')===1,
    'checkout_intent_foreign_key'=>(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='checkout_sessions' AND CONSTRAINT_NAME='fk_checkout_sessions_payment_intent' AND CONSTRAINT_TYPE='FOREIGN KEY'")===1,
    'checkout_backfill'=>(int)$scalar("SELECT COUNT(*) FROM checkout_sessions cs INNER JOIN payment_intents pi ON pi.id=cs.payment_intent_id WHERE cs.public_id='40000000-0000-4000-8000-000000000003' AND pi.public_id='40000000-0000-4000-8000-000000000002'")===1,
    'conversation_backfill'=>(string)$scalar("SELECT conversation_key FROM message_threads WHERE public_id='40000000-0000-4000-8000-000000000005'")==='legacy:40000000-0000-4000-8000-000000000005',
    'legacy_thread_unique_removed'=>$indexCount('message_threads','uq_message_threads_microgift_instance')===0,
    'conversation_unique_present'=>$indexCount('message_threads','uq_message_threads_microgift_conversation')===2,
    'platform_credentials_table'=>(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_platform_credentials'")===1,
    'payment_defaults'=>(string)$scalar("SELECT CONCAT(application_fee_cents,':',COALESCE(destination_account_reference,'NULL')) FROM payment_intents WHERE public_id='40000000-0000-4000-8000-000000000002'")==='0:NULL',
    'provider_defaults'=>(string)$scalar("SELECT CONCAT(details_submitted,':',onboarding_status,':',COALESCE(JSON_LENGTH(requirements_due_json),0),':',COALESCE(last_synced_at,'NULL')) FROM payment_provider_accounts WHERE public_id='40000000-0000-4000-8000-000000000004'")==='0:not_started:0:NULL',
    'moderation_trigger'=>(int)$scalar("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='trg_catalog_assets_review_state'")===1,
];
foreach($checks as $name=>$passed)if(!$passed)throw new RuntimeException("Post-bundle check failed: {$name}");

$pdo->exec("UPDATE catalog_assets SET moderation_status='blocked' WHERE public_id='40000000-0000-4000-8000-000000000006'");
if((string)$scalar("SELECT status FROM catalog_assets WHERE public_id='40000000-0000-4000-8000-000000000006'")!=='quarantined')throw new RuntimeException('Moderation trigger did not quarantine blocked media.');
$pdo->exec("UPDATE catalog_assets SET moderation_status='clear' WHERE public_id='40000000-0000-4000-8000-000000000006'");
if((string)$scalar("SELECT status FROM catalog_assets WHERE public_id='40000000-0000-4000-8000-000000000006'")!=='ready')throw new RuntimeException('Moderation trigger did not restore cleared media.');
$checks['moderation_trigger_behavior']=true;

$fingerprint=(string)$scalar("SELECT SHA2(CONCAT_WS('|',
 (SELECT COUNT(*) FROM users WHERE email IN ('bundle-buyer@example.test','bundle-merchant@example.test')),
 COALESCE((SELECT payment_intent_id FROM checkout_sessions WHERE public_id='40000000-0000-4000-8000-000000000003'),'missing'),
 COALESCE((SELECT conversation_key FROM message_threads WHERE public_id='40000000-0000-4000-8000-000000000005'),'missing'),
 (SELECT status FROM catalog_assets WHERE public_id='40000000-0000-4000-8000-000000000006')
),256)");
$report=['status'=>'passed','database'=>$database,'checks'=>$checks,'fixture_fingerprint'=>$fingerprint,'table_count'=>(int)$scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()'),'migration_count'=>(int)$scalar('SELECT COUNT(*) FROM schema_migrations'),'completed_at'=>gmdate('c')];
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
if($outputPath!==''){
    $directory=dirname($outputPath);
    if(!is_dir($directory)&&!mkdir($directory,0770,true)&&!is_dir($directory))throw new RuntimeException('Unable to create evidence directory.');
    if(file_put_contents($outputPath,$json)===false)throw new RuntimeException('Unable to write bundle verification evidence.');
}
fwrite(STDOUT,$json);
