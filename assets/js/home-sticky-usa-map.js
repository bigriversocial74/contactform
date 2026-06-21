(() => {
  'use strict';

  const desktopMedia = window.matchMedia('(min-width: 901px)');
  const reducedMotionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');
  const stateNames = {
    AL:'Alabama', AK:'Alaska', AZ:'Arizona', AR:'Arkansas', CA:'California', CO:'Colorado', CT:'Connecticut', DE:'Delaware',
    FL:'Florida', GA:'Georgia', HI:'Hawaii', ID:'Idaho', IL:'Illinois', IN:'Indiana', IA:'Iowa', KS:'Kansas', KY:'Kentucky', LA:'Louisiana',
    ME:'Maine', MD:'Maryland', MA:'Massachusetts', MI:'Michigan', MN:'Minnesota', MS:'Mississippi', MO:'Missouri', MT:'Montana', NE:'Nebraska',
    NV:'Nevada', NH:'New Hampshire', NJ:'New Jersey', NM:'New Mexico', NY:'New York', NC:'North Carolina', ND:'North Dakota', OH:'Ohio',
    OK:'Oklahoma', OR:'Oregon', PA:'Pennsylvania', RI:'Rhode Island', SC:'South Carolina', SD:'South Dakota', TN:'Tennessee', TX:'Texas',
    UT:'Utah', VT:'Vermont', VA:'Virginia', WA:'Washington', WV:'West Virginia', WI:'Wisconsin', WY:'Wyoming'
  };
  const nameToCode = Object.fromEntries(Object.entries(stateNames).map(([code, name]) => [name.toLowerCase(), code]));
  const leafletCssHref = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  const leafletJsSrc = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
  const statesGeoJsonUrl = 'https://cdn.jsdelivr.net/gh/PublicaMundi/MappingAPI@master/data/geojson/us-states.json';

  const css = `
@media (min-width:901px){
  body.mg-home-sticky-map-enabled .mg-index-hero{display:block;min-height:430svh;align-items:initial;overflow:visible;border-bottom:0;background-attachment:fixed;isolation:isolate;}
  body.mg-home-sticky-map-enabled .mg-index-hero::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:36vh;z-index:0;pointer-events:none;background:linear-gradient(180deg,rgba(238,242,247,0),rgba(238,242,247,.96));}
  body.mg-home-sticky-map-enabled .mg-index-hero-inner{position:sticky;top:64px;height:calc(100svh - 64px);min-height:calc(100svh - 64px);display:grid;align-items:center;overflow:hidden;z-index:2;transform:none!important;}
  body.mg-home-sticky-map-enabled .mg-index-hero-copy,
  body.mg-home-sticky-map-enabled .mg-index-hero-visual{will-change:transform,opacity,filter;transition:filter .12s linear;}
  body.mg-home-sticky-map-enabled .mg-index-hero-copy{transform:translate3d(var(--mg-home-copy-x,0),var(--mg-home-copy-y,0),0) scale(var(--mg-home-panel-scale,1));opacity:var(--mg-home-panel-opacity,1);filter:blur(var(--mg-home-panel-blur,0px));}
  body.mg-home-sticky-map-enabled .mg-index-hero-visual{transform:translate3d(var(--mg-home-visual-x,0),var(--mg-home-visual-y,0),0) scale(var(--mg-home-panel-scale,1));opacity:var(--mg-home-panel-opacity,1);filter:blur(var(--mg-home-panel-blur,0px));}
  body.mg-home-sticky-map-enabled .mg-index-hero.is-map-clickable .mg-index-hero-copy,
  body.mg-home-sticky-map-enabled .mg-index-hero.is-map-clickable .mg-index-hero-visual{pointer-events:none;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-stage{position:absolute;inset:0;z-index:5;display:grid;place-items:center;pointer-events:none;opacity:var(--mg-home-map,0);transform:translate3d(0,var(--mg-home-map-y,32px),0) scale(var(--mg-home-map-scale,.94));will-change:opacity,transform;}
  body.mg-home-sticky-map-enabled .mg-index-hero.is-map-clickable .mg-home-usa-map-stage{pointer-events:auto;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-shell{width:min(1040px,94%);padding:24px;border:1px solid rgba(124,58,237,.16);border-radius:32px;background:rgba(255,255,255,.9);box-shadow:0 34px 100px rgba(15,23,42,.16);backdrop-filter:blur(18px);}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-header{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:18px;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-kicker{display:inline-flex;align-items:center;gap:8px;min-height:30px;padding:0 12px;border:1px solid #d8e4f4;border-radius:999px;background:#fff;color:#7c3aed;font-size:12px;font-weight:950;letter-spacing:.06em;text-transform:uppercase;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-title{margin:10px 0 0;color:#071225;font-size:clamp(34px,3.7vw,58px);line-height:.98;letter-spacing:-.065em;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-note{max-width:300px;margin:0;color:#64748b;font-size:13px;line-height:1.45;font-weight:750;}
  body.mg-home-sticky-map-enabled .mg-home-leaflet-frame{position:relative;height:min(56vh,520px);min-height:390px;overflow:hidden;border:1px solid #e2e8f0;border-radius:24px;background:#eef3f8;}
  body.mg-home-sticky-map-enabled .mg-home-leaflet-map{position:absolute;inset:0;width:100%;height:100%;background:#eef3f8;}
  body.mg-home-sticky-map-enabled .mg-home-leaflet-frame .leaflet-container{font:inherit;background:#eef3f8;}
  body.mg-home-sticky-map-enabled .mg-home-leaflet-frame .leaflet-control-attribution{font-size:10px;}
  body.mg-home-sticky-map-enabled .mg-home-state-tooltip{padding:7px 9px;border:0;border-radius:10px;background:#071225;color:#fff;box-shadow:0 10px 22px rgba(15,23,42,.18);font-size:12px;font-weight:800;}
  body.mg-home-sticky-map-enabled .mg-index-section,
  body.mg-home-sticky-map-enabled .mg-account-cta-section,
  body.mg-home-sticky-map-enabled .mg-home-footer{position:relative;z-index:3;}
}
@media (max-width:900px),(prefers-reduced-motion:reduce){.mg-home-usa-map-stage{display:none!important;}}
`;

  let hero = null;
  let enabled = false;
  let ticking = false;
  let mapReadyRequested = false;
  let mapInstance = null;
  let geoLayer = null;

  const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
  const smooth = (start, end, value) => {
    const x = clamp((value - start) / (end - start), 0, 1);
    return x * x * (3 - (2 * x));
  };

  const ensureStyle = () => {
    if (document.getElementById('mg-home-sticky-usa-map-style')) return;
    const style = document.createElement('style');
    style.id = 'mg-home-sticky-usa-map-style';
    style.textContent = css;
    document.head.appendChild(style);
  };

  const ensureLeafletCss = () => {
    if (document.querySelector(`link[href="${leafletCssHref}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = leafletCssHref;
    document.head.appendChild(link);
  };

  const ensureLeafletJs = () => new Promise((resolve, reject) => {
    if (typeof window.L !== 'undefined') {
      resolve(window.L);
      return;
    }
    const existing = document.querySelector(`script[src="${leafletJsSrc}"]`);
    if (existing) {
      existing.addEventListener('load', () => resolve(window.L), { once:true });
      existing.addEventListener('error', reject, { once:true });
      return;
    }
    const script = document.createElement('script');
    script.src = leafletJsSrc;
    script.async = true;
    script.onload = () => resolve(window.L);
    script.onerror = reject;
    document.head.appendChild(script);
  });

  const resultsUrl = (code) => `/location-results.php?state=${encodeURIComponent(code)}`;
  const openState = (code) => {
    if (code && stateNames[code]) window.location.assign(resultsUrl(code));
  };

  const buildMapStage = () => {
    const stage = document.createElement('div');
    stage.className = 'mg-home-usa-map-stage';
    stage.setAttribute('aria-label', 'Choose a state to explore local gifts');
    stage.innerHTML = `<div class="mg-home-usa-map-shell"><div class="mg-home-usa-map-header"><div><span class="mg-home-usa-map-kicker">Choose your market</span><h2 class="mg-home-usa-map-title">Explore local gifts by state.</h2></div><p class="mg-home-usa-map-note" data-home-map-note>Same interactive state map as Locations. Click a state to open its results page, or keep scrolling to continue.</p></div><div class="mg-home-leaflet-frame"><div class="mg-home-leaflet-map" id="mgHomeLeafletMap" aria-label="Interactive United States map"></div></div></div>`;
    return stage;
  };

  const initLeafletMap = () => {
    if (mapReadyRequested || mapInstance || !enabled) return;
    mapReadyRequested = true;
    ensureLeafletCss();
    ensureLeafletJs()
      .then((L) => {
        const mapElement = document.getElementById('mgHomeLeafletMap');
        if (!mapElement || mapInstance || !enabled) return;

        const continentalUsBounds = L.latLngBounds([24.3, -125.0], [49.7, -66.5]);
        mapInstance = L.map(mapElement, {
          zoomControl:false,
          dragging:true,
          minZoom:4,
          maxZoom:8,
          scrollWheelZoom:false,
          doubleClickZoom:false,
          boxZoom:false,
          keyboard:true,
          maxBounds:continentalUsBounds.pad(0.08),
          maxBoundsViscosity:0.85
        });

        mapInstance.fitBounds(continentalUsBounds, { padding:[18,18] });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom:19,
          attribution:'&copy; OpenStreetMap contributors'
        }).addTo(mapInstance);

        const defaultStyle = { color:'#c9d4e2', weight:1.2, fillColor:'#e5ebf3', fillOpacity:.88 };
        const hoverStyle = { color:'#7c3aed', weight:1.8, fillColor:'#ddd6fe', fillOpacity:.96 };

        return fetch(statesGeoJsonUrl)
          .then((response) => {
            if (!response.ok) throw new Error('State boundaries unavailable');
            return response.json();
          })
          .then((geojson) => {
            geoLayer = L.geoJSON(geojson, {
              style:defaultStyle,
              onEachFeature:(feature, layer) => {
                const name = String(feature?.properties?.name || feature?.properties?.NAME || '').trim();
                const code = nameToCode[name.toLowerCase()];

                layer.bindTooltip(name, { sticky:true, direction:'top', className:'mg-home-state-tooltip' });
                layer.on({
                  mouseover:() => {
                    layer.setStyle(hoverStyle);
                    layer.bringToFront();
                  },
                  mouseout:() => {
                    if (geoLayer) geoLayer.resetStyle(layer);
                  },
                  click:(event) => {
                    if (event?.originalEvent) L.DomEvent.stop(event.originalEvent);
                    openState(code);
                  }
                });
              }
            }).addTo(mapInstance);

            mapInstance.fitBounds(continentalUsBounds, { padding:[18,18] });
            requestAnimationFrame(() => mapInstance.invalidateSize());
          });
      })
      .catch(() => {
        const note = document.querySelector('[data-home-map-note]');
        if (note) note.textContent = 'The state map could not load, but the Locations page remains available.';
      });
  };

  const update = () => {
    ticking = false;
    if (!enabled || !hero) return;

    const rect = hero.getBoundingClientRect();
    const top = rect.top + window.scrollY;
    const range = Math.max(hero.offsetHeight - window.innerHeight, 1);
    const progress = clamp((window.scrollY - top) / range, 0, 1);
    const split = smooth(0.08, 0.34, progress);
    const map = smooth(0.30, 0.58, progress);
    const hold = smooth(0.58, 0.86, progress);
    const panelOpacity = Math.max(0, 1 - (split * 1.1));

    hero.style.setProperty('--mg-home-copy-x', `${(split * -82).toFixed(2)}vw`);
    hero.style.setProperty('--mg-home-copy-y', `${(split * -18).toFixed(2)}px`);
    hero.style.setProperty('--mg-home-visual-x', `${(split * 82).toFixed(2)}vw`);
    hero.style.setProperty('--mg-home-visual-y', `${(split * 18).toFixed(2)}px`);
    hero.style.setProperty('--mg-home-panel-scale', (1 - (split * 0.04)).toFixed(3));
    hero.style.setProperty('--mg-home-panel-opacity', panelOpacity.toFixed(3));
    hero.style.setProperty('--mg-home-panel-blur', `${(split * 8).toFixed(2)}px`);
    hero.style.setProperty('--mg-home-map', map.toFixed(3));
    hero.style.setProperty('--mg-home-map-y', `${((1 - map) * 34 - hold * 8).toFixed(2)}px`);
    hero.style.setProperty('--mg-home-map-scale', (0.935 + (map * 0.065)).toFixed(3));
    hero.classList.toggle('is-map-clickable', map > 0.72);

    if (map > 0.08) {
      initLeafletMap();
      if (mapInstance) requestAnimationFrame(() => mapInstance.invalidateSize());
    }
  };

  const requestUpdate = () => {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(update);
  };

  const enable = () => {
    if (enabled || !desktopMedia.matches || reducedMotionMedia.matches) return;
    hero = document.querySelector('.mg-index-hero');
    const inner = hero ? hero.querySelector('.mg-index-hero-inner') : null;
    if (!hero || !inner) return;

    ensureStyle();
    if (!hero.querySelector('.mg-home-usa-map-stage')) inner.appendChild(buildMapStage());

    enabled = true;
    document.body.classList.add('mg-home-sticky-map-enabled');
    window.addEventListener('scroll', requestUpdate, { passive:true });
    window.addEventListener('resize', requestUpdate);
    requestUpdate();
  };

  const disable = () => {
    if (!enabled) return;
    enabled = false;
    document.body.classList.remove('mg-home-sticky-map-enabled');
    if (hero) {
      hero.classList.remove('is-map-clickable');
      ['--mg-home-copy-x','--mg-home-copy-y','--mg-home-visual-x','--mg-home-visual-y','--mg-home-panel-scale','--mg-home-panel-opacity','--mg-home-panel-blur','--mg-home-map','--mg-home-map-y','--mg-home-map-scale'].forEach((prop) => hero.style.removeProperty(prop));
    }
    if (mapInstance) {
      mapInstance.remove();
      mapInstance = null;
      geoLayer = null;
      mapReadyRequested = false;
    }
    window.removeEventListener('scroll', requestUpdate);
    window.removeEventListener('resize', requestUpdate);
  };

  const sync = () => desktopMedia.matches && !reducedMotionMedia.matches ? enable() : disable();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync, { once:true });
  else sync();

  if (typeof desktopMedia.addEventListener === 'function') {
    desktopMedia.addEventListener('change', sync);
    reducedMotionMedia.addEventListener('change', sync);
  } else if (typeof desktopMedia.addListener === 'function') {
    desktopMedia.addListener(sync);
    reducedMotionMedia.addListener(sync);
  }
})();
