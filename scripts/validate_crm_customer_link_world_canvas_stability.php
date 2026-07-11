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
$runtime = $read('assets/js/world-canvas-runtime-v2.js');
$normalizer = $read('api/world-canvas/_runtime_v2.php');

$assertContains($crmPage, 'merchant-crm-contact-link-polish.js?v=3.0.0', 'Merchant CRM must load the current customer profile link and contact-row controller.');
$assertContains($crmLink, "'/merchant-customer.php?campaign_contact_id='", 'CRM contact names must target the dedicated merchant customer page.');
$assertContains($crmLink, "actionLink.removeAttribute('data-crm-view-customer')", 'CRM profile links must remove the drawer interception trigger.');
$assertContains($crmLink, "link.setAttribute('data-crm-customer-profile-link'", 'CRM contact names must be marked as direct customer profile links.');
$assertNotContains($crmLink, "link.setAttribute('data-crm-view-customer'", 'CRM contact names must not retain the timeline drawer trigger.');

$assertContains($activity, '$hasViewerMerchantAnchor', 'World Canvas activity must detect viewer merchant anchors.');
$assertContains($activity, '(($node[\'type\'] ?? \'\') === \'merchant\' && !empty($node[\'owned\']))', 'World Canvas activity must remove duplicate aggregate owned merchant nodes.');
$assertContains($activity, 'mg_world_canvas_merge_viewer_nodes($payload, $viewerNodes)', 'World Canvas must merge stable viewer anchors after initial deduplication.');
$assertContains($activity, 'mg_world_canvas_runtime_v2($pdo, $user, $payload)', 'World Canvas must run the canonical v2 entity and geography normalizer.');

$assertContains($worldPage, 'maplibre-gl@5.7.1', 'World Canvas must load the pinned MapLibre geographic runtime.');
$assertContains($worldPage, 'three@0.160.0', 'World Canvas must load the pinned Three.js gameplay layer.');
$assertContains($worldPage, 'world-canvas-runtime-v2.js?v=2.1.0', 'World Canvas must load the current v2 runtime.');
$assertNotContains($worldPage, 'world-canvas-square-map.js', 'World Canvas must not load the legacy square-map loader.');
$assertNotContains($worldPage, 'world-canvas-geo-zoom-v2.js', 'World Canvas must not load the retired transform-based zoom controller.');

$assertContains($runtime, 'new window.maplibregl.Map', 'Runtime v2 must let MapLibre own map camera and projection.');
$assertContains($runtime, 'new window.maplibregl.Marker', 'Runtime v2 must render geographic markers through MapLibre.');
$assertContains($runtime, 'draggable:Boolean(d.owned)', 'Owned Campaign Drops must use native map marker dragging.');
$assertContains($runtime, "m.on('dragend'", 'Campaign Drop coordinates must save after native marker dragging.');
$assertContains($runtime, 'new window.THREE.WebGLRenderer', 'Runtime v2 must initialize the Three.js gameplay-effects layer.');
$assertContains($runtime, 'entity_key||n.id||n.detail_id', 'Runtime v2 must deduplicate map entities with stable keys.');
$assertContains($runtime, "MG.post('/api/world-canvas/persona.php'", 'Runtime v2 must persist the selected user or merchant persona.');

$assertContains($normalizer, "'merchant_location_source' => 'merchant_locations'", 'Merchant avatar geography must use registered merchant locations.');
$assertContains($normalizer, "'user_location_source' => 'user_world_positions'", 'User avatar geography must use saved/shared user positions.');
$assertContains($normalizer, "'random_geo_fallback' => false", 'Runtime v2 must not place unresolved identities at random coordinates.');
$assertContains($normalizer, 'entered_registered_merchant_location', 'In-store users without shared coordinates must fall back to the entered registered location.');

if ($errors !== []) {
    fwrite(STDERR, "CRM customer link / World Canvas stability validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "CRM customer link / World Canvas stability validation passed.\n");
