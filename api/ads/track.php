<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('POST');
$pdo = mg_db();
$input = mg_input();
$user = function_exists('mg_current_user') ? mg_current_user() : null;

try {
    if (function_exists('mg_rate_limit')) {
        $rateKey = is_array($user) && isset($user['id']) ? 'user:' . (int)$user['id'] : 'ip:' . mg_ads_hash((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        mg_rate_limit('ads.track', $rateKey, 240, 60);
    }
    $schema = mg_ads_schema_status($pdo);
    if (!$schema['ready']) {
        mg_ok(['schema_ready' => false, 'tracked' => false], 'Campaign Ads Manager migration is required.');
    }
    $event = mg_ads_track_event($pdo, $input, is_array($user) ? $user : null);
    mg_ok(['schema_ready' => true] + $event, 'Ad event tracked.');
} catch (Throwable $error) {
    mg_security_log('warning', 'ads.track_failed', 'Campaign Ads Manager tracking failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], is_array($user) && isset($user['id']) ? (int)$user['id'] : null);
    mg_fail($error->getMessage(), 422);
}
