<?php
declare(strict_types=1);

require_once __DIR__ . '/_intelligence.php';

mg_require_method('GET');
$user = mg_require_permission('intelligence.exports.create');
$exportId = strtolower(trim((string) ($_GET['id'] ?? '')));
if ($exportId === '') mg_fail('Export not found.', 404);

$stmt = mg_db()->prepare(
    "SELECT * FROM intelligence_export_jobs
     WHERE public_id = ? AND merchant_user_id = ? AND status = 'ready' LIMIT 1"
);
$stmt->execute([$exportId, (int) $user['id']]);
$export = $stmt->fetch();
if (!$export) mg_fail('Export not found.', 404);
if (!empty($export['expires_at']) && strtotime((string) $export['expires_at']) < time()) {
    mg_db()->prepare("UPDATE intelligence_export_jobs SET status='expired',updated_at=NOW() WHERE id=?")
        ->execute([(int) $export['id']]);
    mg_fail('Export has expired.', 410);
}
if ((string) $export['storage_provider'] !== 'private_local' || empty($export['storage_key'])) {
    mg_fail('Export file is unavailable.', 404);
}

$root = realpath(dirname(__DIR__, 2) . '/storage/private');
$path = realpath(dirname(__DIR__, 2) . '/storage/private/' . ltrim((string) $export['storage_key'], '/'));
if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    mg_fail('Export file is unavailable.', 404);
}
if (!empty($export['checksum_sha256']) && !hash_equals((string) $export['checksum_sha256'], hash_file('sha256', $path))) {
    mg_fail('Export integrity check failed.', 409);
}

$extension = (string) $export['format'] === 'json' ? 'json' : 'csv';
$mime = $extension === 'json' ? 'application/json' : 'text/csv';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="microgifter-intelligence-' . rawurlencode((string) $export['date_from']) . '-to-' . rawurlencode((string) $export['date_to']) . '.' . $extension . '"');
readfile($path);
exit;
