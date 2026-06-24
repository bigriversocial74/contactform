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
            'description' => 'Get started with essential Promotional CRM tools.',
            'cta_label' => 'Get Started',
            'cta_href' => '/signup.php?plan=starter',
            'included_label' => 'Includes:',
            'fit' => 'Perfect for individuals and creators getting started.',
            'featured' => false,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'low',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-STARTER',
            'sort_order' => 10,
            'included_features' => [
                'Promotional CRM',
                'Direct Feed Distribution',
                'Engagement Campaigns',
                'Landing Pages',
                'Pre Sale Commerce',
            ],
            'excluded_features' => [
                'Multi-Location Mgmt',
                'Marketing & Design Studio',
                'Automated Commerce Solutions',
            ],
        ],
        [
            'id' => 'growth',
            'name' => 'Growth',
            'price_label' => '$79',
            'billing_label' => '/mo',
            'description' => 'Grow your audience and increase engagement.',
            'cta_label' => 'Start Growing',
            'cta_href' => '/signup.php?plan=growth',
            'included_label' => 'Everything in Starter, plus:',
            'fit' => 'Ideal for growing businesses and creators.',
            'featured' => true,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'normal',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-GROWTH',
            'sort_order' => 20,
            'included_features' => [
                'Promotional CRM',
                'Direct Feed Distribution',
                'Engagement Campaigns',
                'Landing Pages',
                'Pre Sale Commerce',
                'Multi-Location Mgmt up to 3 locations',
            ],
            'excluded_features' => [
                'Marketing & Design Studio',
                'Automated Commerce Solutions',
            ],
        ],
        [
            'id' => 'pro',
            'name' => 'Pro',
            'price_label' => '$199',
            'billing_label' => '/mo',
            'description' => 'Advanced tools to scale your business.',
            'cta_label' => 'Go Pro',
            'cta_href' => '/signup.php?plan=pro',
            'included_label' => 'Everything in Growth, plus:',
            'fit' => 'Built for established brands and teams.',
            'featured' => false,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'high',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-PRO',
            'sort_order' => 30,
            'included_features' => [
                'Promotional CRM',
                'Direct Feed Distribution',
                'Engagement Campaigns',
                'Landing Pages',
                'Pre Sale Commerce',
                'Multi-Location Mgmt up to 10 locations',
                'Marketing & Design Studio templates and branding',
            ],
            'excluded_features' => [
                'Automated Commerce Solutions',
            ],
        ],
        [
            'id' => 'enterprise',
            'name' => 'Enterprise',
            'price_label' => '$499',
            'billing_label' => '/mo',
            'description' => 'Full power. Custom solutions. Unlimited potential.',
            'cta_label' => 'Contact Sales',
            'cta_href' => '/learn-more.php?plan=enterprise',
            'included_label' => 'Everything in Pro, plus:',
            'fit' => 'For high-volume businesses with advanced needs.',
            'featured' => false,
            'public_status' => 'published',
            'moderation_status' => 'approved',
            'risk_level' => 'critical',
            'package_type' => 'pricing_plan',
            'implementation_id' => 'PKG-PRICING-ENTERPRISE',
            'sort_order' => 40,
            'included_features' => [
                'Promotional CRM',
                'Direct Feed Distribution',
                'Engagement Campaigns',
                'Landing Pages',
                'Pre Sale Commerce',
                'Multi-Location Mgmt unlimited locations',
                'Marketing & Design Studio custom and white label',
                'Automated Commerce Solutions workflows and integrations',
            ],
            'excluded_features' => [],
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
    ];
    foreach ($packages as $package) {
        if (($package['public_status'] ?? '') === 'published') {
            $summary['published']++;
        }
        $status = (string)($package['moderation_status'] ?? 'pending_review');
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }
    }
    return $summary;
}
