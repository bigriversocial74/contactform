<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-pwa.php';

function mg_admin_merchant_pwa_can_view(array $user): bool
{
    return mg_admin_permission_user_has($user, 'admin.merchants.view')
        || mg_admin_permission_user_has($user, 'admin.catalog.view')
        || mg_admin_permission_user_has($user, 'admin.settings.manage');
}

function mg_admin_merchant_pwa_can_manage(array $user): bool
{
    return mg_admin_permission_user_has($user, 'admin.merchants.manage')
        || mg_admin_permission_user_has($user, 'admin.catalog.manage')
        || mg_admin_permission_user_has($user, 'admin.settings.manage');
}

function mg_admin_merchant_pwa_summary(PDO $pdo): array
{
    if (!mg_merchant_pwa_schema_ready($pdo)) {
        return ['total'=>0,'draft'=>0,'active'=>0,'paused'=>0,'archived'=>0,'missing_assets'=>0];
    }
    $row = $pdo->query("SELECT COUNT(*) total, SUM(status='draft') draft, SUM(status='active') active, SUM(status='paused') paused, SUM(status='archived') archived FROM merchant_pwa_profiles")->fetch(PDO::FETCH_ASSOC) ?: [];
    $missing = $pdo->query("SELECT COUNT(*) FROM merchant_pwa_profiles p LEFT JOIN merchant_pwa_assets a ON a.profile_id=p.id AND a.status='active' AND a.asset_role IN ('app_icon_192','app_icon_512','splash_logo') WHERE p.status<>'archived' GROUP BY p.id HAVING COUNT(DISTINCT a.asset_role)<3")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    return [
        'total'=>(int)($row['total'] ?? 0),
        'draft'=>(int)($row['draft'] ?? 0),
        'active'=>(int)($row['active'] ?? 0),
        'paused'=>(int)($row['paused'] ?? 0),
        'archived'=>(int)($row['archived'] ?? 0),
        'missing_assets'=>count($missing),
    ];
}

function mg_admin_merchant_pwa_list(PDO $pdo, array $input): array
{
    if (!mg_merchant_pwa_schema_ready($pdo)) {
        return ['schema_ready'=>false,'summary'=>mg_admin_merchant_pwa_summary($pdo),'profiles'=>[]];
    }

    $status = strtolower(trim((string)($input['status'] ?? '')));
    $q = trim((string)($input['q'] ?? ''));
    $where = ['p.status<>\'archived\''];
    $params = [];
    if (in_array($status, ['draft','active','paused','archived'], true)) {
        $where[] = 'p.status=?';
        $params[] = $status;
    }
    if ($q !== '') {
        $where[] = '(p.merchant_slug LIKE ? OR p.app_name LIKE ? OR p.short_name LIKE ? OR w.display_name LIKE ? OR w.support_email LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $sql = "SELECT p.public_id,p.workspace_id,p.merchant_slug,p.app_name,p.short_name,p.description,p.install_headline,p.theme_color,p.background_color,p.status,p.enable_install_prompt,p.enable_push_prompt,p.activated_at,p.updated_at,w.public_id workspace_public_id,w.display_name merchant_display_name,w.status workspace_status,w.support_email,w.support_phone,w.website_url,COUNT(CASE WHEN a.status='active' THEN 1 END) active_asset_count,COUNT(DISTINCT CASE WHEN a.status='active' AND a.asset_role IN ('app_icon_192','app_icon_512','splash_logo') THEN a.asset_role END) required_asset_count FROM merchant_pwa_profiles p INNER JOIN merchant_workspaces w ON w.id=p.workspace_id LEFT JOIN merchant_pwa_assets a ON a.profile_id=p.id WHERE " . implode(' AND ', $where) . " GROUP BY p.id ORDER BY FIELD(p.status,'active','draft','paused','archived'),p.updated_at DESC LIMIT 120";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $profiles = [];
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        $slug = (string)$row['merchant_slug'];
        $profiles[] = [
            'id'=>(string)$row['public_id'],
            'workspace_id'=>(string)$row['workspace_public_id'],
            'merchant_name'=>(string)$row['merchant_display_name'],
            'merchant_slug'=>$slug,
            'app_name'=>(string)$row['app_name'],
            'short_name'=>(string)$row['short_name'],
            'description'=>(string)($row['description'] ?? ''),
            'install_headline'=>(string)($row['install_headline'] ?? ''),
            'theme_color'=>(string)$row['theme_color'],
            'background_color'=>(string)$row['background_color'],
            'status'=>(string)$row['status'],
            'workspace_status'=>(string)$row['workspace_status'],
            'support_email'=>(string)($row['support_email'] ?? ''),
            'support_phone'=>(string)($row['support_phone'] ?? ''),
            'website_url'=>(string)($row['website_url'] ?? ''),
            'active_asset_count'=>(int)$row['active_asset_count'],
            'required_asset_count'=>(int)$row['required_asset_count'],
            'required_assets_ready'=>(int)$row['required_asset_count'] >= 3,
            'enable_install_prompt'=>(int)$row['enable_install_prompt'] === 1,
            'enable_push_prompt'=>(int)$row['enable_push_prompt'] === 1,
            'activated_at'=>$row['activated_at'],
            'updated_at'=>$row['updated_at'],
            'install_url'=>'/m/' . rawurlencode($slug) . '/',
            'app_url'=>'/m/' . rawurlencode($slug) . '/app',
            'manifest_url'=>'/manifest-merchant.php?merchant=' . rawurlencode($slug),
            'merchant_admin_url'=>'/merchant-pwa.php',
        ];
    }
    return ['schema_ready'=>true,'summary'=>mg_admin_merchant_pwa_summary($pdo),'profiles'=>$profiles];
}

function mg_admin_merchant_pwa_update_status(PDO $pdo, string $profilePublicId, string $status, int $userId): array
{
    if (!mg_merchant_pwa_schema_ready($pdo)) mg_fail('Merchant PWA tables are missing. Run migrations first.',409);
    if (!in_array($status, ['draft','active','paused','archived'], true)) mg_fail('Invalid merchant app status.',422);
    $stmt = $pdo->prepare('SELECT id,public_id,merchant_slug,status FROM merchant_pwa_profiles WHERE public_id=? LIMIT 1');
    $stmt->execute([$profilePublicId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) mg_fail('Merchant PWA profile not found.',404);
    $pdo->prepare("UPDATE merchant_pwa_profiles SET status=?,activated_at=CASE WHEN ?='active' AND activated_at IS NULL THEN NOW() ELSE activated_at END,updated_by_user_id=?,updated_at=NOW() WHERE id=?")->execute([$status,$status,$userId,(int)$profile['id']]);
    mg_audit('admin.merchant_pwa.status_updated','merchant_pwa_profile',['profile_id'=>$profilePublicId,'merchant_slug'=>$profile['merchant_slug'],'from_status'=>$profile['status'],'to_status'=>$status],$userId);
    return ['profile_id'=>$profilePublicId,'merchant_slug'=>$profile['merchant_slug'],'status'=>$status];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$userId = (int)$user['id'];
if (!mg_admin_merchant_pwa_can_view($user)) mg_fail('You do not have access to merchant PWA oversight.',403);
$pdo = mg_db();

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.merchant_pwa.read','user:' . $userId,120,60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_admin_merchant_pwa_list($pdo, $_GET) + ['can_manage'=>mg_admin_merchant_pwa_can_manage($user)], 'Merchant PWA profiles loaded.');
    }
    if ($method !== 'POST') mg_fail('Method not allowed.',405);
    if (!mg_admin_merchant_pwa_can_manage($user)) mg_fail('You do not have permission to manage merchant PWA profiles.',403);
    mg_rate_limit('admin.merchant_pwa.write','user:' . $userId,40,300);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? '')));
    if ($action !== 'update_status') mg_fail('Invalid merchant PWA action.',422);
    $result = mg_admin_merchant_pwa_update_status($pdo, trim((string)($input['profile_id'] ?? '')), strtolower(trim((string)($input['status'] ?? ''))), $userId);
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(mg_admin_merchant_pwa_list($pdo, []) + ['can_manage'=>true,'updated'=>$result], 'Merchant PWA status updated.');
} catch (Throwable $e) {
    mg_security_log('error','admin.merchant_pwa.request_failed','Merchant PWA admin request failed.',['exception_class'=>$e::class],$userId);
    mg_fail('Unable to update merchant PWA oversight.',500);
}
