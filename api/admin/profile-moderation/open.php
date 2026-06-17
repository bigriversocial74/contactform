<?php
declare(strict_types=1);

require_once __DIR__ . '/_queue.php';

mg_require_method('POST');
$user = mg_profile_moderation_require_manage();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $data = mg_profile_moderation_open_case(mg_db(), $user, $input);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (DomainException $error) {
    mg_fail($error->getMessage(), 409);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    error_log('Profile moderation case creation failed: ' . $error::class);
    mg_fail('Unable to create moderation case.', 500);
}

mg_ok($data, 'Profile moderation case created.', 201);
