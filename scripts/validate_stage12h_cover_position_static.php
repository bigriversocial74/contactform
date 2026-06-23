<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/api/profiles/cover-position.php';
$code = is_file($file) ? (string) file_get_contents($file) : '';
$checks = [
    'file_exists' => is_file($file),
    'post_only' => str_contains($code, "mg_require_method('POST')"),
    'requires_auth' => str_contains($code, 'mg_current_user'),
    'requires_csrf' => str_contains($code, 'mg_require_csrf_for_write'),
    'bounds_x_y' => str_contains($code, '$x < 0') && str_contains($code, '$x > 100') && str_contains($code, '$y < 0') && str_contains($code, '$y > 100'),
    'metadata_keys' => str_contains($code, 'cover_position_x') && str_contains($code, 'cover_position_y'),
    'audit_event' => str_contains($code, 'profile.cover_position_updated'),
];
$failed = array_keys(array_filter($checks, static fn($pass) => !$pass));
$result = ['ok' => count($failed) === 0, 'failed' => $failed, 'checks' => $checks];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
