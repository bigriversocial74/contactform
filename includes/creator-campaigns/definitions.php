<?php
declare(strict_types=1);

/**
 * Canonical Creator Campaign domain definitions.
 *
 * This domain is intentionally separate from the legacy CRM/reward `campaigns`
 * tables. Keep status, access, relationship, builder, and rule values here.
 */

function mg_creator_campaign_public_id(string $prefix = 'cc'): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function mg_creator_campaign_statuses(): array
{
    return ['draft', 'scheduled', 'active', 'paused', 'completed', 'archived', 'cancelled'];
}

function mg_creator_campaign_access_modes(): array
{
    return ['open', 'invite_only', 'approved_creators', 'selected_creators', 'hybrid'];
}

function mg_creator_campaign_focuses(): array
{
    return [
        'merchant_profile', 'single_product', 'multiple_products', 'product_collection',
        'microgift_offer', 'reward', 'event', 'service', 'experience', 'general_brand_campaign',
    ];
}

function mg_creator_campaign_product_access_modes(): array
{
    return ['none', 'purchase_required', 'reimbursed', 'provided', 'loaned', 'digital_access'];
}

function mg_creator_campaign_existing_creator_preferences(): array
{
    return ['none', 'preferred', 'required'];
}

function mg_creator_campaign_application_question_types(): array
{
    return ['short_text', 'long_text', 'single_choice', 'multiple_choice', 'boolean', 'number', 'url', 'portfolio_link'];
}

function mg_creator_campaign_builder_steps(): array
{
    return [
        1 => 'Campaign Details',
        2 => 'Products',
        3 => 'Creator Eligibility',
        4 => 'Deliverables',
        5 => 'Compensation',
        6 => 'Attribution',
        7 => 'Budget',
        8 => 'Content Rights',
        9 => 'Terms',
        10 => 'Review',
    ];
}

function mg_creator_campaign_product_relationship_types(): array
{
    return ['primary', 'featured', 'commissionable', 'excluded', 'creator_compensation'];
}

function mg_creator_campaign_eligibility_rule_types(): array
{
    return ['specialty', 'category', 'platform', 'verification', 'location', 'audience', 'existing_relationship'];
}

function mg_creator_campaign_eligibility_operators(): array
{
    return ['equals', 'not_equals', 'contains', 'in', 'gte', 'lte', 'between', 'exists'];
}

function mg_creator_campaign_status_transitions(): array
{
    return [
        'draft' => ['scheduled', 'active', 'cancelled'],
        'scheduled' => ['draft', 'active', 'paused', 'cancelled'],
        'active' => ['paused', 'completed', 'cancelled'],
        'paused' => ['active', 'completed', 'cancelled'],
        'completed' => ['archived'],
        'cancelled' => ['archived'],
        'archived' => [],
    ];
}

function mg_creator_campaign_can_transition(string $fromStatus, string $toStatus): bool
{
    $transitions = mg_creator_campaign_status_transitions();
    return isset($transitions[$fromStatus])
        && in_array($toStatus, $transitions[$fromStatus], true);
}

function mg_creator_campaign_is_admin_actor(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    return in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
}

function mg_creator_campaign_assert_transaction_boundary(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Creator campaign mutation services must own their transaction boundary.');
    }
}

function mg_creator_campaign_idempotency_hash(string $key): string
{
    return hash('sha256', trim($key));
}
