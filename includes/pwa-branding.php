<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function mg_pwa_branding_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function mg_pwa_branding_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_pwa_branding_schema_ready(PDO $pdo): bool
{
    return mg_pwa_branding_table_exists($pdo, 'pwa_branding_settings') && mg_pwa_branding_table_exists($pdo, 'pwa_branding_assets');
}

function mg_pwa_branding_roles(): array
{
    return [
        'app_icon_192'=>['label'=>'App icon 192','description'=>'Primary 192x192 install icon.','sizes'=>'192x192','purpose'=>'any','max'=>5242880,'w'=>192,'h'=>192],
        'app_icon_512'=>['label'=>'App icon 512','description'=>'Large 512x512 install icon.','sizes'=>'512x512','purpose'=>'any','max'=>5242880,'w'=>512,'h'=>512],
        'maskable_icon_512'=>['label'=>'Maskable icon','description'=>'512x512 safe-zone launcher icon.','sizes'=>'512x512','purpose'=>'maskable','max'=>5242880,'w'=>512,'h'=>512],
        'apple_touch_icon'=>['label'=>'Apple touch icon','description'=>'180x180 iOS home screen icon.','sizes'=>'180x180','purpose'=>'apple-touch-icon','max'=>5242880,'w'=>180,'h'=>180],
        'notification_icon'=>['label'=>'Notification icon','description'=>'Icon shown in browser push notifications.','sizes'=>'192x192','purpose'=>'notification-icon','max'=>5242880,'w'=>192,'h'=>192],
        'notification_badge'=>['label'=>'Notification badge','description'=>'Small notification tray badge.','sizes'=>'96x96','purpose'=>'notification-badge','max'=>3145728,'w'=>96,'h'=>96],
        'splash_logo'=>['label'=>'Splash logo','description'=>'Logo for the branded PWA launch page.','sizes'=>'512x512','purpose'=>'splash-logo','max'=>5242880,'w'=>512,'h'=>512],
        'splash_background'=>['label'=>'Splash background','description'=>'Optional launch page background image.','sizes'=>'1600x1000','purpose'=>'splash-background','max'=>10485760,'w'=>1600,'h'=>1000],
    ];
}

function mg_pwa_branding_defaults(): array
{
    return [
        'app_name'=>'Microgifter','short_name'=>'Microgifter',
        'description'=>'Microgifter PWA workspace for gifts, claims, campaigns, merchant alerts, and admin operations.',
        'start_url'=>'/pwa-splash.php','scope'=>'/','display'=>'standalone','theme_color'=>'#2563eb','background_color'=>'#f8fafc',
        'splash_title'=>'Microgifter','splash_subtitle'=>'Gifts, rewards, campaigns, claims, and merchant alerts in one installable workspace.',
        'splash_cta_label'=>'Open notifications','splash_cta_url'=>'/notifications.php','asset_version'=>'1',
    ];
}

function mg_pwa_branding_hex(string $value, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', trim($value)) === 1 ? strtolower(trim($value)) : $fallback;
}

function mg_pwa_branding_path(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '' || $value[0] !== '/' || str_starts_with($value, '//') || preg_match('/[\x00-\x1f\x7f]/', $value)) return $fallback;
    return mb_substr($value, 0, 500);
}

function mg_pwa_branding_settings(PDO $pdo): array
{
    $settings = mg_pwa_branding_defaults();
    if (!mg_pwa_branding_table_exists($pdo, 'pwa_branding_settings')) return $settings;
    $rows = $pdo->query("SELECT setting_key,setting_value FROM pwa_branding_settings WHERE setting_key LIKE 'pwa.%'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        $key = preg_replace('/^pwa\./', '', (string)$row['setting_key']);
        if (array_key_exists($key, $settings)) $settings[$key] = (string)$row['setting_value'];
    }
    $defaults = mg_pwa_branding_defaults();
    $settings['theme_color'] = mg_pwa_branding_hex((string)$settings['theme_color'], $defaults['theme_color']);
    $settings['background_color'] = mg_pwa_branding_hex((string)$settings['background_color'], $defaults['background_color']);
    $settings['start_url'] = mg_pwa_branding_path((string)$settings['start_url'], $defaults['start_url']);
    $settings['scope'] = mg_pwa_branding_path((string)$settings['scope'], $defaults['scope']);
    $settings['splash_cta_url'] = mg_pwa_branding_path((string)$settings['splash_cta_url'], $defaults['splash_cta_url']);
    $settings['display'] = in_array($settings['display'], ['standalone','fullscreen','minimal-ui','browser'], true) ? $settings['display'] : 'standalone';
    foreach (['app_name','short_name','description','splash_title','splash_subtitle','splash_cta_label'] as $key) {
        $settings[$key] = trim(mb_substr((string)$settings[$key], 0, in_array($key, ['description','splash_subtitle'], true) ? 260 : 80));
        if ($settings[$key] === '') $settings[$key] = $defaults[$key];
    }
    $settings['asset_version'] = preg_replace('/[^0-9a-zA-Z._-]/', '', (string)$settings['asset_version']) ?: '1';
    return $settings;
}

function mg_pwa_branding_active_assets(PDO $pdo): array
{
    if (!mg_pwa_branding_table_exists($pdo, 'pwa_branding_assets')) return [];
    $rows = $pdo->query("SELECT public_id,asset_role,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,updated_at FROM pwa_branding_assets WHERE status='active' ORDER BY asset_role ASC,id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $assets = [];
    foreach ($rows as $row) {
        $role = (string)$row['asset_role'];
        if (!isset($assets[$role])) $assets[$role] = $row;
    }
    return $assets;
}

function mg_pwa_branding_asset_url(array $asset, array $settings = []): string
{
    $url = '/api/pwa/asset.php?id=' . rawurlencode((string)$asset['public_id']);
    $v = $settings['asset_version'] ?? preg_replace('/[^0-9]/', '', (string)($asset['updated_at'] ?? ''));
    return $v ? $url . '&v=' . rawurlencode((string)$v) : $url;
}

function mg_pwa_branding_payload(PDO $pdo): array
{
    $settings = mg_pwa_branding_settings($pdo);
    $active = mg_pwa_branding_active_assets($pdo);
    $assets = [];
    foreach (mg_pwa_branding_roles() as $role=>$def) {
        $asset = $active[$role] ?? null;
        $assets[$role] = [
            'role'=>$role,'label'=>$def['label'],'description'=>$def['description'],'sizes'=>$def['sizes'],'purpose'=>$def['purpose'],
            'recommended_width'=>$def['w'],'recommended_height'=>$def['h'],'max_bytes'=>$def['max'],
            'asset'=>$asset ? ['id'=>$asset['public_id'],'url'=>mg_pwa_branding_asset_url($asset,$settings),'filename'=>$asset['original_filename'],'mime_type'=>$asset['mime_type'],'byte_size'=>(int)$asset['byte_size'],'width_px'=>$asset['width_px']!==null?(int)$asset['width_px']:null,'height_px'=>$asset['height_px']!==null?(int)$asset['height_px']:null,'updated_at'=>$asset['updated_at']] : null,
        ];
    }
    $missing = [];
    foreach (['app_icon_192','app_icon_512','maskable_icon_512','apple_touch_icon','notification_icon','notification_badge','splash_logo'] as $role) if (empty($assets[$role]['asset'])) $missing[] = $role;
    return ['schema_ready'=>mg_pwa_branding_schema_ready($pdo),'settings'=>$settings,'assets'=>$assets,'missing_required_assets'=>$missing,'manifest_url'=>'/manifest.php?v=' . rawurlencode((string)$settings['asset_version']),'splash_url'=>'/pwa-splash.php'];
}

function mg_pwa_branding_save_settings(PDO $pdo, array $input, int $userId): array
{
    if (!mg_pwa_branding_schema_ready($pdo)) mg_fail('PWA branding tables are missing. Run migrations first.', 409);
    $defaults = mg_pwa_branding_defaults();
    $next = mg_pwa_branding_settings($pdo);
    foreach (['app_name','short_name','description','splash_title','splash_subtitle','splash_cta_label'] as $key) if (array_key_exists($key,$input)) {
        $limit = in_array($key, ['description','splash_subtitle'], true) ? 260 : 80;
        $value = trim(mb_substr((string)$input[$key], 0, $limit));
        $next[$key] = $value !== '' ? $value : $defaults[$key];
    }
    foreach (['start_url','scope','splash_cta_url'] as $key) if (array_key_exists($key,$input)) $next[$key] = mg_pwa_branding_path((string)$input[$key], $defaults[$key]);
    foreach (['theme_color','background_color'] as $key) if (array_key_exists($key,$input)) $next[$key] = mg_pwa_branding_hex((string)$input[$key], $defaults[$key]);
    if (array_key_exists('display',$input)) $next['display'] = in_array((string)$input['display'], ['standalone','fullscreen','minimal-ui','browser'], true) ? (string)$input['display'] : 'standalone';
    $next['asset_version'] = (string)time();
    $stmt = $pdo->prepare("INSERT INTO pwa_branding_settings (setting_key,setting_value,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id),updated_at=NOW()");
    foreach ($next as $key=>$value) $stmt->execute(['pwa.' . $key, (string)$value, $userId]);
    mg_audit('admin.pwa_branding.settings_updated','pwa_branding_settings',['keys'=>array_keys($next)],$userId);
    return mg_pwa_branding_payload($pdo);
}

function mg_pwa_branding_upload(PDO $pdo, array $file, string $role, int $userId): array
{
    $roles = mg_pwa_branding_roles();
    if (!isset($roles[$role])) mg_fail('Invalid PWA asset role.', 422);
    if (!mg_pwa_branding_schema_ready($pdo)) mg_fail('PWA branding tables are missing. Run migrations first.', 409);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) mg_fail('No valid image upload was provided.', 422);
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $map = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    if (!isset($map[$mime])) mg_fail('Unsupported PWA image format. Use PNG, JPG, or WebP.', 422);
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > (int)$roles[$role]['max']) mg_fail('The selected PWA image is too large for this slot.', 422);
    $dimensions = @getimagesize((string)$file['tmp_name']);
    if (!is_array($dimensions)) mg_fail('The uploaded file is not a readable image.', 422);
    $width = (int)($dimensions[0] ?? 0); $height = (int)($dimensions[1] ?? 0);
    $assetId = mg_pwa_branding_uuid();
    $relativeKey = 'pwa/' . $role . '/' . $assetId . '.' . $map[$mime];
    $root = dirname(__DIR__) . '/storage/public';
    $dir = $root . '/pwa/' . $role;
    if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) mg_fail('Unable to prepare PWA image storage.',500);
    $dest = $root . '/' . $relativeKey;
    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) mg_fail('Unable to store the PWA image.',500);
    @chmod($dest,0644);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE pwa_branding_assets SET status='archived',updated_at=NOW() WHERE asset_role=? AND status='active'")->execute([$role]);
        $stmt = $pdo->prepare("INSERT INTO pwa_branding_assets (public_id,asset_role,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,status,uploaded_by_user_id,metadata_json,created_at,updated_at) VALUES (?,?,'public_local',?,?,?,?,?,?,?,'active',?,?,NOW(),NOW())");
        $stmt->execute([$assetId,$role,$relativeKey,basename((string)($file['name'] ?? $role . '.' . $map[$mime])),$mime,$size,hash_file('sha256',$dest),$width,$height,$userId,json_encode(['recommended'=>$roles[$role]],JSON_UNESCAPED_SLASHES)]);
        $pdo->prepare("INSERT INTO pwa_branding_settings (setting_key,setting_value,updated_by_user_id,created_at,updated_at) VALUES ('pwa.asset_version',?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id),updated_at=NOW()")->execute([(string)time(),$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @unlink($dest);
        mg_security_log('error','admin.pwa_branding.asset_upload_failed','PWA branding asset upload failed.',['role'=>$role,'exception_class'=>$e::class],$userId);
        mg_fail('Unable to register the uploaded PWA image.',500);
    }
    mg_audit('admin.pwa_branding.asset_uploaded','pwa_branding_asset',['role'=>$role,'asset_id'=>$assetId,'mime_type'=>$mime],$userId);
    return mg_pwa_branding_payload($pdo);
}

function mg_pwa_branding_manifest(PDO $pdo): array
{
    $payload = mg_pwa_branding_payload($pdo); $s = $payload['settings']; $a = $payload['assets']; $icons = [];
    foreach (['app_icon_192','app_icon_512','maskable_icon_512'] as $role) if (!empty($a[$role]['asset'])) $icons[] = ['src'=>$a[$role]['asset']['url'],'sizes'=>$a[$role]['sizes'],'type'=>$a[$role]['asset']['mime_type'] ?: 'image/png','purpose'=>$a[$role]['purpose']==='maskable'?'maskable':'any'];
    if (!$icons) $icons[] = ['src'=>'/images/logo_main_drk.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'];
    return ['name'=>$s['app_name'],'short_name'=>$s['short_name'],'description'=>$s['description'],'start_url'=>$s['start_url'],'scope'=>$s['scope'],'display'=>$s['display'],'background_color'=>$s['background_color'],'theme_color'=>$s['theme_color'],'icons'=>$icons];
}

function mg_pwa_branding_public_asset(PDO $pdo, string $id): ?array
{
    if (!mg_pwa_branding_table_exists($pdo,'pwa_branding_assets')) return null;
    $stmt = $pdo->prepare("SELECT public_id,asset_role,storage_provider,storage_key,original_filename,mime_type,byte_size,updated_at FROM pwa_branding_assets WHERE public_id=? AND status='active' AND storage_provider='public_local' LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_pwa_branding_notification_defaults(PDO $pdo): array
{
    $p = mg_pwa_branding_payload($pdo); $a = $p['assets'];
    return ['icon'=>$a['notification_icon']['asset']['url'] ?? $a['app_icon_192']['asset']['url'] ?? '/images/logo_main_drk.png','badge'=>$a['notification_badge']['asset']['url'] ?? $a['notification_icon']['asset']['url'] ?? '/images/logo_main_drk.png'];
}
