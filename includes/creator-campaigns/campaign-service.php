<?php
declare(strict_types=1);

function mg_creator_campaign_create_draft(PDO $pdo, array $user, array $input): array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $normalized = mg_creator_campaign_normalize_create_input($input);
    $requestedWorkspaceId = isset($input['workspace_id']) ? (int) $input['workspace_id'] : null;
    $context = mg_creator_campaign_actor_context(
        $pdo,
        $user,
        'merchant.creator_campaigns.manage',
        $requestedWorkspaceId
    );
    $actorUserId = (int) $context['actor_user_id'];
    $workspaceId = (int) $context['workspace_id'];

    if ($normalized['cover_asset_id'] !== null) {
        mg_creator_campaign_repository_assert_asset_owned($pdo, $workspaceId, $normalized['cover_asset_id']);
    }

    $ownsTransaction = !$pdo->inTransaction();
    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $existing = mg_creator_campaign_repository_by_idempotency(
            $pdo,
            $workspaceId,
            $normalized['idempotency_hash'],
            true
        );
        if ($existing) {
            $campaign = mg_creator_campaign_repository_hydrate($pdo, $existing);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return ['campaign' => $campaign, 'idempotent_replay' => true];
        }

        $publicId = mg_creator_campaign_public_id();
        $insert = $pdo->prepare(
            'INSERT INTO creator_campaigns
             (public_id,workspace_id,internal_reference,title,description,objective,category,access_mode,status,timezone,
              starts_at,ends_at,application_deadline_at,geographic_scope_json,cover_asset_id,metadata_json,
              creation_idempotency_hash,lock_version,created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,\'draft\',?,?,?,?,?,?,?,?,1,?,?,NOW(),NOW())'
        );
        $insert->execute([
            $publicId,
            $workspaceId,
            $normalized['internal_reference'],
            $normalized['title'],
            $normalized['description'],
            $normalized['objective'],
            $normalized['category'],
            $normalized['access_mode'],
            $normalized['timezone'],
            $normalized['starts_at'],
            $normalized['ends_at'],
            $normalized['application_deadline_at'],
            mg_creator_campaign_json_encode($normalized['geographic_scope']),
            $normalized['cover_asset_id'],
            mg_creator_campaign_json_encode($normalized['metadata']),
            $normalized['idempotency_hash'],
            $actorUserId,
            $actorUserId,
        ]);
        $campaignId = (int) $pdo->lastInsertId();
        $campaign = mg_creator_campaign_repository_campaign($pdo, $campaignId, $workspaceId);

        $event = $pdo->prepare(
            'INSERT INTO creator_campaign_status_events
             (public_id,campaign_id,from_status,to_status,actor_user_id,reason,idempotency_hash,before_snapshot_json,after_snapshot_json,context_json,created_at)
             VALUES (?,?,NULL,\'draft\',?,?,?,?,?,?,NOW())'
        );
        $event->execute([
            mg_creator_campaign_public_id('ccse'),
            $campaignId,
            $actorUserId,
            'Creator campaign draft created.',
            mg_creator_campaign_idempotency_hash('create-status:' . $normalized['idempotency_key']),
            null,
            mg_creator_campaign_json_encode($campaign),
            mg_creator_campaign_json_encode([
                'workspace_role' => $context['workspace_role'] ?? null,
                'source' => 'native_service',
            ]),
        ]);

        $campaign = mg_creator_campaign_repository_hydrate($pdo, $campaign);
        if ($ownsTransaction) {
            $pdo->commit();
        }

        mg_creator_campaign_record_audit('created', $campaign, $actorUserId, [
            'internal_reference' => $campaign['internal_reference'],
        ]);
        return ['campaign' => $campaign, 'idempotent_replay' => false];
    } catch (PDOException $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string) $error->getCode() === '23000') {
            $existing = mg_creator_campaign_repository_by_idempotency(
                $pdo,
                $workspaceId,
                $normalized['idempotency_hash']
            );
            if ($existing) {
                return [
                    'campaign' => mg_creator_campaign_repository_hydrate($pdo, $existing),
                    'idempotent_replay' => true,
                ];
            }
        }
        throw $error;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_creator_campaign_get(
    PDO $pdo,
    array $user,
    int $campaignId,
    ?int $requestedWorkspaceId = null
): array {
    $context = mg_creator_campaign_actor_context(
        $pdo,
        $user,
        'merchant.creator_campaigns.view',
        $requestedWorkspaceId
    );
    return mg_creator_campaign_repository_hydrate(
        $pdo,
        mg_creator_campaign_repository_campaign($pdo, $campaignId, (int) $context['workspace_id'])
    );
}

function mg_creator_campaign_update_draft(
    PDO $pdo,
    array $user,
    int $campaignId,
    array $input,
    array $options
): array {
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $expectedLockVersion = (int) ($options['expected_lock_version'] ?? 0);
    if ($expectedLockVersion < 1) {
        throw new InvalidArgumentException('expected_lock_version is required.');
    }
    $requestedWorkspaceId = isset($options['workspace_id']) ? (int) $options['workspace_id'] : null;
    $context = mg_creator_campaign_actor_context(
        $pdo,
        $user,
        'merchant.creator_campaigns.manage',
        $requestedWorkspaceId
    );
    $actorUserId = (int) $context['actor_user_id'];
    $workspaceId = (int) $context['workspace_id'];
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $campaign = mg_creator_campaign_repository_campaign($pdo, $campaignId, $workspaceId, true);
        if (!in_array((string) $campaign['status'], ['draft', 'scheduled'], true)) {
            throw new DomainException('Only draft or scheduled creator campaigns may be edited.');
        }
        if ((int) $campaign['lock_version'] !== $expectedLockVersion) {
            throw new DomainException('Creator campaign was updated by another request. Reload and try again.');
        }

        $validationInput = $input;
        $injectedTimezone = false;
        $hasDateField = array_key_exists('starts_at', $input)
            || array_key_exists('ends_at', $input)
            || array_key_exists('application_deadline_at', $input);
        if ($hasDateField && !array_key_exists('timezone', $validationInput)) {
            $validationInput['timezone'] = (string) ($campaign['timezone'] ?? 'UTC');
            $injectedTimezone = true;
        }
        $normalized = mg_creator_campaign_normalize_update_input($validationInput);
        if ($injectedTimezone) {
            unset($normalized['timezone']);
        }

        if (array_key_exists('cover_asset_id', $normalized) && $normalized['cover_asset_id'] !== null) {
            mg_creator_campaign_repository_assert_asset_owned($pdo, $workspaceId, (int) $normalized['cover_asset_id']);
        }

        $prospectiveStarts = array_key_exists('starts_at', $normalized) ? $normalized['starts_at'] : $campaign['starts_at'];
        $prospectiveEnds = array_key_exists('ends_at', $normalized) ? $normalized['ends_at'] : $campaign['ends_at'];
        $prospectiveDeadline = array_key_exists('application_deadline_at', $normalized)
            ? $normalized['application_deadline_at']
            : $campaign['application_deadline_at'];
        if ($prospectiveStarts !== null && $prospectiveEnds !== null && $prospectiveEnds <= $prospectiveStarts) {
            throw new InvalidArgumentException('ends_at must be later than starts_at.');
        }
        if ($prospectiveDeadline !== null && $prospectiveEnds !== null && $prospectiveDeadline > $prospectiveEnds) {
            throw new InvalidArgumentException('application_deadline_at may not be later than ends_at.');
        }

        $allowedColumns = [
            'title', 'description', 'objective', 'category', 'access_mode', 'timezone',
            'starts_at', 'ends_at', 'application_deadline_at', 'geographic_scope_json',
            'metadata_json', 'cover_asset_id',
        ];
        $set = [];
        $params = [];
        foreach ($normalized as $column => $value) {
            if (!in_array($column, $allowedColumns, true)) {
                throw new LogicException('Unsafe creator campaign update column.');
            }
            $set[] = $column . '=?';
            $params[] = $value;
        }
        $set[] = 'updated_by_user_id=?';
        $params[] = $actorUserId;
        $set[] = 'lock_version=lock_version+1';
        $set[] = 'updated_at=NOW()';
        $params[] = $campaignId;
        $params[] = $workspaceId;
        $params[] = $expectedLockVersion;

        $update = $pdo->prepare(
            'UPDATE creator_campaigns SET ' . implode(',', $set)
            . ' WHERE id=? AND workspace_id=? AND lock_version=?'
        );
        $update->execute($params);
        if ($update->rowCount() !== 1) {
            throw new DomainException('Creator campaign update lost its optimistic lock.');
        }

        $after = mg_creator_campaign_repository_hydrate(
            $pdo,
            mg_creator_campaign_repository_campaign($pdo, $campaignId, $workspaceId)
        );
        if ($ownsTransaction) {
            $pdo->commit();
        }
        mg_creator_campaign_record_audit('updated', $after, $actorUserId, [
            'changed_fields' => array_keys($normalized),
        ]);
        return $after;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_creator_campaign_attach_product(
    PDO $pdo,
    array $user,
    int $campaignId,
    int $productId,
    array $linkInput,
    array $options
): array {
    mg_creator_campaign_assert_transaction_boundary($pdo);
    if ($productId < 1) {
        throw new InvalidArgumentException('product_id is required.');
    }
    $expectedLockVersion = (int) ($options['expected_lock_version'] ?? 0);
    if ($expectedLockVersion < 1) {
        throw new InvalidArgumentException('expected_lock_version is required.');
    }
    $link = mg_creator_campaign_normalize_product_link($linkInput);
    $requestedWorkspaceId = isset($options['workspace_id']) ? (int) $options['workspace_id'] : null;
    $context = mg_creator_campaign_actor_context(
        $pdo,
        $user,
        'merchant.creator_campaigns.manage',
        $requestedWorkspaceId
    );
    $actorUserId = (int) $context['actor_user_id'];
    $workspaceId = (int) $context['workspace_id'];
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $campaign = mg_creator_campaign_repository_campaign($pdo, $campaignId, $workspaceId, true);
        if (!in_array((string) $campaign['status'], ['draft', 'scheduled'], true)) {
            throw new DomainException('Products may only be changed while a campaign is draft or scheduled.');
        }
        if ((int) $campaign['lock_version'] !== $expectedLockVersion) {
            throw new DomainException('Creator campaign was updated by another request. Reload and try again.');
        }

        $product = mg_creator_campaign_repository_assert_product_owned(
            $pdo,
            $workspaceId,
            $productId,
            $link['selected_product_version_id']
        );
        if ($link['relationship_type'] === 'primary') {
            $primary = $pdo->prepare(
                "SELECT product_id FROM creator_campaign_products
                 WHERE campaign_id=? AND relationship_type='primary' AND product_id<>? LIMIT 1"
            );
            $primary->execute([$campaignId, $productId]);
            if ($primary->fetchColumn()) {
                throw new DomainException('A creator campaign may have only one primary product.');
            }
        }

        if ($link['selected_product_version_id'] !== null && isset($product['selected_version'])) {
            $version = $product['selected_version'];
            if ($link['value_snapshot_cents'] === null) {
                $link['value_snapshot_cents'] = (int) $version['unit_value_cents'];
            }
            if ($link['currency'] === null) {
                $link['currency'] = strtoupper((string) $version['currency']);
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO creator_campaign_products
             (public_id,campaign_id,product_id,selected_product_version_id,relationship_type,sort_order,value_snapshot_cents,currency,
              created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE selected_product_version_id=VALUES(selected_product_version_id),sort_order=VALUES(sort_order),
              value_snapshot_cents=VALUES(value_snapshot_cents),currency=VALUES(currency),updated_by_user_id=VALUES(updated_by_user_id),updated_at=NOW()'
        );
        $stmt->execute([
            mg_creator_campaign_public_id('ccp'),
            $campaignId,
            $productId,
            $link['selected_product_version_id'],
            $link['relationship_type'],
            $link['sort_order'],
            $link['value_snapshot_cents'],
            $link['currency'],
            $actorUserId,
            $actorUserId,
        ]);

        $update = $pdo->prepare(
            'UPDATE creator_campaigns SET updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND workspace_id=? AND lock_version=?'
        );
        $update->execute([$actorUserId, $campaignId, $workspaceId, $expectedLockVersion]);
        if ($update->rowCount() !== 1) {
            throw new DomainException('Creator campaign product update lost its optimistic lock.');
        }

        $after = mg_creator_campaign_repository_hydrate(
            $pdo,
            mg_creator_campaign_repository_campaign($pdo, $campaignId, $workspaceId)
        );
        if ($ownsTransaction) {
            $pdo->commit();
        }
        mg_creator_campaign_record_audit('product_attached', $after, $actorUserId, [
            'product_id' => $productId,
            'relationship_type' => $link['relationship_type'],
        ]);
        return $after;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
