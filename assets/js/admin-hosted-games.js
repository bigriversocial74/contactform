(() => {
  'use strict';

  const root = document.querySelector('[data-admin-hosted-games]');
  if (!root) return;
  const list = root.querySelector('[data-hgm-admin-list]');
  const modal = root.querySelector('[data-hgm-admin-modal]');
  const form = root.querySelector('[data-hgm-admin-db-form]');
  if (!list || !modal || !form) return;

  const csrf = String(root.dataset.csrf || '');
  const search = root.querySelector('[data-hgm-admin-search]');
  const notice = root.querySelector('[data-hgm-admin-notice]');
  const empty = root.querySelector('[data-hgm-admin-empty]');
  const state = { games: [], current: null, encryptionReady: false };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const number = (value) => new Intl.NumberFormat().format(Number(value || 0));
  const bytes = (value) => {
    const size = Number(value || 0);
    if (size < 1024) return `${size} B`;
    if (size < 1048576) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / 1048576).toFixed(1)} MB`;
  };

  async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const payload = await response.json().catch(() => ({}));
    const data = payload && typeof payload.data === 'object' ? payload.data : payload;
    if (!response.ok || payload.ok === false) throw new Error(String(payload.message || data.message || 'Hosted Games admin request failed.'));
    return data;
  }

  function post(action, fields = {}) {
    return request('/api/admin/hosted-games.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action, csrf_token: csrf, ...fields })
    });
  }

  function statusClass(value) {
    return `is-${String(value || 'pending').replace(/[^a-z0-9_-]/gi, '')}`;
  }

  function row(game) {
    const database = game.database || {};
    return `<article class="hgm-admin-row" data-admin-game-row data-search="${escapeHtml([game.name, game.slug, game.merchant?.name, game.merchant?.email, game.program?.name, game.campaign?.title, game.reward?.title].join(' ').toLowerCase())}">
      <div><strong>${escapeHtml(game.name)}</strong><span>/games/${escapeHtml(game.slug)}/ · ${game.release ? `Release ${number(game.release.version)}` : 'No release uploaded'}</span></div>
      <div><strong>${escapeHtml(game.merchant?.name || 'Merchant')}</strong><span>${escapeHtml(game.merchant?.email || '')}</span></div>
      <div data-hide-mobile><span>Game status</span><strong><em class="hgm-pill ${statusClass(game.status)}">${escapeHtml(game.status)}</em></strong></div>
      <div data-hide-medium><span>Database</span><strong><em class="hgm-pill ${statusClass(database.status)}">${escapeHtml(database.status || 'pending')}</em></strong></div>
      <div data-hide-mobile><span>Release size</span><strong>${game.release ? bytes(game.release.extracted_bytes) : '—'}</strong></div>
      <div class="hgm-admin-actions"><button class="hgm-btn is-primary" type="button" data-admin-game="${escapeHtml(game.id)}">Database</button>${game.status === 'active' ? `<a class="hgm-btn is-soft" href="${escapeHtml(game.public_url)}" target="_blank" rel="noopener">Open</a>` : ''}</div>
    </article>`;
  }

  function render() {
    const query = String(search?.value || '').trim().toLowerCase();
    const games = state.games.filter((game) => !query || [game.name, game.slug, game.merchant?.name, game.merchant?.email, game.program?.name, game.campaign?.title, game.reward?.title].join(' ').toLowerCase().includes(query));
    list.innerHTML = games.map(row).join('');
    if (empty) empty.hidden = state.games.length > 0;
    const stats = {
      total: state.games.length,
      ready: state.games.filter((game) => game.database?.status === 'ready').length,
      pending: state.games.filter((game) => ['pending', 'disabled'].includes(game.database?.status || 'pending')).length,
      errors: state.games.filter((game) => game.database?.status === 'error').length,
      active: state.games.filter((game) => game.status === 'active').length
    };
    Object.entries(stats).forEach(([key, value]) => {
      const node = root.querySelector(`[data-hgm-admin-stat="${key}"]`);
      if (node) node.textContent = number(value);
    });
  }

  function setStatus(message, type = '') {
    const node = root.querySelector('[data-hgm-admin-form-status]');
    if (!node) return;
    node.textContent = String(message || '');
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
  }

  function openModal(game) {
    state.current = game;
    const title = root.querySelector('[data-hgm-admin-modal-title]');
    if (title) title.textContent = `${game.name} database`;
    form.reset();
    form.elements.game_id.value = game.id;
    form.elements.host.value = game.database?.host || '';
    form.elements.port.value = game.database?.port || 3306;
    form.elements.database_name.value = game.database?.database_name || '';
    form.elements.charset.value = 'utf8mb4';
    form.elements.username.value = '';
    form.elements.password.value = '';
    form.elements.test_after_save.checked = true;
    const summary = root.querySelector('[data-hgm-admin-summary]');
    if (summary) {
      summary.innerHTML = [
        ['Merchant', game.merchant?.name || game.merchant?.email || '—'],
        ['Public URL', `/games/${game.slug}/`],
        ['Release', game.release ? `v${game.release.version} · ${number(game.release.file_count)} files` : 'Not uploaded'],
        ['Database', game.database?.status || 'pending']
      ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
    }
    const open = root.querySelector('[data-hgm-admin-open]');
    if (open) { open.href = game.public_url; open.hidden = game.status !== 'active'; }
    root.querySelector('[data-hgm-admin-test]').disabled = !game.database?.configured;
    root.querySelector('[data-hgm-admin-disable]').disabled = !game.database?.configured || game.database?.status === 'disabled';
    setStatus(game.database?.last_error_message || '');
    modal.hidden = false;
    document.documentElement.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.hidden = true;
    document.documentElement.style.overflow = '';
    state.current = null;
  }

  async function load(reopenId = '') {
    const data = await request('/api/admin/hosted-games.php', { headers: { Accept: 'application/json' } });
    state.games = Array.isArray(data.games) ? data.games : [];
    state.encryptionReady = Boolean(data.credential_encryption_ready);
    if (notice) {
      notice.hidden = state.encryptionReady;
      notice.textContent = state.encryptionReady ? '' : 'Credential encryption is not configured. Set MG_INTEGRATION_CREDENTIAL_KEY or MG_PAYMENT_CREDENTIAL_KEY before saving any game database credentials.';
    }
    render();
    if (reopenId) {
      const game = state.games.find((item) => item.id === reopenId);
      if (game) openModal(game);
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!state.encryptionReady) return setStatus('Credential encryption is not configured.', 'error');
    const fields = Object.fromEntries(new FormData(form).entries());
    fields.test_after_save = form.elements.test_after_save.checked ? 1 : 0;
    setStatus('Saving and testing the isolated game database…');
    try {
      const result = await post('save_database', fields);
      setStatus(result.game.database?.status === 'ready' ? 'Database connected and standard tables are ready.' : (result.game.database?.last_error_message || 'Database settings saved.'), result.game.database?.status === 'ready' ? 'success' : 'error');
      await load(result.game.id);
    } catch (error) {
      setStatus(error.message, 'error');
    }
  });

  root.querySelector('[data-hgm-admin-test]')?.addEventListener('click', async () => {
    if (!state.current) return;
    setStatus('Testing database connection and standard tables…');
    try {
      const result = await post('test_database', { game_id: state.current.id });
      const ready = result.game.database?.status === 'ready';
      setStatus(ready ? 'Database connected and standard tables are ready.' : (result.game.database?.last_error_message || 'Connection failed.'), ready ? 'success' : 'error');
      await load(result.game.id);
    } catch (error) { setStatus(error.message, 'error'); }
  });

  root.querySelector('[data-hgm-admin-disable]')?.addEventListener('click', async () => {
    if (!state.current || !window.confirm(`Disable database access for ${state.current.name}? An active game will be paused.`)) return;
    setStatus('Disabling database access…');
    try {
      const result = await post('disable_database', { game_id: state.current.id });
      await load(result.game.id);
    } catch (error) { setStatus(error.message, 'error'); }
  });

  root.querySelector('[data-hgm-admin-pause]')?.addEventListener('click', async () => {
    if (!state.current || !window.confirm(`Pause ${state.current.name} at the platform level?`)) return;
    setStatus('Pausing hosted game…');
    try {
      const result = await post('pause_game', { game_id: state.current.id });
      await load(result.game.id);
    } catch (error) { setStatus(error.message, 'error'); }
  });

  root.addEventListener('click', (event) => {
    const gameButton = event.target.closest('[data-admin-game]');
    if (gameButton) {
      const game = state.games.find((item) => item.id === gameButton.dataset.adminGame);
      if (game) openModal(game);
      return;
    }
    if (event.target.closest('[data-hgm-admin-close]') || event.target === modal) closeModal();
  });

  search?.addEventListener('input', render);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
  load().catch((error) => {
    if (notice) { notice.hidden = false; notice.textContent = error.message; }
  });
})();
