<?php
declare(strict_types=1);

function mg_creator_campaign_onboarding_event(
    PDO $pdo,
    array $onboarding,
    int $ownerUserId,
    string $eventType,
    ?string $stepKey,
    string $severity,
    string $note,
    array $metadata = []
): void {
    if (!in_array($severity, ['info','low','medium','high','critical'], true)) $severity = 'info';
    $pdo->prepare(
        'INSERT INTO creator_campaign_onboarding_events
         (public_id,onboarding_id,owner_user_id,event_type,step_key,severity,note,metadata_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        mg_public_uuid(),
        (int)$onboarding['id'],
        $ownerUserId,
        mb_substr($eventType, 0, 120),
        $stepKey !== null ? mb_substr($stepKey, 0, 80) : null,
        $severity,
        mb_substr(trim($note), 0, 2000),
        $metadata === [] ? null : mg_creator_campaign_onboarding_encode($metadata),
    ]);
}

function mg_creator_campaign_onboarding_audit(
    array $onboarding,
    int $ownerUserId,
    string $action,
    array $context = []
): void {
    mg_audit('creator_campaign.onboarding.' . $action, 'creator_campaign_merchant_onboarding', [
        'onboarding_id'=>(string)$onboarding['public_id'],
        'status'=>(string)$onboarding['status'],
        'current_step'=>(int)$onboarding['current_step'],
    ] + $context, $ownerUserId);
    mg_event('creator_campaign.onboarding.' . $action, [
        'onboarding_id'=>(string)$onboarding['public_id'],
        'workspace_id'=>(int)$onboarding['workspace_id'],
        'status'=>(string)$onboarding['status'],
    ] + $context, $ownerUserId);
}

function mg_creator_campaign_onboarding_update(
    PDO $pdo,
    array $user,
    array $workspace,
    array $fields,
    string $eventType,
    string $stepKey,
    string $note,
    array $metadata = []
): array {
    $ownerId = (int)($user['id'] ?? 0);
    $workspaceId = (int)($workspace['id'] ?? 0);
    $allowed = [
        'status','current_step','primary_operator_user_id','support_contact','pilot_goal',
        'expected_campaign_volume','intended_launch_date','business_defaults_json',
        'product_selection_json','compensation_defaults_json','creator_preferences_json',
        'operator_roles_json','first_campaign_id','readiness_snapshot_json','enrolled_at',
        'ready_at','activated_at','completed_at','last_smoke_test_at',
    ];
    foreach (array_keys($fields) as $column) {
        if (!in_array($column, $allowed, true)) {
            throw new LogicException('Unsafe onboarding update column: ' . $column);
        }
    }
    if ($fields === []) throw new LogicException('Onboarding update requires fields.');

    $pdo->beginTransaction();
    try {
        $onboarding = mg_creator_campaign_onboarding_row($pdo, $ownerId, $workspaceId, true);
        if (!$onboarding) throw new MgCreatorCampaignOnboardingException('Merchant onboarding was not found.', 404);
        $set = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $set[] = $column . '=?';
            $params[] = $value;
        }
        $set[] = 'updated_at=NOW()';
        $params[] = (int)$onboarding['id'];
        $pdo->prepare('UPDATE creator_campaign_merchant_onboarding SET ' . implode(',', $set) . ' WHERE id=?')->execute($params);
        $after = mg_creator_campaign_onboarding_row($pdo, $ownerId, $workspaceId, false);
        if (!$after) throw new RuntimeException('Updated onboarding record could not be read.');
        mg_creator_campaign_onboarding_event($pdo, $after, $ownerId, $eventType, $stepKey, 'info', $note, $metadata);
        $pdo->commit();
        mg_creator_campaign_onboarding_audit($after, $ownerId, str_replace('creator_campaign.onboarding.', '', $eventType), $metadata);
        return $after;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_creator_campaign_onboarding_save_enrollment(PDO $pdo, array $user, array $workspace, array $input): array
{
    if (!mg_creator_campaign_onboarding_bool($input['pilot_boundaries_accepted'] ?? false)) {
        throw new MgCreatorCampaignOnboardingException('Accept the pilot operating boundaries before enrolling.');
    }
    $ownerId = (int)$user['id'];
    $operatorId = mg_creator_campaign_onboarding_resolve_team_user(
        $pdo,
        (int)$workspace['id'],
        $ownerId,
        trim((string)($input['primary_operator'] ?? 'owner'))
    );
    $launchDate = mg_creator_campaign_onboarding_date($input['intended_launch_date'] ?? null, 'intended launch date');
    if ($launchDate !== null && $launchDate < gmdate('Y-m-d')) {
        throw new MgCreatorCampaignOnboardingException('The intended launch date cannot be in the past.');
    }
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'enrolled',
        'current_step'=>2,
        'primary_operator_user_id'=>$operatorId,
        'support_contact'=>mg_creator_campaign_onboarding_text($input['support_contact'] ?? '', 'support contact', 255, true, 3),
        'pilot_goal'=>mg_creator_campaign_onboarding_text($input['pilot_goal'] ?? '', 'pilot goal', 1000, true, 8),
        'expected_campaign_volume'=>mg_creator_campaign_onboarding_text($input['expected_campaign_volume'] ?? '', 'expected campaign volume', 120, true, 1),
        'intended_launch_date'=>$launchDate,
        'enrolled_at'=>gmdate('Y-m-d H:i:s'),
    ], 'creator_campaign.onboarding.enrolled', 'enrollment', 'Merchant accepted pilot boundaries and enrolled.');
}

function mg_creator_campaign_onboarding_save_business(PDO $pdo, array $user, array $workspace, array $input): array
{
    $defaults = [
        'business_category'=>mg_creator_campaign_onboarding_text($input['business_category'] ?? '', 'business category', 100, true, 2),
        'brand_description'=>mg_creator_campaign_onboarding_text($input['brand_description'] ?? '', 'brand description', 2000, true, 20),
        'target_customer'=>mg_creator_campaign_onboarding_text($input['target_customer'] ?? '', 'target customer', 1000, true, 8),
        'service_area'=>mg_creator_campaign_onboarding_text($input['service_area'] ?? '', 'service area', 500),
        'preferred_creator_types'=>mg_creator_campaign_onboarding_list($input['preferred_creator_types'] ?? ''),
        'platforms'=>mg_creator_campaign_onboarding_list($input['platforms'] ?? ''),
        'content_restrictions'=>mg_creator_campaign_onboarding_list($input['content_restrictions'] ?? '', 30, 240),
        'required_disclosures'=>mg_creator_campaign_onboarding_list($input['required_disclosures'] ?? '', 30, 240),
        'review_turnaround_hours'=>max(1, min(720, (int)($input['review_turnaround_hours'] ?? 48))),
    ];
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'in_progress',
        'current_step'=>3,
        'business_defaults_json'=>mg_creator_campaign_onboarding_encode($defaults),
    ], 'creator_campaign.onboarding.business_defaults_saved', 'business', 'Reusable business and campaign defaults saved.');
}

function mg_creator_campaign_onboarding_save_products(PDO $pdo, array $user, array $workspace, array $input): array
{
    $available = mg_creator_campaign_onboarding_products($pdo, (int)$workspace['merchant_user_id']);
    $byPublicId = [];
    foreach ($available as $product) $byPublicId[(string)$product['public_id']] = $product;
    $selected = mg_creator_campaign_onboarding_list($input['product_ids'] ?? [], 50, 80);
    if ($selected === []) throw new MgCreatorCampaignOnboardingException('Select at least one product or offer for the pilot.');
    $saved = [];
    foreach ($selected as $publicId) {
        if (!isset($byPublicId[$publicId])) {
            throw new MgCreatorCampaignOnboardingException('A selected product is unavailable to this workspace.');
        }
        $product = $byPublicId[$publicId];
        $saved[] = [
            'product_public_id'=>$publicId,
            'version_public_id'=>(string)($product['version_public_id'] ?? ''),
            'title'=>(string)($product['title'] ?? $product['slug'] ?? 'Product'),
            'ready'=>(bool)$product['ready'],
            'checks'=>$product['checks'],
        ];
    }
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'in_progress',
        'current_step'=>4,
        'product_selection_json'=>mg_creator_campaign_onboarding_encode(['products'=>$saved]),
    ], 'creator_campaign.onboarding.products_saved', 'products', 'Pilot products and offer readiness selection saved.', [
        'selected_count'=>count($saved),
        'ready_count'=>count(array_filter($saved, static fn(array $row): bool => !empty($row['ready']))),
    ]);
}

function mg_creator_campaign_onboarding_financial_exposure(array $defaults): array
{
    $budget = max(0, (int)($defaults['campaign_budget_minor'] ?? 0));
    $perCreator = max(0, (int)($defaults['per_creator_limit_minor'] ?? 0));
    $flat = max(0, (int)($defaults['flat_fee_minor'] ?? 0));
    $maxCreators = max(1, (int)($defaults['maximum_creators'] ?? 1));
    $calculated = max($flat, $perCreator) * $maxCreators;
    $ceiling = $budget > 0 ? $budget : $calculated;
    return [
        'per_creator_ceiling_minor'=>max($flat, $perCreator),
        'calculated_participant_ceiling_minor'=>$calculated,
        'configured_campaign_ceiling_minor'=>$ceiling,
        'currency'=>(string)($defaults['currency'] ?? 'USD'),
    ];
}

function mg_creator_campaign_onboarding_save_financials(PDO $pdo, array $user, array $workspace, array $input): array
{
    $maximumCreators = max(1, min(100000, (int)($input['maximum_creators'] ?? 1)));
    $commissionBps = max(0, min(10000, (int)($input['commission_bps'] ?? 0)));
    $defaults = [
        'currency'=>mg_creator_campaign_onboarding_currency($input['currency'] ?? ($workspace['default_currency'] ?? 'USD')),
        'flat_fee_minor'=>mg_creator_campaign_onboarding_money($input['flat_fee'] ?? '0', 'flat fee'),
        'commission_bps'=>$commissionBps,
        'product_compensation'=>mg_creator_campaign_onboarding_bool($input['product_compensation'] ?? false),
        'campaign_budget_minor'=>mg_creator_campaign_onboarding_money($input['campaign_budget'] ?? '0', 'campaign budget'),
        'per_creator_limit_minor'=>mg_creator_campaign_onboarding_money($input['per_creator_limit'] ?? '0', 'per-Creator limit'),
        'maximum_creators'=>$maximumCreators,
        'merchant_approval_required'=>true,
        'earning_hold_days'=>max(0, min(365, (int)($input['earning_hold_days'] ?? 7))),
        'dispute_window_days'=>max(1, min(365, (int)($input['dispute_window_days'] ?? 30))),
        'dispute_policy'=>mg_creator_campaign_onboarding_text($input['dispute_policy'] ?? '', 'dispute policy', 1500, true, 8),
    ];
    if ($defaults['campaign_budget_minor'] < 1) {
        throw new MgCreatorCampaignOnboardingException('Set a campaign budget ceiling greater than zero.');
    }
    if ($defaults['flat_fee_minor'] < 1 && $defaults['commission_bps'] < 1 && !$defaults['product_compensation']) {
        throw new MgCreatorCampaignOnboardingException('Choose at least one compensation method.');
    }
    $defaults['exposure'] = mg_creator_campaign_onboarding_financial_exposure($defaults);
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'in_progress',
        'current_step'=>5,
        'compensation_defaults_json'=>mg_creator_campaign_onboarding_encode($defaults),
    ], 'creator_campaign.onboarding.financial_guardrails_saved', 'financials', 'Compensation defaults and maximum exposure guardrails saved.', $defaults['exposure']);
}

function mg_creator_campaign_onboarding_save_eligibility(PDO $pdo, array $user, array $workspace, array $input): array
{
    $minimumAudience = max(0, (int)($input['minimum_audience'] ?? 0));
    $maximumAudience = max(0, (int)($input['maximum_audience'] ?? 0));
    if ($maximumAudience > 0 && $maximumAudience < $minimumAudience) {
        throw new MgCreatorCampaignOnboardingException('Maximum audience must be greater than or equal to minimum audience.');
    }
    $accessMode = strtolower(trim((string)($input['access_mode'] ?? 'hybrid')));
    if (!in_array($accessMode, mg_creator_campaign_access_modes(), true)) {
        throw new MgCreatorCampaignOnboardingException('Invalid participation method.');
    }
    $defaults = [
        'approved_creators_only'=>true,
        'access_mode'=>$accessMode,
        'locations'=>mg_creator_campaign_onboarding_list($input['locations'] ?? ''),
        'platforms'=>mg_creator_campaign_onboarding_list($input['platforms'] ?? ''),
        'categories'=>mg_creator_campaign_onboarding_list($input['categories'] ?? ''),
        'minimum_audience'=>$minimumAudience,
        'maximum_audience'=>$maximumAudience,
        'minimum_profile_completeness'=>max(0, min(100, (int)($input['minimum_profile_completeness'] ?? 80))),
        'prior_campaign_history'=>mg_creator_campaign_onboarding_bool($input['prior_campaign_history'] ?? false),
        'competitor_restrictions'=>mg_creator_campaign_onboarding_list($input['competitor_restrictions'] ?? '', 50, 160),
    ];
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'in_progress',
        'current_step'=>6,
        'creator_preferences_json'=>mg_creator_campaign_onboarding_encode($defaults),
    ], 'creator_campaign.onboarding.creator_preferences_saved', 'eligibility', 'Reusable Creator eligibility preferences saved.');
}

function mg_creator_campaign_onboarding_save_roles(PDO $pdo, array $user, array $workspace, array $input): array
{
    $ownerId = (int)$user['id'];
    $roles = [];
    foreach (MG_CREATOR_CAMPAIGN_ONBOARDING_ROLE_KEYS as $key => $label) {
        $publicId = trim((string)($input[$key] ?? 'owner'));
        $roles[$key] = [
            'user_id'=>mg_creator_campaign_onboarding_resolve_team_user($pdo, (int)$workspace['id'], $ownerId, $publicId),
            'member_public_id'=>$publicId === '' ? 'owner' : $publicId,
            'label'=>$label,
        ];
    }
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'in_progress',
        'current_step'=>7,
        'operator_roles_json'=>mg_creator_campaign_onboarding_encode($roles),
    ], 'creator_campaign.onboarding.operator_roles_saved', 'roles', 'Campaign review, finance, and emergency operator roles saved.');
}

function mg_creator_campaign_onboarding_eligibility_rules(array $preferences): array
{
    $rules = [[
        'rule_type'=>'verification',
        'operator'=>'exists',
        'value'=>true,
        'is_required'=>true,
    ]];
    foreach ([
        'locations'=>'location',
        'platforms'=>'platform',
        'categories'=>'category',
    ] as $source => $type) {
        $values = array_values(array_filter((array)($preferences[$source] ?? [])));
        if ($values !== []) {
            $rules[] = ['rule_type'=>$type,'operator'=>'in','value'=>$values,'is_required'=>true];
        }
    }
    if ((int)($preferences['minimum_audience'] ?? 0) > 0) {
        $rules[] = ['rule_type'=>'audience','operator'=>'gte','value'=>(int)$preferences['minimum_audience'],'is_required'=>true];
    }
    return $rules;
}

function mg_creator_campaign_onboarding_create_first_campaign(
    PDO $pdo,
    array $user,
    array $workspace,
    array $onboarding,
    array $input
): array {
    if ((int)($onboarding['first_campaign_id'] ?? 0) > 0) {
        throw new MgCreatorCampaignOnboardingException('A first campaign is already selected. Use the replace control before creating another.');
    }
    $business = (array)($onboarding['business_defaults'] ?? []);
    $products = (array)(($onboarding['product_selection']['products'] ?? []));
    $financials = (array)($onboarding['compensation_defaults'] ?? []);
    $preferences = (array)($onboarding['creator_preferences'] ?? []);
    if ($business === [] || $products === [] || $financials === [] || $preferences === []) {
        throw new MgCreatorCampaignOnboardingException('Complete business, products, financials, and eligibility before creating the first campaign.');
    }
    $title = mg_creator_campaign_onboarding_text($input['campaign_title'] ?? '', 'campaign title', 180, true, 3);
    $objective = mg_creator_campaign_onboarding_text($input['campaign_objective'] ?? '', 'campaign objective', 180, true, 3);
    $description = mg_creator_campaign_onboarding_text(
        $input['campaign_description'] ?? ($business['brand_description'] ?? ''),
        'campaign description',
        16000,
        true,
        20
    );
    $category = mg_creator_campaign_onboarding_text($input['campaign_category'] ?? ($business['business_category'] ?? ''), 'campaign category', 100, true, 2);
    $timezone = (string)($workspace['timezone'] ?? 'UTC');
    $idempotencyKey = 'cc-onboarding-' . (string)$onboarding['public_id'] . '-first-campaign';

    $created = mg_creator_campaign_create_draft($pdo, $user, [
        'idempotency_key'=>$idempotencyKey,
        'title'=>$title,
        'description'=>$description,
        'objective'=>$objective,
        'category'=>$category,
        'access_mode'=>(string)($preferences['access_mode'] ?? 'hybrid'),
        'timezone'=>$timezone,
        'geographic_scope'=>['label'=>(string)($business['service_area'] ?? '')],
        'metadata'=>[
            'source'=>'creator_campaign_phase15_onboarding',
            'onboarding_id'=>(string)$onboarding['public_id'],
            'pilot_goal'=>(string)($onboarding['pilot_goal'] ?? ''),
        ],
    ]);
    $campaign = (array)$created['campaign'];

    $step1 = mg_creator_campaign_builder_save_step($pdo, $user, (string)$campaign['public_id'], 1, [
        'expected_lock_version'=>(int)$campaign['lock_version'],
        'internal_reference'=>(string)$campaign['internal_reference'],
        'title'=>$title,
        'description'=>$description,
        'objective'=>$objective,
        'category'=>$category,
        'access_mode'=>(string)($preferences['access_mode'] ?? 'hybrid'),
        'timezone'=>$timezone,
        'starts_at'=>'',
        'ends_at'=>'',
        'application_deadline_at'=>'',
        'geographic_scope'=>['label'=>(string)($business['service_area'] ?? '')],
        'campaign_manager_key'=>'owner',
    ]);
    $productRows = [];
    foreach ($products as $index => $product) {
        $productRows[] = [
            'product_public_id'=>(string)$product['product_public_id'],
            'version_public_id'=>(string)($product['version_public_id'] ?? ''),
            'relationship_type'=>$index === 0 ? 'primary' : 'featured',
            'sort_order'=>$index,
        ];
    }
    $step2 = mg_creator_campaign_builder_save_step($pdo, $user, (string)$campaign['public_id'], 2, [
        'expected_lock_version'=>(int)$step1['lock_version'],
        'campaign_focus'=>count($productRows) === 1 ? 'single_product' : 'multiple_products',
        'creator_product_access'=>!empty($financials['product_compensation']) ? 'provided' : 'none',
        'creator_landing_url'=>'',
        'featured_reward_public_id'=>'',
        'products'=>$productRows,
    ]);
    $step3 = mg_creator_campaign_builder_save_step($pdo, $user, (string)$campaign['public_id'], 3, [
        'expected_lock_version'=>(int)$step2['lock_version'],
        'access_mode'=>(string)($preferences['access_mode'] ?? 'hybrid'),
        'existing_creator_preference'=>'preferred',
        'maximum_approved_creators'=>(int)($financials['maximum_creators'] ?? 1),
        'maximum_applications'=>max(10, (int)($financials['maximum_creators'] ?? 1) * 5),
        'automatic_acceptance'=>false,
        'application_deadline_at'=>'',
        'eligibility_rules'=>mg_creator_campaign_onboarding_eligibility_rules($preferences),
        'application_questions'=>[],
    ]);

    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'in_progress',
        'current_step'=>8,
        'first_campaign_id'=>(int)$campaign['id'],
    ], 'creator_campaign.onboarding.first_campaign_created', 'campaign', 'First Creator Campaign draft created through the canonical builder services.', [
        'campaign_id'=>(string)$campaign['public_id'],
        'builder_lock_version'=>(int)$step3['lock_version'],
        'idempotent_replay'=>!empty($created['idempotent_replay']),
    ]);
}

function mg_creator_campaign_onboarding_select_first_campaign(
    PDO $pdo,
    array $user,
    array $workspace,
    string $campaignPublicId
): array {
    $campaign = mg_creator_campaign_onboarding_campaign_row($pdo, (int)$workspace['id'], trim($campaignPublicId));
    if (!in_array((string)$campaign['status'], ['draft','scheduled','active','paused'], true)) {
        throw new MgCreatorCampaignOnboardingException('Select a draft, scheduled, active, or paused campaign for the pilot.');
    }
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'in_progress',
        'current_step'=>8,
        'first_campaign_id'=>(int)$campaign['id'],
    ], 'creator_campaign.onboarding.first_campaign_selected', 'campaign', 'Existing Creator Campaign selected for the production pilot.', [
        'campaign_id'=>(string)$campaign['public_id'],
        'campaign_status'=>(string)$campaign['status'],
    ]);
}

function mg_creator_campaign_onboarding_activate(PDO $pdo, array $user, array $workspace, array $pilot, array $onboarding): array
{
    $products = mg_creator_campaign_onboarding_products($pdo, (int)$workspace['merchant_user_id']);
    $campaigns = mg_creator_campaign_onboarding_campaigns($pdo, (int)$workspace['id']);
    $receipts = mg_creator_campaign_onboarding_receipts($pdo, (int)$onboarding['id'], 20);
    $readiness = mg_creator_campaign_onboarding_readiness($pdo, $user, $workspace, $pilot, $onboarding, $products, $campaigns, $receipts);
    $passed = null;
    foreach ($receipts as $receipt) {
        if ((string)$receipt['receipt_type'] === 'readiness_smoke_test'
            && (string)$receipt['status'] === 'passed'
            && hash_equals((string)$readiness['current_smoke_hash'], (string)$receipt['snapshot_hash'])) {
            $passed = $receipt;
            break;
        }
    }
    if (!$passed || empty($readiness['launch_ready'])) {
        throw new MgCreatorCampaignOnboardingException('A current passing production smoke-test receipt is required before launch activation.');
    }
    $snapshot = [
        'onboarding_id'=>(string)$onboarding['public_id'],
        'smoke_test_receipt_id'=>(string)$passed['public_id'],
        'campaign_id'=>(string)($passed['campaign_public_id'] ?? ''),
        'activated_at'=>gmdate('c'),
        'automatic_execution'=>false,
        'campaign_published'=>false,
    ];
    $hash = hash('sha256', mg_creator_campaign_onboarding_encode($snapshot));
    $pdo->beginTransaction();
    try {
        $current = mg_creator_campaign_onboarding_row($pdo, (int)$user['id'], (int)$workspace['id'], true);
        if (!$current) throw new MgCreatorCampaignOnboardingException('Merchant onboarding was not found.', 404);
        $pdo->prepare(
            "INSERT INTO creator_campaign_onboarding_receipts
             (public_id,onboarding_id,campaign_id,owner_user_id,receipt_type,status,score,checks_json,snapshot_hash,created_at)
             VALUES (?,?,?,?, 'launch_activation','passed',100,?,?,NOW())
             ON DUPLICATE KEY UPDATE public_id=public_id"
        )->execute([
            mg_public_uuid(),(int)$current['id'],(int)$current['first_campaign_id'],(int)$user['id'],
            mg_creator_campaign_onboarding_encode($snapshot),$hash,
        ]);
        $pdo->prepare(
            "UPDATE creator_campaign_merchant_onboarding
             SET status='active',current_step=9,activated_at=COALESCE(activated_at,NOW()),updated_at=NOW()
             WHERE id=?"
        )->execute([(int)$current['id']]);
        $after = mg_creator_campaign_onboarding_row($pdo, (int)$user['id'], (int)$workspace['id']);
        if (!$after) throw new RuntimeException('Activated onboarding record could not be read.');
        mg_creator_campaign_onboarding_event($pdo, $after, (int)$user['id'], 'creator_campaign.onboarding.activated', 'launch', 'info', 'Merchant onboarding activated. Campaign publication remains a separate owner action.', $snapshot);
        $pdo->commit();
        mg_creator_campaign_onboarding_audit($after, (int)$user['id'], 'activated', $snapshot);
        return $after;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_creator_campaign_onboarding_complete(PDO $pdo, array $user, array $workspace): array
{
    $current = mg_creator_campaign_onboarding_row($pdo, (int)$user['id'], (int)$workspace['id']);
    if (!$current || (string)$current['status'] !== 'active') {
        throw new MgCreatorCampaignOnboardingException('Activate the merchant onboarding before completing it.');
    }
    return mg_creator_campaign_onboarding_update($pdo, $user, $workspace, [
        'status'=>'completed',
        'current_step'=>9,
        'completed_at'=>gmdate('Y-m-d H:i:s'),
    ], 'creator_campaign.onboarding.completed', 'launch', 'Merchant completed the Creator Campaign pilot onboarding.');
}
