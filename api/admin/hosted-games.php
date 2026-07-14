<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-games.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$actorId = (int)$user['id'];
$canView = mg_admin_permission_user_has($user, 'admin.hosted_games.view')
    || mg_admin_permission_user_has($user, 'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage');
$canManage = mg_admin_permission_user_has($user, 'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage');
if (!$canView) mg_fail('Hosted Games administrative permission is required.', 403);
if ($method !== 'GET' && !$canManage) mg_fail('Hosted Games management permission is required.', 403);

$pdo = mg_db();
if (!mg_hosted_game_schema_ready($pdo)) {
    mg_fail('Hosted Games setup is incomplete. Import database/hosted_games_management_v1.sql.', 503);
}

function mg_admin_hosted_games_rows(PDO $pdo, ?string $gamePublicId = null): array
{
    $where = '';
    $params = [];
    if ($gamePublicId !== null && $gamePublicId !== '') {
        $where = 'WHERE hg.public_id=?';
        $params[] = $gamePublicId;
    }
    $stmt = $pdo->prepare(
        "SELECT hg.*,u.email AS merchant_email,COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) AS merchant_name,
                hgr.version_number,hgr.file_count,hgr.extracted_bytes,hgr.package_checksum,
                dp.public_id AS program_public_id,dp.name AS program_name,
                c.public_id AS campaign_public_id,c.title AS campaign_title,
                cpt.public_id AS pppm_public_id,cpv.title AS reward_title,
                COALESCE(metrics.plays,0) AS plays,COALESCE(metrics.rewards_delivered,0) AS rewards_delivered,
                COALESCE(metrics.failures,0) AS failures,metrics.last_activity_at
         FROM hosted_games hg
         INNER JOIN users u ON u.id=hg.merchant_user_id
         LEFT JOIN hosted_game_releases hgr ON hgr.public_id=hg.current_release_public_id AND hgr.game_id=hg.id
         LEFT JOIN distribution_programs dp ON dp.id=hg.distribution_program_id
         LEFT JOIN campaigns c ON c.id=hg.campaign_id
         LEFT JOIN catalog_pppm_templates cpt ON cpt.id=hg.pppm_template_id
         LEFT JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id
         LEFT JOIN (
             SELECT game_id,COUNT(*) plays,SUM(status='delivered') rewards_delivered,SUM(status='failed') failures,MAX(updated_at) last_activity_at
             FROM hosted_game_runs GROUP BY game_id
         ) metrics ON metrics.game_id=hg.id
         {$where}
         ORDER BY FIELD(hg.status,'active','draft','paused','archived'),hg.updated_at DESC,hg.id DESC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_admin_hosted_game_payload(PDO $pdo, array $row): array
{
    $database = mg_hosted_game_database_public(mg_hosted_game_database_row($pdo, (int)$row['id'], false));
    return [
        'id'=>(string)$row['public_id'],
        'name'=>(string)$row['name'],
        'slug'=>(string)$row['slug'],
        'status'=>(string)$row['status'],
        'integration_status'=>(string)$row['integration_status'],
        'database_status'=>(string)$row['database_status'],
        'merchant'=>[
            'user_id'=>(int)$row['merchant_user_id'],
            'name'=>(string)$row['merchant_name'],
            'email'=>(string)$row['merchant_email'],
        ],
        'public_url'=>mg_hosted_game_base_url() . '/games/' . rawurlencode((string)$row['slug']) . '/',
        'release'=>!empty($row['current_release_public_id']) ? [
            'id'=>(string)$row['current_release_public_id'],
            'version'=>(int)($row['version_number'] ?? 0),
            'file_count'=>(int)($row['file_count'] ?? 0),
            'extracted_bytes'=>(int)($row['extracted_bytes'] ?? 0),
            'checksum'=>$row['package_checksum'] ?? null,
        ] : null,
        'program'=>!empty($row['program_public_id']) ? ['id'=>(string)$row['program_public_id'],'name'=>(string)$row['program_name']] : null,
        'campaign'=>!empty($row['campaign_public_id']) ? ['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title']] : null,
        'reward'=>!empty($row['pppm_public_id']) ? ['id'=>(string)$row['pppm_public_id'],'title'=>(string)$row['reward_title']] : null,
        'database'=>$database,
        'readiness'=>mg_hosted_game_readiness($pdo, $row),
        'analytics'=>[
            'plays'=>(int)($row['plays'] ?? 0),
            'rewards_delivered'=>(int)($row['rewards_delivered'] ?? 0),
            'failures'=>(int)($row['failures'] ?? 0),
            'last_activity_at'=>$row['last_activity_at'] ?? null,
        ],
        'created_at'=>$row['created_at'] ?? null,
        'updated_at'=>$row['updated_at'] ?? null,
    ];
}

function mg_admin_hosted_game_response(PDO $pdo, string $gamePublicId, string $message): never
{
    $rows = mg_admin_hosted_games_rows($pdo, $gamePublicId);
    if ($rows === []) mg_fail('Hosted game could not be reloaded.', 500);
    mg_ok(['game'=>mg_admin_hosted_game_payload($pdo, $rows[0])], $message);
}

if ($method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $rows = mg_admin_hosted_games_rows($pdo);
    if ($q !== '') {
        $needle = mb_strtolower($q);
        $rows = array_values(array_filter($rows, static function(array $row) use ($needle): bool {
            $haystack = mb_strtolower(implode(' ', [
                (string)$row['name'],(string)$row['slug'],(string)$row['merchant_name'],(string)$row['merchant_email'],
                (string)($row['program_name'] ?? ''),(string)($row['campaign_title'] ?? ''),(string)($row['reward_title'] ?? ''),
            ]));
            return str_contains($haystack, $needle);
        }));
    }
    mg_ok([
        'schema_ready'=>true,
        'migration'=>'hosted_games_management_v1.sql',
        'credential_encryption_ready'=>mg_hosted_game_encryption_ready(),
        'games'=>array_map(static fn(array $row): array => mg_admin_hosted_game_payload($pdo, $row), $rows),
    ]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.hosted_games.write', 'user:' . $actorId, 30, 300);
$action = strtolower(trim((string)($input['action'] ?? '')));
$gamePublicId = trim((string)($input['game_id'] ?? ''));
if ($gamePublicId === '') mg_fail('Hosted game is required.', 422);

try {
    $game = mg_hosted_game_by_public_id($pdo, $gamePublicId, false);
    if (!$game) mg_fail('Hosted game not found.', 404);

    if ($action === 'save_database') {
        if (!mg_hosted_game_encryption_ready()) mg_fail('Hosted game credential encryption is not configured.', 503);
        $host = trim((string)($input['host'] ?? ''));
        $port = max(1, min(65535, (int)($input['port'] ?? 3306)));
        $databaseName = trim((string)($input['database_name'] ?? ''));
        $username = trim((string)($input['username'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $charset = strtolower(trim((string)($input['charset'] ?? 'utf8mb4')));
        $testAfterSave = !array_key_exists('test_after_save', $input) || !empty($input['test_after_save']);
        if ($host === '' || mb_strlen($host) > 255 || preg_match('/[\s\/@?#]/', $host)) mg_fail('Enter a valid database host without a URL scheme.', 422);
        if ($databaseName === '' || mb_strlen($databaseName) > 190 || preg_match('/^[A-Za-z0-9_$-]+$/', $databaseName) !== 1) mg_fail('Enter a valid database name.', 422);
        if (!in_array($charset, ['utf8mb4','utf8'], true)) mg_fail('Unsupported database character set.', 422);

        $pdo->beginTransaction();
        $lockedGame = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
        if (!$lockedGame) mg_fail('Hosted game not found.', 404);
        $existing = mg_hosted_game_database_row($pdo, (int)$lockedGame['id'], true);
        if ($username === '' && !$existing) mg_fail('Database username is required.', 422);
        if ($password === '' && !$existing) mg_fail('Database password is required.', 422);
        $usernameCipher = $username !== '' ? mg_hosted_game_encrypt_secret($username) : (string)$existing['username_ciphertext'];
        $passwordCipher = $password !== '' ? mg_hosted_game_encrypt_secret($password) : (string)$existing['password_ciphertext'];
        if ($existing) {
            $pdo->prepare("UPDATE hosted_game_database_connections SET host=?,port=?,database_name=?,username_ciphertext=?,password_ciphertext=?,charset=?,status='pending',last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$host,$port,$databaseName,$usernameCipher,$passwordCipher,$charset,$actorId,(int)$existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO hosted_game_database_connections (public_id,game_id,driver,host,port,database_name,username_ciphertext,password_ciphertext,charset,status,updated_by_user_id,created_at,updated_at) VALUES (?,?,'mysql',?,?,?,?,?,?,'pending',?,NOW(),NOW())")
                ->execute([mg_hosted_game_uuid(),(int)$lockedGame['id'],$host,$port,$databaseName,$usernameCipher,$passwordCipher,$charset,$actorId]);
        }
        $pdo->prepare("UPDATE hosted_games SET database_status='pending',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$lockedGame['id']]);
        $pdo->commit();

        if ($testAfterSave) {
            try {
                $result = mg_hosted_game_test_database($pdo, (int)$lockedGame['id'], true);
                $pdo->prepare("UPDATE hosted_game_database_connections SET status='ready',last_tested_at=NOW(),last_connected_at=NOW(),last_error_message=NULL,updated_at=NOW() WHERE game_id=?")
                    ->execute([(int)$lockedGame['id']]);
                $pdo->prepare("UPDATE hosted_games SET database_status='ready',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                    ->execute([$actorId,(int)$lockedGame['id']]);
                mg_audit('admin.hosted_game.database_saved','hosted_game',['game_id'=>$gamePublicId,'connected'=>true,'server_version'=>$result['server_version'] ?? null],$actorId);
                mg_admin_hosted_game_response($pdo,$gamePublicId,'Game database saved, connected, and initialized.');
            } catch (Throwable $testError) {
                $message = mb_substr($testError->getMessage(),0,500);
                $pdo->prepare("UPDATE hosted_game_database_connections SET status='error',last_tested_at=NOW(),last_error_message=?,updated_at=NOW() WHERE game_id=?")
                    ->execute([$message,(int)$lockedGame['id']]);
                $pdo->prepare("UPDATE hosted_games SET database_status='error',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                    ->execute([$actorId,(int)$lockedGame['id']]);
                mg_security_log('warning','admin.hosted_game.database_test_failed','Game database credentials were saved but the connection failed.',['game_id'=>$gamePublicId,'message'=>$message],$actorId);
                mg_admin_hosted_game_response($pdo,$gamePublicId,'Database settings were saved, but the connection test failed.');
            }
        }
        mg_audit('admin.hosted_game.database_saved','hosted_game',['game_id'=>$gamePublicId,'tested'=>false],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Game database settings saved.');
    }

    if ($action === 'test_database') {
        try {
            $result = mg_hosted_game_test_database($pdo, (int)$game['id'], true);
            $pdo->prepare("UPDATE hosted_game_database_connections SET status='ready',last_tested_at=NOW(),last_connected_at=NOW(),last_error_message=NULL,updated_at=NOW() WHERE game_id=?")
                ->execute([(int)$game['id']]);
            $pdo->prepare("UPDATE hosted_games SET database_status='ready',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$actorId,(int)$game['id']]);
            mg_audit('admin.hosted_game.database_tested','hosted_game',['game_id'=>$gamePublicId,'result'=>$result],$actorId);
            mg_admin_hosted_game_response($pdo,$gamePublicId,'Game database connected and standard tables are ready.');
        } catch (Throwable $testError) {
            $message = mb_substr($testError->getMessage(),0,500);
            $pdo->prepare("UPDATE hosted_game_database_connections SET status='error',last_tested_at=NOW(),last_error_message=?,updated_at=NOW() WHERE game_id=?")
                ->execute([$message,(int)$game['id']]);
            $pdo->prepare("UPDATE hosted_games SET database_status='error',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$actorId,(int)$game['id']]);
            mg_security_log('warning','admin.hosted_game.database_test_failed','Game database connection test failed.',['game_id'=>$gamePublicId,'message'=>$message],$actorId);
            mg_admin_hosted_game_response($pdo,$gamePublicId,'Game database connection failed.');
        }
    }

    if ($action === 'disable_database') {
        $pdo->beginTransaction();
        $lockedGame = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
        if (!$lockedGame) mg_fail('Hosted game not found.', 404);
        $pdo->prepare("UPDATE hosted_game_database_connections SET status='disabled',last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE game_id=?")
            ->execute([$actorId,(int)$lockedGame['id']]);
        $pdo->prepare("UPDATE hosted_games SET database_status='disabled',status=IF(status='active','paused',status),updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$lockedGame['id']]);
        $pdo->commit();
        mg_audit('admin.hosted_game.database_disabled','hosted_game',['game_id'=>$gamePublicId],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Game database access disabled.');
    }

    if ($action === 'pause_game') {
        $pdo->prepare("UPDATE hosted_games SET status='paused',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$game['id']]);
        mg_audit('admin.hosted_game.paused','hosted_game',['game_id'=>$gamePublicId],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Hosted game paused by platform administration.');
    }
} catch (InvalidArgumentException|MgHostedGameException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','admin.hosted_game.failed','Hosted Games admin operation failed.',['action'=>$action,'game_id'=>$gamePublicId,'message'=>$error->getMessage()],$actorId);
    mg_fail('Unable to complete the Hosted Games admin operation.',500);
}

mg_fail('Invalid Hosted Games admin action.',422);
