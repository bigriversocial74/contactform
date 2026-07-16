document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var modal = document.querySelector('[data-agent-sidebar-tools-modal]');
  if (!modal) return;

  var pendingKey = 'microgifter.agent.quickPrompt.v1';
  var currentMode = String(modal.getAttribute('data-agent-tools-mode') || 'personal');
  var dialog = modal.querySelector('.mg-agent-sidebar-tools-dialog');
  var openers = Array.prototype.slice.call(document.querySelectorAll('[data-agent-suggestions-open]'));
  var closers = Array.prototype.slice.call(modal.querySelectorAll('[data-agent-suggestions-close]'));
  var tabs = Array.prototype.slice.call(modal.querySelectorAll('[data-agent-tools-tab]'));
  var panels = Array.prototype.slice.call(modal.querySelectorAll('[data-agent-tools-panel]'));
  var lastOpener = null;

  function composerFor(mode) {
    if (mode === 'merchant') {
      var merchantForm = document.querySelector('[data-agent-chat-form]');
      return merchantForm ? {
        form: merchantForm,
        input: merchantForm.querySelector('[data-agent-chat-textarea],textarea[name="message"]')
      } : null;
    }

    var personalForm = document.querySelector('[data-personal-agent-composer]');
    return personalForm ? {
      form: personalForm,
      input: personalForm.querySelector('textarea,input[type="text"]')
    } : null;
  }

  function targetUrl(mode) {
    return mode === 'merchant' ? '/merchant-agent-chat.php' : '/agent.php';
  }

  function savePending(mode, prompt, submit) {
    try {
      sessionStorage.setItem(pendingKey, JSON.stringify({
        mode: mode,
        prompt: prompt,
        submit: !!submit,
        createdAt: Date.now()
      }));
    } catch (error) {
      // Session storage may be unavailable in strict privacy modes. Routing still works.
    }
  }

  function dispatchInput(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function applyPrompt(mode, prompt, submit, allowRoute) {
    prompt = String(prompt || '');
    if (!prompt.trim()) return false;

    var composer = composerFor(mode);
    if (!composer || !composer.form || !composer.input) {
      if (allowRoute !== false) {
        savePending(mode, prompt, submit);
        window.location.assign(targetUrl(mode));
      }
      return false;
    }

    composer.input.value = prompt;
    dispatchInput(composer.input);
    closeModal();
    composer.input.focus();
    if (typeof composer.input.setSelectionRange === 'function') {
      composer.input.setSelectionRange(composer.input.value.length, composer.input.value.length);
    }

    if (submit) {
      window.setTimeout(function () {
        if (typeof composer.form.requestSubmit === 'function') composer.form.requestSubmit();
        else composer.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      }, 60);
    }
    return true;
  }

  function selectTab(name, focus) {
    name = name === 'keywords' ? 'keywords' : 'suggestions';
    tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-agent-tools-tab') === name;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      if (active && focus) tab.focus();
    });
    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-agent-tools-panel') !== name;
    });
  }

  function openModal(opener, tabName) {
    lastOpener = opener || document.activeElement;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-agent-sidebar-tools-open');
    selectTab(tabName || 'suggestions', false);
    window.setTimeout(function () {
      var selected = modal.querySelector('[data-agent-tools-tab][aria-selected="true"]');
      if (selected) selected.focus();
    }, 0);
  }

  function closeModal() {
    if (modal.getAttribute('aria-hidden') === 'true') return;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-agent-sidebar-tools-open');
    if (lastOpener && typeof lastOpener.focus === 'function') lastOpener.focus();
  }

  function focusableElements() {
    return Array.prototype.slice.call(dialog.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter(function (node) { return !node.hidden && node.offsetParent !== null; });
  }

  openers.forEach(function (opener) {
    opener.addEventListener('click', function () {
      openModal(opener, opener.getAttribute('data-agent-suggestions-tab') || 'suggestions');
    });
  });

  closers.forEach(function (closer) {
    closer.addEventListener('click', closeModal);
  });

  tabs.forEach(function (tab, index) {
    tab.addEventListener('click', function () {
      selectTab(tab.getAttribute('data-agent-tools-tab') || 'suggestions', false);
    });
    tab.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      var next = event.key === 'ArrowRight' ? index + 1 : index - 1;
      if (next < 0) next = tabs.length - 1;
      if (next >= tabs.length) next = 0;
      selectTab(tabs[next].getAttribute('data-agent-tools-tab') || 'suggestions', true);
    });
  });

  modal.addEventListener('click', function (event) {
    var suggestion = event.target.closest('[data-agent-suggestion-prompt]');
    if (suggestion) {
      applyPrompt(
        suggestion.getAttribute('data-agent-target-mode') || currentMode,
        suggestion.getAttribute('data-agent-suggestion-prompt') || '',
        true,
        true
      );
      return;
    }

    var keyword = event.target.closest('[data-agent-keyword-prompt]');
    if (keyword) {
      applyPrompt(
        keyword.getAttribute('data-agent-target-mode') || currentMode,
        keyword.getAttribute('data-agent-keyword-prompt') || '',
        false,
        true
      );
    }
  });

  document.addEventListener('keydown', function (event) {
    if (modal.getAttribute('aria-hidden') === 'true') return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }
    if (event.key !== 'Tab') return;
    var items = focusableElements();
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  selectTab('suggestions', false);

  window.setTimeout(function () {
    var pending = null;
    try {
      pending = JSON.parse(sessionStorage.getItem(pendingKey) || 'null');
    } catch (error) {
      pending = null;
    }
    if (!pending || pending.mode !== currentMode || !pending.prompt) return;
    if (Number(pending.createdAt || 0) < Date.now() - 5 * 60 * 1000) {
      try { sessionStorage.removeItem(pendingKey); } catch (error) {}
      return;
    }
    if (!composerFor(currentMode)) return;
    try { sessionStorage.removeItem(pendingKey); } catch (error) {}
    applyPrompt(currentMode, String(pending.prompt), !!pending.submit, false);
  }, 550);
});
