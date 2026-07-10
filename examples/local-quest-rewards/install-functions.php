<?php
declare(strict_types=1);

function lqi_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function lqi_post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function lqi_secret(string $k): string { return (string)($_POST[$k] ?? ''); }
function lqi_bool(string $k): bool { return !empty($_POST[$k]); }
function lqi_config_path(): string { return __DIR__ . '/config.php'; }
function lqi_token(): string { return $_SESSION['lqi_csrf'] ??= bin2hex(random_bytes(32)); }
function lqi_check_token(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $known = (string)($_SESSION['lqi_csrf'] ?? '');
    $sent = (string)($_POST['_lqi_csrf'] ?? '');
    if ($known === '' || $sent === '' || !hash_equals($known, $sent)) throw new RuntimeException('The install session expired. Refresh and try again.');
}
function lqi_schema_paths(): array {
    return [__DIR__.'/database/local_quest_rewards.sql',__DIR__.'/database/local_quest_admin_auth.sql',__DIR__.'/database/local_quest_production_foundation_v1.sql',__DIR__.'/database/local_quest_participant_auth_v1.sql'];
}
function lqi_required_tables(): array {
    return ['lqr_admin_users','lqr_users','lqr_link_states','lqr_quests','lqr_quest_completions','lqr_rewards','lqr_reward_claims','lqr_signed_code_replays','lqr_webhook_deliveries','lqr_admin_audit_events','lqr_events','lqr_app_state','lqr_admin_password_resets','lqr_schema_versions','lqr_participant_auth_tokens','lqr_participant_login_attempts'];
}
function lqi_pdo(string $host, string $database, string $user, string $password, bool $withDatabase): PDO {
    $dsn = $withDatabase ? "mysql:host={$host};dbname={$database};charset=utf8mb4" : "mysql:host={$host};charset=utf8mb4";
    return new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function lqi_sql_statements(string $sql): array {
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*(?:--|#).*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('~/\*.*?\*/~s', '', $sql) ?? $sql;
    return array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])));
}
function lqi_run_sql_file(PDO $pdo, string $path): int {
    if (!is_file($path)) throw new RuntimeException('Schema file missing: '.basename($path));
    $sql = file_get_contents($path);
    if (!is_string($sql)) throw new RuntimeException('Schema file unreadable: '.basename($path));
    $count=0; foreach(lqi_sql_statements($sql) as $statement){$pdo->exec($statement);$count++;} return $count;
}
function lqi_atomic_write(string $path, string $content): void {
    $dir=dirname($path); if(!is_writable($dir)) throw new RuntimeException('Application folder is not writable.');
    $tmp=tempnam($dir,'.lqi-'); if(!is_string($tmp)) throw new RuntimeException('Could not create temporary config file.');
    try{if(file_put_contents($tmp,$content,LOCK_EX)===false) throw new RuntimeException('Could not write temporary config file.');@chmod($tmp,0600);if(!@rename($tmp,$path)) throw new RuntimeException('Could not move config.php into place.');@chmod($path,0600);}finally{if(is_file($tmp))@unlink($tmp);}
}
function lqi_write_config(array $v): ?string {
    $path=lqi_config_path();$backup=null;
    if(is_file($path)){ $backup=$path.'.bak-'.gmdate('Ymd-His'); if(!@copy($path,$backup)) throw new RuntimeException('Existing config.php could not be backed up.'); @chmod($backup,0600); }
    $config=[
        'app_name'=>$v['app_name'],'app_public_url'=>$v['app_public_url'],'base_url'=>$v['base_url'],'api_key'=>$v['api_key'],
        'default_program_id'=>$v['default_program_id'],'default_template_id'=>$v['default_template_id'],'webhook_secret'=>$v['webhook_secret'],
        'mode'=>$v['mode'],'allow_sandbox_shortcut'=>$v['allow_sandbox_shortcut'],
        'security'=>['session_name'=>'LQRSESSID','session_timeout_minutes'=>60,'csrf_field'=>'_lqr_csrf','csrf_ttl_minutes'=>120,'signed_code_ttl_minutes'=>15,'signed_code_secret'=>$v['signed_code_secret']],
        'auth'=>['mail_enabled'=>false,'mail_from'=>'no-reply@localhost','password_reset_ttl_minutes'=>30,'email_verification_ttl_minutes'=>1440,'max_login_attempts'=>5,'login_window_minutes'=>15],
        'admin'=>['username'=>$v['owner_username'],'email'=>$v['owner_email'],'password'=>'','password_hash'=>$v['owner_hash'],'bootstrap_enabled'=>false,'reset_token_ttl_minutes'=>30],
        'storage'=>['driver'=>'mysql','dsn'=>"mysql:host={$v['db_host']};dbname={$v['db_name']};charset=utf8mb4",'username'=>$v['db_user'],'password'=>$v['db_secret'],'options'=>[]],
        'installation'=>['schema_version'=>LQI_SCHEMA_VERSION,'installed_at'=>gmdate('c')],
    ];
    lqi_atomic_write($path,"<?php\ndeclare(strict_types=1);\n\nreturn ".var_export($config,true).";\n"); return $backup;
}
function lqi_seed_owner(PDO $pdo,string $username,string $email,string $hash): void {
    $publicId='admin_'.substr(hash('sha256',strtolower($username).'|local-quest-owner'),0,16);
    $stmt=$pdo->prepare("INSERT INTO lqr_admin_users (public_id,username,email,password_hash,display_name,role_key,status,force_password_change,created_at,updated_at) VALUES (?,?,?,?,?,'owner','active',0,NOW(),NOW()) ON DUPLICATE KEY UPDATE email=VALUES(email),password_hash=VALUES(password_hash),display_name=VALUES(display_name),role_key='owner',status='active',force_password_change=0,updated_at=NOW()");
    $stmt->execute([$publicId,strtolower($username),strtolower($email),$hash,'Owner']);
}
function lqi_missing_tables(PDO $pdo): array {
    $missing=[];$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    foreach(lqi_required_tables() as $table){$stmt->execute([$table]);if((int)$stmt->fetchColumn()<1)$missing[]=$table;} return $missing;
}
function lqi_api_test(string $baseUrl,string $apiKey): array {
    $url=rtrim($baseUrl,'/').'/api/public/v1/programs/index.php';$headers=['Authorization: Bearer '.$apiKey,'Accept: application/json','User-Agent: LocalQuestInstaller/1.0'];
    if(function_exists('curl_init')){$ch=curl_init($url);if($ch===false)throw new RuntimeException('Could not initialize cURL.');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);if($body===false){$error=curl_error($ch);curl_close($ch);throw new RuntimeException($error);}$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);}
    elseif((bool)ini_get('allow_url_fopen')){$context=stream_context_create(['http'=>['method'=>'GET','header'=>implode("\r\n",$headers),'ignore_errors'=>true,'timeout'=>20]]);@file_get_contents($url,false,$context);preg_match('/\s(\d{3})\s/',(string)(($http_response_header??[])[0]??''),$m);$status=isset($m[1])?(int)$m[1]:0;}
    else return ['ok'=>false,'status'=>0,'detail'=>'No HTTP client is available for the API check.'];
    return ['ok'=>$status>=200&&$status<300,'status'=>$status,'detail'=>'Microgifter programs endpoint returned HTTP '.$status.'.'];
}
function lqi_check(string $name,bool $ok,string $detail): array { return ['name'=>$name,'ok'=>$ok,'detail'=>$detail]; }
function lqi_diagnostics(?array $db=null): array {
    $checks=[
        lqi_check('PHP version',version_compare(PHP_VERSION,'8.2.0','>='),'Current: '.PHP_VERSION.' / Required: 8.2+'),
        lqi_check('PDO extension',extension_loaded('pdo'),extension_loaded('pdo')?'PDO is loaded.':'PDO is missing.'),
        lqi_check('PDO MySQL driver',in_array('mysql',PDO::getAvailableDrivers(),true),'Available: '.implode(', ',PDO::getAvailableDrivers())),
        lqi_check('HTTP client',function_exists('curl_init')||(bool)ini_get('allow_url_fopen'),'cURL or stream access is required.'),
        lqi_check('Application folder',is_writable(__DIR__),is_writable(__DIR__)?'Writable for config and lock.':'Not writable.'),
    ];
    if($db){try{$pdo=lqi_pdo($db['host'],$db['name'],$db['user'],$db['password'],true);$missing=lqi_missing_tables($pdo);$checks[]=lqi_check('Database connection',true,'Connected successfully.');$checks[]=lqi_check('Required schema',$missing===[],$missing===[]?'All 16 required tables are present.':'Missing: '.implode(', ',$missing));}catch(Throwable $e){$checks[]=lqi_check('Database connection',false,$e->getMessage());}}
    return $checks;
}
