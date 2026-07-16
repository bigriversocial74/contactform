window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var modal = document.querySelector('[data-create-menu]');
  var postView = modal && modal.querySelector('[data-create-center-view="post"]');
  if (!modal || !postView) return;

  var postActive = false;
  var focusQueued = false;

  function setExpanded(value) {
    document.querySelectorAll('[data-create-menu-trigger],[data-global-create],[data-header-create],[data-quick-create],[data-app-create],[data-product-header-create],.mg-header-build-link').forEach(function (trigger) {
      trigger.setAttribute('aria-expanded', value ? 'true' : 'false');
    });
  }

  function setHeader() {
    var title = modal.querySelector('[data-create-center-title]');
    var description = modal.querySelector('[data-create-center-description]');
    if (title) title.textContent = 'Create a post';
    if (description) description.textContent = 'Publish a polished update with photos, video, audio, links, or connected Microgifter content.';
  }

  function focusComposer() {
    if (focusQueued) return;
    focusQueued = true;
    window.requestAnimationFrame(function () {
      focusQueued = false;
      if (!postActive || modal.hidden) return;
      var field = postView.querySelector('input[name="headline"],textarea[name="body"]');
      if (field && typeof field.focus === 'function') field.focus({ preventScroll: true });
    });
  }

  function activatePostView(shouldFocus) {
    if (!postActive || modal.hidden) return;

    modal.querySelectorAll('[data-create-center-view]').forEach(function (view) {
      var active = view.dataset.createCenterView === 'post';
      view.hidden = !active;
      view.setAttribute('aria-hidden', active ? 'false' : 'true');
      view.classList.toggle('is-active', active);
    });

    modal.querySelectorAll('[data-create-tool-key]').forEach(function (tool) {
      tool.classList.toggle('is-active', tool.dataset.createToolKey === 'post');
    });

    var home = modal.querySelector('[data-create-center-home]');
    if (home) home.classList.remove('is-active');

    setHeader();

    var content = modal.querySelector('.mg-create-center-content');
    if (content) content.scrollTop = 0;
    if (shouldFocus !== false) focusComposer();
  }

  function queuePostView(shouldFocus) {
    window.setTimeout(function () {
      activatePostView(shouldFocus);
    }, 0);
    window.requestAnimationFrame(function () {
      activatePostView(false);
    });
  }

  function openPostView() {
    postActive = true;
    modal.dataset.createPostActive = 'true';
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-create-menu-open');
    setExpanded(true);
    activatePostView(true);
    queuePostView(false);
  }

  function leavePostView() {
    postActive = false;
    delete modal.dataset.createPostActive;
  }

  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target : null;
    if (!target) return;

    var postOption = target.closest('[data-create-inline-target="post"],[data-create-menu-option="post"]');
    if (postOption && modal.contains(postOption)) {
      event.preventDefault();
      openPostView();
      return;
    }

    var close = target.closest('[data-create-menu-close]');
    if (close && modal.contains(close)) {
      leavePostView();
      return;
    }

    var otherTool = target.closest('[data-create-inline-target]:not([data-create-inline-target="post"]),[data-create-center-home]');
    if (otherTool && modal.contains(otherTool)) leavePostView();
  }, true);

  window.addEventListener('microgifter:openPostComposer', function () {
    openPostView();
  });

  new MutationObserver(function () {
    if (modal.hidden) {
      if (modal.getAttribute('aria-hidden') === 'true') leavePostView();
      return;
    }
    if (postActive || modal.dataset.createPostActive === 'true') queuePostView(false);
  }).observe(modal, { attributes: true, attributeFilter: ['hidden', 'aria-hidden'] });
})(window, document);
