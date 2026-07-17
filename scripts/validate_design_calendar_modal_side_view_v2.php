<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page' => 'design-calendar.php',
    'workspace' => 'includes/personal-agent/workspace-design-calendar.php',
    'css' => 'assets/css/design-calendar-modal-side-view-v2.css',
    'products' => 'assets/js/design-calendar-product-cards-v2.js',
    'side' => 'assets/js/design-calendar-side-view-v2.js',
    'usability_css' => 'assets/css/design-calendar-usability-v3.css',
    'product_tools' => 'assets/js/design-calendar-product-tools-v3.js',
    'view_preferences' => 'assets/js/design-calendar-view-preferences-v3.js',
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
    'calendar loads the additive usability v3 assets' =>
        str_contains($content['page'], 'design-calendar-usability-v3.css?v=3.0.0')
        && str_contains($content['page'], 'design-calendar-product-tools-v3.js?v=3.0.0')
        && str_contains($content['page'], 'design-calendar-view-preferences-v3.js?v=3.0.0'),
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
    'product search and status filters are available' =>
        str_contains($content['product_tools'], 'data-calendar-product-search')
        && str_contains($content['product_tools'], 'data-calendar-product-status')
        && str_contains($content['product_tools'], "option value=\"published\"")
        && str_contains($content['product_tools'], "option value=\"draft\"")
        && str_contains($content['product_tools'], 'rowText(row).includes(search)'),
    'selected-only review and published selection tools are available' =>
        str_contains($content['product_tools'], 'data-calendar-product-selected-only')
        && str_contains($content['product_tools'], 'data-calendar-review-selection')
        && str_contains($content['product_tools'], 'data-calendar-select-published')
        && str_contains($content['product_tools'], 'data-calendar-clear-product-selection'),
    'sticky summary counts products formats layouts and themes' =>
        str_contains($content['product_tools'], 'data-calendar-selection-summary')
        && str_contains($content['product_tools'], "selectedCount('formats')")
        && str_contains($content['product_tools'], "selectedCount('layouts')")
        && str_contains($content['product_tools'], "selectedCount('themes')")
        && str_contains($content['usability_css'], 'position:sticky'),
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
    'side density includes compact standard and large layouts' =>
        str_contains($content['view_preferences'], 'data-calendar-density="compact"')
        && str_contains($content['view_preferences'], 'data-calendar-density="standard"')
        && str_contains($content['view_preferences'], 'data-calendar-density="large"')
        && str_contains($content['usability_css'], 'minmax(205px,1fr)')
        && str_contains($content['usability_css'], 'minmax(340px,1fr)'),
    'calendar view and side density persist locally' =>
        str_contains($content['view_preferences'], 'microgifter.designCalendar.view')
        && str_contains($content['view_preferences'], 'microgifter.designCalendar.sideDensity')
        && str_contains($content['view_preferences'], 'localStorage.setItem')
        && str_contains($content['view_preferences'], 'button?.click()'),
    'calendar generation and edit endpoints remain unchanged' =>
        str_contains($content['side'], '/api/merchant/design-content-calendar.php')
        && str_contains($content['workspace'], 'data-calendar-generator')
        && str_contains($content['workspace'], 'data-calendar-plan-open'),
    'feature does not introduce SQL or configuration behavior' =>
        !str_contains($content['products'], 'CREATE TABLE')
        && !str_contains($content['side'], 'ALTER TABLE')
        && !str_contains($content['product_tools'], 'CREATE TABLE')
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