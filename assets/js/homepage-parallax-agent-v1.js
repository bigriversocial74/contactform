document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('.mg-ph-main');
  if (!root || window.__mgHomepageParallaxAgentV1Booted) return;
  window.__mgHomepageParallaxAgentV1Booted = true;

  var hero = root.querySelector('.mg-ph-hero-scroll');
  var scene = root.querySelector('[data-ph-scene]');
  var orb = root.querySelector('[data-ph-orb]');
  var copyOne = root.querySelector('[data-ph-copy-one]');
  var copyTwo = root.querySelector('[data-ph-copy-two]');
  var growth = root.querySelector('[data-ph-growth]');
  var mountainsBack = root.querySelector('.mg-ph-mountains-back');
  var mountainsFront = root.querySelector('.mg-ph-mountains-front');
  var foreground = root.querySelector('.mg-ph-foreground');
  var phaseDots = Array.prototype.slice.call(root.querySelectorAll('.mg-ph-phase span'));
  var chartLines = Array.prototype.slice.call(root.querySelectorAll('.mg-ph-line'));
  var chartArea = root.querySelector('.mg-ph-chart-area');
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  function clamp(value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
  }

  function mix(start, end, progress) {
    return start + (end - start) * progress;
  }

  function smoothstep(start, end, value) {
    var progress = clamp((value - start) / Math.max(0.0001, end - start), 0, 1);
    return progress * progress * (3 - (2 * progress));
  }

  function chapterOpacity(progress, enterStart, enterEnd, exitStart, exitEnd) {
    return smoothstep(enterStart, enterEnd, progress) * (1 - smoothstep(exitStart, exitEnd, progress));
  }

  function setPhase(index) {
    phaseDots.forEach(function (dot, dotIndex) {
      dot.classList.toggle('is-active', dotIndex === index);
    });
  }

  function render(progress) {
    if (!scene || reducedMotion.matches) return;

    var opening = smoothstep(0.02, 0.2, progress);
    var transition = smoothstep(0.28, 0.52, progress);
    var growthEnter = smoothstep(0.68, 0.82, progress);
    var growthExit = smoothstep(0.94, 1, progress);

    if (orb) {
      var orbLeft = progress < 0.55
        ? mix(66, 49, smoothstep(0.08, 0.55, progress))
        : mix(49, 52, smoothstep(0.55, 0.95, progress));
      var orbTop = progress < 0.58
        ? mix(46, 68, smoothstep(0.05, 0.58, progress))
        : mix(68, 84, smoothstep(0.58, 0.95, progress));
      var orbScale = progress < 0.42
        ? mix(0.13, 0.86, smoothstep(0.01, 0.42, progress))
        : mix(0.86, 1.12, smoothstep(0.42, 0.95, progress));
      var orbBlur = mix(0, 1.4, smoothstep(0.78, 1, progress));
      orb.style.left = orbLeft.toFixed(3) + '%';
      orb.style.top = orbTop.toFixed(3) + '%';
      orb.style.transform = 'translate(-50%, -50%) scale(' + orbScale.toFixed(4) + ')';
      orb.style.filter = 'blur(' + orbBlur.toFixed(2) + 'px)';
      orb.style.opacity = String(clamp(1 - (growthExit * 0.62), 0.25, 1));
    }

    if (mountainsBack) {
      mountainsBack.style.transform = 'translate3d(0,' + mix(8, 17, progress).toFixed(2) + '%,0) scale(' + mix(1.08, 1.27, progress).toFixed(4) + ')';
    }
    if (mountainsFront) {
      mountainsFront.style.transform = 'translate3d(0,' + mix(6, 23, progress).toFixed(2) + '%,0) scale(' + mix(1.04, 1.38, progress).toFixed(4) + ')';
    }
    if (foreground) {
      foreground.style.transform = 'translate3d(0,' + mix(0, 34, progress).toFixed(2) + '%,0) scale(' + mix(1, 1.46, progress).toFixed(4) + ')';
      foreground.style.opacity = String(clamp(1 - smoothstep(0.76, 1, progress), 0, 1));
    }

    if (copyOne) {
      var copyOneOpacity = 1 - smoothstep(0.27, 0.42, progress);
      copyOne.style.opacity = String(copyOneOpacity);
      copyOne.style.transform = 'translateY(calc(-50% + ' + mix(0, -42, transition).toFixed(2) + 'px))';
      copyOne.style.pointerEvents = copyOneOpacity > 0.2 ? 'auto' : 'none';
      copyOne.setAttribute('aria-hidden', copyOneOpacity > 0.1 ? 'false' : 'true');
    }

    if (copyTwo) {
      var copyTwoOpacity = chapterOpacity(progress, 0.34, 0.5, 0.65, 0.76);
      copyTwo.style.opacity = String(copyTwoOpacity);
      copyTwo.style.transform = 'translateY(calc(-50% + ' + mix(34, -28, smoothstep(0.34, 0.76, progress)).toFixed(2) + 'px))';
      copyTwo.style.pointerEvents = copyTwoOpacity > 0.2 ? 'auto' : 'none';
      copyTwo.setAttribute('aria-hidden', copyTwoOpacity > 0.1 ? 'false' : 'true');
    }

    if (growth) {
      var growthOpacity = growthEnter * (1 - growthExit);
      growth.style.visibility = growthOpacity > 0.01 ? 'visible' : 'hidden';
      growth.style.opacity = String(growthOpacity);
      growth.style.transform = 'translateY(' + mix(34, -16, smoothstep(0.68, 1, progress)).toFixed(2) + 'px)';
      growth.style.pointerEvents = growthOpacity > 0.2 ? 'auto' : 'none';
      growth.setAttribute('aria-hidden', growthOpacity > 0.1 ? 'false' : 'true');
    }

    var chartProgress = smoothstep(0.74, 0.93, progress);
    chartLines.forEach(function (line, index) {
      var stagger = clamp((chartProgress - (index * 0.035)) / Math.max(0.01, 1 - (index * 0.035)), 0, 1);
      line.style.strokeDashoffset = String(1 - stagger);
      line.style.opacity = String(mix(0.18, 1, stagger));
    });
    if (chartArea) chartArea.style.opacity = String(chartProgress * 0.9);

    if (progress < 0.34) setPhase(0);
    else if (progress < 0.7) setPhase(1);
    else setPhase(2);
  }

  var current = 0;
  var target = 0;
  var frame = 0;

  function measure() {
    if (!hero || reducedMotion.matches) return;
    var rect = hero.getBoundingClientRect();
    var distance = Math.max(1, hero.offsetHeight - window.innerHeight);
    target = clamp(-rect.top / distance, 0, 1);
    if (!frame) frame = window.requestAnimationFrame(tick);
  }

  function tick() {
    current += (target - current) * 0.11;
    if (Math.abs(target - current) < 0.0004) current = target;
    render(current);
    if (Math.abs(target - current) >= 0.0004) frame = window.requestAnimationFrame(tick);
    else frame = 0;
  }

  function initializeChart() {
    chartLines.forEach(function (line) {
      line.style.strokeDasharray = '1';
      line.style.strokeDashoffset = '1';
    });
  }

  function initializeReveal() {
    var revealNodes = Array.prototype.slice.call(root.querySelectorAll('.mg-ph-reveal'));
    if (reducedMotion.matches || !('IntersectionObserver' in window)) {
      revealNodes.forEach(function (node) { node.classList.add('is-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });

    revealNodes.forEach(function (node) { observer.observe(node); });
  }

  function applyMotionPreference() {
    if (reducedMotion.matches) {
      if (frame) window.cancelAnimationFrame(frame);
      frame = 0;
      root.querySelectorAll('.mg-ph-reveal').forEach(function (node) { node.classList.add('is-visible'); });
      return;
    }
    initializeChart();
    measure();
  }

  initializeChart();
  initializeReveal();
  render(0);
  measure();

  window.addEventListener('scroll', measure, { passive: true });
  window.addEventListener('resize', measure, { passive: true });
  if (typeof reducedMotion.addEventListener === 'function') reducedMotion.addEventListener('change', applyMotionPreference);
  else if (typeof reducedMotion.addListener === 'function') reducedMotion.addListener(applyMotionPreference);
});
