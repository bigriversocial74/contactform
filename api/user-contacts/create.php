<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/user-lists/_bootstrap.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

mg_user_lists_api_run(static function () use ($user, $input): array {
    $pdo = mg_db();
    $pdo->beginTransaction();
    try {
        $contact = mg_user_contact_create($pdo, (int) $user['id'], $input);
        $listIds = is_array($input['list_ids'] ?? null) ? $input['list_ids'] : [];
        $memberships = [];
        foreach (array_values(array_unique(array_filter(array_map('strval', $listIds)))) as $listId) {
            $memberships[] = mg_user_contact_add_member(
                $pdo,
                (int) $user['id'],
                mg_contact_text($listId, 40),
                'private_contact',
                (string) $contact['id'],
                $input
            );
        }
        $pdo->commit();
        return ['contact' => $contact, 'memberships' => $memberships];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
});
