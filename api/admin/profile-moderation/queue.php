<?php
declare(strict_types=1);

require_once __DIR__ . '/_queue.php';

mg_require_method('GET');
$user = mg_profile_moderation_require_view();

try {
    $data = mg_profile_moderation_queue(mg_db(), $user, $_GET);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    error_log('Profile moderation queue failed: ' . $error::class);
    mg_fail('Unable to load profile moderation queue.', 500);
}

mg_ok($data, 'Profile moderation queue loaded.');
