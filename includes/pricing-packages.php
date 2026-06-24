<?php
declare(strict_types=1);

function mg_pricing_packages(): array
{
    return [
        [
            'id' => 'starter',
            'name' => 'Starter',
            'price_label' => '$29',
            'billing_label' => '/mo',
            'description' => 'Get started with essential Promotional CRM tools, paid Microgifts, and monthly send Stamps.',
            'cta_label' => 'Get Started',
            'cta_href' => '/signup.php?plan=starter',
            'included_label' => 'Includes:',
            'fit' => 'Perfect for individuals and small local merchants getting started.',
            'featured' => false,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'low',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-STARTER',
            'sort_order' => 10,
            'included_features' => [
                '5 paid Microgifts',
                '5 promotional Rewards',
                '3 active campaigns',
                '1,000 CRM contacts',
                '1,000 monthly Stamps',
                '3 Landing Pages',
                'Pre Sale Commerce',
            ],
            'excluded_features' => [
                'Multi-Location Mgmt',
                'Marketing & Design Studio',
                'Automated Commerce Solutions',
            ],
            'limits' => [
                'max_microgifts' => 5,
                'max_rewards' => 5,
                'max_active_campaigns' => 3,
                'max_crm_contacts' => 1000,
                'monthly_stamps_included' => 1000,
                'max_landing_pages' => 3,
                'max_locations' => 1,
                'max_team_seats' => 1,
                'stamp_overage_enabled' => true,
                'bulk_stamp_purchase_enabled' => true,
                'email_stamps_enabled' => true,
                'sms_stamps_enabled' => false,
            ],
        ],
        [
            'id' => 'growth',
            'name' => 'Growth',
            'price_label' => '$79',
            'billing_label' => '/mo',
            'description' => 'Grow your audience with more Microgifts, Rewards, campaigns, contacts, and Stamps.',
            'cta_label' => 'Start Growing',
            'cta_href' => '/signup.php?plan=growth',
            'included_label' => 'Everything in Starter, plus:',
            'fit' => 'Ideal for growing businesses, creators, venues, and local campaigns.',
            'featured' => true,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'normal',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-GROWTH',
            'sort_order' => 20,
            'included_features' => [
                '25 paid Microgifts',
                '25 promotional Rewards',
                '10 active campaigns',
                '5,000 CRM contacts',
                '10,000 monthly Stamps',
                '10 Landing Pages',
                'Multi-Location Mgmt up to 3 locations',
            ],
            'excluded_features' => [
                'Marketing & Design Studio',
                'Automated Commerce Solutions',
            ],
            'limits' => [
                'max_microgifts' => 25,
                'max_rewards' => 25,
                'max_active_campaigns' => 10,
                'max_crm_contacts' => 5000,
                'monthly_stamps_included' => 10000,
                'max_landing_pages' => 10,
                'max_locations' => 3,
                'max_team_seats' => 3,
                'stamp_overage_enabled' => true,
                'bulk_stamp_purchase_enabled' => true,
                'email_stamps_enabled' => true,
                'sms_stamps_enabled' => false,
            ],
        ],
        [
            'id' => 'pro',
            'name' => 'Pro',
            'price_label' => '$199',
            'billing_label' => '/mo',
            'description' => 'Advanced tools to scale paid Microgifts, promotional Rewards, and high-volume Stamped sends.',
            'cta_label' => 'Go Pro',
            'cta_href' => '/signup.php?plan=pro',
            'included_label' => 'Everything in Growth, plus:',
            'fit' => 'Built for established brands, teams, agencies, and serious local operators.',
            'featured' => false,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'high',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-PRO',
            'sort_order' => 30,
            'included_features' => [
                '100 paid Microgifts',
                '100 promotional Rewards',
                '35 active campaigns',
                '25,000 CRM contacts',
                '50,000 monthly Stamps',
                '35 Landing Pages',
                'Multi-Location Mgmt up to 10 locations',
                'Marketing & Design Studio templates and branding',
            ],
            'excluded_features' => [
                'Automated Commerce Solutions',
            ],
            'limits' => [
                'max_microgifts' => 100,
                'max_rewards' => 100,
                'max_active_campaigns' => 35,
                'max_crm_contacts' => 25000,
                'monthly_stamps_included' => 50000,
                'max_landing_pages' => 35,
                'max_locations' => 10,
                'max_team_seats' => 10,
                'stamp_overage_enabled' => true,
                'bulk_stamp_purchase_enabled' => true,
                'email_stamps_enabled' => true,
                'sms_stamps_enabled' => true,
            ],
        ],
        [
            'id' => 'enterprise',
            'name' => 'Enterprise',
            'price_label' => '$499',
            'billing_label' => '/mo',
            'description' => 'Full power, custom limits, bulk Stamps, and automated commerce solutions.',
            'cta_label' => 'Contact Sales',
            'cta_href' => '/learn-more.php?plan=enterprise',
            'included_label' => 'Everything in Pro, plus:',
            'fit' => 'For high-volume businesses, platforms, and custom automated commerce programs.',
            'featured' => false,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'critical',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-ENTERPRISE',
            'sort_order' => 40,
            'included_features' => [
                'Custom paid Microgifts',
                'Custom promotional Rewards',
                'Custom campaigns',
                'Custom CRM contacts',
                'Custom monthly Stamps',
                'Custom Landing Pages',
                'Multi-Location Mgmt unlimited locations',
                'Marketing & Design Studio custom and white label',
                'Automated Commerce Solutions workflows and integrations',
            ],
            'excluded_features' => [],
            'limits' => [
                'max_microgifts' => null,
                'max_rewards' => null,
                'max_active_campaigns' => null,
                'max_crm_contacts' => null,
                'monthly_stamps_included' => null,
                'max_landing_pages' => null,
                'max_locations' => null,
                'max_team_seats' => null,
                'stamp_overage_enabled' => true,
                'bulk_stamp_purchase_enabled' => true,
                'email_stamps_enabled' => true,
                'sms_stamps_enabled' => true,
            ],
        ],
    ];
}

function mg_public_pricing_packages(): array
{
    $packages = array_values(array_filter(mg_pricing_packages(), static fn(array $package): bool => ($package['public_status'] ?? '') === 'published'));
    usort($packages, static fn(array $a, array $b): int => (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
    return $packages;
}

function mg_pricing_package_summary(): array
{
    $packages = mg_pricing_packages();
    $summary = [
        'total' => count($packages),
        'published' => 0,
        'approved' => 0,
        'pending_review' => 0,
        'needs_changes' => 0,
        'on_hold' => 0,
        'implemented' => 0,
        'monthly_stamps_included' => 0,
    ];
    foreach ($packages as $package) {
        if (($package['public_status'] ?? '') === 'published') {
            $summary['published']++;
        }
        $status = (string)($package['moderation_status'] ?? 'pending_review');
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }
        $stamps = $package['limits']['monthly_stamps_included'] ?? 0;
        if (is_numeric($stamps)) {
            $summary['monthly_stamps_included'] += (int)$stamps;
        }
    }
    return $summary;
}
