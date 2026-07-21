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
    ['/agent.php', 'Explore agentic commerce'],
    ['/sales-crm.php', 'Connect conversations'],
    ['/build.php', 'Open Design Studio'],
    ['/feed.php', 'Explore the social feed']
  ];
  document.querySelectorAll('.pppm-link').forEach((link, index) => {
    const route = routes[index];
    if (!route) return;
    link.href = route[0];
    link.innerHTML = `${route[1]} <span>→</span>`;
  });

  const growthStage = document.getElementById('growthStage');
  if (growthStage) {
    growthStage.classList.remove('hero-product-stage');
    growthStage.classList.add('hero-third-message');
    growthStage.innerHTML = `
      <div class="hero-third-message__inner">
        <p class="eyebrow">One connected relationship system</p>
        <h2>Every customer moment becomes part of what happens next.</h2>
        <p>Microgifter connects conversations, gifting, CRM activity, claims, rewards, and follow-up so every interaction carries useful context forward.</p>
      </div>`;
  }

  document.getElementById('product-workspace-showcase')?.remove();

  const timelineImages = [
    { index: 0, src: '/assets/images/hero_inbox.png?v=1.0.0', alt: 'Microgifter gifting inbox workspace' },
    { index: 1, src: '/assets/images/hero_merchant_CRM.png?v=1.0.0', alt: 'Microgifter merchant CRM workspace' },
    { index: 2, src: '/assets/images/hero_agent_chat.png?v=1.0.0', alt: 'Microgifter agentic commerce workspace' },
    { index: 3, src: '/assets/images/hero_messages.png?v=1.0.0', alt: 'Microgifter customer messaging workspace' },
    { index: 4, src: '/assets/images/hero_design_studio.png?v=1.0.0', alt: 'Microgifter Design Studio workspace' },
    { index: 5, src: '/assets/images/hero_social_feed.png?v=1.0.0', alt: 'Microgifter social feed workspace' }
  ];

  const timelineCopy = [
    {
      eyebrow: 'Social Gifting',
      title: 'Sell now. Send later.',
      body: 'Give customers a simple inbox for buying, receiving, sending, claiming, and managing local gifts across every stage of the relationship.'
    },
    {
      eyebrow: 'Merchant CRM',
      title: 'Every action becomes customer memory.',
      body: 'Connect purchases, claims, visits, messages, referrals, and reward activity to usable customer records.'
    },
    {
      eyebrow: 'Agentic Integrations',
      title: 'One intelligent layer for customers and merchants.',
      body: 'Customer agents help people discover, plan, purchase, and send local gifts while merchant agents assist with service, recommendations, campaigns, follow-up, and recurring commerce.'
    },
    {
      eyebrow: 'Customer Messaging',
      title: 'Keep every conversation connected to the relationship.',
      body: 'Message customers with purchase, claim, reward, visit, and campaign context already attached so follow-up stays relevant and useful.'
    },
    {
      eyebrow: 'Design Studio',
      title: 'Create campaigns that are ready to publish.',
      body: 'Build branded images, offers, social content, campaign assets, and merchant promotions from one connected creative workspace.'
    },
    {
      eyebrow: 'Social Feed',
      title: 'Turn local activity into ongoing discovery.',
      body: 'Share products, offers, campaigns, merchant stories, customer moments, and community activity through a social feed built around local commerce.'
    }
  ];

  const timelineEvents = [...document.querySelectorAll('#pppm-presentation .pppm-event')];

  timelineImages.forEach((image) => {
    const visual = timelineEvents[image.index]?.querySelector('.pppm-visual');
    if (!visual) return;
    visual.classList.add('pppm-visual--real-image');
    visual.innerHTML = `
      <div class="pppm-desktop-shell" role="img" aria-label="${image.alt}">
        <div class="pppm-desktop-shell__bar" aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
        <div class="pppm-desktop-shell__screen">
          <img src="${image.src}" alt="${image.alt}" decoding="async">
        </div>
      </div>`;
  });

  timelineCopy.forEach((copy, index) => {
    const card = timelineEvents[index]?.querySelector('.pppm-card');
    if (!card) return;
    const eyebrow = card.querySelector('p');
    const title = card.querySelector('h3');
    const body = card.querySelector(':scope > span');
    if (eyebrow) eyebrow.textContent = copy.eyebrow;
    if (title) title.textContent = copy.title;
    if (body) body.textContent = copy.body;
  });

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

  const renderFinalCta = () => {
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
    if (!frame) frame = window.requestAnimationFrame(() => {
      frame = 0;
      renderFinalCta();
    });
  };

  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', requestRender, { passive: true });
  desktop.addEventListener?.('change', requestRender);
  reduced.addEventListener?.('change', requestRender);
  requestRender();
})();