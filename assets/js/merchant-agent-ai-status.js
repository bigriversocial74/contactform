document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;
  var banner = root.querySelector('[data-merchant-agent-ai-status-banner]');
  var label = banner && banner.querySelector('[data-merchant-agent-ai-status-label]');
  var message = banner && banner.querySelector('[data-merchant-agent-ai-status-message]');
  var manage = banner && banner.querySelector('[data-merchant-agent-ai-manage]');
  var form = root.querySelector('[data-agent-chat-form]');
  var status = root.querySelector('[data-agent-chat-status]');

  function isSystematic(text) {
    text = String(text || '').trim().replace(/\s+/g, ' ');
    return /^(?:\/?snapshot|current snapshot|merchant snapshot)(?:\s+(?:7|14|30|60|90|180|365)(?:\s+days?)?)?$/i.test(text)
      || /^(?:\/?ai report)(?:\s+(?:details|alerts|recent))?(?:\s+(?:7|14|30|60|90|180|365)(?:\s+days?)?)?$/i.test(text)
      || /^@[a-z0-9][a-z0-9._-]{0,119}$/i.test(text);
  }

  function update(aiStatus) {
    if (!aiStatus || typeof aiStatus !== 'object') return;
    var key = String(aiStatus.key || 'unavailable');
    root.dataset.merchantAgentAiCanGenerate = aiStatus.can_generate ? 'true' : 'false';
    root.dataset.merchantAgentAiStatus = key;
    root.dataset.merchantAgentAiMessage = String(aiStatus.message || '');
    if (!banner) return;
    banner.className = 'mg-merchant-agent-ai-status is-' + key.replace(/[^a-z0-9_-]/gi, '');
    if (label) label.textContent = aiStatus.label || 'AI unavailable';
    if (message) message.textContent = aiStatus.message || 'Systematic Merchant Agent tools remain available.';
    if (manage) {
      manage.href = aiStatus.manage_url || root.dataset.merchantAgentAiManageUrl || '/account-subscriptions.php?agent=merchant';
      manage.hidden = !!aiStatus.can_generate;
    }
  }

  if (form) form.addEventListener('submit', function (event) {
    var textarea = form.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
    var text = textarea ? textarea.value : '';
    if (root.dataset.merchantAgentAiCanGenerate === 'true' || isSystematic(text)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    var detail = root.dataset.merchantAgentAiMessage || 'AI generation is unavailable. Database and systematic Merchant Agent tools remain available.';
    if (status) {
      status.textContent = detail;
      status.className = 'mg-form-status is-error';
    }
    if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, true);

  if (window.Microgifter && typeof window.Microgifter.post === 'function') {
    var originalPost = window.Microgifter.post.bind(window.Microgifter);
    window.Microgifter.post = function () {
      return originalPost.apply(null, arguments).then(function (response) {
        var data = response && response.data ? response.data : response;
        if (data && data.ai_status) update(data.ai_status);
        if (data && data.state && data.state.ai_status) update(data.state.ai_status);
        return response;
      });
    };
  }
});
