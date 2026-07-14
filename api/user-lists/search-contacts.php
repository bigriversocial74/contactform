<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$query = mg_contact_text($_GET['q'] ?? '', 80);
$listPublicId = mg_contact_text($_GET['list_id'] ?? '', 40);

mg_user_lists_api_run(static function () use ($user, $query, $listPublicId): array {
    $pdo = mg_db();
    $listInternalId = null;
    if ($listPublicId !== '') {
        $list = mg_user_contact_list_load($pdo, (int) $user['id'], $listPublicId);
        $listInternalId = (int) $list['id_internal'];
    }
    return ['contacts' => mg_user_contact_search($pdo, (int) $user['id'], $query, $listInternalId)];
});
