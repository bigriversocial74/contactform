(() => {
  'use strict';

  const hero = document.querySelector('.hero-scroll');
  const scene = document.getElementById('scene');
  const mountains = document.getElementById('mountains');
  const foreground = document.getElementById('foreground');
  const orb = document.getElementById('orb');
  const firstCopy = document.getElementById('heroCopy');
  const secondCopy = document.getElementById('secondCopy');
  const growthStage = document.getElementById('growthStage');
  const trackingLines = [...document.querySelectorAll('.tracking-line')];
  const chartArea = document.querySelector('.chart-area');
  const dots = [...document.querySelectorAll('.phase-dot')];
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (!hero || !scene || !mountains || !foreground || !orb || !firstCopy || !secondCopy || !growthStage) return;

  const title = firstCopy.querySelector('h1');
  const intro = firstCopy.querySelector('.intro');
  const action = firstCopy.querySelector('.primary-button');

  if (title) {
    title.innerHTML = '<span class="hero-title-lead">The Future of Gifting</span><span class="hero-title-arrived">Has Arrived.</span>';
  }

  const lead = firstCopy.querySelector('.hero-title-lead');
  const arrived = firstCopy.querySelector('.hero-title-arrived');

  [lead, arrived].forEach(node => {
    if (!node) return;
    node.style.display = 'block';
    node.style.willChange = 'opacity, transform, filter';
  });

  if (arrived) {
    arrived.style.opacity = '0';
    arrived.style.transform = 'translateY(24px) scale(.96)';
    arrived.style.filter = 'blur(7px)';
  }

  [intro, action].forEach(node => {
    if (!node) return;
    node.style.opacity = '0';
    node.style.transform = 'translateY(18px)';
    node.style.pointerEvents = 'none';
    node.style.willChange = 'opacity, transform';
  });

  if (reducedMotion) {
    orb.style.opacity = '.8';
    orb.style.zIndex = '5';
    if (arrived) {
      arrived.style.opacity = '1';
      arrived.style.transform = 'none';
      arrived.style.filter = 'none';
    }
    [intro, action].forEach(node => {
      if (!node) return;
      node.style.opacity = '1';
      node.style.transform = 'none';
      node.style.pointerEvents = 'auto';
    });
    return;
  }

  let target = 0;
  let current = 0;
  let raf = 0;

  const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));
  const mix = (from, to, amount) => from + (to - from) * amount;
  const easeOutExpo = value => value === 1 ? 1 : 1 - Math.pow(2, -10 * value);
  const easeInOutCubic = value => value < .5
    ? 4 * value * value * value
    : 1 - Math.pow(-2 * value + 2, 3) / 2;
  const smoothstep = value => value * value * (3 - 2 * value);
  const range = (p, start, end) => smoothstep(clamp((p - start) / (end - start)));

  function measure() {
    const rect = hero.getBoundingClientRect();
    const distance = hero.offsetHeight - window.innerHeight;
    target = distance > 0 ? clamp(-rect.top / distance) : 0;
    if (!raf) raf = requestAnimationFrame(render);
  }

  function render() {
    current += (target - current) * 0.085;
    const p = clamp(current);
    scene.style.setProperty('--progress', p.toFixed(4));

    mountains.style.transform = `translate3d(0, calc(100px - ${(p * 8).toFixed(2)}vh), 0) scale(${(1.14 + p * .055).toFixed(4)})`;
    foreground.style.transform = `translate3d(0, ${(p * 18).toFixed(2)}vh, 0) scale(${(1 + p * .16).toFixed(4)})`;

    // Rise from behind the foreground while moving away toward the mountains.
    const rise = range(p, .008, .115);
    const apexHold = clamp((p - .112) / .105);
    const shakeEnvelope = Math.sin(apexHold * Math.PI);
    const shoot = range(p, .217, .315);
    const entrance = easeOutExpo(clamp(p / .315));

    const riseArc = Math.sin(rise * Math.PI);
    let left = mix(61, 69, rise) + riseArc * 7.5;
    let top = mix(114, 39, rise) - riseArc * 7;
    let scale = mix(.44, .11, rise);
    let rotation = mix(-8, -2, rise) + riseArc * 5;

    // Keep the orb behind the foreground until it clears the ridge line.
    orb.style.zIndex = p < .082 ? '2' : '5';

    // Prolonged stall and shake at the top of the rise.
    left += Math.sin(apexHold * Math.PI * 14) * 1.25 * shakeEnvelope;
    top += Math.cos(apexHold * Math.PI * 16) * .8 * shakeEnvelope;
    rotation += Math.sin(apexHold * Math.PI * 18) * 3.8 * shakeEnvelope;

    // Reverse the depth motion and drive the orb toward the viewer.
    top = mix(top, 46, shoot);
    left = mix(left, 66, shoot);
    scale = mix(scale, .82, shoot);
    rotation = mix(rotation, -5, shoot);

    const chapterTwo = easeInOutCubic(clamp((p - .29) / .45));
    const waveX = Math.sin(chapterTwo * Math.PI * 1.65) * 8;
    const waveY = Math.sin(chapterTwo * Math.PI * 2.2) * 5;
    left = mix(left, 34, chapterTwo) + waveX;
    top = mix(top, 58, chapterTwo) + waveY;
    scale *= mix(1, 1.18, chapterTwo);
    rotation = mix(rotation, 10, chapterTwo);

    const chartSettle = smoothstep(clamp((p - .70) / .16));
    left = mix(left, 50, chartSettle);
    top = mix(top, 83, chartSettle);
    scale = mix(scale, scale * .52, chartSettle);
    rotation += chartSettle * 7;

    const handoff = smoothstep(clamp((p - .94) / .06));
    top = mix(top, 112, handoff);
    scale = mix(scale, scale * .72, handoff);
    rotation += handoff * 9;

    const pulse = 1 + Math.sin(p * Math.PI * 9) * .018;
    orb.style.left = `${left}%`;
    orb.style.top = `${top}%`;
    orb.style.opacity = '.8';
    orb.style.transform = `translate(-50%, -50%) scale(${(scale * pulse).toFixed(4)}) rotate(${rotation.toFixed(2)}deg)`;
    orb.style.filter = `drop-shadow(0 ${28 + entrance * 22}px ${30 + entrance * 30}px rgba(255,181,147,${.12 + entrance * .22}))`;

    const arrivedIn = range(p, .278, .335);
    if (arrived) {
      arrived.style.opacity = String(arrivedIn);
      arrived.style.transform = `translateY(${((1 - arrivedIn) * 24).toFixed(1)}px) scale(${(.96 + arrivedIn * .04).toFixed(4)})`;
      arrived.style.filter = `blur(${((1 - arrivedIn) * 7).toFixed(2)}px)`;
    }

    const supportingIn = range(p, .315, .365);
    [intro, action].forEach(node => {
      if (!node) return;
      node.style.opacity = String(supportingIn);
      node.style.transform = `translateY(${((1 - supportingIn) * 18).toFixed(1)}px)`;
      node.style.pointerEvents = supportingIn > .8 ? 'auto' : 'none';
    });

    const firstExit = smoothstep(clamp((p - .38) / .11));
    firstCopy.style.opacity = String(1 - firstExit);
    firstCopy.style.transform = `translateY(calc(-50% - ${firstExit * 46}px))`;
    firstCopy.style.pointerEvents = firstExit > .7 ? 'none' : 'auto';

    const secondEnter = smoothstep(clamp((p - .45) / .11));
    const secondExit = smoothstep(clamp((p - .68) / .12));
    const secondOpacity = secondEnter * (1 - secondExit);
    secondCopy.style.opacity = String(secondOpacity);
    secondCopy.style.transform = `translateY(calc(-50% + ${(1 - secondEnter) * 46 - secondExit * 34}px))`;
    secondCopy.style.pointerEvents = secondOpacity > .65 ? 'auto' : 'none';
    secondCopy.setAttribute('aria-hidden', secondOpacity > .1 ? 'false' : 'true');

    const growthEnter = smoothstep(clamp((p - .72) / .11));
    const growthExit = smoothstep(clamp((p - .955) / .045));
    const growthOpacity = growthEnter * (1 - growthExit);
    growthStage.style.opacity = String(growthOpacity);
    growthStage.style.visibility = growthOpacity > .01 ? 'visible' : 'hidden';
    growthStage.style.transform = `translateY(${(1 - growthEnter) * 34 - growthExit * 24}px)`;
    growthStage.setAttribute('aria-hidden', growthOpacity > .1 ? 'false' : 'true');

    const chartDraw = smoothstep(clamp((p - .765) / .17));
    trackingLines.forEach((line, index) => {
      const delay = index * .055;
      const local = smoothstep(clamp((chartDraw - delay) / (1 - delay)));
      line.style.strokeDashoffset = String(1 - local);
      line.style.opacity = String(.25 + local * .75);
    });
    if (chartArea) chartArea.style.opacity = String(chartDraw * .78);

    const activePhase = p >= .72 ? 2 : (p >= .45 ? 1 : 0);
    dots.forEach((dot, index) => dot.classList.toggle('is-active', index === activePhase));

    if (Math.abs(target - current) > .0005) {
      raf = requestAnimationFrame(render);
    } else {
      current = target;
      raf = 0;
    }
  }

  window.addEventListener('scroll', measure, { passive: true });
  window.addEventListener('resize', measure, { passive: true });
  measure();
})();

(() => {
  'use strict';
  const items = document.querySelectorAll('.reveal-on-scroll');
  if (!items.length) return;
  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    items.forEach(item => item.classList.add('is-visible'));
    return;
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: .18 });
  items.forEach(item => observer.observe(item));
})();

(() => {
  'use strict';
  const agentSection = document.querySelector('.agent-scroll');
  const agentPin = document.querySelector('.agent-pin');
  const agentIntro = agentSection?.querySelector('.agent-section__intro');
  const agentConsole = agentSection?.querySelector('.agent-console');
  const panels = agentSection ? [...agentSection.querySelectorAll('.agent-console__bar, .agent-console__customer, .signal-card, .agent-console__footer')] : [];
  const mountainSection = document.querySelector('.mountain-zoom-section');
  const mountainPin = document.querySelector('.mountain-zoom-pin');
  const howSteps = [...document.querySelectorAll('.how-step')];
  const howConnectors = [...document.querySelectorAll('.how-connector')];
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reducedMotion || (!agentSection && !mountainSection)) return;
  let ticking = false;
  const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
  const smooth = v => v * v * (3 - 2 * v);
  const range = (p, start, end) => smooth(clamp((p - start) / (end - start)));
  const sectionProgress = section => {
    const rect = section.getBoundingClientRect();
    const distance = section.offsetHeight - window.innerHeight;
    return distance > 0 ? clamp(-rect.top / distance) : 0;
  };
  function renderAgent() {
    if (!agentSection || !agentPin || window.innerWidth <= 980) return;
    const p = sectionProgress(agentSection);
    const copyIn = range(p, .02, .22);
    const copyOut = range(p, .72, .92);
    const copyOpacity = copyIn * (1 - copyOut * .72);
    agentIntro.style.setProperty('--agent-copy-opacity', copyOpacity.toFixed(3));
    agentIntro.style.setProperty('--agent-copy-y', `${((1 - copyIn) * 58 - copyOut * 32).toFixed(1)}px`);
    const consoleIn = range(p, .16, .39);
    const consoleOut = range(p, .88, 1);
    agentConsole.style.setProperty('--agent-console-opacity', (consoleIn * (1 - consoleOut)).toFixed(3));
    agentConsole.style.setProperty('--agent-console-x', `${((1 - consoleIn) * 86 + consoleOut * 45).toFixed(1)}px`);
    agentConsole.style.setProperty('--agent-console-scale', (0.95 + consoleIn * .05 - consoleOut * .025).toFixed(4));
    panels.forEach((panel, index) => {
      const start = .28 + index * .075;
      const enter = range(p, start, start + .15);
      const exit = range(p, .91, 1);
      panel.style.setProperty('--panel-opacity', (enter * (1 - exit)).toFixed(3));
      panel.style.setProperty('--panel-x', `${((1 - enter) * 48 + exit * 24).toFixed(1)}px`);
    });
  }
  function renderMountain() {
    if (!mountainSection || !mountainPin) return;
    const p = sectionProgress(mountainSection);
    const zoom = range(p, .02, .68);
    const copyIn = range(p, .03, .18);
    const copyOut = range(p, .43, .58);
    const imageFade = range(p, .50, .70);
    const howIn = range(p, .38, .50);
    const howIntroIn = range(p, .44, .55);
    const howOut = range(p, .84, .96);
    mountainPin.style.setProperty('--mountain-scale', (1.03 + zoom * .68).toFixed(4));
    mountainPin.style.setProperty('--mountain-y', `${(-zoom * 12).toFixed(2)}vh`);
    mountainPin.style.setProperty('--foreground-scale', (1.04 + zoom * .26).toFixed(4));
    mountainPin.style.setProperty('--foreground-y', `${(zoom * 7).toFixed(2)}vh`);
    mountainPin.style.setProperty('--mountain-image-opacity', (1 - imageFade).toFixed(3));
    mountainPin.style.setProperty('--foreground-image-opacity', (1 - imageFade * 1.08).toFixed(3));
    mountainPin.style.setProperty('--mountain-overlay-opacity', (1 - imageFade).toFixed(3));
    mountainPin.style.setProperty('--mountain-copy-opacity', (copyIn * (1 - copyOut)).toFixed(3));
    mountainPin.style.setProperty('--mountain-copy-y', `${((1 - copyIn) * 46 - copyOut * 34).toFixed(1)}px`);
    mountainPin.style.setProperty('--how-opacity', (howIn * (1 - howOut)).toFixed(3));
    mountainPin.style.setProperty('--how-y', `${((1 - howIn) * 54).toFixed(1)}px`);
    mountainPin.style.setProperty('--how-intro-opacity', howIntroIn.toFixed(3));
    mountainPin.style.setProperty('--how-intro-y', `${((1 - howIntroIn) * 36).toFixed(1)}px`);
    howSteps.forEach((step, index) => {
      const start = .50 + index * .035;
      const enter = range(p, start, start + .10);
      step.style.setProperty('--how-step-opacity', enter.toFixed(3));
      step.style.setProperty('--how-step-y', `${((1 - enter) * 42).toFixed(1)}px`);
      step.style.setProperty('--how-step-scale', (0.96 + enter * .04).toFixed(4));
    });
    howConnectors.forEach((connector, index) => {
      const start = .54 + index * .04;
      const enter = range(p, start, start + .08);
      connector.style.setProperty('--how-connector-opacity', enter.toFixed(3));
      connector.style.setProperty('--how-connector-scale', enter.toFixed(3));
    });
    mountainPin.style.setProperty('--zoom-progress', p.toFixed(4));
  }
  function render() { ticking = false; renderAgent(); renderMountain(); }
  function requestRender() { if (!ticking) { ticking = true; requestAnimationFrame(render); } }
  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', requestRender, { passive: true });
  requestRender();
})();

(() => {
  'use strict';
  const section = document.querySelector('.pppm-presentation');
  const timeline = section?.querySelector('.pppm-timeline');
  const events = section ? [...section.querySelectorAll('.pppm-event')] : [];
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!section || !timeline || !events.length || reducedMotion) return;
  let ticking = false;
  const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
  const smooth = v => v * v * (3 - 2 * v);
  const range = (p, start, end) => smooth(clamp((p - start) / (end - start)));
  function render() {
    ticking = false;
    const timelineRect = timeline.getBoundingClientRect();
    const progressStart = window.innerHeight * .72;
    const progressEnd = -timelineRect.height + window.innerHeight * .35;
    const timelineProgress = clamp((progressStart - timelineRect.top) / (progressStart - progressEnd));
    timeline.style.setProperty('--pppm-progress', timelineProgress.toFixed(4));
    events.forEach((event, index) => {
      const rect = event.getBoundingClientRect();
      const distance = Math.max(1, event.offsetHeight - window.innerHeight);
      const p = clamp(-rect.top / distance);
      const odd = index % 2 === 0;
      const enter = range(p, .04, .30);
      const leave = range(p, .79, .98);
      const visibility = clamp(enter * (1 - leave * .84));
      const hold = range(p, .28, .42) * (1 - range(p, .72, .86));
      const connector = range(p, .20, .40);
      const cardStart = odd ? -150 : 150;
      const visualStart = odd ? 170 : -170;
      event.style.setProperty('--card-opacity', visibility.toFixed(3));
      event.style.setProperty('--visual-opacity', visibility.toFixed(3));
      event.style.setProperty('--card-x', `${(cardStart * (1 - enter) + (odd ? -18 : 18) * leave).toFixed(1)}px`);
      event.style.setProperty('--visual-x', `${(visualStart * (1 - enter) + (odd ? 24 : -24) * leave).toFixed(1)}px`);
      event.style.setProperty('--visual-scale', (.90 + enter * .10 + hold * .018).toFixed(3));
      event.style.setProperty('--marker-scale', (.78 + enter * .22 + hold * .05).toFixed(3));
      event.style.setProperty('--connector-scale', connector.toFixed(3));
    });
  }
  function requestRender() { if (!ticking) { ticking = true; requestAnimationFrame(render); } }
  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', requestRender, { passive: true });
  requestRender();
})();

(() => {
  'use strict';
  const section = document.querySelector('.final-cta');
  const stage = section?.querySelector('.final-cta__stage');
  const inner = section?.querySelector('.final-cta__inner');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!section || !stage || !inner || reducedMotion) return;
  let ticking = false;
  const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
  const smooth = v => v * v * (3 - 2 * v);
  const range = (p, start, end) => smooth(clamp((p - start) / (end - start)));
  function render() {
    ticking = false;
    const rect = section.getBoundingClientRect();
    const distance = section.offsetHeight - window.innerHeight;
    const p = distance > 0 ? clamp(-rect.top / distance) : 1;
    const ctaP = clamp(p / .74);
    const mountainIn = range(ctaP, .00, .18);
    const foregroundIn = range(ctaP, .08, .28);
    const fall = range(ctaP, .02, .34);
    const settle = range(ctaP, .31, .42);
    const eyebrowIn = range(ctaP, .34, .44);
    const titleIn = range(ctaP, .38, .50);
    const copyIn = range(ctaP, .46, .58);
    const actionsIn = range(ctaP, .54, .66);
    const metaIn = range(ctaP, .62, .72);
    const shoot = range(ctaP, .78, .98);
    const pricingIn = range(p, .76, .86);
    const fallY = -22 + fall * 88;
    const settleY = Math.sin(settle * Math.PI) * 1.8;
    const shootY = shoot * 16;
    const scale = .09 + fall * .91 + shoot * 8.3;
    const orbOpacity = clamp(range(ctaP, .01, .09) * (1 - range(ctaP, .94, 1)));
    const textExit = range(ctaP, .79, .93);
    stage.style.setProperty('--cta-mountain-opacity', mountainIn.toFixed(3));
    stage.style.setProperty('--cta-foreground-opacity', foregroundIn.toFixed(3));
    stage.style.setProperty('--cta-inner-opacity', (titleIn * (1 - textExit)).toFixed(3));
    stage.style.setProperty('--cta-inner-y', `${((1 - titleIn) * 40 - textExit * 20).toFixed(1)}px`);
    stage.style.setProperty('--cta-inner-scale', (0.97 + titleIn * .03 - textExit * .025).toFixed(4));
    stage.style.setProperty('--cta-eyebrow-opacity', (eyebrowIn * (1 - textExit)).toFixed(3));
    stage.style.setProperty('--cta-eyebrow-y', `${((1 - eyebrowIn) * 20).toFixed(1)}px`);
    stage.style.setProperty('--cta-copy-opacity', (copyIn * (1 - textExit)).toFixed(3));
    stage.style.setProperty('--cta-copy-y', `${((1 - copyIn) * 24).toFixed(1)}px`);
    stage.style.setProperty('--cta-actions-opacity', (actionsIn * (1 - textExit)).toFixed(3));
    stage.style.setProperty('--cta-actions-y', `${((1 - actionsIn) * 28).toFixed(1)}px`);
    stage.style.setProperty('--cta-meta-opacity', (metaIn * (1 - textExit)).toFixed(3));
    stage.style.setProperty('--cta-meta-y', `${((1 - metaIn) * 24).toFixed(1)}px`);
    stage.style.setProperty('--cta-orb-opacity', orbOpacity.toFixed(3));
    stage.style.setProperty('--cta-orb-scale', scale.toFixed(4));
    stage.style.setProperty('--cta-orb-y', `${(fallY + settleY + shootY).toFixed(2)}vh`);
    stage.style.setProperty('--cta-mountain-scale', (1 + ctaP * .06 + shoot * .04).toFixed(4));
    stage.style.setProperty('--cta-mountain-y', `${(-ctaP * 14).toFixed(1)}px`);
    stage.style.setProperty('--cta-foreground-scale', (1.02 + ctaP * .025).toFixed(4));
    stage.style.setProperty('--cta-foreground-y', `${(-Math.min(ctaP, .72) * 9).toFixed(1)}px`);
    if (p >= .28) {
      stage.style.setProperty('--cta-mountain-opacity', '1');
      stage.style.setProperty('--cta-foreground-opacity', '1');
    }
    stage.style.setProperty('--pricing-opacity', pricingIn.toFixed(3));
    stage.style.setProperty('--pricing-y', `${((1 - pricingIn) * 70).toFixed(1)}px`);
    stage.style.setProperty('--pricing-scale', (0.97 + pricingIn * .03).toFixed(4));
    stage.style.setProperty('--price-card-opacity', pricingIn.toFixed(3));
    stage.style.setProperty('--price-card-y', `${((1 - pricingIn) * 44).toFixed(1)}px`);
    inner.style.opacity = String(clamp(titleIn * (1 - textExit)));
  }
  function requestRender() { if (!ticking) { ticking = true; requestAnimationFrame(render); } }
  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', requestRender, { passive: true });
  requestRender();
})();

(() => {
  'use strict';
  const section = document.querySelector('.story-scroll');
  if (!section || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const sticky = section.querySelector('.story-sticky');
  const copy = section.querySelector('.story-copy');
  const steps = [...section.querySelectorAll('.steps article')];
  const ambient = section.querySelector('.story-ambient');
  const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
  const smooth = v => v * v * (3 - 2 * v);
  const range = (p, start, end) => smooth(clamp((p - start) / (end - start)));
  let ticking = false;
  function render() {
    ticking = false;
    if (window.innerWidth <= 560) return;
    const rect = section.getBoundingClientRect();
    const distance = section.offsetHeight - window.innerHeight;
    const p = distance > 0 ? clamp(-rect.top / distance) : 0;
    const copyIn = range(p, .02, .18);
    const copyShift = range(p, .42, .72);
    copy.style.setProperty('--story-copy-opacity', (copyIn * (1 - copyShift * .28)).toFixed(3));
    copy.style.setProperty('--story-copy-y', `${((1 - copyIn) * 58 - copyShift * 28).toFixed(1)}px`);
    sticky.style.setProperty('--story-line-scale', range(p, .18, .84).toFixed(4));
    sticky.style.setProperty('--story-progress', p.toFixed(4));
    steps.forEach((step, index) => {
      const start = .18 + index * .135;
      const enter = range(p, start, start + .16);
      const settle = range(p, start + .12, start + .30);
      step.style.setProperty('--story-step-opacity', enter.toFixed(3));
      step.style.setProperty('--story-step-y', `${((1 - enter) * 64 - settle * 8).toFixed(1)}px`);
      step.style.setProperty('--story-step-scale', (0.965 + enter * .035).toFixed(4));
      step.classList.toggle('is-active', p >= start + .07 && p < start + .24);
    });
    if (ambient) {
      ambient.style.setProperty('--story-ambient-y', `${(-p * 70).toFixed(1)}px`);
      ambient.style.setProperty('--story-ambient-scale', (1 + p * .24).toFixed(4));
      ambient.style.setProperty('--story-ambient-opacity', (.35 + range(p, .08, .6) * .45).toFixed(3));
    }
  }
  function requestRender() { if (!ticking) { ticking = true; requestAnimationFrame(render); } }
  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', requestRender, { passive: true });
  requestRender();
})();