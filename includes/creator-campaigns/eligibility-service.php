<?php
declare(strict_types=1);

/**
 * Replace the native eligibility-rule projection for a draft/scheduled campaign.
 * Full creator matching and invitation behavior are intentionally deferred.
 */
function mg_creator_campaign_replace_eligibility_rules(
    PDO $pdo,
    array $user,
    int $campaignId,
    array $rules,
    array $options
): array {
    mg_creator_campaign_assert_transaction_boundary($pdo);
    if (count($rules) > 50) {
        throw new InvalidArgumentException('A creator campaign may not contain more than 50 eligibility rules.');
    }
    $normalizedRules = array_map('mg_creator_campaign_normalize_eligibility_rule', $rules);
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
            throw new DomainException('Eligibility rules may only be changed while a campaign is draft or scheduled.');
        }
        if ((int) $campaign['lock_version'] !== $expectedLockVersion) {
            throw new DomainException('Creator campaign was updated by another request. Reload and try again.');
        }

        $pdo->prepare('DELETE FROM creator_campaign_eligibility_rules WHERE campaign_id=?')->execute([$campaignId]);
        $insert = $pdo->prepare(
            'INSERT INTO creator_campaign_eligibility_rules
             (public_id,campaign_id,rule_type,operator_key,value_json,is_required,sort_order,created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        foreach ($normalizedRules as $index => $rule) {
            $insert->execute([
                mg_creator_campaign_public_id('ccer'),
                $campaignId,
                $rule['rule_type'],
                $rule['operator_key'],
                $rule['value_json'],
                $rule['is_required'] ? 1 : 0,
                $rule['sort_order'] ?: $index,
                $actorUserId,
                $actorUserId,
            ]);
        }

        $update = $pdo->prepare(
            'UPDATE creator_campaigns
             SET updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND workspace_id=? AND lock_version=?'
        );
        $update->execute([$actorUserId, $campaignId, $workspaceId, $expectedLockVersion]);
        if ($update->rowCount() !== 1) {
            throw new DomainException('Creator campaign eligibility update lost its optimistic lock.');
        }

        $after = mg_creator_campaign_repository_hydrate(
            $pdo,
            mg_creator_campaign_repository_campaign($pdo, $campaignId, $workspaceId)
        );
        if ($ownsTransaction) {
            $pdo->commit();
        }

        mg_creator_campaign_record_audit('eligibility_replaced', $after, $actorUserId, [
            'rule_count' => count($normalizedRules),
        ]);
        return $after;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
