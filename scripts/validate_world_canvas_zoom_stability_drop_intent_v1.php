<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

function mg_world_intent_read(string $root, string $path): string
{
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        throw new RuntimeException("Missing required file: {$path}");
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException("Unable to read required file: {$path}");
    }
    return $content;
}

function mg_world_intent_expect(bool $condition, string $label, array &$failures, int &$passes): void
{
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
}

try {
    $loader = mg_world_intent_read($root, 'assets/js/world-canvas-square-map.js');
    $stability = mg_world_intent_read($root, 'assets/js/world-canvas-zoom-interaction-stability.js');
    $stabilityCss = mg_world_intent_read($root, 'assets/css/world-canvas-zoom-interaction-stability.css');
    $detail = mg_world_intent_read($root, 'assets/js/world-canvas-detail-orchestration.js');
    $intent = mg_world_intent_read($root, 'assets/js/world-canvas-target-drop-intent.js');
    $intentCss = mg_world_intent_read($root, 'assets/css/world-canvas-target-drop-intent.css');

    mg_world_intent_expect(
        str_contains($loader, '/assets/js/world-canvas-zoom-interaction-stability.js')
        && str_contains($loader, '/assets/css/world-canvas-zoom-interaction-stability.css')
        && str_contains($loader, '/assets/js/world-canvas-target-drop-intent.js')
        && str_contains($loader, '/assets/css/world-canvas-target-drop-intent.css'),
        'World Canvas loader includes the zoom stability and drop intent assets',
        $failures,
        $passes
    );

    mg_world_intent_expect(
        str_contains($stability, "root.dataset.worldZoomInteraction = 'active'")
        && str_contains($stability, "document.dispatchEvent(new CustomEvent('mg:world-zoom-settled'")
        && str_contains($stability, 'MicrogifterWorldDetail.refresh')
        && str_contains($stability, "map.addEventListener('wheel'"),
        'Zoom and pan interactions expose an active phase and one settled refresh',
        $failures,
        $passes
    );

    mg_world_intent_expect(
        str_contains($detail, 'function interactionActive()')
        && str_contains($detail, 'if (interactionActive()) return;')
        && str_contains($detail, "document.addEventListener('mg:world-zoom-settled', schedule)"),
        'Detail budgets, collisions, and density pause while the viewport is moving',
        $failures,
        $passes
    );

    mg_world_intent_expect(
        str_contains($stabilityCss, '[data-world-zoom-interaction="active"] .mg-world-node')
        && str_contains($stabilityCss, 'transition:none!important')
        && str_contains($stabilityCss, '.mg-world-density-layer')
        && str_contains($stabilityCss, '.mg-world-target-drop em'),
        'Node, density, and Campaign Drop Zone presentation transitions are stabilized during movement',
        $failures,
        $passes
    );

    mg_world_intent_expect(
        str_contains($intent, 'Add Campaign Drop Zone')
        && str_contains($intent, 'Step 2 of 3')
        && str_contains($intent, 'Step 3 of 3')
        && str_contains($intent, 'Confirm and add draft'),
        'Campaign Drop Zone creation clearly presents the three-click flow',
        $failures,
        $passes
    );

    mg_world_intent_expect(
        str_contains($intent, "map.addEventListener('click'")
        && str_contains($intent, 'event.stopImmediatePropagation()')
        && str_contains($intent, "map.dispatchEvent(new MouseEvent('click'")
        && str_contains($intent, 'bypass = true'),
        'Blank-map creation is blocked until the explicit confirmation click',
        $failures,
        $passes
    );

    mg_world_intent_expect(
        str_contains($intent, 'role="dialog"')
        && str_contains($intent, 'aria-modal="true"')
        && str_contains($intent, "event.key !== 'Escape'")
        && str_contains($intentCss, '.mg-world-drop-confirm'),
        'Confirmation modal has dialog semantics, cancellation, and keyboard escape support',
        $failures,
        $passes
    );

    mg_world_intent_expect(
        !str_contains($intent, '/api/world-canvas/target-drops.php')
        && !str_contains($intent, 'fetch(')
        && !str_contains($intent, 'MG.post('),
        'Intent guard does not duplicate or bypass the existing server-authoritative creation controller',
        $failures,
        $passes
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("World Canvas zoom stability/drop intent validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "World Canvas zoom stability/drop intent validation passed: {$passes} checks.\n";
