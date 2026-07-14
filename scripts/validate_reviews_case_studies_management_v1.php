<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'database/reviews_case_studies_management_v1.sql' => ['customer_review_replies', 'featured_case_studies', 'review_case_study_audit', 'featured_on_profile'],
    'admin/reviews.php' => ['data-admin-reviews-page', 'Review moderation', 'Audit history'],
    'admin/case-studies.php' => ['data-admin-case-studies-page', 'Featured Case Studies', 'Search profiles', 'Save case study'],
    'merchant-reviews.php' => ["\$merchantView = 'reviews'", 'merchant-workspace.php'],
    'includes/merchant-reviews-view.php' => ['data-merchant-reviews-page', 'Public merchant reply', 'Message customer privately'],
    'api/admin/reviews.php' => ['moderate_review', 'save_case_study', 'admin.case_study.saved'],
    'api/admin/case-studies.php' => ['featured_case_studies', 'admin.case_study.saved', 'profiles'],
    'api/merchant/reviews.php' => ['customer_review_reply', 'customer_review_message', 'merchant.review.private_message_sent'],
    'api/public/review-replies.php' => ['featured_on_profile', 'reply_body'],
    'api/public/case-studies/list.php' => ["f.status='published'", 'display_order', 'hero_featured'],
    'api/public/case-studies/detail.php' => ['featured_in_case_study', 'customer_review_replies'],
    'api/public/case-studies/stats.php' => [
        'published_products',
        'active_campaigns',
        'FROM commerce_orders',
        "payment_status IN ('paid','partially_refunded','refunded')",
        'orders_series',
        "'sales_source' => 'commerce_orders'",
        "header('Cache-Control: no-store",
    ],
    'case-study.php' => ['/assets/js/case-study.js?v=1.1.0', 'data-cs-review-stars', 'Captured commerce orders'],
    'assets/js/public-profile-review-replies.js' => ['Response from the merchant', 'Featured review'],
    'assets/js/case-study-curation.js' => ['Featured Reviews', 'Merchant response'],
    'assets/js/case-study.js' => [
        '/api/public/case-studies/stats.php',
        "cache: 'no-store'",
        'renderCampaigns(campaigns)',
        'analytics.orders_series',
        "root.dataset.statsSource = 'database'",
        'Captured commerce orders · cash and online',
    ],
    'assets/js/featured-case-studies.js' => ['/api/public/case-studies/list.php'],
    'includes/merchant-navigation.php' => ["'reviews' => ['Customer Reviews'"],
    'includes/merchant-view.php' => ["\$merchantView==='reviews'"],
    'config/migrations.php' => ['reviews_case_studies_management_v1.sql'],
];

$forbidden = [
    'case-study.php' => ['case-study-real-stats.js'],
    'assets/js/case-study.js' => ['/api/public/profile.php', 'Analytics-ready estimate', 'function fallback'],
    'api/public/case-studies/stats.php' => ['redemption_settlement_ledger'],
];

$errors = [];
$checks = 0;
foreach ($required as $path => $markers) {
    $file = $root . '/' . $path;
    if (!is_file($file)) {
        $errors[] = "Missing {$path}";
        continue;
    }
    $content = (string)file_get_contents($file);
    foreach ($markers as $marker) {
        $checks++;
        if (!str_contains($content, $marker)) {
            $errors[] = "{$path} missing marker: {$marker}";
        }
    }
}

foreach ($forbidden as $path => $markers) {
    $file = $root . '/' . $path;
    if (!is_file($file)) {
        $errors[] = "Missing {$path}";
        continue;
    }
    $content = (string)file_get_contents($file);
    foreach ($markers as $marker) {
        $checks++;
        if (str_contains($content, $marker)) {
            $errors[] = "{$path} contains forbidden marker: {$marker}";
        }
    }
}

if ($errors) {
    fwrite(STDERR, "Reviews & Case Studies Management v1 validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Reviews & Case Studies Management v1: {$checks} contract checks passed.\n";
