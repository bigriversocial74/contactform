<?php
declare(strict_types=1);

require_once __DIR__ . '/_feed.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.items.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$itemPublicId = trim((string) ($input['pppm_id'] ?? ''));
$versionPublicId = strtolower(trim((string) ($input['post_version_id'] ?? '')));
if ($itemPublicId === '' || $versionPublicId === '') mg_fail('PPPM item and post version are required.', 422);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $itemStmt = $pdo->prepare(
        'SELECT * FROM pppm_items WHERE public_id = ?
         AND (issuer_user_id = ? OR owner_user_id = ? OR merchant_user_id = ?)
         LIMIT 1 FOR UPDATE'
    );
    $itemStmt->execute([$itemPublicId, (int) $user['id'], (int) $user['id'], (int) $user['id']]);
    $item = $itemStmt->fetch();
    if (!$item) mg_fail('PPPM item not found.', 404);

    $versionStmt = $pdo->prepare(
        "SELECT fpv.id, fpv.public_id, fpv.version_status, fp.merchant_user_id, fp.status
         FROM feed_post_versions fpv INNER JOIN feed_posts fp ON fp.id = fpv.feed_post_id
         WHERE fpv.public_id = ? AND fp.merchant_user_id = ?
           AND fpv.version_status = 'published' AND fp.status IN ('published','promoted')
         LIMIT 1"
    );
    $versionStmt->execute([$versionPublicId, (int) $user['id']]);
    $version = $versionStmt->fetch();
    if (!$version) mg_fail('Published feed post version not found.', 404);

    $existing = $pdo->prepare('SELECT feed_post_version_id FROM pppm_feed_bindings WHERE pppm_item_id = ? LIMIT 1 FOR UPDATE');
    $existing->execute([(int) $item['id']]);
    $existingVersion = $existing->fetchColumn();
    if ($existingVersion && (int) $existingVersion !== (int) $version['id']) {
        mg_fail('This issued PPPM item already has immutable envelope contents.', 409);
    }
    if (!$existingVersion) {
        $pdo->prepare('INSERT INTO pppm_feed_bindings (pppm_item_id, feed_post_version_id, bound_at) VALUES (?, ?, NOW())')
            ->execute([(int) $item['id'], (int) $version['id']]);
        mg_pppm_record_event($pdo, $item, 'feed_content_bound', (string) $item['status'], (string) $item['status'], (int) $user['id'], null, [
            'post_version_id' => $versionPublicId,
        ]);
    }
    $pdo->commit();
    mg_ok(['pppm_id' => $itemPublicId, 'post_version_id' => $versionPublicId, 'bound' => true], 'Envelope contents bound.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to bind envelope contents.', 500);
}
