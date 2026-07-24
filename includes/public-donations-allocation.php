<?php
declare(strict_types=1);

require_once __DIR__ . '/public-donations-community-assignments.php';

/**
 * Public Donations Phase 4 allocation engine.
 *
 * Every quantity unit is issued through the canonical wallet -> PPPM ->
 * Microgift -> Inbox bridge. This file orchestrates validation, deterministic
 * locks, inventory, idempotency, batching, attribution, and notifications.
 */

function mg_public_donations_allocation_fail(string $message, int $status = 422): never
{
    if (function_exists('mg_fail')) mg_fail($message, $status);
    throw new RuntimeException($message, $status);
}

function mg_public_donations_allocation_uuid(): string
{
    return mg_public_donations_assignment_uuid();
}

function mg_public_donations_allocation_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];

    $requirements = [
        'campaign_community_assignments' => ['public_id','campaign_id','community_user_id','status','last_allocated_at'],
        'campaign_donation_operations' => ['public_id','idempotency_key','request_hash','status','requested_quantity'],
        'campaign_donation_batches' => ['public_id','operation_id','assignment_id','community_user_id','quantity'],
        'campaign_donation_rewards' => ['public_id','operation_id','batch_id','wallet_item_id','pppm_item_id','microgift_instance_id'],
    ];
    try {
        foreach ($requirements as $table => $columns) {
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema=DATABASE() AND table_name=? AND column_name IN ({$placeholders})"
            );
            $stmt->execute(array_merge([$table], $columns));
            if ((int)$stmt->fetchColumn() !== count($columns)) return $cache[$key] = false;
        }
        $stmt = $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.columns
              WHERE table_schema=DATABASE() AND table_name='wallet_items' AND column_name='source_type' LIMIT 1"
        );
        $columnType = strtolower((string)$stmt->fetchColumn());
        return $cache[$key] = $columnType !== '' && str_contains($columnType, "'public_donation'");
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_public_donations_allocation_idempotency_key(mixed $value): string
{
    $key = trim((string)$value);
    if ($key === '') $key = 'public-donation:' . mg_public_donations_allocation_uuid();
    if (mb_strlen($key) > 190 || preg_match('/^[A-Za-z0-9:._-]{8,190}$/', $key) !== 1) {
        mg_public_donations_allocation_fail('Invalid allocation idempotency key.', 422);
    }
    return $key;
}

function mg_public_donations_allocation_text(mixed $value, int $maximum, string $label): ?string
{
    $text = trim((string)$value);
    if ($text === '') return null;
    if (mb_strlen($text) > $maximum) mg_public_donations_allocation_fail($label . ' is too long.', 422);
    return $text;
}

function mg_public_donations_allocation_recipients(mixed $value): array
{
    if (!is_array($value) || $value === []) {
        mg_public_donations_allocation_fail('Select at least one active Community assignment.', 422);
    }

    $normalized = [];
    foreach ($value as $row) {
        if (!is_array($row)) mg_public_donations_allocation_fail('Invalid allocation recipient.', 422);
        $assignmentId = strtolower(trim((string)($row['assignment_id'] ?? '')));
        if (strlen($assignmentId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $assignmentId) !== 1) {
            mg_public_donations_allocation_fail('Invalid Community assignment selection.', 422);
        }
        $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false || (int)$quantity < 1 || (int)$quantity > 1000) {
            mg_public_donations_allocation_fail('Each allocation quantity must be between 1 and 1000.', 422);
        }
        if (isset($normalized[$assignmentId])) {
            mg_public_donations_allocation_fail('Each Community account may appear only once per allocation.', 422);
        }
        $normalized[$assignmentId] = ['assignment_id' => $assignmentId, 'quantity' => (int)$quantity];
    }

    if (count($normalized) > 50) mg_public_donations_allocation_fail('Allocations are limited to 50 Community accounts.', 422);
    ksort($normalized, SORT_STRING);
    $rows = array_values($normalized);
    $total = array_sum(array_column($rows, 'quantity'));
    if ($total > 1000) mg_public_donations_allocation_fail('Allocations are limited to 1,000 reward units.', 422);
    return $rows;
}

function mg_public_donations_allocation_mode(array $recipients): string
{
    if (count($recipients) === 1) return 'single';
    $quantities = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['quantity'], $recipients)));
    return count($quantities) === 1 ? 'same_quantity' : 'custom_quantity';
}

function mg_public_donations_allocation_request_hash(
    string $campaignRef,
    string $templateRef,
    array $recipients,
    ?string $message,
    ?string $internalNote
): string {
    $canonical = [
        'campaign' => strtolower($campaignRef),
        'reward_template' => strtolower($templateRef),
        'recipients' => $recipients,
        'message' => $message,
        'internal_note' => $internalNote,
    ];
    return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function mg_public_donations_allocation_expiry(array $template): ?string
{
    $rule = (string)($template['expiration_rule'] ?? 'none');
    if (in_array($rule, ['fixed_date', 'event_date'], true) && !empty($template['expires_at'])) {
        return (string)$template['expires_at'];
    }
    if ($rule === 'after_issue' && (int)($template['expiration_days'] ?? 0) > 0) {
        return date('Y-m-d H:i:s', time() + ((int)$template['expiration_days'] * 86400));
    }
    return null;
}

function mg_public_donations_allocation_campaign(PDO $pdo, int $merchantId, string $campaignRef, bool $forUpdate): array
{
    $campaignRef = strtolower(trim($campaignRef));
    if ($campaignRef === '' || mb_strlen($campaignRef) > 140 || preg_match('/^[a-z0-9][a-z0-9-]{0,139}$/', $campaignRef) !== 1) {
        mg_public_donations_allocation_fail('Invalid Public Donations campaign.', 422);
    }
    $stmt = $pdo->prepare(
        "SELECT id,public_id,public_slug,title,status,campaign_type,quantity_limit,issued_count
           FROM campaigns
          WHERE merchant_user_id=? AND (public_id=? OR public_slug=?)
          LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$merchantId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign || (string)$campaign['campaign_type'] !== 'public_donation') {
        mg_public_donations_allocation_fail('Public Donations campaign not found.', 404);
    }
    if ((string)$campaign['status'] !== 'active') {
        mg_public_donations_allocation_fail('Only active Public Donations campaigns can allocate rewards.', 409);
    }
    return $campaign;
}

function mg_public_donations_allocation_template(PDO $pdo, int $merchantId, string $templateRef, bool $forUpdate): array
{
    $templateRef = strtolower(trim($templateRef));
    if (strlen($templateRef) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $templateRef) !== 1) {
        mg_public_donations_allocation_fail('Invalid reward template.', 422);
    }
    $stmt = $pdo->prepare(
        "SELECT id,public_id,title,description,status,value_amount_cents,currency,quantity_limit,issued_count,
                expiration_rule,expiration_days,expires_at,redemption_instructions
           FROM reward_templates
          WHERE merchant_user_id=? AND public_id=?
          LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$merchantId, $templateRef]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template || (string)$template['status'] !== 'active') {
        mg_public_donations_allocation_fail('Active reward template not found.', 404);
    }
    return $template;
}

function mg_public_donations_allocation_templates(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT public_id,title,description,value_amount_cents,currency,quantity_limit,issued_count,
                expiration_rule,expiration_days,expires_at
           FROM reward_templates
          WHERE merchant_user_id=? AND status='active'
          ORDER BY title ASC,id ASC LIMIT 200"
    );
    $stmt->execute([$merchantId]);
    return array_map(static function(array $row): array {
        $limit = $row['quantity_limit'] !== null ? (int)$row['quantity_limit'] : null;
        $issued = (int)$row['issued_count'];
        return [
            'id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'description' => trim((string)($row['description'] ?? '')) ?: null,
            'value_cents' => (int)$row['value_amount_cents'],
            'currency' => (string)$row['currency'],
            'quantity_limit' => $limit,
            'issued_count' => $issued,
            'remaining_inventory' => $limit === null ? null : max(0, $limit - $issued),
            'expires_at' => $row['expires_at'] !== null ? (string)$row['expires_at'] : null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_public_donations_allocation_assignments(
    PDO $pdo,
    int $merchantId,
    int $campaignId,
    array $recipients,
    bool $forUpdate
): array {
    $ids = array_column($recipients, 'assignment_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT assignment.id,assignment.public_id,assignment.community_user_id,assignment.status,
                pp.public_id AS community_account_id,
                CASE
                    WHEN pp.status='active' AND pp.visibility IN ('public','unlisted')
                        THEN COALESCE(NULLIF(pp.display_name,''),NULLIF(u.display_name,''),u.full_name)
                    ELSE COALESCE(NULLIF(u.display_name,''),u.full_name)
                END AS display_name
           FROM campaign_community_assignments assignment
           INNER JOIN users u ON u.id=assignment.community_user_id AND u.status='active'
           INNER JOIN public_profiles pp ON pp.user_id=u.id
           INNER JOIN user_roles community_link ON community_link.user_id=u.id
           INNER JOIN roles community_role ON community_role.id=community_link.role_id AND community_role.slug='community'
          WHERE assignment.merchant_user_id=? AND assignment.campaign_id=? AND assignment.status='active'
            AND assignment.public_id IN ({$placeholders})
          ORDER BY assignment.id ASC" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute(array_merge([$merchantId, $campaignId], $ids));
    $found = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $found[(string)$row['public_id']] = $row;
    if (count($found) !== count($recipients)) {
        mg_public_donations_allocation_fail('Every recipient must be an active, currently eligible Community assignment.', 409);
    }

    $resolved = [];
    foreach ($recipients as $recipient) {
        $row = $found[$recipient['assignment_id']] ?? null;
        if (!$row) mg_public_donations_allocation_fail('Community assignment changed during allocation.', 409);
        $row['quantity'] = (int)$recipient['quantity'];
        $resolved[] = $row;
    }
    usort($resolved, static fn(array $a, array $b): int => ((int)$a['id']) <=> ((int)$b['id']));
    return $resolved;
}

function mg_public_donations_allocation_remaining(?int $limit, int $issued): ?int
{
    return $limit === null ? null : max(0, $limit - $issued);
}

function mg_public_donations_allocation_inventory(array $campaign, array $template, int $requested): array
{
    $campaignLimit = $campaign['quantity_limit'] !== null ? (int)$campaign['quantity_limit'] : null;
    $templateLimit = $template['quantity_limit'] !== null ? (int)$template['quantity_limit'] : null;
    $campaignRemaining = mg_public_donations_allocation_remaining($campaignLimit, (int)$campaign['issued_count']);
    $templateRemaining = mg_public_donations_allocation_remaining($templateLimit, (int)$template['issued_count']);
    $finite = array_values(array_filter([$campaignRemaining, $templateRemaining], static fn(?int $value): bool => $value !== null));
    $available = $finite === [] ? null : min($finite);
    if ($available !== null && $requested > $available) {
        mg_public_donations_allocation_fail('Insufficient campaign or reward-template inventory.', 409);
    }
    return [
        'campaign_before' => $campaignRemaining,
        'template_before' => $templateRemaining,
        'available_before' => $available,
        'campaign_after' => $campaignRemaining === null ? null : $campaignRemaining - $requested,
        'template_after' => $templateRemaining === null ? null : $templateRemaining - $requested,
        'available_after' => $available === null ? null : $available - $requested,
    ];
}

function mg_public_donations_allocation_preflight(
    PDO $pdo,
    int $merchantId,
    string $campaignRef,
    string $templateRef,
    array $recipients,
    ?string $message = null,
    ?string $internalNote = null
): array {
    $campaign = mg_public_donations_allocation_campaign($pdo, $merchantId, $campaignRef, false);
    $template = mg_public_donations_allocation_template($pdo, $merchantId, $templateRef, false);
    $assignments = mg_public_donations_allocation_assignments($pdo, $merchantId, (int)$campaign['id'], $recipients, false);
    $quantity = array_sum(array_map(static fn(array $row): int => (int)$row['quantity'], $assignments));
    $inventory = mg_public_donations_allocation_inventory($campaign, $template, $quantity);
    $valueCents = max(0, (int)$template['value_amount_cents']);
    $totalValue = $quantity * $valueCents;
    $large = $quantity >= 100 || $totalValue >= 100000;

    return [
        'campaign' => [
            'id' => (string)$campaign['public_id'],
            'title' => (string)$campaign['title'],
            'issued_count' => (int)$campaign['issued_count'],
            'quantity_limit' => $campaign['quantity_limit'] !== null ? (int)$campaign['quantity_limit'] : null,
        ],
        'reward_template' => [
            'id' => (string)$template['public_id'],
            'title' => (string)$template['title'],
            'value_cents' => $valueCents,
            'currency' => (string)$template['currency'],
            'issued_count' => (int)$template['issued_count'],
            'quantity_limit' => $template['quantity_limit'] !== null ? (int)$template['quantity_limit'] : null,
        ],
        'mode' => mg_public_donations_allocation_mode($recipients),
        'recipient_count' => count($assignments),
        'requested_quantity' => $quantity,
        'total_stated_value_cents' => $totalValue,
        'currency' => (string)$template['currency'],
        'large_operation' => $large,
        'confirmation_level' => $large ? 'large_operation' : 'standard',
        'inventory' => $inventory,
        'recipients' => array_map(static fn(array $row): array => [
            'assignment_id' => (string)$row['public_id'],
            'community_account_id' => (string)$row['community_account_id'],
            'display_name' => (string)$row['display_name'],
            'quantity' => (int)$row['quantity'],
        ], $assignments),
        'message' => $message,
        'internal_note_present' => $internalNote !== null,
        'preview_reserves_inventory' => false,
    ];
}

function mg_public_donations_allocation_operation(PDO $pdo, int $merchantId, string $idempotencyKey, bool $forUpdate): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM campaign_donation_operations WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$merchantId, $idempotencyKey]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_public_donations_allocation_tracking(PDO $pdo, int $merchantId, string $operationPublicId): array
{
    $stmt = $pdo->prepare(
        "SELECT operation.*,campaign.public_id AS campaign_public_id,campaign.title AS campaign_title,
                template.public_id AS template_public_id,template.title AS template_title
           FROM campaign_donation_operations operation
           INNER JOIN campaigns campaign ON campaign.id=operation.campaign_id
           INNER JOIN reward_templates template ON template.id=operation.reward_template_id
          WHERE operation.merchant_user_id=? AND operation.public_id=? LIMIT 1"
    );
    $stmt->execute([$merchantId, $operationPublicId]);
    $operation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$operation) mg_public_donations_allocation_fail('Allocation operation not found.', 404);

    $batchStmt = $pdo->prepare(
        "SELECT batch.public_id,batch.quantity,batch.recalled_quantity,batch.stated_value_cents,batch.currency,batch.status,
                batch.created_at,assignment.public_id AS assignment_public_id,profile.public_id AS community_account_id,
                COALESCE(NULLIF(profile.display_name,''),NULLIF(user.display_name,''),user.full_name) AS display_name,
                COUNT(reward.id) AS reward_count
           FROM campaign_donation_batches batch
           INNER JOIN campaign_community_assignments assignment ON assignment.id=batch.assignment_id
           INNER JOIN users user ON user.id=batch.community_user_id
           INNER JOIN public_profiles profile ON profile.user_id=user.id
           LEFT JOIN campaign_donation_rewards reward ON reward.batch_id=batch.id
          WHERE batch.operation_id=?
          GROUP BY batch.id,batch.public_id,batch.quantity,batch.recalled_quantity,batch.stated_value_cents,batch.currency,
                   batch.status,batch.created_at,assignment.public_id,profile.public_id,profile.display_name,user.display_name,user.full_name
          ORDER BY batch.id ASC"
    );
    $batchStmt->execute([(int)$operation['id']]);

    return [
        'id' => (string)$operation['public_id'],
        'kind' => (string)$operation['operation_kind'],
        'mode' => (string)$operation['operation_mode'],
        'status' => (string)$operation['status'],
        'campaign' => ['id' => (string)$operation['campaign_public_id'], 'title' => (string)$operation['campaign_title']],
        'reward_template' => ['id' => (string)$operation['template_public_id'], 'title' => (string)$operation['template_title']],
        'recipient_count' => (int)$operation['recipient_count'],
        'requested_quantity' => (int)$operation['requested_quantity'],
        'completed_quantity' => (int)$operation['completed_quantity'],
        'inventory_before' => $operation['inventory_before'] !== null ? (int)$operation['inventory_before'] : null,
        'inventory_after' => $operation['inventory_after'] !== null ? (int)$operation['inventory_after'] : null,
        'total_stated_value_cents' => (int)$operation['total_stated_value_cents'],
        'currency' => (string)$operation['currency'],
        'confirmation_level' => (string)$operation['confirmation_level'],
        'message' => $operation['message'] !== null ? (string)$operation['message'] : null,
        'created_at' => (string)$operation['created_at'],
        'completed_at' => $operation['completed_at'] !== null ? (string)$operation['completed_at'] : null,
        'batches' => array_map(static fn(array $row): array => [
            'id' => (string)$row['public_id'],
            'assignment_id' => (string)$row['assignment_public_id'],
            'community_account_id' => (string)$row['community_account_id'],
            'display_name' => (string)$row['display_name'],
            'quantity' => (int)$row['quantity'],
            'recalled_quantity' => (int)$row['recalled_quantity'],
            'reward_count' => (int)$row['reward_count'],
            'stated_value_cents' => (int)$row['stated_value_cents'],
            'currency' => (string)$row['currency'],
            'status' => (string)$row['status'],
            'created_at' => (string)$row['created_at'],
        ], $batchStmt->fetchAll(PDO::FETCH_ASSOC)),
    ];
}

function mg_public_donations_allocation_recent(PDO $pdo, int $merchantId, ?int $campaignId = null, int $limit = 20): array
{
    $limit = max(1, min($limit, 50));
    $params = [$merchantId];
    $campaignFilter = '';
    if ($campaignId !== null) {
        $campaignFilter = ' AND operation.campaign_id=?';
        $params[] = $campaignId;
    }
    $stmt = $pdo->prepare(
        "SELECT operation.public_id,operation.status,operation.operation_mode,operation.recipient_count,
                operation.requested_quantity,operation.completed_quantity,operation.total_stated_value_cents,
                operation.currency,operation.confirmation_level,operation.created_at,operation.completed_at,
                campaign.public_id AS campaign_public_id,campaign.title AS campaign_title,
                template.public_id AS template_public_id,template.title AS template_title
           FROM campaign_donation_operations operation
           INNER JOIN campaigns campaign ON campaign.id=operation.campaign_id
           INNER JOIN reward_templates template ON template.id=operation.reward_template_id
          WHERE operation.merchant_user_id=? AND operation.operation_kind='allocation'{$campaignFilter}
          ORDER BY operation.id DESC LIMIT {$limit}"
    );
    $stmt->execute($params);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'status' => (string)$row['status'],
        'mode' => (string)$row['operation_mode'],
        'recipient_count' => (int)$row['recipient_count'],
        'requested_quantity' => (int)$row['requested_quantity'],
        'completed_quantity' => (int)$row['completed_quantity'],
        'total_stated_value_cents' => (int)$row['total_stated_value_cents'],
        'currency' => (string)$row['currency'],
        'confirmation_level' => (string)$row['confirmation_level'],
        'campaign' => ['id' => (string)$row['campaign_public_id'], 'title' => (string)$row['campaign_title']],
        'reward_template' => ['id' => (string)$row['template_public_id'], 'title' => (string)$row['template_title']],
        'created_at' => (string)$row['created_at'],
        'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_public_donations_allocation_execute(
    PDO $pdo,
    int $merchantId,
    int $actorId,
    string $campaignRef,
    string $templateRef,
    array $recipients,
    string $idempotencyKey,
    ?string $message,
    ?string $internalNote,
    bool $confirmLargeOperation
): array {
    if (!function_exists('mg_zero_reward_issue_from_wallet')) {
        throw new RuntimeException('Canonical wallet reward delivery bridge is unavailable.');
    }

    $requestHash = mg_public_donations_allocation_request_hash(
        $campaignRef,
        $templateRef,
        $recipients,
        $message,
        $internalNote
    );

    $pdo->beginTransaction();
    try {
        // Deterministic lock order: campaign -> reward template -> assignments -> idempotency operation.
        $campaign = mg_public_donations_allocation_campaign($pdo, $merchantId, $campaignRef, true);
        $template = mg_public_donations_allocation_template($pdo, $merchantId, $templateRef, true);
        $assignments = mg_public_donations_allocation_assignments($pdo, $merchantId, (int)$campaign['id'], $recipients, true);
        $existing = mg_public_donations_allocation_operation($pdo, $merchantId, $idempotencyKey, true);
        if ($existing) {
            if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                mg_public_donations_allocation_fail('This idempotency key belongs to a different allocation request.', 409);
            }
            if ((string)$existing['status'] === 'completed') {
                $pdo->commit();
                $result = mg_public_donations_allocation_tracking($pdo, $merchantId, (string)$existing['public_id']);
                $result['duplicate'] = true;
                return $result;
            }
            mg_public_donations_allocation_fail('This allocation request is already processing.', 409);
        }

        $quantity = array_sum(array_map(static fn(array $row): int => (int)$row['quantity'], $assignments));
        $inventory = mg_public_donations_allocation_inventory($campaign, $template, $quantity);
        $valueCents = max(0, (int)$template['value_amount_cents']);
        $totalValue = $quantity * $valueCents;
        $large = $quantity >= 100 || $totalValue >= 100000;
        if ($large && !$confirmLargeOperation) {
            mg_public_donations_allocation_fail('Confirm this large allocation before issuing rewards.', 409);
        }

        $operationPublicId = mg_public_donations_allocation_uuid();
        $mode = mg_public_donations_allocation_mode($recipients);
        $inventoryBefore = $inventory['available_before'];
        $inventoryAfter = $inventory['available_after'];
        try {
            $pdo->prepare(
                "INSERT INTO campaign_donation_operations
                 (public_id,merchant_user_id,campaign_id,reward_template_id,operation_kind,operation_mode,status,
                  idempotency_key,request_hash,recipient_count,requested_quantity,completed_quantity,
                  inventory_before,inventory_after,total_stated_value_cents,currency,confirmation_level,
                  message,internal_note,created_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,?,'allocation',?,'processing',?,?,?,?,0,?,?,?,?,?,?,?, ?,NOW(),NOW())"
            )->execute([
                $operationPublicId,$merchantId,(int)$campaign['id'],(int)$template['id'],$mode,
                $idempotencyKey,$requestHash,count($assignments),$quantity,$inventoryBefore,$inventoryAfter,
                $totalValue,(string)$template['currency'],$large ? 'large_operation' : 'standard',
                $message,$internalNote,$actorId,
            ]);
        } catch (PDOException $error) {
            if (!str_contains(strtolower($error->getMessage()), 'duplicate')) throw $error;
            $existing = mg_public_donations_allocation_operation($pdo, $merchantId, $idempotencyKey, true);
            if (!$existing || !hash_equals((string)$existing['request_hash'], $requestHash)) {
                mg_public_donations_allocation_fail('Allocation idempotency conflict.', 409);
            }
            if ((string)$existing['status'] !== 'completed') {
                mg_public_donations_allocation_fail('This allocation request is already processing.', 409);
            }
            $pdo->commit();
            $result = mg_public_donations_allocation_tracking($pdo, $merchantId, (string)$existing['public_id']);
            $result['duplicate'] = true;
            return $result;
        }
        $operationId = (int)$pdo->lastInsertId();
        $expiresAt = mg_public_donations_allocation_expiry($template);
        $completed = 0;

        foreach ($assignments as $assignment) {
            $batchPublicId = mg_public_donations_allocation_uuid();
            $batchValue = (int)$assignment['quantity'] * $valueCents;
            $pdo->prepare(
                "INSERT INTO campaign_donation_batches
                 (public_id,operation_id,assignment_id,merchant_user_id,campaign_id,reward_template_id,community_user_id,
                  quantity,recalled_quantity,stated_value_cents,currency,status,message,created_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,0,?,?,'allocated',?,?,NOW(),NOW())"
            )->execute([
                $batchPublicId,$operationId,(int)$assignment['id'],$merchantId,(int)$campaign['id'],(int)$template['id'],
                (int)$assignment['community_user_id'],(int)$assignment['quantity'],$batchValue,(string)$template['currency'],$message,$actorId,
            ]);
            $batchId = (int)$pdo->lastInsertId();

            for ($sequence = 1; $sequence <= (int)$assignment['quantity']; $sequence++) {
                $walletPublicId = mg_public_donations_allocation_uuid();
                $rewardPublicId = mg_public_donations_allocation_uuid();
                $metadata = [
                    'source_type' => 'public_donation',
                    'operation_id' => $operationPublicId,
                    'batch_id' => $batchPublicId,
                    'donation_reward_id' => $rewardPublicId,
                    'campaign_public_id' => (string)$campaign['public_id'],
                    'reward_template_public_id' => (string)$template['public_id'],
                    'assignment_public_id' => (string)$assignment['public_id'],
                    'original_community_account_id' => (string)$assignment['community_account_id'],
                    'allocation_sequence' => $sequence,
                    'message' => $message,
                ];
                $pdo->prepare(
                    "INSERT INTO wallet_items
                     (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,
                      value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at)
                     VALUES (?,?,NULL,?,?,?,'public_donation',?,'issued',?,?,?,?,NOW(),?,NOW(),NOW())"
                )->execute([
                    $walletPublicId,(int)$assignment['community_user_id'],$merchantId,(int)$template['id'],(int)$campaign['id'],
                    $batchPublicId,$valueCents,(string)$template['currency'],(string)$template['title'],
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$expiresAt,
                ]);
                $walletId = (int)$pdo->lastInsertId();

                $bridge = mg_zero_reward_issue_from_wallet($pdo, [
                    'merchant_user_id' => $merchantId,
                    'recipient_user_id' => (int)$assignment['community_user_id'],
                    'recipient_external_id' => (string)$assignment['community_account_id'],
                    'recipient_name' => (string)$assignment['display_name'],
                    'wallet_item_db_id' => $walletId,
                    'wallet_item_public_id' => $walletPublicId,
                    'campaign_public_id' => (string)$campaign['public_id'],
                    'reward_template_public_id' => (string)$template['public_id'],
                    'source_type' => 'public_donation',
                    'source_reference' => $operationPublicId,
                    'source_line_reference' => $batchPublicId . ':' . $sequence,
                    'title' => (string)$template['title'],
                    'description' => $template['description'] ?? null,
                    'currency' => (string)$template['currency'],
                    'display_value_cents' => $valueCents,
                    'expires_at' => $expiresAt,
                    'redemption_instructions' => $template['redemption_instructions'] ?? null,
                    'terms' => $metadata,
                ]);
                $pppmId = (int)($bridge['pppm_item_db_id'] ?? 0);
                $microgiftPublicId = trim((string)($bridge['microgift_instance_id'] ?? ''));
                if ($pppmId < 1 || $microgiftPublicId === '' || empty($bridge['action_center']['recipient_inbox_item_id'])) {
                    throw new RuntimeException('Canonical reward lifecycle was not completed.');
                }
                $microgiftStmt = $pdo->prepare('SELECT id FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE');
                $microgiftStmt->execute([$microgiftPublicId]);
                $microgiftId = (int)$microgiftStmt->fetchColumn();
                if ($microgiftId < 1) throw new RuntimeException('Issued Microgift instance was not found.');

                $pdo->prepare(
                    "INSERT INTO campaign_donation_rewards
                     (public_id,operation_id,batch_id,merchant_user_id,campaign_id,reward_template_id,original_community_user_id,
                      wallet_item_id,pppm_item_id,microgift_instance_id,allocation_sequence,reward_title_snapshot,
                      value_cents_snapshot,currency_snapshot,status,allocated_at,metadata_json,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'allocated',NOW(),?,NOW(),NOW())"
                )->execute([
                    $rewardPublicId,$operationId,$batchId,$merchantId,(int)$campaign['id'],(int)$template['id'],
                    (int)$assignment['community_user_id'],$walletId,$pppmId,$microgiftId,$sequence,(string)$template['title'],
                    $valueCents,(string)$template['currency'],
                    json_encode([
                        'wallet_item_public_id' => $walletPublicId,
                        'pppm_item_public_id' => (string)($bridge['pppm_item_id'] ?? ''),
                        'microgift_instance_public_id' => $microgiftPublicId,
                        'inbox_item_id' => (string)$bridge['action_center']['recipient_inbox_item_id'],
                        'assignment_public_id' => (string)$assignment['public_id'],
                        'community_account_id' => (string)$assignment['community_account_id'],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
                $completed++;
            }

            $pdo->prepare('UPDATE campaign_community_assignments SET last_allocated_at=NOW(),updated_at=NOW() WHERE id=?')
                ->execute([(int)$assignment['id']]);

            if (function_exists('mg_create_notification')) {
                mg_create_notification(
                    $pdo,
                    (int)$assignment['community_user_id'],
                    'public_donations.rewards_allocated',
                    'Public Donations rewards added to your Inbox',
                    (int)$assignment['quantity'] . ' reward' . ((int)$assignment['quantity'] === 1 ? '' : 's') .
                        ' from “' . mb_substr((string)$campaign['title'], 0, 160) . '” are now in your Microgifter Inbox.',
                    '/inbox.php',
                    [
                        'actor_user_id' => $actorId,
                        'allow_self' => true,
                        'merchant_user_id' => $merchantId,
                        'campaign_public_id' => (string)$campaign['public_id'],
                        'operation_public_id' => $operationPublicId,
                        'batch_public_id' => $batchPublicId,
                        'quantity' => (int)$assignment['quantity'],
                        'event_key' => 'public-donations.allocation.' . strtolower($operationPublicId) . '.' . strtolower($batchPublicId),
                    ]
                );
            }
        }

        if ($completed !== $quantity) throw new RuntimeException('Allocated reward count did not match the request.');
        $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+?,updated_at=NOW() WHERE id=?')
            ->execute([$quantity, (int)$template['id']]);
        $pdo->prepare('UPDATE campaigns SET issued_count=issued_count+?,updated_at=NOW() WHERE id=?')
            ->execute([$quantity, (int)$campaign['id']]);
        $pdo->prepare(
            "UPDATE campaign_donation_operations
                SET status='completed',completed_quantity=?,completed_at=NOW(),updated_at=NOW()
              WHERE id=? AND status='processing'"
        )->execute([$completed, $operationId]);

        $pdo->commit();
        $result = mg_public_donations_allocation_tracking($pdo, $merchantId, $operationPublicId);
        $result['duplicate'] = false;
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
