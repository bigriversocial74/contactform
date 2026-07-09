<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_reward_image_safe_url(mixed $value): string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return '';
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : '';
}
function mg_reward_image_upload(array $file, int $merchantId, string $templateId): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) mg_fail('Unable to upload reward image.', 422);
    $name = trim((string)($file['name'] ?? 'reward-image')) ?: 'reward-image';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) mg_fail('Use JPG, PNG, GIF, or WebP reward images.', 422);
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 8 * 1024 * 1024) mg_fail('Reward image must be 8MB or smaller.', 422);
    $dir = dirname(__DIR__, 2) . '/uploads/reward-packs/' . $merchantId . '/' . $templateId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) mg_fail('Unable to prepare reward image storage.', 500);
    $base = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'reward-image';
    $filename = strtolower(trim($base, '-')) . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!is_uploaded_file((string)$file['tmp_name']) || !move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $filename)) mg_fail('Unable to store reward image.', 500);
    return '/uploads/reward-packs/' . $merchantId . '/' . $templateId . '/' . rawurlencode($filename);
}

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.reward_templates.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$templateId = strtolower(trim((string)($input['template_id'] ?? $input['id'] ?? '')));
if (strlen($templateId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $templateId) !== 1) mg_fail('Invalid reward template.', 422);
$stmt = $pdo->prepare('SELECT id, metadata_json FROM reward_templates WHERE public_id=? AND merchant_user_id=? LIMIT 1');
$stmt->execute([$templateId, $merchantId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) mg_fail('Reward template not found.', 404);
$url = mg_reward_image_safe_url($input['reward_image_url'] ?? $input['image_url'] ?? '');
$upload = mg_reward_image_upload($_FILES['reward_image_file'] ?? $_FILES['image'] ?? [], $merchantId, $templateId);
if ($upload !== '') $url = $upload;
if ($url === '') mg_fail('Choose or upload a reward image first.', 422);
$metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
if (!is_array($metadata)) $metadata = [];
$metadata['reward_image_url'] = $url;
$pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
$pack['cover_image_url'] = $url;
$metadata['media_pack'] = $pack;
$pdo->prepare('UPDATE reward_templates SET metadata_json=?, updated_at=NOW() WHERE id=? AND merchant_user_id=?')->execute([json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$row['id'], $merchantId]);
mg_ok(['template_id' => $templateId, 'reward_image_url' => $url, 'cover_image_url' => $url], 'Reward image saved.');
