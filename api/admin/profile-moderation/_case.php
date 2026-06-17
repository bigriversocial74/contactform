<?php
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

function mg_profile_moderation_detail(PDO $pdo, array $user, string $casePublicId): array
{
    $access = mg_profile_moderation_access($user);
    if (!$access['view']) throw new RuntimeException('Permission denied.');

    $stmt = $pdo->prepare(
        "SELECT c.*,pp.public_id AS profile_public_id,pp.slug,pp.display_name,pp.headline,pp.bio,pp.avatar_url,pp.cover_url,pp.location_label,pp.website_url,pp.profile_type,pp.visibility,pp.status AS profile_status,pp.completion_score,pp.published_at,pp.created_at AS profile_created_at,pp.updated_at AS profile_updated_at,
                u.id AS owner_id,u.status AS owner_status,
                COALESCE(NULLIF(ou.display_name,''),NULLIF(ou.full_name,''),'Moderator') AS opened_by_name,
                COALESCE(NULLIF(au.display_name,''),NULLIF(au.full_name,''),'Moderator') AS assigned_name,
                (SELECT COUNT(*) FROM catalog_products cp WHERE cp.merchant_user_id=pp.user_id) AS products_total,
                (SELECT COUNT(*) FROM catalog_products cp WHERE cp.merchant_user_id=pp.user_id AND cp.status='published') AS products_published,
                (SELECT COUNT(*) FROM feed_posts fp WHERE fp.merchant_user_id=pp.user_id) AS posts_total,
                (SELECT COUNT(*) FROM feed_posts fp WHERE fp.merchant_user_id=pp.user_id AND fp.status IN ('published','promoted')) AS posts_published,
                (SELECT COUNT(*) FROM merchant_storefronts ms WHERE ms.merchant_user_id=pp.user_id) AS storefronts_total
         FROM profile_moderation_cases c
         INNER JOIN public_profiles pp ON pp.id=c.profile_id
         INNER JOIN users u ON u.id=pp.user_id
         LEFT JOIN users ou ON ou.id=c.opened_by_user_id
         LEFT JOIN users au ON au.id=c.assigned_user_id
         WHERE c.public_id=? LIMIT 1"
    );
    $stmt->execute([$casePublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Moderation case not found.');

    $linksStmt = $pdo->prepare('SELECT public_id,label,url,link_type,sort_order,is_active FROM public_profile_links WHERE profile_id=? ORDER BY sort_order,id');
    $linksStmt->execute([(int)$row['profile_id']]);
    $sectionsStmt = $pdo->prepare('SELECT public_id,section_type,title,body,sort_order,is_active FROM public_profile_sections WHERE profile_id=? ORDER BY sort_order,id');
    $sectionsStmt->execute([(int)$row['profile_id']]);

    $actionsStmt = $pdo->prepare(
        "SELECT ma.public_id,ma.actor_type,ma.action_type,ma.reason_code,ma.reason_text,ma.previous_profile_status,ma.resulting_profile_status,ma.created_at,
                COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),CASE WHEN ma.actor_type='owner' THEN 'Profile owner' ELSE 'System' END) AS actor_name
         FROM profile_moderation_actions ma
         LEFT JOIN users u ON u.id=ma.actor_user_id
         WHERE ma.case_id=? ORDER BY ma.created_at DESC,ma.id DESC"
    );
    $actionsStmt->execute([(int)$row['id']]);

    $appealsStmt = $pdo->prepare(
        "SELECT pa.public_id,pa.status,pa.statement,pa.decision_reason,pa.submitted_at,pa.reviewed_at,
                COALESCE(NULLIF(ru.display_name,''),NULLIF(ru.full_name,''),'Moderator') AS reviewer_name
         FROM profile_moderation_appeals pa
         LEFT JOIN users ru ON ru.id=pa.reviewed_by_user_id
         WHERE pa.case_id=? ORDER BY pa.submitted_at DESC,pa.id DESC"
    );
    $appealsStmt->execute([(int)$row['id']]);

    $case = mg_profile_moderation_public_case_row($row + ['action_count' => 0, 'appeal_status' => null]);
    $case['opened_by'] = $row['opened_by_user_id'] !== null ? ['id' => (int)$row['opened_by_user_id'], 'name' => (string)$row['opened_by_name']] : null;
    $case['evidence'] = mg_profile_moderation_decode_json($row['evidence_json']);

    $profile = [
        'id' => (string)$row['profile_public_id'],
        'slug' => (string)$row['slug'],
        'display_name' => (string)$row['display_name'],
        'headline' => $row['headline'] !== null ? (string)$row['headline'] : null,
        'biography' => $row['bio'] !== null ? (string)$row['bio'] : null,
        'avatar_url' => $row['avatar_url'] !== null ? (string)$row['avatar_url'] : null,
        'cover_url' => $row['cover_url'] !== null ? (string)$row['cover_url'] : null,
        'location_label' => $row['location_label'] !== null ? (string)$row['location_label'] : null,
        'website_url' => $row['website_url'] !== null ? (string)$row['website_url'] : null,
        'profile_type' => (string)$row['profile_type'],
        'visibility' => (string)$row['visibility'],
        'status' => (string)$row['profile_status'],
        'completion_score' => (int)$row['completion_score'],
        'published_at' => $row['published_at'] !== null ? (string)$row['published_at'] : null,
        'created_at' => (string)$row['profile_created_at'],
        'updated_at' => $row['profile_updated_at'] !== null ? (string)$row['profile_updated_at'] : null,
        'public_url' => '/profile.php?slug=' . rawurlencode((string)$row['slug']),
        'preview_url' => '/profile.php?slug=' . rawurlencode((string)$row['slug']) . '&preview=1',
        'owner' => $access['users'] ? ['id' => (int)$row['owner_id'], 'status' => (string)$row['owner_status']] : ['status' => (string)$row['owner_status']],
        'links' => $linksStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'sections' => $sectionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'content' => [
            'storefronts' => (int)$row['storefronts_total'],
            'products_total' => (int)$row['products_total'],
            'products_published' => (int)$row['products_published'],
            'posts_total' => (int)$row['posts_total'],
            'posts_published' => (int)$row['posts_published'],
        ],
    ];

    $actions = array_map(static fn(array $action): array => [
        'id' => (string)$action['public_id'],
        'actor_type' => (string)$action['actor_type'],
        'actor_name' => (string)$action['actor_name'],
        'type' => (string)$action['action_type'],
        'reason_code' => $action['reason_code'] !== null ? (string)$action['reason_code'] : null,
        'reason' => $action['reason_text'] !== null ? (string)$action['reason_text'] : null,
        'previous_profile_status' => $action['previous_profile_status'] !== null ? (string)$action['previous_profile_status'] : null,
        'resulting_profile_status' => $action['resulting_profile_status'] !== null ? (string)$action['resulting_profile_status'] : null,
        'created_at' => (string)$action['created_at'],
    ], $actionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $appeals = array_map(static fn(array $appeal): array => [
        'id' => (string)$appeal['public_id'],
        'status' => (string)$appeal['status'],
        'statement' => (string)$appeal['statement'],
        'decision_reason' => $appeal['decision_reason'] !== null ? (string)$appeal['decision_reason'] : null,
        'reviewer_name' => $appeal['reviewed_at'] !== null ? (string)$appeal['reviewer_name'] : null,
        'submitted_at' => (string)$appeal['submitted_at'],
        'reviewed_at' => $appeal['reviewed_at'] !== null ? (string)$appeal['reviewed_at'] : null,
    ], $appealsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    return [
        'access' => $access,
        'case' => $case,
        'profile' => $profile,
        'actions' => $actions,
        'appeals' => $appeals,
        'options' => [
            'actions' => mg_profile_moderation_actions(),
            'reason_codes' => mg_profile_moderation_reason_codes(),
            'priorities' => mg_profile_moderation_priorities(),
            'restore_statuses' => ['active', 'draft', 'hidden'],
        ],
    ];
}
