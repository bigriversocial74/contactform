(() => {
  'use strict';

  const root = document.querySelector('[data-homepage-core-v1]');
  if (!root) return;

  const lead = root.querySelector('.hero-title-lead');
  const arrived = root.querySelector('.hero-title-arrived');
  if (lead) lead.textContent = 'Microgifter Is the Future';
  if (arrived) arrived.textContent = 'of Social Gifting.';

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const chapters = [...root.querySelectorAll('[data-core-chapter]')];
  const revealItems = [...root.querySelectorAll('[data-core-reveal]')];
  const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));

  if (!('IntersectionObserver' in window) || reducedMotion) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  } else {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: .14 });
    revealItems.forEach((item) => revealObserver.observe(item));
  }

  if (reducedMotion || window.innerWidth <= 980 || !chapters.length) {
    chapters.forEach((chapter) => {
      chapter.style.setProperty('--chapter-progress', '1');
      chapter.querySelectorAll('[data-core-step]').forEach((step) => step.classList.add('is-active'));
    });
    return;
  }

  let frame = 0;

  const updateChapter = (chapter) => {
    const rect = chapter.getBoundingClientRect();
    const distance = Math.max(1, chapter.offsetHeight - window.innerHeight);
    const progress = clamp(-rect.top / distance);
    chapter.style.setProperty('--chapter-progress', progress.toFixed(4));

    const steps = [...chapter.querySelectorAll('[data-core-step]')];
    if (steps.length) {
      const scaled = progress * steps.length;
      const activeIndex = Math.min(steps.length - 1, Math.floor(scaled));
      steps.forEach((step, index) => {
        step.classList.toggle('is-active', index === activeIndex);
        step.classList.toggle('is-past', index < activeIndex);
        step.style.setProperty('--step-progress', clamp(scaled - index).toFixed(4));
      });
    }

    const track = chapter.querySelector('[data-core-track]');
    if (track) {
      const viewport = Math.max(1, chapter.querySelector('.mg-core-pin')?.clientWidth || window.innerWidth);
      const available = Math.max(0, track.scrollWidth - viewport + Math.min(180, viewport * .12));
      track.style.setProperty('--track-x', `${(-available * progress).toFixed(1)}px`);
    }
  };

  const render = () => {
    frame = 0;
    chapters.forEach(updateChapter);
  };

  const requestRender = () => {
    if (!frame) frame = requestAnimationFrame(render);
  };

  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', requestRender, { passive: true });
  render();
})();
