(() => {
  'use strict';

  const root = document.querySelector('[data-admin-hosted-games]');
  if (!root) return;
  const list = root.querySelector('[data-hgm-admin-list]');
  const modal = root.querySelector('[data-hgm-admin-modal]');
  const gameForm = root.querySelector('[data-hgm-admin-game-form]');
  const integrationForm = root.querySelector('[data-hgm-admin-integration-form]');
  const dbForm = root.querySelector('[data-hgm-admin-db-form]');
  const canManage = root.dataset.canManage === '1';
  if (!list) return;

  const csrf = String(root.dataset.csrf || '');
  const search = root.querySelector('[data-hgm-admin-search]');
  const notice = root.querySelector('[data-hgm-admin-notice]');
  const empty = root.querySelector('[data-hgm-admin-empty]');
  const fileInput = root.querySelector('[data-hgm-admin-file]');
  const uploadButton = root.querySelector('[data-hgm-admin-upload]');
  const state = {
    games: [],
    merchants: [],
    options: { programs: [], campaigns: [], rewards: [] },
    current: null,
    selectedFile: null,
    encryptionReady: false,
    slugTouched: false
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const number = (value) => new Intl.NumberFormat().format(Number(value || 0));
  const bytes = (value) => {
    const size = Number(value || 0);
    if (size < 1024) return `${size} B`;
    if (size < 1048576) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / 1048576).toFixed(1)} MB`;
  };
  const slugify = (value) => String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 140);

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

  function setStatus(selector, message, type = '') {
    const node = root.querySelector(selector);
    if (!node) return;
    node.textContent = String(message || '');
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
  }

  function statusClass(value) {
    return `is-${String(value || 'pending').replace(/[^a-z0-9_-]/gi, '')}`;
  }

  function readinessRows(readiness = {}) {
    const rows = [
      ['Game ZIP release', Boolean(readiness.release_ready)],
      ['Program, Campaign and reward API', Boolean(readiness.integration_ready)],
      ['Isolated game database', Boolean(readiness.database_ready)],
      ['Signed webhook', Boolean(readiness.webhook_secret_ready)]
    ];
    return rows.map(([label, ready]) => `<div class="hgm-ready-row"><span>${escapeHtml(label)}</span><strong class="${ready ? 'is-ready' : ''}">${ready ? 'Ready' : 'Required'}</strong></div>`).join('');
  }

  function row(game) {
    const database = game.database || {};
    const readiness = game.readiness || {};
    const manage = canManage ? `<button class="hgm-btn is-primary" type="button" data-admin-game="${escapeHtml(game.id)}">Manage</button>` : '';
    const open = game.status === 'active' ? `<a class="hgm-btn is-soft" href="${escapeHtml(game.public_url)}" target="_blank" rel="noopener">Open</a>` : '';
    return `<article class="hgm-admin-row" data-admin-game-row data-search="${escapeHtml([game.name, game.slug, game.merchant?.name, game.merchant?.email, game.program?.name, game.campaign?.title, game.reward?.title].join(' ').toLowerCase())}">
      <div><strong>${escapeHtml(game.name)}</strong><span>/games/${escapeHtml(game.slug)}/ · ${game.release ? `Release ${number(game.release.version)}` : 'No release uploaded'}</span></div>
      <div><strong>${escapeHtml(game.merchant?.name || 'Merchant')}</strong><span>${escapeHtml(game.merchant?.email || '')}</span></div>
      <div data-hide-mobile><span>Game status</span><strong><em class="hgm-pill ${statusClass(game.status)}">${escapeHtml(game.status)}</em></strong></div>
      <div data-hide-medium><span>Integration</span><strong><em class="hgm-pill ${statusClass(game.integration_status)}">${escapeHtml(game.integration_status || 'pending')}</em></strong></div>
      <div data-hide-mobile><span>Database</span><strong><em class="hgm-pill ${statusClass(database.status)}">${escapeHtml(database.status || 'pending')}</em></strong></div>
      <div class="hgm-admin-actions">${manage}${open}</div>
    </article>`;
  }

  function render() {
    const query = String(search?.value || '').trim().toLowerCase();
    const games = state.games.filter((game) => !query || [game.name, game.slug, game.merchant?.name, game.merchant?.email, game.program?.name, game.campaign?.title, game.reward?.title].join(' ').toLowerCase().includes(query));
    list.innerHTML = games.map(row).join('');
    if (empty) empty.hidden = state.games.length > 0;
    const stats = {
      total: state.games.length,
      publish_ready: state.games.filter((game) => game.readiness?.publish_ready).length,
      ready: state.games.filter((game) => game.database?.status === 'ready').length,
      pending: state.games.filter((game) => !game.readiness?.publish_ready).length,
      active: state.games.filter((game) => game.status === 'active').length
    };
    Object.entries(stats).forEach(([key, value]) => {
      const node = root.querySelector(`[data-hgm-admin-stat="${key}"]`);
      if (node) node.textContent = number(value);
    });
  }

  function fillMerchants(selected = '') {
    const select = root.querySelector('[data-hgm-admin-merchant]');
    if (!select) return;
    select.innerHTML = '<option value="">Select merchant</option>' + state.merchants.map((merchant) => `<option value="${merchant.user_id}" ${String(merchant.user_id) === String(selected) ? 'selected' : ''}>${escapeHtml(merchant.name)} · ${escapeHtml(merchant.email)}</option>`).join('');
  }

  function fillSelect(select, options, placeholder, valueKey, labelBuilder, selected = '') {
    if (!select) return;
    select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>` + options.map((item) => `<option value="${escapeHtml(item[valueKey])}" ${String(item[valueKey]) === String(selected) ? 'selected' : ''}>${escapeHtml(labelBuilder(item))}</option>`).join('');
  }

  function populateOptions(game = null) {
    if (!integrationForm) return;
    const program = integrationForm.elements.program_id;
    const campaign = integrationForm.elements.campaign_id;
    const reward = integrationForm.elements.reward_template_id;
    fillSelect(program, state.options.programs || [], 'Select a Program', 'public_id', (item) => `${item.name} · ${item.program_type} · ${item.status}`, game?.program?.id || '');
    fillSelect(campaign, state.options.campaigns || [], 'Select a Campaign', 'public_id', (item) => `${item.title} · ${item.campaign_type} · ${item.status}`, game?.campaign?.id || '');
    const selectedProgram = String(program?.value || game?.program?.id || '');
    const rewards = (state.options.rewards || []).filter((item) => String(item.program_id) === selectedProgram);
    fillSelect(reward, rewards, selectedProgram ? 'Select a Program reward' : 'Select a Program first', 'template_id', (item) => {
      const currency = item.currency || 'USD';
      const value = (Number(item.unit_value_cents || 0) / 100).toLocaleString(undefined, { style: 'currency', currency });
      return `${item.title} · ${value}`;
    }, game?.reward?.id || '');
  }

  async function loadMerchantOptions(merchantUserId) {
    state.options = { programs: [], campaigns: [], rewards: [] };
    if (!merchantUserId) {
      populateOptions(state.current);
      return;
    }
    const data = await request(`/api/admin/hosted-games.php?merchant_user_id=${encodeURIComponent(merchantUserId)}`, { headers: { Accept: 'application/json' } });
    state.options = data.options || state.options;
    if (Array.isArray(data.merchants)) state.merchants = data.merchants;
    populateOptions(state.current);
  }

  function setFile(file) {
    state.selectedFile = file || null;
    const title = root.querySelector('[data-hgm-admin-file-title]');
    const detail = root.querySelector('[data-hgm-admin-file-detail]');
    if (state.selectedFile) {
      if (title) title.textContent = state.selectedFile.name;
      if (detail) detail.textContent = `${bytes(state.selectedFile.size)} · ready to upload`;
    } else {
      if (title) title.textContent = state.current?.release ? `Upload release ${Number(state.current.release.version) + 1}` : 'Select a game ZIP';
      if (detail) detail.textContent = state.current?.release ? `Current release v${state.current.release.version} · ${number(state.current.release.file_count)} files · ${bytes(state.current.release.extracted_bytes)}` : 'HTML, JavaScript, media, WebGL, WASM and Unity assets · maximum 100 MB ZIP';
    }
    if (uploadButton) uploadButton.disabled = !state.current || !state.selectedFile;
  }

  async function openModal(game = null) {
    if (!modal || !gameForm || !integrationForm || !dbForm) return;
    state.current = game;
    state.slugTouched = Boolean(game);
    state.selectedFile = null;
    const title = root.querySelector('[data-hgm-admin-modal-title]');
    if (title) title.textContent = game ? `Manage ${game.name}` : 'Create hosted game';

    gameForm.reset();
    integrationForm.reset();
    dbForm.reset();
    gameForm.elements.game_id.value = game?.id || '';
    gameForm.elements.name.value = game?.name || '';
    gameForm.elements.slug.value = game?.slug || '';
    gameForm.elements.description.value = game?.description || '';
    gameForm.elements.cover_url.value = game?.cover_url || '';
    fillMerchants(game?.merchant?.user_id || '');
    gameForm.elements.merchant_user_id.disabled = Boolean(game);

    integrationForm.elements.game_id.value = game?.id || '';
    dbForm.elements.game_id.value = game?.id || '';
    dbForm.elements.host.value = game?.database?.host || '';
    dbForm.elements.port.value = game?.database?.port || 3306;
    dbForm.elements.database_name.value = game?.database?.database_name || '';
    dbForm.elements.charset.value = 'utf8mb4';
    dbForm.elements.username.value = '';
    dbForm.elements.password.value = '';
    dbForm.elements.test_after_save.checked = true;

    const existing = root.querySelector('[data-hgm-admin-existing]');
    if (existing) existing.hidden = !game;
    if (fileInput) fileInput.value = '';
    const progress = root.querySelector('[data-hgm-admin-progress]');
    if (progress) progress.style.width = '0%';
    setFile(null);

    const summary = root.querySelector('[data-hgm-admin-summary]');
    if (summary && game) {
      summary.innerHTML = [
        ['Merchant', game.merchant?.name || game.merchant?.email || '—'],
        ['Public URL', `/games/${game.slug}/`],
        ['Release', game.release ? `v${game.release.version} · ${number(game.release.file_count)} files` : 'Not uploaded'],
        ['Program', game.program?.name || 'Not selected'],
        ['Campaign', game.campaign?.title || 'Not selected'],
        ['Reward', game.reward?.title || 'Not selected'],
        ['Database', game.database?.status || 'pending']
      ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
    }
    const readiness = root.querySelector('[data-hgm-admin-readiness]');
    if (readiness) readiness.innerHTML = readinessRows(game?.readiness || {});

    const open = root.querySelector('[data-hgm-admin-open]');
    if (open) { open.href = game?.public_url || '#'; open.hidden = game?.status !== 'active'; }
    const publish = root.querySelector('[data-hgm-admin-publish]');
    if (publish) publish.disabled = !game?.readiness?.publish_ready || game?.status === 'active';
    const pause = root.querySelector('[data-hgm-admin-pause]');
    if (pause) pause.disabled = !game || game.status === 'paused' || game.status === 'archived';
    const archive = root.querySelector('[data-hgm-admin-archive]');
    if (archive) archive.disabled = !game || game.status === 'archived';
    const test = root.querySelector('[data-hgm-admin-test]');
    if (test) test.disabled = !game?.database?.configured;
    const disable = root.querySelector('[data-hgm-admin-disable]');
    if (disable) disable.disabled = !game?.database?.configured || game.database?.status === 'disabled';

    ['[data-hgm-admin-game-status]','[data-hgm-admin-upload-status]','[data-hgm-admin-integration-status]','[data-hgm-admin-db-status]','[data-hgm-admin-publish-status]'].forEach((selector) => setStatus(selector, ''));
    if (game?.database?.last_error_message) setStatus('[data-hgm-admin-db-status]', game.database.last_error_message, 'error');

    modal.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    await loadMerchantOptions(game?.merchant?.user_id || gameForm.elements.merchant_user_id.value || '');
    populateOptions(game);
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    document.documentElement.style.overflow = '';
    state.current = null;
    state.selectedFile = null;
  }

  async function load(reopenId = '') {
    const data = await request('/api/admin/hosted-games.php', { headers: { Accept: 'application/json' } });
    state.games = Array.isArray(data.games) ? data.games : [];
    state.merchants = Array.isArray(data.merchants) ? data.merchants : [];
    state.encryptionReady = Boolean(data.credential_encryption_ready);
    if (notice) {
      notice.hidden = state.encryptionReady;
      notice.textContent = state.encryptionReady ? '' : 'Credential encryption is not configured. Set MG_INTEGRATION_CREDENTIAL_KEY or MG_PAYMENT_CREDENTIAL_KEY before creating game API or database credentials.';
    }
    render();
    if (reopenId) {
      const game = state.games.find((item) => item.id === reopenId) || null;
      if (game) await openModal(game);
    }
  }

  gameForm?.elements.name?.addEventListener('input', () => {
    if (!state.slugTouched) gameForm.elements.slug.value = slugify(gameForm.elements.name.value);
  });
  gameForm?.elements.slug?.addEventListener('input', () => { state.slugTouched = true; });
  gameForm?.elements.merchant_user_id?.addEventListener('change', async () => {
    if (state.current) return;
    try { await loadMerchantOptions(gameForm.elements.merchant_user_id.value); }
    catch (error) { setStatus('[data-hgm-admin-game-status]', error.message, 'error'); }
  });

  gameForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fields = Object.fromEntries(new FormData(gameForm).entries());
    if (!state.current) fields.merchant_user_id = gameForm.elements.merchant_user_id.value;
    setStatus('[data-hgm-admin-game-status]', 'Saving hosted game…');
    try {
      const result = await post('save_game', fields);
      setStatus('[data-hgm-admin-game-status]', 'Hosted game saved.', 'success');
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-game-status]', error.message, 'error'); }
  });

  integrationForm?.elements.program_id?.addEventListener('change', () => populateOptions({
    program: { id: integrationForm.elements.program_id.value },
    campaign: { id: integrationForm.elements.campaign_id.value },
    reward: { id: '' }
  }));

  integrationForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!state.current) return;
    if (!state.encryptionReady) return setStatus('[data-hgm-admin-integration-status]', 'Credential encryption is not configured.', 'error');
    setStatus('[data-hgm-admin-integration-status]', 'Provisioning the merchant game API integration…');
    try {
      const result = await post('configure_integration', Object.fromEntries(new FormData(integrationForm).entries()));
      setStatus('[data-hgm-admin-integration-status]', 'Program, Campaign, reward and game API integration configured.', 'success');
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-integration-status]', error.message, 'error'); }
  });

  fileInput?.addEventListener('change', () => setFile(fileInput.files?.[0] || null));
  root.querySelector('[data-hgm-admin-drop]')?.addEventListener('dragover', (event) => {
    event.preventDefault();
    event.currentTarget.classList.add('is-dragging');
  });
  root.querySelector('[data-hgm-admin-drop]')?.addEventListener('dragleave', (event) => event.currentTarget.classList.remove('is-dragging'));
  root.querySelector('[data-hgm-admin-drop]')?.addEventListener('drop', (event) => {
    event.preventDefault();
    event.currentTarget.classList.remove('is-dragging');
    const file = event.dataTransfer?.files?.[0] || null;
    if (!file || !file.name.toLowerCase().endsWith('.zip')) return setStatus('[data-hgm-admin-upload-status]', 'Drop a ZIP file.', 'error');
    setFile(file);
  });

  uploadButton?.addEventListener('click', () => {
    if (!state.current || !state.selectedFile) return setStatus('[data-hgm-admin-upload-status]', 'Select a ZIP file first.', 'error');
    const form = new FormData();
    form.set('csrf_token', csrf);
    form.set('game_id', state.current.id);
    form.set('game_zip', state.selectedFile, state.selectedFile.name);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/admin/hosted-game-upload.php');
    xhr.responseType = 'json';
    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) return;
      const progress = root.querySelector('[data-hgm-admin-progress]');
      if (progress) progress.style.width = `${Math.round((event.loaded / event.total) * 100)}%`;
    });
    xhr.addEventListener('load', async () => {
      const response = xhr.response || {};
      if (xhr.status < 200 || xhr.status >= 300 || response.ok === false) {
        setStatus('[data-hgm-admin-upload-status]', response.message || 'Game ZIP upload failed.', 'error');
        uploadButton.disabled = false;
        return;
      }
      setStatus('[data-hgm-admin-upload-status]', 'Game release uploaded and activated.', 'success');
      await load(state.current.id);
    });
    xhr.addEventListener('error', () => {
      setStatus('[data-hgm-admin-upload-status]', 'Game ZIP upload failed.', 'error');
      uploadButton.disabled = false;
    });
    setStatus('[data-hgm-admin-upload-status]', 'Uploading and validating game package…');
    uploadButton.disabled = true;
    xhr.send(form);
  });

  dbForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!state.current) return;
    if (!state.encryptionReady) return setStatus('[data-hgm-admin-db-status]', 'Credential encryption is not configured.', 'error');
    const fields = Object.fromEntries(new FormData(dbForm).entries());
    fields.test_after_save = dbForm.elements.test_after_save.checked ? 1 : 0;
    setStatus('[data-hgm-admin-db-status]', 'Saving and testing the isolated game database…');
    try {
      const result = await post('save_database', fields);
      const ready = result.game.database?.status === 'ready';
      setStatus('[data-hgm-admin-db-status]', ready ? 'Database connected and standard tables are ready.' : (result.game.database?.last_error_message || 'Database settings saved.'), ready ? 'success' : 'error');
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-db-status]', error.message, 'error'); }
  });

  root.querySelector('[data-hgm-admin-test]')?.addEventListener('click', async () => {
    if (!state.current) return;
    setStatus('[data-hgm-admin-db-status]', 'Testing database connection and standard tables…');
    try {
      const result = await post('test_database', { game_id: state.current.id });
      const ready = result.game.database?.status === 'ready';
      setStatus('[data-hgm-admin-db-status]', ready ? 'Database connected and standard tables are ready.' : (result.game.database?.last_error_message || 'Connection failed.'), ready ? 'success' : 'error');
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-db-status]', error.message, 'error'); }
  });

  root.querySelector('[data-hgm-admin-disable]')?.addEventListener('click', async () => {
    if (!state.current || !window.confirm(`Disable database access for ${state.current.name}? An active game will be paused.`)) return;
    setStatus('[data-hgm-admin-db-status]', 'Disabling database access…');
    try {
      const result = await post('disable_database', { game_id: state.current.id });
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-db-status]', error.message, 'error'); }
  });

  root.querySelector('[data-hgm-admin-publish]')?.addEventListener('click', async () => {
    if (!state.current) return;
    setStatus('[data-hgm-admin-publish-status]', 'Publishing hosted game…');
    try {
      const result = await post('publish_game', { game_id: state.current.id });
      setStatus('[data-hgm-admin-publish-status]', 'Hosted game published.', 'success');
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-publish-status]', error.message, 'error'); }
  });

  root.querySelector('[data-hgm-admin-pause]')?.addEventListener('click', async () => {
    if (!state.current || !window.confirm(`Pause ${state.current.name} at the platform level?`)) return;
    setStatus('[data-hgm-admin-publish-status]', 'Pausing hosted game…');
    try {
      const result = await post('pause_game', { game_id: state.current.id });
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-publish-status]', error.message, 'error'); }
  });

  root.querySelector('[data-hgm-admin-archive]')?.addEventListener('click', async () => {
    if (!state.current || !window.confirm(`Archive ${state.current.name}? The public game will stop immediately.`)) return;
    setStatus('[data-hgm-admin-publish-status]', 'Archiving hosted game…');
    try {
      const result = await post('archive_game', { game_id: state.current.id });
      await load(result.game.id);
    } catch (error) { setStatus('[data-hgm-admin-publish-status]', error.message, 'error'); }
  });

  root.addEventListener('click', async (event) => {
    if (event.target.closest('[data-hgm-admin-create]')) {
      await openModal(null);
      return;
    }
    const gameButton = event.target.closest('[data-admin-game]');
    if (gameButton) {
      const game = state.games.find((item) => item.id === gameButton.dataset.adminGame);
      if (game) await openModal(game);
      return;
    }
    if (event.target.closest('[data-hgm-admin-close]') || event.target === modal) closeModal();
  });

  search?.addEventListener('input', render);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal && !modal.hidden) closeModal(); });
  load().catch((error) => {
    if (notice) { notice.hidden = false; notice.textContent = error.message; }
  });
})();
