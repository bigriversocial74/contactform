<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$read = static function (string $path) use ($root, &$errors): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $errors[] = "Missing required file: {$path}";
        return '';
    }
    $content = file_get_contents($full);
    if ($content === false) {
        $errors[] = "Unable to read file: {$path}";
        return '';
    }
    return $content;
};

$assertContains = static function (string $content, string $needle, string $message) use (&$errors): void {
    if (!str_contains($content, $needle)) $errors[] = $message;
};

$assertNotContains = static function (string $content, string $needle, string $message) use (&$errors): void {
    if (str_contains($content, $needle)) $errors[] = $message;
};

$crmPage = $read('merchant-crm.php');
$crmLink = $read('assets/js/merchant-crm-contact-link-polish.js');
$activity = $read('api/world-canvas/activity.php');
$worldPage = $read('world-canvas.php');
$squareLoader = $read('assets/js/world-canvas-square-map.js');
$zoomV2 = $read('assets/js/world-canvas-geo-zoom-v2.js');

$assertContains($crmPage, 'merchant-crm-contact-link-polish.js?v=2.0.0', 'Merchant CRM must cache-bump the customer profile link controller.');
$assertContains($crmLink, "'/merchant-customer.php?campaign_contact_id='", 'CRM contact names must target the dedicated merchant customer page.');
$assertContains($crmLink, "actionLink.removeAttribute('data-crm-view-customer')", 'CRM profile links must remove the drawer interception trigger.');
$assertContains($crmLink, "link.setAttribute('data-crm-customer-profile-link'", 'CRM contact names must be marked as direct customer profile links.');
$assertNotContains($crmLink, "link.setAttribute('data-crm-view-customer'", 'CRM contact names must not retain the timeline drawer trigger.');

$assertContains($activity, '$hasViewerMerchantAnchor', 'World Canvas activity must detect viewer merchant anchors.');
$assertContains($activity, "($node['type'] ?? '') === 'merchant' && !empty($node['owned'])", 'World Canvas activity must remove duplicate aggregate owned merchant nodes.');
$assertContains($activity, 'mg_world_canvas_merge_viewer_nodes($payload, $viewerNodes)', 'World Canvas must merge stable viewer anchors after deduplication.');

$assertContains($worldPage, 'world-canvas-square-map.js?v=2.0.0', 'World Canvas must cache-bump the square map loader.');
$assertContains($squareLoader, 'world-canvas-geo-zoom-v2.js?v=2.0.0', 'Square map loader must load the v2 zoom controller.');
$assertNotContains($squareLoader, "addScript('/assets/js/world-canvas-geo-zoom.js'", 'Square map loader must not load the legacy zoom controller.');

$assertContains($zoomV2, "var node = target.closest && target.closest('[data-world-node]')", 'Zoom v2 must recognize map markers as valid drag origins.');
$assertContains($zoomV2, 'if (node) return false;', 'Zoom v2 must allow pointer drag starts over map markers.');
$assertContains($zoomV2, 'dragThreshold = 5', 'Zoom v2 must distinguish clicks from map drags.');
$assertContains($zoomV2, 'state.suppressNodeClickUntil = Date.now() + 350', 'Zoom v2 must suppress marker activation after a real pan.');
$assertContains($zoomV2, "event.target.closest('[data-world-node]')", 'Zoom v2 must scope post-drag click suppression to map markers.');
$assertContains($zoomV2, "map.style.touchAction = 'none'", 'Zoom v2 must own touch panning behavior.');
$assertContains($zoomV2, "qs('[data-world-node].is-merchant.is-owned.is-geo-locked'", 'Zoom v2 must prefer the stable owned merchant anchor as the current viewer.');

if ($errors !== []) {
    fwrite(STDERR, "CRM customer link / World Canvas stability validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "CRM customer link / World Canvas stability validation passed.\n");
