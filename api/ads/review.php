<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('POST');
$user = mg_require_api_user();
$pdo = mg_db();
$input = mg_input();
mg_require_csrf_for_write($input);
mg_ads_require_admin_user($user);

$publicId = mg_ads_text($input['ad_campaign_id'] ?? $input['public_id'] ?? '', 80, '');
$action = mg_ads_enum($input['action'] ?? 'approve', ['approve','reject','pause','reactivate'], 'approve');
$notes = mg_ads_nullable_text($input['review_notes'] ?? $input['notes'] ?? '', 2000);
if ($publicId === '') mg_fail('Ad campaign id is required.', 422);

try {
    if (function_exists('mg_rate_limit')) mg_rate_limit('ads.review', 'user:' . (int)$user['id'], 120, 60);
    $campaign = mg_ads_review_campaign($pdo, (int)$user['id'], $publicId, $action, $notes);
    mg_ok(['schema_ready' => true, 'campaign' => $campaign], 'Ad campaign review updated.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.review_failed', 'Campaign Ads Manager review failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
