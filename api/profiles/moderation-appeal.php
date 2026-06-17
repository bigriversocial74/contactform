<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/profile-moderation/_owner.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $data = mg_profile_moderation_submit_appeal(mg_db(), (int)$user['id'], $input);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (DomainException $error) {
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    error_log('Profile moderation appeal failed: ' . $error::class);
    mg_fail('Unable to submit moderation appeal.', 500);
}

mg_ok($data, 'Profile moderation appeal submitted.', 201);
