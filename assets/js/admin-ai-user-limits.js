(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  if (!root || root.dataset.adminUsersCanManageAiLimits !== '1' || !window.Microgifter?.get || !window.Microgifter?.post) return;

  const status = root.querySelector('[data-users-status]');

  function setStatus(message) {
    if (status) status.textContent = message || '';
  }

  function userIdFromCell(cell) {
    const meta = Array.from(cell.querySelectorAll('.mg-admin-user-meta')).find((node) => /User #\d+/.test(node.textContent || ''));
    const match = String(meta?.textContent || '').match(/User #(\d+)/);
    return match ? Number(match[1]) : 0;
  }

  function addButtons() {
    root.querySelectorAll('.mg-admin-user-identity').forEach((cell) => {
      if (cell.querySelector('[data-admin-ai-limit-open]')) return;
      const userId = userIdFromCell(cell);
      if (!userId) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'mg-btn mg-btn-soft mg-admin-ai-limit-btn';
      button.textContent = 'AI limits';
      button.dataset.adminAiLimitOpen = String(userId);
      button.style.marginTop = '8px';
      cell.appendChild(button);
    });
  }

  async function editLimits(userId) {
    setStatus('Loading AI limits…');
    const payload = await window.Microgifter.get(`/api/admin/ai-user-limits.php?user_id=${encodeURIComponent(userId)}&provider_key=anthropic`);
    const data = payload.data || payload;
    const user = data.user || {};
    const limits = data.limits || {};
    const hour = window.prompt(`Claude requests per hour for ${user.display_name || user.email || `User #${userId}`}\nUse 0/blank for provider default.`, limits.requests_per_hour ?? '');
    if (hour === null) { setStatus(''); return; }
    const day = window.prompt('Claude requests per day\nUse 0/blank for provider default.', limits.requests_per_day ?? '');
    if (day === null) { setStatus(''); return; }
    const enabled = window.confirm('Allow this user/merchant to use Claude API requests?\nOK = enabled, Cancel = disabled.');
    const note = window.prompt('Admin note for this limit setting:', limits.note || '') || '';
    setStatus('Saving AI limits…');
    await window.Microgifter.post('/api/admin/ai-user-limits.php', {
      user_id: userId,
      provider_key: 'anthropic',
      enabled: enabled ? 1 : 0,
      requests_per_hour: String(hour || '').trim() === '' ? null : Number(hour),
      requests_per_day: String(day || '').trim() === '' ? null : Number(day),
      note
    });
    setStatus('AI limits saved.');
  }

  root.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-admin-ai-limit-open]');
    if (!button) return;
    event.preventDefault();
    button.disabled = true;
    try {
      await editLimits(Number(button.dataset.adminAiLimitOpen || 0));
    } catch (error) {
      setStatus(error?.message || 'Unable to save AI limits.');
    } finally {
      button.disabled = false;
    }
  });

  addButtons();
  new MutationObserver(addButtons).observe(root, { childList: true, subtree: true });
})();
