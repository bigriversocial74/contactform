document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;

  var params = new URLSearchParams(window.location.search);
  var source = String(params.get('source') || '').trim();
  var prompt = String(params.get('prompt') || '').trim();
  if (source !== 'personal-agent') return;

  var form = root.querySelector('[data-agent-chat-form]');
  var textarea = form && form.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
  if (!form || !textarea) return;

  var note = root.querySelector('[data-merchant-agent-handoff-note]');
  if (note) {
    note.hidden = false;
    note.textContent = prompt ? 'Continued from Personal Agent.' : 'Merchant mode opened from Personal Agent.';
  }

  if (prompt) {
    textarea.value = prompt;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  window.setTimeout(function () {
    if (prompt) form.requestSubmit();
    else textarea.focus({ preventScroll: true });

    var clean = new URL(window.location.href);
    clean.searchParams.delete('prompt');
    clean.searchParams.delete('source');
    window.history.replaceState({}, '', clean.pathname + clean.search + clean.hash);
  }, 350);
});
