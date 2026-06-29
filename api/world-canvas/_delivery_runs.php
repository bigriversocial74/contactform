<?php
/** Target Drop delivery run helpers. */
declare(strict_types=1);

require_once __DIR__ . '/_target_drop_interests.php';

function mg_world_delivery_runs_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'merchant_target_drop_delivery_runs');
}

function mg_world_delivery_run_public_id(): string
{
    try { return 'tdrun_' . bin2hex(random_bytes(16)); }
    catch (Throwable) { return 'tdrun_' . str_replace('.', '', uniqid('', true)); }
}

function mg_world_delivery_run_control(float $startX, float $startY, float $endX, float $endY): array
{
    $dx = $endX - $startX;
    $dy = $endY - $startY;
    $lift = max(8.0, min(24.0, sqrt(($dx * $dx) + ($dy * $dy)) * 0.34));
    return ['x' => $startX + ($dx * 0.5), 'y' => max(-12.0, min(112.0, $startY + ($dy * 0.5) - $lift))];
}

function mg_world_delivery_run_project(?float $lat, ?float $lng, string $seed, string $type): ?array
{
    $geo = mg_world_canvas_valid_geo($lat, $lng, null, $type);
    return $geo ? mg_world_canvas_geo_project($geo, $seed, 0, $type) : null;
}

function mg_world_delivery_run_target_row(PDO $pdo, string $dropPublicId, int $merchantId = 0): ?array
{
    if (!mg_world_target_drops_ready($pdo)) return null;
    if ($merchantId > 0) {
        $rows = mg_world_canvas_rows($pdo, 'SELECT * FROM merchant_target_drops WHERE public_id=? AND merchant_user_id=? LIMIT 1', [$dropPublicId, $merchantId]);
    } else {
        $rows = mg_world_canvas_rows($pdo, "SELECT * FROM merchant_target_drops WHERE public_id=? AND visibility IN ('public','audience') LIMIT 1", [$dropPublicId]);
    }
    return $rows[0] ?? null;
}

function mg_world_delivery_run_ensure_launch(PDO $pdo, array $drop): array
{
    if (!empty($drop['launch_latitude']) && !empty($drop['launch_longitude'])) return $drop;
    $merchantId = (int)($drop['merchant_user_id'] ?? 0);
    $locationId = isset($drop['merchant_location_id']) ? (int)$drop['merchant_location_id'] : null;
    if ($merchantId <= 0 || !function_exists('mg_world_target_drop_launch_location')) return $drop;
    $launch = mg_world_target_drop_launch_location($pdo, $merchantId, $locationId);
    if (!empty($launch['latitude']) && !empty($launch['longitude'])) {
        $drop['merchant_location_id'] = $launch['id'] ?? ($drop['merchant_location_id'] ?? null);
        $drop['launch_latitude'] = $launch['latitude'];
        $drop['launch_longitude'] = $launch['longitude'];
    }
    return $drop;
}

function mg_world_delivery_run_create(PDO $pdo, array $drop, string $runType = 'live'): ?array
{
    if (!mg_world_delivery_runs_ready($pdo)) return null;
    $runType = $runType === 'test' ? 'test' : 'live';
    $dropId = (int)($drop['db_id'] ?? $drop['id_numeric'] ?? $drop['id'] ?? 0);
    if ($dropId <= 0 && !empty($drop['public_id'])) {
        $row = mg_world_delivery_run_target_row($pdo, (string)$drop['public_id'], (int)($drop['merchant_user_id'] ?? 0));
        if ($row) $drop = array_merge($row, $drop);
        $dropId = (int)($drop['id'] ?? 0);
    }
    if ($dropId <= 0) return null;
    $drop = mg_world_delivery_run_ensure_launch($pdo, $drop);
    $merchantId = (int)($drop['merchant_user_id'] ?? 0);
    $targetLat = (float)($drop['target_latitude'] ?? 0);
    $targetLng = (float)($drop['target_longitude'] ?? 0);
    $launchLat = isset($drop['launch_latitude']) ? (float)$drop['launch_latitude'] : null;
    $launchLng = isset($drop['launch_longitude']) ? (float)$drop['launch_longitude'] : null;
    $targetPoint = isset($drop['target_x'], $drop['target_y']) ? ['x' => (float)$drop['target_x'], 'y' => (float)$drop['target_y']] : mg_world_delivery_run_project($targetLat, $targetLng, (string)($drop['public_id'] ?? $dropId), 'target_drop');
    $launchPoint = isset($drop['launch_x'], $drop['launch_y']) ? ['x' => (float)$drop['launch_x'], 'y' => (float)$drop['launch_y']] : mg_world_delivery_run_project($launchLat, $launchLng, (string)($drop['public_id'] ?? $dropId) . ':launch', 'merchant');
    if (!$targetPoint) return null;
    if (!$launchPoint) throw new RuntimeException('Set your merchant World location before running a test launch.');
    $control = mg_world_delivery_run_control((float)$launchPoint['x'], (float)$launchPoint['y'], (float)$targetPoint['x'], (float)$targetPoint['y']);
    $publicId = mg_world_delivery_run_public_id();
    $startedAt = date('Y-m-d H:i:s');
    $interceptOpens = date('Y-m-d H:i:s', time() + 1);
    $interceptCloses = date('Y-m-d H:i:s', time() + 2);
    $stmt = $pdo->prepare("INSERT INTO merchant_target_drop_delivery_runs (public_id,target_drop_id,merchant_user_id,status,run_type,launch_latitude,launch_longitude,target_latitude,target_longitude,launch_x,launch_y,target_x,target_y,control_point_json,animation_duration_ms,animation_started_at,intercept_window_opens_at,intercept_window_closes_at,metadata_json,created_at,updated_at) VALUES (?,?,?,'sending',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([$publicId, $dropId, $merchantId, $runType, $launchLat, $launchLng, $targetLat, $targetLng, $launchPoint['x'], $launchPoint['y'], $targetPoint['x'], $targetPoint['y'], json_encode($control, JSON_UNESCAPED_SLASHES), 1700, $startedAt, $interceptOpens, $interceptCloses, json_encode(['source' => 'world_canvas', 'drop_public_id' => (string)($drop['public_id'] ?? '')], JSON_UNESCAPED_SLASHES)]);
    return mg_world_delivery_run_get($pdo, $publicId);
}

function mg_world_delivery_run_get(PDO $pdo, string $publicId, int $viewerUserId = 0): ?array
{
    if (!mg_world_delivery_runs_ready($pdo)) return null;
    $rows = mg_world_canvas_rows($pdo, 'SELECT r.*, d.public_id AS drop_public_id, d.drop_name, d.campaign_title FROM merchant_target_drop_delivery_runs r JOIN merchant_target_drops d ON d.id=r.target_drop_id WHERE r.public_id=? LIMIT 1', [$publicId]);
    return $rows ? mg_world_delivery_run_payload($rows[0], $viewerUserId) : null;
}

function mg_world_delivery_run_payload(array $row, int $viewerUserId = 0): array
{
    $started = !empty($row['animation_started_at']) ? strtotime((string)$row['animation_started_at']) : time();
    $duration = max(700, (int)($row['animation_duration_ms'] ?? 1700));
    $elapsed = max(0, (int)round((time() - (int)$started) * 1000));
    $status = (string)($row['status'] ?? 'queued');
    if ($status === 'sending' && $elapsed >= $duration) $status = 'delivered';
    $merchantUserId = (int)($row['merchant_user_id'] ?? 0);
    $owned = $viewerUserId > 0 && $merchantUserId === $viewerUserId;
    return [
        'id' => (string)$row['public_id'],
        'target_drop_id' => (string)($row['drop_public_id'] ?? ''),
        'drop_name' => (string)($row['drop_name'] ?? ''),
        'campaign_title' => (string)($row['campaign_title'] ?? ''),
        'merchant_user_id' => $merchantUserId,
        'owned' => $owned,
        'run_type' => (string)($row['run_type'] ?? 'live'),
        'status' => $status,
        'stored_status' => (string)($row['status'] ?? 'queued'),
        'launch_x' => $row['launch_x'] === null ? null : (float)$row['launch_x'],
        'launch_y' => $row['launch_y'] === null ? null : (float)$row['launch_y'],
        'target_x' => $row['target_x'] === null ? null : (float)$row['target_x'],
        'target_y' => $row['target_y'] === null ? null : (float)$row['target_y'],
        'control' => mg_world_canvas_json_array($row['control_point_json'] ?? null),
        'duration_ms' => $duration,
        'elapsed_ms' => min($duration, $elapsed),
        'animation_started_at' => $row['animation_started_at'] ?? null,
        'delivered_at' => $row['delivered_at'] ?? null,
        'intercept_window_opens_at' => $row['intercept_window_opens_at'] ?? null,
        'intercept_window_closes_at' => $row['intercept_window_closes_at'] ?? null,
        'intercept_ready' => !$owned,
        'can_intercept' => !$owned,
        'intercepted_by_user_id' => $row['intercepted_by_user_id'] === null ? null : (int)$row['intercepted_by_user_id'],
        'intercept_tool_public_id' => $row['intercept_tool_public_id'] ?? null,
    ];
}

function mg_world_delivery_run_list(PDO $pdo, array $user): array
{
    if (!mg_world_delivery_runs_ready($pdo)) return [];
    $userId = (int)($user['id'] ?? 0);
    $rows = mg_world_canvas_rows($pdo, "SELECT r.*, d.public_id AS drop_public_id, d.drop_name, d.campaign_title FROM merchant_target_drop_delivery_runs r JOIN merchant_target_drops d ON d.id=r.target_drop_id WHERE r.merchant_user_id=? OR (r.run_type='live' AND d.visibility IN ('public','audience') AND r.status IN ('queued','sending','delivered')) ORDER BY r.created_at DESC LIMIT 80", [$userId]);
    return array_map(static fn(array $row): array => mg_world_delivery_run_payload($row, $userId), $rows);
}
