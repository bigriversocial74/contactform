<?php
declare(strict_types=1);

/**
 * Public Donations Phase 6 merchant Community Support dashboard.
 *
 * Read-only reporting over canonical assignment, donation attribution, Wallet,
 * PPPM, and Microgift lifecycle records. Every query is merchant scoped and
 * downstream recipient identities are intentionally never selected.
 */

function mg_community_support_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $required = [
        'campaigns',
        'reward_templates',
        'campaign_community_assignments',
        'campaign_donation_operations',
        'campaign_donation_batches',
        'campaign_donation_rewards',
        'wallet_items',
        'pppm_items',
        'microgift_instances',
        'users',
        'public_profiles',
        'user_roles',
        'roles',
    ];

    try {
        $placeholders = implode(',', array_fill(0, count($required), '?'));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$placeholders})"
        );
        $stmt->execute($required);
        return $cache[$key] = (int)$stmt->fetchColumn() === count($required);
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_community_support_safe_profile_url(array $row): ?string
{
    $slug = trim((string)($row['profile_slug'] ?? ''));
    $visible = $slug !== ''
        && (string)($row['profile_status'] ?? '') === 'active'
        && in_array((string)($row['profile_visibility'] ?? ''), ['public', 'unlisted'], true);

    return $visible ? '/profile.php?slug=' . rawurlencode($slug) : null;
}

function mg_community_support_display_name(array $row): string
{
    $name = trim((string)($row['display_name'] ?? ''));
    return $name !== '' ? mb_substr($name, 0, 180) : 'Community member';
}

function mg_community_support_reward_sql(): array
{
    $regifted = "(
        wallet.user_id<>reward.original_community_user_id
        OR pppm.owner_user_id<>reward.original_community_user_id
        OR microgift.owner_user_id<>reward.original_community_user_id
    )";
    $claimed = "(
        wallet.claimed_at IS NOT NULL OR microgift.claimed_at IS NOT NULL
        OR wallet.status='claimed'
        OR pppm.status IN ('claim_pending','verified')
        OR microgift.status IN ('claim_pending','claimed','redeemable')
    )";
    $redeemed = "(
        wallet.redeemed_at IS NOT NULL OR microgift.redeemed_at IS NOT NULL
        OR wallet.status='redeemed' OR pppm.status='redeemed' OR microgift.status='redeemed'
    )";
    $expired = "(
        wallet.status='expired' OR pppm.status='expired' OR microgift.status='expired'
        OR (wallet.expires_at IS NOT NULL AND wallet.expires_at<=NOW())
        OR (pppm.expires_at IS NOT NULL AND pppm.expires_at<=NOW())
        OR (microgift.expires_at IS NOT NULL AND microgift.expires_at<=NOW())
    )";
    $cancelled = "(
        wallet.status='cancelled'
        OR pppm.status IN ('cancelled','voided','refunded')
        OR microgift.status IN ('cancelled','revoked','replaced')
    )";
    $available = "(
        reward.status='allocated'
        AND NOT {$claimed}
        AND NOT {$redeemed}
        AND NOT {$expired}
        AND NOT {$cancelled}
    )";

    return compact('regifted', 'claimed', 'redeemed', 'expired', 'cancelled', 'available');
}

function mg_community_support_reward_aggregate_sql(string $groupColumn): string
{
    $allowed = ['reward.campaign_id', 'reward.original_community_user_id', 'reward.batch_id'];
    if (!in_array($groupColumn, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported Community Support aggregation.');
    }

    $flags = mg_community_support_reward_sql();

    return "SELECT {$groupColumn} AS group_id,
                   COUNT(*) AS gross_allocated,
                   COALESCE(SUM(reward.status='recalled'),0) AS recalled,
                   COALESCE(SUM({$flags['available']}),0) AS available,
                   COALESCE(SUM(reward.status='allocated' AND {$flags['regifted']}),0) AS regifted,
                   COALESCE(SUM(reward.status='allocated' AND {$flags['claimed']}),0) AS claimed,
                   COALESCE(SUM(reward.status='allocated' AND {$flags['redeemed']}),0) AS redeemed,
                   COALESCE(SUM(reward.status='allocated' AND {$flags['expired']}),0) AS expired,
                   COALESCE(SUM(reward.value_cents_snapshot),0) AS gross_stated_value_cents,
                   COALESCE(SUM(CASE WHEN reward.status='recalled' THEN reward.value_cents_snapshot ELSE 0 END),0) AS recalled_stated_value_cents,
                   MIN(reward.currency_snapshot) AS currency,
                   COUNT(DISTINCT reward.currency_snapshot) AS currency_count
              FROM campaign_donation_rewards reward
              INNER JOIN campaigns campaign
                      ON campaign.id=reward.campaign_id
                     AND campaign.merchant_user_id=reward.merchant_user_id
                     AND campaign.campaign_type='public_donation'
              INNER JOIN wallet_items wallet ON wallet.id=reward.wallet_item_id
              INNER JOIN pppm_items pppm ON pppm.id=reward.pppm_item_id
              INNER JOIN microgift_instances microgift ON microgift.id=reward.microgift_instance_id
             WHERE reward.merchant_user_id=?
             GROUP BY {$groupColumn}";
}

function mg_community_support_reward_map(PDO $pdo, int $merchantId, string $groupColumn): array
{
    $stmt = $pdo->prepare(mg_community_support_reward_aggregate_sql($groupColumn));
    $stmt->execute([$merchantId]);
    $map = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $gross = (int)$row['gross_allocated'];
        $recalled = (int)$row['recalled'];
        $grossValue = (int)$row['gross_stated_value_cents'];
        $recalledValue = (int)$row['recalled_stated_value_cents'];
        $map[(int)$row['group_id']] = [
            'gross_allocated' => $gross,
            'recalled' => $recalled,
            'net_allocated' => max(0, $gross - $recalled),
            'available' => (int)$row['available'],
            'regifted' => (int)$row['regifted'],
            'claimed' => (int)$row['claimed'],
            'redeemed' => (int)$row['redeemed'],
            'expired' => (int)$row['expired'],
            'gross_stated_value_cents' => $grossValue,
            'recalled_stated_value_cents' => $recalledValue,
            'net_stated_value_cents' => max(0, $grossValue - $recalledValue),
            'currency' => (int)$row['currency_count'] === 1 ? (string)$row['currency'] : null,
            'mixed_currency' => (int)$row['currency_count'] > 1,
        ];
    }

    return $map;
}

function mg_community_support_empty_metrics(): array
{
    return [
        'gross_allocated' => 0,
        'recalled' => 0,
        'net_allocated' => 0,
        'available' => 0,
        'regifted' => 0,
        'claimed' => 0,
        'redeemed' => 0,
        'expired' => 0,
        'gross_stated_value_cents' => 0,
        'recalled_stated_value_cents' => 0,
        'net_stated_value_cents' => 0,
        'currency' => null,
        'mixed_currency' => false,
    ];
}

function mg_community_support_campaigns(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT id,public_id,public_slug,title,status,starts_at,ends_at,quantity_limit,issued_count,updated_at
           FROM campaigns
          WHERE merchant_user_id=? AND campaign_type='public_donation' AND status<>'archived'
          ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END,
                   updated_at DESC,id DESC
          LIMIT 200"
    );
    $stmt->execute([$merchantId]);
    $campaignRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assignmentStmt = $pdo->prepare(
        "SELECT campaign_id,COUNT(*) AS assignments,
                COALESCE(SUM(status='active'),0) AS active_assignments,
                COALESCE(SUM(status='paused'),0) AS paused_assignments,
                COALESCE(SUM(status='removed'),0) AS removed_assignments,
                COUNT(DISTINCT community_user_id) AS community_accounts
           FROM campaign_community_assignments
          WHERE merchant_user_id=?
          GROUP BY campaign_id"
    );
    $assignmentStmt->execute([$merchantId]);
    $assignmentMap = [];
    foreach ($assignmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $assignmentMap[(int)$row['campaign_id']] = $row;
    }

    $operationStmt = $pdo->prepare(
        "SELECT campaign_id,
                COALESCE(SUM(status='failed'),0) AS failed_operations,
                MAX(created_at) AS last_operation_at
           FROM campaign_donation_operations
          WHERE merchant_user_id=?
          GROUP BY campaign_id"
    );
    $operationStmt->execute([$merchantId]);
    $operationMap = [];
    foreach ($operationStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $operationMap[(int)$row['campaign_id']] = $row;
    }

    $rewardMap = mg_community_support_reward_map($pdo, $merchantId, 'reward.campaign_id');
    $campaigns = [];

    foreach ($campaignRows as $row) {
        $id = (int)$row['id'];
        $assignment = $assignmentMap[$id] ?? [];
        $operation = $operationMap[$id] ?? [];
        $metrics = $rewardMap[$id] ?? mg_community_support_empty_metrics();
        $limit = $row['quantity_limit'] !== null ? (int)$row['quantity_limit'] : null;
        $issued = (int)$row['issued_count'];
        $remaining = $limit !== null ? max(0, $limit - $issued) : null;
        $slug = trim((string)$row['public_slug']);
        $publicRef = $slug !== '' ? $slug : (string)$row['public_id'];

        $campaigns[] = [
            'id' => (string)$row['public_id'],
            'slug' => $slug !== '' ? $slug : null,
            'title' => (string)$row['title'],
            'status' => (string)$row['status'],
            'starts_at' => $row['starts_at'] !== null ? (string)$row['starts_at'] : null,
            'ends_at' => $row['ends_at'] !== null ? (string)$row['ends_at'] : null,
            'quantity_limit' => $limit,
            'issued_count' => $issued,
            'remaining_inventory' => $remaining,
            'assignments' => (int)($assignment['assignments'] ?? 0),
            'active_assignments' => (int)($assignment['active_assignments'] ?? 0),
            'paused_assignments' => (int)($assignment['paused_assignments'] ?? 0),
            'removed_assignments' => (int)($assignment['removed_assignments'] ?? 0),
            'community_accounts' => (int)($assignment['community_accounts'] ?? 0),
            'failed_operations' => (int)($operation['failed_operations'] ?? 0),
            'last_operation_at' => ($operation['last_operation_at'] ?? null) !== null ? (string)$operation['last_operation_at'] : null,
            'metrics' => $metrics,
            'campaign_url' => '/merchant-campaigns.php?campaign_id=' . rawurlencode((string)$row['public_id']),
            'public_url' => '/public-donations.php?campaign=' . rawurlencode($publicRef),
        ];
    }

    return $campaigns;
}

function mg_community_support_accounts(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT assignment.community_user_id,
                profile.public_id AS community_account_id,
                CASE
                    WHEN profile.status='active' AND profile.visibility IN ('public','unlisted')
                        THEN COALESCE(NULLIF(profile.display_name,''),NULLIF(user.display_name,''),user.full_name)
                    ELSE COALESCE(NULLIF(user.display_name,''),user.full_name)
                END AS display_name,
                profile.slug AS profile_slug,profile.status AS profile_status,profile.visibility AS profile_visibility,
                user.status AS account_status,
                COUNT(DISTINCT assignment.campaign_id) AS campaign_count,
                COALESCE(SUM(assignment.status='active'),0) AS active_assignments,
                COALESCE(SUM(assignment.status='paused'),0) AS paused_assignments,
                COALESCE(SUM(assignment.status='removed'),0) AS removed_assignments,
                MAX(assignment.last_allocated_at) AS last_allocated_at,
                MAX(assignment.updated_at) AS last_assignment_activity_at,
                GROUP_CONCAT(DISTINCT campaign.title ORDER BY campaign.title SEPARATOR ' • ') AS campaign_titles,
                MAX(EXISTS (
                    SELECT 1
                      FROM user_roles role_link
                      INNER JOIN roles role ON role.id=role_link.role_id AND role.slug='community'
                     WHERE role_link.user_id=user.id
                )) AS has_community_role
           FROM campaign_community_assignments assignment
           INNER JOIN campaigns campaign
                   ON campaign.id=assignment.campaign_id
                  AND campaign.merchant_user_id=assignment.merchant_user_id
                  AND campaign.campaign_type='public_donation'
           INNER JOIN users user ON user.id=assignment.community_user_id
           INNER JOIN public_profiles profile ON profile.user_id=user.id
          WHERE assignment.merchant_user_id=?
          GROUP BY assignment.community_user_id,user.id,profile.public_id,profile.display_name,user.display_name,user.full_name,
                   profile.slug,profile.status,profile.visibility,user.status
          ORDER BY last_assignment_activity_at DESC,display_name ASC
          LIMIT 500"
    );
    $stmt->execute([$merchantId]);
    $rewardMap = mg_community_support_reward_map($pdo, $merchantId, 'reward.original_community_user_id');
    $accounts = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userId = (int)$row['community_user_id'];
        $accounts[] = [
            'id' => (string)$row['community_account_id'],
            'display_name' => mg_community_support_display_name($row),
            'account_status' => (string)$row['account_status'],
            'has_community_role' => (bool)$row['has_community_role'],
            'campaign_count' => (int)$row['campaign_count'],
            'campaign_titles' => trim((string)$row['campaign_titles']),
            'active_assignments' => (int)$row['active_assignments'],
            'paused_assignments' => (int)$row['paused_assignments'],
            'removed_assignments' => (int)$row['removed_assignments'],
            'last_allocated_at' => $row['last_allocated_at'] !== null ? (string)$row['last_allocated_at'] : null,
            'last_activity_at' => $row['last_assignment_activity_at'] !== null ? (string)$row['last_assignment_activity_at'] : null,
            'metrics' => $rewardMap[$userId] ?? mg_community_support_empty_metrics(),
            'public_profile_url' => mg_community_support_safe_profile_url($row),
            'dashboard_url' => '/merchant-community-support.php?tab=accounts&account=' . rawurlencode((string)$row['community_account_id']),
        ];
    }

    return $accounts;
}

function mg_community_support_batches(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT batch.id,batch.public_id,batch.quantity,batch.recalled_quantity,batch.stated_value_cents,
                batch.currency,batch.status,batch.created_at,
                campaign.public_id AS campaign_public_id,campaign.title AS campaign_title,
                template.public_id AS template_public_id,template.title AS template_title,
                assignment.public_id AS assignment_public_id,
                profile.public_id AS community_account_id,
                CASE
                    WHEN profile.status='active' AND profile.visibility IN ('public','unlisted')
                        THEN COALESCE(NULLIF(profile.display_name,''),NULLIF(user.display_name,''),user.full_name)
                    ELSE COALESCE(NULLIF(user.display_name,''),user.full_name)
                END AS display_name,
                profile.slug AS profile_slug,profile.status AS profile_status,profile.visibility AS profile_visibility
           FROM campaign_donation_batches batch
           INNER JOIN campaigns campaign
                   ON campaign.id=batch.campaign_id
                  AND campaign.merchant_user_id=batch.merchant_user_id
                  AND campaign.campaign_type='public_donation'
           INNER JOIN reward_templates template ON template.id=batch.reward_template_id
           INNER JOIN campaign_community_assignments assignment ON assignment.id=batch.assignment_id
           INNER JOIN users user ON user.id=batch.community_user_id
           INNER JOIN public_profiles profile ON profile.user_id=user.id
          WHERE batch.merchant_user_id=?
          ORDER BY batch.created_at DESC,batch.id DESC
          LIMIT 250"
    );
    $stmt->execute([$merchantId]);
    $rewardMap = mg_community_support_reward_map($pdo, $merchantId, 'reward.batch_id');
    $batches = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['id'];
        $metrics = $rewardMap[$id] ?? mg_community_support_empty_metrics();
        $quantity = (int)$row['quantity'];
        $recalled = (int)$row['recalled_quantity'];
        $batches[] = [
            'id' => (string)$row['public_id'],
            'quantity' => $quantity,
            'recalled_quantity' => $recalled,
            'net_quantity' => max(0, $quantity - $recalled),
            'stated_value_cents' => (int)$row['stated_value_cents'],
            'net_stated_value_cents' => (int)$metrics['net_stated_value_cents'],
            'currency' => (string)$row['currency'],
            'status' => (string)$row['status'],
            'created_at' => (string)$row['created_at'],
            'campaign' => [
                'id' => (string)$row['campaign_public_id'],
                'title' => (string)$row['campaign_title'],
                'url' => '/merchant-campaigns.php?campaign_id=' . rawurlencode((string)$row['campaign_public_id']),
            ],
            'reward_template' => [
                'id' => (string)$row['template_public_id'],
                'title' => (string)$row['template_title'],
            ],
            'community' => [
                'id' => (string)$row['community_account_id'],
                'display_name' => mg_community_support_display_name($row),
                'assignment_id' => (string)$row['assignment_public_id'],
                'public_profile_url' => mg_community_support_safe_profile_url($row),
                'assignment_url' => '/merchant-campaigns.php?campaign_id=' . rawurlencode((string)$row['campaign_public_id'])
                    . '&community_assignment=' . rawurlencode((string)$row['assignment_public_id']),
            ],
            'metrics' => $metrics,
            'batch_url' => '/merchant-campaigns.php?donation_batch=' . rawurlencode((string)$row['public_id']),
        ];
    }

    return $batches;
}

function mg_community_support_activity(PDO $pdo, int $merchantId): array
{
    $operationStmt = $pdo->prepare(
        "SELECT operation.public_id,operation.operation_kind,operation.status,operation.completed_quantity,
                operation.total_stated_value_cents,operation.currency,operation.created_at,operation.completed_at,
                campaign.public_id AS campaign_public_id,campaign.title AS campaign_title
           FROM campaign_donation_operations operation
           INNER JOIN campaigns campaign
                   ON campaign.id=operation.campaign_id
                  AND campaign.merchant_user_id=operation.merchant_user_id
                  AND campaign.campaign_type='public_donation'
          WHERE operation.merchant_user_id=?
          ORDER BY operation.created_at DESC,operation.id DESC
          LIMIT 150"
    );
    $operationStmt->execute([$merchantId]);
    $activity = [];

    foreach ($operationStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kind = (string)$row['operation_kind'];
        $activity[] = [
            'id' => 'operation:' . (string)$row['public_id'],
            'type' => $kind,
            'status' => (string)$row['status'],
            'title' => $kind === 'recall' ? 'Donation rewards recalled' : 'Donation rewards allocated',
            'detail' => number_format((int)$row['completed_quantity']) . ' reward unit' . ((int)$row['completed_quantity'] === 1 ? '' : 's'),
            'quantity' => (int)$row['completed_quantity'],
            'value_cents' => (int)$row['total_stated_value_cents'],
            'currency' => (string)$row['currency'],
            'occurred_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : (string)$row['created_at'],
            'campaign' => [
                'id' => (string)$row['campaign_public_id'],
                'title' => (string)$row['campaign_title'],
                'url' => '/merchant-campaigns.php?campaign_id=' . rawurlencode((string)$row['campaign_public_id']),
            ],
        ];
    }

    $assignmentStmt = $pdo->prepare(
        "SELECT assignment.public_id,assignment.status,assignment.updated_at,
                campaign.public_id AS campaign_public_id,campaign.title AS campaign_title,
                profile.public_id AS community_account_id,
                CASE
                    WHEN profile.status='active' AND profile.visibility IN ('public','unlisted')
                        THEN COALESCE(NULLIF(profile.display_name,''),NULLIF(user.display_name,''),user.full_name)
                    ELSE COALESCE(NULLIF(user.display_name,''),user.full_name)
                END AS display_name
           FROM campaign_community_assignments assignment
           INNER JOIN campaigns campaign
                   ON campaign.id=assignment.campaign_id
                  AND campaign.merchant_user_id=assignment.merchant_user_id
                  AND campaign.campaign_type='public_donation'
           INNER JOIN users user ON user.id=assignment.community_user_id
           INNER JOIN public_profiles profile ON profile.user_id=user.id
          WHERE assignment.merchant_user_id=?
          ORDER BY assignment.updated_at DESC,assignment.id DESC
          LIMIT 150"
    );
    $assignmentStmt->execute([$merchantId]);

    foreach ($assignmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (string)$row['status'];
        $activity[] = [
            'id' => 'assignment:' . (string)$row['public_id'],
            'type' => 'assignment',
            'status' => $status,
            'title' => match ($status) {
                'active' => 'Community assignment active',
                'paused' => 'Community assignment paused',
                default => 'Community assignment removed',
            },
            'detail' => mg_community_support_display_name($row),
            'quantity' => null,
            'value_cents' => null,
            'currency' => null,
            'occurred_at' => (string)$row['updated_at'],
            'campaign' => [
                'id' => (string)$row['campaign_public_id'],
                'title' => (string)$row['campaign_title'],
                'url' => '/merchant-campaigns.php?campaign_id=' . rawurlencode((string)$row['campaign_public_id']),
            ],
            'community_account_id' => (string)$row['community_account_id'],
        ];
    }

    usort($activity, static fn(array $a, array $b): int => strcmp((string)$b['occurred_at'], (string)$a['occurred_at']));
    return array_slice($activity, 0, 200);
}

function mg_community_support_currency_summary(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT reward.currency_snapshot AS currency,
                COALESCE(SUM(reward.value_cents_snapshot),0) AS gross_cents,
                COALESCE(SUM(CASE WHEN reward.status='recalled' THEN reward.value_cents_snapshot ELSE 0 END),0) AS recalled_cents
           FROM campaign_donation_rewards reward
           INNER JOIN campaigns campaign
                   ON campaign.id=reward.campaign_id
                  AND campaign.merchant_user_id=reward.merchant_user_id
                  AND campaign.campaign_type='public_donation'
          WHERE reward.merchant_user_id=?
          GROUP BY reward.currency_snapshot
          ORDER BY reward.currency_snapshot"
    );
    $stmt->execute([$merchantId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $gross = (int)$row['gross_cents'];
        $recalled = (int)$row['recalled_cents'];
        $rows[] = [
            'currency' => (string)$row['currency'],
            'gross_cents' => $gross,
            'recalled_cents' => $recalled,
            'net_cents' => max(0, $gross - $recalled),
        ];
    }
    return $rows;
}

function mg_community_support_attention(array $campaigns, array $accounts, array $activity): array
{
    $attention = [];
    $now = time();
    $endingSoon = $now + (14 * 86400);

    foreach ($campaigns as $campaign) {
        $limit = $campaign['quantity_limit'];
        $remaining = $campaign['remaining_inventory'];
        if ($limit !== null && $remaining !== null) {
            $threshold = max(5, (int)ceil($limit * 0.10));
            if ($remaining <= $threshold) {
                $attention[] = [
                    'type' => 'low_inventory',
                    'severity' => $remaining === 0 ? 'high' : 'medium',
                    'title' => 'Low campaign inventory',
                    'detail' => $campaign['title'] . ' has ' . number_format($remaining) . ' reward unit' . ($remaining === 1 ? '' : 's') . ' remaining.',
                    'href' => $campaign['campaign_url'],
                ];
            }
        }

        $endsAt = $campaign['ends_at'] !== null ? strtotime((string)$campaign['ends_at']) : false;
        if ($endsAt !== false && $endsAt >= $now && $endsAt <= $endingSoon && in_array($campaign['status'], ['active', 'paused'], true)) {
            $days = max(0, (int)ceil(($endsAt - $now) / 86400));
            $attention[] = [
                'type' => 'ending_soon',
                'severity' => 'medium',
                'title' => 'Campaign nearing end date',
                'detail' => $campaign['title'] . ' ends in ' . number_format($days) . ' day' . ($days === 1 ? '' : 's') . '.',
                'href' => $campaign['campaign_url'],
            ];
        }
    }

    foreach ($accounts as $account) {
        if (!$account['has_community_role'] && ($account['active_assignments'] + $account['paused_assignments']) > 0) {
            $attention[] = [
                'type' => 'role_removed',
                'severity' => 'high',
                'title' => 'Community role removed',
                'detail' => $account['display_name'] . ' still has an active or paused campaign assignment.',
                'href' => $account['dashboard_url'],
            ];
        }
        if ((int)$account['metrics']['available'] >= 10 && (int)$account['metrics']['redeemed'] === 0) {
            $attention[] = [
                'type' => 'untouched_balance',
                'severity' => 'medium',
                'title' => 'Large untouched reward balance',
                'detail' => $account['display_name'] . ' has ' . number_format((int)$account['metrics']['available']) . ' available rewards and no redemptions.',
                'href' => $account['dashboard_url'],
            ];
        }
    }

    foreach ($activity as $item) {
        if ($item['type'] === 'allocation' || $item['type'] === 'recall') {
            if ($item['status'] === 'failed') {
                $attention[] = [
                    'type' => 'failed_operation',
                    'severity' => 'high',
                    'title' => 'Donation operation failed',
                    'detail' => $item['campaign']['title'] . ' has a failed ' . $item['type'] . ' operation.',
                    'href' => $item['campaign']['url'],
                ];
            }
        }
    }

    $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($attention, static fn(array $a, array $b): int => ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9));
    return array_slice($attention, 0, 50);
}

function mg_community_support_summary(array $campaigns, array $accounts, array $currencySummary): array
{
    $summary = [
        'campaigns' => count($campaigns),
        'community_accounts' => count($accounts),
        'gross_allocated' => 0,
        'recalled' => 0,
        'net_allocated' => 0,
        'available' => 0,
        'regifted' => 0,
        'claimed' => 0,
        'redeemed' => 0,
        'remaining_inventory' => 0,
        'limited_campaigns' => 0,
        'stated_value_by_currency' => $currencySummary,
    ];

    foreach ($campaigns as $campaign) {
        foreach (['gross_allocated', 'recalled', 'net_allocated', 'available', 'regifted', 'claimed', 'redeemed'] as $key) {
            $summary[$key] += (int)$campaign['metrics'][$key];
        }
        if ($campaign['remaining_inventory'] !== null) {
            $summary['remaining_inventory'] += (int)$campaign['remaining_inventory'];
            $summary['limited_campaigns']++;
        }
    }

    return $summary;
}

function mg_community_support_dashboard(PDO $pdo, int $merchantId): array
{
    if ($merchantId < 1) {
        throw new InvalidArgumentException('A valid merchant is required.');
    }
    if (!mg_community_support_schema_ready($pdo)) {
        throw new RuntimeException('Public Donations Community Support schema is incomplete.');
    }

    $campaigns = mg_community_support_campaigns($pdo, $merchantId);
    $accounts = mg_community_support_accounts($pdo, $merchantId);
    $batches = mg_community_support_batches($pdo, $merchantId);
    $activity = mg_community_support_activity($pdo, $merchantId);
    $currencySummary = mg_community_support_currency_summary($pdo, $merchantId);

    return [
        'summary' => mg_community_support_summary($campaigns, $accounts, $currencySummary),
        'attention' => mg_community_support_attention($campaigns, $accounts, $activity),
        'campaigns' => $campaigns,
        'community_accounts' => $accounts,
        'donation_batches' => $batches,
        'activity' => $activity,
        'privacy' => [
            'original_community_accounts_only' => true,
            'downstream_recipient_identity_exposed' => false,
            'merchant_scoped' => true,
        ],
        'generated_at' => gmdate('c'),
    ];
}
