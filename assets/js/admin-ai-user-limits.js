(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  if (!root || root.dataset.adminUsersCanManageAiLimits !== '1' || !window.Microgifter?.get || !window.Microgifter?.post) return;

  const status = root.querySelector('[data-users-status]');
  let activeUserId = 0;
  let activeLimits = null;

  function setStatus(message) {
    if (status) status.textContent = message || '';
  }

  function userIdFromCell(cell) {
    const meta = Array.from(cell.querySelectorAll('.mg-admin-user-meta')).find((node) => /User #\d+/.test(node.textContent || ''));
    const match = String(meta?.textContent || '').match(/User #(\d+)/);
    return match ? Number(match[1]) : 0;
  }

  function cleanLimit(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') return 0;
    const number = Number.parseInt(raw, 10);
    if (!Number.isFinite(number) || number < 0) return 0;
    return Math.min(number, 1000000);
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

  function ensureModal() {
    let layer = document.querySelector('[data-admin-ai-limit-layer]');
    if (layer) return layer;

    layer = document.createElement('div');
    layer.className = 'mg-admin-user-create-layer mg-hidden';
    layer.dataset.adminAiLimitLayer = '';
    layer.innerHTML = `
      <button class="mg-admin-user-create-backdrop" type="button" data-admin-ai-limit-close aria-label="Close AI limits"></button>
      <aside class="mg-admin-user-create-modal" role="dialog" aria-modal="true" aria-labelledby="mg-admin-ai-limit-title">
        <header>
          <div>
            <span class="mg-eyebrow">Admin setting</span>
            <h2 id="mg-admin-ai-limit-title">AI limits</h2>
            <p data-admin-ai-limit-subtitle>Loading account limits...</p>
          </div>
          <button class="mg-admin-user-drawer-close" type="button" data-admin-ai-limit-close aria-label="Close AI limits">×</button>
        </header>
        <form data-admin-ai-limit-form>
          <div class="mg-admin-user-create-notice" data-admin-ai-limit-explainer>
            <strong>How this setting works:</strong> 0 means no AI credits and blocks customer/merchant AI requests. Use a positive number to allow requests. Blank values are saved as 0. The account must be enabled and both hourly and daily limits must be above 0 before AI can be used.
          </div>
          <div class="mg-admin-user-create-grid">
            <label>Provider
              <input name="provider_key" type="text" value="anthropic" readonly>
            </label>
            <label>AI access
              <select name="enabled">
                <option value="1">Enabled if credits are available</option>
                <option value="0">Disabled</option>
              </select>
            </label>
            <label>Requests per hour
              <input name="requests_per_hour" type="number" min="0" max="1000000" step="1" inputmode="numeric" placeholder="0 = no credits">
            </label>
            <label>Requests per day
              <input name="requests_per_day" type="number" min="0" max="1000000" step="1" inputmode="numeric" placeholder="0 = no credits">
            </label>
          </div>
          <label class="mg-admin-management-reason"><span>Admin note</span>
            <textarea name="note" rows="3" maxlength="240" placeholder="Explain why this AI credit limit is being set."></textarea>
          </label>
          <div class="mg-admin-user-create-notice" data-admin-ai-limit-notice role="status" aria-live="polite"></div>
          <footer>
            <button class="mg-btn mg-btn-ghost" type="button" data-admin-ai-limit-close>Cancel</button>
            <button class="mg-btn mg-btn-primary" type="submit" data-admin-ai-limit-submit>Save AI limits</button>
          </footer>
        </form>
      </aside>`;
    document.body.appendChild(layer);
    return layer;
  }

  function modalNotice(message, type = '') {
    const node = document.querySelector('[data-admin-ai-limit-notice]');
    if (!node) return;
    node.textContent = message || '';
    if (type) node.dataset.type = type;
    else delete node.dataset.type;
  }

  function closeModal() {
    const layer = document.querySelector('[data-admin-ai-limit-layer]');
    if (layer) layer.classList.add('mg-hidden');
    activeUserId = 0;
    activeLimits = null;
  }

  async function openModal(userId) {
    const layer = ensureModal();
    const form = layer.querySelector('[data-admin-ai-limit-form]');
    const subtitle = layer.querySelector('[data-admin-ai-limit-subtitle]');
    activeUserId = userId;
    activeLimits = null;
    layer.classList.remove('mg-hidden');
    modalNotice('Loading AI limits...');
    setStatus('Loading AI limits...');

    const payload = await window.Microgifter.get(`/api/admin/ai-user-limits.php?user_id=${encodeURIComponent(userId)}&provider_key=anthropic`);
    const data = payload.data || payload;
    const user = data.user || {};
    const limits = data.limits || {};
    activeLimits = limits;

    if (subtitle) {
      subtitle.textContent = `${user.display_name || user.email || `User #${userId}`} · ${user.email || 'No email'}`;
    }
    form.elements.provider_key.value = limits.provider_key || 'anthropic';
    form.elements.enabled.value = limits.enabled ? '1' : '0';
    form.elements.requests_per_hour.value = cleanLimit(limits.requests_per_hour);
    form.elements.requests_per_day.value = cleanLimit(limits.requests_per_day);
    form.elements.note.value = limits.note || '';
    modalNotice('0 means no credits. Set both limits above 0 to allow AI requests.');
    setStatus('');
  }

  async function saveModal(event) {
    event.preventDefault();
    if (!activeUserId) return;
    const form = event.currentTarget;
    const button = form.querySelector('[data-admin-ai-limit-submit]');
    const requestsPerHour = cleanLimit(form.elements.requests_per_hour.value);
    const requestsPerDay = cleanLimit(form.elements.requests_per_day.value);
    const enabled = form.elements.enabled.value === '1';
    const note = String(form.elements.note.value || '').trim();

    if (button) button.disabled = true;
    modalNotice('Saving AI limits...');
    setStatus('Saving AI limits...');
    try {
      await window.Microgifter.post('/api/admin/ai-user-limits.php', {
        user_id: activeUserId,
        provider_key: 'anthropic',
        enabled: enabled ? 1 : 0,
        requests_per_hour: requestsPerHour,
        requests_per_day: requestsPerDay,
        note
      });
      const creditMessage = enabled && requestsPerHour > 0 && requestsPerDay > 0
        ? `AI limits saved. ${requestsPerHour}/hour and ${requestsPerDay}/day allowed.`
        : 'AI limits saved. This account has no usable AI credits until both limits are above 0 and access is enabled.';
      modalNotice(creditMessage, 'success');
      setStatus(creditMessage);
      setTimeout(closeModal, 650);
    } catch (error) {
      const message = error?.message || 'Unable to save AI limits.';
      modalNotice(message, 'error');
      setStatus(message);
    } finally {
      if (button) button.disabled = false;
    }
  }

  root.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-admin-ai-limit-open]');
    if (!button) return;
    event.preventDefault();
    button.disabled = true;
    try {
      await openModal(Number(button.dataset.adminAiLimitOpen || 0));
    } catch (error) {
      setStatus(error?.message || 'Unable to load AI limits.');
      closeModal();
    } finally {
      button.disabled = false;
    }
  });

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-admin-ai-limit-close]')) {
      event.preventDefault();
      closeModal();
    }
  });

  document.addEventListener('submit', (event) => {
    if (event.target.matches('[data-admin-ai-limit-form]')) saveModal(event);
  });

  addButtons();
  new MutationObserver(addButtons).observe(root, { childList: true, subtree: true });
})();
