<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

mg_require_method('GET');
$rawToken = trim((string) ($_GET['token'] ?? ''));
$pdo = mg_db();

try {
    $pdo->beginTransaction();
    $token = mg_media_resolve_token($pdo, $rawToken);
    $entitlement = null;
    if (!empty($token['entitlement_id'])) {
        $stmt = $pdo->prepare(
            'SELECT de.*, dfr.max_downloads, dfr.filename_override, dfr.disposition
             FROM digital_entitlements de
             INNER JOIN digital_fulfillment_rules dfr ON dfr.id = de.fulfillment_rule_id
             WHERE de.id = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([(int) $token['entitlement_id']]);
        $entitlement = $stmt->fetch();
        if (!$entitlement || (string) $entitlement['status'] !== 'active') mg_fail('Digital entitlement is not active.', 409);
        if (!empty($entitlement['expires_at']) && strtotime((string) $entitlement['expires_at']) < time()) {
            $pdo->prepare("UPDATE digital_entitlements SET status = 'expired', updated_at = NOW() WHERE id = ?")->execute([(int) $entitlement['id']]);
            mg_fail('Digital entitlement has expired.', 410);
        }
        if ($token['purpose'] === 'download' && $entitlement['max_downloads'] !== null && (int) $entitlement['downloads_used'] >= (int) $entitlement['max_downloads']) {
            $pdo->prepare("UPDATE digital_entitlements SET status = 'exhausted', updated_at = NOW() WHERE id = ?")->execute([(int) $entitlement['id']]);
            mg_fail('Download limit reached.', 409);
        }
    }

    $file = mg_media_file_from_token($token);
    $filename = $entitlement && !empty($entitlement['filename_override']) ? (string) $entitlement['filename_override'] : $file['filename'];
    $eventType = $token['purpose'] === 'download' ? 'download_started' : 'stream_started';
    if ($entitlement) {
        $pdo->prepare(
            'INSERT INTO digital_access_events
             (public_id, entitlement_id, user_id, event_type, ip_hash, user_agent_hash, occurred_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            mg_feed_uuid(),
            (int) $entitlement['id'],
            $token['user_id'] ?: null,
            $eventType,
            mg_media_hash_context((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            mg_media_hash_context((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        ]);
        $pdo->prepare(
            'UPDATE digital_entitlements SET first_accessed_at = COALESCE(first_accessed_at, NOW()),
             last_accessed_at = NOW(), updated_at = NOW() WHERE id = ?'
        )->execute([(int) $entitlement['id']]);
    }
    $pdo->commit();

    $bytes = mg_media_stream_file($file['path'], $file['mime'], $filename, (string) $token['disposition']);

    if ($entitlement && $token['purpose'] === 'download') {
        $pdo = mg_db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT downloads_used FROM digital_entitlements WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int) $entitlement['id']]);
        $downloadsUsed = (int) $stmt->fetchColumn() + 1;
        $newStatus = $entitlement['max_downloads'] !== null && $downloadsUsed >= (int) $entitlement['max_downloads'] ? 'exhausted' : 'active';
        $pdo->prepare('UPDATE digital_entitlements SET downloads_used = ?, status = ?, last_accessed_at = NOW(), updated_at = NOW() WHERE id = ?')
            ->execute([$downloadsUsed, $newStatus, (int) $entitlement['id']]);
        $pdo->prepare(
            'INSERT INTO digital_access_events
             (public_id, entitlement_id, user_id, event_type, bytes_served, occurred_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([mg_feed_uuid(), (int) $entitlement['id'], $token['user_id'] ?: null, 'download_completed', $bytes]);
        $pdo->commit();
    }
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to deliver media.', 500);
}
