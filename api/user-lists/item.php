<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$listId = mg_contact_text($_GET['id'] ?? '', 40);

mg_user_lists_api_run(static function () use ($user, $listId): array {
    if ($listId === '') {
        throw new InvalidArgumentException('List id is required.');
    }
    $pdo = mg_db();
    $list = mg_user_contact_list_load($pdo, (int) $user['id'], $listId);
    $members = mg_user_contact_list_members($pdo, (int) $user['id'], (int) $list['id_internal']);
    unset($list['id_internal']);
    return ['list' => $list, 'members' => $members];
});
