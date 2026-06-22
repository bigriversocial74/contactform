<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_launch_qa_json_array(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_launch_qa_private_literal_host(string $host): bool
{
    $host = trim($host, '[]');
    if (strcasecmp($host, 'localhost') === 0) return true;
    if (!filter_var($host, FILTER_VALIDATE_IP)) return false;
    return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function mg_launch_qa_webhook_policy(?string $url, string $environment): array
{
    $url = trim((string)$url);
    if ($url === '') return ['ok'=>false,'message'=>'Webhook URL is missing.'];
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return ['ok'=>false,'message'=>'Webhook URL is malformed.'];
    $scheme = strtolower((string)$parts['scheme']);
    $environment = strtolower($environment) === 'live' ? 'live' : 'test';
    if (!in_array($scheme, ['https','http'], true)) return ['ok'=>false,'message'=>'Webhook URL must use HTTP or HTTPS.'];
    if ($environment === 'live' && $scheme !== 'https') return ['ok'=>false,'message'=>'Live webhook URLs must use HTTPS.'];
    if ($environment === 'live' && mg_launch_qa_private_literal_host((string)$parts['host'])) return ['ok'=>false,'message'=>'Live webhook URL cannot use localhost, private IP, or reserved IP hosts.'];
    return ['ok'=>true,'message'=>'Webhook URL policy passed.'];
}

function mg_launch_qa_check(string $key, string $label, bool $ok, string $pass, string $fail, string $severity = 'block'): array
{
    return ['key'=>$key,'label'=>$label,'state'=>$ok?'ok':$severity,'message'=>$ok?$pass:$fail];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.developer_api.view');
$pdo = mg_db();

$appsStmt = $pdo->prepare("SELECT mda.public_id,mda.name,mda.environment,mda.status,mda.webhook_url,mda.webhook_secret_hash,mda.scopes_json,mda.metadata_json,mda.created_at,mda.updated_at,dp.public_id AS default_program_id,dp.name AS default_program_name,dp.status AS default_program_status,dsc.status AS source_status FROM merchant_developer_apps mda LEFT JOIN distribution_programs dp ON dp.id=mda.default_program_id LEFT JOIN distribution_source_connections dsc ON dsc.id=mda.distribution_source_connection_id WHERE mda.merchant_user_id=? ORDER BY FIELD(mda.environment,'live','test'),mda.updated_at DESC,mda.id DESC");
$appsStmt->execute([(int)$user['id']]);
$apps = $appsStmt->fetchAll();

$keyStmt = $pdo->prepare("SELECT mda.public_id AS app_public_id, COUNT(*) active_key_count, SUM(mak.environment='live') live_key_count, MAX(mak.last_used_at) last_used_at FROM merchant_api_keys mak INNER JOIN merchant_developer_apps mda ON mda.id=mak.app_id WHERE mak.merchant_user_id=? AND mak.status='active' AND (mak.expires_at IS NULL OR mak.expires_at>NOW()) GROUP BY mda.public_id");
$keyStmt->execute([(int)$user['id']]);
$keyStats = [];
foreach ($keyStmt->fetchAll() as $row) $keyStats[(string)$row['app_public_id']] = $row;

$logStmt = $pdo->prepare("SELECT mda.public_id AS app_public_id, SUM(darl.status_code BETWEEN 200 AND 299) success_7d, SUM(darl.status_code >= 400) errors_7d, MAX(CASE WHEN darl.status_code BETWEEN 200 AND 299 THEN darl.created_at END) last_success_at FROM merchant_developer_apps mda LEFT JOIN distribution_api_request_logs darl ON darl.app_id=mda.id AND darl.created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY) WHERE mda.merchant_user_id=? GROUP BY mda.public_id");
$logStmt->execute([(int)$user['id']]);
$logStats = [];
foreach ($logStmt->fetchAll() as $row) $logStats[(string)$row['app_public_id']] = $row;

$qaApps = [];
$ready = 0;
$blocked = 0;
$warnings = 0;
$liveApps = 0;
foreach ($apps as $app) {
    $appId = (string)$app['public_id'];
    $environment = (string)$app['environment'];
    $isLive = $environment === 'live';
    if ($isLive) $liveApps++;
    $keys = $keyStats[$appId] ?? [];
    $logs = $logStats[$appId] ?? [];
    $scopes = mg_launch_qa_json_array($app['scopes_json'] ?? null);
    $hasIssueScope = in_array('distribution:rewards.issue', $scopes, true);
    $hasStatusScope = in_array('distribution:rewards.status', $scopes, true);
    $hasProgramScope = in_array('distribution:programs.read', $scopes, true);
    $webhookPolicy = mg_launch_qa_webhook_policy($app['webhook_url'] ?? null, $environment);
    $checks = [
        mg_launch_qa_check('live_mode','Live-mode app',$isLive,'App is live-mode.','App is still test-mode.','warn'),
        mg_launch_qa_check('active_app','Active app',(string)$app['status']==='active','App is active.','App is not active.'),
        mg_launch_qa_check('default_program','Default program',!empty($app['default_program_id']),'Default program is attached.','Default program is missing.'),
        mg_launch_qa_check('program_status','Program active',(string)($app['default_program_status'] ?? '')==='active','Default program is active.','Default program is not active.'),
        mg_launch_qa_check('source_status','Source connection active',(string)($app['source_status'] ?? '')==='active','Source connection is active.','Source connection is not active.'),
        mg_launch_qa_check('live_credential','Live credential',(int)($keys['live_key_count'] ?? 0)>0,'At least one live credential exists.','No active live credential exists.'),
        mg_launch_qa_check('webhook_url','Webhook URL',!empty($app['webhook_url']),'Webhook URL is configured.','Webhook URL is missing.'),
        mg_launch_qa_check('webhook_policy','Webhook URL policy',(bool)$webhookPolicy['ok'],$webhookPolicy['message'],$webhookPolicy['message']),
        mg_launch_qa_check('webhook_secret','Webhook signing key',trim((string)($app['webhook_secret_hash'] ?? '')) !== '','Webhook signing key is configured.','Webhook signing key is missing.'),
        mg_launch_qa_check('scopes','Required scopes',$hasIssueScope && $hasStatusScope && $hasProgramScope,'Required public API scopes are present.','Required public API scopes are missing.'),
        mg_launch_qa_check('recent_success','Recent successful request',(int)($logs['success_7d'] ?? 0)>0,'Recent successful API request found.','No successful API request in the last 7 days.','warn'),
    ];
    $blockCount = 0;
    $warnCount = 0;
    foreach ($checks as $check) {
        if ($check['state'] === 'block') $blockCount++;
        if ($check['state'] === 'warn') $warnCount++;
    }
    if ($blockCount === 0 && $isLive) $ready++;
    if ($blockCount > 0) $blocked++;
    if ($warnCount > 0) $warnings++;
    $qaApps[] = [
        'app_id'=>$appId,
        'name'=>(string)$app['name'],
        'environment'=>$environment,
        'status'=>(string)$app['status'],
        'ready'=>$blockCount===0 && $isLive,
        'blockers'=>$blockCount,
        'warnings'=>$warnCount,
        'checks'=>$checks,
        'last_success_at'=>$logs['last_success_at'] ?? null,
        'errors_7d'=>(int)($logs['errors_7d'] ?? 0),
    ];
}

mg_ok([
    'summary'=>[
        'ready_for_launch'=>$ready > 0,
        'ready_apps'=>$ready,
        'blocked_apps'=>$blocked,
        'warning_apps'=>$warnings,
        'live_apps'=>$liveApps,
        'total_apps'=>count($qaApps),
    ],
    'apps'=>$qaApps,
    'generated_at'=>gmdate('c'),
]);
