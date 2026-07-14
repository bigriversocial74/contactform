<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-games.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.hosted_games.view' : 'merchant.hosted_games.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];

if (!mg_hosted_game_schema_ready($pdo)) {
    mg_fail('Hosted Games setup is incomplete. Import database/hosted_games_management_v1.sql.', 503);
}

function mg_hosted_game_merchant_query(PDO $pdo, int $merchantUserId, ?string $gamePublicId = null): array
{
    $where = 'hg.merchant_user_id=?';
    $params = [$merchantUserId];
    if ($gamePublicId !== null && $gamePublicId !== '') {
        $where .= ' AND hg.public_id=?';
        $params[] = $gamePublicId;
    }
    $stmt = $pdo->prepare(
        "SELECT hg.*,hgr.version_number,hgr.file_count,hgr.extracted_bytes,hgr.package_checksum,
                dp.public_id AS program_public_id,dp.name AS program_name,dp.status AS program_status,
                c.public_id AS campaign_public_id,c.title AS campaign_title,c.status AS campaign_status,
                cpt.public_id AS pppm_public_id,cpv.title AS reward_title,cpv.unit_value_cents AS reward_value_cents,cpv.currency AS reward_currency,
                COALESCE(metrics.plays,0) AS plays,COALESCE(metrics.qualified,0) AS qualified,
                COALESCE(metrics.rewards_queued,0) AS rewards_queued,COALESCE(metrics.rewards_delivered,0) AS rewards_delivered,
                COALESCE(metrics.failures,0) AS failures,metrics.last_activity_at
         FROM hosted_games hg
         LEFT JOIN hosted_game_releases hgr ON hgr.public_id=hg.current_release_public_id AND hgr.game_id=hg.id
         LEFT JOIN distribution_programs dp ON dp.id=hg.distribution_program_id
         LEFT JOIN campaigns c ON c.id=hg.campaign_id
         LEFT JOIN catalog_pppm_templates cpt ON cpt.id=hg.pppm_template_id
         LEFT JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id
         LEFT JOIN (
             SELECT game_id,COUNT(*) plays,
                    SUM(status IN ('qualified','issuing','queued','delivered')) qualified,
                    SUM(status IN ('queued','delivered')) rewards_queued,
                    SUM(status='delivered') rewards_delivered,
                    SUM(status='failed') failures,
                    MAX(updated_at) last_activity_at
             FROM hosted_game_runs
             GROUP BY game_id
         ) metrics ON metrics.game_id=hg.id
         WHERE {$where}
         ORDER BY FIELD(hg.status,'active','draft','paused','archived'),hg.updated_at DESC,hg.id DESC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_hosted_game_merchant_payload(PDO $pdo, array $row): array
{
    $game = mg_hosted_game_public_record($pdo, $row);
    $game['analytics'] = [
        'plays'=>(int)($row['plays'] ?? 0),
        'qualified'=>(int)($row['qualified'] ?? 0),
        'rewards_queued'=>(int)($row['rewards_queued'] ?? 0),
        'rewards_delivered'=>(int)($row['rewards_delivered'] ?? 0),
        'failures'=>(int)($row['failures'] ?? 0),
        'last_activity_at'=>$row['last_activity_at'] ?? null,
    ];
    return $game;
}

function mg_hosted_game_program_options(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT public_id,name,program_type,status,starts_at,ends_at,budget_cents,max_items,per_recipient_limit FROM distribution_programs WHERE merchant_user_id=? AND status<>'archived' ORDER BY FIELD(status,'active','scheduled','draft','paused','completed','cancelled'),updated_at DESC,id DESC");
    $stmt->execute([$merchantUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_hosted_game_campaign_options(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT c.public_id,c.title,c.campaign_type,c.status,c.starts_at,c.ends_at,c.reward_template_id,rt.public_id AS reward_template_public_id,rt.title AS reward_template_title FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.merchant_user_id=? AND c.status<>'archived' ORDER BY FIELD(c.status,'active','draft','paused','ended'),c.updated_at DESC,c.id DESC");
    $stmt->execute([$merchantUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_hosted_game_reward_options(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare(
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
    $stmt->execute([$merchantUserId,$merchantUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_hosted_game_return(PDO $pdo, int $merchantUserId, string $gamePublicId, string $message): never
{
    $rows = mg_hosted_game_merchant_query($pdo, $merchantUserId, $gamePublicId);
    if ($rows === []) mg_fail('Hosted game could not be reloaded.', 500);
    mg_ok(['game'=>mg_hosted_game_merchant_payload($pdo, $rows[0])], $message);
}

if ($method === 'GET') {
    $games = array_map(
        static fn(array $row): array => mg_hosted_game_merchant_payload($pdo, $row),
        mg_hosted_game_merchant_query($pdo, $merchantUserId)
    );
    mg_ok([
        'schema_ready'=>true,
        'migration'=>'hosted_games_management_v1.sql',
        'credential_encryption_ready'=>mg_hosted_game_encryption_ready(),
        'games'=>$games,
        'programs'=>mg_hosted_game_program_options($pdo, $merchantUserId),
        'campaigns'=>mg_hosted_game_campaign_options($pdo, $merchantUserId),
        'rewards'=>mg_hosted_game_reward_options($pdo, $merchantUserId),
        'upload_limits'=>[
            'max_zip_bytes'=>104857600,
            'max_files'=>5000,
            'max_extracted_bytes'=>536870912,
        ],
    ]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) mg_rate_limit('merchant.hosted_games.write', 'user:' . $merchantUserId, 40, 300);
$action = strtolower(trim((string)($input['action'] ?? '')));

try {
    if ($action === 'save_game') {
        $gamePublicId = trim((string)($input['game_id'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $slug = mg_hosted_game_slug((string)($input['slug'] ?? $name));
        $description = trim((string)($input['description'] ?? ''));
        $coverUrl = trim((string)($input['cover_url'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180) mg_fail('Enter a valid game name.', 422);
        if (mb_strlen($description) > 5000) mg_fail('Game description is too long.', 422);
        if ($coverUrl !== '' && (!filter_var($coverUrl, FILTER_VALIDATE_URL) || mb_strlen($coverUrl) > 500)) mg_fail('Enter a valid cover image URL.', 422);
        $pdo->beginTransaction();
        if ($gamePublicId === '') {
            $gamePublicId = mg_hosted_game_uuid();
            $pdo->prepare("INSERT INTO hosted_games (public_id,merchant_user_id,name,slug,description,cover_url,status,integration_status,database_status,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'draft','pending','pending',?,?,NOW(),NOW())")
                ->execute([$gamePublicId,$merchantUserId,$name,$slug,$description ?: null,$coverUrl ?: null,$merchantUserId,$merchantUserId]);
        } else {
            $game = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, true);
            if ((string)$game['status'] === 'archived') mg_fail('Archived games cannot be edited.', 409);
            $pdo->prepare('UPDATE hosted_games SET name=?,slug=?,description=?,cover_url=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
                ->execute([$name,$slug,$description ?: null,$coverUrl ?: null,$merchantUserId,(int)$game['id']]);
            if (!empty($game['developer_app_id'])) {
                $pdo->prepare('UPDATE merchant_developer_apps SET name=?,updated_at=NOW() WHERE id=? AND merchant_user_id=?')
                    ->execute([$name . ' Hosted Game API',(int)$game['developer_app_id'],$merchantUserId]);
            }
        }
        $pdo->commit();
        mg_audit('merchant.hosted_game.saved','hosted_game',['game_id'=>$gamePublicId,'slug'=>$slug],$merchantUserId);
        mg_hosted_game_return($pdo,$merchantUserId,$gamePublicId,'Hosted game saved.');
    }

    if ($action === 'configure_integration') {
        $gamePublicId = trim((string)($input['game_id'] ?? ''));
        $programPublicId = trim((string)($input['program_id'] ?? ''));
        $campaignPublicId = trim((string)($input['campaign_id'] ?? ''));
        $templatePublicId = trim((string)($input['reward_template_id'] ?? ''));
        if ($gamePublicId === '' || $programPublicId === '' || $campaignPublicId === '' || $templatePublicId === '') {
            mg_fail('Game, program, campaign, and reward are required.', 422);
        }
        $pdo->beginTransaction();
        $game = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, true);
        if ((string)$game['status'] === 'archived') mg_fail('Archived games cannot be configured.', 409);
        $programStmt = $pdo->prepare("SELECT * FROM distribution_programs WHERE public_id=? AND merchant_user_id=? AND status NOT IN ('cancelled','archived') LIMIT 1 FOR UPDATE");
        $programStmt->execute([$programPublicId,$merchantUserId]);
        $program = $programStmt->fetch(PDO::FETCH_ASSOC);
        if (!$program) mg_fail('Distribution Program not found or unavailable.', 404);
        $campaignStmt = $pdo->prepare("SELECT * FROM campaigns WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1 FOR UPDATE");
        $campaignStmt->execute([$campaignPublicId,$merchantUserId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) mg_fail('Campaign not found or unavailable.', 404);
        $rewardStmt = $pdo->prepare("SELECT cpt.id,cpt.public_id,cpv.title FROM distribution_program_products dpp INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id INNER JOIN catalog_products cp ON cp.id=cpv.product_id WHERE dpp.program_id=? AND cpt.public_id=? AND dpp.status='active' AND cpt.status='active' AND cp.merchant_user_id=? LIMIT 1 FOR UPDATE");
        $rewardStmt->execute([(int)$program['id'],$templatePublicId,$merchantUserId]);
        $reward = $rewardStmt->fetch(PDO::FETCH_ASSOC);
        if (!$reward) mg_fail('The selected reward is not actively attached to this Distribution Program.', 409);
        mg_hosted_game_ensure_runtime_integration($pdo, $game, $merchantUserId, (int)$program['id']);
        $pdo->prepare("UPDATE hosted_games SET distribution_program_id=?,campaign_id=?,pppm_template_id=?,integration_status='ready',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([(int)$program['id'],(int)$campaign['id'],(int)$reward['id'],$merchantUserId,(int)$game['id']]);
        $pdo->commit();
        mg_audit('merchant.hosted_game.integration_configured','hosted_game',['game_id'=>$gamePublicId,'program_id'=>$programPublicId,'campaign_id'=>$campaignPublicId,'reward_template_id'=>$templatePublicId],$merchantUserId);
        mg_hosted_game_return($pdo,$merchantUserId,$gamePublicId,'Game integration configured.');
    }

    if (in_array($action, ['publish','pause','archive'], true)) {
        $gamePublicId = trim((string)($input['game_id'] ?? ''));
        $pdo->beginTransaction();
        $game = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, true);
        if ($action === 'publish') {
            $readiness = mg_hosted_game_readiness($pdo, $game);
            if (!$readiness['publish_ready']) {
                mg_fail('Upload a release, configure the reward integration, and have Microgifter Admin verify the game database before publishing.', 409);
            }
            $pdo->prepare("UPDATE hosted_games SET status='active',published_at=COALESCE(published_at,NOW()),archived_at=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$merchantUserId,(int)$game['id']]);
            if (!empty($game['developer_app_id'])) {
                $pdo->prepare("UPDATE merchant_developer_apps SET status='active',updated_at=NOW() WHERE id=? AND merchant_user_id=?")
                    ->execute([(int)$game['developer_app_id'],$merchantUserId]);
            }
            $message = 'Hosted game published.';
        } elseif ($action === 'pause') {
            if ((string)$game['status'] === 'archived') mg_fail('Archived games cannot be paused.', 409);
            $pdo->prepare("UPDATE hosted_games SET status='paused',updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$merchantUserId,(int)$game['id']]);
            $message = 'Hosted game paused.';
        } else {
            $pdo->prepare("UPDATE hosted_games SET status='archived',archived_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$merchantUserId,(int)$game['id']]);
            if (!empty($game['developer_app_id'])) {
                $pdo->prepare("UPDATE merchant_developer_apps SET status='paused',updated_at=NOW() WHERE id=? AND merchant_user_id=?")
                    ->execute([(int)$game['developer_app_id'],$merchantUserId]);
            }
            $message = 'Hosted game archived.';
        }
        $pdo->commit();
        mg_audit('merchant.hosted_game.' . $action,'hosted_game',['game_id'=>$gamePublicId],$merchantUserId);
        mg_hosted_game_return($pdo,$merchantUserId,$gamePublicId,$message);
    }
} catch (InvalidArgumentException|MgHostedGameException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (PDOException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ((string)$error->getCode() === '23000') mg_fail('That game URL slug is already in use.', 409);
    mg_security_log('error','merchant.hosted_game.database_error','Hosted game operation failed.',['action'=>$action,'message'=>$error->getMessage()],$merchantUserId);
    mg_fail('Unable to save the hosted game.', 500);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','merchant.hosted_game.failed','Hosted game operation failed.',['action'=>$action,'message'=>$error->getMessage()],$merchantUserId);
    mg_fail('Unable to complete the hosted game operation.', 500);
}

mg_fail('Invalid hosted game action.', 422);
