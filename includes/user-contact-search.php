<?php
declare(strict_types=1);

require_once __DIR__ . '/user-contact-lists.php';

/**
 * Search private contacts and registered users already connected to the owner
 * through at least one active follow edge. Final list eligibility remains the
 * stricter mutual-follow check in mg_user_contact_list_eligibility_detail().
 */
function mg_user_contact_relationship_search(PDO $pdo, int $ownerUserId, string $query, ?int $listId = null): array
{
    $query = mg_contact_text($query, 80);
    if (mb_strlen($query) < 2) {
        return [];
    }

    $like = '%' . $query . '%';
    $results = [];

    $private = $pdo->prepare(
        "SELECT c.public_id,c.display_name,c.nickname,c.email,c.birthdate,c.phone_last4,
         EXISTS(
           SELECT 1 FROM user_contact_list_members m
           WHERE m.list_id=? AND m.owner_user_id=? AND m.user_contact_id=c.id
         ) already_in_list
         FROM user_contacts c
         WHERE c.owner_user_id=?
           AND c.archived_at IS NULL
           AND (c.display_name LIKE ? OR c.nickname LIKE ? OR c.email LIKE ?)
         ORDER BY c.display_name
         LIMIT 10"
    );
    $private->execute([$listId ?? 0, $ownerUserId, $ownerUserId, $like, $like, $like]);
    foreach ($private->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'private_contact',
            'id' => (string) $row['public_id'],
            'display_name' => (string) $row['display_name'],
            'subtitle' => (string) ($row['nickname'] ?: mg_contact_email_hint($row['email'] ?? null)),
            'birthdate' => $row['birthdate'] ?: null,
            'phone_masked' => mg_contact_phone_mask($row['phone_last4'] ?? null),
            'eligible' => true,
            'eligibility_code' => 'private_contact',
            'already_in_list' => (bool) $row['already_in_list'],
            'avatar_url' => '',
            'profile_slug' => '',
        ];
    }

    $users = $pdo->prepare(
        "SELECT DISTINCT u.id,u.public_id,
           COALESCE(pp.display_name,u.display_name,u.full_name,'Microgifter user') display_name,
           pp.avatar_url,pp.slug,
           EXISTS(
             SELECT 1 FROM user_contact_list_members m
             WHERE m.list_id=? AND m.owner_user_id=? AND m.contact_user_id=u.id
           ) already_in_list
         FROM users u
         INNER JOIN social_follows relationship
           ON (
             relationship.follower_user_id=? AND relationship.followed_user_id=u.id
           ) OR (
             relationship.followed_user_id=? AND relationship.follower_user_id=u.id
           )
         LEFT JOIN public_profiles pp
           ON pp.user_id=u.id
          AND pp.status='active'
          AND pp.visibility IN ('public','unlisted')
         WHERE u.id<>?
           AND u.status='active'
           AND relationship.status='active'
           AND (
             u.display_name LIKE ?
             OR u.full_name LIKE ?
             OR pp.display_name LIKE ?
             OR pp.slug LIKE ?
           )
         ORDER BY display_name
         LIMIT 20"
    );
    $users->execute([
        $listId ?? 0,
        $ownerUserId,
        $ownerUserId,
        $ownerUserId,
        $ownerUserId,
        $like,
        $like,
        $like,
        $like,
    ]);

    foreach ($users->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eligibility = mg_user_contact_list_eligibility_detail($pdo, $ownerUserId, (int) $row['id']);
        $results[] = [
            'type' => 'linked_user',
            'id' => (string) $row['public_id'],
            'display_name' => (string) $row['display_name'],
            'subtitle' => (string) $eligibility['message'],
            'eligible' => (bool) $eligibility['eligible'],
            'eligibility_code' => (string) $eligibility['code'],
            'already_in_list' => (bool) $row['already_in_list'],
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'profile_slug' => (string) ($row['slug'] ?? ''),
        ];
    }

    return $results;
}
