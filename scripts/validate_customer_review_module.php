<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        fwrite(STDERR, "Unable to read file: {$path}\n");
        exit(1);
    }
    return $content;
};

$merchantPage = $read('merchant-campaigns.php');
$builder = $read('assets/js/stage12-customer-review.js');
$profileRuntime = $read('assets/js/public-profile-runtime.js');
$profileReviews = $read('assets/js/public-profile-reviews.js');
$merchantApi = $read('api/merchant/customer-review-campaign.php');
$profileApi = $read('api/public/profile-reviews.php');
$submitApi = $read('api/public/campaigns/customer-review.php');
$sql = $read('database/customer_review_campaign_v1.sql');
$manifest = $read('config/migrations.php');

$checks = [
    'merchant campaigns loads customer review builder' =>
        str_contains($merchantPage, 'stage12-customer-review.js'),
    'campaign builder registers CUSTOMER REVIEW' =>
        str_contains($builder, "key: 'customer_review'")
        && str_contains($builder, "label: 'CUSTOMER REVIEW'"),
    'campaign builder provides period limits' =>
        str_contains($builder, 'review_max_per_period')
        && str_contains($builder, 'review_limit_period')
        && str_contains($builder, '<option value="day">Day</option>')
        && str_contains($builder, '<option value="quarter">Quarter</option>')
        && str_contains($builder, '<option value="year">Year</option>'),
    'profile runtime loads review module' =>
        str_contains($profileRuntime, 'public-profile-reviews.js'),
    'profile module provides reviews tab and fullscreen modal' =>
        str_contains($profileReviews, "state.tab.textContent = 'Reviews'")
        && str_contains($profileReviews, 'mg-review-modal')
        && str_contains($profileReviews, 'data-review-star="5"')
        && str_contains($profileReviews, 'Add Review'),
    'merchant API persists review campaign rules' =>
        str_contains($merchantApi, "'campaign_type' => 'customer_review'")
        && str_contains($merchantApi, "'max_reviews_per_period' => \$maxReviews")
        && str_contains($merchantApi, "'limit_period' => \$period"),
    'public profile API exposes summary reviews campaign and eligibility' =>
        str_contains($profileApi, "'summary' => \$summary")
        && str_contains($profileApi, "'reviews' => \$reviews")
        && str_contains($profileApi, "'eligibility' => \$eligibility"),
    'submit API enforces review period and creates wallet item' =>
        str_contains($submitApi, 'mg_customer_review_submit_period_start')
        && str_contains($submitApi, "source_type,source_id,status")
        && str_contains($submitApi, "'customer_review'")
        && str_contains($submitApi, 'UPDATE customer_reviews SET wallet_item_id'),
    'submit API bridges wallet reward into PPPM Inbox' =>
        str_contains($submitApi, 'mg_zero_reward_issue_from_wallet')
        && str_contains($submitApi, "'reward_destination' => 'wallet_pppm_inbox'")
        && str_contains($submitApi, "'inbox_url' => '/inbox.php'"),
    'migration adds enums and customer_reviews storage' =>
        str_contains($sql, "'customer_review'")
        && str_contains($sql, 'CREATE TABLE IF NOT EXISTS customer_reviews')
        && str_contains($sql, 'idx_customer_reviews_campaign_reviewer_time'),
    'migration is registered in canonical manifest' =>
        str_contains($manifest, "'customer_review_campaign_v1.sql'"),
];

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Customer Review module validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Customer Review module validation passed.\n";
