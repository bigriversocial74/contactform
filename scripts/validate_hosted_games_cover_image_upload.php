<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'includes/hosted-game-cover-images.php',
    'api/admin/hosted-game-cover-upload.php',
    'api/merchant/hosted-game-cover-upload.php',
    'api/public/hosted-game-cover.php',
    'assets/js/hosted-games-cover-upload.js',
    'assets/css/hosted-games-cover-upload.css',
    'admin/hosted-games.php',
    'merchant-games.php',
    'includes/merchant-hosted-games-view.php',
];
foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) $errors[] = "Missing required file: {$file}";
}

$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    return is_string($content) ? $content : '';
};
$mustContain = static function (string $path, array $needles) use (&$errors, $read): void {
    $content = $read($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) $errors[] = "{$path} is missing contract: {$needle}";
    }
};
$mustNotContain = static function (string $path, array $needles) use (&$errors, $read): void {
    $content = $read($path);
    foreach ($needles as $needle) {
        if (str_contains($content, $needle)) $errors[] = "{$path} contains forbidden contract: {$needle}";
    }
};

$mustContain('includes/hosted-game-cover-images.php', [
    'MG_HOSTED_GAME_COVER_MAX_BYTES = 10485760',
    'MG_HOSTED_GAME_COVER_MIN_WIDTH = 640',
    'MG_HOSTED_GAME_COVER_MIN_HEIGHT = 360',
    "'image/jpeg'",
    "'image/png'",
    "'image/webp'",
    'new finfo(FILEINFO_MIME_TYPE)',
    'getimagesize',
    'is_uploaded_file',
    'move_uploaded_file',
    'hash_file',
    "'private_local'",
    "'hosted_game_cover'",
    "UPDATE hosted_games SET cover_url=",
    "status='archived'",
]);
$mustNotContain('includes/hosted-game-cover-images.php', [
    'image/svg+xml',
    'application/octet-stream',
    'storage/public',
]);

$mustContain('api/admin/hosted-game-cover-upload.php', [
    "admin.hosted_games.manage",
    'mg_require_csrf_for_write($_POST)',
    'mg_hosted_game_by_public_id',
    'mg_hosted_game_store_cover_image',
    "admin.hosted_game.cover_uploaded",
]);
$mustContain('api/merchant/hosted-game-cover-upload.php', [
    "merchant.hosted_games.manage",
    'mg_require_csrf_for_write($_POST)',
    'mg_hosted_game_for_merchant',
    'mg_hosted_game_store_cover_image',
    "merchant.hosted_game.cover_uploaded",
]);

$mustContain('api/public/hosted-game-cover.php', [
    "['GET', 'HEAD']",
    'mg_hosted_game_cover_reference_matches',
    "status='ready'",
    '$isOwner',
    '$isAdmin',
    '$isPublic',
    'X-Content-Type-Options: nosniff',
    'Cross-Origin-Resource-Policy: same-origin',
    'Cache-Control: public',
    'Cache-Control: private, no-store',
]);

$mustContain('assets/js/hosted-games-cover-upload.js', [
    '/api/admin/hosted-game-cover-upload.php',
    '/api/merchant/hosted-game-cover-upload.php',
    "['image/jpeg', 'image/png', 'image/webp']",
    '10485760',
    'data-hgm-cover-file',
    'data-hgm-cover-upload',
    'XMLHttpRequest',
    'form.elements.cover_url.value = coverUrl',
]);

foreach (['admin/hosted-games.php', 'includes/merchant-hosted-games-view.php'] as $path) {
    $mustContain($path, [
        'data-hgm-cover-uploader',
        'data-hgm-cover-preview',
        'data-hgm-cover-file',
        'data-hgm-cover-upload',
        'minimum 640 × 360',
        'maximum 10 MB',
        'Optional external-image fallback',
    ]);
}
$mustContain('admin/hosted-games.php', [
    'hosted-games-cover-upload.css',
    'hosted-games-cover-upload.js',
]);
$mustContain('merchant-games.php', [
    'hosted-games-cover-upload.css',
    'hosted-games-cover-upload.js',
]);

if ($errors !== []) {
    fwrite(STDERR, "Hosted Games cover upload validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Hosted Games cover upload validation passed.\n";
