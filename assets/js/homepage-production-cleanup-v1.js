(() => {
  'use strict';

  if (document.body?.dataset.pageId !== 'index') return;

  const heroPrimary = document.querySelector('#heroCopy .primary-button');
  if (heroPrimary) {
    heroPrimary.href = '#agent-in-action';
    heroPrimary.innerHTML = 'See Microgifter in action <span>→</span>';
  }

  const routes = [
    ['/discover.php', 'Explore local gifts'],
    ['/sales-crm.php', 'See the CRM'],
    ['/merchant-campaigns.php', 'Build a campaign'],
    ['/sales-crm.php', 'Connect conversations'],
    ['/inbox.php', 'Follow the lifecycle'],
    ['/build.php', 'Automate demand']
  ];
  document.querySelectorAll('.pppm-link').forEach((link, index) => {
    const route = routes[index];
    if (!route) return;
    link.href = route[0];
    link.innerHTML = `${route[1]} <span>→</span>`;
  });

  const section = document.querySelector('.final-cta');
  const stage = section?.querySelector('.final-cta__stage');
  const orb = section?.querySelector('.final-cta__orb');
  const pricing = section?.querySelector('.pricing-reveal');
  if (!section || !stage || !orb || !pricing) return;

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const desktop = window.matchMedia('(min-width: 821px)');
  const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));
  const smooth = value => value * value * (3 - 2 * value);
  const range = (value, start, end) => smooth(clamp((value - start) / (end - start)));
  let frame = 0;

  const render = () => {
    frame = 0;
    if (!desktop.matches || reduced.matches) {
      orb.style.removeProperty('transform');
      orb.style.removeProperty('opacity');
      orb.style.removeProperty('filter');
      pricing.style.removeProperty('opacity');
      pricing.style.removeProperty('transform');
      return;
    }

    const rect = section.getBoundingClientRect();
    const distance = Math.max(1, section.offsetHeight - window.innerHeight);
    const progress = clamp(-rect.top / distance);

    const drop = range(progress, .01, .24);
    const hold = range(progress, .24, .37);
    const launch = range(progress, .39, .68);
    const dissolve = range(progress, .62, .73);
    const pricingIn = range(progress, .70, .82);

    const dropY = -26 + drop * 74;
    const settle = Math.sin(hold * Math.PI * 2) * (1 - hold) * 1.8;
    const launchY = launch * 19;
    const scale = .12 + drop * .78 + launch * 3.15;
    const opacity = clamp(range(progress, .01, .06) * (1 - dissolve));

    orb.style.setProperty('transform', `translate3d(-50%, ${(dropY + settle + launchY).toFixed(2)}vh, 0) scale(${scale.toFixed(4)})`, 'important');
    orb.style.setProperty('opacity', opacity.toFixed(3), 'important');
    orb.style.setProperty('filter', `drop-shadow(0 ${Math.round(18 + launch * 40)}px ${Math.round(24 + launch * 56)}px rgba(255,181,147,${(.14 + launch * .18).toFixed(3)}))`, 'important');

    pricing.style.setProperty('opacity', pricingIn.toFixed(3), 'important');
    pricing.style.setProperty('transform', `translate3d(0, ${((1 - pricingIn) * 54).toFixed(1)}px, 0) scale(${(.975 + pricingIn * .025).toFixed(4)})`, 'important');
    pricing.style.pointerEvents = pricingIn > .72 ? 'auto' : 'none';
  };

  const requestRender = () => {
    if (!frame) frame = window.requestAnimationFrame(render);
  };

  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', requestRender, { passive: true });
  desktop.addEventListener?.('change', requestRender);
  reduced.addEventListener?.('change', requestRender);
  requestRender();
})();
