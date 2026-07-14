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
        "SELECT hg.*,u.email AS merchant_email,COALESCE(NULLIF(mw.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) AS merchant_name,
                hgr.version_number,hgr.file_count,hgr.extracted_bytes,hgr.package_checksum,
                dp.public_id AS program_public_id,dp.name AS program_name,dp.status AS program_status,
                c.public_id AS campaign_public_id,c.title AS campaign_title,c.status AS campaign_status,
                cpt.public_id AS pppm_public_id,cpv.title AS reward_title,cpv.unit_value_cents AS reward_value_cents,cpv.currency AS reward_currency,
                COALESCE(metrics.plays,0) AS plays,COALESCE(metrics.rewards_delivered,0) AS rewards_delivered,
                COALESCE(metrics.failures,0) AS failures,metrics.last_activity_at
         FROM hosted_games hg
         INNER JOIN users u ON u.id=hg.merchant_user_id
         LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=hg.merchant_user_id
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
        'description'=>(string)($row['description'] ?? ''),
        'cover_url'=>(string)($row['cover_url'] ?? ''),
        'entry_file'=>(string)($row['entry_file'] ?? 'index.html'),
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
        'program'=>!empty($row['program_public_id']) ? ['id'=>(string)$row['program_public_id'],'name'=>(string)$row['program_name'],'status'=>(string)($row['program_status'] ?? '')] : null,
        'campaign'=>!empty($row['campaign_public_id']) ? ['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title'],'status'=>(string)($row['campaign_status'] ?? '')] : null,
        'reward'=>!empty($row['pppm_public_id']) ? [
            'id'=>(string)$row['pppm_public_id'],
            'title'=>(string)$row['reward_title'],
            'unit_value_cents'=>(int)($row['reward_value_cents'] ?? 0),
            'currency'=>(string)($row['reward_currency'] ?? 'USD'),
        ] : null,
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

function mg_admin_hosted_game_merchants(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT mw.merchant_user_id AS user_id,COALESCE(NULLIF(mw.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) AS name,u.email,mw.status
                        FROM merchant_workspaces mw INNER JOIN users u ON u.id=mw.merchant_user_id
                        ORDER BY name ASC,u.email ASC");
    return array_map(static fn(array $row): array => [
        'user_id'=>(int)$row['user_id'],
        'name'=>(string)$row['name'],
        'email'=>(string)$row['email'],
        'status'=>(string)$row['status'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_admin_hosted_game_merchant_exists(PDO $pdo, int $merchantUserId): bool
{
    if ($merchantUserId < 1) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM merchant_workspaces WHERE merchant_user_id=? LIMIT 1');
    $stmt->execute([$merchantUserId]);
    return (bool)$stmt->fetchColumn();
}

function mg_admin_hosted_game_options(PDO $pdo, int $merchantUserId): array
{
    if (!mg_admin_hosted_game_merchant_exists($pdo, $merchantUserId)) return ['programs'=>[],'campaigns'=>[],'rewards'=>[]];

    $programs = $pdo->prepare("SELECT public_id,name,program_type,status,starts_at,ends_at,budget_cents,max_items,per_recipient_limit FROM distribution_programs WHERE merchant_user_id=? AND status<>'archived' ORDER BY FIELD(status,'active','scheduled','draft','paused','completed','cancelled'),updated_at DESC,id DESC");
    $programs->execute([$merchantUserId]);

    $campaigns = $pdo->prepare("SELECT c.public_id,c.title,c.campaign_type,c.status,c.starts_at,c.ends_at,c.reward_template_id,rt.public_id AS reward_template_public_id,rt.title AS reward_template_title FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.merchant_user_id=? AND c.status<>'archived' ORDER BY FIELD(c.status,'active','draft','paused','ended'),c.updated_at DESC,c.id DESC");
    $campaigns->execute([$merchantUserId]);

    $rewards = $pdo->prepare(
        "SELECT dp.public_id AS program_id,dp.name AS program_name,dp.status AS program_status,
                cpt.public_id AS template_id,cpt.status AS template_status,dpp.status AS program_product_status,
                dpp.quantity_limit,dpp.quantity_issued,cp.public_id AS product_id,cp.slug AS product_slug,
                cpv.title,cpv.description,cpv.unit_value_cents,cpv.currency
         FROM distribution_program_products dpp
         INNER JOIN distribution_programs dp ON dp.id=dpp.program_id
         INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id
         INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id
         INNER JOIN catalog_products cp ON cp.id=cpv.product_id
         WHERE dp.merchant_user_id=? AND cp.merchant_user_id=? AND cpt.status='active' AND dpp.status='active'
         ORDER BY dp.updated_at DESC,cpv.title ASC"
    );
    $rewards->execute([$merchantUserId,$merchantUserId]);

    return [
        'programs'=>$programs->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'campaigns'=>$campaigns->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'rewards'=>$rewards->fetchAll(PDO::FETCH_ASSOC) ?: [],
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
    $merchantUserId = max(0, (int)($_GET['merchant_user_id'] ?? 0));
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
        'can_manage'=>$GLOBALS['canManage'],
        'merchants'=>mg_admin_hosted_game_merchants($pdo),
        'options'=>$merchantUserId > 0 ? mg_admin_hosted_game_options($pdo, $merchantUserId) : ['programs'=>[],'campaigns'=>[],'rewards'=>[]],
        'games'=>array_map(static fn(array $row): array => mg_admin_hosted_game_payload($pdo, $row), $rows),
    ]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.hosted_games.write', 'user:' . $actorId, 50, 300);
$action = strtolower(trim((string)($input['action'] ?? '')));
$gamePublicId = trim((string)($input['game_id'] ?? ''));

try {
    if ($action === 'save_game') {
        $name = trim((string)($input['name'] ?? ''));
        $slug = mg_hosted_game_slug((string)($input['slug'] ?? $name));
        $description = trim((string)($input['description'] ?? ''));
        $coverUrl = trim((string)($input['cover_url'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180) mg_fail('Enter a valid game name.', 422);
        if (mb_strlen($description) > 5000) mg_fail('Game description is too long.', 422);
        if ($coverUrl !== '' && (!filter_var($coverUrl, FILTER_VALIDATE_URL) || mb_strlen($coverUrl) > 500)) mg_fail('Enter a valid cover image URL.', 422);

        $pdo->beginTransaction();
        if ($gamePublicId === '') {
            $merchantUserId = max(0, (int)($input['merchant_user_id'] ?? 0));
            if (!mg_admin_hosted_game_merchant_exists($pdo, $merchantUserId)) mg_fail('Select a valid merchant account.', 422);
            $gamePublicId = mg_hosted_game_uuid();
            $pdo->prepare("INSERT INTO hosted_games (public_id,merchant_user_id,name,slug,description,cover_url,status,integration_status,database_status,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'draft','pending','pending',?,?,NOW(),NOW())")
                ->execute([$gamePublicId,$merchantUserId,$name,$slug,$description ?: null,$coverUrl ?: null,$actorId,$actorId]);
        } else {
            $game = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
            if (!$game) mg_fail('Hosted game not found.', 404);
            if ((string)$game['status'] === 'archived') mg_fail('Archived games cannot be edited.', 409);
            $merchantUserId = (int)$game['merchant_user_id'];
            $pdo->prepare('UPDATE hosted_games SET name=?,slug=?,description=?,cover_url=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
                ->execute([$name,$slug,$description ?: null,$coverUrl ?: null,$actorId,(int)$game['id']]);
            if (!empty($game['developer_app_id'])) {
                $pdo->prepare('UPDATE merchant_developer_apps SET name=?,updated_at=NOW() WHERE id=? AND merchant_user_id=?')
                    ->execute([$name . ' Hosted Game API',(int)$game['developer_app_id'],$merchantUserId]);
            }
        }
        $pdo->commit();
        mg_audit('admin.hosted_game.saved','hosted_game',['game_id'=>$gamePublicId,'merchant_user_id'=>$merchantUserId,'slug'=>$slug],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Hosted game saved by Microgifter Admin.');
    }

    if ($gamePublicId === '') mg_fail('Hosted game is required.', 422);
    $game = mg_hosted_game_by_public_id($pdo, $gamePublicId, false);
    if (!$game) mg_fail('Hosted game not found.', 404);
    $merchantUserId = (int)$game['merchant_user_id'];

    if ($action === 'configure_integration') {
        $programPublicId = trim((string)($input['program_id'] ?? ''));
        $campaignPublicId = trim((string)($input['campaign_id'] ?? ''));
        $templatePublicId = trim((string)($input['reward_template_id'] ?? ''));
        if ($programPublicId === '' || $campaignPublicId === '' || $templatePublicId === '') mg_fail('Program, campaign, and reward are required.', 422);

        $pdo->beginTransaction();
        $game = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
        if (!$game) mg_fail('Hosted game not found.', 404);
        if ((string)$game['status'] === 'archived') mg_fail('Archived games cannot be configured.', 409);
        $programStmt = $pdo->prepare("SELECT * FROM distribution_programs WHERE public_id=? AND merchant_user_id=? AND status NOT IN ('cancelled','archived') LIMIT 1 FOR UPDATE");
        $programStmt->execute([$programPublicId,$merchantUserId]);
        $program = $programStmt->fetch(PDO::FETCH_ASSOC);
        if (!$program) mg_fail('Distribution Program not found or unavailable for this merchant.', 404);
        $campaignStmt = $pdo->prepare("SELECT * FROM campaigns WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1 FOR UPDATE");
        $campaignStmt->execute([$campaignPublicId,$merchantUserId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) mg_fail('Campaign not found or unavailable for this merchant.', 404);
        $rewardStmt = $pdo->prepare("SELECT cpt.id,cpt.public_id,cpv.title FROM distribution_program_products dpp INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id INNER JOIN catalog_products cp ON cp.id=cpv.product_id WHERE dpp.program_id=? AND cpt.public_id=? AND dpp.status='active' AND cpt.status='active' AND cp.merchant_user_id=? LIMIT 1 FOR UPDATE");
        $rewardStmt->execute([(int)$program['id'],$templatePublicId,$merchantUserId]);
        $reward = $rewardStmt->fetch(PDO::FETCH_ASSOC);
        if (!$reward) mg_fail('The selected reward is not actively attached to this merchant Distribution Program.', 409);
        mg_hosted_game_ensure_runtime_integration($pdo, $game, $actorId, (int)$program['id']);
        $pdo->prepare("UPDATE hosted_games SET distribution_program_id=?,campaign_id=?,pppm_template_id=?,integration_status='ready',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([(int)$program['id'],(int)$campaign['id'],(int)$reward['id'],$actorId,(int)$game['id']]);
        $pdo->commit();
        mg_audit('admin.hosted_game.integration_configured','hosted_game',['game_id'=>$gamePublicId,'merchant_user_id'=>$merchantUserId,'program_id'=>$programPublicId,'campaign_id'=>$campaignPublicId,'reward_template_id'=>$templatePublicId],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Game Program, Campaign, reward, and API integration configured by Microgifter Admin.');
    }

    if ($action === 'publish_game') {
        $pdo->beginTransaction();
        $lockedGame = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
        if (!$lockedGame) mg_fail('Hosted game not found.', 404);
        $readiness = mg_hosted_game_readiness($pdo, $lockedGame);
        if (!$readiness['publish_ready']) mg_fail('Upload a release, configure the Program/Campaign/reward integration, and verify the isolated database before publishing.', 409);
        $pdo->prepare("UPDATE hosted_games SET status='active',published_at=COALESCE(published_at,NOW()),archived_at=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$lockedGame['id']]);
        if (!empty($lockedGame['developer_app_id'])) {
            $pdo->prepare("UPDATE merchant_developer_apps SET status='active',updated_at=NOW() WHERE id=? AND merchant_user_id=?")
                ->execute([(int)$lockedGame['developer_app_id'],$merchantUserId]);
        }
        $pdo->commit();
        mg_audit('admin.hosted_game.published','hosted_game',['game_id'=>$gamePublicId,'merchant_user_id'=>$merchantUserId],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Hosted game published by Microgifter Admin.');
    }

    if ($action === 'pause_game') {
        $pdo->prepare("UPDATE hosted_games SET status='paused',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$game['id']]);
        if (!empty($game['developer_app_id'])) {
            $pdo->prepare("UPDATE merchant_developer_apps SET status='paused',updated_at=NOW() WHERE id=? AND merchant_user_id=?")
                ->execute([(int)$game['developer_app_id'],$merchantUserId]);
        }
        mg_audit('admin.hosted_game.paused','hosted_game',['game_id'=>$gamePublicId,'merchant_user_id'=>$merchantUserId],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Hosted game paused by Microgifter Admin.');
    }

    if ($action === 'archive_game') {
        $pdo->beginTransaction();
        $lockedGame = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
        if (!$lockedGame) mg_fail('Hosted game not found.', 404);
        $pdo->prepare("UPDATE hosted_games SET status='archived',archived_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$lockedGame['id']]);
        if (!empty($lockedGame['developer_app_id'])) {
            $pdo->prepare("UPDATE merchant_developer_apps SET status='paused',updated_at=NOW() WHERE id=? AND merchant_user_id=?")
                ->execute([(int)$lockedGame['developer_app_id'],$merchantUserId]);
        }
        $pdo->commit();
        mg_audit('admin.hosted_game.archived','hosted_game',['game_id'=>$gamePublicId,'merchant_user_id'=>$merchantUserId],$actorId);
        mg_admin_hosted_game_response($pdo,$gamePublicId,'Hosted game archived by Microgifter Admin.');
    }

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
} catch (InvalidArgumentException|MgHostedGameException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','admin.hosted_game.failed','Hosted Games admin operation failed.',['action'=>$action,'game_id'=>$gamePublicId,'message'=>$error->getMessage()],$actorId);
    mg_fail('Unable to complete the Hosted Games admin operation.',500);
}

mg_fail('Invalid Hosted Games admin action.',422);
