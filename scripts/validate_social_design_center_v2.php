<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/personal-agent/social-design-center.php';

$errors = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$errors, &$checks): void {
    $checks++;
    if (!$condition) $errors[] = $message;
};

$registry = mg_social_design_registry();
$templates = $registry['templates'] ?? [];
$formats = $registry['formats'] ?? [];
$variants = $registry['variants'] ?? [];

$expectedTemplates = [
    'hero-offer',
    'minimal-card',
    'review-highlight',
    'premium-dark',
    'giftable-offer',
    'bold-price-poster',
    'editorial-split',
    'full-photo-cta',
    'clean-coupon',
    'social-proof-promo',
];
$expectedFormats = ['square', 'portrait', 'story'];
$expectedVariants = ['spotlight', 'split', 'bold'];

$assert(($registry['version'] ?? 0) === 2, 'Registry version must be 2.');
$assert(array_keys($templates) === $expectedTemplates, 'Exactly the ten approved template families must be registered in canonical order.');
$assert(array_keys($formats) === $expectedFormats, 'Exactly three social formats must be registered.');
$assert(array_keys($variants) === $expectedVariants, 'Exactly three layout variants must be registered.');

foreach ($templates as $id => $template) {
    $assert(($template['id'] ?? '') === $id, "Template {$id} must have a matching id.");
    $assert(trim((string)($template['label'] ?? '')) !== '', "Template {$id} must have a label.");
    $assert(trim((string)($template['description'] ?? '')) !== '', "Template {$id} must have a description.");
    $assert(($template['supported_formats'] ?? []) === $expectedFormats, "Template {$id} must support all formats.");
    $assert(($template['supported_variants'] ?? []) === $expectedVariants, "Template {$id} must support all variants.");
    $assert(is_bool($template['supports_review'] ?? null), "Template {$id} review capability must be explicit.");
    $blocks = $template['blocks'] ?? [];
    foreach (['merchant', 'product_image', 'title', 'price', 'cta'] as $requiredBlock) {
        $assert(in_array($requiredBlock, $blocks, true), "Template {$id} must include {$requiredBlock}.");
    }
}

foreach ($formats as $id => $format) {
    $assert(($format['id'] ?? '') === $id, "Format {$id} must have a matching id.");
    $assert((int)($format['width'] ?? 0) > 0 && (int)($format['height'] ?? 0) > 0, "Format {$id} must define output dimensions.");
    $assert((int)($format['safe_zone'] ?? 0) > 0, "Format {$id} must define a safe zone.");
    $assert((float)($format['type_scale'] ?? 0) > 0, "Format {$id} must define type scaling.");
    $assert((float)($format['spacing_scale'] ?? 0) > 0, "Format {$id} must define spacing scaling.");
}

foreach ($variants as $id => $variant) {
    $assert(($variant['id'] ?? '') === $id, "Variant {$id} must have a matching id.");
    $assert(trim((string)($variant['description'] ?? '')) !== '', "Variant {$id} must have a description.");
}

$registryText = strtolower(json_encode($registry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
foreach (['popular choices', 'best seller', 'ingredient grid', 'toppings grid', 'fake review'] as $forbidden) {
    $assert(!str_contains($registryText, $forbidden), "Registry must not contain ungrounded content: {$forbidden}.");
}

$productRows = [
    [
        'public_id' => 'product-review',
        'reviewer_name' => 'Sarah Miller',
        'rating' => 5,
        'review_body' => 'Always fresh and delicious.',
        'status' => 'published',
        'featured_on_profile' => 0,
        'submitted_at' => '2026-07-01 10:00:00',
    ],
];
$merchantRows = [
    [
        'public_id' => 'merchant-review',
        'reviewer_name' => 'Jordan Smith',
        'rating' => 5,
        'review_body' => 'A wonderful local business.',
        'status' => 'published',
        'featured_on_profile' => 1,
        'submitted_at' => '2026-07-20 10:00:00',
    ],
];
$resolved = mg_social_design_select_review($productRows, $merchantRows);
$assert(($resolved['id'] ?? '') === 'product-review', 'Product review must take priority over merchant review.');
$assert(($resolved['source'] ?? '') === 'product', 'Product review source must be explicit.');
$assert(($resolved['reviewer'] ?? '') === 'Sarah M.', 'Reviewer output must use first name and last initial.');

$merchantFallback = mg_social_design_select_review([], $merchantRows);
$assert(($merchantFallback['id'] ?? '') === 'merchant-review', 'Merchant review must be used when no product review is available.');
$assert(($merchantFallback['source'] ?? '') === 'merchant', 'Merchant fallback source must be explicit.');

$invalidRows = [
    ['review_body' => 'Pending text', 'reviewer_name' => 'A B', 'status' => 'pending'],
    ['review_body' => '', 'reviewer_name' => 'A B', 'status' => 'published'],
    ['review_body' => 'Removed text', 'reviewer_name' => 'A B', 'status' => 'removed'],
    ['review_body' => 'Deleted text', 'reviewer_name' => 'A B', 'status' => 'published', 'deleted_at' => '2026-07-01'],
];
$assert(mg_social_design_select_review($invalidRows, []) === null, 'Ineligible reviews must resolve to null.');

$payload = mg_social_design_render_payload(
    [
        'public_id' => 'product-1',
        'title' => 'Local Brunch',
        'description' => '<p>A fresh local experience.</p>',
        'unit_value_cents' => 2500,
        'currency' => 'USD',
        'image_url' => '/image.jpg',
        'metadata' => ['cta' => 'Gift this experience'],
    ],
    ['display_name' => 'Neighborhood Cafe', 'avatar_url' => '/avatar.jpg'],
    'review-highlight',
    'portrait',
    'split',
    $productRows[0]
);
$assert(($payload['composition'] ?? []) === ['template' => 'review-highlight', 'format' => 'portrait', 'variant' => 'split'], 'Render payload composition must be exact.');
$assert(($payload['output']['width'] ?? 0) === 1080 && ($payload['output']['height'] ?? 0) === 1350, 'Render payload must use format output dimensions.');
$assert(($payload['review']['quote'] ?? '') === 'Always fresh and delicious.', 'Render payload must preserve eligible real review text.');
$assert(($payload['review_fallback'] ?? null) === null, 'A real review must suppress fallback copy.');

$fallbackPayload = mg_social_design_render_payload(
    ['title' => 'Local Brunch', 'unit_value_cents' => 2500, 'currency' => 'USD'],
    ['display_name' => 'Neighborhood Cafe'],
    'social-proof-promo',
    'story',
    'bold',
    null
);
$assert(($fallbackPayload['review'] ?? null) === null, 'Missing review must remain null.');
$assert(($fallbackPayload['review_fallback'] ?? '') === 'Support local. Gift better.', 'Missing review must use the approved non-review fallback.');

$files = [
    'page' => $root . '/design-studio.php',
    'api' => $root . '/api/merchant/product.php',
    'js' => $root . '/assets/js/personal-agent-design-studio-social.js',
    'css' => $root . '/assets/css/personal-agent-design-studio-social.css',
    'save' => $root . '/assets/js/design-studio-creative-save.js',
];
foreach ($files as $label => $path) {
    $assert(is_file($path), "Required {$label} file is missing.");
}

$page = is_file($files['page']) ? (string)file_get_contents($files['page']) : '';
$api = is_file($files['api']) ? (string)file_get_contents($files['api']) : '';
$js = is_file($files['js']) ? (string)file_get_contents($files['js']) : '';
$css = is_file($files['css']) ? (string)file_get_contents($files['css']) : '';
$save = is_file($files['save']) ? (string)file_get_contents($files['save']) : '';

$assert(str_contains($page, 'mg-social-design-registry'), 'Design Studio page must expose the canonical registry.');
$assert(str_contains($page, 'personal-agent-design-studio-social.css?v=2.0.0'), 'Design Studio must load Social v2 CSS.');
$assert(str_contains($page, 'personal-agent-design-studio-social.js?v=2.0.0'), 'Design Studio must load Social v2 JavaScript.');
$assert(str_contains($api, 'mg_social_design_resolve_review'), 'Product detail API must resolve real review data.');
$assert(str_contains($api, "'design_review'=>"), 'Product detail API must return the resolved review.');
$assert(str_contains($js, 'data-social-step="template"'), 'Social UI must include template step.');
$assert(str_contains($js, 'data-social-step="format"'), 'Social UI must include format step.');
$assert(str_contains($js, 'data-social-step="variant"'), 'Social UI must include layout step.');
$assert(str_contains($js, 'data-social-step="preview"'), 'Social UI must include preview step.');
$assert(str_contains($js, 'data-social-download'), 'Social UI must preserve JPG download action.');
$assert(str_contains($js, 'data-social-post-feed'), 'Social UI must preserve feed posting action.');
$assert(str_contains($save, 'data-social-save-asset'), 'Existing creative-save integration must still add Save Creative Asset.');
$assert(str_contains($css, '.mg-social-v2-template-grid'), 'Social v2 template gallery styles must exist.');
$assert(str_contains($css, '.format-story'), 'Story format adaptation must exist.');
$assert(str_contains($css, '.layout-spotlight') || str_contains($js, 'layout-spotlight'), 'Spotlight layout must be renderable.');
$assert(str_contains($css, '.layout-split'), 'Split Feature layout must be renderable.');
$assert(str_contains($css, '.layout-bold'), 'Bold Offer layout must be renderable.');

$implementationText = strtolower($js . $css);
$assert(!str_contains($implementationText, 'popular choices'), 'Runtime rendering must not include Popular choices.');
$assert(!str_contains($implementationText, 'best seller'), 'Runtime rendering must not include invented best-seller claims.');
$assert(!str_contains($implementationText, 'ingredient grid'), 'Runtime rendering must not include ingredient grids.');
$assert(!str_contains($implementationText, 'toppings grid'), 'Runtime rendering must not include toppings grids.');

if ($errors) {
    fwrite(STDERR, "Social Design Center v2 validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Social Design Center v2 validation passed ({$checks} assertions).\n";
