<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page' => 'design-calendar.php',
    'workspace' => 'includes/personal-agent/workspace-design-calendar.php',
    'css' => 'assets/css/design-calendar-modal-side-view-v2.css',
    'products' => 'assets/js/design-calendar-product-cards-v2.js',
    'side' => 'assets/js/design-calendar-side-view-v2.js',
];

$content = [];
foreach ($paths as $key => $path) {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    $content[$key] = $value;
}

$checks = [
    'calendar loads the v2 modal and display assets' =>
        str_contains($content['page'], 'design-calendar-modal-side-view-v2.css?v=2.0.0')
        && str_contains($content['page'], 'design-calendar-product-cards-v2.js?v=2.0.0')
        && str_contains($content['page'], 'design-calendar-side-view-v2.js?v=2.0.0'),
    'side by side is the third calendar display' =>
        str_contains($content['workspace'], 'data-calendar-view="grid"')
        && str_contains($content['workspace'], 'data-calendar-view="stack"')
        && str_contains($content['workspace'], 'data-calendar-view="side"')
        && str_contains($content['workspace'], 'data-calendar-side hidden'),
    'planner product area uses the complete modal row' =>
        str_contains($content['css'], '.mg-calendar-plan-dialog .mg-design-calendar-form')
        && str_contains($content['css'], 'grid-template-columns:1fr!important')
        && str_contains($content['css'], '.mg-calendar-product-card'),
    'product cards use live catalog presentation without raw metadata parsing' =>
        str_contains($content['products'], '/api/ads/merchant-products.php?status=all')
        && str_contains($content['products'], "item.source || '') === 'catalog_product'")
        && str_contains($content['products'], 'item.image_url')
        && !str_contains($content['products'], 'metadata_json'),
    'planner checkbox controls render as accessible switches' =>
        str_contains($content['css'], 'input[type="checkbox"]')
        && str_contains($content['css'], 'appearance:none')
        && str_contains($content['css'], ':checked::after')
        && str_contains($content['css'], 'transform:translateX(16px)'),
    'side view renders every filtered scheduled ad as an editable template' =>
        str_contains($content['side'], 'data-calendar-side')
        && str_contains($content['side'], 'data-calendar-event=')
        && str_contains($content['side'], 'data-calendar-open')
        && str_contains($content['side'], 'refs.has(String(item.public_id'),
    'side view wraps responsive ad units with native format proportions' =>
        str_contains($content['css'], 'grid-template-columns:repeat(auto-fill,minmax(250px,1fr))')
        && str_contains($content['css'], '.mg-calendar-side-ad-unit.is-square{aspect-ratio:1/1}')
        && str_contains($content['css'], '.mg-calendar-side-ad-unit.is-portrait{aspect-ratio:4/5}')
        && str_contains($content['css'], '.mg-calendar-side-ad-unit.is-story{aspect-ratio:9/16}'),
    'each wrapped row equalizes to its tallest ad card' =>
        str_contains($content['side'], 'card.offsetTop')
        && str_contains($content['side'], 'Math.max(...cardsInRow.map')
        && str_contains($content['side'], "card.style.minHeight = tallest + 'px'")
        && str_contains($content['side'], 'ResizeObserver'),
    'calendar generation and edit endpoints remain unchanged' =>
        str_contains($content['side'], "/api/merchant/design-content-calendar.php")
        && str_contains($content['workspace'], 'data-calendar-generator')
        && str_contains($content['workspace'], 'data-calendar-plan-open'),
    'feature does not introduce SQL or configuration behavior' =>
        !str_contains($content['products'], 'CREATE TABLE')
        && !str_contains($content['side'], 'ALTER TABLE')
        && !str_contains($content['workspace'], 'config.php'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "\nDesign Calendar modal/side-view validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

$total = count($checks);
echo "\nDesign Calendar modal/side-view contract: {$total}/{$total}.\n";
