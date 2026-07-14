<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

mg_user_lists_api_run(static function () use ($user, $input): array {
    $membershipId = mg_contact_text($input['membership_id'] ?? '', 40);
    if ($membershipId === '') {
        throw new InvalidArgumentException('Membership id is required.');
    }
    $pdo = mg_db();
    $stmt = $pdo->prepare('DELETE FROM user_contact_list_members WHERE owner_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([(int) $user['id'], $membershipId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('List membership not found.');
    }
    mg_audit('user_contact_list.member_removed', 'user_contact_list_member', ['membership_id' => $membershipId], (int) $user['id']);
    return ['removed' => true, 'membership_id' => $membershipId];
});
