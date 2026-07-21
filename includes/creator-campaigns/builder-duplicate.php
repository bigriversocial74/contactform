<?php
declare(strict_types=1);

function mg_creator_campaign_builder_duplicate(
    PDO $pdo,
    array $user,
    string $campaignPublicId,
    string $idempotencyKey
): array {
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $idempotencyKey = mg_creator_campaign_validate_idempotency_key($idempotencyKey);
    $resolved = mg_creator_campaign_builder_resolve_campaign($pdo, $user, $campaignPublicId, 'merchant.creator_campaigns.manage');
    $context = $resolved['context'];
    $source = mg_creator_campaign_repository_hydrate($pdo, $resolved['campaign']);
    $sourceId = (int) $source['id'];
    $actorId = (int) $context['actor_user_id'];
    $hash = mg_creator_campaign_idempotency_hash('duplicate:' . $idempotencyKey);

    $pdo->beginTransaction();
    try {
        $existing = mg_creator_campaign_repository_by_idempotency($pdo, (int) $context['workspace_id'], $hash, true);
        if ($existing) {
            $pdo->commit();
            return mg_creator_campaign_builder_present($pdo, mg_creator_campaign_repository_hydrate($pdo, $existing), true);
        }
        $reference = substr((string) $source['internal_reference'], 0, 76) . '-COPY-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $title = substr((string) $source['title'] . ' Copy', 0, 180);
        $insert = $pdo->prepare(
            "INSERT INTO creator_campaigns
             (public_id,workspace_id,campaign_manager_user_id,internal_reference,title,description,objective,category,campaign_focus,
              access_mode,status,timezone,starts_at,ends_at,application_deadline_at,geographic_scope_json,cover_asset_id,
              featured_reward_template_id,creator_product_access,creator_landing_url,maximum_approved_creators,
              maximum_applications,automatic_acceptance,existing_creator_preference,builder_step,builder_completed_steps_json,
              builder_validation_json,builder_version,metadata_json,creation_idempotency_hash,lock_version,created_by_user_id,
              updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,'draft',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,1,?,?,NOW(),NOW())"
        );
        $insert->execute([
            mg_creator_campaign_public_id(), (int) $context['workspace_id'], $source['campaign_manager_user_id'], $reference,
            $title, $source['description'], $source['objective'], $source['category'], $source['campaign_focus'], $source['access_mode'],
            $source['timezone'], $source['starts_at'], $source['ends_at'], $source['application_deadline_at'],
            $source['geographic_scope_json'], $source['cover_asset_id'], $source['featured_reward_template_id'],
            $source['creator_product_access'], $source['creator_landing_url'],
            $source['maximum_approved_creators'], $source['maximum_applications'], $source['automatic_acceptance'],
            $source['existing_creator_preference'], min(10, (int) ($source['builder_step'] ?? 1)),
            $source['builder_completed_steps_json'], null, $source['metadata_json'], $hash, $actorId, $actorId,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $productInsert = $pdo->prepare(
            'INSERT INTO creator_campaign_products
             (public_id,campaign_id,product_id,selected_product_version_id,relationship_type,sort_order,value_snapshot_cents,currency,
              created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        foreach ($source['products'] as $row) {
            $productInsert->execute([
                mg_creator_campaign_public_id('ccp'), $newId, (int) $row['product_id'],
                $row['selected_product_version_id'] === null ? null : (int) $row['selected_product_version_id'],
                (string) $row['relationship_type'], (int) $row['sort_order'],
                $row['value_snapshot_cents'] === null ? null : (int) $row['value_snapshot_cents'],
                $row['currency'], $actorId, $actorId,
            ]);
        }

        $ruleInsert = $pdo->prepare(
            'INSERT INTO creator_campaign_eligibility_rules
             (public_id,campaign_id,rule_type,operator_key,value_json,is_required,sort_order,created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        foreach ($source['eligibility_rules'] as $row) {
            $ruleInsert->execute([
                mg_creator_campaign_public_id('ccer'), $newId, (string) $row['rule_type'],
                (string) $row['operator_key'], $row['value_json'], (int) $row['is_required'],
                (int) $row['sort_order'], $actorId, $actorId,
            ]);
        }

        $questionInsert = $pdo->prepare(
            'INSERT INTO creator_campaign_application_questions
             (public_id,campaign_id,prompt,helper_text,question_type,options_json,is_required,sort_order,created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        foreach (mg_creator_campaign_builder_questions($pdo, $sourceId) as $row) {
            $questionInsert->execute([
                mg_creator_campaign_public_id('ccaq'), $newId, (string) $row['prompt'], $row['helper_text'],
                (string) $row['question_type'], empty($row['options']) ? null : mg_creator_campaign_json_encode($row['options']),
                !empty($row['is_required']) ? 1 : 0, (int) $row['sort_order'], $actorId, $actorId,
            ]);
        }

        $copy = mg_creator_campaign_repository_hydrate(
            $pdo,
            mg_creator_campaign_repository_campaign($pdo, $newId, (int) $context['workspace_id'])
        );
        $validation = mg_creator_campaign_builder_validation($pdo, $copy);
        $pdo->prepare('UPDATE creator_campaigns SET builder_validation_json=? WHERE id=?')->execute([
            mg_creator_campaign_json_encode($validation), $newId,
        ]);
        $copy['builder_validation_json'] = mg_creator_campaign_json_encode($validation);
        $event = $pdo->prepare(
            "INSERT INTO creator_campaign_status_events
             (public_id,campaign_id,from_status,to_status,actor_user_id,reason,idempotency_hash,after_snapshot_json,context_json,created_at)
             VALUES (?, ?, NULL, 'draft', ?, ?, ?, ?, ?, NOW())"
        );
        $event->execute([
            mg_creator_campaign_public_id('ccse'), $newId, $actorId, 'Creator campaign duplicated.',
            mg_creator_campaign_idempotency_hash('duplicate-status:' . $idempotencyKey),
            mg_creator_campaign_json_encode($copy),
            mg_creator_campaign_json_encode(['source_campaign_public_id' => $campaignPublicId]),
        ]);
        $pdo->commit();
        mg_creator_campaign_record_audit('duplicated', $copy, $actorId, ['source_campaign_public_id' => $campaignPublicId]);
        return mg_creator_campaign_builder_present($pdo, $copy, true);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
