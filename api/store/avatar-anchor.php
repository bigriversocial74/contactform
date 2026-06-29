<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas.php';
require_once dirname(__DIR__) . '/world-canvas/_world.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

function mg_avatar_anchor_number(mixed $value, float $min, float $max): ?float
{
    if ($value === null || $value === '') return null;
    $number = (float)$value;
    if (!is_finite($number) || $number < $min || $number > $max) return null;
    return $number;
}

try {
    mg_rate_limit('store.avatar_anchor', 'user:' . (int)$user['id'], 60, 60);
    if (($input['consent'] ?? '') !== 'yes') mg_fail('Consent is required.', 403);
    $lat = mg_avatar_anchor_number($input['avatar_latitude'] ?? null, -90, 90);
    $lng = mg_avatar_anchor_number($input['avatar_longitude'] ?? null, -180, 180);
    if ($lat === null || $lng === null) mg_fail('Valid avatar coordinates are required.', 422);
    mg_store_require_schema($pdo);
    $session = mg_store_active_session_for_customer($pdo, (int)$user['id'], true);
    if (!$session) mg_fail('No active avatar session.', 400);

    $accuracy = isset($input['avatar_accuracy']) ? max(0, min(100000, (int)$input['avatar_accuracy'])) : null;
    $metadata = mg_world_canvas_json_array($session['metadata_json'] ?? '');
    $metadata['avatar_geo'] = [
        'latitude' => round($lat, 7),
        'longitude' => round($lng, 7),
        'accuracy_meters' => $accuracy,
        'source' => 'explicit_opt_in',
        'saved_at' => gmdate('c'),
    ];

    $sets = ['metadata_json=?','last_active_at=NOW()','updated_at=NOW()'];
    $params = [json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_latitude')) { $sets[] = 'avatar_latitude=?'; $params[] = round($lat, 7); }
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_longitude')) { $sets[] = 'avatar_longitude=?'; $params[] = round($lng, 7); }
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_accuracy_meters')) { $sets[] = 'avatar_geo_accuracy_meters=?'; $params[] = $accuracy; }
    if (mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_source')) { $sets[] = 'avatar_geo_source=?'; $params[] = 'explicit_opt_in'; }
    $params[] = (int)$session['id'];
    $params[] = (int)$user['id'];
    $pdo->prepare('UPDATE mg_store_sessions SET ' . implode(',', $sets) . ' WHERE id=? AND customer_user_id=?')->execute($params);
    mg_store_log_event($pdo, $session, 'avatar_anchor_saved', 'Avatar anchor saved', ['accuracy_meters' => $accuracy]);
    mg_ok(['session_id' => (string)$session['public_id'], 'anchored' => true], 'Avatar anchor saved.');
} catch (Throwable $error) {
    mg_security_log('error', 'store.avatar_anchor_failed', 'Avatar anchor save failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to save avatar anchor.', 500);
}
