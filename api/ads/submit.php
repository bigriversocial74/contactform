<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('POST');
$user = mg_require_api_user();
$pdo = mg_db();
$input = mg_input();
mg_require_csrf_for_write($input);
mg_ads_require_merchant_user($user, $pdo);

$publicId = mg_ads_text($input['ad_campaign_id'] ?? $input['public_id'] ?? '', 80, '');
if ($publicId === '') mg_fail('Ad campaign id is required.', 422);

try {
    if (function_exists('mg_rate_limit')) mg_rate_limit('ads.submit', 'user:' . (int)$user['id'], 40, 60);
    $campaign = mg_ads_submit_campaign($pdo, (int)$user['id'], $publicId);
    mg_ok(['schema_ready' => true, 'campaign' => $campaign], 'Ad campaign submitted for review.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.submit_failed', 'Campaign Ads Manager submit failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
