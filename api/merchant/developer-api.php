<?php
 declare(strict_types=1);
 require_once __DIR__ . '/_merchant.php';

 function mg_developer_api_json(mixed $value, int $maxBytes = 65536): ?string
 {
     if ($value === null || $value === '' || $value === []) return null;
     if (!is_array($value)) mg_fail('Expected an object or list.', 422);
     $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
     if (!is_string($json) || strlen($json) > $maxBytes) mg_fail('Developer API payload is too large.', 422);
     return $json;
 }

 function mg_developer_api_normalize_scopes(mixed $value): array
 {
     $allowed = ['distribution:programs.read','distribution:rewards.issue','distribution:rewards.status','distribution:webhooks.manage'];
     if (!is_array($value) || $value === []) return $allowed;
     $scopes = array_values(array_unique(array_filter(array_map(static fn(mixed $scope): string => trim((string) $scope), $value), static fn(string $scope): bool => $scope !== '')));
     foreach ($scopes as $scope) if (!in_array($scope, $allowed, true)) mg_fail('Invalid API scope.', 422);
     return $scopes;
 }

 function mg_developer_api_origins(mixed $value): array
 {
     if (is_string($value)) $value = preg_split('/[\s,]+/', $value) ?: [];
     if (!is_array($value)) return [];
     $origins = [];
     foreach ($value as $origin) {
         $origin = trim((string) $origin);
         if ($origin === '') continue;
         if (mb_strlen($origin) > 255) mg_fail('Allowed origin is too long.', 422);
         $origins[] = $origin;
     }
     return array_values(array_unique($origins));
 }

 function mg_developer_api_program_db_id(PDO $pdo, int $merchantUserId, ?string $programPublicId): ?int
 {
     $programPublicId = trim((string) ($programPublicId ?? ''));
     if ($programPublicId === '') return null;
     $stmt = $pdo->prepare('SELECT id FROM distribution_programs WHERE public_id=? AND merchant_user_id=? LIMIT 1');
     $stmt->execute([$programPublicId, $merchantUserId]);
     $id = $stmt->fetchColumn();
     if (!$id) mg_fail('Distribution program not found.', 404);
     return (int) $id;
 }

 function mg_developer_api_app_for_update(PDO $pdo, int $merchantUserId, string $appPublicId): array
 {
     $stmt = $pdo->prepare('SELECT * FROM merchant_developer_apps WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
     $stmt->execute([$appPublicId, $merchantUserId]);
     $app = $stmt->fetch();
     if (!$app) mg_fail('Developer app not found.', 404);
     return $app;
 }

 function mg_developer_api_fetch_analytics(PDO $pdo, int $merchantUserId): array
 {
     $requestTotals = $pdo->prepare("SELECT COUNT(*) total_requests, SUM(created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) requests_24h, SUM(status_code >= 400) error_requests, SUM(status_code >= 400 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) errors_24h, SUM(status_code=429) rate_limited_requests, SUM(status_code=429 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) rate_limited_24h FROM distribution_api_request_logs WHERE merchant_user_id=?");
     $requestTotals->execute([$merchantUserId]);
     $totals = $requestTotals->fetch() ?: [];

     $daily = $pdo->prepare("SELECT DATE(created_at) AS day, COUNT(*) requests, SUM(status_code >= 400) errors, SUM(status_code=429) rate_limited FROM distribution_api_request_logs WHERE merchant_user_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
     $daily->execute([$merchantUserId]);

     $status = $pdo->prepare("SELECT CASE WHEN status_code BETWEEN 200 AND 299 THEN '2xx' WHEN status_code BETWEEN 300 AND 399 THEN '3xx' WHEN status_code BETWEEN 400 AND 499 THEN '4xx' WHEN status_code >= 500 THEN '5xx' ELSE 'unknown' END AS status_family, response_status, COUNT(*) requests FROM distribution_api_request_logs WHERE merchant_user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY status_family,response_status ORDER BY requests DESC,status_family ASC LIMIT 20");
     $status->execute([$merchantUserId]);

     $apps = $pdo->prepare("SELECT mda.public_id,mda.name,mda.environment,mda.status, COUNT(darl.id) requests_7d, SUM(darl.status_code >= 400) errors_7d, SUM(darl.status_code=429) rate_limited_7d, MAX(darl.created_at) last_request_at FROM merchant_developer_apps mda LEFT JOIN distribution_api_request_logs darl ON darl.app_id=mda.id AND darl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) WHERE mda.merchant_user_id=? GROUP BY mda.id ORDER BY requests_7d DESC,mda.updated_at DESC LIMIT 25");
     $apps->execute([$merchantUserId]);

     $keys = $pdo->prepare("SELECT mak.public_id,mak.name,mak.environment,mak.key_prefix,mak.status,mda.name AS app_name, COUNT(darl.id) requests_7d, SUM(darl.status_code >= 400) errors_7d, SUM(darl.status_code=429) rate_limited_7d, MAX(darl.created_at) last_request_at FROM merchant_api_keys mak INNER JOIN merchant_developer_apps mda ON mda.id=mak.app_id LEFT JOIN distribution_api_request_logs darl ON darl.api_key_id=mak.id AND darl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) WHERE mak.merchant_user_id=? GROUP BY mak.id,mda.id ORDER BY requests_7d DESC,mak.created_at DESC LIMIT 25");
     $keys->execute([$merchantUserId]);

     $quota = $pdo->prepare("SELECT paqb.bucket_scope,paqb.bucket_key,paqb.limit_value,paqb.used_count,paqb.window_start,paqb.window_end,mak.key_prefix,mda.name AS app_name FROM public_api_quota_buckets paqb INNER JOIN merchant_api_keys mak ON mak.id=paqb.api_key_id INNER JOIN merchant_developer_apps mda ON mda.id=paqb.app_id WHERE paqb.merchant_user_id=? AND paqb.window_end > NOW() ORDER BY FIELD(paqb.bucket_scope,'minute','day','month'),paqb.used_count DESC LIMIT 30");
     $quota->execute([$merchantUserId]);

     $webhooks = $pdo->prepare("SELECT event_type,status,COUNT(*) events,MAX(created_at) last_event_at FROM developer_webhook_events WHERE merchant_user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY event_type,status ORDER BY events DESC,event_type ASC LIMIT 25");
     $webhooks->execute([$merchantUserId]);

     $sandbox = $pdo->prepare("SELECT COUNT(*) sandbox_rewards, SUM(created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) sandbox_rewards_24h, MAX(created_at) last_sandbox_reward_at FROM public_api_sandbox_rewards WHERE merchant_user_id=?");
     $sandbox->execute([$merchantUserId]);

     return [
         'totals' => $totals,
         'daily' => $daily->fetchAll(),
         'status_breakdown' => $status->fetchAll(),
         'apps' => $apps->fetchAll(),
         'keys' => $keys->fetchAll(),
         'quota_buckets' => $quota->fetchAll(),
         'webhooks' => $webhooks->fetchAll(),
         'sandbox' => $sandbox->fetch() ?: [],
     ];
 }

 $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
 $user = mg_require_permission($method === 'GET' ? 'merchant.developer_api.view' : 'merchant.developer_api.manage');
 $pdo = mg_db();

 if ($method === 'GET') {
     $programs = $pdo->prepare('SELECT public_id,name,program_type,status FROM distribution_programs WHERE merchant_user_id=? ORDER BY updated_at DESC,id DESC');
     $programs->execute([(int) $user['id']]);
     $apps = $pdo->prepare("SELECT mda.public_id,mda.name,mda.environment,mda.status,mda.allowed_origins_json,mda.webhook_url,mda.scopes_json,mda.created_at,mda.updated_at,dp.public_id AS default_program_id,dp.name AS default_program_name,dsc.public_id AS source_id,dsc.provider_key,dsc.status AS source_status,COUNT(mak.id) AS key_count,SUM(mak.status='active') AS active_key_count,MAX(mak.last_used_at) AS last_used_at FROM merchant_developer_apps mda LEFT JOIN distribution_programs dp ON dp.id=mda.default_program_id LEFT JOIN distribution_source_connections dsc ON dsc.id=mda.distribution_source_connection_id LEFT JOIN merchant_api_keys mak ON mak.app_id=mda.id WHERE mda.merchant_user_id=? GROUP BY mda.id,dp.id,dsc.id ORDER BY mda.updated_at DESC,mda.id DESC");
     $apps->execute([(int) $user['id']]);
     $keys = $pdo->prepare("SELECT mak.public_id,mda.public_id AS app_public_id,mda.name AS app_name,mak.name,mak.environment,mak.key_prefix,mak.scopes_json,mak.status,mak.expires_at,mak.last_used_at,mak.created_at,mak.revoked_at FROM merchant_api_keys mak INNER JOIN merchant_developer_apps mda ON mda.id=mak.app_id WHERE mak.merchant_user_id=? ORDER BY mak.created_at DESC,mak.id DESC");
     $keys->execute([(int) $user['id']]);
     $logs = $pdo->prepare("SELECT darl.public_id,darl.method,darl.endpoint,darl.status_code,darl.response_status,darl.idempotency_key,darl.error_message,darl.created_at,mda.name AS app_name,mak.key_prefix FROM distribution_api_request_logs darl LEFT JOIN merchant_developer_apps mda ON mda.id=darl.app_id LEFT JOIN merchant_api_keys mak ON mak.id=darl.api_key_id WHERE darl.merchant_user_id=? ORDER BY darl.created_at DESC,darl.id DESC LIMIT 50");
     $logs->execute([(int) $user['id']]);
     $summary = $pdo->prepare("SELECT COUNT(*) app_count,SUM(status='active') active_apps FROM merchant_developer_apps WHERE merchant_user_id=?");
     $summary->execute([(int) $user['id']]);
     $keySummary = $pdo->prepare("SELECT COUNT(*) key_count,SUM(status='active') active_keys FROM merchant_api_keys WHERE merchant_user_id=?");
     $keySummary->execute([(int) $user['id']]);
     mg_ok(['summary' => array_merge($summary->fetch() ?: [], $keySummary->fetch() ?: []),'programs' => $programs->fetchAll(),'apps' => $apps->fetchAll(),'keys' => $keys->fetchAll(),'logs' => $logs->fetchAll(),'analytics' => mg_developer_api_fetch_analytics($pdo, (int)$user['id']),'scopes' => ['distribution:programs.read','distribution:rewards.issue','distribution:rewards.status','distribution:webhooks.manage']]);
 }

 if ($method !== 'POST') mg_fail('Method not allowed.', 405);
 $input = mg_input();
 mg_require_csrf_for_write($input);
 $action = trim((string) ($input['action'] ?? ''));

 if ($action === 'save_app') {
     $appId = trim((string) ($input['app_id'] ?? ''));
     $name = trim((string) ($input['name'] ?? ''));
     $environment = trim((string) ($input['environment'] ?? 'test')) === 'live' ? 'live' : 'test';
     $status = trim((string) ($input['status'] ?? 'active'));
     $programPublicId = trim((string) ($input['default_program_id'] ?? '')) ?: null;
     $webhookUrl = trim((string) ($input['webhook_url'] ?? '')) ?: null;
     $origins = mg_developer_api_origins($input['allowed_origins'] ?? []);
     $scopes = mg_developer_api_normalize_scopes($input['scopes'] ?? []);
     if ($name === '' || mb_strlen($name) > 180) mg_fail('Invalid app name.', 422);
     if (!in_array($status, ['draft','active','paused','revoked'], true)) mg_fail('Invalid app status.', 422);
     if ($webhookUrl !== null && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) mg_fail('Invalid webhook URL.', 422);
     $pdo->beginTransaction();
     try {
         $programDbId = mg_developer_api_program_db_id($pdo, (int) $user['id'], $programPublicId);
         if ($appId === '') {
             $sourceId = mg_merchant_uuid();
             $appId = mg_merchant_uuid();
             $providerKey = 'api-' . strtolower(str_replace('-', '', substr($appId, 0, 18)));
             $pdo->prepare("INSERT INTO distribution_source_connections (public_id,merchant_user_id,program_id,source_type,provider_key,display_name,status,secret_hash,configuration_json,created_at,updated_at) VALUES (?,?,?,'api',?,?,?,?,?,NOW(),NOW())")
                 ->execute([$sourceId, (int) $user['id'], $programDbId, $providerKey, $name . ' API', $status === 'active' ? 'active' : 'paused', hash('sha256', $sourceId . $providerKey), mg_developer_api_json(['environment' => $environment])]);
             $sourceDbId = (int) $pdo->lastInsertId();
             $pdo->prepare("INSERT INTO merchant_developer_apps (public_id,merchant_user_id,distribution_source_connection_id,default_program_id,name,environment,status,allowed_origins_json,webhook_url,webhook_secret_hash,scopes_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
                 ->execute([$appId, (int) $user['id'], $sourceDbId, $programDbId, $name, $environment, $status, mg_developer_api_json($origins), $webhookUrl, $webhookUrl ? hash('sha256', $webhookUrl . $name) : null, mg_developer_api_json($scopes), (int) $user['id']]);
         } else {
             $app = mg_developer_api_app_for_update($pdo, (int) $user['id'], $appId);
             $pdo->prepare("UPDATE merchant_developer_apps SET default_program_id=?,name=?,environment=?,status=?,allowed_origins_json=?,webhook_url=?,webhook_secret_hash=COALESCE(?,webhook_secret_hash),scopes_json=?,updated_at=NOW() WHERE id=?")
                 ->execute([$programDbId, $name, $environment, $status, mg_developer_api_json($origins), $webhookUrl, $webhookUrl ? hash('sha256', $webhookUrl . $name) : null, mg_developer_api_json($scopes), (int) $app['id']]);
             if (!empty($app['distribution_source_connection_id'])) {
                 $sourceStatus = $status === 'active' ? 'active' : ($status === 'revoked' ? 'revoked' : 'paused');
                 $pdo->prepare('UPDATE distribution_source_connections SET program_id=?,display_name=?,status=?,configuration_json=?,updated_at=NOW() WHERE id=? AND merchant_user_id=?')
                     ->execute([$programDbId, $name . ' API', $sourceStatus, mg_developer_api_json(['environment' => $environment]), (int) $app['distribution_source_connection_id'], (int) $user['id']]);
             }
         }
         $pdo->commit();
         mg_audit('merchant.developer_app_saved', 'merchant_developer_app', ['app_id' => $appId, 'environment' => $environment, 'status' => $status], (int) $user['id']);
         mg_ok(['app_id' => $appId, 'status' => $status], 'Developer app saved.', 201);
     } catch (Throwable $e) {
         if ($pdo->inTransaction()) $pdo->rollBack();
         mg_fail('Unable to save developer app.', 500);
     }
 }

 mg_fail('Invalid developer API action.', 422);
