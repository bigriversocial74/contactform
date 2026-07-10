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
    $detail = mg_world_zoom_read($root, 'assets/js/world-canvas-detail-orchestration.js');
    $progressiveCss = mg_world_zoom_read($root, 'assets/css/world-canvas-progressive-zoom.css');
    $detailCss = mg_world_zoom_read($root, 'assets/css/world-canvas-detail-orchestration.css');
    $arrivalCss = mg_world_zoom_read($root, 'assets/css/world-canvas-icon-zoom-stability.css');
    $transition = mg_world_zoom_read($root, 'assets/js/store-world-transition.js');
    $transitionCss = mg_world_zoom_read($root, 'assets/css/store-world-transition.css');
    $presence = mg_world_zoom_read($root, 'assets/js/store-presence-feed.js');
    $chat = mg_world_zoom_read($root, 'assets/js/store-chat-widget.js');
    $runtime = mg_world_zoom_read($root, 'api/store/_canvas_runtime.php');
    $worldTransition = mg_world_zoom_read($root, 'api/store/_world_transition.php');
    $heartbeat = mg_world_zoom_read($root, 'api/store/heartbeat.php');
    $sessionStatus = mg_world_zoom_read($root, 'api/store/session-status.php');
    $chatApi = mg_world_zoom_read($root, 'api/store/chat-widget.php');
    $footer = mg_world_zoom_read($root, 'includes/footer.php');
    $drops = mg_world_zoom_read($root, 'assets/js/world-canvas-target-drops.js');

    mg_world_zoom_expect(
        str_contains($loader, '/assets/css/world-canvas-progressive-zoom.css')
        && str_contains($loader, '/assets/css/world-canvas-detail-orchestration.css')
        && str_contains($loader, '/assets/js/world-canvas-detail-orchestration.js'),
        'World Canvas loader includes progressive zoom and detail orchestration assets',
        $failures,
        $passes
    );

    $tiers = ['world','region','city','store','detail'];
    $hasAllTiers = true;
    foreach ($tiers as $tier) {
        if (!str_contains($zoom, "'{$tier}'")) {
            $hasAllTiers = false;
            break;
        }
    }
    mg_world_zoom_expect(
        $hasAllTiers
        && str_contains($zoom, 'root.dataset.worldZoomTier = currentTier')
        && str_contains($zoom, 'zoomProgress(state.zoom)'),
        'Zoom controller exposes five progressive detail tiers and continuous progress',
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
        str_contains($zoom, '--mg-world-region-progress')
        && str_contains($zoom, '--mg-world-city-progress')
        && str_contains($zoom, '--mg-world-store-progress')
        && str_contains($zoom, '--mg-world-detail-progress')
        && str_contains($detailCss, 'transition:opacity .22s ease'),
        'Zoom tiers crossfade detail through continuous progress variables',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($detail, 'applyViewportBudget')
        && str_contains($detail, 'data-world-in-viewport')
        && str_contains($detail, 'is-detail-lite')
        && str_contains($detailCss, 'content-visibility:auto')
        && !str_contains($detailCss, '.mg-world-viewport{position:relative'),
        'Detailed cards are viewport-budgeted without replacing the map viewport positioning contract',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($detail, 'applyCollisionLayout')
        && str_contains($detail, '--mg-world-collision-x')
        && str_contains($detail, '--mg-world-label-shift-x')
        && str_contains($detailCss, 'is-world-collision-shifted:after'),
        'Screen-space collision management offsets labels while preserving coordinate anchors',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($detail, 'renderDensity')
        && str_contains($detail, 'spot.count >= 2')
        && str_contains($detailCss, '.mg-world-density-layer')
        && !str_contains($detail, 'is-cluster-hidden'),
        'Density illumination summarizes activity without replacing individual dots',
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
        'Signed-in pages load one shared Store transition controller',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($transition, '[data-store-global-exit], [data-store-exit], [data-store-chat-exit]')
        && str_contains($transition, "MG.post('/api/store/exit.php', {})")
        && str_contains($transition, 'data.world_transition'),
        'All manual Store Canvas exit surfaces use the server-backed world transition response',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($presence, "emit('mg:store-session-ended'")
        && str_contains($presence, "emit('mg:store-switched'")
        && str_contains($chat, 'mg:store-session-ended')
        && str_contains($transition, 'mg:store-session-ended')
        && str_contains($transition, 'mg:store-switched'),
        'Timed-out sessions and merchant switches use shared automatic transition events',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($runtime, 'mg_store_world_transition_from_session')
        && str_contains($runtime, "'timeout'")
        && str_contains($worldTransition, "'merchant_location_opt_in'")
        && str_contains($worldTransition, 'mg_world_location_save_user')
        && str_contains($heartbeat, 'mg_store_runtime_heartbeat'),
        'Timed-out sessions return opted-in avatars to World Canvas through server location authority',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($worldTransition, 'function mg_store_world_transition_eligible')
        && str_contains($runtime, 'function mg_store_runtime_project_session')
        && str_contains($runtime, "['world_transition_eligible']")
        && str_contains($sessionStatus, 'mg_store_runtime_project_session')
        && str_contains($heartbeat, 'mg_store_runtime_project_session')
        && str_contains($chatApi, 'mg_store_runtime_project_session')
        && str_contains($transition, 'session.world_transition_eligible === true')
        && !str_contains($transition, 'if (detail.world_transition &&'),
        'Automatic redirects require server-projected merchant-location transition eligibility',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($runtime, "'switched_from'=>\$switchedFrom")
        && str_contains($transition, "mode: 'switch'")
        && !str_contains($transition, "mode: 'switch', world_transition"),
        'Store switching animates between active stores without creating false World Canvas authority',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($transition, '/world-canvas.php?entry=store-exit')
        && str_contains($zoom, "get('entry') === 'store-exit'")
        && str_contains($zoom, "viewer.classList.add('is-store-arrival')")
        && str_contains($arrivalCss, 'mgWorldStoreArrival'),
        'Store exit animates into World Canvas and highlights the server-positioned viewer avatar',
        $failures,
        $passes
    );

    mg_world_zoom_expect(
        str_contains($transitionCss, 'mgStoreWorldAvatar')
        && str_contains($transitionCss, 'mgStoreSwitchPulse')
        && str_contains($transitionCss, 'prefers-reduced-motion'),
        'Store-to-World and Store-to-Store transitions are responsive and reduced-motion safe',
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
        && str_contains($zoom, "document.dispatchEvent(new CustomEvent('mg:world-zoom-change'")
        && str_contains($detail, 'window.MicrogifterWorldDetail'),
        'One zoom controller publishes shared state to the detail orchestrator and Campaign Drop Zones',
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
