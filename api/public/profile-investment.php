<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/market/merchant-market-engine.php';

mg_require_method('GET');

$pdo = mg_db();
$slug = mg_public_profile_slug((string)($_GET['slug'] ?? ''));
$currentUser = mg_current_user();
$viewerId = (int)($currentUser['id'] ?? 0);
$viewerId = $viewerId > 0 ? $viewerId : null;

try {
    $payload = mg_merchant_market_build($pdo, $slug, [
        'viewer_id' => $viewerId,
        'preview' => !empty($_GET['preview']),
    ]);
} catch (Throwable) {
    mg_fail('Profile not found.', 404);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($payload);
