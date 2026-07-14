<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$includeArchived = filter_var($_GET['archived'] ?? false, FILTER_VALIDATE_BOOL);

mg_user_lists_api_run(static function () use ($user, $includeArchived): array {
    return ['lists' => mg_user_contact_lists(mg_db(), (int) $user['id'], $includeArchived)];
});
