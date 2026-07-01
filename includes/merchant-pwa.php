<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function mg_merchant_pwa_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function mg_merchant_pwa_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_merchant_pwa_schema_ready(PDO $pdo): bool
{
    return mg_merchant_pwa_table_exists($pdo, 'merchant_pwa_profiles') && mg_merchant_pwa_table_exists($pdo, 'merchant_pwa_assets');
}

function mg_merchant_pwa_slugify(string $value, string $fallback = 'merchant'): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    $slug = trim($slug, '-');
    if ($slug === '') $slug = $fallback;
    return mb_substr($slug, 0, 90);
}

function mg_merchant_pwa_hex(string $value, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', trim($value)) === 1 ? strtolower(trim($value)) : $fallback;
}

function mg_merchant_pwa_bool(mixed $value): int
{
    if (is_bool($value)) return $value ? 1 : 0;
    if (is_numeric($value)) return (int)$value === 1 ? 1 : 0;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true) ? 1 : 0;
}

function mg_merchant_pwa_roles(): array
{
    return [
        'app_icon_192'=>['label'=>'App icon 192','description'=>'Home screen icon used for smaller Android install contexts.','sizes'=>'192x192','purpose'=>'any','max'=>5242880,'w'=>192,'h'=>192],
        'app_icon_512'=>['label'=>'App icon 512','description'=>'Large install icon used by the merchant-branded app manifest.','sizes'=>'512x512','purpose'=>'any','max'=>5242880,'w'=>512,'h'=>512],
        'maskable_icon_512'=>['label'=>'Maskable icon','description'=>'512x512 icon with safe-zone padding for launcher masks.','sizes'=>'512x512','purpose'=>'maskable','max'=>5242880,'w'=>512,'h'=>512],
        'apple_touch_icon'=>['label'=>'Apple touch icon','description'=>'180x180 iOS home screen icon.','sizes'=>'180x180','purpose'=>'apple-touch-icon','max'=>5242880,'w'=>180,'h'=>180],
        'notification_icon'=>['label'=>'Notification icon','description'=>'Merchant-branded browser notification icon.','sizes'=>'192x192','purpose'=>'notification-icon','max'=>5242880,'w'=>192,'h'=>192],
        'notification_badge'=>['label'=>'Notification badge','description'=>'Small monochrome notification tray badge.','sizes'=>'96x96','purpose'=>'notification-badge','max'=>3145728,'w'=>96,'h'=>96],
        'splash_logo'=>['label'=>'Splash logo','description'=>'Logo shown on the merchant install and app start screens.','sizes'=>'512x512','purpose'=>'splash-logo','max'=>5242880,'w'=>512,'h'=>512],
        'splash_background'=>['label'=>'Splash background','description'=>'Optional branded background for the install screen.','sizes'=>'1600x1000','purpose'=>'splash-background','max'=>10485760,'w'=>1600,'h'=>1000],
    ];
}

function mg_merchant_pwa_default_profile(array $workspace): array
{
    $name = trim((string)($workspace['display_name'] ?? 'Merchant')) ?: 'Merchant';
    return [
        'merchant_slug' => mg_merchant_pwa_slugify($name, 'merchant'),
        'app_name' => mb_substr($name . ' Rewards', 0, 100),
        'short_name' => mb_substr($name, 0, 60),
        'description' => mb_substr($name . ' rewards, gift claims, reminders, and local offers powered by Microgifter.', 0, 280),
        'install_headline' => 'Install ' . mb_substr($name, 0, 90) . ' Rewards',
        'install_subtitle' => 'Get rewards, gift alerts, claim reminders, and local offers from ' . mb_substr($name, 0, 140) . '.',
        'splash_title' => $name . ' Rewards',
        'splash_subtitle' => 'Your merchant-branded Microgifter rewards app.',
        'theme_color' => '#2563eb',
        'background_color' => '#f8fafc',
        'status' => 'draft',
        'enable_install_prompt' => 1,
        'enable_push_prompt' => 1,
    ];
}

function mg_merchant_pwa_unique_slug(PDO $pdo, string $base, int $ignoreProfileId = 0): string
{
    $base = mg_merchant_pwa_slugify($base, 'merchant');
    $slug = $base;
    $i = 2;
    $stmt = $pdo->prepare('SELECT id FROM merchant_pwa_profiles WHERE merchant_slug=? LIMIT 1');
    while (true) {
        $stmt->execute([$slug]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id < 1 || ($ignoreProfileId > 0 && $id === $ignoreProfileId)) return $slug;
        $suffix = '-' . $i++;
        $slug = mb_substr($base, 0, 100 - mb_strlen($suffix)) . $suffix;
    }
}

function mg_merchant_pwa_ensure_profile(PDO $pdo, array $workspace, int $userId = 0): array
{
    if (!mg_merchant_pwa_schema_ready($pdo)) mg_fail('Merchant PWA tables are missing. Run migrations first.', 409);
    $stmt = $pdo->prepare('SELECT * FROM merchant_pwa_profiles WHERE workspace_id=? LIMIT 1');
    $stmt->execute([(int)$workspace['id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) return $profile;

    $defaults = mg_merchant_pwa_default_profile($workspace);
    $slug = mg_merchant_pwa_unique_slug($pdo, $defaults['merchant_slug']);
    $publicId = mg_merchant_pwa_uuid();
    $insert = $pdo->prepare("INSERT INTO merchant_pwa_profiles (public_id,workspace_id,merchant_slug,app_name,short_name,description,install_headline,install_subtitle,splash_title,splash_subtitle,theme_color,background_color,status,enable_install_prompt,enable_push_prompt,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $insert->execute([$publicId,(int)$workspace['id'],$slug,$defaults['app_name'],$defaults['short_name'],$defaults['description'],$defaults['install_headline'],$defaults['install_subtitle'],$defaults['splash_title'],$defaults['splash_subtitle'],$defaults['theme_color'],$defaults['background_color'],$defaults['status'],$defaults['enable_install_prompt'],$defaults['enable_push_prompt'],$userId > 0 ? $userId : null]);
    $stmt->execute([(int)$workspace['id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_merchant_pwa_profile_by_slug(PDO $pdo, string $slug): array
{
    $slug = mg_merchant_pwa_slugify($slug, '');
    if ($slug === '' || !mg_merchant_pwa_schema_ready($pdo)) mg_fail('Merchant app not found.', 404);
    $stmt = $pdo->prepare('SELECT p.*,w.public_id workspace_public_id,w.display_name workspace_display_name,w.website_url,w.support_email,w.support_phone,w.business_type FROM merchant_pwa_profiles p INNER JOIN merchant_workspaces w ON w.id=p.workspace_id WHERE p.merchant_slug=? AND p.status<>\'archived\' LIMIT 1');
    $stmt->execute([$slug]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) mg_fail('Merchant app not found.', 404);
    return $profile;
}

function mg_merchant_pwa_active_assets(PDO $pdo, int $profileId): array
{
    if (!mg_merchant_pwa_table_exists($pdo, 'merchant_pwa_assets')) return [];
    $stmt = $pdo->prepare("SELECT public_id,asset_role,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,updated_at FROM merchant_pwa_assets WHERE profile_id=? AND status='active' ORDER BY asset_role ASC,id DESC");
    $stmt->execute([$profileId]);
    $assets = [];
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        $role = (string)$row['asset_role'];
        if (!isset($assets[$role])) $assets[$role] = $row;
    }
    return $assets;
}

function mg_merchant_pwa_asset_url(array $profile, array $asset): string
{
    $url = '/api/pwa/merchant-asset.php?merchant=' . rawurlencode((string)$profile['merchant_slug']) . '&role=' . rawurlencode((string)$asset['asset_role']);
    $v = preg_replace('/[^0-9a-zA-Z._-]/', '', (string)($asset['updated_at'] ?? time()));
    return $v !== '' ? $url . '&v=' . rawurlencode($v) : $url;
}

function mg_merchant_pwa_asset_payload(PDO $pdo, array $profile): array
{
    $active = mg_merchant_pwa_active_assets($pdo, (int)$profile['id']);
    $assets = [];
    foreach (mg_merchant_pwa_roles() as $role=>$def) {
        $asset = $active[$role] ?? null;
        $assets[$role] = [
            'role'=>$role,
            'label'=>$def['label'],
            'description'=>$def['description'],
            'sizes'=>$def['sizes'],
            'purpose'=>$def['purpose'],
            'recommended_width'=>$def['w'],
            'recommended_height'=>$def['h'],
            'max_bytes'=>$def['max'],
            'asset'=>$asset ? [
                'id'=>$asset['public_id'],
                'url'=>mg_merchant_pwa_asset_url($profile,$asset),
                'filename'=>$asset['original_filename'],
                'mime_type'=>$asset['mime_type'],
                'byte_size'=>(int)$asset['byte_size'],
                'width_px'=>$asset['width_px']!==null?(int)$asset['width_px']:null,
                'height_px'=>$asset['height_px']!==null?(int)$asset['height_px']:null,
                'updated_at'=>$asset['updated_at'],
            ] : null,
        ];
    }
    return $assets;
}

function mg_merchant_pwa_public_payload(PDO $pdo, array $profile): array
{
    $assets = mg_merchant_pwa_asset_payload($pdo, $profile);
    $slug = (string)$profile['merchant_slug'];
    $fallbackIcon = '/images/logo_main_drk.png';
    $logo = $assets['splash_logo']['asset']['url'] ?? $assets['app_icon_512']['asset']['url'] ?? $fallbackIcon;
    $background = $assets['splash_background']['asset']['url'] ?? null;
    return [
        'schema_ready'=>mg_merchant_pwa_schema_ready($pdo),
        'profile'=>[
            'id'=>(string)$profile['public_id'],
            'workspace_id'=>(string)($profile['workspace_public_id'] ?? ''),
            'merchant_slug'=>$slug,
            'app_name'=>(string)$profile['app_name'],
            'short_name'=>(string)$profile['short_name'],
            'description'=>(string)($profile['description'] ?? ''),
            'install_headline'=>(string)($profile['install_headline'] ?? $profile['app_name']),
            'install_subtitle'=>(string)($profile['install_subtitle'] ?? $profile['description']),
            'splash_title'=>(string)($profile['splash_title'] ?? $profile['app_name']),
            'splash_subtitle'=>(string)($profile['splash_subtitle'] ?? $profile['description']),
            'theme_color'=>mg_merchant_pwa_hex((string)$profile['theme_color'], '#2563eb'),
            'background_color'=>mg_merchant_pwa_hex((string)$profile['background_color'], '#f8fafc'),
            'status'=>(string)$profile['status'],
            'enable_install_prompt'=>(int)$profile['enable_install_prompt'] === 1,
            'enable_push_prompt'=>(int)$profile['enable_push_prompt'] === 1,
            'install_url'=>'/m/' . rawurlencode($slug) . '/',
            'start_url'=>'/m/' . rawurlencode($slug) . '/app',
            'manifest_url'=>'/manifest-merchant.php?merchant=' . rawurlencode($slug),
            'logo_url'=>$logo,
            'background_url'=>$background,
        ],
        'merchant'=>[
            'display_name'=>(string)($profile['workspace_display_name'] ?? $profile['short_name']),
            'website_url'=>(string)($profile['website_url'] ?? ''),
            'support_email'=>(string)($profile['support_email'] ?? ''),
            'support_phone'=>(string)($profile['support_phone'] ?? ''),
            'business_type'=>(string)($profile['business_type'] ?? ''),
        ],
        'assets'=>$assets,
    ];
}

function mg_merchant_pwa_payload_for_workspace(PDO $pdo, array $workspace, int $userId = 0): array
{
    $profile = mg_merchant_pwa_ensure_profile($pdo, $workspace, $userId);
    return mg_merchant_pwa_public_payload($pdo, $profile) + ['can_manage'=>true];
}

function mg_merchant_pwa_save_profile(PDO $pdo, array $workspace, array $input, int $userId): array
{
    $profile = mg_merchant_pwa_ensure_profile($pdo, $workspace, $userId);
    $currentId = (int)$profile['id'];
    $defaults = mg_merchant_pwa_default_profile($workspace);
    $slugInput = trim((string)($input['merchant_slug'] ?? $profile['merchant_slug']));
    $slug = mg_merchant_pwa_unique_slug($pdo, $slugInput !== '' ? $slugInput : (string)$profile['merchant_slug'], $currentId);
    $status = in_array((string)($input['status'] ?? $profile['status']), ['draft','active','paused'], true) ? (string)($input['status'] ?? $profile['status']) : 'draft';
    $text = static function(string $key, int $limit, string $fallback) use ($input, $profile): string {
        $value = array_key_exists($key, $input) ? trim((string)$input[$key]) : trim((string)($profile[$key] ?? ''));
        $value = mb_substr($value, 0, $limit);
        return $value !== '' ? $value : $fallback;
    };
    $next = [
        'merchant_slug'=>$slug,
        'app_name'=>$text('app_name',100,$defaults['app_name']),
        'short_name'=>$text('short_name',60,$defaults['short_name']),
        'description'=>$text('description',280,$defaults['description']),
        'install_headline'=>$text('install_headline',140,$defaults['install_headline']),
        'install_subtitle'=>$text('install_subtitle',320,$defaults['install_subtitle']),
        'splash_title'=>$text('splash_title',120,$defaults['splash_title']),
        'splash_subtitle'=>$text('splash_subtitle',320,$defaults['splash_subtitle']),
        'theme_color'=>mg_merchant_pwa_hex((string)($input['theme_color'] ?? $profile['theme_color']), $defaults['theme_color']),
        'background_color'=>mg_merchant_pwa_hex((string)($input['background_color'] ?? $profile['background_color']), $defaults['background_color']),
        'status'=>$status,
        'enable_install_prompt'=>mg_merchant_pwa_bool($input['enable_install_prompt'] ?? $profile['enable_install_prompt']),
        'enable_push_prompt'=>mg_merchant_pwa_bool($input['enable_push_prompt'] ?? $profile['enable_push_prompt']),
    ];
    $stmt = $pdo->prepare('UPDATE merchant_pwa_profiles SET merchant_slug=?,app_name=?,short_name=?,description=?,install_headline=?,install_subtitle=?,splash_title=?,splash_subtitle=?,theme_color=?,background_color=?,status=?,enable_install_prompt=?,enable_push_prompt=?,activated_at=CASE WHEN ?=\'active\' AND activated_at IS NULL THEN NOW() ELSE activated_at END,updated_by_user_id=?,updated_at=NOW() WHERE id=? AND workspace_id=?');
    $stmt->execute([$next['merchant_slug'],$next['app_name'],$next['short_name'],$next['description'],$next['install_headline'],$next['install_subtitle'],$next['splash_title'],$next['splash_subtitle'],$next['theme_color'],$next['background_color'],$next['status'],$next['enable_install_prompt'],$next['enable_push_prompt'],$next['status'],$userId,$currentId,(int)$workspace['id']]);
    if (function_exists('mg_audit')) mg_audit('merchant.pwa_profile_updated','merchant_pwa_profile',['workspace_id'=>$workspace['public_id'] ?? null,'merchant_slug'=>$next['merchant_slug']],$userId);
    $stmt = $pdo->prepare('SELECT * FROM merchant_pwa_profiles WHERE id=? LIMIT 1');
    $stmt->execute([$currentId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: $profile;
    return mg_merchant_pwa_public_payload($pdo, $profile) + ['can_manage'=>true];
}

function mg_merchant_pwa_upload(PDO $pdo, array $workspace, array $file, string $role, int $userId): array
{
    $roles = mg_merchant_pwa_roles();
    if (!isset($roles[$role])) mg_fail('Invalid merchant PWA asset role.', 422);
    $profile = mg_merchant_pwa_ensure_profile($pdo, $workspace, $userId);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) mg_fail('No valid image upload was provided.', 422);
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $map = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    if (!isset($map[$mime])) mg_fail('Unsupported image format. Use PNG, JPG, or WebP.', 422);
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > (int)$roles[$role]['max']) mg_fail('The selected image is too large for this slot.', 422);
    $dimensions = @getimagesize((string)$file['tmp_name']);
    if (!is_array($dimensions)) mg_fail('The uploaded file is not a readable image.', 422);
    $assetId = mg_merchant_pwa_uuid();
    $safeWorkspace = preg_replace('/[^a-f0-9-]/i', '', (string)($workspace['public_id'] ?? 'workspace')) ?: (string)$profile['public_id'];
    $relativeKey = 'merchant-pwa/' . $safeWorkspace . '/' . $role . '/' . $assetId . '.' . $map[$mime];
    $root = dirname(__DIR__) . '/storage/public';
    $dir = $root . '/merchant-pwa/' . $safeWorkspace . '/' . $role;
    if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) mg_fail('Unable to prepare merchant PWA image storage.',500);
    $dest = $root . '/' . $relativeKey;
    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) mg_fail('Unable to store the merchant PWA image.',500);
    @chmod($dest,0644);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE merchant_pwa_assets SET status='archived',updated_at=NOW() WHERE profile_id=? AND asset_role=? AND status='active'")->execute([(int)$profile['id'],$role]);
        $stmt = $pdo->prepare("INSERT INTO merchant_pwa_assets (public_id,workspace_id,profile_id,asset_role,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,status,uploaded_by_user_id,metadata_json,created_at,updated_at) VALUES (?,?,?,?,'public_local',?,?,?,?,?,?,?,'active',?,?,NOW(),NOW())");
        $stmt->execute([$assetId,(int)$workspace['id'],(int)$profile['id'],$role,$relativeKey,basename((string)($file['name'] ?? $role . '.' . $map[$mime])),$mime,$size,hash_file('sha256',$dest),(int)($dimensions[0] ?? 0),(int)($dimensions[1] ?? 0),$userId,json_encode(['recommended'=>$roles[$role]],JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @unlink($dest);
        if (function_exists('mg_security_log')) mg_security_log('error','merchant.pwa_asset_upload_failed','Merchant PWA asset upload failed.',['role'=>$role,'exception_class'=>$e::class],$userId);
        mg_fail('Unable to register the uploaded merchant PWA image.',500);
    }
    if (function_exists('mg_audit')) mg_audit('merchant.pwa_asset_uploaded','merchant_pwa_asset',['role'=>$role,'asset_id'=>$assetId],$userId);
    $stmt = $pdo->prepare('SELECT * FROM merchant_pwa_profiles WHERE id=? LIMIT 1');
    $stmt->execute([(int)$profile['id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: $profile;
    return mg_merchant_pwa_public_payload($pdo, $profile) + ['can_manage'=>true];
}

function mg_merchant_pwa_manifest(PDO $pdo, string $slug): array
{
    $profile = mg_merchant_pwa_profile_by_slug($pdo, $slug);
    $payload = mg_merchant_pwa_public_payload($pdo, $profile);
    $p = $payload['profile'];
    $assets = $payload['assets'];
    $icon192 = $assets['app_icon_192']['asset']['url'] ?? '/images/logo_main_drk.png';
    $icon512 = $assets['app_icon_512']['asset']['url'] ?? $icon192;
    $maskable = $assets['maskable_icon_512']['asset']['url'] ?? $icon512;
    $apple = $assets['apple_touch_icon']['asset']['url'] ?? $icon192;
    return [
        'id' => '/m/' . $p['merchant_slug'] . '/',
        'name' => $p['app_name'],
        'short_name' => $p['short_name'],
        'description' => $p['description'],
        'start_url' => '/m/' . $p['merchant_slug'] . '/app?source=pwa',
        'scope' => '/m/' . $p['merchant_slug'] . '/',
        'display' => 'standalone',
        'theme_color' => $p['theme_color'],
        'background_color' => $p['background_color'],
        'icons' => [
            ['src'=>$icon192,'sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
            ['src'=>$icon512,'sizes'=>'512x512','type'=>'image/png','purpose'=>'any'],
            ['src'=>$maskable,'sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable'],
            ['src'=>$apple,'sizes'=>'180x180','type'=>'image/png','purpose'=>'any'],
        ],
        'shortcuts' => [
            ['name'=>'My Rewards','short_name'=>'Rewards','description'=>'Open this merchant rewards inbox.','url'=>'/m/' . $p['merchant_slug'] . '/app#rewards','icons'=>[['src'=>$icon192,'sizes'=>'192x192']]],
            ['name'=>'Claim Gift','short_name'=>'Claim','description'=>'Open gift claim tools.','url'=>'/m/' . $p['merchant_slug'] . '/app#claim','icons'=>[['src'=>$icon192,'sizes'=>'192x192']]],
            ['name'=>'Send Gift','short_name'=>'Send','description'=>'Send this merchant gift to someone.','url'=>'/m/' . $p['merchant_slug'] . '/app#send','icons'=>[['src'=>$icon192,'sizes'=>'192x192']]],
        ],
        'prefer_related_applications' => false,
    ];
}

function mg_merchant_pwa_public_asset(PDO $pdo, string $slug, string $role): array
{
    $profile = mg_merchant_pwa_profile_by_slug($pdo, $slug);
    if (!isset(mg_merchant_pwa_roles()[$role])) mg_fail('Merchant PWA asset not found.',404);
    $stmt = $pdo->prepare("SELECT * FROM merchant_pwa_assets WHERE profile_id=? AND asset_role=? AND status='active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([(int)$profile['id'],$role]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$asset) mg_fail('Merchant PWA asset not found.',404);
    $root = realpath(dirname(__DIR__) . '/storage/public');
    if ($root === false) mg_fail('Merchant PWA asset storage is unavailable.',404);
    $path = realpath($root . '/' . (string)$asset['storage_key']);
    if ($path === false || !str_starts_with($path, $root) || !is_file($path)) mg_fail('Merchant PWA asset file is unavailable.',404);
    return ['path'=>$path,'mime_type'=>(string)$asset['mime_type'],'byte_size'=>(int)$asset['byte_size'],'updated_at'=>$asset['updated_at'] ?? gmdate('c')];
}
