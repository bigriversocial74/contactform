(() => {
  'use strict';

  const DESKTOP_QUERY = '(min-width: 901px)';
  const desktopMedia = window.matchMedia(DESKTOP_QUERY);
  const reducedMotionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');

  const states = [
    ['WA', 'Washington', 0, 1], ['MT', 'Montana', 2, 1], ['ND', 'North Dakota', 4, 1], ['MN', 'Minnesota', 5, 1], ['WI', 'Wisconsin', 6, 1], ['MI', 'Michigan', 7, 1], ['NY', 'New York', 9, 1],
    ['VT', 'Vermont', 10, 0], ['NH', 'New Hampshire', 11, 0], ['ME', 'Maine', 12, 0],
    ['OR', 'Oregon', 0, 2], ['ID', 'Idaho', 1, 2], ['WY', 'Wyoming', 2, 2], ['SD', 'South Dakota', 4, 2], ['IA', 'Iowa', 5, 2], ['IL', 'Illinois', 6, 2], ['IN', 'Indiana', 7, 2], ['OH', 'Ohio', 8, 2], ['PA', 'Pennsylvania', 9, 2], ['NJ', 'New Jersey', 10, 2], ['CT', 'Connecticut', 11, 2], ['MA', 'Massachusetts', 12, 2], ['RI', 'Rhode Island', 13, 2],
    ['CA', 'California', 0, 3], ['NV', 'Nevada', 1, 3], ['UT', 'Utah', 2, 3], ['CO', 'Colorado', 3, 3], ['NE', 'Nebraska', 4, 3], ['MO', 'Missouri', 5, 3], ['KY', 'Kentucky', 6, 3], ['WV', 'West Virginia', 7, 3], ['VA', 'Virginia', 8, 3], ['MD', 'Maryland', 9, 3], ['DE', 'Delaware', 10, 3],
    ['AZ', 'Arizona', 1, 4], ['NM', 'New Mexico', 2, 4], ['KS', 'Kansas', 4, 4], ['OK', 'Oklahoma', 5, 4], ['AR', 'Arkansas', 6, 4], ['TN', 'Tennessee', 7, 4], ['NC', 'North Carolina', 8, 4], ['SC', 'South Carolina', 9, 4],
    ['TX', 'Texas', 4, 5], ['LA', 'Louisiana', 6, 5], ['MS', 'Mississippi', 7, 5], ['AL', 'Alabama', 8, 5], ['GA', 'Georgia', 9, 5], ['FL', 'Florida', 10, 6],
    ['AK', 'Alaska', 0, 6], ['HI', 'Hawaii', 1, 6]
  ];

  const css = `
@media (min-width:901px){
  body.mg-home-sticky-map-enabled .mg-main{position:relative;background:transparent;}
  body.mg-home-sticky-map-enabled .mg-index-hero{min-height:260svh;align-items:flex-start;border-bottom:0;isolation:isolate;z-index:0;background-attachment:fixed;}
  body.mg-home-sticky-map-enabled .mg-index-hero::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:28vh;z-index:0;pointer-events:none;background:linear-gradient(180deg,rgba(238,242,247,0),rgba(238,242,247,.92));}
  body.mg-home-sticky-map-enabled .mg-index-hero-inner{position:sticky;top:64px;min-height:calc(100svh - 64px);display:grid;z-index:2;transition:none;transform:translate3d(0,calc(var(--mg-home-parallax,0px) * -0.18),0);}
  body.mg-home-sticky-map-enabled .mg-index-hero-copy,body.mg-home-sticky-map-enabled .mg-index-hero-visual{will-change:transform,opacity,filter;transition:filter .12s linear;}
  body.mg-home-sticky-map-enabled .mg-index-hero-copy{transform:translate3d(calc(var(--mg-home-split,0) * -74vw),calc(var(--mg-home-split,0) * -18px),0) scale(calc(1 - (var(--mg-home-split,0) * .035)));opacity:calc(1 - (var(--mg-home-split,0) * 1.08));filter:blur(calc(var(--mg-home-split,0) * 7px));}
  body.mg-home-sticky-map-enabled .mg-index-hero-visual{transform:translate3d(calc(var(--mg-home-split,0) * 74vw),calc(var(--mg-home-split,0) * 18px),0) scale(calc(1 - (var(--mg-home-split,0) * .035)));opacity:calc(1 - (var(--mg-home-split,0) * 1.08));filter:blur(calc(var(--mg-home-split,0) * 7px));}
  body.mg-home-sticky-map-enabled .mg-index-hero.is-map-clickable .mg-index-hero-copy,body.mg-home-sticky-map-enabled .mg-index-hero.is-map-clickable .mg-index-hero-visual{pointer-events:none;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-stage{position:absolute;inset:0;z-index:5;display:grid;place-items:center;pointer-events:none;opacity:var(--mg-home-map,0);transform:translate3d(0,calc((1 - var(--mg-home-map,0)) * 32px),0) scale(calc(.94 + (var(--mg-home-map,0) * .06)));will-change:opacity,transform;}
  body.mg-home-sticky-map-enabled .mg-index-hero.is-map-clickable .mg-home-usa-map-stage{pointer-events:auto;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-shell{width:min(980px,94%);padding:24px;border:1px solid rgba(124,58,237,.16);border-radius:32px;background:rgba(255,255,255,.86);box-shadow:0 34px 100px rgba(15,23,42,.15);backdrop-filter:blur(18px);}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-header{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:18px;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-kicker{display:inline-flex;align-items:center;gap:8px;min-height:30px;padding:0 12px;border:1px solid #d8e4f4;border-radius:999px;background:#fff;color:#7c3aed;font-size:12px;font-weight:950;letter-spacing:.06em;text-transform:uppercase;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-title{margin:10px 0 0;color:#071225;font-size:clamp(34px,3.7vw,58px);line-height:.98;letter-spacing:-.065em;}
  body.mg-home-sticky-map-enabled .mg-home-usa-map-note{max-width:285px;margin:0;color:#64748b;font-size:13px;line-height:1.45;font-weight:750;}
  body.mg-home-sticky-map-enabled .mg-home-usa-svg-wrap{position:relative;overflow:hidden;border:1px solid #e2e8f0;border-radius:24px;background:radial-gradient(circle at 50% 0%,rgba(237,233,254,.72),transparent 38%),linear-gradient(180deg,#fbfdff,#f8fafc);}
  body.mg-home-sticky-map-enabled .mg-home-usa-svg{width:100%;height:auto;display:block;}
  body.mg-home-sticky-map-enabled .mg-state-link{outline:none;}
  body.mg-home-sticky-map-enabled .mg-state-shape{fill:rgba(255,255,255,.92);stroke:#cbd5e1;stroke-width:1.4;transition:fill .16s ease,stroke .16s ease,filter .16s ease,transform .16s ease;}
  body.mg-home-sticky-map-enabled .mg-state-label{fill:#334155;font-size:13px;font-weight:950;text-anchor:middle;dominant-baseline:middle;pointer-events:none;transition:fill .16s ease;}
  body.mg-home-sticky-map-enabled .mg-state-link:hover .mg-state-shape,body.mg-home-sticky-map-enabled .mg-state-link:focus .mg-state-shape{fill:#7c3aed;stroke:#6d5dfc;filter:drop-shadow(0 10px 13px rgba(124,58,237,.22));}
  body.mg-home-sticky-map-enabled .mg-state-link:hover .mg-state-label,body.mg-home-sticky-map-enabled .mg-state-link:focus .mg-state-label{fill:#fff;}
  body.mg-home-sticky-map-enabled .mg-index-section,body.mg-home-sticky-map-enabled .mg-account-cta-section,body.mg-home-sticky-map-enabled .mg-home-footer{position:relative;z-index:3;}
  body.mg-home-sticky-map-enabled .mg-index-section:first-of-type{box-shadow:0 -34px 90px rgba(15,23,42,.08);}
}
@media (max-width:900px), (prefers-reduced-motion:reduce){.mg-home-usa-map-stage{display:none!important;}}
`;

  let hero = null;
  let stage = null;
  let ticking = false;
  let enabled = false;

  const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
  const smooth = (edge0, edge1, value) => {
    const x = clamp((value - edge0) / (edge1 - edge0), 0, 1);
    return x * x * (3 - (2 * x));
  };

  const ensureStyle = () => {
    if (document.getElementById('mg-home-sticky-usa-map-style')) {
      return;
    }
    const style = document.createElement('style');
    style.id = 'mg-home-sticky-usa-map-style';
    style.textContent = css;
    document.head.appendChild(style);
  };

  const stateHref = (abbr) => `/discover.php?state=${encodeURIComponent(abbr.toLowerCase())}`;

  const renderStateLinks = () => {
    const cellW = 62;
    const cellH = 42;
    const gapX = 74;
    const gapY = 60;
    const startX = 26;
    const startY = 28;

    return states.map(([abbr, name, col, row]) => {
      const x = startX + (col * gapX);
      const y = startY + (row * gapY);
      const cx = x + (cellW / 2);
      const cy = y + (cellH / 2);
      return `<a class="mg-state-link" href="${stateHref(abbr)}" aria-label="Explore ${name}" data-state="${abbr}">
        <title>${name}</title>
        <rect class="mg-state-shape" x="${x}" y="${y}" width="${cellW}" height="${cellH}" rx="12"></rect>
        <text class="mg-state-label" x="${cx}" y="${cy}">${abbr}</text>
      </a>`;
    }).join('');
  };

  const buildMapStage = () => {
    const wrapper = document.createElement('div');
    wrapper.className = 'mg-home-usa-map-stage';
    wrapper.setAttribute('aria-label', 'Choose a state to explore local gifts');
    wrapper.innerHTML = `
      <div class="mg-home-usa-map-shell">
        <div class="mg-home-usa-map-header">
          <div>
            <span class="mg-home-usa-map-kicker">Choose your market</span>
            <h2 class="mg-home-usa-map-title">Explore local gifts by state.</h2>
          </div>
          <p class="mg-home-usa-map-note">Each state is a clickable SVG link. Scroll past this area to continue the landing page.</p>
        </div>
        <div class="mg-home-usa-svg-wrap">
          <svg class="mg-home-usa-svg" viewBox="0 0 1080 470" role="img" aria-labelledby="mg-usa-map-title mg-usa-map-desc">
            <title id="mg-usa-map-title">United States state selector</title>
            <desc id="mg-usa-map-desc">A clickable SVG state selector for finding local gifts by state.</desc>
            <g>${renderStateLinks()}</g>
          </svg>
        </div>
      </div>`;
    return wrapper;
  };

  const update = () => {
    ticking = false;

    if (!enabled || !hero) {
      return;
    }

    const rect = hero.getBoundingClientRect();
    const top = rect.top + window.scrollY;
    const range = Math.max(hero.offsetHeight - window.innerHeight, 1);
    const progress = clamp((window.scrollY - top) / range, 0, 1);
    const split = smooth(0.08, 0.48, progress);
    const map = smooth(0.38, 0.72, progress);

    hero.style.setProperty('--mg-home-scroll-progress', progress.toFixed(3));
    hero.style.setProperty('--mg-home-split', split.toFixed(3));
    hero.style.setProperty('--mg-home-map', map.toFixed(3));
    hero.style.setProperty('--mg-home-parallax', `${(progress * 38).toFixed(2)}px`);
    hero.classList.toggle('is-map-clickable', map > 0.78);
  };

  const requestUpdate = () => {
    if (ticking) {
      return;
    }
    ticking = true;
    window.requestAnimationFrame(update);
  };

  const enable = () => {
    if (enabled || !desktopMedia.matches || reducedMotionMedia.matches) {
      return;
    }

    hero = document.querySelector('.mg-index-hero');
    const inner = hero ? hero.querySelector('.mg-index-hero-inner') : null;

    if (!hero || !inner) {
      return;
    }

    ensureStyle();

    stage = hero.querySelector('.mg-home-usa-map-stage');
    if (!stage) {
      stage = buildMapStage();
      inner.appendChild(stage);
    }

    enabled = true;
    document.body.classList.add('mg-home-sticky-map-enabled');
    hero.style.setProperty('--mg-home-split', '0');
    hero.style.setProperty('--mg-home-map', '0');
    hero.style.setProperty('--mg-home-parallax', '0px');

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate);
    requestUpdate();
  };

  const disable = () => {
    if (!enabled) {
      return;
    }

    enabled = false;
    document.body.classList.remove('mg-home-sticky-map-enabled');

    if (hero) {
      hero.classList.remove('is-map-clickable');
      hero.style.removeProperty('--mg-home-scroll-progress');
      hero.style.removeProperty('--mg-home-split');
      hero.style.removeProperty('--mg-home-map');
      hero.style.removeProperty('--mg-home-parallax');
    }

    window.removeEventListener('scroll', requestUpdate);
    window.removeEventListener('resize', requestUpdate);
  };

  const sync = () => {
    if (desktopMedia.matches && !reducedMotionMedia.matches) {
      enable();
    } else {
      disable();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sync, { once: true });
  } else {
    sync();
  }

  if (typeof desktopMedia.addEventListener === 'function') {
    desktopMedia.addEventListener('change', sync);
    reducedMotionMedia.addEventListener('change', sync);
  } else if (typeof desktopMedia.addListener === 'function') {
    desktopMedia.addListener(sync);
    reducedMotionMedia.addListener(sync);
  }
})();
