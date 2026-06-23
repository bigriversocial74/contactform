<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_public_profile.php';

mg_require_method('POST');

$pdo = mg_db();
$user = mg_current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId < 1) mg_fail('Authentication required.', 401);

$input = mg_input();
mg_require_csrf_for_write($input);

$slug = mg_public_profile_slug((string)($input['slug'] ?? ''));
$x = filter_var($input['x'] ?? null, FILTER_VALIDATE_INT);
$y = filter_var($input['y'] ?? null, FILTER_VALIDATE_INT);

if ($x === false || $y === false || $x < 0 || $x > 100 || $y < 0 || $y > 100) {
    mg_fail('Invalid cover position.', 422);
}

$stmt = $pdo->prepare('SELECT id,user_id,metadata_json FROM public_profiles WHERE slug=? LIMIT 1');
$stmt->execute([$slug]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) mg_fail('Profile not found.', 404);
if ((int)$profile['user_id'] !== $userId) mg_fail('You can only adjust your own profile cover.', 403);

$meta = [];
if (is_string($profile['metadata_json'] ?? null) && trim((string)$profile['metadata_json']) !== '') {
    $decoded = json_decode((string)$profile['metadata_json'], true);
    if (is_array($decoded)) $meta = $decoded;
}

$meta['cover_position_x'] = (int)$x;
$meta['cover_position_y'] = (int)$y;

$stmt = $pdo->prepare('UPDATE public_profiles SET metadata_json=?, updated_at=NOW() WHERE id=? AND user_id=? LIMIT 1');
$stmt->execute([
    json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    (int)$profile['id'],
    $userId,
]);

mg_audit('profile.cover_position_updated', 'public_profile', [
    'profile_slug' => $slug,
    'cover_position_x' => (int)$x,
    'cover_position_y' => (int)$y,
], $userId);

mg_ok([
    'cover_position' => [
        'x' => (int)$x,
        'y' => (int)$y,
    ],
], 'Cover position saved.');
