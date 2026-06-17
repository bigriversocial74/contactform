<?php
declare(strict_types=1);

require_once __DIR__ . '/_actions.php';

mg_require_method('POST');
$user = mg_profile_moderation_require_manage();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $data = mg_profile_moderation_apply_action(mg_db(), $user, $input);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (DomainException $error) {
    mg_fail($error->getMessage(), 409);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    error_log('Profile moderation action failed: ' . $error::class);
    mg_fail('Unable to apply moderation action.', 500);
}

mg_ok($data, 'Profile moderation action applied.');
