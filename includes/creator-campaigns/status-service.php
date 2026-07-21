<?php
declare(strict_types=1);

function mg_creator_campaign_assert_publish_ready(PDO $pdo, array $campaign, string $toStatus): void
{
    if (!in_array($toStatus, ['scheduled', 'active'], true)) {
        return;
    }
    if (function_exists('mg_creator_campaign_builder_validation')) {
        $builderValidation = mg_creator_campaign_builder_validation($pdo, $campaign);
        if (empty($builderValidation['phase2_ready'])) {
            foreach ((array) ($builderValidation['checks'] ?? []) as $check) {
                if (($check['status'] ?? '') === 'fail') {
                    throw new DomainException((string) ($check['message'] ?? 'Complete the campaign builder before publication.'));
                }
            }
            throw new DomainException('Complete the campaign details, products, and creator eligibility before publication.');
        }
        if (!mg_creator_campaign_builder_table_exists($pdo, 'creator_campaign_agreement_versions')) {
            throw new DomainException('Campaign publication will unlock when the Agreement phase is installed. The Phase 2 builder may be saved, validated, previewed, and duplicated now.');
        }
    }
    if (trim((string) ($campaign['title'] ?? '')) === '') {
        throw new DomainException('A campaign title is required before publication.');
    }

    $focus = (string) ($campaign['campaign_focus'] ?? 'general_brand_campaign');
    $productRequired = in_array($focus, ['single_product', 'multiple_products', 'product_collection', 'microgift_offer', 'reward'], true);
    if ($productRequired) {
        $products = $pdo->prepare(
            "SELECT COUNT(*) FROM creator_campaign_products
             WHERE campaign_id=? AND relationship_type<>'excluded'"
        );
        $products->execute([(int) $campaign['id']]);
        $hasReward = !empty($campaign['featured_reward_template_id']);
        if ((int) $products->fetchColumn() < 1 && !$hasReward) {
            throw new DomainException('This campaign focus requires at least one non-excluded product or reward offer before publication.');
        }
    }

    $now = gmdate('Y-m-d H:i:s');
    $startsAt = $campaign['starts_at'] ?? null;
    $endsAt = $campaign['ends_at'] ?? null;
    if ($toStatus === 'scheduled') {
        if ($startsAt === null || $startsAt === '') {
            throw new DomainException('starts_at is required before scheduling a creator campaign.');
        }
        if ((string) $startsAt <= $now) {
            throw new DomainException('A scheduled creator campaign must start in the future.');
        }
    }
    if ($toStatus === 'active' && $startsAt !== null && $startsAt !== '' && (string) $startsAt > $now) {
        throw new DomainException('Use scheduled status when starts_at is in the future.');
    }
    if ($endsAt !== null && $endsAt !== '' && (string) $endsAt <= $now) {
        throw new DomainException('A creator campaign cannot publish after its end time.');
    }
}

function mg_creator_campaign_transition_status(
    PDO $pdo,
    array $user,
    int $campaignId,
    string $toStatus,
    array $options
): array {
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $toStatus = strtolower(trim($toStatus));
    if (!in_array($toStatus, mg_creator_campaign_statuses(), true)) {
        throw new InvalidArgumentException('Target creator campaign status is invalid.');
    }

    $idempotencyKey = mg_creator_campaign_validate_idempotency_key($options['idempotency_key'] ?? null);
    $idempotencyHash = mg_creator_campaign_idempotency_hash($idempotencyKey);
    $reason = mg_creator_campaign_string($options['reason'] ?? null, 'reason', 500, true);
    $expectedLockVersion = (int) ($options['expected_lock_version'] ?? 0);
    if ($expectedLockVersion < 1) {
        throw new InvalidArgumentException('expected_lock_version is required.');
    }

    $requestedWorkspaceId = isset($options['workspace_id']) ? (int) $options['workspace_id'] : null;
    $context = mg_creator_campaign_actor_context(
        $pdo,
        $user,
        'merchant.creator_campaigns.publish',
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
        $existingEvent = mg_creator_campaign_repository_status_event($pdo, $campaignId, $idempotencyHash);
        if ($existingEvent) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            $replay = json_decode((string) ($existingEvent['after_snapshot_json'] ?? ''), true);
            return [
                'campaign' => is_array($replay) ? $replay : $campaign,
                'idempotent_replay' => true,
                'status_event_id' => (int) $existingEvent['id'],
            ];
        }

        $fromStatus = (string) $campaign['status'];
        mg_creator_campaign_assert_publish_ready($pdo, $campaign, $toStatus);
        if (!mg_creator_campaign_can_transition($fromStatus, $toStatus)) {
            throw new DomainException('Creator campaign transition from ' . $fromStatus . ' to ' . $toStatus . ' is not allowed.');
        }
        if ((int) $campaign['lock_version'] !== $expectedLockVersion) {
            throw new DomainException('Creator campaign was updated by another request. Reload and try again.');
        }

        $set = ['status=?', 'updated_by_user_id=?', 'lock_version=lock_version+1', 'updated_at=NOW()'];
        $params = [$toStatus, $actorUserId];
        $timestampColumn = [
            'scheduled' => 'published_at',
            'active' => 'published_at',
            'paused' => 'paused_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
            'archived' => 'archived_at',
        ][$toStatus] ?? null;
        if ($timestampColumn !== null) {
            $set[] = $timestampColumn . '=COALESCE(' . $timestampColumn . ',NOW())';
        }

        $params[] = $campaignId;
        $params[] = $workspaceId;
        $params[] = $expectedLockVersion;
        $update = $pdo->prepare(
            'UPDATE creator_campaigns SET ' . implode(',', $set)
            . ' WHERE id=? AND workspace_id=? AND lock_version=?'
        );
        $update->execute($params);
        if ($update->rowCount() !== 1) {
            throw new DomainException('Creator campaign status update lost its optimistic lock.');
        }

        $after = mg_creator_campaign_repository_campaign($pdo, $campaignId, $workspaceId, false);
        $event = $pdo->prepare(
            'INSERT INTO creator_campaign_status_events
             (public_id,campaign_id,from_status,to_status,actor_user_id,reason,idempotency_hash,before_snapshot_json,after_snapshot_json,context_json,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $event->execute([
            mg_creator_campaign_public_id('ccse'),
            $campaignId,
            $fromStatus,
            $toStatus,
            $actorUserId,
            $reason,
            $idempotencyHash,
            mg_creator_campaign_json_encode($campaign),
            mg_creator_campaign_json_encode($after),
            mg_creator_campaign_json_encode([
                'workspace_role' => $context['workspace_role'] ?? null,
                'source' => $options['source'] ?? 'native_service',
            ]),
        ]);
        $eventId = (int) $pdo->lastInsertId();

        if ($ownsTransaction) {
            $pdo->commit();
        }

        mg_creator_campaign_record_audit('status_changed', $after, $actorUserId, [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'status_event_id' => $eventId,
        ]);

        return [
            'campaign' => $after,
            'idempotent_replay' => false,
            'status_event_id' => $eventId,
        ];
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
