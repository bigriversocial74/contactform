<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once dirname(__DIR__) . '/api/db.php';

function mg_contact_text(mixed $value, int $limit = 255): string
{
    return mb_substr(trim((string) $value), 0, $limit);
}

function mg_contact_nullable_text(mixed $value, int $limit = 255): ?string
{
    $value = mg_contact_text($value, $limit);
    return $value === '' ? null : $value;
}

function mg_contact_slug(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    return trim(mb_substr($value, 0, 150), '-') ?: 'list';
}

function mg_contact_email_hint(?string $email): string
{
    $email = trim((string) $email);
    if ($email === '' || !str_contains($email, '@')) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    $first = mb_substr($local, 0, 1);
    return $first . str_repeat('•', max(3, min(7, mb_strlen($local) - 1))) . '@' . $domain;
}

function mg_contact_normalize_phone(?string $phone): string
{
    return preg_replace('/\D+/', '', (string) $phone) ?? '';
}

function mg_contact_phone_encrypt(string $phone): array
{
    $digits = mg_contact_normalize_phone($phone);
    if ($digits === '') {
        return ['ciphertext' => null, 'last4' => null, 'hash' => null];
    }
    if (strlen($digits) < 7 || strlen($digits) > 18) {
        throw new InvalidArgumentException('Enter a valid phone number.');
    }
    $keyMaterial = trim((string) mg_env('MG_CONTACT_DATA_KEY', mg_config_value('security', 'contact_data_key', '')));
    if ($keyMaterial === '') {
        throw new RuntimeException('Private contact phone encryption is not configured. Set MG_CONTACT_DATA_KEY before storing phone numbers.');
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required to store private phone numbers.');
    }
    $key = hash('sha256', $keyMaterial, true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($digits, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'mg-contact-phone-v1');
    if (!is_string($ciphertext) || $tag === '') {
        throw new RuntimeException('Unable to encrypt the phone number.');
    }
    return [
        'ciphertext' => base64_encode($iv . $tag . $ciphertext),
        'last4' => substr($digits, -4),
        'hash' => hash_hmac('sha256', $digits, $keyMaterial),
    ];
}

function mg_contact_phone_mask(?string $last4): string
{
    $last4 = preg_replace('/\D+/', '', (string) $last4) ?? '';
    return strlen($last4) === 4 ? '••• ••• ' . $last4 : '';
}

function mg_user_contact_list_eligibility_detail(PDO $pdo, int $ownerUserId, int $contactUserId): array
{
    if ($ownerUserId < 1 || $contactUserId < 1) {
        return ['eligible' => false, 'code' => 'invalid_user', 'message' => 'A valid user is required.'];
    }
    if ($ownerUserId === $contactUserId) {
        return ['eligible' => false, 'code' => 'self', 'message' => 'You cannot add yourself to a contact list.'];
    }

    $users = $pdo->prepare('SELECT id,status FROM users WHERE id IN (?,?)');
    $users->execute([$ownerUserId, $contactUserId]);
    $states = [];
    foreach ($users->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $states[(int) $row['id']] = (string) ($row['status'] ?? '');
    }
    if (($states[$ownerUserId] ?? '') !== 'active' || ($states[$contactUserId] ?? '') !== 'active') {
        return ['eligible' => false, 'code' => 'inactive', 'message' => 'Both accounts must be active.'];
    }

    $blocked = $pdo->prepare('SELECT 1 FROM social_blocks WHERE (blocking_user_id=? AND blocked_user_id=?) OR (blocking_user_id=? AND blocked_user_id=?) LIMIT 1');
    $blocked->execute([$ownerUserId, $contactUserId, $contactUserId, $ownerUserId]);
    if ($blocked->fetchColumn()) {
        return ['eligible' => false, 'code' => 'blocked', 'message' => 'This account is unavailable.'];
    }

    $follows = $pdo->prepare("SELECT follower_user_id,followed_user_id,status FROM social_follows WHERE (follower_user_id=? AND followed_user_id=?) OR (follower_user_id=? AND followed_user_id=?)");
    $follows->execute([$ownerUserId, $contactUserId, $contactUserId, $ownerUserId]);
    $ownerFollows = false;
    $contactFollows = false;
    foreach ($follows->fetchAll(PDO::FETCH_ASSOC) as $follow) {
        if ((string) ($follow['status'] ?? '') !== 'active') {
            continue;
        }
        if ((int) $follow['follower_user_id'] === $ownerUserId && (int) $follow['followed_user_id'] === $contactUserId) {
            $ownerFollows = true;
        }
        if ((int) $follow['follower_user_id'] === $contactUserId && (int) $follow['followed_user_id'] === $ownerUserId) {
            $contactFollows = true;
        }
    }
    if (!$ownerFollows || !$contactFollows) {
        return [
            'eligible' => false,
            'code' => $ownerFollows ? 'follow_back_required' : 'mutual_follow_required',
            'message' => 'Users must follow each other before list membership is allowed.',
        ];
    }

    $privacy = $pdo->prepare('SELECT allow_list_membership FROM user_contact_preferences WHERE user_id=? LIMIT 1');
    $privacy->execute([$contactUserId]);
    $allow = $privacy->fetchColumn();
    if ($allow !== false && (int) $allow !== 1) {
        return ['eligible' => false, 'code' => 'privacy', 'message' => 'This user does not permit contact-list membership.'];
    }

    return ['eligible' => true, 'code' => 'eligible', 'message' => 'Mutual follow confirmed.'];
}

function mg_user_contact_list_eligible(int $ownerUserId, int $contactUserId): bool
{
    return (bool) (mg_user_contact_list_eligibility_detail(mg_db(), $ownerUserId, $contactUserId)['eligible'] ?? false);
}

function mg_user_contact_list_create(PDO $pdo, int $ownerUserId, array $input): array
{
    $name = mg_contact_text($input['name'] ?? '', 160);
    if ($name === '') {
        throw new InvalidArgumentException('List name is required.');
    }
    $description = mg_contact_nullable_text($input['description'] ?? null, 1000);
    $listType = mg_contact_text($input['list_type'] ?? 'custom', 64) ?: 'custom';
    $iconKey = mg_contact_text($input['icon_key'] ?? 'people', 64) ?: 'people';
    $allowedTypes = ['family','friends','coworkers','clients','birthday','holiday','community','team','vip','custom'];
    if (!in_array($listType, $allowedTypes, true)) {
        $listType = 'custom';
    }
    $allowedIcons = ['people','family','heart','work','gift','calendar','community','team','star','custom'];
    if (!in_array($iconKey, $allowedIcons, true)) {
        $iconKey = 'people';
    }

    $baseSlug = mg_contact_slug($name);
    $slug = $baseSlug;
    $suffix = 2;
    $exists = $pdo->prepare('SELECT 1 FROM user_contact_lists WHERE owner_user_id=? AND slug=? LIMIT 1');
    while (true) {
        $exists->execute([$ownerUserId, $slug]);
        if (!$exists->fetchColumn()) {
            break;
        }
        $slug = mb_substr($baseSlug, 0, 140) . '-' . $suffix++;
    }

    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare('INSERT INTO user_contact_lists (public_id,owner_user_id,name,slug,description,list_type,icon_key,sort_order,is_archived,created_at,updated_at) VALUES (?,?,?,?,?,?,?,100,0,NOW(),NOW())');
    $stmt->execute([$publicId, $ownerUserId, $name, $slug, $description, $listType, $iconKey]);
    mg_audit('user_contact_list.created', 'user_contact_list', ['list_id' => $publicId, 'list_type' => $listType], $ownerUserId);
    mg_event('user_contact_list.created', ['list_id' => $publicId, 'list_type' => $listType], $ownerUserId);
    return mg_user_contact_list_load($pdo, $ownerUserId, $publicId);
}

function mg_user_contact_lists(PDO $pdo, int $ownerUserId, bool $includeArchived = false): array
{
    $sql = "SELECT l.public_id,l.name,l.slug,l.description,l.list_type,l.icon_key,l.sort_order,l.is_archived,l.created_at,l.updated_at,
            COUNT(m.id) member_count,
            MIN(CASE WHEN c.birthdate IS NOT NULL THEN
                DATE_ADD(c.birthdate, INTERVAL (YEAR(CURDATE())-YEAR(c.birthdate) + (DATE_FORMAT(c.birthdate,'%m%d') < DATE_FORMAT(CURDATE(),'%m%d'))) YEAR)
            END) next_birthday
            FROM user_contact_lists l
            LEFT JOIN user_contact_list_members m ON m.list_id=l.id
            LEFT JOIN user_contacts c ON c.id=m.user_contact_id AND c.archived_at IS NULL
            WHERE l.owner_user_id=?" . ($includeArchived ? '' : ' AND l.is_archived=0') . "
            GROUP BY l.id
            ORDER BY l.is_archived,l.sort_order,l.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ownerUserId]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'name' => (string) $row['name'],
        'slug' => (string) $row['slug'],
        'description' => (string) ($row['description'] ?? ''),
        'list_type' => (string) $row['list_type'],
        'icon_key' => (string) $row['icon_key'],
        'sort_order' => (int) $row['sort_order'],
        'is_archived' => (bool) $row['is_archived'],
        'member_count' => (int) $row['member_count'],
        'next_birthday' => $row['next_birthday'] ?: null,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_user_contact_list_load(PDO $pdo, int $ownerUserId, string $publicId): array
{
    $stmt = $pdo->prepare('SELECT id,public_id,name,slug,description,list_type,icon_key,sort_order,is_archived,created_at,updated_at FROM user_contact_lists WHERE owner_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$ownerUserId, $publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('List not found.');
    }
    $row['id_internal'] = (int) $row['id'];
    $row['id'] = (string) $row['public_id'];
    unset($row['public_id']);
    $row['is_archived'] = (bool) $row['is_archived'];
    return $row;
}

function mg_user_contact_list_members(PDO $pdo, int $ownerUserId, int $listId): array
{
    $stmt = $pdo->prepare("SELECT m.public_id membership_id,m.contact_user_id,m.user_contact_id,m.relationship_type,m.relationship_label,m.private_notes,m.added_at,
        COALESCE(c.display_name,pp.display_name,u.display_name,u.full_name,'Contact') display_name,
        COALESCE(c.nickname,'') nickname,c.birthdate,c.phone_last4,c.city,c.state_region,c.interests,c.gift_preferences,c.budget_min,c.budget_max,
        pp.avatar_url,pp.slug profile_slug,u.public_id linked_user_public_id
        FROM user_contact_list_members m
        LEFT JOIN user_contacts c ON c.id=m.user_contact_id AND c.owner_user_id=m.owner_user_id
        LEFT JOIN users u ON u.id=m.contact_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=m.contact_user_id
        WHERE m.owner_user_id=? AND m.list_id=?
        ORDER BY display_name");
    $stmt->execute([$ownerUserId, $listId]);
    return array_map(static fn(array $row): array => [
        'membership_id' => (string) $row['membership_id'],
        'contact_type' => $row['contact_user_id'] ? 'linked_user' : 'private_contact',
        'contact_id' => $row['contact_user_id'] ? (string) ($row['linked_user_public_id'] ?? '') : (string) $row['user_contact_id'],
        'display_name' => (string) $row['display_name'],
        'nickname' => (string) ($row['nickname'] ?? ''),
        'relationship_type' => (string) ($row['relationship_type'] ?? ''),
        'relationship_label' => (string) ($row['relationship_label'] ?? ''),
        'private_notes' => (string) ($row['private_notes'] ?? ''),
        'birthdate' => $row['birthdate'] ?: null,
        'phone_masked' => mg_contact_phone_mask($row['phone_last4'] ?? null),
        'location' => trim(implode(', ', array_filter([(string) ($row['city'] ?? ''), (string) ($row['state_region'] ?? '')]))),
        'interests' => (string) ($row['interests'] ?? ''),
        'gift_preferences' => (string) ($row['gift_preferences'] ?? ''),
        'budget_min' => $row['budget_min'] !== null ? (float) $row['budget_min'] : null,
        'budget_max' => $row['budget_max'] !== null ? (float) $row['budget_max'] : null,
        'avatar_url' => (string) ($row['avatar_url'] ?? ''),
        'profile_slug' => (string) ($row['profile_slug'] ?? ''),
        'added_at' => $row['added_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_user_contact_search(PDO $pdo, int $ownerUserId, string $query, ?int $listId = null): array
{
    $query = mg_contact_text($query, 80);
    if (mb_strlen($query) < 2) {
        return [];
    }
    $like = '%' . $query . '%';
    $results = [];

    $private = $pdo->prepare("SELECT c.id,c.public_id,c.display_name,c.nickname,c.email,c.birthdate,c.phone_last4,
        EXISTS(SELECT 1 FROM user_contact_list_members m WHERE m.list_id=? AND m.user_contact_id=c.id) already_in_list
        FROM user_contacts c
        WHERE c.owner_user_id=? AND c.archived_at IS NULL AND (c.display_name LIKE ? OR c.nickname LIKE ? OR c.email LIKE ?)
        ORDER BY c.display_name LIMIT 10");
    $private->execute([$listId ?? 0, $ownerUserId, $like, $like, $like]);
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
        ];
    }

    $users = $pdo->prepare("SELECT u.id,u.public_id,COALESCE(pp.display_name,u.display_name,u.full_name,'Microgifter user') display_name,
        pp.avatar_url,pp.slug,
        EXISTS(SELECT 1 FROM user_contact_list_members m WHERE m.list_id=? AND m.contact_user_id=u.id) already_in_list
        FROM users u
        LEFT JOIN public_profiles pp ON pp.user_id=u.id AND pp.status NOT IN ('hidden','suspended')
        WHERE u.id<>? AND u.status='active' AND (u.display_name LIKE ? OR u.full_name LIKE ? OR pp.display_name LIKE ? OR pp.slug LIKE ?)
        ORDER BY display_name LIMIT 20");
    $users->execute([$listId ?? 0, $ownerUserId, $like, $like, $like, $like]);
    foreach ($users->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eligibility = mg_user_contact_list_eligibility_detail($pdo, $ownerUserId, (int) $row['id']);
        $results[] = [
            'type' => 'linked_user',
            'id' => (string) $row['public_id'],
            'display_name' => (string) $row['display_name'],
            'subtitle' => $eligibility['message'],
            'eligible' => (bool) $eligibility['eligible'],
            'eligibility_code' => (string) $eligibility['code'],
            'already_in_list' => (bool) $row['already_in_list'],
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'profile_slug' => (string) ($row['slug'] ?? ''),
        ];
    }
    return $results;
}

function mg_user_contact_find_linked_user(PDO $pdo, string $publicId): int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE public_id=? AND status=\'active\' LIMIT 1');
    $stmt->execute([$publicId]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id < 1) {
        throw new RuntimeException('User not found.');
    }
    return $id;
}

function mg_user_contact_find_private(PDO $pdo, int $ownerUserId, string $publicId): int
{
    $stmt = $pdo->prepare('SELECT id FROM user_contacts WHERE owner_user_id=? AND public_id=? AND archived_at IS NULL LIMIT 1');
    $stmt->execute([$ownerUserId, $publicId]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id < 1) {
        throw new RuntimeException('Private contact not found.');
    }
    return $id;
}

function mg_user_contact_add_member(PDO $pdo, int $ownerUserId, string $listPublicId, string $contactType, string $contactPublicId, array $input = []): array
{
    $list = mg_user_contact_list_load($pdo, $ownerUserId, $listPublicId);
    if ($list['is_archived']) {
        throw new RuntimeException('Archived lists cannot accept new contacts.');
    }
    $contactUserId = null;
    $userContactId = null;
    if ($contactType === 'linked_user') {
        $contactUserId = mg_user_contact_find_linked_user($pdo, $contactPublicId);
        $eligibility = mg_user_contact_list_eligibility_detail($pdo, $ownerUserId, $contactUserId);
        if (empty($eligibility['eligible'])) {
            throw new RuntimeException((string) $eligibility['message']);
        }
    } elseif ($contactType === 'private_contact') {
        $userContactId = mg_user_contact_find_private($pdo, $ownerUserId, $contactPublicId);
    } else {
        throw new InvalidArgumentException('Invalid contact type.');
    }

    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare('INSERT INTO user_contact_list_members (public_id,list_id,owner_user_id,contact_user_id,user_contact_id,relationship_type,relationship_label,private_notes,added_by,added_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    try {
        $stmt->execute([
            $publicId,
            (int) $list['id_internal'],
            $ownerUserId,
            $contactUserId,
            $userContactId,
            mg_contact_nullable_text($input['relationship_type'] ?? null, 64),
            mg_contact_nullable_text($input['relationship_label'] ?? null, 120),
            mg_contact_nullable_text($input['private_notes'] ?? null, 2000),
            $ownerUserId,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            throw new RuntimeException('This contact is already in the list.');
        }
        throw $e;
    }
    mg_audit('user_contact_list.member_added', 'user_contact_list_member', ['list_id' => $listPublicId, 'membership_id' => $publicId, 'contact_type' => $contactType], $ownerUserId);
    return ['membership_id' => $publicId, 'list_id' => $listPublicId];
}

function mg_user_contact_create(PDO $pdo, int $ownerUserId, array $input): array
{
    $firstName = mg_contact_nullable_text($input['first_name'] ?? null, 120);
    $lastName = mg_contact_nullable_text($input['last_name'] ?? null, 120);
    $displayName = mg_contact_text($input['display_name'] ?? trim(implode(' ', array_filter([$firstName, $lastName]))), 180);
    if ($displayName === '') {
        throw new InvalidArgumentException('Contact name is required.');
    }
    $email = mg_contact_nullable_text($input['email'] ?? null, 190);
    if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    $birthdate = mg_contact_nullable_text($input['birthdate'] ?? null, 10);
    if ($birthdate !== null) {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
        if (!$date || $date->format('Y-m-d') !== $birthdate || $date > new DateTimeImmutable('today')) {
            throw new InvalidArgumentException('Enter a valid birthday.');
        }
    }
    $budgetMin = ($input['budget_min'] ?? '') === '' ? null : max(0, (float) $input['budget_min']);
    $budgetMax = ($input['budget_max'] ?? '') === '' ? null : max(0, (float) $input['budget_max']);
    if ($budgetMin !== null && $budgetMax !== null && $budgetMax < $budgetMin) {
        throw new InvalidArgumentException('Maximum budget must be greater than or equal to minimum budget.');
    }
    $phone = mg_contact_phone_encrypt((string) ($input['phone'] ?? ''));
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare('INSERT INTO user_contacts (public_id,owner_user_id,first_name,middle_name,last_name,display_name,nickname,email,phone_ciphertext,phone_last4,phone_hash,birthdate,birth_year_visible,relationship_type,relationship_label,company,job_title,address_line_1,address_line_2,city,state_region,postal_code,country_code,notes,gift_preferences,interests,allergies_or_restrictions,preferred_merchants,preferred_categories,budget_min,budget_max,source,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'manual\',NOW(),NOW())');
    $stmt->execute([
        $publicId,$ownerUserId,$firstName,mg_contact_nullable_text($input['middle_name'] ?? null,120),$lastName,$displayName,
        mg_contact_nullable_text($input['nickname'] ?? null,120),$email,$phone['ciphertext'],$phone['last4'],$phone['hash'],$birthdate,
        !empty($input['birth_year_visible']) ? 1 : 0,mg_contact_nullable_text($input['relationship_type'] ?? null,64),
        mg_contact_nullable_text($input['relationship_label'] ?? null,120),mg_contact_nullable_text($input['company'] ?? null,180),
        mg_contact_nullable_text($input['job_title'] ?? null,180),mg_contact_nullable_text($input['address_line_1'] ?? null,190),
        mg_contact_nullable_text($input['address_line_2'] ?? null,190),mg_contact_nullable_text($input['city'] ?? null,120),
        mg_contact_nullable_text($input['state_region'] ?? null,120),mg_contact_nullable_text($input['postal_code'] ?? null,40),
        strtoupper((string) (mg_contact_nullable_text($input['country_code'] ?? null,2) ?? 'US')),mg_contact_nullable_text($input['notes'] ?? null,10000),
        mg_contact_nullable_text($input['gift_preferences'] ?? null,10000),mg_contact_nullable_text($input['interests'] ?? null,10000),
        mg_contact_nullable_text($input['allergies_or_restrictions'] ?? null,10000),mg_contact_nullable_text($input['preferred_merchants'] ?? null,10000),
        mg_contact_nullable_text($input['preferred_categories'] ?? null,10000),$budgetMin,$budgetMax,
    ]);
    mg_audit('user_contact.created', 'user_contact', ['contact_id' => $publicId, 'has_phone' => $phone['ciphertext'] !== null], $ownerUserId);
    return ['id' => $publicId, 'display_name' => $displayName, 'phone_masked' => mg_contact_phone_mask($phone['last4'])];
}
