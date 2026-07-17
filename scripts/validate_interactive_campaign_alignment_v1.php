<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$instant = $read('instant-win.php');
$stamp = $read('stamp-card.php');
$css = $read('assets/css/public-campaign-experience-v1.css');
$instantJs = $read('assets/js/public-instant-win.js');
$stampJs = $read('assets/js/public-stamp-card.js');
$campaignCore = $read('api/merchant/campaigns-core.php');
$foundation = $read('includes/campaign-landing-foundation.php');
$loyaltyJs = $read('assets/js/loyalty-cards.js');

foreach ([
    'instant-win.php' => [$instant, 'instant_win_reward'],
    'stamp-card.php' => [$stamp, 'stamp_card_reward'],
] as $path => [$source, $type]) {
    $bootstrapPosition = strpos($source, "mg_campaign_landing_bootstrap('" . $type . "'");
    $headerPosition = strpos($source, "require __DIR__ . '/includes/header.php'");
    $usesStandardCards = $path === 'stamp-card.php'
        ? str_contains($source, 'data-campaign-foundation-cards') && str_contains($source, 'mg-stamp-summary-card')
        : str_contains($source, 'mg_campaign_landing_render_bottom_cards');
    $assert($path . ' exists', $source !== '');
    $assert($path . ' uses canonical campaign bootstrap', $bootstrapPosition !== false);
    $assert($path . ' loads campaign metadata before the header', $bootstrapPosition !== false && $headerPosition !== false && $bootstrapPosition < $headerPosition);
    $assert($path . ' uses canonical campaign state', str_contains($source, 'mg_campaign_landing_state'));
    $assert($path . ' uses canonical merchant profile component', str_contains($source, 'mg_campaign_landing_render_profile'));
    $assert($path . ' uses standardized foundation cards', $usesStandardCards);
    $assert($path . ' exposes campaign state on the page', str_contains($source, 'data-campaign-state'));
    $assert($path . ' supports merchant preview', str_contains($source, 'data-merchant-campaign-preview'));
    $assert($path . ' uses campaign artwork priority', str_contains($source, 'mg_campaign_landing_campaign_image'));
    $assert($path . ' no longer owns a duplicate campaign SQL loader', !str_contains($source, 'FROM campaigns c'));
    $assert($path . ' no longer defines a duplicate URL sanitizer', !preg_match('/function\s+mg_(?:instant_win|stamp_card)_page_safe_url/', $source));
}

$assert('Instant Win exposes canonical scratch and wheel modes',
    str_contains($instant, "['scratch_reveal' => 'scratch_card'")
    && str_contains($instant, "['scratch_card', 'spin_wheel']")
    && str_contains($instant, 'data-campaign-mode'));
$assert('Instant Win keeps both interaction modes',
    str_contains($instant, 'data-instant-scratch-canvas')
    && str_contains($instant, 'mg-instant-wheel')
    && str_contains($instant, '/api/public/campaigns/instant-win.php'));
$assert('Instant Win standardizes card sections',
    !str_contains($instant, '<span class="mg-rl-eyebrow">History</span>')
    && !str_contains($instant, '<span class="mg-rl-eyebrow">Status</span>'));

$assert('Stamp Card exposes verified runtime mode',
    str_contains($stamp, "'stamp_card' => 'verified_stamp_card'")
    && str_contains($stamp, 'data-campaign-mode="<?= mg_e($campaignMode) ?>"'));
$assert('Stamp Card renders campaign artwork in the public canvas',
    str_contains($stamp, 'mg-stamp-campaign-art')
    && str_contains($stamp, '$campaignImageUrl ? \'Campaign image\''));
$assert('Stamp Card preserves Saved Card toggle',
    str_contains($stamp, 'data-loyalty-save-toggle')
    && str_contains($stamp, 'data-campaign-id'));
$assert('Stamp Card preserves verified progress runtime',
    str_contains($stamp, 'data-stamp-card-visual')
    && str_contains($stamp, 'data-stamp-grid')
    && str_contains($stamp, '/api/public/campaigns/stamp-card.php'));
$assert('Stamp Card standardizes card sections',
    !str_contains($stamp, '<span class="mg-rl-eyebrow">Verification</span>')
    && !str_contains($stamp, '<span class="mg-rl-eyebrow">CRM Rule</span>'));
$assert('Stamp Card separates details, rules, and updates into three cards',
    substr_count($stamp, '<article class="mg-rl-card mg-stamp-summary-card') === 3
    && str_contains($stamp, 'Item details')
    && str_contains($stamp, 'Reward &amp; campaign rules')
    && str_contains($stamp, 'Active status &amp; updates'));

$assert('Merchant campaign controller persists canonical interactive modes',
    str_contains($campaignCore, "['scratch_card', 'spin_wheel']")
    && str_contains($campaignCore, "'mode' => 'verified_stamp_card'"));
$assert('Campaign foundation exposes required shared primitives',
    str_contains($foundation, 'function mg_campaign_landing_bootstrap')
    && str_contains($foundation, 'function mg_campaign_landing_render_bottom_cards'));

$assert('Interactive CSS is scoped to interactive landing pages',
    str_contains($css, '.mg-rl-interactive .mg-rl-player')
    && str_contains($css, '.mg-rl-instant .mg-instant-card')
    && str_contains($css, '.mg-rl-stamp .mg-stamp-card-visual'));
$assert('Interactive CSS no longer applies global page or button overrides',
    !str_contains($css, 'html,body')
    && !str_contains($css, '.mg-btn,.mg-public-campaign-primary-action'));
$assert('Interactive CSS includes responsive campaign artwork layouts',
    str_contains($css, '.mg-instant-stage.has-campaign-art')
    && str_contains($css, '.mg-stamp-stage.has-campaign-art')
    && str_contains($css, '@media (max-width: 980px)'));

$assert('Instant Win initializes once per page rather than once per duplicated form',
    str_contains($instantJs, "querySelectorAll('[data-instant-win-experience]')")
    && !str_contains($instantJs, "querySelectorAll('[data-instant-win-form]').forEach"));
$assert('Instant Win synchronizes both rendered forms',
    str_contains($instantJs, "page.querySelectorAll('[data-instant-win-form]')")
    && str_contains($instantJs, 'setRevealFields'));
$assert('Stamp Card initializes once per page rather than once per duplicated form',
    str_contains($stampJs, "querySelectorAll('[data-stamp-card-experience]')")
    && !str_contains($stampJs, "querySelectorAll('[data-stamp-card-form]').forEach"));
$assert('Stamp Card synchronizes progress and submit controls',
    str_contains($stampJs, 'setButtonsBusy')
    && str_contains($stampJs, 'updateProgress')
    && str_contains($stampJs, 'Reward sent to Inbox'));
$assert('Loyalty client still binds the structural save toggle',
    str_contains($loyaltyJs, "document.querySelector('[data-loyalty-save-toggle]')"));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Interactive Campaign Alignment v1 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Interactive Campaign Alignment v1 validation passed.' . PHP_EOL;
