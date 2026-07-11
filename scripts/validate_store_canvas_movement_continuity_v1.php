<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . ltrim($path, '/'));
    return is_string($content) ? $content : '';
};

$check = static function (bool $passed, string $message) use (&$failures): void {
    if (!$passed) $failures[] = $message;
};

$page = $read('merchant-canvas.php');
$runtime = $read('assets/js/merchant-canvas-movement-continuity.js');
$manual = $read('assets/js/merchant-canvas-manual-operations.js');
$visual = $read('assets/js/merchant-canvas-visual-restoration.js');

$manualIndex = strpos($page, '/assets/js/merchant-canvas-manual-operations.js');
$continuityIndex = strpos($page, '/assets/js/merchant-canvas-movement-continuity.js');
$visualIndex = strpos($page, '/assets/js/merchant-canvas-visual-restoration.js');

$check(
    $manualIndex !== false
    && $continuityIndex !== false
    && $visualIndex !== false
    && $manualIndex < $continuityIndex
    && $continuityIndex < $visualIndex,
    'Movement continuity must load after live polling and before visual movement.'
);

$check(
    str_contains($runtime, 'MutationObserver')
    && str_contains($runtime, 'record.removedNodes')
    && str_contains($runtime, 'record.addedNodes')
    && str_contains($runtime, "positions.set(sessionId")
    && str_contains($runtime, "card.style.left = saved.left")
    && str_contains($runtime, "card.style.top = saved.top"),
    'Movement continuity must preserve and restore coordinates by session ID.'
);

$check(
    str_contains($runtime, "card.dataset.visualMovement = 'presentation-only'")
    && str_contains($runtime, "card.style.setProperty('transition', 'none')")
    && str_contains($runtime, 'function applySavedPosition(card, saved)')
    && str_contains($runtime, "delete card.dataset.movementContinuity"),
    'Restored coordinates must remain presentation-only and suppress origin transitions until settled.'
);

$check(
    substr_count($runtime, 'applySavedPosition(card, saved);') >= 3
    && substr_count($runtime, 'window.requestAnimationFrame(function ()') >= 3
    && str_contains($runtime, 'The visual-restoration runtime also reacts to the same live-poll DOM')
    && str_contains($runtime, 'restorationTokens.get(sessionId) !== token'),
    'Poll replacement coordinates must be reapplied after competing visual-restoration frames.'
);

$check(
    str_contains($runtime, 'window.__mgMerchantCanvasMovementContinuityBooted')
    && str_contains($runtime, 'restorationTokens.clear()')
    && str_contains($runtime, 'observer.disconnect()'),
    'Movement continuity must boot once and release transient restoration state on page exit.'
);

$check(
    !str_contains($runtime, 'localStorage')
    && !str_contains($runtime, 'sessionStorage')
    && !str_contains($runtime, 'setInterval(')
    && !str_contains($runtime, '/api/')
    && !str_contains($runtime, 'fetch('),
    'Movement continuity must stay in-memory and cannot perform customer-impacting actions.'
);

$check(
    str_contains($manual, 'poll_after_ms || 7000')
    && str_contains($manual, "loadCanvas({ reason: 'poll' })")
    && str_contains($manual, 'clear(layer)')
    && str_contains($manual, "layer.insertAdjacentHTML('beforeend', state.customers.map(customerCard).join(''))")
    && str_contains($visual, 'positionCustomers')
    && str_contains($visual, 'state.customerObserver = new MutationObserver'),
    'Validation must cover the seven-second DOM rebuild and competing visual positioning runtime.'
);

if ($failures !== []) {
    fwrite(STDERR, "Store Canvas Movement Continuity v2 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Store Canvas Movement Continuity v2 validation passed.\n");
