<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/location-data.php';

$page_title = 'Locations | Microgifter';
$page_section = 'locations';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_scripts = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    '/assets/js/locations-map.js',
];

$page_manifest = [
    'id' => 'locations',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'header_controls' => [],
    'public_header' => ['presentation' => false, 'links' => []],
    'onboarding' => ['enabled' => false, 'page' => 'locations', 'sections' => []],
];

$states = mg_location_states();
$stateCounts = array_fill_keys(array_keys($states), 0);
$countError = false;
try {
    $stateCounts = mg_location_merchant_state_counts(mg_db());
} catch (Throwable) {
    $countError = true;
}
$totalMerchants = array_sum($stateCounts);

require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.mg-main{padding:0}.locations-shell,.locations-shell *{box-sizing:border-box}.locations-shell{--dark:#071225;--muted:#64748b;--border:#dbe5f1;--map:#eef3f8;--purple:#7c3aed;display:grid;grid-template-columns:360px minmax(0,1fr);min-height:calc(100vh - 72px);background:#f8fafc;color:var(--dark)}.locations-sidebar{position:relative;z-index:400;min-width:0;padding:24px 20px;border-right:1px solid var(--border);background:#fff;box-shadow:10px 0 28px rgba(15,23,42,.05)}.locations-sidebar-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px}.locations-sidebar-head h1{margin:0;color:var(--dark);font-size:30px;line-height:1;letter-spacing:-.05em}.locations-sidebar-head p{margin:9px 0 0;color:var(--muted);font-size:13px;line-height:1.5}.locations-count{min-width:58px;padding:8px 11px;border:1px solid var(--border);border-radius:16px;background:#f8fafc;text-align:center}.locations-count strong{display:block;color:#071225;font-size:18px;line-height:1}.locations-count small{display:block;margin-top:2px;color:#64748b;font-size:9px;font-weight:950;letter-spacing:.07em;text-transform:uppercase}.locations-search{position:relative;margin-bottom:16px}.locations-search:before{content:"⌕";position:absolute;top:50%;left:14px;transform:translateY(-50%);color:#94a3b8;font-size:18px}.locations-search input{width:100%;height:46px;padding:0 14px 0 42px;border:1px solid var(--border);border-radius:14px;outline:none;background:#fff;color:var(--dark)}.locations-status{margin:-5px 0 13px;color:#64748b;font-size:12px;line-height:1.4}.locations-list{max-height:calc(100vh - 275px);overflow:auto;padding-right:4px}.state-link{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:14px;min-height:54px;padding:0 12px;border-radius:13px;color:var(--dark);text-decoration:none;cursor:pointer;transition:.16s ease}.state-link:hover,.state-link.is-active{color:var(--purple);background:#f5f3ff;transform:translateX(2px)}.state-link.is-empty{opacity:.62}.state-link-main{display:flex;align-items:center;gap:11px;min-width:0}.state-code{width:34px;height:34px;display:grid;place-items:center;border:1px solid var(--border);border-radius:10px;background:#f8fafc;color:#475569;font-size:11px;font-weight:950}.state-name{min-width:0;font-size:14px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.state-right{display:flex;align-items:center;gap:8px}.state-count{min-width:25px;height:25px;display:grid;place-items:center;padding:0 7px;border-radius:999px;background:#e2e8f0;color:#475569;font-size:11px;font-weight:950}.state-link.has-merchants .state-count{background:#ede9fe;color:#6d28d9}.state-arrow{color:#94a3b8}.locations-map-wrap{position:relative;min-width:0;min-height:calc(100vh - 72px);background:var(--map);overflow:hidden}#locations-map{position:absolute;inset:0;width:100%;height:100%;background:var(--map)}.locations-map-note{position:absolute;left:22px;bottom:22px;z-index:450;max-width:380px;padding:14px 16px;border:1px solid rgba(219,229,241,.96);border-radius:16px;background:rgba(255,255,255,.95);box-shadow:0 18px 44px rgba(15,23,42,.1)}.locations-map-note strong{display:block;color:var(--dark);font-size:14px}.locations-map-note span{display:block;margin-top:4px;color:var(--muted);font-size:12px;line-height:1.45}.leaflet-container{font:inherit}.leaflet-tile{max-width:none!important;max-height:none!important}.leaflet-control-zoom{border:1px solid var(--border)!important;border-radius:12px!important;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.08)!important}.state-tooltip{padding:8px 10px;border:0;border-radius:10px;background:#071225;color:#fff;box-shadow:0 10px 22px rgba(15,23,42,.18);font-size:12px;font-weight:850;line-height:1.25}.state-tooltip span{display:block;margin-top:3px;color:#cbd5e1;font-size:11px;font-weight:800}.leaflet-interactive{transition:fill-opacity .16s ease,stroke .16s ease}@media(max-width:860px){.locations-shell{display:block;min-height:auto;background:#fff}.locations-sidebar{width:100%;min-height:auto;padding:22px 16px 40px;border-right:0;box-shadow:none}.locations-list{display:block;max-height:none;overflow:visible;padding:0}.state-link{width:100%;min-width:0;min-height:56px;padding:0 12px;border-bottom:1px solid #eef2f7;border-radius:0;background:#fff}.state-link:hover,.state-link.is-active{transform:none;border-radius:12px}.locations-map-wrap{display:none!important}}
</style>

<section class="locations-shell">
  <aside class="locations-sidebar">
    <div class="locations-sidebar-head">
      <div>
        <h1>Locations</h1>
        <p>Select a state to view local Microgifter merchants and offers.</p>
      </div>
      <span class="locations-count"><strong><?= mg_e((string) $totalMerchants) ?></strong><small>merchants</small></span>
    </div>
    <div class="locations-search"><input type="search" placeholder="Search by state or abbreviation" aria-label="Search locations" data-location-search></div>
    <p class="locations-status"><?= $countError ? 'Live merchant counts are temporarily unavailable.' : 'Live counts show states with published merchant products.' ?></p>
    <nav class="locations-list" aria-label="State locations" data-state-list>
      <?php foreach ($states as $code => $name): ?>
        <?php $count = (int) ($stateCounts[$code] ?? 0); ?>
        <a class="state-link<?= $count > 0 ? ' has-merchants' : ' is-empty' ?>" href="/location-results.php?state=<?= rawurlencode($code) ?>" data-state-link data-state-code="<?= mg_e($code) ?>" data-state-name="<?= mg_e(strtolower($name)) ?>" data-state-count="<?= $count ?>">
          <span class="state-link-main"><span class="state-code"><?= mg_e($code) ?></span><span class="state-name"><?= mg_e($name) ?></span></span>
          <span class="state-right"><span class="state-count"><?= $count ?></span><span class="state-arrow">→</span></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="locations-map-wrap">
    <div id="locations-map" aria-label="Interactive United States map" data-geojson-url="https://cdn.jsdelivr.net/gh/PublicaMundi/MappingAPI@master/data/geojson/us-states.json"></div>
    <div class="locations-map-note"><strong>Choose a state</strong><span>Map colors and sidebar badges show states with active merchants and published products.</span></div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
