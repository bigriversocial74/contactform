<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_product_discovery.php';

mg_require_method('GET');
$pdo = mg_db();
$viewer = mg_profile_discovery_viewer($pdo);
$viewerId = isset($viewer['id']) ? (int)$viewer['id'] : null;
$identifier = $viewerId !== null ? 'user:' . $viewerId : 'ip:' . (mg_client_ip() ?? 'unknown');
mg_rate_limit('product.discovery.read', $identifier, $viewerId !== null ? 240 : 90, 60);

try {
    $products = mg_product_discovery_search($pdo, $_GET, $viewerId);
} catch (InvalidArgumentException $error) {
    mg_security_log('warning','product.discovery.invalid_request','Invalid product discovery request.',[
        'reason'=>$error->getMessage(),'authenticated'=>$viewerId !== null,
    ],$viewerId);
    mg_fail('Invalid product search filters.',422);
} catch (Throwable $error) {
    mg_security_log('error','product.discovery.failed','Product discovery query failed.',[
        'exception_class'=>$error::class,'authenticated'=>$viewerId !== null,
    ],$viewerId);
    mg_fail('Unable to search local vouchers.',500);
}

mg_event('product.discovery.read',[
    'authenticated'=>$viewerId !== null,
    'query_present'=>trim((string)($_GET['q'] ?? '')) !== '',
    'result_count'=>count($products['items'] ?? []),
],$viewerId);

if ($viewerId === null) {
    header_remove('Set-Cookie');
    header('Cache-Control: public, max-age=30, stale-while-revalidate=30');
} else {
    header('Cache-Control: private, no-store, max-age=0');
}
header('Vary: Cookie, Authorization');
header('X-Robots-Tag: noindex, follow');
mg_ok(['products'=>$products]);
