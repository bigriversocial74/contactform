(() => {
  'use strict';

  const app = document.querySelector('[data-hgr-app]');
  if (!app || !app.dataset.gameId) return;
  const api = String(app.dataset.api || '');
  const uploadApi = String(app.dataset.uploadApi || '');
  const downloadApi = String(app.dataset.downloadApi || '');
  const gameId = String(app.dataset.gameId || '');
  const csrf = String(app.dataset.csrf || '');
  const canManage = app.dataset.canManage === '1';
  const list = app.querySelector('[data-hgr-list]');
  const empty = app.querySelector('[data-hgr-empty]');
  const notice = app.querySelector('[data-hgr-notice]');
  const compareLeft = app.querySelector('[data-hgr-compare-left]');
  const compareRight = app.querySelector('[data-hgr-compare-right]');
  const compareResult = app.querySelector('[data-hgr-compare-result]');
  let releases = [];
  let game = null;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  }

  function showNotice(message, type = 'info') {
    if (!notice) return;
    notice.hidden = !message;
    notice.textContent = String(message || '');
    notice.className = `hgr-notice${type === 'error' ? ' is-error' : type === 'success' ? ' is-success' : ''}`;
  }

  async function parseResponse(response) {
    const payload = await response.json().catch(() => ({}));
    const data = payload && typeof payload.data === 'object' ? payload.data : payload;
    if (!response.ok || payload.ok === false) throw new Error(String(payload.message || data.message || 'Release request failed.'));
    return { data, message: String(payload.message || '') };
  }

  async function getData() {
    const url = new URL(api, window.location.origin);
    url.searchParams.set('game_id', gameId);
    return parseResponse(await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
  }

  async function post(action, payload = {}) {
    return parseResponse(await fetch(api, {
      method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ game_id: gameId, action, csrf_token: csrf, ...payload })
    }));
  }

  function formatBytes(bytes) {
    const value = Number(bytes || 0);
    if (!value) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(value) / Math.log(1024)));
    return `${(value / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
  }

  function formatDate(value) {
    if (!value) return 'Not recorded';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  }

  function badge(label, status) {
    return `<span class="hgr-badge" data-status="${escapeHtml(status)}">${escapeHtml(label)}</span>`;
  }

  function healthSummary(release) {
    const health = release.health || {};
    const result = health.result || {};
    const warnings = Array.isArray(result.warnings) ? result.warnings : [];
    const errors = Array.isArray(result.errors) ? result.errors : [];
    if (!warnings.length && !errors.length) return `Health ${health.status || 'not run'}${health.checked_at ? ` · ${formatDate(health.checked_at)}` : ''}`;
    return [...errors, ...warnings].join(' ');
  }

  function actionButtons(release) {
    const buttons = [];
    buttons.push(`<button class="hgr-btn" type="button" data-hgr-action="preview" data-release-id="${escapeHtml(release.id)}">Preview & test</button>`);
    if (canManage) {
      buttons.push(`<button class="hgr-btn" type="button" data-hgr-action="health_check" data-release-id="${escapeHtml(release.id)}">Run health check</button>`);
      if (!release.is_active && release.status !== 'failed') {
        const isRollback = release.status === 'archived' || Number(release.version) < Math.max(...releases.map((item) => Number(item.version || 0)));
        buttons.push(`<button class="hgr-btn is-success" type="button" data-hgr-action="${isRollback ? 'rollback' : 'activate'}" data-release-id="${escapeHtml(release.id)}">${isRollback ? 'Roll back to this release' : 'Activate release'}</button>`);
      }
      if (!release.is_active) buttons.push(`<button class="hgr-btn is-danger" type="button" data-hgr-action="archive" data-release-id="${escapeHtml(release.id)}">Archive</button>`);
    }
    if (release.package_download_available) {
      const url = new URL(downloadApi, window.location.origin);
      url.searchParams.set('game_id', gameId);
      url.searchParams.set('release_id', release.id);
      buttons.push(`<a class="hgr-btn" href="${escapeHtml(url.pathname + url.search)}">Download original ZIP</a>`);
    }
    return buttons.join('');
  }

  function renderRelease(release) {
    const validation = release.validation || {};
    const health = release.health || {};
    const uploadedBy = release.uploaded_by || {};
    return `<article class="hgr-release-card${release.is_active ? ' is-active' : ''}" data-release-card="${escapeHtml(release.id)}">
      <header class="hgr-release-head">
        <div class="hgr-version">v${escapeHtml(release.version)}</div>
        <div class="hgr-release-title"><h3>${escapeHtml(release.manifest_version || `Release ${release.version}`)}</h3><p>${escapeHtml(release.original_filename)} · uploaded ${escapeHtml(formatDate(release.created_at))} by ${escapeHtml(uploadedBy.name || 'User')}</p></div>
        <div class="hgr-badges">${badge(release.status, release.status)}${badge(`validation ${validation.status || 'pending'}`, validation.status || 'pending')}${badge(`health ${health.status || 'not run'}`, health.status || 'not_run')}</div>
      </header>
      <div class="hgr-release-body">
        <div>
          <div class="hgr-meta-grid">
            <div><span>Package</span><strong>${escapeHtml(formatBytes(release.package_zip_bytes))} ZIP</strong></div>
            <div><span>Extracted</span><strong>${escapeHtml(formatBytes(release.extracted_bytes))} · ${escapeHtml(release.file_count)} files</strong></div>
            <div><span>Manifest</span><strong>${escapeHtml(release.manifest_schema || 'Legacy')} · ${escapeHtml(release.manifest_version || '1.0.0')}</strong></div>
            <div><span>SDK</span><strong>${escapeHtml(release.sdk_version || 'Not recorded')}</strong></div>
            <div><span>Entry</span><strong>${escapeHtml(release.entry_file)}</strong></div>
            <div><span>Checksum</span><strong>${escapeHtml(String(release.checksum || '').slice(0, 16))}…</strong></div>
            <div><span>Test sessions</span><strong>${escapeHtml(release.test_sessions || 0)} · ${escapeHtml(formatDate(release.last_tested_at))}</strong></div>
            <div><span>Activated</span><strong>${escapeHtml(formatDate(release.activated_at))}</strong></div>
          </div>
          ${canManage ? `<label class="hgr-notes">Release notes<textarea rows="3" maxlength="10000" data-hgr-notes="${escapeHtml(release.id)}">${escapeHtml(release.release_notes || '')}</textarea><button class="hgr-btn" type="button" data-hgr-action="save_notes" data-release-id="${escapeHtml(release.id)}">Save notes</button></label>` : `<div class="hgr-health-detail"><strong>Release notes</strong><br>${escapeHtml(release.release_notes || 'No release notes.')}</div>`}
        </div>
        <div><div class="hgr-actions">${actionButtons(release)}</div><div class="hgr-health-detail">${escapeHtml(healthSummary(release))}</div></div>
      </div>
    </article>`;
  }

  function populateCompare() {
    if (!compareLeft || !compareRight) return;
    const selectedLeft = compareLeft.value;
    const selectedRight = compareRight.value;
    const options = releases.map((release) => `<option value="${escapeHtml(release.id)}">v${escapeHtml(release.version)} · ${escapeHtml(release.status)} · ${escapeHtml(release.manifest_version || 'legacy')}</option>`).join('');
    compareLeft.innerHTML = `<option value="">Select first release</option>${options}`;
    compareRight.innerHTML = `<option value="">Select second release</option>${options}`;
    compareLeft.value = releases.some((item) => item.id === selectedLeft) ? selectedLeft : (releases[1]?.id || '');
    compareRight.value = releases.some((item) => item.id === selectedRight) ? selectedRight : (releases[0]?.id || '');
  }

  function render() {
    const current = releases.find((release) => release.is_active);
    const currentNode = app.querySelector('[data-hgr-current]');
    if (currentNode) currentNode.textContent = current ? `Live release v${current.version} · ${current.manifest_version || 'legacy'}` : 'No active release';
    const stats = {
      total: releases.length,
      testing: releases.filter((release) => ['draft', 'testing'].includes(release.status)).length,
      active: releases.filter((release) => release.status === 'active').length,
      attention: releases.filter((release) => release.status === 'failed' || ['failed', 'warning'].includes(release.validation?.status) || ['failed', 'warning'].includes(release.health?.status)).length
    };
    Object.entries(stats).forEach(([key, value]) => { const node = app.querySelector(`[data-hgr-stat="${key}"]`); if (node) node.textContent = String(value); });
    list.innerHTML = releases.map(renderRelease).join('');
    empty.hidden = releases.length > 0;
    populateCompare();
  }

  async function load() {
    try {
      const response = await getData();
      game = response.data.game || null;
      releases = Array.isArray(response.data.releases) ? response.data.releases : [];
      render();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Unable to load release history.', 'error');
    }
  }

  app.querySelector('[data-hgr-upload-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    const status = app.querySelector('[data-hgr-upload-status]');
    const body = new FormData(form);
    body.append('game_id', gameId);
    body.append('csrf_token', csrf);
    button.disabled = true;
    status.textContent = 'Validating and preserving package…';
    try {
      const response = await parseResponse(await fetch(uploadApi, { method: 'POST', credentials: 'same-origin', body, headers: { Accept: 'application/json' } }));
      showNotice(response.message || 'Draft release uploaded.', 'success');
      form.reset();
      await load();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Unable to upload release.', 'error');
    } finally {
      button.disabled = false;
      status.textContent = '';
    }
  });

  app.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-hgr-action]');
    if (!button || !app.contains(button)) return;
    const action = String(button.dataset.hgrAction || '');
    const releaseId = String(button.dataset.releaseId || '');
    if (!releaseId) return;
    if (action === 'archive' && !window.confirm('Archive this non-active release? Its files and history will be retained.')) return;
    if ((action === 'activate' || action === 'rollback') && !window.confirm('Make this the active release? The current live release will be retained as archived history.')) return;
    button.disabled = true;
    try {
      let response;
      if (action === 'save_notes') {
        const notes = app.querySelector(`[data-hgr-notes="${CSS.escape(releaseId)}"]`);
        response = await post('update_notes', { release_id: releaseId, release_notes: notes?.value || '' });
      } else if (action === 'preview') {
        response = await post('create_preview', { release_id: releaseId });
        window.open(String(response.data.preview_url || `/hosted-game-preview.php?game=${encodeURIComponent(gameId)}&release=${encodeURIComponent(releaseId)}`), '_blank', 'noopener');
      } else {
        response = await post(action, { release_id: releaseId });
      }
      showNotice(response.message || 'Release action completed.', 'success');
      if (action !== 'preview') await load();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Unable to complete release action.', 'error');
    } finally { button.disabled = false; }
  });

  app.querySelector('[data-hgr-compare]')?.addEventListener('click', async (event) => {
    const left = compareLeft?.value || '';
    const right = compareRight?.value || '';
    if (!left || !right || left === right) {
      showNotice('Select two different releases to compare.', 'error');
      return;
    }
    event.currentTarget.disabled = true;
    try {
      const response = await post('compare', { left_release_id: left, right_release_id: right });
      const data = response.data;
      const changes = Array.isArray(data.changes) ? data.changes : [];
      compareResult.hidden = false;
      compareResult.innerHTML = `<div class="hgr-compare-summary"><strong>v${escapeHtml(data.left?.version)} → v${escapeHtml(data.right?.version)}</strong><span>${escapeHtml(data.change_count || 0)} manifest changes</span></div>${changes.length ? `<table class="hgr-compare-table"><thead><tr><th>Path</th><th>Change</th><th>First release</th><th>Second release</th></tr></thead><tbody>${changes.map((change) => `<tr><td><code>${escapeHtml(change.path)}</code></td><td>${escapeHtml(change.type)}</td><td><code>${escapeHtml(JSON.stringify(change.left))}</code></td><td><code>${escapeHtml(JSON.stringify(change.right))}</code></td></tr>`).join('')}</tbody></table>` : '<div class="hgr-empty">The normalized manifests are identical.</div>'}`;
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Unable to compare manifests.', 'error');
    } finally { event.currentTarget.disabled = false; }
  });

  app.querySelector('[data-hgr-refresh]')?.addEventListener('click', () => void load());
  void load();
})();
