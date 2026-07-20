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

  const hero = document.querySelector('.hero-scroll');
  const growthStage = document.getElementById('growthStage');
  if (growthStage) {
    growthStage.classList.add('hero-product-stage');
    growthStage.innerHTML = `
      <div class="hero-product-showcase" aria-label="Microgifter product workspace previews">
        <figure class="hero-product-shot hero-product-shot--agent">
          <img src="/assets/images/hero_agent_chat.png?v=1.0.0" alt="Microgifter agent chat workspace" decoding="async">
          <figcaption>
            <span>01 · Agent chat</span>
            <strong>Turn customer intent into the next thoughtful action.</strong>
            <small>Keep conversations, gifting context, service history, and recommendations connected through one active relationship agent.</small>
          </figcaption>
        </figure>
        <figure class="hero-product-shot hero-product-shot--crm">
          <img src="/assets/images/hero_merchant_CRM.png?v=1.0.0" alt="Microgifter merchant CRM workspace" decoding="async">
          <figcaption>
            <span>02 · Merchant CRM</span>
            <strong>See the complete relationship behind every customer.</strong>
            <small>Connect purchases, claims, visits, rewards, referrals, messages, and campaign activity in one usable merchant record.</small>
          </figcaption>
        </figure>
        <figure class="hero-product-shot hero-product-shot--inbox">
          <img src="/assets/images/hero_inbox.png?v=1.0.0" alt="Microgifter gifting inbox workspace" decoding="async">
          <figcaption>
            <span>03 · Gift inbox</span>
            <strong>Follow every Microgift from purchase through redemption.</strong>
            <small>Manage received, sent, claimed, redeemed, refunded, and regifted activity without losing ownership or customer context.</small>
          </figcaption>
        </figure>
      </div>`;
  }

  const showcaseCards = growthStage ? [...growthStage.querySelectorAll('.hero-product-shot')] : [];
  const section = document.querySelector('.final-cta');
  const stage = section?.querySelector('.final-cta__stage');
  const orb = section?.querySelector('.final-cta__orb');
  const pricing = section?.querySelector('.pricing-reveal');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const desktop = window.matchMedia('(min-width: 821px)');
  const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));
  const smooth = value => value * value * (3 - 2 * value);
  const range = (value, start, end) => smooth(clamp((value - start) / (end - start)));
  let frame = 0;

  const renderHeroShowcase = () => {
    if (!hero || !growthStage || !showcaseCards.length) return;
    if (reduced.matches) {
      showcaseCards.forEach(card => {
        card.style.removeProperty('opacity');
        card.style.removeProperty('transform');
        card.style.removeProperty('filter');
      });
      return;
    }

    const rect = hero.getBoundingClientRect();
    const distance = Math.max(1, hero.offsetHeight - window.innerHeight);
    const progress = clamp(-rect.top / distance);
    const stageProgress = range(progress, .72, .91);

    showcaseCards.forEach((card, index) => {
      const delay = index * .16;
      const local = smooth(clamp((stageProgress - delay) / Math.max(.01, 1 - delay)));
      const direction = index === 0 ? -1 : (index === 2 ? 1 : 0);
      const x = direction * (1 - local) * 90;
      const y = (1 - local) * (index === 1 ? 62 : 38);
      const rotation = direction * (1 - local) * 6;
      card.style.opacity = local.toFixed(3);
      card.style.transform = `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0) scale(${(.9 + local * .1).toFixed(4)}) rotate(${rotation.toFixed(2)}deg)`;
      card.style.filter = `blur(${((1 - local) * 9).toFixed(2)}px)`;
    });
  };

  const render = () => {
    frame = 0;
    renderHeroShowcase();

    if (!section || !stage || !orb || !pricing) return;
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
