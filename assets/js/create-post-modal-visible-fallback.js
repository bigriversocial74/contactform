(function (window, document) {
  'use strict';

  function q(selector, context) {
    return (context || document).querySelector(selector);
  }

  function closeCreateMenus() {
    document.querySelectorAll('[data-create-menu]').forEach(function (menu) {
      menu.hidden = true;
      menu.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('mg-create-menu-open');
    document.querySelectorAll('[data-create-menu-trigger]').forEach(function (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    });
  }

  function forceComposerVisible() {
    var modal = q('[data-global-post-composer]');
    if (!modal) return false;

    var dialog = q('.mg-post-composer-dialog', modal);
    var backdrop = q('.mg-post-composer-backdrop', modal);
    var composer = q('[data-post-composer]', modal);
    var form = q('[data-post-form]', modal);

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.style.setProperty('display', 'grid', 'important');
    modal.style.setProperty('position', 'fixed', 'important');
    modal.style.setProperty('inset', '0', 'important');
    modal.style.setProperty('z-index', '2147483000', 'important');
    modal.style.setProperty('place-items', 'center', 'important');
    modal.style.setProperty('padding', 'clamp(1rem,3vw,2rem)', 'important');
    modal.style.setProperty('isolation', 'isolate', 'important');
    document.body.classList.add('mg-post-composer-open');

    if (backdrop) {
      backdrop.style.setProperty('position', 'absolute', 'important');
      backdrop.style.setProperty('inset', '0', 'important');
      backdrop.style.setProperty('z-index', '0', 'important');
      backdrop.style.setProperty('pointer-events', 'auto', 'important');
      backdrop.style.setProperty('background', 'rgba(15,23,42,.58)', 'important');
    }

    if (dialog) {
      dialog.hidden = false;
      dialog.removeAttribute('aria-hidden');
      dialog.style.setProperty('display', 'block', 'important');
      dialog.style.setProperty('visibility', 'visible', 'important');
      dialog.style.setProperty('opacity', '1', 'important');
      dialog.style.setProperty('position', 'relative', 'important');
      dialog.style.setProperty('z-index', '3', 'important');
      dialog.style.setProperty('width', 'min(980px,calc(100vw - 2rem))', 'important');
      dialog.style.setProperty('max-height', 'min(88vh,980px)', 'important');
      dialog.style.setProperty('overflow', 'auto', 'important');
      dialog.style.setProperty('background', '#fff', 'important');
      dialog.style.setProperty('border-radius', '28px', 'important');
      dialog.style.setProperty('box-shadow', '0 32px 90px rgba(15,23,42,.28)', 'important');
    }

    if (composer) {
      composer.hidden = false;
      composer.classList.remove('mg-hidden');
      composer.style.setProperty('display', 'block', 'important');
      composer.style.setProperty('visibility', 'visible', 'important');
      composer.style.setProperty('opacity', '1', 'important');
    }

    if (form) {
      form.hidden = false;
      form.style.setProperty('display', 'grid', 'important');
      form.style.setProperty('visibility', 'visible', 'important');
      form.style.setProperty('opacity', '1', 'important');
    }

    window.setTimeout(function () {
      var first = q('input[name="headline"]', modal) || dialog;
      if (first && typeof first.focus === 'function') first.focus();
    }, 50);

    return true;
  }

  document.addEventListener('click', function (event) {
    var postOption = event.target && event.target.closest ? event.target.closest('[data-create-menu-option="post"]') : null;
    if (!postOption) return;
    event.preventDefault();
    event.stopPropagation();
    closeCreateMenus();
    forceComposerVisible();
    window.setTimeout(forceComposerVisible, 75);
    window.setTimeout(forceComposerVisible, 250);
  }, true);

  document.addEventListener('click', function (event) {
    if (!event.target || !event.target.closest) return;
    if (!event.target.closest('[data-global-post-composer-close],[data-composer-close]')) return;
    var modal = q('[data-global-post-composer]');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    modal.style.removeProperty('display');
    document.body.classList.remove('mg-post-composer-open');
  }, true);

  window.addEventListener('microgifter:openPostComposer', function () {
    window.setTimeout(forceComposerVisible, 0);
    window.setTimeout(forceComposerVisible, 100);
  });

  window.Microgifter = window.Microgifter || {};
  window.Microgifter.forcePostComposerVisible = forceComposerVisible;
})(window, document);
