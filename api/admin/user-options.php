<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_context.php';

mg_require_method('GET');
$actor = mg_admin_users_require_user();

try {
    $targetUserId = mg_admin_user_detail_id($_GET['user_id'] ?? null);
    $context = mg_admin_management_context(mg_db(), $actor, $targetUserId);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_fail('Unable to load user options.', 500);
}

mg_ok(['management' => $context], 'User options loaded.');
