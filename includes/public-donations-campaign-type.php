<?php
declare(strict_types=1);

/**
 * Canonical Public Donations campaign definition.
 */
function mg_public_donations_campaign_definition(): array
{
    return [
        'key' => 'public_donation',
        'label' => 'Public Donations',
        'category' => 'community_support',
        'description' => 'Allocate merchant-funded rewards directly to Community accounts and publish aggregate community impact.',
        'merchant_use_case' => 'Merchant-selected Community support campaigns with controlled reward allocation and informational public impact pages.',
        'public_path' => '/public-donations.php',
        'submit_endpoint' => '',
        'source_type' => 'public_donation',
        'event_type' => 'public_donation.allocated',
        'requires_reward_template' => true,
        'public_enabled' => true,
        'public_transactional' => false,
        'public_mode' => 'informational',
        'crm_enabled' => false,
        'embed_allowed' => false,
        'internal_only' => false,
        'wallet_issue_mode' => 'merchant_initiated_bulk',
        'default_status' => 'draft',
        'analytics_bucket' => 'community_support',
        'default_copy' => [
            'title' => 'Support Community accounts with merchant rewards',
            'form_headline' => 'Public Donations',
            'description' => 'Allocate merchant-funded rewards directly to Community accounts and share aggregate impact publicly.',
            'form_description' => 'Informational public page. Rewards are allocated by the merchant and cannot be purchased or requested by the public.',
            'success_message' => 'Public Donations campaign saved.',
            'quantity_limit' => '',
            'per_user_limit' => '1',
        ],
        'rules_schema' => [
            'mode' => 'merchant_initiated_bulk',
            'public_mode' => 'informational',
            'public_transactional' => false,
            'entry_reward_enabled' => false,
        ],
    ];
}
