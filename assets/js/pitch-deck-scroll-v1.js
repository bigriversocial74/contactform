(() => {
  'use strict';

  const deck = document.querySelector('[data-pitch-deck]');
  if (!deck) return;

  const scrollSection = deck.querySelector('.pitch-scroll');
  const stage = deck.querySelector('.pitch-sticky');
  const scene = deck.querySelector('[data-pitch-scene]');
  const slides = [...deck.querySelectorAll('[data-pitch-slide]')];
  const navButtons = [...deck.querySelectorAll('[data-pitch-jump]')];
  const revealGroups = slides.map(slide => [...slide.querySelectorAll('[data-pitch-reveal]')]);
  const currentLabel = deck.querySelector('[data-pitch-current]');
  const nextButton = deck.querySelector('[data-pitch-next]');
  const orb = deck.querySelector('.pitch-landscape__orb');
  const siteHeader = document.querySelector('.mg-site-header');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const desktopMode = window.matchMedia('(min-width: 901px)');

  if (!scrollSection || !stage || !scene || slides.length === 0) return;

  const slideCount = slides.length;
  deck.style.setProperty('--pitch-slides', String(slideCount));
  deck.style.setProperty('overflow', 'visible', 'important');
  deck.classList.add('is-enhanced');

  const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));
  const mix = (from, to, amount) => from + (to - from) * amount;
  const smoothstep = value => {
    const v = clamp(value);
    return v * v * (3 - 2 * v);
  };

  const orbFrames = [
    { left: 78, top: 50, scale: .78, opacity: .22, rotation: 0 },
    { left: 88, top: 26, scale: .52, opacity: .12, rotation: 6 },
    { left: 13, top: 74, scale: .48, opacity: .13, rotation: -8 },
    { left: 84, top: 74, scale: .60, opacity: .10, rotation: 11 },
    { left: 14, top: 25, scale: .46, opacity: .12, rotation: -5 },
    { left: 87, top: 48, scale: .54, opacity: .11, rotation: 12 },
    { left: 82, top: 22, scale: .43, opacity: .12, rotation: 18 },
    { left: 12, top: 72, scale: .52, opacity: .12, rotation: 7 },
    { left: 17, top: 28, scale: .49, opacity: .11, rotation: -10 },
    { left: 81, top: 50, scale: .82, opacity: .24, rotation: 20 },
  ];

  let targetProgress = 0;
  let currentProgress = 0;
  let activeIndex = 0;
  let rafId = 0;
  let deckTop = 0;
  let progressTravel = 1;
  let stageTravel = 1;
  let headerOffset = 72;
  let stageHeight = Math.max(400, window.innerHeight - 72);
  let isDesktop = desktopMode.matches && !reducedMotion.matches;

  function formatSlide(index) {
    return String(index + 1).padStart(2, '0');
  }

  function clearStagePosition() {
    ['position', 'top', 'left', 'right', 'bottom', 'width', 'height', 'z-index']
      .forEach(property => stage.style.removeProperty(property));
  }

  function positionStage() {
    if (!isDesktop) return;

    // Move an absolutely positioned stage through the tall scroll track.
    // This produces the pinned presentation effect without relying on
    // position:sticky, which can be disabled by any ancestor overflow rule.
    const localTop = clamp(
      window.scrollY - deckTop + headerOffset,
      0,
      stageTravel
    );

    stage.style.setProperty('position', 'absolute', 'important');
    stage.style.setProperty('top', `${localTop.toFixed(2)}px`, 'important');
    stage.style.setProperty('left', '0', 'important');
    stage.style.setProperty('right', '0', 'important');
    stage.style.setProperty('bottom', 'auto', 'important');
    stage.style.setProperty('width', '100%', 'important');
    stage.style.setProperty('height', `${stageHeight}px`, 'important');
    stage.style.setProperty('z-index', '4');
  }

  function showStaticSlides() {
    clearStagePosition();
    slides.forEach(slide => {
      slide.classList.add('is-active');
      slide.setAttribute('aria-hidden', 'false');
      slide.style.removeProperty('--slide-opacity');
      slide.style.removeProperty('--slide-y');
      slide.style.removeProperty('--slide-scale');
      slide.style.removeProperty('--slide-blur');
      slide.style.setProperty('--slide-local', '1');
    });
    revealGroups.flat().forEach(item => item.style.setProperty('--reveal', '1'));
  }

  function updateMetrics() {
    isDesktop = desktopMode.matches && !reducedMotion.matches;
    headerOffset = Math.max(0, Math.round(siteHeader?.getBoundingClientRect().height || 72));
    stageHeight = Math.max(400, window.innerHeight - headerOffset);

    const rect = scrollSection.getBoundingClientRect();
    deckTop = window.scrollY + rect.top;
    progressTravel = Math.max(1, scrollSection.offsetHeight - window.innerHeight);
    stageTravel = Math.max(1, scrollSection.offsetHeight - stageHeight);

    if (!isDesktop) {
      showStaticSlides();
      return;
    }

    positionStage();
    measure();
  }

  function measure() {
    if (!isDesktop) return;
    targetProgress = clamp((window.scrollY - deckTop) / progressTravel);
    positionStage();
    if (!rafId) rafId = requestAnimationFrame(render);
  }

  function renderSlide(slide, index, position) {
    const delta = index - position;
    const distance = Math.abs(delta);
    const opacity = clamp(1 - distance * 1.45);
    const visibility = opacity > .015;
    const local = smoothstep(clamp(1 - distance));
    const y = delta * 74;
    const scale = .965 + local * .035;
    const blur = (1 - local) * 10;

    slide.style.setProperty('--slide-opacity', opacity.toFixed(4));
    slide.style.setProperty('--slide-y', `${y.toFixed(2)}px`);
    slide.style.setProperty('--slide-scale', scale.toFixed(4));
    slide.style.setProperty('--slide-blur', `${blur.toFixed(2)}px`);
    slide.style.setProperty('--slide-local', local.toFixed(4));
    slide.classList.toggle('is-active', visibility);
    slide.setAttribute('aria-hidden', visibility ? 'false' : 'true');

    revealGroups[index].forEach((item, itemIndex) => {
      const stagger = itemIndex * .075;
      const reveal = smoothstep(clamp((local - stagger) / Math.max(.2, 1 - stagger)));
      item.style.setProperty('--reveal', reveal.toFixed(4));
    });
  }

  function renderOrb(position) {
    if (!orb) return;
    const lower = Math.floor(position);
    const upper = Math.min(slideCount - 1, lower + 1);
    const amount = smoothstep(position - lower);
    const from = orbFrames[lower] || orbFrames[0];
    const to = orbFrames[upper] || from;
    const pulse = 1 + Math.sin(currentProgress * Math.PI * 10) * .012;

    const left = mix(from.left, to.left, amount);
    const top = mix(from.top, to.top, amount);
    const scale = mix(from.scale, to.scale, amount) * pulse;
    const opacity = mix(from.opacity, to.opacity, amount);
    const rotation = mix(from.rotation, to.rotation, amount);

    orb.style.left = `${left.toFixed(2)}%`;
    orb.style.top = `${top.toFixed(2)}%`;
    orb.style.opacity = opacity.toFixed(3);
    orb.style.transform = `translate(-50%, -50%) scale(${scale.toFixed(4)}) rotate(${rotation.toFixed(2)}deg)`;
  }

  function setActive(index) {
    if (index === activeIndex && currentLabel?.textContent === formatSlide(index)) return;
    activeIndex = index;
    if (currentLabel) currentLabel.textContent = formatSlide(index);
    navButtons.forEach((button, buttonIndex) => {
      const selected = buttonIndex === index;
      button.classList.toggle('is-active', selected);
      button.setAttribute('aria-current', selected ? 'step' : 'false');
    });
  }

  function render() {
    positionStage();
    currentProgress += (targetProgress - currentProgress) * .095;
    if (Math.abs(targetProgress - currentProgress) < .00015) currentProgress = targetProgress;

    const position = currentProgress * (slideCount - 1);
    const nextActive = Math.min(slideCount - 1, Math.max(0, Math.round(position)));

    deck.style.setProperty('--deck-progress', currentProgress.toFixed(5));
    deck.style.setProperty('--deck-slide', position.toFixed(4));
    slides.forEach((slide, index) => renderSlide(slide, index, position));
    renderOrb(position);
    setActive(nextActive);

    if (Math.abs(targetProgress - currentProgress) > .00015) {
      rafId = requestAnimationFrame(render);
    } else {
      rafId = 0;
    }
  }

  function jumpTo(index, behavior = 'smooth') {
    const safeIndex = Math.min(slideCount - 1, Math.max(0, index));
    if (!isDesktop) {
      slides[safeIndex]?.scrollIntoView({ behavior, block: 'start' });
      return;
    }
    const ratio = slideCount > 1 ? safeIndex / (slideCount - 1) : 0;
    window.scrollTo({ top: deckTop + progressTravel * ratio, behavior });
  }

  function deckIsInView() {
    const rect = scrollSection.getBoundingClientRect();
    return rect.bottom > headerOffset && rect.top < window.innerHeight;
  }

  navButtons.forEach(button => {
    button.addEventListener('click', () => {
      const index = Number.parseInt(button.dataset.pitchJump || '0', 10);
      jumpTo(Number.isFinite(index) ? index : 0);
    });
  });

  nextButton?.addEventListener('click', () => jumpTo(1));

  window.addEventListener('keydown', event => {
    if (!isDesktop || !deckIsInView()) return;
    const target = event.target;
    if (target instanceof HTMLElement && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT|BUTTON|A)$/.test(target.tagName))) return;

    if (event.key === 'ArrowDown' || event.key === 'ArrowRight' || event.key === 'PageDown' || event.key === ' ') {
      event.preventDefault();
      jumpTo(activeIndex + 1);
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft' || event.key === 'PageUp') {
      event.preventDefault();
      jumpTo(activeIndex - 1);
    } else if (event.key === 'Home') {
      event.preventDefault();
      jumpTo(0);
    } else if (event.key === 'End') {
      event.preventDefault();
      jumpTo(slideCount - 1);
    }
  });

  window.addEventListener('scroll', measure, { passive: true });
  window.addEventListener('resize', updateMetrics, { passive: true });
  window.addEventListener('load', updateMetrics, { once: true });
  reducedMotion.addEventListener?.('change', updateMetrics);
  desktopMode.addEventListener?.('change', updateMetrics);

  updateMetrics();
  if (isDesktop) {
    currentProgress = targetProgress;
    render();
  }
})();
