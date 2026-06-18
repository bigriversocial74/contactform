<?php
declare(strict_types=1);

require_once __DIR__ . '/_feed.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';

mg_require_method('POST');
$user = mg_require_permission('engagement.record');
$input = mg_input();
mg_require_csrf_for_write($input);

$pppmId = trim((string) ($input['pppm_id'] ?? '')) ?: null;
$versionId = strtolower(trim((string) ($input['post_version_id'] ?? '')));
$elementId = strtolower(trim((string) ($input['element_id'] ?? ''))) ?: null;
$eventType = trim((string) ($input['event_type'] ?? ''));
$position = isset($input['playback_position_ms']) ? max(0, (int) $input['playback_position_ms']) : null;
$allowed = ['impression','open','view','play','pause','progress_25','progress_50','progress_75','complete','replay','mute','unmute','carousel_advance','cta_click','claim_open','share'];
if ($versionId === '' || !in_array($eventType, $allowed, true)) {
    mg_fail('Invalid engagement event.', 422);
}

$pdo = mg_db();
$versionStmt = $pdo->prepare('SELECT id FROM feed_post_versions WHERE public_id = ? LIMIT 1');
$versionStmt->execute([$versionId]);
$versionDbId = $versionStmt->fetchColumn();
if (!$versionDbId) mg_fail('Feed post version not found.', 404);

$elementDbId = null;
if ($elementId) {
    $elementStmt = $pdo->prepare('SELECT id FROM feed_post_elements WHERE public_id = ? AND feed_post_version_id = ? LIMIT 1');
    $elementStmt->execute([$elementId, (int) $versionDbId]);
    $elementDbId = $elementStmt->fetchColumn();
    if (!$elementDbId) mg_fail('Feed element not found.', 404);
}

$pppmDbId = null;
if ($pppmId) {
    $itemStmt = $pdo->prepare(
        'SELECT id FROM pppm_items WHERE public_id = ? AND (recipient_user_id = ? OR owner_user_id = ? OR issuer_user_id = ? OR merchant_user_id = ?) LIMIT 1'
    );
    $itemStmt->execute([$pppmId, (int) $user['id'], (int) $user['id'], (int) $user['id'], (int) $user['id']]);
    $pppmDbId = $itemStmt->fetchColumn();
    if (!$pppmDbId) mg_fail('PPPM item not found.', 404);
}

$metadata = mg_feed_json($input['metadata'] ?? null, 32768);
$eventId = mg_feed_uuid();
$pdo->prepare(
    'INSERT INTO content_engagement_events
     (public_id, pppm_item_id, feed_post_version_id, feed_post_element_id, viewer_user_id,
      event_type, playback_position_ms, metadata_json, occurred_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
)->execute([$eventId, $pppmDbId ?: null, (int) $versionDbId, $elementDbId ?: null, (int) $user['id'], $eventType, $position, $metadata]);

if ($pppmDbId && in_array($eventType, ['open','claim_open'], true)) {
    $itemStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
    $itemStmt->execute([(int) $pppmDbId]);
    $item = $itemStmt->fetch();
    if ($item) {
        mg_pppm_record_event($pdo, $item, $eventType === 'open' ? 'content_opened' : 'claim_opened', (string) $item['status'], (string) $item['status'], (int) $user['id'], null, ['engagement_event_id' => $eventId]);
    }
}

mg_ok(['event_id' => $eventId], 'Engagement recorded.', 201);
