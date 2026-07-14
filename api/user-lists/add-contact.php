<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

mg_user_lists_api_run(static function () use ($user, $input): array {
    $listId = mg_contact_text($input['list_id'] ?? '', 40);
    $contactType = mg_contact_text($input['contact_type'] ?? '', 40);
    $contactId = mg_contact_text($input['contact_id'] ?? '', 40);
    if ($listId === '' || $contactType === '' || $contactId === '') {
        throw new InvalidArgumentException('List and contact are required.');
    }
    return mg_user_contact_add_member(mg_db(), (int) $user['id'], $listId, $contactType, $contactId, $input);
});
