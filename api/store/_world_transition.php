<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/world-canvas/_locations.php';

function mg_store_world_transition_geo(array $session): ?array
{
    $metadata = mg_world_canvas_json_array($session['metadata_json'] ?? '');
    $storedGeo = isset($metadata['avatar_geo']) && is_array($metadata['avatar_geo']) ? $metadata['avatar_geo'] : [];
    $source = (string)($session['avatar_geo_source'] ?? ($storedGeo['source'] ?? ''));
    if ($source !== 'merchant_location_opt_in') return null;

    $lat = $session['avatar_latitude'] ?? ($storedGeo['latitude'] ?? null);
    $lng = $session['avatar_longitude'] ?? ($storedGeo['longitude'] ?? null);
    $accuracy = $session['avatar_geo_accuracy_meters'] ?? ($storedGeo['accuracy_meters'] ?? null);
    return mg_world_canvas_valid_geo($lat, $lng, $accuracy, 'store_exit_merchant_location');
}

function mg_store_world_transition_eligible(array $session): bool
{
    return mg_store_world_transition_geo($session) !== null;
}

function mg_store_world_transition_from_session(PDO $pdo, array $session, int $userId, string $reason = 'manual'): ?array
{
    if ($userId <= 0 || !mg_world_canvas_table($pdo, 'user_world_positions')) return null;

    $geo = mg_store_world_transition_geo($session);
    if ($geo === null) return null;

    $position = mg_world_location_save_user($pdo, $userId, $geo, 'store_session');
    if (function_exists('mg_store_log_event')) {
        mg_store_log_event($pdo, $session, 'world_canvas_entered', 'Avatar entered World Canvas at merchant location', [
            'position_id' => $position['id'] ?? null,
            'geo_source' => 'store_exit_merchant_location',
            'transition_reason' => $reason,
        ]);
    }

    $position['transition_reason'] = $reason;
    return $position;
}
