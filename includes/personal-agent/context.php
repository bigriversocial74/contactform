<?php
declare(strict_types=1);

function mg_personal_agent_resolve_context(PDO $pdo, int $userId, string $type, string $publicId): array
{
    $type = strtolower(mg_personal_agent_text($type, 30));
    $publicId = mg_personal_agent_text($publicId, 80);
    if ($type === '' || $type === 'none' || $publicId === '') {
        return ['type'=>'none','id'=>'','name'=>'','internal'=>[],'details'=>[]];
    }
    if ($type === 'contact') {
        $stmt = $pdo->prepare("SELECT id,public_id,display_name,nickname,relationship_type,relationship_label,birthdate,interests,gift_preferences,
            allergies_or_restrictions,preferred_merchants,preferred_categories,budget_min,budget_max,city,state_region,phone_last4
            FROM user_contacts WHERE owner_user_id=? AND public_id=? AND archived_at IS NULL LIMIT 1");
        $stmt->execute([$userId,$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Contact not found.');
        return [
            'type'=>'contact','id'=>(string)$row['public_id'],'name'=>(string)$row['display_name'],
            'internal'=>['user_contact_id'=>(int)$row['id'],'contact_user_id'=>null,'list_id'=>null],
            'details'=>[
                'nickname'=>(string)($row['nickname'] ?? ''),
                'relationship'=>trim((string)($row['relationship_label'] ?: $row['relationship_type'] ?? '')),
                'birthdate'=>$row['birthdate'] ?: null,
                'interests'=>(string)($row['interests'] ?? ''),
                'gift_preferences'=>(string)($row['gift_preferences'] ?? ''),
                'restrictions'=>(string)($row['allergies_or_restrictions'] ?? ''),
                'preferred_merchants'=>(string)($row['preferred_merchants'] ?? ''),
                'preferred_categories'=>(string)($row['preferred_categories'] ?? ''),
                'budget_min'=>$row['budget_min'] !== null ? (float)$row['budget_min'] : null,
                'budget_max'=>$row['budget_max'] !== null ? (float)$row['budget_max'] : null,
                'location'=>trim(implode(', ',array_filter([(string)($row['city'] ?? ''),(string)($row['state_region'] ?? '')]))),
                'phone_masked'=>mg_contact_phone_mask($row['phone_last4'] ?? null),
            ],
        ];
    }
    if ($type === 'linked_user') {
        $stmt = $pdo->prepare("SELECT u.id,u.public_id,COALESCE(pp.display_name,u.display_name,u.full_name,'Contact') display_name,pp.avatar_url,pp.slug,
            MAX(m.relationship_type) relationship_type,MAX(m.relationship_label) relationship_label,GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') list_names
            FROM user_contact_list_members m INNER JOIN users u ON u.id=m.contact_user_id AND u.status='active'
            LEFT JOIN public_profiles pp ON pp.user_id=u.id AND pp.status='active'
            INNER JOIN user_contact_lists l ON l.id=m.list_id AND l.owner_user_id=m.owner_user_id
            WHERE m.owner_user_id=? AND u.public_id=? GROUP BY u.id LIMIT 1");
        $stmt->execute([$userId,$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Linked contact not found.');
        $eligibility = mg_user_contact_list_eligibility_detail($pdo, $userId, (int)$row['id']);
        if (empty($eligibility['eligible'])) throw new RuntimeException('Linked contact is no longer eligible.');
        return [
            'type'=>'linked_user','id'=>(string)$row['public_id'],'name'=>(string)$row['display_name'],
            'internal'=>['user_contact_id'=>null,'contact_user_id'=>(int)$row['id'],'list_id'=>null],
            'details'=>[
                'relationship'=>trim((string)($row['relationship_label'] ?: $row['relationship_type'] ?? '')),
                'list_names'=>(string)($row['list_names'] ?? ''),
                'avatar_url'=>(string)($row['avatar_url'] ?? ''),
                'profile_url'=>!empty($row['slug']) ? '/profile.php?u=' . rawurlencode((string)$row['slug']) : '',
                'privacy_note'=>'Only approved profile fields may be used. Private phone, email, address, and birthday data are not included.',
            ],
        ];
    }
    if ($type === 'list') {
        $list = mg_user_contact_list_load($pdo, $userId, $publicId);
        $members = mg_user_contact_list_members($pdo, $userId, (int)$list['id_internal']);
        return [
            'type'=>'list','id'=>(string)$list['id'],'name'=>(string)$list['name'],
            'internal'=>['user_contact_id'=>null,'contact_user_id'=>null,'list_id'=>(int)$list['id_internal']],
            'details'=>[
                'description'=>(string)($list['description'] ?? ''),
                'list_type'=>(string)$list['list_type'],
                'member_count'=>count($members),
                'members'=>array_map(static fn(array $member): array => [
                    'name'=>$member['display_name'],
                    'relationship'=>$member['relationship_label'] ?: $member['relationship_type'],
                    'birthdate'=>$member['birthdate'],
                    'gift_preferences'=>$member['gift_preferences'],
                    'interests'=>$member['interests'],
                    'budget_min'=>$member['budget_min'],
                    'budget_max'=>$member['budget_max'],
                ], array_slice($members, 0, 30)),
            ],
        ];
    }
    if ($type === 'plan') {
        foreach (mg_personal_agent_plans($pdo, $userId, 'all', 250) as $plan) {
            if ($plan['id'] === $publicId) {
                $stmt = $pdo->prepare('SELECT id FROM user_gifting_plans WHERE owner_user_id=? AND public_id=? LIMIT 1');
                $stmt->execute([$userId,$publicId]);
                return [
                    'type'=>'plan','id'=>$plan['id'],'name'=>$plan['title'],
                    'internal'=>['plan_id'=>(int)$stmt->fetchColumn(),'user_contact_id'=>null,'contact_user_id'=>null,'list_id'=>null],
                    'details'=>$plan,
                ];
            }
        }
        throw new RuntimeException('Gifting plan not found.');
    }
    throw new InvalidArgumentException('Unsupported personal agent context.');
}

function mg_personal_agent_public_context(array $context): array
{
    return [
        'type'=>(string)($context['type'] ?? 'none'),
        'id'=>(string)($context['id'] ?? ''),
        'name'=>(string)($context['name'] ?? ''),
        'details'=>is_array($context['details'] ?? null) ? $context['details'] : [],
    ];
}

