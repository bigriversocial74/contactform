<?php
declare(strict_types=1);

/**
 * Public Donations Phase 5 recall controls.
 *
 * Recalls are limited to untouched rewards that remain owned by the original
 * Community recipient. Downstream owners are never mutated.
 */

function mg_public_donations_recall_fail(string $message, int $status = 422): never
{
    if (function_exists('mg_fail')) mg_fail($message, $status);
    throw new RuntimeException($message, $status);
}

function mg_public_donations_recall_uuid(): string
{
    if (function_exists('mg_public_donations_allocation_uuid')) return mg_public_donations_allocation_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_public_donations_recall_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $required = [
        'campaign_donation_operations','campaign_donation_batches','campaign_donation_rewards',
        'campaign_community_assignments','wallet_items','pppm_items','pppm_item_events',
        'microgift_instances','microgift_lifecycle_actions','microgift_inbox_items','campaign_events',
    ];
    try {
        $placeholders = implode(',', array_fill(0, count($required), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$placeholders})");
        $stmt->execute($required);
        return $cache[$key] = (int)$stmt->fetchColumn() === count($required);
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_public_donations_recall_idempotency_key(mixed $value): string
{
    $key = trim((string)$value);
    if ($key === '' || mb_strlen($key) > 190 || preg_match('/^[A-Za-z0-9][A-Za-z0-9:._-]{7,189}$/', $key) !== 1) {
        mg_public_donations_recall_fail('A valid recall idempotency key is required.', 422);
    }
    return $key;
}

function mg_public_donations_recall_reason(mixed $value): string
{
    $reason = trim((string)$value);
    if ($reason === '' || mb_strlen($reason) > 500) {
        mg_public_donations_recall_fail('A recall reason between 1 and 500 characters is required.', 422);
    }
    return $reason;
}

function mg_public_donations_recall_quantity(mixed $value): int
{
    $quantity = filter_var($value, FILTER_VALIDATE_INT);
    if ($quantity === false || $quantity < 1 || $quantity > 1000) {
        mg_public_donations_recall_fail('Recall quantity must be between 1 and 1,000.', 422);
    }
    return (int)$quantity;
}

function mg_public_donations_recall_request_hash(string $batchPublicId, int $quantity, string $reason): string
{
    return hash('sha256', json_encode([
        'batch_id' => strtolower(trim($batchPublicId)),
        'quantity' => $quantity,
        'reason' => $reason,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function mg_public_donations_recall_batch(PDO $pdo, int $merchantId, string $batchPublicId, bool $forUpdate = false): array
{
    $batchPublicId = strtolower(trim($batchPublicId));
    if (strlen($batchPublicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $batchPublicId) !== 1) {
        mg_public_donations_recall_fail('Invalid Public Donations allocation batch.', 422);
    }
    $stmt = $pdo->prepare(
        "SELECT batch.*,operation.public_id AS allocation_operation_public_id,
                campaign.public_id AS campaign_public_id,campaign.public_slug,campaign.title AS campaign_title,
                campaign.status AS campaign_status,campaign.quantity_limit AS campaign_quantity_limit,
                campaign.issued_count AS campaign_issued_count,campaign.campaign_type,
                template.public_id AS template_public_id,template.title AS template_title,
                template.value_amount_cents,template.currency,template.status AS template_status,
                template.quantity_limit AS template_quantity_limit,template.issued_count AS template_issued_count,
                assignment.public_id AS assignment_public_id,assignment.status AS assignment_status,
                profile.public_id AS community_account_id,
                COALESCE(NULLIF(profile.display_name,''),NULLIF(user.display_name,''),user.full_name) AS display_name
           FROM campaign_donation_batches batch
           INNER JOIN campaign_donation_operations operation ON operation.id=batch.operation_id AND operation.operation_kind='allocation'
           INNER JOIN campaigns campaign ON campaign.id=batch.campaign_id
           INNER JOIN reward_templates template ON template.id=batch.reward_template_id
           INNER JOIN campaign_community_assignments assignment ON assignment.id=batch.assignment_id
           INNER JOIN users user ON user.id=batch.community_user_id
           INNER JOIN public_profiles profile ON profile.user_id=user.id
          WHERE batch.public_id=? AND batch.merchant_user_id=?
          LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$batchPublicId, $merchantId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$batch || (string)$batch['campaign_type'] !== 'public_donation') {
        mg_public_donations_recall_fail('Public Donations allocation batch not found.', 404);
    }
    return $batch;
}

function mg_public_donations_recall_rows(PDO $pdo, int $batchId, bool $forUpdate = false): array
{
    $sql = "SELECT reward.*,
                   wallet.public_id AS wallet_public_id,wallet.user_id AS wallet_user_id,
                   wallet.status AS wallet_status,wallet.claimed_at AS wallet_claimed_at,
                   wallet.redeemed_at AS wallet_redeemed_at,wallet.expires_at AS wallet_expires_at,
                   pppm.public_id AS pppm_public_id,pppm.owner_user_id AS pppm_owner_user_id,
                   pppm.recipient_user_id AS pppm_recipient_user_id,pppm.status AS pppm_status,
                   pppm.expires_at AS pppm_expires_at,
                   microgift.public_id AS microgift_public_id,microgift.template_id AS microgift_template_id,
                   microgift.owner_user_id AS microgift_owner_user_id,
                   microgift.recipient_user_id AS microgift_recipient_user_id,
                   microgift.status AS microgift_status,microgift.claimed_at AS microgift_claimed_at,
                   microgift.redeemed_at AS microgift_redeemed_at,microgift.expires_at AS microgift_expires_at
              FROM campaign_donation_rewards reward
              INNER JOIN wallet_items wallet ON wallet.id=reward.wallet_item_id
              INNER JOIN pppm_items pppm ON pppm.id=reward.pppm_item_id
              INNER JOIN microgift_instances microgift ON microgift.id=reward.microgift_instance_id
             WHERE reward.batch_id=?
             ORDER BY reward.id ASC" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$batchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_public_donations_recall_expired(mixed $value): bool
{
    $text = trim((string)$value);
    return $text !== '' && strtotime($text) !== false && strtotime($text) <= time();
}

function mg_public_donations_recall_classify(array $row): string
{
    if ((string)$row['status'] === 'recalled') return 'already_recalled';

    $walletStatus = (string)$row['wallet_status'];
    $pppmStatus = (string)$row['pppm_status'];
    $microgiftStatus = (string)$row['microgift_status'];
    if ($walletStatus === 'redeemed' || $pppmStatus === 'redeemed' || $microgiftStatus === 'redeemed'
        || !empty($row['wallet_redeemed_at']) || !empty($row['microgift_redeemed_at'])) return 'redeemed';

    if ($walletStatus === 'expired' || $pppmStatus === 'expired' || $microgiftStatus === 'expired'
        || mg_public_donations_recall_expired($row['wallet_expires_at'] ?? null)
        || mg_public_donations_recall_expired($row['pppm_expires_at'] ?? null)
        || mg_public_donations_recall_expired($row['microgift_expires_at'] ?? null)) return 'expired';

    if (in_array($walletStatus, ['cancelled'], true)
        || in_array($pppmStatus, ['cancelled','voided','refunded'], true)
        || in_array($microgiftStatus, ['cancelled','revoked','replaced'], true)) return 'cancelled';

    if (!empty($row['wallet_claimed_at']) || !empty($row['microgift_claimed_at'])
        || in_array($walletStatus, ['claimed'], true)
        || in_array($pppmStatus, ['claim_pending','verified'], true)
        || in_array($microgiftStatus, ['claim_pending','claimed','redeemable'], true)) return 'claimed';

    $originalUserId = (int)$row['original_community_user_id'];
    if ((int)$row['wallet_user_id'] !== $originalUserId
        || (int)$row['pppm_owner_user_id'] !== $originalUserId
        || (int)$row['microgift_owner_user_id'] !== $originalUserId) return 'regifted';

    $untouched = in_array($walletStatus, ['issued','viewed'], true)
        && in_array($pppmStatus, ['assigned','sent','delivered','viewed'], true)
        && in_array($microgiftStatus, ['issued','delivered'], true)
        && empty($row['wallet_claimed_at']) && empty($row['wallet_redeemed_at'])
        && empty($row['microgift_claimed_at']) && empty($row['microgift_redeemed_at']);
    return $untouched ? 'recallable' : 'unavailable';
}

function mg_public_donations_recall_preview_from_rows(array $batch, array $rows): array
{
    $counts = [
        'original' => count($rows),
        'recallable' => 0,
        'regifted' => 0,
        'claimed' => 0,
        'redeemed' => 0,
        'expired' => 0,
        'cancelled' => 0,
        'already_recalled' => 0,
        'unavailable' => 0,
    ];
    $recallableIds = [];
    foreach ($rows as $row) {
        $classification = mg_public_donations_recall_classify($row);
        $counts[$classification]++;
        if ($classification === 'recallable') $recallableIds[] = (int)$row['id'];
    }
    return [
        'batch' => [
            'id' => (string)$batch['public_id'],
            'allocation_operation_id' => (string)$batch['allocation_operation_public_id'],
            'quantity' => (int)$batch['quantity'],
            'recalled_quantity' => (int)$batch['recalled_quantity'],
            'status' => (string)$batch['status'],
            'created_at' => (string)$batch['created_at'],
        ],
        'campaign' => [
            'id' => (string)$batch['campaign_public_id'],
            'slug' => trim((string)$batch['public_slug']) ?: null,
            'title' => (string)$batch['campaign_title'],
        ],
        'reward_template' => [
            'id' => (string)$batch['template_public_id'],
            'title' => (string)$batch['template_title'],
            'value_cents' => (int)$batch['value_amount_cents'],
            'currency' => (string)$batch['currency'],
        ],
        'community' => [
            'assignment_id' => (string)$batch['assignment_public_id'],
            'account_id' => (string)$batch['community_account_id'],
            'display_name' => (string)$batch['display_name'],
        ],
        'counts' => $counts,
        'maximum_recall_quantity' => $counts['recallable'],
        'recallable_reward_ids' => $recallableIds,
        'downstream_recipients_protected' => true,
    ];
}

function mg_public_donations_recall_preview(PDO $pdo, int $merchantId, string $batchPublicId): array
{
    $batch = mg_public_donations_recall_batch($pdo, $merchantId, $batchPublicId, false);
    return mg_public_donations_recall_preview_from_rows($batch, mg_public_donations_recall_rows($pdo, (int)$batch['id'], false));
}

function mg_public_donations_recall_batches(PDO $pdo, int $merchantId, ?string $campaignRef = null, int $limit = 100): array
{
    $limit = max(1, min($limit, 100));
    $params = [$merchantId];
    $filter = '';
    if ($campaignRef !== null && trim($campaignRef) !== '') {
        $filter = ' AND (campaign.public_id=? OR campaign.public_slug=?)';
        $params[] = strtolower(trim($campaignRef));
        $params[] = strtolower(trim($campaignRef));
    }
    $stmt = $pdo->prepare(
        "SELECT batch.public_id,batch.quantity,batch.recalled_quantity,batch.status,batch.created_at,
                campaign.public_id AS campaign_public_id,campaign.public_slug,campaign.title AS campaign_title,
                template.public_id AS template_public_id,template.title AS template_title,
                profile.public_id AS community_account_id,
                COALESCE(NULLIF(profile.display_name,''),NULLIF(user.display_name,''),user.full_name) AS display_name
           FROM campaign_donation_batches batch
           INNER JOIN campaign_donation_operations operation ON operation.id=batch.operation_id AND operation.operation_kind='allocation'
           INNER JOIN campaigns campaign ON campaign.id=batch.campaign_id AND campaign.campaign_type='public_donation'
           INNER JOIN reward_templates template ON template.id=batch.reward_template_id
           INNER JOIN users user ON user.id=batch.community_user_id
           INNER JOIN public_profiles profile ON profile.user_id=user.id
          WHERE batch.merchant_user_id=?{$filter}
          ORDER BY batch.id DESC LIMIT {$limit}"
    );
    $stmt->execute($params);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'quantity' => (int)$row['quantity'],
        'recalled_quantity' => (int)$row['recalled_quantity'],
        'remaining_attribution_quantity' => max(0, (int)$row['quantity'] - (int)$row['recalled_quantity']),
        'status' => (string)$row['status'],
        'created_at' => (string)$row['created_at'],
        'campaign' => ['id'=>(string)$row['campaign_public_id'],'slug'=>trim((string)$row['public_slug'])?:null,'title'=>(string)$row['campaign_title']],
        'reward_template' => ['id'=>(string)$row['template_public_id'],'title'=>(string)$row['template_title']],
        'community' => ['account_id'=>(string)$row['community_account_id'],'display_name'=>(string)$row['display_name']],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_public_donations_recall_operation(PDO $pdo, int $merchantId, string $idempotencyKey, bool $forUpdate = false): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM campaign_donation_operations WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$merchantId, $idempotencyKey]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_public_donations_recall_tracking(PDO $pdo, int $merchantId, string $operationPublicId): array
{
    $stmt = $pdo->prepare(
        "SELECT operation.*,campaign.public_id AS campaign_public_id,campaign.title AS campaign_title,
                template.public_id AS template_public_id,template.title AS template_title
           FROM campaign_donation_operations operation
           INNER JOIN campaigns campaign ON campaign.id=operation.campaign_id
           INNER JOIN reward_templates template ON template.id=operation.reward_template_id
          WHERE operation.merchant_user_id=? AND operation.public_id=? AND operation.operation_kind='recall' LIMIT 1"
    );
    $stmt->execute([$merchantId, strtolower(trim($operationPublicId))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_public_donations_recall_fail('Recall operation not found.', 404);
    $meta = json_decode((string)($row['internal_note'] ?? ''), true);
    return [
        'id' => (string)$row['public_id'],
        'status' => (string)$row['status'],
        'mode' => (string)$row['operation_mode'],
        'requested_quantity' => (int)$row['requested_quantity'],
        'completed_quantity' => (int)$row['completed_quantity'],
        'inventory_before' => $row['inventory_before'] !== null ? (int)$row['inventory_before'] : null,
        'inventory_after' => $row['inventory_after'] !== null ? (int)$row['inventory_after'] : null,
        'total_stated_value_cents' => (int)$row['total_stated_value_cents'],
        'currency' => (string)$row['currency'],
        'reason' => is_array($meta) ? (string)($meta['reason'] ?? '') : '',
        'batch_id' => is_array($meta) ? (string)($meta['batch_id'] ?? '') : '',
        'campaign' => ['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title']],
        'reward_template' => ['id'=>(string)$row['template_public_id'],'title'=>(string)$row['template_title']],
        'created_at' => (string)$row['created_at'],
        'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
    ];
}

function mg_public_donations_recall_available_inventory(array $batch): ?int
{
    $values = [];
    if ($batch['campaign_quantity_limit'] !== null) $values[] = max(0, (int)$batch['campaign_quantity_limit'] - (int)$batch['campaign_issued_count']);
    if ($batch['template_quantity_limit'] !== null) $values[] = max(0, (int)$batch['template_quantity_limit'] - (int)$batch['template_issued_count']);
    return $values === [] ? null : min($values);
}

function mg_public_donations_recall_execute(
    PDO $pdo,
    int $merchantId,
    int $actorId,
    string $batchPublicId,
    int $quantity,
    string $reason,
    string $idempotencyKey
): array {
    $quantity = mg_public_donations_recall_quantity($quantity);
    $reason = mg_public_donations_recall_reason($reason);
    $idempotencyKey = mg_public_donations_recall_idempotency_key($idempotencyKey);
    $requestHash = mg_public_donations_recall_request_hash($batchPublicId, $quantity, $reason);

    $replay = mg_public_donations_recall_operation($pdo, $merchantId, $idempotencyKey, false);
    if ($replay) {
        if ((string)$replay['operation_kind'] !== 'recall' || !hash_equals((string)$replay['request_hash'], $requestHash)) {
            mg_public_donations_recall_fail('This idempotency key belongs to a different operation.', 409);
        }
        if ((string)$replay['status'] === 'completed') {
            $result = mg_public_donations_recall_tracking($pdo, $merchantId, (string)$replay['public_id']);
            $result['duplicate'] = true;
            return $result;
        }
        mg_public_donations_recall_fail('This recall is already processing.', 409);
    }

    $pdo->beginTransaction();
    try {
        // Deterministic lock order: batch/campaign/template -> rewards -> idempotency operation.
        $batch = mg_public_donations_recall_batch($pdo, $merchantId, $batchPublicId, true);
        $pdo->prepare('SELECT id FROM campaigns WHERE id=? LIMIT 1 FOR UPDATE')->execute([(int)$batch['campaign_id']]);
        $pdo->prepare('SELECT id FROM reward_templates WHERE id=? LIMIT 1 FOR UPDATE')->execute([(int)$batch['reward_template_id']]);
        $rows = mg_public_donations_recall_rows($pdo, (int)$batch['id'], true);
        $preview = mg_public_donations_recall_preview_from_rows($batch, $rows);
        if ($quantity > (int)$preview['maximum_recall_quantity']) {
            mg_public_donations_recall_fail('Requested recall quantity exceeds the currently recallable quantity.', 409);
        }

        $existing = mg_public_donations_recall_operation($pdo, $merchantId, $idempotencyKey, true);
        if ($existing) {
            if ((string)$existing['operation_kind'] !== 'recall' || !hash_equals((string)$existing['request_hash'], $requestHash)) {
                mg_public_donations_recall_fail('Recall idempotency conflict.', 409);
            }
            if ((string)$existing['status'] === 'completed') {
                $pdo->commit();
                $result = mg_public_donations_recall_tracking($pdo, $merchantId, (string)$existing['public_id']);
                $result['duplicate'] = true;
                return $result;
            }
            mg_public_donations_recall_fail('This recall is already processing.', 409);
        }

        $selected = [];
        foreach ($rows as $row) {
            if (mg_public_donations_recall_classify($row) === 'recallable') $selected[] = $row;
            if (count($selected) === $quantity) break;
        }
        if (count($selected) !== $quantity) throw new RuntimeException('Recall selection did not match the requested quantity.');

        $operationPublicId = mg_public_donations_recall_uuid();
        $inventoryBefore = mg_public_donations_recall_available_inventory($batch);
        $inventoryAfter = $inventoryBefore === null ? null : $inventoryBefore + $quantity;
        $metadata = json_encode(['batch_id'=>(string)$batch['public_id'],'reason'=>$reason], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $pdo->prepare(
            "INSERT INTO campaign_donation_operations
             (public_id,merchant_user_id,campaign_id,reward_template_id,operation_kind,operation_mode,status,
              idempotency_key,request_hash,recipient_count,requested_quantity,completed_quantity,
              inventory_before,inventory_after,total_stated_value_cents,currency,confirmation_level,
              message,internal_note,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,'recall','partial_recall','processing',?,?,?,?,0,?,?,?,?,'standard',?,?,?,NOW(),NOW())"
        )->execute([
            $operationPublicId,$merchantId,(int)$batch['campaign_id'],(int)$batch['reward_template_id'],
            $idempotencyKey,$requestHash,1,$quantity,$inventoryBefore,$inventoryAfter,
            $quantity * (int)$batch['value_amount_cents'],(string)$batch['currency'],
            'Recall of untouched Public Donations rewards',$metadata,$actorId,
        ]);
        $operationId = (int)$pdo->lastInsertId();
        $completed = 0;

        foreach ($selected as $row) {
            $originalUserId = (int)$row['original_community_user_id'];
            $walletUpdate = $pdo->prepare("UPDATE wallet_items SET status='cancelled',updated_at=NOW() WHERE id=? AND user_id=? AND status IN ('issued','viewed') AND claimed_at IS NULL AND redeemed_at IS NULL");
            $walletUpdate->execute([(int)$row['wallet_item_id'], $originalUserId]);
            if ($walletUpdate->rowCount() !== 1) throw new RuntimeException('Wallet reward became ineligible during recall.');

            $pppmFrom = (string)$row['pppm_status'];
            $pppmUpdate = $pdo->prepare("UPDATE pppm_items SET status='cancelled',cancelled_at=NOW(),version_no=version_no+1,updated_at=NOW() WHERE id=? AND owner_user_id=? AND status IN ('assigned','sent','delivered','viewed')");
            $pppmUpdate->execute([(int)$row['pppm_item_id'], $originalUserId]);
            if ($pppmUpdate->rowCount() !== 1) throw new RuntimeException('PPPM reward became ineligible during recall.');
            $pppmStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id=? LIMIT 1');
            $pppmStmt->execute([(int)$row['pppm_item_id']]);
            $pppmItem = $pppmStmt->fetch(PDO::FETCH_ASSOC);
            if (!$pppmItem) throw new RuntimeException('Recalled PPPM item not found.');
            mg_pppm_record_event($pdo, $pppmItem, 'public_donation_recalled', $pppmFrom, 'cancelled', $actorId, null, [
                'recall_operation_id'=>$operationPublicId,'donation_reward_id'=>(string)$row['public_id'],'reason'=>$reason,
            ]);

            $instance = [
                'id'=>(int)$row['microgift_instance_id'],'public_id'=>(string)$row['microgift_public_id'],
                'template_id'=>(int)$row['microgift_template_id'],'status'=>(string)$row['microgift_status'],
                'owner_user_id'=>(int)$row['microgift_owner_user_id'],'recipient_user_id'=>(int)$row['microgift_recipient_user_id'],
            ];
            mg_microgift_apply_lifecycle(
                $pdo,$instance,'cancel','public_donation_recall',$operationPublicId,
                'public-donation-recall:' . strtolower((string)$row['public_id']),$actorId,$reason
            );
            $instanceStmt = $pdo->prepare('SELECT * FROM microgift_instances WHERE id=? LIMIT 1 FOR UPDATE');
            $instanceStmt->execute([(int)$row['microgift_instance_id']]);
            $updatedInstance = $instanceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$updatedInstance || (string)$updatedInstance['status'] !== 'cancelled') throw new RuntimeException('Microgift recall lifecycle did not complete.');
            mg_action_center_project_lifecycle($pdo, $updatedInstance, [
                'merchant_user_id'=>$merchantId,'occurred_at'=>date('Y-m-d H:i:s'),
            ]);

            $rewardUpdate = $pdo->prepare("UPDATE campaign_donation_rewards SET status='recalled',recalled_at=NOW(),recalled_by_user_id=?,recall_reason=?,updated_at=NOW() WHERE id=? AND status='allocated'");
            $rewardUpdate->execute([$actorId,$reason,(int)$row['id']]);
            if ($rewardUpdate->rowCount() !== 1) throw new RuntimeException('Donation attribution became ineligible during recall.');
            $completed++;
        }

        if ($completed !== $quantity) throw new RuntimeException('Completed recall count did not match the request.');
        $newRecalled = (int)$batch['recalled_quantity'] + $completed;
        $batchStatus = $newRecalled >= (int)$batch['quantity'] ? 'recalled' : 'partially_recalled';
        $pdo->prepare('UPDATE campaign_donation_batches SET recalled_quantity=?,status=?,updated_at=NOW() WHERE id=?')
            ->execute([$newRecalled,$batchStatus,(int)$batch['id']]);
        $pdo->prepare('UPDATE campaigns SET issued_count=GREATEST(issued_count-?,0),updated_at=NOW() WHERE id=?')
            ->execute([$completed,(int)$batch['campaign_id']]);
        $pdo->prepare('UPDATE reward_templates SET issued_count=GREATEST(issued_count-?,0),updated_at=NOW() WHERE id=?')
            ->execute([$completed,(int)$batch['reward_template_id']]);
        $pdo->prepare("UPDATE campaign_donation_operations SET status='completed',completed_quantity=?,completed_at=NOW(),updated_at=NOW() WHERE id=? AND status='processing'")
            ->execute([$completed,$operationId]);

        $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,NULL,NULL,?,?,NOW())')
            ->execute([
                mg_public_donations_recall_uuid(),$merchantId,(int)$batch['campaign_id'],'public_donations.recall.completed',
                json_encode(['operation_id'=>$operationPublicId,'batch_id'=>(string)$batch['public_id'],'quantity'=>$completed,'reason'=>$reason], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

        if (function_exists('mg_create_notification')) {
            mg_create_notification(
                $pdo,(int)$batch['community_user_id'],'public_donations.rewards_recalled',
                'Public Donations rewards recalled',
                $completed . ' untouched reward' . ($completed === 1 ? '' : 's') . ' from “' . mb_substr((string)$batch['campaign_title'], 0, 160) . '” were recalled. Reason: ' . $reason,
                '/inbox.php',
                [
                    'actor_user_id'=>$actorId,'allow_self'=>true,'merchant_user_id'=>$merchantId,
                    'campaign_public_id'=>(string)$batch['campaign_public_id'],'operation_public_id'=>$operationPublicId,
                    'batch_public_id'=>(string)$batch['public_id'],'quantity'=>$completed,
                    'event_key'=>'public-donations.recall.' . strtolower($operationPublicId),
                ]
            );
        }

        $pdo->commit();
        $result = mg_public_donations_recall_tracking($pdo, $merchantId, $operationPublicId);
        $result['duplicate'] = false;
        $result['preview_after'] = mg_public_donations_recall_preview($pdo, $merchantId, (string)$batch['public_id']);
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
