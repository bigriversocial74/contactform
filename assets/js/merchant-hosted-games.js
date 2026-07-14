(() => {
  'use strict';

  const root = document.querySelector('[data-merchant-hosted-games]');
  if (!root) return;

  const csrf = String(root.dataset.csrf || '');
  const grid = root.querySelector('[data-hgm-grid]');
  const empty = root.querySelector('[data-hgm-empty]');
  const notice = root.querySelector('[data-hgm-notice]');
  const modal = root.querySelector('[data-hgm-modal]');
  const identityForm = root.querySelector('[data-hgm-identity-form]');
  const integrationForm = root.querySelector('[data-hgm-integration-form]');
  const fileInput = root.querySelector('[data-hgm-file]');
  const fileTitle = root.querySelector('[data-hgm-file-title]');
  const fileDetail = root.querySelector('[data-hgm-file-detail]');
  const uploadButton = root.querySelector('[data-hgm-upload]');
  const publishButton = root.querySelector('[data-hgm-publish]');
  const searchInput = root.querySelector('[data-hgm-search]');
  const state = { payload: null, games: [], current: null, selectedFile: null, slugTouched: false };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const formatNumber = (value) => new Intl.NumberFormat().format(Number(value || 0));
  const formatBytes = (value) => {
    const bytes = Number(value || 0);
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  };
  const slugify = (value) => String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 140);

  function setStatus(selector, message, type = '') {
    const node = root.querySelector(selector);
    if (!node) return;
    node.textContent = String(message || '');
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
  }

  async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const payload = await response.json().catch(() => ({}));
    const data = payload && typeof payload.data === 'object' ? payload.data : payload;
    if (!response.ok || payload.ok === false) throw new Error(String(payload.message || data.message || 'Hosted Games request failed.'));
    return data;
  }

  async function post(action, fields = {}) {
    return request('/api/merchant/hosted-games.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action, csrf_token: csrf, ...fields })
    });
  }

  function readinessRows(readiness = {}) {
    const rows = [
      ['Game ZIP release', Boolean(readiness.release_ready)],
      ['Campaign + reward API', Boolean(readiness.integration_ready)],
      ['Signed webhook', Boolean(readiness.webhook_secret_ready)],
      ['Isolated game database', Boolean(readiness.database_ready)],
    ];
    return rows.map(([label, ready]) => `<div class="hgm-ready-row"><span>${escapeHtml(label)}</span><strong class="${ready ? 'is-ready' : ''}">${ready ? 'Ready' : 'Required'}</strong></div>`).join('');
  }

  function statusClass(value) {
    return `is-${String(value || 'pending').replace(/[^a-z0-9_-]/gi, '')}`;
  }

  function gameCard(game) {
    const analytics = game.analytics || {};
    const readiness = game.readiness || {};
    const coverClass = game.cover_url ? ' has-image' : '';
    const liveAction = game.status === 'active'
      ? `<button class="hgm-btn" type="button" data-hgm-action="pause" data-game-id="${escapeHtml(game.id)}">Pause</button>`
      : `<button class="hgm-btn is-success" type="button" data-hgm-action="publish" data-game-id="${escapeHtml(game.id)}" ${readiness.publish_ready ? '' : 'disabled'}>Publish</button>`;
    return `<article class="hgm-card" data-game-card data-search="${escapeHtml([game.name, game.slug, game.distribution_program_name, game.campaign_title, game.reward_title].join(' ').toLowerCase())}">
      <div class="hgm-card-cover${coverClass}" data-cover-url="${escapeHtml(game.cover_url || '')}">
        <div class="hgm-card-status"><span class="hgm-pill ${statusClass(game.status)}">${escapeHtml(game.status)}</span><span class="hgm-pill ${readiness.publish_ready ? 'is-ready' : 'is-pending'}">${readiness.publish_ready ? 'Publish ready' : 'Setup'}</span></div>
        <h3>${escapeHtml(game.name)}</h3><p>/games/${escapeHtml(game.slug)}/ · ${game.release_version ? `Release ${formatNumber(game.release_version)}` : 'No release'}</p>
      </div>
      <div class="hgm-card-body">
        <div class="hgm-ready-list">${readinessRows(readiness)}</div>
        <div class="hgm-card-meta">
          <div><span>Program</span><strong>${escapeHtml(game.distribution_program_name || 'Not selected')}</strong></div>
          <div><span>Campaign</span><strong>${escapeHtml(game.campaign_title || 'Not selected')}</strong></div>
          <div><span>Reward</span><strong>${escapeHtml(game.reward_title || 'Not selected')}</strong></div>
          <div><span>Plays</span><strong>${formatNumber(analytics.plays)}</strong></div>
          <div><span>Delivered</span><strong>${formatNumber(analytics.rewards_delivered)}</strong></div>
          <div><span>Files</span><strong>${game.file_count ? formatNumber(game.file_count) : '—'}</strong></div>
        </div>
        <div class="hgm-card-actions">
          <button class="hgm-btn is-primary" type="button" data-hgm-action="edit" data-game-id="${escapeHtml(game.id)}">Manage</button>
          ${game.status === 'active' ? `<a class="hgm-btn is-soft" href="${escapeHtml(game.public_url)}" target="_blank" rel="noopener">Open game</a>` : ''}
          <button class="hgm-btn" type="button" data-hgm-action="copy" data-game-id="${escapeHtml(game.id)}">Copy URL</button>
          ${liveAction}
          <button class="hgm-btn is-danger" type="button" data-hgm-action="archive" data-game-id="${escapeHtml(game.id)}">Archive</button>
        </div>
      </div>
    </article>`;
  }

  function applyCoverImages() {
    root.querySelectorAll('[data-cover-url]').forEach((node) => {
      const url = String(node.dataset.coverUrl || '').replace(/["'\\\n\r]/g, '');
      if (url) node.style.backgroundImage = `linear-gradient(180deg,rgba(5,14,25,.12),rgba(5,14,25,.88)),url("${url}")`;
    });
  }

  function render() {
    const query = String(searchInput?.value || '').trim().toLowerCase();
    const games = state.games.filter((game) => !query || [game.name, game.slug, game.distribution_program_name, game.campaign_title, game.reward_title].join(' ').toLowerCase().includes(query));
    if (grid) grid.innerHTML = games.map(gameCard).join('');
    applyCoverImages();
    if (empty) empty.hidden = state.games.length > 0;
    const totalPlays = state.games.reduce((sum, game) => sum + Number(game.analytics?.plays || 0), 0);
    const delivered = state.games.reduce((sum, game) => sum + Number(game.analytics?.rewards_delivered || 0), 0);
    const pending = state.games.filter((game) => !game.readiness?.publish_ready).length;
    const stats = { total: state.games.length, active: state.games.filter((game) => game.status === 'active').length, plays: totalPlays, delivered, pending };
    Object.entries(stats).forEach(([key, value]) => {
      const node = root.querySelector(`[data-hgm-stat="${key}"]`);
      if (node) node.textContent = formatNumber(value);
    });
  }

  function fillSelect(select, options, placeholder, valueKey, labelBuilder, selected = '') {
    if (!select) return;
    select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>` + options.map((item) => `<option value="${escapeHtml(item[valueKey])}" ${String(item[valueKey]) === String(selected) ? 'selected' : ''}>${escapeHtml(labelBuilder(item))}</option>`).join('');
  }

  function populateOptions(game = null) {
    const payload = state.payload || { programs: [], campaigns: [], rewards: [] };
    const programSelect = integrationForm?.elements.program_id;
    const campaignSelect = integrationForm?.elements.campaign_id;
    const rewardSelect = integrationForm?.elements.reward_template_id;
    fillSelect(programSelect, payload.programs || [], 'Select a program', 'public_id', (item) => `${item.name} · ${item.program_type} · ${item.status}`, game?.distribution_program_id || '');
    fillSelect(campaignSelect, payload.campaigns || [], 'Select a campaign', 'public_id', (item) => `${item.title} · ${item.campaign_type} · ${item.status}`, game?.campaign_id || '');
    const selectedProgram = String(programSelect?.value || game?.distribution_program_id || '');
    const rewards = (payload.rewards || []).filter((item) => String(item.program_id) === selectedProgram);
    fillSelect(rewardSelect, rewards, selectedProgram ? 'Select a program reward' : 'Select a program first', 'template_id', (item) => `${item.title} · ${(Number(item.unit_value_cents || 0) / 100).toLocaleString(undefined, { style: 'currency', currency: item.currency || 'USD' })}`, game?.reward_template_id || '');
  }

  function updateModal(game = null) {
    state.current = game;
    state.selectedFile = null;
    const title = root.querySelector('[data-hgm-modal-title]');
    if (title) title.textContent = game ? `Manage ${game.name}` : 'Create hosted game';
    identityForm.reset();
    integrationForm.reset();
    identityForm.elements.game_id.value = game?.id || '';
    identityForm.elements.name.value = game?.name || '';
    identityForm.elements.slug.value = game?.slug || '';
    identityForm.elements.description.value = game?.description || '';
    identityForm.elements.cover_url.value = game?.cover_url || '';
    integrationForm.elements.game_id.value = game?.id || '';
    state.slugTouched = Boolean(game);
    populateOptions(game);
    if (fileTitle) fileTitle.textContent = game?.release_version ? `Upload release ${Number(game.release_version) + 1}` : 'Select a game ZIP';
    if (fileDetail) fileDetail.textContent = game?.release_version ? `Current release ${game.release_version} · ${formatNumber(game.file_count)} files · ${formatBytes(game.extracted_bytes)}` : 'HTML, CSS, JavaScript, images, audio, video, WebGL, WASM, and game assets · maximum 100 MB ZIP';
    if (fileInput) fileInput.value = '';
    if (uploadButton) uploadButton.disabled = true;
    const progress = root.querySelector('[data-hgm-progress]');
    if (progress) progress.style.width = '0%';
    const summary = root.querySelector('[data-hgm-release-summary]');
    if (summary) {
      summary.hidden = !game?.release_version;
      summary.textContent = game?.release_version ? `Current release v${game.release_version} · ${formatNumber(game.file_count)} files · ${formatBytes(game.extracted_bytes)}` : '';
    }
    const readinessNode = root.querySelector('[data-hgm-modal-readiness]');
    if (readinessNode) readinessNode.innerHTML = readinessRows(game?.readiness || {});
    if (publishButton) publishButton.disabled = !game?.readiness?.publish_ready || game?.status === 'active';
    const preview = root.querySelector('[data-hgm-preview]');
    if (preview) {
      preview.hidden = game?.status !== 'active';
      preview.href = game?.public_url || '#';
    }
    root.querySelectorAll('[data-hgm-step-indicator]').forEach((node) => node.classList.remove('is-active'));
    const activeStep = !game ? 'identity' : !game.readiness?.release_ready ? 'release' : !game.readiness?.integration_ready ? 'integration' : 'identity';
    root.querySelector(`[data-hgm-step-indicator="${activeStep}"]`)?.classList.add('is-active');
    ['[data-hgm-identity-status]','[data-hgm-upload-status]','[data-hgm-integration-status]','[data-hgm-publish-status]'].forEach((selector) => setStatus(selector, ''));
  }

  function openModal(game = null) {
    updateModal(game);
    modal.hidden = false;
    document.documentElement.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.hidden = true;
    document.documentElement.style.overflow = '';
  }

  async function load(reopenId = '') {
    const payload = await request('/api/merchant/hosted-games.php', { headers: { Accept: 'application/json' } });
    state.payload = payload;
    state.games = Array.isArray(payload.games) ? payload.games : [];
    if (notice) {
      if (!payload.credential_encryption_ready) {
        notice.hidden = false;
        notice.textContent = 'Hosted-game credential encryption is not configured. Microgifter Admin must configure MG_INTEGRATION_CREDENTIAL_KEY or MG_PAYMENT_CREDENTIAL_KEY before game API access can be created.';
      } else {
        notice.hidden = true;
      }
    }
    render();
    if (reopenId) {
      const game = state.games.find((item) => item.id === reopenId) || null;
      if (game) openModal(game);
    }
  }

  identityForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(identityForm);
    setStatus('[data-hgm-identity-status]', 'Saving game…');
    try {
      const result = await post('save_game', Object.fromEntries(form.entries()));
      await load(result.game.id);
    } catch (error) {
      setStatus('[data-hgm-identity-status]', error.message, 'error');
    }
  });

  identityForm?.elements.name?.addEventListener('input', () => {
    if (!state.slugTouched) identityForm.elements.slug.value = slugify(identityForm.elements.name.value);
  });
  identityForm?.elements.slug?.addEventListener('input', () => { state.slugTouched = true; });

  integrationForm?.elements.program_id?.addEventListener('change', () => populateOptions({
    distribution_program_id: integrationForm.elements.program_id.value,
    campaign_id: integrationForm.elements.campaign_id.value,
    reward_template_id: ''
  }));

  integrationForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(integrationForm);
    if (!form.get('game_id')) return setStatus('[data-hgm-integration-status]', 'Save the game identity first.', 'error');
    setStatus('[data-hgm-integration-status]', 'Creating secure game API integration…');
    try {
      const result = await post('configure_integration', Object.fromEntries(form.entries()));
      await load(result.game.id);
    } catch (error) {
      setStatus('[data-hgm-integration-status]', error.message, 'error');
    }
  });

  fileInput?.addEventListener('change', () => {
    state.selectedFile = fileInput.files?.[0] || null;
    if (state.selectedFile) {
      if (fileTitle) fileTitle.textContent = state.selectedFile.name;
      if (fileDetail) fileDetail.textContent = `${formatBytes(state.selectedFile.size)} · ready to upload`;
    }
    if (uploadButton) uploadButton.disabled = !state.current || !state.selectedFile;
  });

  root.querySelector('[data-hgm-drop]')?.addEventListener('dragover', (event) => {
    event.preventDefault();
    event.currentTarget.classList.add('is-dragging');
  });
  root.querySelector('[data-hgm-drop]')?.addEventListener('dragleave', (event) => event.currentTarget.classList.remove('is-dragging'));
  root.querySelector('[data-hgm-drop]')?.addEventListener('drop', (event) => {
    event.preventDefault();
    event.currentTarget.classList.remove('is-dragging');
    const file = event.dataTransfer?.files?.[0] || null;
    if (!file || !file.name.toLowerCase().endsWith('.zip')) return setStatus('[data-hgm-upload-status]', 'Drop a ZIP file.', 'error');
    state.selectedFile = file;
    if (fileTitle) fileTitle.textContent = file.name;
    if (fileDetail) fileDetail.textContent = `${formatBytes(file.size)} · ready to upload`;
    if (uploadButton) uploadButton.disabled = !state.current;
  });

  uploadButton?.addEventListener('click', () => {
    if (!state.current || !state.selectedFile) return setStatus('[data-hgm-upload-status]', 'Select a ZIP file first.', 'error');
    const currentGameId = state.current.id;
    const form = new FormData();
    form.set('csrf_token', csrf);
    form.set('game_id', currentGameId);
    form.set('game_zip', state.selectedFile, state.selectedFile.name);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/merchant/hosted-game-upload.php');
    xhr.responseType = 'json';
    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) return;
      const progress = root.querySelector('[data-hgm-progress]');
      if (progress) progress.style.width = `${Math.round((event.loaded / event.total) * 100)}%`;
    });
    xhr.addEventListener('load', async () => {
      const response = xhr.response || {};
      if (xhr.status < 200 || xhr.status >= 300 || response.ok === false) {
        setStatus('[data-hgm-upload-status]', response.message || 'Game ZIP upload failed.', 'error');
        uploadButton.disabled = false;
        return;
      }
      await load(currentGameId);
    });
    xhr.addEventListener('error', () => {
      setStatus('[data-hgm-upload-status]', 'Game ZIP upload failed.', 'error');
      uploadButton.disabled = false;
    });
    xhr.addEventListener('abort', () => {
      setStatus('[data-hgm-upload-status]', 'Game ZIP upload was cancelled.', 'error');
      uploadButton.disabled = false;
    });
    setStatus('[data-hgm-upload-status]', 'Uploading and validating game package…');
    uploadButton.disabled = true;
    xhr.send(form);
  });

  publishButton?.addEventListener('click', async () => {
    if (!state.current) return;
    setStatus('[data-hgm-publish-status]', 'Publishing game…');
    try {
      const result = await post('publish', { game_id: state.current.id });
      await load(result.game.id);
    } catch (error) {
      setStatus('[data-hgm-publish-status]', error.message, 'error');
    }
  });

  root.addEventListener('click', async (event) => {
    const createButton = event.target.closest('[data-hgm-create]');
    if (createButton) return openModal(null);
    if (event.target.closest('[data-hgm-close]') || event.target === modal) return closeModal();
    const button = event.target.closest('[data-hgm-action]');
    if (!button) return;
    const game = state.games.find((item) => item.id === button.dataset.gameId);
    if (!game) return;
    const action = button.dataset.hgmAction;
    if (action === 'edit') return openModal(game);
    if (action === 'copy') {
      try { await navigator.clipboard.writeText(game.public_url); button.textContent = 'Copied'; window.setTimeout(() => { button.textContent = 'Copy URL'; }, 1200); }
      catch { window.prompt('Copy hosted game URL', game.public_url); }
      return;
    }
    if (action === 'archive' && !window.confirm(`Archive ${game.name}? The public game will stop immediately.`)) return;
    button.disabled = true;
    try {
      await post(action, { game_id: game.id });
      await load();
    } catch (error) {
      window.alert(error.message);
      button.disabled = false;
    }
  });

  searchInput?.addEventListener('input', render);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });

  load().catch((error) => {
    if (notice) { notice.hidden = false; notice.textContent = error.message; }
  });
})();
