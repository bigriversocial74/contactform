document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var body = document.body;
  if (!body || body.getAttribute('data-page-id') !== 'stamp-card') return;

  var wrap = document.querySelector('.mg-rl-stamp .mg-rl-wrap');
  var player = document.querySelector('.mg-rl-stamp .mg-rl-player');
  var sidebar = document.querySelector('.mg-rl-stamp .mg-rl-join-desktop');
  var desktop = window.matchMedia('(min-width: 1181px)');

  if (!wrap || !player || !sidebar) return;

  function alignSidebar() {
    if (!desktop.matches) {
      sidebar.style.removeProperty('margin-top');
      return;
    }

    sidebar.style.marginTop = '0px';
    var wrapTop = wrap.getBoundingClientRect().top;
    var playerTop = player.getBoundingClientRect().top;
    var offset = Math.max(0, Math.round(playerTop - wrapTop));
    sidebar.style.marginTop = offset + 'px';
  }

  var queued = false;
  function queueAlignment() {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(function () {
      queued = false;
      alignSidebar();
    });
  }

  alignSidebar();
  window.addEventListener('load', queueAlignment, { once: true });
  window.addEventListener('resize', queueAlignment, { passive: true });
  desktop.addEventListener('change', queueAlignment);

  if ('ResizeObserver' in window) {
    var observer = new ResizeObserver(queueAlignment);
    observer.observe(wrap);
    observer.observe(player);
    var hero = document.querySelector('.mg-rl-stamp .mg-rl-hero');
    if (hero) observer.observe(hero);
  }

  document.querySelectorAll('.mg-rl-stamp [data-campaign-user-details]').forEach(function (details) {
    details.addEventListener('toggle', queueAlignment);
  });
});
