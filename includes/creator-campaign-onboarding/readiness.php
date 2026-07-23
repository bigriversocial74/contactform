<?php
declare(strict_types=1);

function mg_creator_campaign_onboarding_selected_products(array $onboarding, array $products): array
{
    $byId = [];
    foreach ($products as $product) $byId[(string)$product['public_id']] = $product;
    $selected = [];
    foreach ((array)($onboarding['product_selection']['products'] ?? []) as $saved) {
        $publicId = (string)($saved['product_public_id'] ?? '');
        if ($publicId !== '' && isset($byId[$publicId])) $selected[] = $byId[$publicId];
    }
    return $selected;
}

function mg_creator_campaign_onboarding_campaign_readiness(
    PDO $pdo,
    array $user,
    array $onboarding,
    array $campaigns
): array {
    $campaignId = (int)($onboarding['first_campaign_id'] ?? 0);
    if ($campaignId < 1) {
        return [
            'selected'=>false,
            'campaign'=>null,
            'builder_validation'=>null,
            'checks'=>[],
            'complete'=>false,
        ];
    }
    $campaign = null;
    foreach ($campaigns as $candidate) {
        if ((int)$candidate['id'] === $campaignId) {
            $campaign = $candidate;
            break;
        }
    }
    if (!$campaign) {
        return [
            'selected'=>true,
            'campaign'=>null,
            'builder_validation'=>null,
            'checks'=>['campaign_exists'=>false],
            'complete'=>false,
        ];
    }
    try {
        $builder = mg_creator_campaign_builder_validate_campaign($pdo, $user, (string)$campaign['public_id'], false);
    } catch (Throwable $error) {
        $builder = ['phase2_ready'=>false,'blockers'=>[['message'=>$error->getMessage()]]];
    }
    $agreementServiceReady = mg_creator_campaign_builder_table_exists($pdo, 'creator_campaign_agreement_versions');
    $checks = [
        'campaign_exists'=>true,
        'editable_or_running'=>in_array((string)$campaign['status'], ['draft','scheduled','active','paused'], true),
        'builder_ready'=>!empty($builder['phase2_ready']),
        'product_attached'=>(int)$campaign['product_count'] > 0,
        'deliverable_defined'=>(int)$campaign['deliverable_count'] > 0,
        'compensation_active'=>(int)$campaign['active_compensation_count'] > 0,
        'budget_configured'=>(int)$campaign['budget_count'] > 0,
        'tracking_configured'=>(int)$campaign['tracking_count'] > 0,
        'agreement_service_ready'=>$agreementServiceReady,
        'automatic_acceptance_disabled'=>(int)($campaign['automatic_acceptance'] ?? 0) === 0,
    ];
    return [
        'selected'=>true,
        'campaign'=>$campaign,
        'builder_validation'=>$builder,
        'checks'=>$checks,
        'complete'=>!in_array(false, $checks, true),
    ];
}

function mg_creator_campaign_onboarding_readiness(
    PDO $pdo,
    array $user,
    array $workspace,
    array $pilot,
    array $onboarding,
    array $products,
    array $campaigns,
    array $receipts
): array {
    $selectedProducts = mg_creator_campaign_onboarding_selected_products($onboarding, $products);
    $business = (array)($onboarding['business_defaults'] ?? []);
    $financials = (array)($onboarding['compensation_defaults'] ?? []);
    $preferences = (array)($onboarding['creator_preferences'] ?? []);
    $roles = (array)($onboarding['operator_roles'] ?? []);
    $campaignReadiness = mg_creator_campaign_onboarding_campaign_readiness($pdo, $user, $onboarding, $campaigns);

    $financialExposure = mg_creator_campaign_onboarding_financial_exposure($financials);
    $budget = (int)($financials['campaign_budget_minor'] ?? 0);
    $maximumCreators = max(1, (int)($financials['maximum_creators'] ?? 1));
    $flatCommitment = (int)($financials['flat_fee_minor'] ?? 0) * $maximumCreators;
    $perCreatorCommitment = (int)($financials['per_creator_limit_minor'] ?? 0) * $maximumCreators;
    $financialWithinCeiling = $budget > 0 && max($flatCommitment, $perCreatorCommitment) <= $budget;

    $primaryOperatorId = (int)($onboarding['primary_operator_user_id'] ?? 0);
    $operatorIds = [$primaryOperatorId];
    foreach (array_keys(MG_CREATOR_CAMPAIGN_ONBOARDING_ROLE_KEYS) as $key) {
        $operatorIds[] = (int)($roles[$key]['user_id'] ?? 0);
    }
    $operatorEvidence = mg_creator_campaign_onboarding_operator_evidence(
        $pdo,
        (int)$workspace['id'],
        (int)$workspace['merchant_user_id'],
        $operatorIds
    );
    $primaryOperatorCurrent = $primaryOperatorId > 0 && !empty($operatorEvidence[$primaryOperatorId]['current']);
    $roleComplete = true;
    $roleEvidence = [];
    foreach (array_keys(MG_CREATOR_CAMPAIGN_ONBOARDING_ROLE_KEYS) as $key) {
        $userId = (int)($roles[$key]['user_id'] ?? 0);
        $roleEvidence[$key] = $operatorEvidence[$userId] ?? [
            'user_id'=>$userId,
            'account_active'=>false,
            'workspace_current'=>false,
            'current'=>false,
        ];
        if ($userId < 1 || empty($roleEvidence[$key]['current'])) $roleComplete = false;
    }

    $smokeFingerprintPayload = [
        'version'=>'creator_campaign_onboarding_smoke_v15_1',
        'onboarding_id'=>(string)$onboarding['public_id'],
        'workspace_id'=>(string)$workspace['public_id'],
        'primary_operator_user_id'=>$primaryOperatorId,
        'primary_operator_evidence'=>$operatorEvidence[$primaryOperatorId] ?? null,
        'support_contact'=>(string)($onboarding['support_contact'] ?? ''),
        'pilot_goal'=>(string)($onboarding['pilot_goal'] ?? ''),
        'expected_campaign_volume'=>(string)($onboarding['expected_campaign_volume'] ?? ''),
        'intended_launch_date'=>(string)($onboarding['intended_launch_date'] ?? ''),
        'business_defaults'=>$business,
        'product_selection'=>(array)($onboarding['product_selection'] ?? []),
        'selected_product_evidence'=>array_map(static fn(array $product): array => [
            'public_id'=>(string)$product['public_id'],
            'current_version_id'=>(int)($product['current_version_id'] ?? 0),
            'version_public_id'=>(string)($product['version_public_id'] ?? ''),
            'status'=>(string)$product['status'],
            'version_status'=>(string)($product['version_status'] ?? ''),
            'unit_value_cents'=>(int)($product['unit_value_cents'] ?? 0),
            'currency'=>(string)($product['currency'] ?? ''),
            'ready_image_count'=>(int)($product['ready_image_count'] ?? 0),
            'active_pppm_count'=>(int)($product['active_pppm_count'] ?? 0),
            'checks'=>(array)$product['checks'],
        ], $selectedProducts),
        'compensation_defaults'=>$financials,
        'creator_preferences'=>$preferences,
        'operator_roles'=>$roles,
        'operator_evidence'=>$roleEvidence,
        'campaign'=>$campaignReadiness['campaign'],
        'campaign_checks'=>$campaignReadiness['checks'],
        'financial_within_ceiling'=>$financialWithinCeiling,
        'emergency_disabled'=>!empty($pilot['emergency_disabled']),
    ];
    $currentSmokeHash = hash('sha256', mg_creator_campaign_onboarding_encode($smokeFingerprintPayload));
    $latestSmoke = null;
    $currentPassingSmoke = null;
    foreach ($receipts as $receipt) {
        if ((string)$receipt['receipt_type'] !== 'readiness_smoke_test') continue;
        $latestSmoke ??= $receipt;
        if ($currentPassingSmoke === null
            && (string)$receipt['status'] === 'passed'
            && hash_equals($currentSmokeHash, (string)($receipt['snapshot_hash'] ?? ''))) {
            $currentPassingSmoke = $receipt;
        }
    }
    $latestSmokeCurrent = $latestSmoke !== null
        && hash_equals($currentSmokeHash, (string)($latestSmoke['snapshot_hash'] ?? ''));

    $steps = [
        1=>[
            'key'=>'enrollment',
            'label'=>mg_creator_campaign_onboarding_step_label(1),
            'complete'=>$primaryOperatorCurrent
                && trim((string)($onboarding['support_contact'] ?? '')) !== ''
                && trim((string)($onboarding['pilot_goal'] ?? '')) !== ''
                && trim((string)($onboarding['expected_campaign_volume'] ?? '')) !== ''
                && !empty($onboarding['intended_launch_date']),
            'detail'=>'Current primary operator, support path, pilot goal, volume, and target date.',
        ],
        2=>[
            'key'=>'business',
            'label'=>mg_creator_campaign_onboarding_step_label(2),
            'complete'=>trim((string)($business['business_category'] ?? '')) !== ''
                && mb_strlen(trim((string)($business['brand_description'] ?? ''))) >= 20
                && trim((string)($business['target_customer'] ?? '')) !== ''
                && (array)($business['platforms'] ?? []) !== [],
            'detail'=>'Reusable brand, customer, platform, disclosure, and review defaults.',
        ],
        3=>[
            'key'=>'products',
            'label'=>mg_creator_campaign_onboarding_step_label(3),
            'complete'=>$selectedProducts !== []
                && count($selectedProducts) === count((array)($onboarding['product_selection']['products'] ?? []))
                && count(array_filter($selectedProducts, static fn(array $row): bool => !empty($row['ready']))) === count($selectedProducts),
            'detail'=>'Every selected product is published, priced, imaged, and claim-ready.',
        ],
        4=>[
            'key'=>'financials',
            'label'=>mg_creator_campaign_onboarding_step_label(4),
            'complete'=>$budget > 0
                && (int)($financials['maximum_creators'] ?? 0) > 0
                && !empty($financials['merchant_approval_required'])
                && $financialWithinCeiling,
            'detail'=>'Compensation choices fit inside the configured campaign budget ceiling.',
        ],
        5=>[
            'key'=>'eligibility',
            'label'=>mg_creator_campaign_onboarding_step_label(5),
            'complete'=>!empty($preferences['approved_creators_only'])
                && in_array((string)($preferences['access_mode'] ?? ''), mg_creator_campaign_access_modes(), true)
                && (int)($preferences['minimum_profile_completeness'] ?? 0) >= 1,
            'detail'=>'Approved-Creator, participation, audience, platform, location, and conflict defaults.',
        ],
        6=>[
            'key'=>'roles',
            'label'=>mg_creator_campaign_onboarding_step_label(6),
            'complete'=>$roleComplete,
            'detail'=>'Current workspace members hold owner, review, finance, payout-record, and emergency roles.',
        ],
        7=>[
            'key'=>'campaign',
            'label'=>mg_creator_campaign_onboarding_step_label(7),
            'complete'=>!empty($campaignReadiness['complete']),
            'detail'=>'Canonical builder, product, deliverable, compensation, budget, attribution, agreement infrastructure, and manual Creator approval controls are ready.',
        ],
        8=>[
            'key'=>'smoke_test',
            'label'=>mg_creator_campaign_onboarding_step_label(8),
            'complete'=>$currentPassingSmoke !== null,
            'detail'=>'A durable read-only production smoke-test receipt matches the current launch state.',
        ],
        9=>[
            'key'=>'launch',
            'label'=>mg_creator_campaign_onboarding_step_label(9),
            'complete'=>in_array((string)$onboarding['status'], ['active','completed'], true),
            'detail'=>'Merchant onboarding activated without publishing a campaign or enabling automation.',
        ],
    ];

    $setupSteps = array_slice($steps, 0, 7, true);
    $setupReady = count(array_filter($setupSteps, static fn(array $step): bool => !empty($step['complete']))) === count($setupSteps);
    $launchReady = $setupReady
        && $currentPassingSmoke !== null
        && empty($pilot['emergency_disabled']);
    $completed = count(array_filter($steps, static fn(array $step): bool => !empty($step['complete'])));
    $score = (int)round(($completed / count($steps)) * 100);
    $nextStep = 9;
    foreach ($steps as $number => $step) {
        if (empty($step['complete'])) {
            $nextStep = $number;
            break;
        }
    }

    return [
        'score'=>$score,
        'completed'=>$completed,
        'total'=>count($steps),
        'setup_ready'=>$setupReady,
        'launch_ready'=>$launchReady,
        'next_step'=>$nextStep,
        'steps'=>$steps,
        'selected_products'=>$selectedProducts,
        'campaign'=>$campaignReadiness,
        'financial_exposure'=>$financialExposure,
        'financial_within_ceiling'=>$financialWithinCeiling,
        'operator_evidence'=>$operatorEvidence,
        'latest_smoke_test'=>$latestSmoke,
        'latest_smoke_test_current'=>$latestSmokeCurrent,
        'current_passing_smoke_test'=>$currentPassingSmoke,
        'current_smoke_hash'=>$currentSmokeHash,
        'pilot_emergency_clear'=>empty($pilot['emergency_disabled']),
        'generated_at'=>gmdate('c'),
    ];
}

function mg_creator_campaign_onboarding_refresh_snapshot(
    PDO $pdo,
    array $user,
    array $workspace,
    array $pilot,
    array $onboarding,
    array $products,
    array $campaigns,
    array $receipts
): array {
    $readiness = mg_creator_campaign_onboarding_readiness(
        $pdo,$user,$workspace,$pilot,$onboarding,$products,$campaigns,$receipts
    );
    $status = (string)$onboarding['status'];
    if (!in_array($status, ['active','completed'], true)) {
        $status = !empty($readiness['launch_ready']) ? 'ready' : ($status === 'invited' ? 'invited' : 'in_progress');
    }
    $pdo->prepare(
        'UPDATE creator_campaign_merchant_onboarding
         SET status=?,current_step=?,readiness_snapshot_json=?,ready_at=CASE WHEN ?=\'ready\' THEN COALESCE(ready_at,NOW()) ELSE ready_at END,updated_at=NOW()
         WHERE id=?'
    )->execute([
        $status,
        (int)$readiness['next_step'],
        mg_creator_campaign_onboarding_encode($readiness),
        $status,
        (int)$onboarding['id'],
    ]);
    return [
        'onboarding'=>mg_creator_campaign_onboarding_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $onboarding,
        'readiness'=>$readiness,
    ];
}
