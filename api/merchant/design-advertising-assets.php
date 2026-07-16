<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/storage.php';

const MG_AD_ASSET_TABLE = 'merchant_advertising_assets';
const MG_AD_ASSET_MIGRATION = 'database/20260716_design_studio_advertising_workflow_v2.sql';

function mg_ad_asset_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function mg_ad_asset_schema_ready(PDO $pdo): bool
{
    return mg_ad_asset_table_exists($pdo, MG_AD_ASSET_TABLE)
        && mg_ad_asset_table_exists($pdo, 'design_content_schedule')
        && mg_ad_asset_table_exists($pdo, 'catalog_assets');
}

function mg_ad_asset_text(mixed $value, int $max, string $fallback = ''): string
{
    $text = trim((string) $value);
    if ($text === '') $text = $fallback;
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text) ?? '';
    return mb_substr($text, 0, $max);
}

function mg_ad_asset_uuid(mixed $value): string
{
    $id = strtolower(trim((string) $value));
    if (preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) mg_fail('Saved creative was not found.', 404);
    return $id;
}

function mg_ad_asset_choice(mixed $value, array $allowed, string $fallback): string
{
    $choice = strtolower(trim((string) $value));
    return in_array($choice, $allowed, true) ? $choice : $fallback;
}

function mg_ad_asset_json(mixed $value, int $maxBytes = 30000): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) mg_fail('Creative metadata must be a valid object.', 422);
        $value = $decoded;
    }
    if (!is_array($value)) mg_fail('Creative metadata must be a valid object.', 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) mg_fail('Creative metadata is too large.', 422);
    return $json;
}

function mg_ad_asset_owned_product(PDO $pdo, int $merchantUserId, mixed $publicId): ?array
{
    $id = strtolower(trim((string) $publicId));
    if ($id === '') return null;
    if (preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) mg_fail('Selected product is invalid.', 422);
    $stmt = $pdo->prepare('SELECT id,public_id,slug FROM catalog_products WHERE public_id=? AND merchant_user_id=? AND status<>\'archived\' LIMIT 1');
    $stmt->execute([$id, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Selected product is unavailable.', 404);
    return $row;
}

function mg_ad_asset_owned_schedule(PDO $pdo, int $merchantUserId, mixed $publicId): ?array
{
    $id = strtolower(trim((string) $publicId));
    if ($id === '') return null;
    if (preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) mg_fail('Source schedule item is invalid.', 422);
    $stmt = $pdo->prepare('SELECT id,public_id,catalog_product_id,scheduled_date FROM design_content_schedule WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$id, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Source schedule item is unavailable.', 404);
    return $row;
}

function mg_ad_asset_owned(PDO $pdo, int $merchantUserId, string $publicId, bool $forUpdate = false): array
{
    $stmt = $pdo->prepare(
        'SELECT maa.*,ca.storage_provider,ca.storage_key,ca.public_id catalog_asset_public_id
         FROM merchant_advertising_assets maa
         INNER JOIN catalog_assets ca ON ca.id=maa.catalog_asset_id
         WHERE maa.public_id=? AND maa.merchant_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$publicId, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Saved creative was not found.', 404);
    return $row;
}

function mg_ad_asset_format_row(array $row): array
{
    $assetPublicId = (string) ($row['catalog_asset_public_id'] ?? '');
    $scheduleId = $row['schedule_public_id'] ?? null;
    $productId = $row['product_public_id'] ?? null;
    $url = $assetPublicId !== '' ? '/api/merchant/design-advertising-assets.php?file=' . rawurlencode((string) $row['public_id']) : null;
    $designUrl = '/agent.php?view=design&mode=' . ((string) $row['asset_kind'] === 'print' ? 'print' : 'social')
        . ($productId ? '&product=' . rawurlencode((string) $productId) : '')
        . '&format=' . rawurlencode((string) $row['format_key'])
        . '&layout=' . rawurlencode((string) $row['layout_key'])
        . ($scheduleId ? '&schedule=' . rawurlencode((string) $scheduleId) : '')
        . '&asset=' . rawurlencode((string) $row['public_id']);
    return [
        'id' => (string) $row['public_id'],
        'title' => (string) $row['title'],
        'asset_kind' => (string) $row['asset_kind'],
        'format_key' => (string) $row['format_key'],
        'layout_key' => (string) $row['layout_key'],
        'status' => (string) $row['status'],
        'product_id' => $productId,
        'product_name' => $row['product_title'] ?? null,
        'schedule_id' => $scheduleId,
        'scheduled_date' => $row['scheduled_date'] ?? null,
        'caption' => json_decode((string) ($row['caption_json'] ?? ''), true) ?: [],
        'image_url' => $url,
        'download_url' => $url !== null ? $url . '&download=1' : null,
        'design_url' => $designUrl,
        'date_saved' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_ad_asset_find_by_idempotency(PDO $pdo, int $merchantUserId, string $key): ?array
{
    $stmt = $pdo->prepare(
        'SELECT maa.*,ca.public_id catalog_asset_public_id,p.public_id product_public_id,v.title product_title,s.public_id schedule_public_id,s.scheduled_date
         FROM merchant_advertising_assets maa
         INNER JOIN catalog_assets ca ON ca.id=maa.catalog_asset_id
         LEFT JOIN catalog_products p ON p.id=maa.catalog_product_id
         LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         LEFT JOIN design_content_schedule s ON s.id=maa.schedule_item_id
         WHERE maa.merchant_user_id=? AND maa.idempotency_key=? LIMIT 1'
    );
    $stmt->execute([$merchantUserId, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'catalog.products.view' : 'catalog.products.manage');
$actorUserId = (int) ($user['id'] ?? 0);
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$workspaceId = (int) $workspace['id'];
$merchantUserId = (int) ($workspace['merchant_user_id'] ?? $actorUserId);

if ($method === 'GET') {
    mg_rate_limit('merchant.design_advertising_assets.read', 'user:' . $actorUserId, 180, 60);
    $fileId = strtolower(trim((string) ($_GET['file'] ?? '')));
    if ($fileId !== '') {
        if (preg_match('/^[a-f0-9-]{36}$/', $fileId) !== 1) mg_fail('Saved creative was not found.', 404);
        if (!mg_ad_asset_schema_ready($pdo)) mg_fail('Advertising asset setup is incomplete.', 503);
        $fileStmt = $pdo->prepare(
            'SELECT ca.storage_provider,ca.storage_key,ca.original_filename,ca.mime_type,ca.byte_size,ca.checksum_sha256
             FROM merchant_advertising_assets maa INNER JOIN catalog_assets ca ON ca.id=maa.catalog_asset_id
             WHERE maa.public_id=? AND maa.merchant_user_id=? AND maa.status IN (\'active\',\'archived\') AND ca.status=\'ready\' LIMIT 1'
        );
        $fileStmt->execute([$fileId, $merchantUserId]);
        $fileAsset = $fileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$fileAsset) mg_fail('Saved creative was not found.', 404);
        try { $filePath = mg_storage_resolve_asset_path((string) $fileAsset['storage_provider'], (string) $fileAsset['storage_key']); }
        catch (Throwable $error) { mg_security_log('error','merchant.design_creative_file_resolution_failed','Saved creative storage resolution failed.',['asset_id'=>$fileId,'exception_class'=>$error::class],$actorUserId); mg_fail('Saved creative is unavailable.',404); }
        if (!is_file($filePath) || !is_readable($filePath)) mg_fail('Saved creative is unavailable.',404);
        $fileSize = filesize($filePath); if ($fileSize === false) mg_fail('Saved creative is unavailable.',404);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)($fileAsset['original_filename'] ?? 'creative.jpg'))) ?: 'creative.jpg';
        $mime = trim((string)($fileAsset['mime_type'] ?? '')) ?: 'application/octet-stream';
        $etag = '"' . ((string)($fileAsset['checksum_sha256'] ?? '') ?: hash_file('sha256',$filePath)) . '"';
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { header('ETag: '.$etag); http_response_code(304); exit; }
        header('Content-Type: '.$mime); header('Content-Length: '.(int)$fileSize); header('ETag: '.$etag); header('X-Content-Type-Options: nosniff'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: private, max-age=120');
        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
        header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($filename));
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') exit;
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); while (ob_get_level() > 0) ob_end_clean(); readfile($filePath); exit;
    }
    if (!mg_ad_asset_schema_ready($pdo)) {
        mg_ok(['assets'=>[], 'setup_required'=>true, 'migration'=>MG_AD_ASSET_MIGRATION]);
    }
    $where = ['maa.merchant_user_id=?'];
    $params = [$merchantUserId];
    $status = mg_ad_asset_choice($_GET['status'] ?? 'active', ['active','archived','all'], 'active');
    if ($status !== 'all') {$where[]='maa.status=?';$params[]=$status;}
    $format = mg_ad_asset_text($_GET['format'] ?? '', 40);
    if ($format !== '') {$where[]='maa.format_key=?';$params[]=$format;}
    $productId = strtolower(trim((string) ($_GET['product_id'] ?? '')));
    if ($productId !== '') {$where[]='p.public_id=?';$params[]=$productId;}
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    if ($dateFrom !== '') {$where[]='DATE(maa.created_at)>=?';$params[]=$dateFrom;}
    if ($dateTo !== '') {$where[]='DATE(maa.created_at)<=?';$params[]=$dateTo;}
    $stmt = $pdo->prepare(
        'SELECT maa.*,ca.public_id catalog_asset_public_id,p.public_id product_public_id,v.title product_title,s.public_id schedule_public_id,s.scheduled_date
         FROM merchant_advertising_assets maa
         INNER JOIN catalog_assets ca ON ca.id=maa.catalog_asset_id
         LEFT JOIN catalog_products p ON p.id=maa.catalog_product_id
         LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         LEFT JOIN design_content_schedule s ON s.id=maa.schedule_item_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY maa.created_at DESC LIMIT 200'
    );
    $stmt->execute($params);
    $rows = array_map('mg_ad_asset_format_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    mg_ok(['assets'=>$rows, 'setup_required'=>false]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
mg_rate_limit('merchant.design_advertising_assets.write', 'user:' . $actorUserId, 45, 60);
$input = mg_input();
mg_require_csrf_for_write($input);
if (!mg_ad_asset_schema_ready($pdo)) mg_fail('Advertising asset setup is incomplete. Import ' . MG_AD_ASSET_MIGRATION . '.', 503);
$action = strtolower(trim((string) ($input['action'] ?? 'save')));

if ($action === 'save') {
    $key = mg_ad_asset_text($input['idempotency_key'] ?? '', 128);
    if ($key === '' || preg_match('/^[A-Za-z0-9:_-]{12,128}$/', $key) !== 1) mg_fail('A valid save request key is required.', 422);
    $existing = mg_ad_asset_find_by_idempotency($pdo, $merchantUserId, $key);
    if ($existing) mg_ok(['asset'=>mg_ad_asset_format_row($existing), 'duplicate'=>true], 'Creative asset was already saved.');

    $kind = mg_ad_asset_choice($input['asset_kind'] ?? 'social', ['print','social'], 'social');
    $format = mg_ad_asset_choice($input['format_key'] ?? 'square', ['poster','tent','square','portrait','story'], $kind === 'print' ? 'poster' : 'square');
    $layout = mg_ad_asset_choice($input['layout_key'] ?? 'spotlight', ['support-local','spotlight','split','bold'], $kind === 'print' ? 'support-local' : 'spotlight');
    $title = mg_ad_asset_text($input['title'] ?? '', 180, 'Saved Design Studio creative');
    $captionJson = mg_ad_asset_json($input['caption'] ?? null);
    $renderJson = mg_ad_asset_json($input['render_metadata'] ?? null);
    $product = mg_ad_asset_owned_product($pdo, $merchantUserId, $input['product_id'] ?? '');
    $schedule = mg_ad_asset_owned_schedule($pdo, $merchantUserId, $input['schedule_id'] ?? '');
    if ($schedule && $product && (int) $schedule['catalog_product_id'] !== (int) $product['id']) mg_fail('The scheduled post does not belong to the selected product.', 422);
    if ($schedule && !$product) {
        $stmt = $pdo->prepare('SELECT id,public_id,slug FROM catalog_products WHERE id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([(int)$schedule['catalog_product_id'],$merchantUserId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $file = $_FILES['creative'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) mg_fail('Choose a rendered creative to save.', 422);
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 16777216) mg_fail('The rendered creative is not allowed.', 422);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->file($tmp));
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime])) mg_fail('Save a JPG, PNG, or WebP creative.', 422);
    $dimensions = @getimagesize($tmp);
    if (!is_array($dimensions) || (int)$dimensions[0] < 1 || (int)$dimensions[1] < 1 || (int)$dimensions[0] > 12000 || (int)$dimensions[1] > 12000 || ((int)$dimensions[0] * (int)$dimensions[1]) > 40000000) {
        mg_fail('The creative image dimensions are not allowed.', 422);
    }

    $assetPublicId = mg_public_uuid();
    $storageKey = mg_storage_normalize_key('creative/' . gmdate('Y/m') . '/merchant-' . $merchantUserId . '/' . str_replace('-', '', $assetPublicId) . '.' . $extensions[$mime]);
    try {
        $absolutePath = mg_storage_store_uploaded_file($tmp, $storageKey);
    } catch (Throwable $error) {
        mg_security_log('error','merchant.design_creative_storage_failed','Saved creative storage failed.',['exception_class'=>$error::class],$actorUserId);
        mg_fail('Persistent media storage is unavailable. The creative was not saved.', 503);
    }
    $checksum = hash_file('sha256', $absolutePath) ?: null;
    $original = mg_ad_asset_text($file['name'] ?? '', 255, 'design-studio-creative.' . $extensions[$mime]);
    $catalogMetadata = json_encode(['source'=>'design_studio_advertising','storage_class'=>'persistent','asset_kind'=>$kind,'format_key'=>$format,'layout_key'=>$layout,'uploaded_at'=>gmdate('c')], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $creativePublicId = mg_merchant_uuid();

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO catalog_assets
             (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,duration_ms,status,metadata_json,created_at,updated_at)
             VALUES (?,?, 'image','persistent_local',?,?,?,?,?,?,?,NULL,'ready',?,NOW(),NOW())"
        )->execute([$assetPublicId,$merchantUserId,$storageKey,$original,$mime,$size,$checksum,(int)$dimensions[0],(int)$dimensions[1],$catalogMetadata]);
        $catalogAssetId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO merchant_advertising_assets
             (public_id,workspace_id,merchant_user_id,catalog_product_id,schedule_item_id,catalog_asset_id,idempotency_key,title,asset_kind,format_key,layout_key,caption_json,render_metadata_json,status,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,\'active\',?,NOW(),NOW())'
        )->execute([$creativePublicId,$workspaceId,$merchantUserId,$product['id']??null,$schedule['id']??null,$catalogAssetId,$key,$title,$kind,$format,$layout,$captionJson,$renderJson,$actorUserId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_storage_delete_asset_file('persistent_local', $storageKey);
        $duplicate = mg_ad_asset_find_by_idempotency($pdo, $merchantUserId, $key);
        if ($duplicate) mg_ok(['asset'=>mg_ad_asset_format_row($duplicate),'duplicate'=>true], 'Creative asset was already saved.');
        mg_security_log('error','merchant.design_creative_save_failed','Saved creative registration failed.',['exception_class'=>$error::class],$actorUserId);
        mg_fail('Unable to save the creative asset.', 500);
    }
    $saved = mg_ad_asset_find_by_idempotency($pdo, $merchantUserId, $key);
    mg_audit('merchant.design_creative_saved','merchant_advertising_asset',['asset_id'=>$creativePublicId,'catalog_asset_id'=>$assetPublicId,'product_id'=>$product['public_id']??null,'schedule_id'=>$schedule['public_id']??null,'format_key'=>$format,'layout_key'=>$layout],$actorUserId);
    mg_ok(['asset'=>mg_ad_asset_format_row($saved ?: []),'duplicate'=>false], 'Creative asset saved.', 201);
}

if (!in_array($action, ['rename','archive','restore','remove'], true)) mg_fail('Invalid saved creative action.', 422);
$publicId = mg_ad_asset_uuid($input['asset_id'] ?? '');
$pdo->beginTransaction();
$asset = mg_ad_asset_owned($pdo, $merchantUserId, $publicId, true);
if ($action === 'rename') {
    $title = mg_ad_asset_text($input['title'] ?? '', 180);
    if ($title === '') mg_fail('Enter a name for the saved creative.', 422);
    $pdo->prepare('UPDATE merchant_advertising_assets SET title=?,updated_at=NOW() WHERE id=?')->execute([$title,(int)$asset['id']]);
} elseif ($action === 'archive' || $action === 'restore') {
    $status = $action === 'archive' ? 'archived' : 'active';
    $pdo->prepare('UPDATE merchant_advertising_assets SET status=?,archived_at=IF(?=\'archived\',NOW(),NULL),updated_at=NOW() WHERE id=?')->execute([$status,$status,(int)$asset['id']]);
} else {
    $pdo->prepare('DELETE FROM merchant_advertising_assets WHERE id=?')->execute([(int)$asset['id']]);
    $pdo->prepare('DELETE FROM catalog_assets WHERE id=? AND owner_user_id=?')->execute([(int)$asset['catalog_asset_id'],$merchantUserId]);
}
$pdo->commit();
if ($action === 'remove') {
    try { mg_storage_delete_asset_file((string)$asset['storage_provider'], (string)$asset['storage_key']); }
    catch (Throwable $error) { mg_security_log('warning','merchant.design_creative_file_cleanup_failed','Saved creative database record was removed but file cleanup failed.',['asset_id'=>$publicId,'exception_class'=>$error::class],$actorUserId); }
}
mg_audit('merchant.design_creative_' . $action,'merchant_advertising_asset',['asset_id'=>$publicId],$actorUserId);
mg_ok(['asset_id'=>$publicId,'action'=>$action], $action === 'remove' ? 'Creative removed.' : 'Creative updated.');
