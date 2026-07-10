<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

function mg_world_zoom_read(string $root, string $path): string
{
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException("Missing required file: {$path}");
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException("Unable to read required file: {$path}");
    return $content;
}

function mg_world_zoom_expect(bool $condition, string $label, array &$failures, int &$passes): void
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
    $loader = mg_world_zoom_read($root, 'assets/js/world-canvas-square-map.js');
    $zoom = mg_world_zoom_read($root, 'assets/js/world-canvas-geo-zoom.js');
    $dots = mg_world_zoom_read($root, 'assets/js/world-canvas-dot-system.js');
    $zoneScale = mg_world_zoom_read($root, 'assets/js/world-canvas-zone-scale.js');
    $progressiveCss = mg_world_zoom_read($root, 'assets/css/world-canvas-progressive-zoom.css');
    $arrivalCss = mg_world_zoom_read($root, 'assets/css/world-canvas-icon-zoom-stability.css');
    $transition = mg_world_zoom_read($root, 'assets/js/store-world-transition.js');
    $transitionCss = mg_world_zoom_read($root, 'assets/css/store-world-transition.css');
    $footer = mg_world_zoom_read($root, 'includes/footer.php');
    $drops = mg_world_zoom_read($root, 'assets/js/world-canvas-target-drops.js');

    mg_world_zoom_expect(
        str_contains($loader, '/assets/css/world-canvas-progressive-zoom.css'),
        'World Canvas loader includes the progressive zoom stylesheet',
        $failures,
        $passes
    );

    $tiers = ['world','region','city','store','detail'];
    $hasAllTiers = true;
    foreach ($tiers as $tier) {
        if (!str_contains($zoom, "return '{$tier}'") && !str_contains($zoom, "'{$tier}'")) {
            $hasAllTiers = false;
            break;
        }
    }
    mg_world_zoom_expect(
        $hasAllTiers && str_contains($zoom, 'root.dataset.worldZoomTier = tier'),
        'Zoom controller exposes five progressive detail tiers',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($zoom, "root.dataset.worldAvatarVisibility = 'show'")
        && str_contains($dots, "node.classList.remove('is-cluster-hidden')")
        && !str_contains($dots, 'function renderClusters'),
        'Every active user remains an individual visible dot at world zoom',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($progressiveCss, '[data-world-zoom-tier="world"] .mg-world-node')
        && str_contains($progressiveCss, '[data-world-zoom-tier="city"] .mg-world-node')
        && str_contains($progressiveCss, '[data-world-zoom-tier="detail"] .mg-world-node')
        && str_contains($progressiveCss, 'scale(var(--mg-world-inverse-zoom,1))'),
        'Users and merchants progress from dots to detailed cards without viewport double-scaling',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($zoneScale, 'base / Math.pow(zoom, 0.72)')
        && str_contains($zoneScale, '--mg-zone-screen-size')
        && str_contains($progressiveCss, '--mg-zone-scale')
        && str_contains($progressiveCss, '[data-world-zoom-tier="detail"] .mg-world-target-drop'),
        'Campaign Drop Zones shrink as zoom increases while progressively revealing detail',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($drops, 'Campaign Drop Zones')
        && str_contains($drops, 'Campaign Drop Zone')
        && !str_contains($drops, 'Gift Zone'),
        'World Canvas presents the renamed Campaign Drop Zone terminology',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($footer, '/assets/js/store-world-transition.js')
        && str_contains($footer, '/assets/css/store-world-transition.css')
        && str_contains($footer, 'if ($user)'),
        'Signed-in pages load one shared Store-to-World transition controller',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($transition, "[data-store-global-exit], [data-store-exit], [data-store-chat-exit]")
        && str_contains($transition, "MG.post('/api/store/exit.php', {})")
        && str_contains($transition, 'data.world_transition'),
        'All existing Store Canvas exit surfaces use the server-backed world transition response',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($transition, "/world-canvas.php?entry=store-exit")
        && str_contains($zoom, "get('entry') === 'store-exit'")
        && str_contains($zoom, "viewer.classList.add('is-store-arrival')")
        && str_contains($arrivalCss, 'mgWorldStoreArrival'),
        'Store exit animates into World Canvas and highlights the server-positioned viewer avatar',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($transitionCss, 'mgStoreWorldAvatar')
        && str_contains($transitionCss, 'prefers-reduced-motion'),
        'Store-to-World transition has responsive and reduced-motion presentation',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        !str_contains($transition, 'latitude:')
        && !str_contains($transition, 'longitude:')
        && !str_contains($transition, '/api/world-canvas/user-position.php')
        && !str_contains($transition, 'localStorage')
        && !str_contains($transition, 'sessionStorage'),
        'Transition animation cannot supply coordinates or create browser-local location authority',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($zoom, 'window.MicrogifterWorldZoom')
        && str_contains($zoom, 'geoToPoint: geoToPoint')
        && str_contains($zoom, "document.dispatchEvent(new CustomEvent('mg:world-zoom-change'"),
        'One zoom controller publishes a shared zoom state for nodes and Campaign Drop Zones',
        $failures,
        $passes
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("World Canvas progressive zoom validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "World Canvas progressive zoom validation passed: {$passes} checks.\n";
