<?php
declare(strict_types=1);

function mg_creator_campaign_onboarding_smoke_check(string $key, string $label, bool $ok, string $detail): array
{
    return compact('key','label','ok','detail') + ['required'=>true];
}

function mg_creator_campaign_onboarding_run_smoke_test(
    PDO $pdo,
    array $user,
    array $workspace,
    array $pilot,
    array $onboarding
): array {
    $products = mg_creator_campaign_onboarding_products($pdo, (int)$workspace['merchant_user_id']);
    $campaigns = mg_creator_campaign_onboarding_campaigns($pdo, (int)$workspace['id']);
    $existingReceipts = mg_creator_campaign_onboarding_receipts($pdo, (int)$onboarding['id'], 20);
    $readiness = mg_creator_campaign_onboarding_readiness(
        $pdo,$user,$workspace,$pilot,$onboarding,$products,$campaigns,$existingReceipts
    );
    $campaignState = (array)($readiness['campaign'] ?? []);
    $campaign = is_array($campaignState['campaign'] ?? null) ? $campaignState['campaign'] : null;
    $campaignChecks = (array)($campaignState['checks'] ?? []);
    $step = static fn(int $number): bool => !empty($readiness['steps'][$number]['complete']);

    $automaticAcceptanceOff = false;
    if ($campaign) {
        $raw = mg_creator_campaign_onboarding_campaign_row($pdo, (int)$workspace['id'], (string)$campaign['public_id']);
        $automaticAcceptanceOff = empty($raw['automatic_acceptance']);
    }

    $checks = [
        mg_creator_campaign_onboarding_smoke_check('owner_authority','Merchant owner authority',
            (int)($workspace['merchant_user_id'] ?? 0) === (int)$user['id'],
            'The signed-in owner controls the selected merchant workspace.'),
        mg_creator_campaign_onboarding_smoke_check('pilot_available','Phase 14 pilot available',
            (int)($pilot['id'] ?? 0) > 0,
            'The existing operator pilot record remains authoritative for safety controls.'),
        mg_creator_campaign_onboarding_smoke_check('emergency_clear','Emergency stop clear',
            empty($pilot['emergency_disabled']),
            empty($pilot['emergency_disabled']) ? 'The workspace emergency stop is clear.' : 'Clear the emergency stop before launch.'),
        mg_creator_campaign_onboarding_smoke_check('enrollment','Pilot enrollment complete',$step(1),
            'Primary operator, support, target date, goal, and pilot boundaries are recorded.'),
        mg_creator_campaign_onboarding_smoke_check('business_defaults','Business defaults complete',$step(2),
            'Brand, target customer, platform, disclosure, and review defaults are reusable.'),
        mg_creator_campaign_onboarding_smoke_check('product_readiness','Selected products ready',$step(3),
            'Selected products are published, priced, imaged, and claim-ready.'),
        mg_creator_campaign_onboarding_smoke_check('financial_guardrails','Financial guardrails valid',$step(4),
            'Compensation exposure stays within the merchant-configured budget ceiling.'),
        mg_creator_campaign_onboarding_smoke_check('creator_preferences','Creator preferences complete',$step(5),
            'Only approved Creators are eligible under the saved participation defaults.'),
        mg_creator_campaign_onboarding_smoke_check('operator_roles','Operator roles complete',$step(6),
            'Campaign, review, finance, payout-record, and emergency responsibilities are assigned.'),
        mg_creator_campaign_onboarding_smoke_check('campaign_selected','First campaign selected',$campaign !== null,
            $campaign ? 'Campaign ' . (string)$campaign['public_id'] . ' is scoped to this workspace.' : 'Select or create a first campaign.'),
        mg_creator_campaign_onboarding_smoke_check('builder_ready','Canonical builder ready',!empty($campaignChecks['builder_ready']),
            'Campaign details, products, and Creator eligibility pass the canonical builder validation.'),
        mg_creator_campaign_onboarding_smoke_check('product_attached','Campaign product attached',!empty($campaignChecks['product_attached']),
            'At least one non-excluded canonical product is attached to the campaign.'),
        mg_creator_campaign_onboarding_smoke_check('deliverable_defined','Deliverable configured',!empty($campaignChecks['deliverable_defined']),
            'At least one campaign deliverable is defined in the dedicated deliverables workspace.'),
        mg_creator_campaign_onboarding_smoke_check('compensation_active','Compensation active',!empty($campaignChecks['compensation_active']),
            'At least one compensation rule is active.'),
        mg_creator_campaign_onboarding_smoke_check('budget_configured','Budget configured',!empty($campaignChecks['budget_configured']),
            'A campaign budget record exists and remains the canonical financial ceiling.'),
        mg_creator_campaign_onboarding_smoke_check('tracking_configured','Tracking configured',!empty($campaignChecks['tracking_configured']),
            'At least one active tracking source is available for attribution.'),
        mg_creator_campaign_onboarding_smoke_check('agreement_service','Agreement service ready',!empty($campaignChecks['agreement_service_ready']),
            'Immutable participant agreement versions are available when a Creator is approved.'),
        mg_creator_campaign_onboarding_smoke_check('manual_approval','Automatic Creator acceptance disabled',$automaticAcceptanceOff,
            'Creator applications and agreements remain under explicit merchant approval.'),
        mg_creator_campaign_onboarding_smoke_check('non_execution','Read-only smoke test boundary',true,
            'This test did not publish a campaign, approve a Creator, send outreach, approve content, alter earnings, record a payout, or call a payment provider.'),
    ];

    $passedCount = count(array_filter($checks, static fn(array $check): bool => !empty($check['ok'])));
    $score = (int)round(($passedCount / max(1, count($checks))) * 100);
    $passed = $passedCount === count($checks);
    $snapshot = [
        'version'=>'creator_campaign_onboarding_smoke_v15',
        'onboarding_id'=>(string)$onboarding['public_id'],
        'workspace_id'=>(string)$workspace['public_id'],
        'campaign_id'=>(string)($campaign['public_id'] ?? ''),
        'checks'=>$checks,
        'score'=>$score,
        'status'=>$passed ? 'passed' : 'failed',
        'automatic_execution'=>false,
        'campaign_published'=>false,
        'payment_provider_called'=>false,
    ];
    $snapshotHash = (string)$readiness['current_smoke_hash'];

    $pdo->beginTransaction();
    try {
        $current = mg_creator_campaign_onboarding_row($pdo, (int)$user['id'], (int)$workspace['id'], true);
        if (!$current) throw new MgCreatorCampaignOnboardingException('Merchant onboarding was not found.', 404);
        $existing = $pdo->prepare(
            'SELECT * FROM creator_campaign_onboarding_receipts
             WHERE onboarding_id=? AND receipt_type=\'readiness_smoke_test\' AND snapshot_hash=? LIMIT 1'
        );
        $existing->execute([(int)$current['id'], $snapshotHash]);
        $receipt = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$receipt) {
            $publicId = mg_public_uuid();
            $pdo->prepare(
                "INSERT INTO creator_campaign_onboarding_receipts
                 (public_id,onboarding_id,campaign_id,owner_user_id,receipt_type,status,score,checks_json,snapshot_hash,created_at)
                 VALUES (?,?,?,?, 'readiness_smoke_test',?,?,?, ?,NOW())"
            )->execute([
                $publicId,
                (int)$current['id'],
                $campaign ? (int)$campaign['id'] : null,
                (int)$user['id'],
                $passed ? 'passed' : 'failed',
                $score,
                mg_creator_campaign_onboarding_encode($checks),
                $snapshotHash,
            ]);
            $receipt = ['public_id'=>$publicId,'status'=>$passed ? 'passed' : 'failed','score'=>$score,'snapshot_hash'=>$snapshotHash];
        }
        $pdo->prepare(
            "UPDATE creator_campaign_merchant_onboarding
             SET status=CASE WHEN ?=1 THEN 'ready' ELSE 'in_progress' END,
                 current_step=CASE WHEN ?=1 THEN 9 ELSE 8 END,
                 last_smoke_test_at=NOW(),updated_at=NOW()
             WHERE id=? AND status NOT IN ('active','completed')"
        )->execute([$passed ? 1 : 0, $passed ? 1 : 0, (int)$current['id']]);
        $after = mg_creator_campaign_onboarding_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $current;
        mg_creator_campaign_onboarding_event(
            $pdo,$after,(int)$user['id'],
            'creator_campaign.onboarding.smoke_test_' . ($passed ? 'passed' : 'failed'),
            'smoke_test',
            $passed ? 'info' : 'high',
            $passed ? 'Production onboarding smoke test passed.' : 'Production onboarding smoke test found launch blockers.',
            ['receipt_id'=>(string)$receipt['public_id'],'score'=>$score,'failed_checks'=>array_values(array_map(
                static fn(array $check): string => (string)$check['key'],
                array_filter($checks, static fn(array $check): bool => empty($check['ok']))
            ))]
        );
        $pdo->commit();
        mg_creator_campaign_onboarding_audit($after, (int)$user['id'], 'smoke_test_' . ($passed ? 'passed' : 'failed'), [
            'receipt_id'=>(string)$receipt['public_id'],'score'=>$score,
        ]);
        return [
            'receipt'=>$receipt,
            'checks'=>$checks,
            'score'=>$score,
            'passed'=>$passed,
            'snapshot_hash'=>$snapshotHash,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
