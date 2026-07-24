<?php
declare(strict_types=1);

/**
 * Public Donations Phase 10 reconciliation engine.
 *
 * Detection is intentionally broader than repair. Missing attribution, broken
 * canonical links, and ownership disagreements are report-only because the
 * reconciler must never invent ownership or historical lifecycle evidence.
 */

const MG_PUBLIC_DONATIONS_REPAIR_MODES = [
    'counters',
    'batch_totals',
    'recalled_visibility',
    'assignments',
];

function mg_public_donations_reconcile_table(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $stmt->execute([$table]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function mg_public_donations_reconcile_schema_ready(PDO $pdo): bool
{
    foreach ([
        'campaigns', 'reward_templates', 'campaign_donation_operations',
        'campaign_donation_batches', 'campaign_donation_rewards',
        'campaign_community_assignments', 'wallet_items', 'pppm_items',
        'microgift_instances', 'microgift_inbox_items', 'users', 'user_roles', 'roles',
    ] as $table) {
        if (!mg_public_donations_reconcile_table($pdo, $table)) return false;
    }
    return true;
}

function mg_public_donations_reconcile_uuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
}

function mg_public_donations_reconcile_limit(mixed $value): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT);
    return max(1, min($limit === false ? 100 : (int)$limit, 1000));
}

function mg_public_donations_reconcile_reference(mixed $value, string $label): ?string
{
    $reference = strtolower(trim((string)$value));
    if ($reference === '') return null;
    if (strlen($reference) > 190 || preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $reference) !== 1) {
        throw new InvalidArgumentException('Invalid ' . $label . ' reference.');
    }
    return $reference;
}

/** @return list<string> */
function mg_public_donations_reconcile_modes(mixed $value): array
{
    if ($value === null || $value === false || trim((string)$value) === '') return [];
    $parts = preg_split('/[\s,;]+/', strtolower(trim((string)$value))) ?: [];
    $modes = [];
    foreach ($parts as $mode) {
        if ($mode === 'safe') {
            foreach (MG_PUBLIC_DONATIONS_REPAIR_MODES as $safe) $modes[$safe] = true;
            continue;
        }
        if (!in_array($mode, MG_PUBLIC_DONATIONS_REPAIR_MODES, true)) {
            throw new InvalidArgumentException('Unsupported repair mode: ' . $mode);
        }
        $modes[$mode] = true;
    }
    return array_keys($modes);
}

function mg_public_donations_reconcile_filters(array $options): array
{
    $merchantId = filter_var($options['merchant_id'] ?? null, FILTER_VALIDATE_INT);
    if ($merchantId === false || $merchantId < 1) {
        throw new InvalidArgumentException('A positive merchant ID is required.');
    }
    return [
        'merchant_id' => (int)$merchantId,
        'campaign' => mg_public_donations_reconcile_reference($options['campaign'] ?? null, 'campaign'),
        'operation' => mg_public_donations_reconcile_reference($options['operation'] ?? null, 'operation'),
        'limit' => mg_public_donations_reconcile_limit($options['limit'] ?? 100),
    ];
}

function mg_public_donations_reconcile_scope_sql(array $filters, string $rewardAlias = 'reward'): array
{
    $where = ["{$rewardAlias}.merchant_user_id=?"];
    $params = [(int)$filters['merchant_id']];
    if ($filters['campaign'] !== null) {
        $where[] = '(campaign.public_id=? OR campaign.public_slug=?)';
        $params[] = $filters['campaign'];
        $params[] = $filters['campaign'];
    }
    if ($filters['operation'] !== null) {
        $where[] = 'operation.public_id=?';
        $params[] = $filters['operation'];
    }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function mg_public_donations_reconcile_attribution_rows(PDO $pdo, array $filters): array
{
    $scope = mg_public_donations_reconcile_scope_sql($filters);
    $limit = (int)$filters['limit'];
    $sql = "SELECT
                reward.id,reward.public_id,reward.status,reward.original_community_user_id,
                reward.wallet_item_id,reward.pppm_item_id,reward.microgift_instance_id,
                reward.campaign_id,reward.reward_template_id,reward.batch_id,reward.operation_id,
                operation.public_id AS operation_public_id,
                batch.public_id AS batch_public_id,batch.quantity AS batch_quantity,
                batch.recalled_quantity AS batch_recalled_quantity,batch.status AS batch_status,
                campaign.public_id AS campaign_public_id,campaign.public_slug,
                template.public_id AS reward_template_public_id,
                wallet.id AS wallet_exists,wallet.user_id AS wallet_owner,wallet.status AS wallet_status,
                wallet.pppm_item_id AS wallet_pppm_item_id,
                pppm.id AS pppm_exists,pppm.owner_user_id AS pppm_owner,pppm.status AS pppm_status,
                microgift.id AS microgift_exists,microgift.owner_user_id AS microgift_owner,
                microgift.status AS microgift_status,microgift.pppm_item_id AS microgift_pppm_item_id,
                COALESCE(inbox.inbox_count,0) AS inbox_count,
                COALESCE(inbox.active_inbox_count,0) AS active_inbox_count,
                COALESCE(inbox.nonrevoked_inbox_count,0) AS nonrevoked_inbox_count
            FROM campaign_donation_rewards reward
            INNER JOIN campaign_donation_operations operation ON operation.id=reward.operation_id
            INNER JOIN campaign_donation_batches batch ON batch.id=reward.batch_id
            INNER JOIN campaigns campaign ON campaign.id=reward.campaign_id AND campaign.campaign_type='public_donation'
            INNER JOIN reward_templates template ON template.id=reward.reward_template_id
            LEFT JOIN wallet_items wallet ON wallet.id=reward.wallet_item_id
            LEFT JOIN pppm_items pppm ON pppm.id=reward.pppm_item_id
            LEFT JOIN microgift_instances microgift ON microgift.id=reward.microgift_instance_id
            LEFT JOIN (
                SELECT instance_id,
                       COUNT(*) AS inbox_count,
                       SUM(archived_at IS NULL) AS active_inbox_count,
                       SUM(state<>'revoked' OR archived_at IS NULL) AS nonrevoked_inbox_count
                  FROM microgift_inbox_items
                 GROUP BY instance_id
            ) inbox ON inbox.instance_id=reward.microgift_instance_id
            WHERE {$scope['sql']}
            ORDER BY reward.id ASC
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($scope['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_public_donations_reconcile_counter_rows(PDO $pdo, array $filters): array
{
    $where = ["campaign.merchant_user_id=?", "campaign.campaign_type='public_donation'"];
    $params = [(int)$filters['merchant_id']];
    if ($filters['campaign'] !== null) {
        $where[] = '(campaign.public_id=? OR campaign.public_slug=?)';
        $params[] = $filters['campaign'];
        $params[] = $filters['campaign'];
    }
    if ($filters['operation'] !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM campaign_donation_rewards scoped_reward INNER JOIN campaign_donation_operations scoped_operation ON scoped_operation.id=scoped_reward.operation_id WHERE scoped_reward.campaign_id=campaign.id AND scoped_operation.public_id=?)';
        $params[] = $filters['operation'];
    }
    $stmt = $pdo->prepare(
        "SELECT campaign.id,campaign.public_id,campaign.public_slug,campaign.issued_count,campaign.quantity_limit,
                COALESCE(SUM(reward.status='allocated'),0) AS expected_net,
                COUNT(reward.id) AS gross_allocated,
                COALESCE(SUM(reward.status='recalled'),0) AS recalled
           FROM campaigns campaign
           LEFT JOIN campaign_donation_rewards reward
                  ON reward.campaign_id=campaign.id
                 AND reward.merchant_user_id=campaign.merchant_user_id
          WHERE " . implode(' AND ', $where) . "
          GROUP BY campaign.id
          ORDER BY campaign.id ASC"
    );
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $templateIds = [];
    foreach ($campaigns as $campaign) {
        $ids = $pdo->prepare('SELECT DISTINCT reward_template_id FROM campaign_donation_rewards WHERE campaign_id=?');
        $ids->execute([(int)$campaign['id']]);
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) $templateIds[(int)$id] = true;
    }
    $templates = [];
    foreach (array_keys($templateIds) as $templateId) {
        $stmt = $pdo->prepare(
            "SELECT template.id,template.public_id,template.issued_count,template.quantity_limit,
                    COALESCE(SUM(reward.status='allocated'),0) AS expected_net,
                    COUNT(reward.id) AS gross_allocated,
                    COALESCE(SUM(reward.status='recalled'),0) AS recalled
               FROM reward_templates template
               LEFT JOIN campaign_donation_rewards reward
                      ON reward.reward_template_id=template.id
                     AND reward.merchant_user_id=template.merchant_user_id
              WHERE template.id=? AND template.merchant_user_id=?
              GROUP BY template.id"
        );
        $stmt->execute([$templateId, (int)$filters['merchant_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $templates[] = $row;
    }
    return ['campaigns' => $campaigns, 'templates' => $templates];
}

function mg_public_donations_reconcile_batch_rows(PDO $pdo, array $filters): array
{
    $where = ['batch.merchant_user_id=?'];
    $params = [(int)$filters['merchant_id']];
    if ($filters['campaign'] !== null) {
        $where[] = '(campaign.public_id=? OR campaign.public_slug=?)';
        $params[] = $filters['campaign'];
        $params[] = $filters['campaign'];
    }
    if ($filters['operation'] !== null) {
        $where[] = 'operation.public_id=?';
        $params[] = $filters['operation'];
    }
    $stmt = $pdo->prepare(
        "SELECT batch.id,batch.public_id,batch.quantity,batch.recalled_quantity,batch.status,
                campaign.public_id AS campaign_public_id,operation.public_id AS operation_public_id,
                COUNT(reward.id) AS attributed_quantity,
                COALESCE(SUM(reward.status='recalled'),0) AS expected_recalled
           FROM campaign_donation_batches batch
           INNER JOIN campaigns campaign ON campaign.id=batch.campaign_id
           INNER JOIN campaign_donation_operations operation ON operation.id=batch.operation_id
           LEFT JOIN campaign_donation_rewards reward ON reward.batch_id=batch.id
          WHERE " . implode(' AND ', $where) . "
          GROUP BY batch.id
          ORDER BY batch.id ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_public_donations_reconcile_missing_attribution(PDO $pdo, array $filters): array
{
    $where = ["wallet.merchant_user_id=?", "wallet.source_type='public_donation'", 'reward.id IS NULL'];
    $params = [(int)$filters['merchant_id']];
    if ($filters['campaign'] !== null) {
        $where[] = '(campaign.public_id=? OR campaign.public_slug=?)';
        $params[] = $filters['campaign'];
        $params[] = $filters['campaign'];
    }
    $limit = (int)$filters['limit'];
    $stmt = $pdo->prepare(
        "SELECT wallet.public_id AS wallet_item_id,campaign.public_id AS campaign_id,
                wallet.pppm_item_id,wallet.status
           FROM wallet_items wallet
           LEFT JOIN campaigns campaign ON campaign.id=wallet.campaign_id
           LEFT JOIN campaign_donation_rewards reward ON reward.wallet_item_id=wallet.id
          WHERE " . implode(' AND ', $where) . "
          ORDER BY wallet.id ASC LIMIT {$limit}"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_public_donations_reconcile_stale_assignments(PDO $pdo, array $filters): array
{
    $where = ["assignment.merchant_user_id=?", "assignment.status IN ('active','paused')", 'community_role.user_id IS NULL'];
    $params = [(int)$filters['merchant_id']];
    if ($filters['campaign'] !== null) {
        $where[] = '(campaign.public_id=? OR campaign.public_slug=?)';
        $params[] = $filters['campaign'];
        $params[] = $filters['campaign'];
    }
    $limit = (int)$filters['limit'];
    $stmt = $pdo->prepare(
        "SELECT assignment.id,assignment.public_id,assignment.status,
                campaign.public_id AS campaign_public_id,assignment.community_user_id
           FROM campaign_community_assignments assignment
           INNER JOIN campaigns campaign ON campaign.id=assignment.campaign_id
           LEFT JOIN (
                SELECT user_roles.user_id
                  FROM user_roles
                  INNER JOIN roles ON roles.id=user_roles.role_id AND roles.slug='community'
                 GROUP BY user_roles.user_id
           ) community_role ON community_role.user_id=assignment.community_user_id
          WHERE " . implode(' AND ', $where) . "
          ORDER BY assignment.id ASC LIMIT {$limit}"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_public_donations_reconcile_detect(PDO $pdo, array $options): array
{
    if (!mg_public_donations_reconcile_schema_ready($pdo)) {
        throw new RuntimeException('Public Donations reconciliation schema is incomplete.');
    }
    $filters = mg_public_donations_reconcile_filters($options);
    $attributionRows = mg_public_donations_reconcile_attribution_rows($pdo, $filters);
    $counters = mg_public_donations_reconcile_counter_rows($pdo, $filters);
    $batches = mg_public_donations_reconcile_batch_rows($pdo, $filters);
    $missingAttribution = mg_public_donations_reconcile_missing_attribution($pdo, $filters);
    $staleAssignments = mg_public_donations_reconcile_stale_assignments($pdo, $filters);

    $issues = [
        'missing_attribution' => [],
        'missing_links' => [],
        'ownership_mismatches' => [],
        'counter_drift' => [],
        'batch_drift' => [],
        'recalled_visible' => [],
        'assignment_role_removed' => [],
    ];

    foreach ($missingAttribution as $row) {
        $issues['missing_attribution'][] = [
            'wallet_item_id' => (string)$row['wallet_item_id'],
            'campaign_id' => $row['campaign_id'] !== null ? (string)$row['campaign_id'] : null,
            'reason' => 'Public Donations wallet item has no attribution row.',
            'repairable' => false,
        ];
    }

    foreach ($attributionRows as $row) {
        $missing = [];
        if (empty($row['wallet_exists'])) $missing[] = 'wallet';
        if (empty($row['pppm_exists'])) $missing[] = 'pppm';
        if (empty($row['microgift_exists'])) $missing[] = 'microgift';
        if ((int)$row['inbox_count'] === 0) $missing[] = 'inbox';
        if ($missing !== []) {
            $issues['missing_links'][] = [
                'attribution_id' => (string)$row['public_id'],
                'campaign_id' => (string)$row['campaign_public_id'],
                'missing' => $missing,
                'repairable' => false,
            ];
        }

        $owners = [];
        foreach (['wallet_owner','pppm_owner','microgift_owner'] as $field) {
            if ($row[$field] !== null) $owners[(int)$row[$field]] = true;
        }
        $linkMismatch = ($row['wallet_pppm_item_id'] !== null && (int)$row['wallet_pppm_item_id'] !== (int)$row['pppm_item_id'])
            || ($row['microgift_pppm_item_id'] !== null && (int)$row['microgift_pppm_item_id'] !== (int)$row['pppm_item_id']);
        if (count($owners) > 1 || $linkMismatch) {
            $issues['ownership_mismatches'][] = [
                'attribution_id' => (string)$row['public_id'],
                'campaign_id' => (string)$row['campaign_public_id'],
                'wallet_owner' => $row['wallet_owner'] !== null ? (int)$row['wallet_owner'] : null,
                'pppm_owner' => $row['pppm_owner'] !== null ? (int)$row['pppm_owner'] : null,
                'microgift_owner' => $row['microgift_owner'] !== null ? (int)$row['microgift_owner'] : null,
                'link_mismatch' => $linkMismatch,
                'repairable' => false,
            ];
        }

        if ((string)$row['status'] === 'recalled') {
            $visible = (string)($row['wallet_status'] ?? '') !== 'cancelled'
                || (string)($row['pppm_status'] ?? '') !== 'cancelled'
                || !in_array((string)($row['microgift_status'] ?? ''), ['cancelled','revoked'], true)
                || (int)$row['nonrevoked_inbox_count'] > 0;
            if ($visible) {
                $issues['recalled_visible'][] = [
                    'attribution_id' => (string)$row['public_id'],
                    'campaign_id' => (string)$row['campaign_public_id'],
                    'wallet_item_id' => (int)$row['wallet_item_id'],
                    'pppm_item_id' => (int)$row['pppm_item_id'],
                    'microgift_instance_id' => (int)$row['microgift_instance_id'],
                    'repairable' => !empty($row['wallet_exists']) && !empty($row['pppm_exists']) && !empty($row['microgift_exists']),
                ];
            }
        }
    }

    foreach ($counters['campaigns'] as $row) {
        $expected = (int)$row['expected_net'];
        if ((int)$row['issued_count'] !== $expected) {
            $issues['counter_drift'][] = [
                'entity' => 'campaign', 'database_id' => (int)$row['id'],
                'public_id' => (string)$row['public_id'],
                'actual' => (int)$row['issued_count'], 'expected' => $expected,
                'repairable' => true,
            ];
        }
    }
    foreach ($counters['templates'] as $row) {
        $expected = (int)$row['expected_net'];
        if ((int)$row['issued_count'] !== $expected) {
            $issues['counter_drift'][] = [
                'entity' => 'reward_template', 'database_id' => (int)$row['id'],
                'public_id' => (string)$row['public_id'],
                'actual' => (int)$row['issued_count'], 'expected' => $expected,
                'repairable' => true,
            ];
        }
    }

    foreach ($batches as $row) {
        $quantity = (int)$row['quantity'];
        $attributed = (int)$row['attributed_quantity'];
        $expectedRecalled = (int)$row['expected_recalled'];
        $expectedStatus = $expectedRecalled === 0 ? 'allocated'
            : ($expectedRecalled >= $quantity ? 'recalled' : 'partially_recalled');
        if ($quantity !== $attributed || (int)$row['recalled_quantity'] !== $expectedRecalled || (string)$row['status'] !== $expectedStatus) {
            $issues['batch_drift'][] = [
                'batch_id' => (string)$row['public_id'],
                'database_id' => (int)$row['id'],
                'quantity' => $quantity,
                'attributed_quantity' => $attributed,
                'actual_recalled' => (int)$row['recalled_quantity'],
                'expected_recalled' => $expectedRecalled,
                'actual_status' => (string)$row['status'],
                'expected_status' => $expectedStatus,
                'repairable' => $quantity === $attributed,
            ];
        }
    }

    foreach ($staleAssignments as $row) {
        $issues['assignment_role_removed'][] = [
            'assignment_id' => (string)$row['public_id'],
            'database_id' => (int)$row['id'],
            'campaign_id' => (string)$row['campaign_public_id'],
            'community_user_id' => (int)$row['community_user_id'],
            'actual_status' => (string)$row['status'],
            'expected_status' => 'removed',
            'existing_rewards_affected' => false,
            'repairable' => true,
        ];
    }

    $totals = ['issues' => 0, 'repairable' => 0, 'report_only' => 0];
    foreach ($issues as $group) {
        foreach ($group as $issue) {
            $totals['issues']++;
            if (!empty($issue['repairable'])) $totals['repairable']++;
            else $totals['report_only']++;
        }
    }

    $campaignMetrics = [];
    foreach ($counters['campaigns'] as $row) {
        $limit = $row['quantity_limit'] !== null ? (int)$row['quantity_limit'] : null;
        $net = (int)$row['expected_net'];
        $campaignMetrics[] = [
            'campaign_id' => (string)$row['public_id'],
            'gross_allocated' => (int)$row['gross_allocated'],
            'recalled' => (int)$row['recalled'],
            'net_allocated' => $net,
            'quantity_limit' => $limit,
            'remaining_inventory' => $limit !== null ? max(0, $limit - $net) : null,
        ];
    }

    return [
        'filters' => $filters,
        'scanned_attributions' => count($attributionRows),
        'metrics' => ['campaigns' => $campaignMetrics],
        'issues' => $issues,
        'totals' => $totals,
        'unexplained_drift' => $totals['report_only'],
        'dry_run_clean' => $totals['issues'] === 0,
    ];
}

function mg_public_donations_reconcile_lock(PDO $pdo, int $merchantId): string
{
    $name = 'mg:public-donations:reconcile:' . $merchantId;
    $stmt = $pdo->prepare('SELECT GET_LOCK(?,10)');
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Another Public Donations reconciliation is running.');
    return $name;
}

function mg_public_donations_reconcile_unlock(PDO $pdo, ?string $name): void
{
    if (!$name) return;
    try { $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$name]); } catch (Throwable) {}
}

function mg_public_donations_reconcile_audit_receipt(PDO $pdo, array $receipt, ?int $actorId = null): void
{
    if (!mg_public_donations_reconcile_table($pdo, 'audit_logs')) return;
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id,action,entity_type,metadata_json,ip_address,user_agent,created_at) VALUES (?,\'public_donations.reconcile\',\'public_donations\',?,NULL,\'cli\',NOW())');
        $stmt->execute([$actorId, json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable) {
        // The returned receipt is still the immutable CLI audit artifact.
    }
}

function mg_public_donations_reconcile_apply(PDO $pdo, array $options): array
{
    $filters = mg_public_donations_reconcile_filters($options);
    $modes = mg_public_donations_reconcile_modes($options['repair'] ?? null);
    $dryRun = $modes === [];
    $before = mg_public_donations_reconcile_detect($pdo, $filters);
    $receiptId = mg_public_donations_reconcile_uuid();
    $repairs = [];
    $lock = null;

    if (!$dryRun) {
        $lock = mg_public_donations_reconcile_lock($pdo, (int)$filters['merchant_id']);
        try {
            $pdo->beginTransaction();
            if (in_array('counters', $modes, true)) {
                foreach ($before['issues']['counter_drift'] as $issue) {
                    if (empty($issue['repairable'])) continue;
                    $table = $issue['entity'] === 'campaign' ? 'campaigns' : 'reward_templates';
                    $stmt = $pdo->prepare("UPDATE {$table} SET issued_count=?,updated_at=NOW() WHERE id=?");
                    $stmt->execute([(int)$issue['expected'], (int)$issue['database_id']]);
                    $repairs[] = ['mode' => 'counters', 'entity' => $issue['entity'], 'public_id' => $issue['public_id'], 'from' => $issue['actual'], 'to' => $issue['expected']];
                }
            }
            if (in_array('batch_totals', $modes, true)) {
                foreach ($before['issues']['batch_drift'] as $issue) {
                    if (empty($issue['repairable'])) continue;
                    $stmt = $pdo->prepare('UPDATE campaign_donation_batches SET recalled_quantity=?,status=?,updated_at=NOW() WHERE id=?');
                    $stmt->execute([(int)$issue['expected_recalled'], (string)$issue['expected_status'], (int)$issue['database_id']]);
                    $repairs[] = ['mode' => 'batch_totals', 'batch_id' => $issue['batch_id'], 'recalled_to' => $issue['expected_recalled'], 'status_to' => $issue['expected_status']];
                }
            }
            if (in_array('recalled_visibility', $modes, true)) {
                foreach ($before['issues']['recalled_visible'] as $issue) {
                    if (empty($issue['repairable'])) continue;
                    $pdo->prepare("UPDATE wallet_items SET status='cancelled',updated_at=NOW() WHERE id=?")->execute([(int)$issue['wallet_item_id']]);
                    $pdo->prepare("UPDATE pppm_items SET status='cancelled',cancelled_at=COALESCE(cancelled_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$issue['pppm_item_id']]);
                    $pdo->prepare("UPDATE microgift_instances SET status='cancelled',cancelled_at=COALESCE(cancelled_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$issue['microgift_instance_id']]);
                    $pdo->prepare("UPDATE microgift_inbox_items SET state='revoked',archived_at=COALESCE(archived_at,NOW()),updated_at=NOW() WHERE instance_id=?")->execute([(int)$issue['microgift_instance_id']]);
                    $repairs[] = ['mode' => 'recalled_visibility', 'attribution_id' => $issue['attribution_id']];
                }
            }
            if (in_array('assignments', $modes, true)) {
                foreach ($before['issues']['assignment_role_removed'] as $issue) {
                    if (empty($issue['repairable'])) continue;
                    $pdo->prepare("UPDATE campaign_community_assignments SET status='removed',removed_at=COALESCE(removed_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$issue['database_id']]);
                    $repairs[] = ['mode' => 'assignments', 'assignment_id' => $issue['assignment_id'], 'existing_rewards_affected' => false];
                }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        } finally {
            mg_public_donations_reconcile_unlock($pdo, $lock);
        }
    }

    $after = $dryRun ? $before : mg_public_donations_reconcile_detect($pdo, $filters);
    $receipt = [
        'receipt_id' => $receiptId,
        'mode' => $dryRun ? 'dry_run' : 'repair',
        'repair_modes' => $modes,
        'filters' => $filters,
        'before' => $before['totals'],
        'repairs_applied' => count($repairs),
        'repairs' => $repairs,
        'after' => $after['totals'],
        'unexplained_drift_after' => $after['unexplained_drift'],
        'completed_at' => gmdate('c'),
    ];
    $receipt['checksum'] = hash('sha256', json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    mg_public_donations_reconcile_audit_receipt($pdo, $receipt, isset($options['actor_id']) ? (int)$options['actor_id'] : null);

    return ['receipt' => $receipt, 'report' => $after];
}
