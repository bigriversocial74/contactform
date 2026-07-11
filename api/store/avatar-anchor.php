<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas.php';
require_once dirname(__DIR__) . '/world-canvas/_locations.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('store.avatar_anchor', 'user:' . (int)$user['id'], 30, 60);
    if (($input['consent'] ?? '') !== 'yes') mg_fail('Consent is required.', 403);

    mg_store_require_schema($pdo);
    $session = mg_store_active_session_for_customer($pdo, (int)$user['id'], true);
    if (!$session) mg_fail('No active Store Canvas session.', 400);

    $merchantUserId = (int)($session['merchant_user_id'] ?? 0);
    $geo = mg_world_location_main_merchant($pdo, $merchantUserId);
    if ($geo === null) mg_fail('This merchant has not mapped an active World Canvas location.', 422);

    $lat = round((float)$geo['latitude'], 7);
    $lng = round((float)$geo['longitude'], 7);
    $accuracy = isset($geo['accuracy_meters']) ? max(0, min(100000, (int)$geo['accuracy_meters'])) : null;
    $metadata = mg_world_canvas_json_array($session['metadata_json'] ?? '');
    $metadata['avatar_geo'] = [
        'latitude' => $lat,
        'longitude' => $lng,
        'accuracy_meters' => $accuracy,
        'source' => 'merchant_location_opt_in',
        'merchant_user_id' => $merchantUserId,
        'saved_at' => gmdate('c'),
    ];

    $sets = ['metadata_json=?','last_active_at=NOW()','updated_at=NOW()'];
    $params = [json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_latitude')) { $sets[] = 'avatar_latitude=?'; $params[] = $lat; }
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_longitude')) { $sets[] = 'avatar_longitude=?'; $params[] = $lng; }
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_accuracy_meters')) { $sets[] = 'avatar_geo_accuracy_meters=?'; $params[] = $accuracy; }
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_source')) { $sets[] = 'avatar_geo_source=?'; $params[] = 'merchant_location_opt_in'; }
    $params[] = (int)$session['id'];
    $params[] = (int)$user['id'];

    $pdo->prepare('UPDATE mg_store_sessions SET ' . implode(',', $sets) . ' WHERE id=? AND customer_user_id=?')->execute($params);
    mg_store_log_event($pdo, $session, 'avatar_anchor_saved', 'Avatar anchored to merchant location', [
        'geo_source' => 'merchant_location_opt_in',
        'merchant_user_id' => $merchantUserId,
    ]);

    mg_ok([
        'session_id' => (string)$session['public_id'],
        'anchored' => true,
        'geo_source' => 'merchant_location_opt_in',
    ], 'Avatar anchored to the merchant World Canvas location.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'store.avatar_anchor_failed', 'Store avatar anchor failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to anchor the avatar.', 500);
}
