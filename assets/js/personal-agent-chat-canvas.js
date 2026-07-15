document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent;
  if (!app || !app.root) return;

  var root = app.root;
  var greeting = root.querySelector('.mg-personal-agent-intro-greeting');
  if (greeting) {
    var hour = new Date().getHours();
    var salutation = hour < 12 ? 'Good morning' : (hour < 18 ? 'Good afternoon' : 'Good evening');
    var displayName = String(root.getAttribute('data-display-name') || '').trim();
    greeting.textContent = salutation + (displayName ? ', ' + displayName : '') + '.';
  }

  var contextChip = root.querySelector('[data-personal-agent-context-chip]');
  if (contextChip) {
    contextChip.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (typeof app.showContext === 'function') {
        app.showContext({ type: 'none', id: '', name: '', details: {} });
      }
      if (typeof app.setStatus === 'function') {
        app.setStatus('Selected context cleared.', 'success');
      }
    });
  }

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-personal-agent-new-thread]');
    if (!button) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    app.state.threadId = '';

    if (app.ui.feed) {
      app.ui.feed.innerHTML = '';
      var message = document.createElement('div');
      message.className = 'mg-personal-agent-message is-assistant is-thread-start';
      message.textContent = 'New personal gifting conversation started.';
      app.ui.feed.appendChild(message);
    }

    if (typeof app.setStatus === 'function') app.setStatus('');

    var input = app.ui.composer && app.ui.composer.querySelector('textarea,input');
    if (input) input.focus();
  }, true);
});
