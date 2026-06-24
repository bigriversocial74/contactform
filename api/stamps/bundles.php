<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();

if ($method === 'GET') {
    mg_ok(['bundles' => mg_stamp_bundle_rows($pdo)]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $pdo->beginTransaction();
    $bundle = mg_stamp_bundle_save($pdo, $input);
    $pdo->commit();
    mg_audit('stamps.bundle_saved', 'stamp_bundle', ['bundle_id' => $bundle['id'], 'bundle_key' => $bundle['bundle_key']], (int)$user['id']);
    mg_ok(['bundle' => $bundle, 'bundles' => mg_stamp_bundle_rows($pdo)], 'Stamp bundle saved.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'stamps.bundle_save_failed', 'Unable to save Stamp bundle.', ['exception_class' => $error::class], (int)$user['id']);
    mg_fail('Unable to save Stamp bundle.', 500);
}
