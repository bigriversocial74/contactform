<?php
declare(strict_types=1);

/**
 * Loyalty Quest Campaign Type v1.
 *
 * This module keeps the first-class campaign contract in one place while the
 * core campaign registry is upgraded in the same scoped build.
 */
function mg_loyalty_quest_campaign_definition(): array
{
    return [
        'key' => 'loyalty_quest',
        'label' => 'Loyalty Quest',
        'category' => 'loyalty_retention',
        'description' => 'Create verified local challenges that reward visits, purchases, referrals, events, milestones, and community participation.',
        'merchant_use_case' => 'Multi-location loyalty, hospitality engagement, local discovery, purchase challenges, event participation, referral quests, and repeat-visit programs.',
        'public_path' => '/loyalty-quest.php',
        'submit_endpoint' => '/api/public/campaigns/loyalty-quest.php',
        'source_type' => 'loyalty_quest',
        'event_type' => 'loyalty_quest.completed',
        'requires_reward_template' => true,
        'public_enabled' => true,
        'crm_enabled' => true,
        'embed_allowed' => true,
        'internal_only' => false,
        'wallet_issue_mode' => 'verified_quest_reward',
        'default_status' => 'draft',
        'analytics_bucket' => 'loyalty_quest',
        'default_copy' => [
            'title' => 'Complete a local quest and earn a reward',
            'form_headline' => 'Start this Loyalty Quest',
            'description' => 'Complete the merchant-defined action and receive a verified Microgifter reward.',
            'form_description' => 'Review the requirements, sign in with Microgifter, and complete the verified action.',
            'success_message' => 'Quest completion verified. Your reward is being issued.',
            'quantity_limit' => '',
            'per_user_limit' => '1',
            'quest_action_type' => 'location_visit',
            'quest_verification_type' => 'signed_qr',
            'quest_radius_meters' => '150',
            'quest_required_count' => '1',
            'quest_visibility' => 'public',
        ],
        'rules_schema' => [
            'mode' => 'verified_loyalty_quest',
            'action_type' => [
                'location_visit','signed_qr','purchase','product_purchase','event_attendance',
                'referral','social_action','milestone','multi_location','sequence','invite_code'
            ],
            'verification_type' => [
                'signed_qr','static_qr','geolocation','purchase_record','receipt_review',
                'staff_confirmation','event_check_in','microgifter_transaction','referral_conversion','manual_review'
            ],
            'visibility' => ['public','customers','loyalty_members','new_customers','invite_only','campaign_contacts','geographic_radius'],
            'location_id' => true,
            'radius_meters' => true,
            'required_count' => true,
            'instructions' => true,
            'eligibility' => true,
            'proof_required' => true,
            'staff_confirmation_required' => true,
            'signed_qr_required' => true,
            'budget_limit' => true,
            'daily_limit' => true,
            'entry_reward_enabled' => true,
        ],
    ];
}

function mg_loyalty_quest_allowed_action_types(): array
{
    return mg_loyalty_quest_campaign_definition()['rules_schema']['action_type'];
}

function mg_loyalty_quest_allowed_verification_types(): array
{
    return mg_loyalty_quest_campaign_definition()['rules_schema']['verification_type'];
}

function mg_loyalty_quest_allowed_visibility(): array
{
    return mg_loyalty_quest_campaign_definition()['rules_schema']['visibility'];
}

function mg_loyalty_quest_normalize_rules(array $input, array $existing = []): array
{
    $action = strtolower(trim((string)($input['quest_action_type'] ?? $existing['action_type'] ?? 'location_visit')));
    if (!in_array($action, mg_loyalty_quest_allowed_action_types(), true)) $action = 'location_visit';

    $verification = strtolower(trim((string)($input['quest_verification_type'] ?? $existing['verification_type'] ?? 'signed_qr')));
    if (!in_array($verification, mg_loyalty_quest_allowed_verification_types(), true)) $verification = 'signed_qr';

    $visibility = strtolower(trim((string)($input['quest_visibility'] ?? $existing['visibility'] ?? 'public')));
    if (!in_array($visibility, mg_loyalty_quest_allowed_visibility(), true)) $visibility = 'public';

    $radius = max(25, min(5000, (int)($input['quest_radius_meters'] ?? $existing['radius_meters'] ?? 150)));
    $requiredCount = max(1, min(100, (int)($input['quest_required_count'] ?? $existing['required_count'] ?? 1)));
    $dailyLimitRaw = trim((string)($input['quest_daily_limit'] ?? $existing['daily_limit'] ?? ''));
    $budgetLimitRaw = trim((string)($input['quest_budget_limit'] ?? $existing['budget_limit'] ?? ''));

    return [
        'mode' => 'verified_loyalty_quest',
        'action_type' => $action,
        'verification_type' => $verification,
        'visibility' => $visibility,
        'location_id' => mb_substr(trim((string)($input['quest_location_id'] ?? $existing['location_id'] ?? '')), 0, 64),
        'radius_meters' => $radius,
        'required_count' => $requiredCount,
        'instructions' => mb_substr(trim((string)($input['quest_instructions'] ?? $existing['instructions'] ?? '')), 0, 2000),
        'eligibility' => mb_substr(trim((string)($input['quest_eligibility'] ?? $existing['eligibility'] ?? '')), 0, 1000),
        'invite_code' => mb_substr(strtoupper(trim((string)($input['quest_invite_code'] ?? $existing['invite_code'] ?? ''))), 0, 64),
        'proof_required' => !empty($input['quest_proof_required']) || (!array_key_exists('quest_proof_required', $input) && !empty($existing['proof_required'])),
        'staff_confirmation_required' => !empty($input['quest_staff_confirmation_required']) || (!array_key_exists('quest_staff_confirmation_required', $input) && !empty($existing['staff_confirmation_required'])),
        'signed_qr_required' => $verification === 'signed_qr',
        'daily_limit' => $dailyLimitRaw === '' ? null : max(1, (int)$dailyLimitRaw),
        'budget_limit' => $budgetLimitRaw === '' ? null : max(0.01, (float)$budgetLimitRaw),
        'entry_reward_enabled' => true,
        'merchant_account_required' => true,
        'microgifter_identity_required' => true,
        'version' => 1,
    ];
}

function mg_loyalty_quest_validate_rules(array $rules, string $status): array
{
    $errors = [];
    if (!in_array((string)($rules['action_type'] ?? ''), mg_loyalty_quest_allowed_action_types(), true)) $errors[] = 'Choose a valid quest action.';
    if (!in_array((string)($rules['verification_type'] ?? ''), mg_loyalty_quest_allowed_verification_types(), true)) $errors[] = 'Choose a valid verification method.';
    if (!in_array((string)($rules['visibility'] ?? ''), mg_loyalty_quest_allowed_visibility(), true)) $errors[] = 'Choose a valid quest audience.';
    if ($status === 'active' && trim((string)($rules['instructions'] ?? '')) === '') $errors[] = 'Active Loyalty Quest campaigns require participant instructions.';
    if ($status === 'active' && in_array((string)($rules['action_type'] ?? ''), ['location_visit','multi_location'], true) && trim((string)($rules['location_id'] ?? '')) === '') $errors[] = 'Location-based Loyalty Quests require a merchant location.';
    if ((string)($rules['visibility'] ?? '') === 'invite_only' && trim((string)($rules['invite_code'] ?? '')) === '') $errors[] = 'Invite-only Loyalty Quests require an invite code.';
    return $errors;
}
