<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relative) use ($root, &$failures): string {
    $path = $root . '/' . $relative;
    $content = @file_get_contents($path);
    if (!is_string($content)) {
        $failures[] = "Unable to read {$relative}";
        return '';
    }
    return $content;
};

$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$appHeader = $read('includes/header-components/app-header.php');
$loggedIn = $read('includes/header-templates/logged-in.php');
$css = $read('assets/css/gift-center-header-create-fix-v1.css');

$expect(
    str_contains($appHeader, '$show_header_create = !$is_agent_workspace_header && !$is_gift_center_header;'),
    'Gift Center must suppress the separate right-side global create trigger.'
);
$expect(
    str_contains($appHeader, 'mg-header-create mg-header-build-link mg-gift-center-create'),
    'Gift Center must render the create trigger beside the Inbox, Sent, and Claimed tabs.'
);
$expect(
    str_contains($appHeader, 'data-header-create data-global-create'),
    'Gift Center create trigger must use the canonical Create Center JavaScript contract.'
);
$expect(
    str_contains($appHeader, '/assets/css/gift-center-header-create-fix-v1.css?v=1.0.0'),
    'Gift Center header must load the scoped layout repair stylesheet.'
);
$expect(
    !str_contains($loggedIn, 'data-header-signal="messages"'),
    'The duplicate right-side Messages header signal must be removed.'
);
$expect(
    str_contains($loggedIn, 'data-header-signal="notifications"'),
    'The canonical notifications signal must remain available.'
);
$expect(
    str_contains($css, '.mg-section-agent .mg-header-gift-tools'),
    'The stylesheet must scope Gift Center row layout to the agent section.'
);
$expect(
    str_contains($css, 'grid-template-columns:minmax(0,1fr) 46px!important;'),
    'Mobile Gift Center layout must reserve a right-side column for the create trigger.'
);
$expect(
    str_contains($css, '.mg-section-agent .mg-gift-center-create'),
    'The create trigger must have a dedicated scoped selector.'
);
$expect(
    str_contains($css, '@media(min-width:721px)'),
    'Desktop Create Center full-viewport behavior must be breakpoint-scoped.'
);
$expect(
    str_contains($css, 'padding:0!important;'),
    'Desktop Create Center overlay padding must be removed.'
);
$expect(
    str_contains($css, 'width:100vw!important;')
    && str_contains($css, 'height:100dvh!important;')
    && str_contains($css, 'border-radius:0!important;'),
    'Desktop Create Center dialog must fill the viewport without an inset card edge.'
);

if ($failures !== []) {
    fwrite(STDERR, "Gift Center header/create layout validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Gift Center header/create layout validation passed.\n");
