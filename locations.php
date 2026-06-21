<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Locations | Microgifter';
$page_section = 'locations';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];

$page_manifest = [
    'id' => 'locations',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'locations',
        'sections' => [],
    ],
];

$states = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
    'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
    'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
    'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
    'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
    'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
    'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
    'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
    'WI' => 'Wisconsin', 'WY' => 'Wyoming',
];

require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
:root{
  --locations-dark:#071225;
  --locations-text:#1f2937;
  --locations-muted:#64748b;
  --locations-border:#dbe5f1;
  --locations-map:#eef3f8;
  --locations-purple:#7c3aed;
}

.mg-main{
  padding:0;
}

.locations-shell,
.locations-shell *{
  box-sizing:border-box;
}

.locations-shell{
  display:grid;
  grid-template-columns:340px minmax(0,1fr);
  min-height:calc(100vh - 72px);
  background:#f8fafc;
}

.locations-sidebar{
  position:relative;
  z-index:400;
  min-width:0;
  padding:24px 20px;
  border-right:1px solid var(--locations-border);
  background:#fff;
  box-shadow:10px 0 28px rgba(15,23,42,.05);
}

.locations-sidebar-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
  margin-bottom:20px;
}

.locations-sidebar-head h1{
  margin:0;
  color:var(--locations-dark);
  font-size:30px;
  line-height:1;
  letter-spacing:-.05em;
}

.locations-sidebar-head p{
  margin:9px 0 0;
  color:var(--locations-muted);
  font-size:13px;
  line-height:1.5;
}

.locations-count{
  min-width:42px;
  padding:7px 10px;
  border:1px solid var(--locations-border);
  border-radius:999px;
  background:#f8fafc;
  color:#475569;
  text-align:center;
  font-size:12px;
  font-weight:900;
}

.locations-search{
  position:relative;
  margin-bottom:16px;
}

.locations-search::before{
  content:"⌕";
  position:absolute;
  top:50%;
  left:14px;
  transform:translateY(-50%);
  color:#94a3b8;
  font-size:18px;
}

.locations-search input{
  width:100%;
  height:46px;
  padding:0 14px 0 42px;
  border:1px solid var(--locations-border);
  border-radius:14px;
  outline:none;
  background:#fff;
  color:var(--locations-dark);
}

.locations-list{
  max-height:calc(100vh - 245px);
  overflow:auto;
  padding-right:4px;
}

.state-link{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:14px;
  min-height:52px;
  padding:0 12px;
  border-radius:13px;
  color:var(--locations-dark);
  text-decoration:none;
  transition:.16s ease;
}

.state-link:hover,
.state-link.is-active{
  color:var(--locations-purple);
  background:#f5f3ff;
  transform:translateX(2px);
}

.state-link-main{
  display:flex;
  align-items:center;
  gap:11px;
}

.state-code{
  width:34px;
  height:34px;
  display:grid;
  place-items:center;
  border:1px solid var(--locations-border);
  border-radius:10px;
  background:#f8fafc;
  color:#475569;
  font-size:11px;
  font-weight:950;
}

.state-name{
  font-size:14px;
  font-weight:800;
}

.state-arrow{
  color:#94a3b8;
}

.locations-map-wrap{
  position:relative;
  min-width:0;
  min-height:calc(100vh - 72px);
  background:var(--locations-map);
  overflow:hidden;
}

#locations-map{
  position:absolute;
  inset:0;
  width:100%;
  height:100%;
  background:var(--locations-map);
}

.locations-map-note{
  position:absolute;
  left:22px;
  bottom:22px;
  z-index:450;
  max-width:360px;
  padding:14px 16px;
  border:1px solid rgba(219,229,241,.96);
  border-radius:16px;
  background:rgba(255,255,255,.95);
  box-shadow:0 18px 44px rgba(15,23,42,.1);
}

.locations-map-note strong{
  display:block;
  color:var(--locations-dark);
  font-size:14px;
}

.locations-map-note span{
  display:block;
  margin-top:4px;
  color:var(--locations-muted);
  font-size:12px;
  line-height:1.45;
}

.leaflet-container{
  font:inherit;
}

.leaflet-tile{
  max-width:none !important;
  max-height:none !important;
}

.leaflet-control-zoom{
  border:1px solid var(--locations-border) !important;
  border-radius:12px !important;
  overflow:hidden;
  box-shadow:0 10px 24px rgba(15,23,42,.08) !important;
}

.state-tooltip{
  padding:7px 9px;
  border:0;
  border-radius:10px;
  background:#071225;
  color:#fff;
  box-shadow:0 10px 22px rgba(15,23,42,.18);
  font-size:12px;
  font-weight:800;
}

@media(max-width:860px){
  .locations-shell{
    display:block;
    min-height:auto;
    background:#fff;
  }

  .locations-sidebar{
    width:100%;
    min-height:auto;
    padding:22px 16px 40px;
    border-right:0;
    border-bottom:0;
    box-shadow:none;
  }

  .locations-list{
    display:block;
    max-height:none;
    overflow:visible;
    padding:0;
  }

  .state-link{
    width:100%;
    min-width:0;
    min-height:54px;
    padding:0 12px;
    border-bottom:1px solid #eef2f7;
    border-radius:0;
    background:#fff;
  }

  .state-link:hover,
  .state-link.is-active{
    transform:none;
    border-radius:12px;
  }

  .state-code{
    width:34px;
    height:34px;
    border-radius:10px;
  }

  .state-arrow{
    display:block;
  }

  .locations-map-wrap{
    display:none !important;
  }
}

/* Ensure sidebar links remain above the map and are always clickable. */
.locations-sidebar,
.locations-list,
.state-link{
  pointer-events:auto;
}

.state-link{
  position:relative;
  z-index:2;
  cursor:pointer;
}

/* Four-column Microgifter footer */
.locations-footer{
  position:relative;
  z-index:20;
  width:100%;
  padding:84px 0 34px;
  border-top:1px solid #e2e8f0;
  background:#fff;
  color:#071225;
}

.locations-footer,
.locations-footer *{
  box-sizing:border-box;
}

.locations-footer__inner{
  width:min(1180px,92%);
  margin:0 auto;
}

.locations-footer__grid{
  display:grid;
  grid-template-columns:1.45fr repeat(3,minmax(0,1fr));
  gap:54px;
  align-items:start;
}

.locations-footer__brand{
  max-width:330px;
}

.locations-footer__logo{
  display:inline-flex;
  align-items:center;
  color:#071225;
  text-decoration:none;
  font-size:24px;
  font-weight:950;
  letter-spacing:-.045em;
}

.locations-footer__brand p{
  margin:18px 0 0;
  color:#64748b;
  font-size:14px;
  line-height:1.6;
}

.locations-footer__socials{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin-top:24px;
}

.locations-footer__socials a{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  border:1px solid #dbe5f1;
  border-radius:12px;
  background:#f8fafc;
  color:#475569;
  text-decoration:none;
  font-size:13px;
  font-weight:950;
}

.locations-footer__column h3{
  margin:7px 0 18px;
  color:#071225;
  font-size:14px;
  font-weight:950;
  letter-spacing:.065em;
  text-transform:uppercase;
}

.locations-footer__column nav{
  display:grid;
  gap:13px;
}

.locations-footer__column a{
  color:#64748b;
  text-decoration:none;
  font-size:14px;
  font-weight:720;
}

.locations-footer__bottom{
  display:flex;
  justify-content:space-between;
  gap:24px;
  margin-top:66px;
  padding-top:24px;
  border-top:1px solid #e2e8f0;
  color:#94a3b8;
  font-size:12px;
}

.locations-footer__bottom-links{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
}

.locations-footer__bottom a{
  color:#64748b;
  text-decoration:none;
}

@media(max-width:900px){
  .locations-footer__grid{
    grid-template-columns:1fr 1fr;
  }

  .locations-footer__brand{
    grid-column:1/-1;
  }
}

@media(max-width:680px){
  .locations-footer{
    padding-top:64px;
  }

  .locations-footer__grid{
    grid-template-columns:1fr;
    gap:34px;
  }

  .locations-footer__brand{
    grid-column:auto;
  }

  .locations-footer__bottom{
    display:grid;
  }
}

</style>

<section class="locations-shell">
  <aside class="locations-sidebar">
    <div class="locations-sidebar-head">
      <div>
        <h1>Locations</h1>
        <p>Select a state to view local Microgifter merchants and offers.</p>
      </div>
      <span class="locations-count"><?= count($states) ?></span>
    </div>

    <div class="locations-search">
      <input
        type="search"
        placeholder="Search by state or abbreviation"
        aria-label="Search locations"
        data-location-search
      >
    </div>

    <nav class="locations-list" aria-label="State locations" data-state-list>
      <?php foreach ($states as $code => $name): ?>
        <a
          class="state-link"
          href="location-results.php?state=<?= rawurlencode($code) ?>"
          data-state-link
          data-state-code="<?= mg_e($code) ?>"
          data-state-name="<?= mg_e(strtolower($name)) ?>"
        >
          <span class="state-link-main">
            <span class="state-code"><?= mg_e($code) ?></span>
            <span class="state-name"><?= mg_e($name) ?></span>
          </span>
          <span class="state-arrow">→</span>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <div class="locations-map-wrap">
    <div id="locations-map" aria-label="Interactive United States map"></div>

    <div class="locations-map-note">
      <strong>Choose a state</strong>
      <span>Click any state on the map or select one from the sidebar to open its results page.</span>
    </div>
  </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
  const stateNames = <?= json_encode($states, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const nameToCode = Object.fromEntries(
    Object.entries(stateNames).map(([code, name]) => [name.toLowerCase(), code])
  );

  const resultsUrl = (code) =>
    `location-results.php?state=${encodeURIComponent(code)}`;

  const openState = (code) => {
    if (!code || !stateNames[code]) {
      return;
    }

    window.location.assign(resultsUrl(code));
  };

  const links = Array.from(document.querySelectorAll('[data-state-link]'));

  const search = document.querySelector('[data-location-search]');

  if (search) {
    search.addEventListener('input', () => {
      const query = search.value.trim().toLowerCase();

      links.forEach((link) => {
        link.hidden = !(
          !query ||
          link.dataset.stateName.includes(query) ||
          link.dataset.stateCode.toLowerCase().includes(query)
        );
      });
    });
  }

  const mapElement = document.getElementById('locations-map');

  if (!mapElement || window.matchMedia('(max-width: 860px)').matches || typeof L === 'undefined') {
    return;
  }

  const continentalUsBounds = L.latLngBounds(
    [24.3, -125.0],
    [49.7, -66.5]
  );

  const map = L.map(mapElement, {
    zoomControl:true,
    minZoom:4,
    maxZoom:8,
    scrollWheelZoom:true,
    maxBounds:continentalUsBounds.pad(0.08),
    maxBoundsViscosity:0.85
  });

  map.fitBounds(continentalUsBounds, {
    padding:[18,18]
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom:19,
    attribution:'&copy; OpenStreetMap contributors'
  }).addTo(map);

  const defaultStyle = {
    color:'#c9d4e2',
    weight:1.2,
    fillColor:'#e5ebf3',
    fillOpacity:.88
  };

  const hoverStyle = {
    color:'#7c3aed',
    weight:1.8,
    fillColor:'#ddd6fe',
    fillOpacity:.96
  };

  let geoLayer;

  fetch('https://cdn.jsdelivr.net/gh/PublicaMundi/MappingAPI@master/data/geojson/us-states.json')
    .then((response) => {
      if (!response.ok) {
        throw new Error('State boundaries unavailable');
      }

      return response.json();
    })
    .then((geojson) => {
      geoLayer = L.geoJSON(geojson, {
        style:defaultStyle,
        onEachFeature:(feature, layer) => {
          const name = String(
            feature?.properties?.name ||
            feature?.properties?.NAME ||
            ''
          ).trim();

          const code = nameToCode[name.toLowerCase()];

          layer.bindTooltip(name, {
            sticky:true,
            direction:'top',
            className:'state-tooltip'
          });

          layer.on({
            mouseover:() => {
              layer.setStyle(hoverStyle);
              layer.bringToFront();
            },
            mouseout:() => {
              if (geoLayer) {
                geoLayer.resetStyle(layer);
              }
            },
            click:(event) => {
              if (event?.originalEvent) {
                L.DomEvent.stop(event.originalEvent);
              }

              openState(code);
            }
          });
        }
      }).addTo(map);

      map.fitBounds(continentalUsBounds, {
        padding:[18,18]
      });

      requestAnimationFrame(() => map.invalidateSize());
    })
    .catch(() => {
      const message = document.querySelector('.locations-map-note span');

      if (message) {
        message.textContent =
          'The map could not load, but every state remains available from the sidebar.';
      }
    });

  window.addEventListener('load', () => map.invalidateSize());
  window.addEventListener('resize', () => map.invalidateSize());
})();
</script>

<footer class="locations-footer">
  <div class="locations-footer__inner">
    <div class="locations-footer__grid">
      <div class="locations-footer__brand">
        <a class="locations-footer__logo" href="/">Microgifter</a>

        <p>
          Pre-purchase gifts, local rewards, and simple digital redemption
          for businesses, customers, teams, and communities.
        </p>

        <div class="locations-footer__socials" aria-label="Social links">
          <a href="https://instagram.com/microgifter" aria-label="Instagram">ig</a>
          <a href="https://linkedin.com/microgifter" aria-label="LinkedIn">in</a>
          <a href="mailto:hello@microgifter.com" aria-label="Email">✉</a>
        </div>
      </div>

      <div class="locations-footer__column">
        <h3>Product</h3>
        <nav aria-label="Product links">
          <a href="/retail.php">Retail Subscriptions</a>
          <a href="/corporate.php">Corporate Gifting</a>
          <a href="/discover.php">Discover</a>
        </nav>
      </div>

      <div class="locations-footer__column">
        <h3>Businesses</h3>
        <nav aria-label="Business links">
          <a href="/#simple">How It Works</a>
          <a href="/learn-more.php">Book A Demo</a>
          <a href="/signup.php">Create Account</a>
        </nav>
      </div>

      <div class="locations-footer__column">
        <h3>Company</h3>
        <nav aria-label="Company links">
          <a href="/about.php">About</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
          <a href="/support.php">Support</a>
        </nav>
      </div>
    </div>

    <div class="locations-footer__bottom">
      <span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span>

      <div class="locations-footer__bottom-links">
        <a href="/privacy.php">Privacy</a>
        <a href="/terms.php">Terms</a>
        <a href="/signin.php">Sign In</a>
      </div>
    </div>
  </div>
</footer>

<?php require __DIR__ . '/includes/footer.php'; ?>
