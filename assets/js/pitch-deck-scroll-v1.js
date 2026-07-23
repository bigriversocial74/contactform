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

  const RUNTIME_VERSION = '2.1.0';
  const HOLD_PORTION = 0.74;
  const REVEAL_PORTION = 0.44;
  const MIN_STEP_PX = 980;
  const STEP_HEIGHT_MULTIPLIER = 1.38;
  const MIN_FIT_SCALE = 0.76;
  const slideCount = slides.length;

  deck.dataset.pitchRuntime = RUNTIME_VERSION;
  deck.style.setProperty('--pitch-slides', String(slideCount));
  deck.style.setProperty('overflow', 'visible', 'important');
  deck.classList.add('is-enhanced');

  const runtimeCssHref = `/assets/css/pitch-deck-scroll-runtime-v2.css?v=${RUNTIME_VERSION}`;
  let runtimeCss = document.querySelector('link[href*="pitch-deck-scroll-runtime-v2.css"]');
  if (!runtimeCss) {
    runtimeCss = document.createElement('link');
    runtimeCss.rel = 'stylesheet';
    runtimeCss.href = runtimeCssHref;
    runtimeCss.dataset.pitchRuntimeCss = RUNTIME_VERSION;
    document.head.append(runtimeCss);
  }

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

  let rafId = 0;
  let activeIndex = 0;
  let isDesktop = desktopMode.matches && !reducedMotion.matches;
  let headerHeight = 72;
  let visibleHeaderOffset = 72;
  let minimumStageHeight = Math.max(400, window.innerHeight - headerHeight);
  let activeStageHeight = Math.max(400, window.innerHeight - visibleHeaderOffset);
  let sectionTop = 0;
  let sectionHeight = minimumStageHeight;
  let startScroll = 0;
  let endScroll = 1;
  let stepPixels = MIN_STEP_PX;
  let timeline = 0;
  let stageState = '';

  function formatSlide(index) {
    return String(index + 1).padStart(2, '0');
  }

  function readHeaderHeight() {
    if (!siteHeader) return 0;
    const rect = siteHeader.getBoundingClientRect();
    return Math.max(0, Math.round(rect.height));
  }

  function readVisibleHeaderOffset() {
    if (!siteHeader) return 0;
    const rect = siteHeader.getBoundingClientRect();
    if (rect.height <= 0 || rect.bottom <= 0 || rect.top >= window.innerHeight) return 0;
    return Math.round(clamp(rect.bottom, 0, Math.min(rect.height, window.innerHeight)));
  }

  function refreshVisibleHeaderGeometry() {
    visibleHeaderOffset = readVisibleHeaderOffset();
    activeStageHeight = Math.max(400, window.innerHeight - visibleHeaderOffset);
    deck.style.setProperty('--pitch-header-visible', `${visibleHeaderOffset}px`);
  }

  function setImportant(property, value) {
    stage.style.setProperty(property, value, 'important');
  }

  function clearStagePosition() {
    ['position', 'top', 'left', 'right', 'bottom', 'width', 'height', 'z-index']
      .forEach(property => stage.style.removeProperty(property));
    stageState = '';
  }

  function setStageState(nextState) {
    if (!isDesktop) return;

    const sectionRect = scrollSection.getBoundingClientRect();
    const width = Math.max(1, sectionRect.width);
    const left = sectionRect.left;

    if (nextState === 'before') {
      if (stageState !== 'before') {
        setImportant('position', 'absolute');
        setImportant('top', '0');
        setImportant('bottom', 'auto');
        setImportant('left', '0');
        setImportant('right', '0');
        setImportant('width', '100%');
        setImportant('height', `${minimumStageHeight}px`);
        stage.style.setProperty('z-index', '4');
        stageState = 'before';
      }
      return;
    }

    if (nextState === 'after') {
      if (stageState !== 'after') {
        setImportant('position', 'absolute');
        setImportant('top', 'auto');
        setImportant('bottom', '0');
        setImportant('left', '0');
        setImportant('right', '0');
        setImportant('width', '100%');
        setImportant('height', `${Math.max(400, window.innerHeight)}px`);
        stage.style.setProperty('z-index', '4');
        stageState = 'after';
      }
      return;
    }

    refreshVisibleHeaderGeometry();
    setImportant('position', 'fixed');
    setImportant('top', `${visibleHeaderOffset}px`);
    setImportant('bottom', 'auto');
    setImportant('left', `${left}px`);
    setImportant('right', 'auto');
    setImportant('width', `${width}px`);
    setImportant('height', `${activeStageHeight}px`);
    stage.style.setProperty('z-index', '40');
    stageState = 'active';
  }

  function updateStageState() {
    const y = window.scrollY;
    if (y <= startScroll) {
      setStageState('before');
    } else if (y >= endScroll) {
      setStageState('after');
    } else {
      setStageState('active');
    }
  }

  function showStaticSlides() {
    clearStagePosition();
    scrollSection.style.removeProperty('height');
    slides.forEach(slide => {
      slide.classList.add('is-active');
      slide.setAttribute('aria-hidden', 'false');
      slide.style.removeProperty('--slide-opacity');
      slide.style.removeProperty('--slide-y');
      slide.style.removeProperty('--slide-scale');
      slide.style.removeProperty('--slide-blur');
      slide.style.setProperty('--slide-local', '1');
      slide.querySelector('.pitch-slide__inner')?.style.removeProperty('--pitch-fit');
    });
    revealGroups.flat().forEach(item => item.style.setProperty('--reveal', '1'));
  }

  function fitSlides() {
    if (!isDesktop) return;

    slides.forEach(slide => {
      const inner = slide.querySelector('.pitch-slide__inner');
      if (!inner) return;

      inner.style.setProperty('--pitch-fit', '1');
      slide.classList.remove('is-height-fitted');

      const styles = window.getComputedStyle(slide);
      const availableHeight = Math.max(
        260,
        minimumStageHeight - parseFloat(styles.paddingTop || '0') - parseFloat(styles.paddingBottom || '0')
      );
      const contentHeight = Math.max(inner.scrollHeight, inner.getBoundingClientRect().height);
      const fit = clamp(availableHeight / Math.max(1, contentHeight), MIN_FIT_SCALE, 1);

      inner.style.setProperty('--pitch-fit', fit.toFixed(4));
      inner.dataset.pitchFit = fit.toFixed(3);
      slide.classList.toggle('is-height-fitted', fit < .995);
    });
  }

  function updateMetrics() {
    isDesktop = desktopMode.matches && !reducedMotion.matches;
    headerHeight = readHeaderHeight();
    visibleHeaderOffset = readVisibleHeaderOffset();
    minimumStageHeight = Math.max(400, window.innerHeight - headerHeight);
    activeStageHeight = Math.max(400, window.innerHeight - visibleHeaderOffset);

    if (!isDesktop) {
      showStaticSlides();
      return;
    }

    const naturalRect = scrollSection.getBoundingClientRect();
    sectionTop = window.scrollY + naturalRect.top;

    stepPixels = Math.max(MIN_STEP_PX, Math.round(minimumStageHeight * STEP_HEIGHT_MULTIPLIER));
    sectionHeight = minimumStageHeight + stepPixels * slideCount;
    scrollSection.style.setProperty('height', `${sectionHeight}px`, 'important');

    startScroll = Math.max(0, sectionTop - headerHeight);
    endScroll = Math.max(startScroll + 1, sectionTop + sectionHeight - window.innerHeight);

    deck.style.setProperty('--pitch-header-height', `${headerHeight}px`);
    deck.style.setProperty('--pitch-header-visible', `${visibleHeaderOffset}px`);
    updateStageState();
    fitSlides();
    scheduleRender();
  }

  function timelineFromScroll() {
    const distance = clamp(window.scrollY - startScroll, 0, stepPixels * slideCount);
    return distance / stepPixels;
  }

  function positionFromTimeline(value) {
    if (value >= slideCount - 1) return slideCount - 1;

    const index = Math.max(0, Math.floor(value));
    const local = value - index;
    const transition = smoothstep(clamp((local - HOLD_PORTION) / (1 - HOLD_PORTION)));
    return index + transition;
  }

  function revealForItem(slideIndex, itemIndex, timelineValue) {
    if (slideIndex === 0 && timelineValue <= 0) return 1;
    if (timelineValue < slideIndex) return 0;
    if (timelineValue >= slideIndex + HOLD_PORTION) return 1;

    const localHold = clamp((timelineValue - slideIndex) / REVEAL_PORTION);
    const stagger = itemIndex * .075;
    return smoothstep(clamp((localHold - stagger) / Math.max(.18, 1 - stagger)));
  }

  function renderSlide(slide, index, position, timelineValue) {
    const delta = index - position;
    const distance = Math.abs(delta);
    const local = smoothstep(clamp(1 - distance));
    const opacity = clamp(1 - distance * 1.12);
    const visibility = opacity > .01;
    const y = delta * 54;
    const scale = .982 + local * .018;

    slide.style.setProperty('--slide-opacity', opacity.toFixed(4));
    slide.style.setProperty('--slide-y', `${y.toFixed(2)}px`);
    slide.style.setProperty('--slide-scale', scale.toFixed(4));
    slide.style.setProperty('--slide-blur', '0px');
    slide.style.setProperty('--slide-local', local.toFixed(4));
    slide.classList.toggle('is-active', visibility);
    slide.setAttribute('aria-hidden', visibility ? 'false' : 'true');

    revealGroups[index].forEach((item, itemIndex) => {
      item.style.setProperty('--reveal', revealForItem(index, itemIndex, timelineValue).toFixed(4));
    });
  }

  function renderOrb(position) {
    if (!orb) return;
    const lower = Math.floor(position);
    const upper = Math.min(slideCount - 1, lower + 1);
    const amount = smoothstep(position - lower);
    const from = orbFrames[lower] || orbFrames[0];
    const to = orbFrames[upper] || from;
    const pulse = 1 + Math.sin(timeline * Math.PI * 1.2) * .008;

    orb.style.left = `${mix(from.left, to.left, amount).toFixed(2)}%`;
    orb.style.top = `${mix(from.top, to.top, amount).toFixed(2)}%`;
    orb.style.opacity = mix(from.opacity, to.opacity, amount).toFixed(3);
    orb.style.transform = `translate(-50%, -50%) scale(${(mix(from.scale, to.scale, amount) * pulse).toFixed(4)}) rotate(${mix(from.rotation, to.rotation, amount).toFixed(2)}deg)`;
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
    rafId = 0;
    if (!isDesktop) return;

    updateStageState();
    timeline = timelineFromScroll();
    const position = positionFromTimeline(timeline);
    const nextActive = Math.min(slideCount - 1, Math.max(0, Math.round(position)));
    const normalizedProgress = clamp(timeline / slideCount);

    deck.style.setProperty('--deck-progress', normalizedProgress.toFixed(5));
    deck.style.setProperty('--deck-slide', position.toFixed(4));
    deck.style.setProperty('--deck-timeline', timeline.toFixed(4));
    slides.forEach((slide, index) => renderSlide(slide, index, position, timeline));
    renderOrb(position);
    setActive(nextActive);
  }

  function scheduleRender() {
    if (!rafId) rafId = requestAnimationFrame(render);
  }

  function jumpTo(index, behavior = 'smooth') {
    const safeIndex = Math.min(slideCount - 1, Math.max(0, index));
    if (!isDesktop) {
      slides[safeIndex]?.scrollIntoView({ behavior, block: 'start' });
      return;
    }

    const holdAnchor = safeIndex === 0 ? 0 : .12;
    const target = startScroll + (safeIndex + holdAnchor) * stepPixels;
    window.scrollTo({ top: Math.min(endScroll, target), behavior });
  }

  function deckIsInView() {
    const rect = scrollSection.getBoundingClientRect();
    return rect.bottom > visibleHeaderOffset && rect.top < window.innerHeight;
  }

  function onScroll() {
    scheduleRender();
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

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', updateMetrics, { passive: true });
  window.addEventListener('orientationchange', updateMetrics, { passive: true });
  window.addEventListener('load', updateMetrics, { once: true });
  reducedMotion.addEventListener?.('change', updateMetrics);
  desktopMode.addEventListener?.('change', updateMetrics);
  runtimeCss?.addEventListener?.('load', updateMetrics, { once: true });

  if (document.fonts?.ready) {
    document.fonts.ready.then(updateMetrics).catch(() => {});
  }
  deck.querySelectorAll('img').forEach(image => {
    if (!image.complete) image.addEventListener('load', updateMetrics, { once: true });
  });

  updateMetrics();
})();
