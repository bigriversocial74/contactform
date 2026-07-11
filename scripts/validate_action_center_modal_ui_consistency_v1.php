<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$css = $read('assets/css/gift-action-center-modals.css');
$portal = $read('assets/js/gift-action-center-modal-portal.js');
$claim = $read('assets/js/gift-action-center-claim-modal.js');
$send = $read('assets/js/gift-action-center-send-modal.js');
$include = $read('includes/gift-action-center.php');

$checks = [
    'one canonical modal stylesheet is loaded' => str_contains($include, '/assets/css/gift-action-center-modals.css'),
    'obsolete mobile override is removed' => !is_file($root . '/assets/css/gift-claim-mobile-modal-fix.css')
        && !str_contains($portal, 'loadMobileClaimModalFix'),
    'action and claim shells share one header rule' => str_contains($css, 'body > .mg-action-modal .mg-action-modal-header,')
        && str_contains($css, '.mg-claim-modal-header {'),
    'action and claim close controls share one rule' => str_contains($css, 'body > .mg-action-modal [data-action-modal-close],')
        && str_contains($css, '.mg-claim-modal-close,')
        && str_contains($css, '.mg-modal-close {'),
    'close controls remain top right through header layout' => str_contains($css, 'justify-content: space-between !important;')
        && str_contains($css, 'flex: 0 0 42px !important;'),
    'regift no longer hides or floats the shared header' => !str_contains($css, '.mg-send-exact-modal .mg-action-modal-header{position:absolute')
        && !str_contains($css, '.mg-send-exact-modal .mg-action-modal-header>div{position:absolute'),
    'regift receives visible canonical header copy' => str_contains($portal, "title.textContent = 'Regift Microgift'")
        && str_contains($portal, 'eyebrow.textContent = eyebrowLabel'),
    'regift has one primary footer action and no footer Cancel control' => str_contains($portal, "const cancel = actions && actions.querySelector('.mg-send-exact-secondary,[data-action-modal-close]')")
        && str_contains($portal, 'if (cancel) cancel.remove();')
        && str_contains($portal, "actions.dataset.singleAction = 'true'")
        && str_contains($portal, "const desiredLabel = 'Review regift';")
        && str_contains($portal, 'primary.textContent = desiredLabel')
        && !str_contains($portal, "cancel.textContent = 'Cancel'"),
    'regift normalization cannot self-trigger indefinitely' => str_contains($portal, 'function queueActionModalNormalization(modal)')
        && str_contains($portal, 'primary.textContent.trim() !== desiredLabel')
        && !str_contains($portal, '/^Review Regift$/i'),
    'claim markup keeps close control after title group' => preg_match('/<header class="mg-claim-modal-header">.*?<div>.*?<\/div>\s*<button class="mg-claim-modal-close"/s', $claim) === 1,
    'desktop and mobile use the same modal shells' => str_contains($css, '@media (max-width: 760px)')
        && str_contains($css, 'body > .mg-action-modal,')
        && str_contains($css, '.mg-claim-modal,'),
    'keyboard focus is trapped inside open overlays' => str_contains($portal, "if (event.key !== 'Tab' || !modal) return;")
        && str_contains($portal, 'trapFocus(event, claimModal || actionModal || drawer);'),
    'focus returns to the opening action' => str_contains($portal, 'restoreFocus(lastActionTrigger)')
        && str_contains($portal, 'restoreFocus(lastClaimTrigger)'),
    'modal scripts remain behavior authorities' => str_contains($send, 'buildExactSendModal')
        && str_contains($claim, 'openForItem'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Action Center modal UI consistency score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Modal UI consistency regression failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Action Center modal UI consistency contract passed at 10.0/10.\n";
