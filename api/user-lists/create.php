<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

mg_user_lists_api_run(static function () use ($user, $input): array {
    $list = mg_user_contact_list_create(mg_db(), (int) $user['id'], $input);
    return ['list' => $list, 'open_url' => '/list.php?id=' . rawurlencode((string) $list['id'])];
});
