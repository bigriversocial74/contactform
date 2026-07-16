(function (window, document) {
  'use strict';

  function q(selector, context) {
    return (context || document).querySelector(selector);
  }

  function setCreateExpanded(value) {
    document.querySelectorAll('[data-create-menu-trigger],[data-global-create],[data-header-create],[data-quick-create],[data-app-create],[data-product-header-create],.mg-header-build-link').forEach(function (trigger) {
      trigger.setAttribute('aria-expanded', value ? 'true' : 'false');
    });
  }

  function embeddedPostNodes() {
    var menu = q('[data-create-menu]');
    var view = menu && q('[data-create-center-view="post"]', menu);
    return menu && view ? { menu: menu, view: view } : null;
  }

  function activateEmbeddedPost(nodes, shouldFocus) {
    if (!nodes) return false;

    var menu = nodes.menu;
    var postView = nodes.view;
    menu.dataset.createPostActive = 'true';
    menu.hidden = false;
    menu.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-create-menu-open');
    document.body.classList.remove('mg-post-composer-open');
    setCreateExpanded(true);

    menu.querySelectorAll('[data-create-center-view]').forEach(function (view) {
      var active = view === postView;
      view.hidden = !active;
      view.setAttribute('aria-hidden', active ? 'false' : 'true');
      view.classList.toggle('is-active', active);
    });

    menu.querySelectorAll('[data-create-tool-key]').forEach(function (tool) {
      tool.classList.toggle('is-active', tool.dataset.createToolKey === 'post');
    });

    var home = q('[data-create-center-home]', menu);
    if (home) home.classList.remove('is-active');

    var title = q('[data-create-center-title]', menu);
    var description = q('[data-create-center-description]', menu);
    if (title) title.textContent = 'Create a post';
    if (description) description.textContent = 'Publish a polished update with photos, video, audio, links, or connected Microgifter content.';

    var content = q('.mg-create-center-content', menu);
    if (content) content.scrollTop = 0;

    if (shouldFocus !== false) {
      window.requestAnimationFrame(function () {
        var first = q('input[name="headline"],textarea[name="body"]', postView);
        if (first && typeof first.focus === 'function') first.focus({ preventScroll: true });
      });
    }

    return true;
  }

  function openEmbeddedPost() {
    var nodes = embeddedPostNodes();
    if (!nodes) return false;

    activateEmbeddedPost(nodes, true);
    window.setTimeout(function () { activateEmbeddedPost(nodes, false); }, 0);
    window.requestAnimationFrame(function () { activateEmbeddedPost(nodes, false); });
    return true;
  }

  function closeCreateMenus() {
    document.querySelectorAll('[data-create-menu]').forEach(function (menu) {
      menu.hidden = true;
      menu.setAttribute('aria-hidden', 'true');
      delete menu.dataset.createPostActive;
    });
    document.body.classList.remove('mg-create-menu-open');
    setCreateExpanded(false);
  }

  function forceLegacyComposerVisible() {
    var modal = q('[data-global-post-composer]');
    if (!modal) return false;

    var dialog = q('.mg-post-composer-dialog', modal);
    var backdrop = q('.mg-post-composer-backdrop', modal);
    var composer = q('[data-post-composer]', modal);
    var form = q('[data-post-form]', modal);

    closeCreateMenus();
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

  function forceComposerVisible() {
    if (openEmbeddedPost()) return true;
    return forceLegacyComposerVisible();
  }

  document.addEventListener('click', function (event) {
    var postOption = event.target && event.target.closest ? event.target.closest('[data-create-menu-option="post"],[data-create-inline-target="post"]') : null;
    if (!postOption) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    forceComposerVisible();
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
