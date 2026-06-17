<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/profile-moderation/_owner.php';

mg_require_method('GET');
$user = mg_require_api_user();

try {
    $data = mg_profile_moderation_owner_status(mg_db(), (int)$user['id']);
} catch (Throwable $error) {
    error_log('Profile moderation owner status failed: ' . $error::class);
    mg_fail('Unable to load profile moderation status.', 500);
}

mg_ok($data, 'Profile moderation status loaded.');
