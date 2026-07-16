document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  var sidebar = document.querySelector('[data-personal-agent-chat-sidebar][data-agent-sidebar-mode="merchant"]');
  var host = sidebar && sidebar.querySelector('[data-merchant-agent-thread-groups]');
  var select = root && root.querySelector('[data-agent-thread-select]');
  var internalNew = root && root.querySelector('[data-agent-new-thread]');
  var sidebarNew = sidebar && sidebar.querySelector('[data-merchant-agent-new-chat]');
  if (!root || !sidebar || !host || !select) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function cleanLabel(value) {
    return String(value || 'Current chat').replace(/\s+·\s+(active|saved|archived)$/i, '').trim() || 'Current chat';
  }

  function render() {
    var options = Array.prototype.slice.call(select.options || []);
    if (!options.length) {
      host.innerHTML = '<div class="mg-personal-chat-empty">No merchant chats yet. Start a new conversation.</div>';
      return;
    }
    var active = String(select.value || '');
    host.innerHTML = '<section class="mg-personal-chat-group"><h3>Merchant chats</h3>' + options.map(function (option) {
      var id = String(option.value || '');
      var title = cleanLabel(option.textContent);
      var state = /archived/i.test(option.textContent || '') ? 'Archived' : (/saved/i.test(option.textContent || '') ? 'Saved' : 'Open');
      return '<article class="mg-personal-chat-row' + (id === active ? ' is-active' : '') + '" data-merchant-agent-thread-row="' + esc(id) + '">'
        + '<button class="mg-personal-chat-open" type="button" data-merchant-agent-open-thread="' + esc(id) + '">'
        + '<strong>' + esc(title) + '</strong><span>' + esc(state) + '</span></button>'
        + '</article>';
    }).join('') + '</section>';
  }

  sidebar.addEventListener('click', function (event) {
    var newChat = event.target.closest('[data-merchant-agent-new-chat]');
    if (newChat) {
      event.preventDefault();
      if (internalNew) internalNew.click();
      return;
    }
    var open = event.target.closest('[data-merchant-agent-open-thread]');
    if (!open) return;
    event.preventDefault();
    var id = open.getAttribute('data-merchant-agent-open-thread') || '';
    select.value = id;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    window.setTimeout(render, 120);
  });

  select.addEventListener('change', function () { window.setTimeout(render, 0); });
  if ('MutationObserver' in window) {
    new MutationObserver(render).observe(select, { childList: true, subtree: true, attributes: true });
  }
  if (sidebarNew && !internalNew) sidebarNew.disabled = true;
  render();
});
