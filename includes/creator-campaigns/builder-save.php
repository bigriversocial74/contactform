<?php
declare(strict_types=1);

function mg_creator_campaign_builder_save_step(
    PDO $pdo,
    array $user,
    string $campaignPublicId,
    int $step,
    array $input
): array {
    mg_creator_campaign_assert_transaction_boundary($pdo);
    if (!in_array($step, [1, 2, 3], true)) throw new InvalidArgumentException('Only Builder Steps 1 through 3 are writable in Phase 2.');
    $expectedLock = (int) ($input['expected_lock_version'] ?? 0);
    if ($expectedLock < 1) throw new InvalidArgumentException('expected_lock_version is required.');
    $resolved = mg_creator_campaign_builder_resolve_campaign(
        $pdo,
        $user,
        $campaignPublicId,
        'merchant.creator_campaigns.manage',
        false
    );
    $context = $resolved['context'];
    $campaignId = (int) $resolved['campaign']['id'];
    $actorId = (int) $context['actor_user_id'];

    $pdo->beginTransaction();
    try {
        $campaign = mg_creator_campaign_repository_campaign($pdo, $campaignId, (int) $context['workspace_id'], true);
        if (!in_array((string) $campaign['status'], ['draft', 'scheduled'], true)) {
            throw new DomainException('Only draft or scheduled campaigns may be edited.');
        }
        if ((int) $campaign['lock_version'] !== $expectedLock) {
            throw new DomainException('The campaign changed in another request. Reload before saving.');
        }

        $set = [];
        $params = [];
        if ($step === 1) {
            $timezone = mg_creator_campaign_validate_timezone($input['timezone'] ?? $campaign['timezone'] ?? 'UTC');
            $startsAt = mg_creator_campaign_datetime($input['starts_at'] ?? null, 'starts_at', $timezone);
            $endsAt = mg_creator_campaign_datetime($input['ends_at'] ?? null, 'ends_at', $timezone);
            $deadline = mg_creator_campaign_datetime($input['application_deadline_at'] ?? null, 'application_deadline_at', $timezone);
            if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) throw new InvalidArgumentException('End time must be later than start time.');
            if ($deadline !== null && $endsAt !== null && $deadline > $endsAt) throw new InvalidArgumentException('Application deadline may not be later than the campaign end time.');
            $accessMode = strtolower(trim((string) ($input['access_mode'] ?? $campaign['access_mode'] ?? 'open')));
            if (!in_array($accessMode, mg_creator_campaign_access_modes(), true)) throw new InvalidArgumentException('Access mode is invalid.');
            $internalReference = mg_creator_campaign_string(
                $input['internal_reference'] ?? $campaign['internal_reference'] ?? null,
                'internal_reference',
                100,
                true
            );
            $referenceCheck = $pdo->prepare(
                'SELECT 1 FROM creator_campaigns WHERE workspace_id=? AND internal_reference=? AND id<>? LIMIT 1'
            );
            $referenceCheck->execute([(int) $context['workspace_id'], $internalReference, $campaignId]);
            if ($referenceCheck->fetchColumn()) {
                throw new DomainException('Internal campaign reference already exists in this workspace.');
            }
            $fields = [
                'internal_reference' => $internalReference,
                'title' => mg_creator_campaign_string($input['title'] ?? null, 'title', 180, true),
                'description' => mg_creator_campaign_string($input['description'] ?? null, 'description', 16000),
                'objective' => mg_creator_campaign_string($input['objective'] ?? null, 'objective', 180, true),
                'category' => mg_creator_campaign_string($input['category'] ?? null, 'category', 100, true),
                'access_mode' => $accessMode,
                'timezone' => $timezone,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'application_deadline_at' => $deadline,
                'geographic_scope_json' => mg_creator_campaign_json_encode(mg_creator_campaign_json_value($input['geographic_scope'] ?? null, 'geographic_scope')),
                'cover_asset_id' => mg_creator_campaign_builder_resolve_asset($pdo, $context, $input['cover_asset_public_id'] ?? ''),
                'campaign_manager_user_id' => mg_creator_campaign_builder_resolve_manager($pdo, $context, $input['campaign_manager_key'] ?? 'owner'),
            ];
            foreach ($fields as $column => $value) {
                $set[] = $column . '=?';
                $params[] = $value;
            }
        } elseif ($step === 2) {
            $focus = strtolower(trim((string) ($input['campaign_focus'] ?? 'general_brand_campaign')));
            $productAccess = strtolower(trim((string) ($input['creator_product_access'] ?? 'none')));
            if (!in_array($focus, mg_creator_campaign_focuses(), true)) throw new InvalidArgumentException('Campaign focus is invalid.');
            if (!in_array($productAccess, mg_creator_campaign_product_access_modes(), true)) throw new InvalidArgumentException('Creator product access is invalid.');
            $landingUrl = mg_creator_campaign_string($input['creator_landing_url'] ?? null, 'creator_landing_url', 500);
            if ($landingUrl !== null && filter_var($landingUrl, FILTER_VALIDATE_URL) === false) throw new InvalidArgumentException('Creator landing destination must be a valid URL.');
            $set = [
                'campaign_focus=?', 'featured_reward_template_id=?', 'creator_product_access=?',
                'creator_landing_url=?',
            ];
            $params = [
                $focus,
                mg_creator_campaign_builder_resolve_reward($pdo, $context, $input['featured_reward_public_id'] ?? ''),
                $productAccess,
                $landingUrl,
            ];
            $productRows = mg_creator_campaign_builder_product_rows($pdo, $context, is_array($input['products'] ?? null) ? $input['products'] : []);
            $pdo->prepare('DELETE FROM creator_campaign_products WHERE campaign_id=?')->execute([$campaignId]);
            $insertProduct = $pdo->prepare(
                'INSERT INTO creator_campaign_products
                 (public_id,campaign_id,product_id,selected_product_version_id,relationship_type,sort_order,value_snapshot_cents,currency,
                  created_by_user_id,updated_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
            );
            foreach ($productRows as $row) {
                $insertProduct->execute([
                    mg_creator_campaign_public_id('ccp'), $campaignId, $row['product_id'], $row['version_id'],
                    $row['relationship_type'], $row['sort_order'], $row['value_snapshot_cents'], $row['currency'],
                    $actorId, $actorId,
                ]);
            }
        } else {
            $accessMode = strtolower(trim((string) ($input['access_mode'] ?? $campaign['access_mode'] ?? 'open')));
            $preference = strtolower(trim((string) ($input['existing_creator_preference'] ?? 'none')));
            if (!in_array($accessMode, mg_creator_campaign_access_modes(), true)) throw new InvalidArgumentException('Access mode is invalid.');
            if (!in_array($preference, mg_creator_campaign_existing_creator_preferences(), true)) throw new InvalidArgumentException('Existing creator preference is invalid.');
            $maximumCreators = $input['maximum_approved_creators'] ?? null;
            $maximumApplications = $input['maximum_applications'] ?? null;
            foreach (['maximum_approved_creators' => $maximumCreators, 'maximum_applications' => $maximumApplications] as $name => $value) {
                if ($value !== null && $value !== '' && (!is_numeric($value) || (int) $value < 1 || (int) $value > 100000)) {
                    throw new InvalidArgumentException($name . ' must be between 1 and 100000.');
                }
            }
            $timezone = (string) ($campaign['timezone'] ?? 'UTC');
            $deadline = mg_creator_campaign_datetime($input['application_deadline_at'] ?? null, 'application_deadline_at', $timezone);
            if ($deadline !== null && !empty($campaign['ends_at']) && $deadline > (string) $campaign['ends_at']) {
                throw new InvalidArgumentException('Application deadline may not be later than the campaign end time.');
            }
            $automaticAcceptance = !empty($input['automatic_acceptance']);
            if ($automaticAcceptance && !mg_creator_campaign_builder_table_exists($pdo, 'creator_campaign_participants')) {
                throw new DomainException('Automatic acceptance is unavailable until Creator Participation is installed.');
            }
            $set = [
                'access_mode=?', 'maximum_approved_creators=?', 'maximum_applications=?',
                'automatic_acceptance=?', 'existing_creator_preference=?', 'application_deadline_at=?',
            ];
            $params = [
                $accessMode,
                $maximumCreators === null || $maximumCreators === '' ? null : (int) $maximumCreators,
                $maximumApplications === null || $maximumApplications === '' ? null : (int) $maximumApplications,
                $automaticAcceptance ? 1 : 0,
                $preference,
                $deadline,
            ];
            $rules = is_array($input['eligibility_rules'] ?? null) ? $input['eligibility_rules'] : [];
            if (count($rules) > 50) throw new InvalidArgumentException('A campaign may not contain more than 50 eligibility rules.');
            $normalizedRules = array_map('mg_creator_campaign_normalize_eligibility_rule', $rules);
            $questions = is_array($input['application_questions'] ?? null) ? $input['application_questions'] : [];
            if (count($questions) > 25) throw new InvalidArgumentException('A campaign may not contain more than 25 application questions.');
            $normalizedQuestions = [];
            foreach ($questions as $index => $question) {
                if (!is_array($question)) throw new InvalidArgumentException('Application questions must be objects.');
                $normalizedQuestions[] = mg_creator_campaign_builder_normalize_question($question, $index);
            }
            $pdo->prepare('DELETE FROM creator_campaign_eligibility_rules WHERE campaign_id=?')->execute([$campaignId]);
            $insertRule = $pdo->prepare(
                'INSERT INTO creator_campaign_eligibility_rules
                 (public_id,campaign_id,rule_type,operator_key,value_json,is_required,sort_order,created_by_user_id,updated_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())'
            );
            foreach ($normalizedRules as $index => $rule) {
                $insertRule->execute([
                    mg_creator_campaign_public_id('ccer'), $campaignId, $rule['rule_type'], $rule['operator_key'],
                    $rule['value_json'], $rule['is_required'] ? 1 : 0, $rule['sort_order'] ?: $index, $actorId, $actorId,
                ]);
            }
            $pdo->prepare('DELETE FROM creator_campaign_application_questions WHERE campaign_id=?')->execute([$campaignId]);
            $insertQuestion = $pdo->prepare(
                'INSERT INTO creator_campaign_application_questions
                 (public_id,campaign_id,prompt,helper_text,question_type,options_json,is_required,sort_order,created_by_user_id,updated_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
            );
            foreach ($normalizedQuestions as $question) {
                $insertQuestion->execute([
                    mg_creator_campaign_public_id('ccaq'), $campaignId, $question['prompt'], $question['helper_text'],
                    $question['question_type'], $question['options_json'], $question['is_required'], $question['sort_order'],
                    $actorId, $actorId,
                ]);
            }
        }

        $completed = mg_creator_campaign_builder_mark_step($campaign, $step);
        $set[] = 'builder_step=?';
        $params[] = min(10, max((int) ($campaign['builder_step'] ?? 1), $step + 1));
        $set[] = 'builder_completed_steps_json=?';
        $params[] = mg_creator_campaign_json_encode($completed);
        $set[] = 'builder_version=builder_version+1';
        $set[] = 'updated_by_user_id=?';
        $params[] = $actorId;
        $set[] = 'lock_version=lock_version+1';
        $set[] = 'updated_at=NOW()';
        $params[] = $campaignId;
        $params[] = (int) $context['workspace_id'];
        $params[] = $expectedLock;
        $update = $pdo->prepare(
            'UPDATE creator_campaigns SET ' . implode(',', $set) . ' WHERE id=? AND workspace_id=? AND lock_version=?'
        );
        $update->execute($params);
        if ($update->rowCount() !== 1) throw new DomainException('The builder save lost its optimistic lock.');

        $afterRaw = mg_creator_campaign_repository_hydrate(
            $pdo,
            mg_creator_campaign_repository_campaign($pdo, $campaignId, (int) $context['workspace_id'])
        );
        $validation = mg_creator_campaign_builder_validation($pdo, $afterRaw);
        $pdo->prepare('UPDATE creator_campaigns SET builder_validation_json=? WHERE id=?')->execute([
            mg_creator_campaign_json_encode($validation), $campaignId,
        ]);
        $afterRaw['builder_validation_json'] = mg_creator_campaign_json_encode($validation);
        $pdo->commit();

        mg_creator_campaign_record_audit('builder_step_saved', $afterRaw, $actorId, [
            'builder_step' => $step,
            'phase2_score' => $validation['phase2_score'],
        ]);
        return mg_creator_campaign_builder_present($pdo, $afterRaw, true);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
