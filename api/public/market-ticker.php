<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/app.php';
require_once dirname(__DIR__, 2) . '/includes/market/public-market-ticker.php';

mg_require_method('GET');
$limit = max(4, min(24, (int)($_GET['limit'] ?? 12)));
try {
    $items = mg_public_market_ticker_items(mg_db(), $limit, true);
} catch (Throwable $error) {
    $items = mg_public_market_ticker_fallback_items();
}
$hasLiveItems = mg_public_market_ticker_has_live_items($items);

header_remove('Set-Cookie');
header('Cache-Control: public, max-age=' . ($hasLiveItems ? '45' : '180') . ', stale-while-revalidate=60');
header('X-Robots-Tag: noindex, follow');
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'message' => 'OK', 'data' => ['items' => $items, 'has_live_items' => $hasLiveItems]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
