(() => {
  'use strict';

  const root = document.querySelector('[data-hosted-game-analytics]');
  if (!root) return;

  const config = {
    mode: String(root.dataset.mode || 'merchant'),
    gameId: String(root.dataset.gameId || ''),
    apiUrl: String(root.dataset.apiUrl || ''),
    exportUrl: String(root.dataset.exportUrl || ''),
    csrf: String(root.dataset.csrf || '')
  };
  const state = { payload: null, loading: false };
  const days = root.querySelector('[data-hga-days]');
  const release = root.querySelector('[data-hga-release]');
  const diagnosticStatus = root.querySelector('[data-hga-diagnostic-status]');
  const severity = root.querySelector('[data-hga-severity]');
  const notice = root.querySelector('[data-hga-notice]');
  const refresh = root.querySelector('[data-hga-refresh]');
  const exportLink = root.querySelector('[data-hga-export]');

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
  const number = (value, decimals = 0) => new Intl.NumberFormat(undefined, { maximumFractionDigits: decimals }).format(Number(value || 0));
  const percent = (value) => `${number(value, 1)}%`;
  const money = (cents, currency = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number(cents || 0) / 100);
  const duration = (milliseconds) => {
    const value = Math.max(0, Number(milliseconds || 0));
    if (value < 1000) return `${Math.round(value)} ms`;
    if (value < 60000) return `${(value / 1000).toFixed(value < 10000 ? 1 : 0)} sec`;
    const minutes = Math.floor(value / 60000);
    const seconds = Math.round((value % 60000) / 1000);
    return `${minutes}m ${seconds}s`;
  };
  const dateTime = (value) => {
    if (!value) return '—';
    const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T') + 'Z';
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString();
  };

  function setNotice(message = '', type = 'error') {
    if (!notice) return;
    notice.hidden = !message;
    notice.textContent = message;
    notice.dataset.type = type;
  }

  async function parseResponse(response) {
    const payload = await response.json().catch(() => ({}));
    const data = payload && typeof payload.data === 'object' ? payload.data : payload;
    if (!response.ok || payload.ok === false) throw new Error(String(payload.message || data.message || 'Hosted game analytics request failed.'));
    return data;
  }

  function queryUrl(baseUrl = config.apiUrl) {
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('game_id', config.gameId);
    url.searchParams.set('days', String(days?.value || '30'));
    if (release?.value) url.searchParams.set('release_id', release.value);
    if (diagnosticStatus?.value) url.searchParams.set('diagnostic_status', diagnosticStatus.value);
    if (severity?.value) url.searchParams.set('severity', severity.value);
    return url;
  }

  function updateExportLink() {
    if (!exportLink) return;
    exportLink.href = queryUrl(config.exportUrl).toString();
  }

  function setKpi(key, value) {
    const node = root.querySelector(`[data-hga-kpi="${key}"]`);
    if (node) node.textContent = value;
  }

  function renderHeader(payload) {
    const game = payload.game || {};
    const title = root.querySelector('[data-hga-game-name]');
    const detail = root.querySelector('[data-hga-game-detail]');
    const open = root.querySelector('[data-hga-open-game]');
    if (title) title.textContent = game.name || 'Hosted Game Analytics';
    if (detail) detail.textContent = `${game.slug ? `/games/${game.slug}/` : 'Hosted game'} · ${game.status || 'unknown'} · ${payload.range?.start_date || ''} through ${payload.range?.end_date || ''} UTC`;
    if (open) {
      open.hidden = game.status !== 'active' || !game.public_url;
      open.href = game.public_url || '#';
    }
    const rangeLabel = root.querySelector('[data-hga-range-label]');
    if (rangeLabel) rangeLabel.textContent = `${payload.range?.days || 0} days · generated ${new Date(payload.generated_at || Date.now()).toLocaleTimeString()}`;
  }

  function renderReleaseOptions(payload) {
    if (!release) return;
    const current = release.value;
    release.innerHTML = '<option value="">All releases</option>' + (payload.releases || []).map((item) => {
      const label = `Release ${number(item.version_number)} · ${item.status || 'unknown'}`;
      return `<option value="${escapeHtml(item.public_id)}" ${String(item.public_id) === String(current) ? 'selected' : ''}>${escapeHtml(label)}</option>`;
    }).join('');
  }

  function renderSummary(payload) {
    const summary = payload.summary || {};
    const rewards = summary.rewards || {};
    setKpi('game_loads', number(summary.game_loads));
    setKpi('unique_players', number(summary.unique_players));
    setKpi('connected_players', number(summary.connected_players));
    setKpi('runs_started', number(summary.runs_started));
    setKpi('runs_completed', number(summary.runs_completed));
    setKpi('qualification_rate', percent(summary.qualification_rate));
    setKpi('abandonment_rate', percent(summary.abandonment_rate));
    setKpi('average_play_duration_ms', duration(summary.average_play_duration_ms));
    setKpi('average_score', number(summary.average_score, 1));
    setKpi('highest_score', number(summary.highest_score));
    setKpi('repeat_player_rate', percent(summary.repeat_player_rate));
    setKpi('cost_per_qualified_player_cents', money(rewards.cost_per_qualified_player_cents, payload.game?.reward_currency || 'USD'));
    Object.entries(rewards).forEach(([key, value]) => {
      const node = root.querySelector(`[data-hga-reward="${key}"]`);
      if (!node) return;
      node.textContent = key.endsWith('_cents') ? money(value, payload.game?.reward_currency || 'USD') : number(value);
    });
  }

  function renderChart(rows = []) {
    const node = root.querySelector('[data-hga-chart]');
    if (!node) return;
    if (!rows.length) {
      node.innerHTML = '<div class="hga-empty">No trend data is available for this period.</div>';
      return;
    }
    const width = 900;
    const height = 280;
    const left = 42;
    const right = 16;
    const top = 18;
    const bottom = 34;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const series = ['loads','runs','completed','errors'];
    const maximum = Math.max(1, ...rows.flatMap((row) => series.map((key) => Number(row[key] || 0))));
    const x = (index) => left + (rows.length === 1 ? plotWidth / 2 : (index / (rows.length - 1)) * plotWidth);
    const y = (value) => top + plotHeight - (Number(value || 0) / maximum) * plotHeight;
    const path = (key) => rows.map((row, index) => `${index === 0 ? 'M' : 'L'}${x(index).toFixed(2)},${y(row[key]).toFixed(2)}`).join(' ');
    const area = (key) => `${path(key)} L${x(rows.length - 1).toFixed(2)},${(top + plotHeight).toFixed(2)} L${x(0).toFixed(2)},${(top + plotHeight).toFixed(2)} Z`;
    const grid = Array.from({ length: 5 }, (_, index) => {
      const ratio = index / 4;
      const gridY = top + ratio * plotHeight;
      const label = Math.round(maximum * (1 - ratio));
      return `<line class="hga-chart-grid" x1="${left}" y1="${gridY}" x2="${width - right}" y2="${gridY}"></line><text class="hga-chart-label" x="${left - 8}" y="${gridY + 3}" text-anchor="end">${label}</text>`;
    }).join('');
    const labelStep = Math.max(1, Math.ceil(rows.length / 7));
    const labels = rows.map((row, index) => index % labelStep === 0 || index === rows.length - 1
      ? `<text class="hga-chart-label" x="${x(index)}" y="${height - 10}" text-anchor="middle">${escapeHtml(String(row.date || '').slice(5))}</text>`
      : '').join('');
    node.innerHTML = `<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Hosted game performance trend">${grid}<path class="hga-chart-area" data-series="loads" d="${area('loads')}"></path>${series.map((key) => `<path class="hga-chart-line" data-series="${key}" d="${path(key)}"></path>`).join('')}${labels}</svg>`;
  }

  function renderBars(selector, rows = []) {
    const node = root.querySelector(selector);
    if (!node) return;
    if (!rows.length) {
      node.innerHTML = '<div class="hga-empty">No data yet.</div>';
      return;
    }
    const maximum = Math.max(1, ...rows.map((row) => Number(row.value || row.occurrences || 0)));
    node.innerHTML = rows.map((row) => {
      const value = Number(row.value || row.occurrences || 0);
      const label = row.label || row.category || 'Unknown';
      return `<div class="hga-bar"><span title="${escapeHtml(label)}">${escapeHtml(String(label).replaceAll('_',' '))}</span><div class="hga-bar-track"><i style="width:${Math.max(2,(value / maximum) * 100)}%"></i></div><strong>${number(value)}</strong></div>`;
    }).join('');
  }

  function renderFunnels(payload) {
    const eventsNode = root.querySelector('[data-hga-event-funnel]');
    const events = payload.funnels?.events || [];
    if (eventsNode) {
      if (!events.length) eventsNode.innerHTML = '<div class="hga-empty">No Standard v1 funnel events yet.</div>';
      else {
        const maximum = Math.max(1, ...events.map((row) => Number(row.occurrences || 0)));
        eventsNode.innerHTML = events.map((row) => `<div class="hga-funnel-row"><span class="hga-funnel-label">${escapeHtml(String(row.event_type || '').replaceAll('_',' '))}</span><div class="hga-funnel-track"><i style="width:${Math.max(2,(Number(row.occurrences || 0) / maximum) * 100)}%"></i></div><strong>${number(row.occurrences)}</strong></div>`).join('');
      }
    }
    const levelsNode = root.querySelector('[data-hga-level-funnel]');
    if (levelsNode) {
      const levels = payload.funnels?.levels || [];
      levelsNode.innerHTML = levels.length ? levels.map((row) => {
        const started = Number(row.started || 0);
        const completed = Number(row.completed || 0);
        return `<tr><td>${escapeHtml(row.level)}</td><td>${number(started)}</td><td>${number(completed)}</td><td>${started > 0 ? percent((completed / started) * 100) : '0%'}</td></tr>`;
      }).join('') : '<tr><td colspan="4">No level events recorded.</td></tr>';
    }
  }

  function renderReleases(payload) {
    const node = root.querySelector('[data-hga-releases]');
    if (!node) return;
    const rows = payload.releases || [];
    node.innerHTML = rows.length ? rows.map((row) => `<tr><td>v${number(row.version_number)} <span class="hga-pill">${escapeHtml(row.status)}</span></td><td>${number(row.game_loads)}</td><td>${number(row.runs_started)}</td><td>${number(row.runs_completed)}</td><td>${number(row.qualified_runs)}</td><td>${number(row.abandoned_runs)}</td><td>${number(row.average_score,1)}</td><td>${number(row.rewards_delivered)}</td><td>${number(row.diagnostic_groups)}</td></tr>`).join('') : '<tr><td colspan="9">No releases found.</td></tr>';
  }

  function renderHealth(payload) {
    const health = payload.health || {};
    const readiness = health.readiness || {};
    const database = health.database || {};
    const startup = health.startup || {};
    const node = root.querySelector('[data-hga-health]');
    if (node) {
      const items = [
        ['Game ZIP', readiness.release_ready, readiness.release_ready ? 'Ready' : 'Required'],
        ['Distribution integration', readiness.integration_ready, readiness.integration_ready ? 'Ready' : 'Required'],
        ['Signed webhook', readiness.webhook_secret_ready, readiness.webhook_secret_ready ? 'Ready' : 'Required'],
        ['Isolated database', readiness.database_ready, database.status || 'Required'],
        ['Average startup', Number(startup.average_ms || 0) < 3000, startup.samples ? duration(startup.average_ms) : 'No samples'],
        ['Maximum startup', Number(startup.maximum_ms || 0) < 10000, startup.samples ? duration(startup.maximum_ms) : 'No samples']
      ];
      node.innerHTML = items.map(([label, good, value]) => `<div class="hga-health-item"><span>${escapeHtml(label)}</span><strong class="${good ? 'is-good' : 'is-warning'}">${escapeHtml(value)}</strong></div>`).join('');
    }
    renderBars('[data-hga-health-categories]', health.open_categories || []);
  }

  function diagnosticCard(item) {
    const sample = item.sample || {};
    const stack = sample.stack || '';
    const context = sample.context && typeof sample.context === 'object' ? JSON.stringify(sample.context, null, 2) : '';
    const details = [stack, context].filter(Boolean).join('\n\n');
    const actions = item.status === 'open'
      ? `<button type="button" data-hga-diagnostic-action="resolved" data-diagnostic-id="${escapeHtml(item.public_id)}">Mark resolved</button><button type="button" data-hga-diagnostic-action="ignored" data-diagnostic-id="${escapeHtml(item.public_id)}">Ignore</button>`
      : `<button type="button" data-hga-diagnostic-action="open" data-diagnostic-id="${escapeHtml(item.public_id)}">Reopen</button>`;
    return `<article class="hga-diagnostic" data-severity="${escapeHtml(item.severity)}">
      <i class="hga-diagnostic-mark"></i>
      <div class="hga-diagnostic-main">
        <div class="hga-diagnostic-head"><strong>${escapeHtml(item.title)}</strong><span class="hga-pill is-${escapeHtml(item.severity)}">${escapeHtml(item.severity)}</span><span class="hga-pill is-${escapeHtml(item.status)}">${escapeHtml(item.status)}</span></div>
        <p>${escapeHtml(item.message)}</p>
        <div class="hga-diagnostic-meta"><span>${escapeHtml(String(item.category || '').replaceAll('_',' '))}</span><span>Release ${item.release_version ? `v${number(item.release_version)}` : 'unknown'}</span><span>${escapeHtml(item.browser_family || 'Other')}</span><span>${number(item.occurrence_count)} occurrences</span><span>${number(item.affected_players)} players</span><span>Last seen ${escapeHtml(dateTime(item.last_seen_at))}</span></div>
        ${details ? `<details><summary>Technical sample</summary><pre class="hga-diagnostic-details">${escapeHtml(details)}</pre></details>` : ''}
      </div>
      <div class="hga-diagnostic-actions">${actions}</div>
    </article>`;
  }

  function renderDiagnostics(payload) {
    const node = root.querySelector('[data-hga-diagnostics]');
    const empty = root.querySelector('[data-hga-diagnostics-empty]');
    const count = root.querySelector('[data-hga-diagnostic-count]');
    const rows = payload.diagnostics || [];
    if (node) node.innerHTML = rows.map(diagnosticCard).join('');
    if (empty) empty.hidden = rows.length > 0;
    if (count) count.textContent = `${number(rows.length)} groups`;
  }

  function render(payload) {
    state.payload = payload;
    renderHeader(payload);
    renderReleaseOptions(payload);
    renderSummary(payload);
    renderChart(payload.timeseries || []);
    renderBars('[data-hga-breakdown="devices"]', payload.breakdowns?.devices || []);
    renderBars('[data-hga-breakdown="browsers"]', payload.breakdowns?.browsers || []);
    renderBars('[data-hga-breakdown="viewports"]', payload.breakdowns?.viewports || []);
    renderFunnels(payload);
    renderReleases(payload);
    renderHealth(payload);
    renderDiagnostics(payload);
    updateExportLink();
  }

  async function load() {
    if (state.loading || !config.gameId || !config.apiUrl) return;
    state.loading = true;
    if (refresh) { refresh.disabled = true; refresh.textContent = 'Loading…'; }
    setNotice('');
    try {
      const response = await fetch(queryUrl().toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      render(await parseResponse(response));
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Unable to load hosted game analytics.');
    } finally {
      state.loading = false;
      if (refresh) { refresh.disabled = false; refresh.textContent = 'Refresh'; }
    }
  }

  async function updateDiagnostic(button) {
    const diagnosticId = String(button.dataset.diagnosticId || '');
    const status = String(button.dataset.hgaDiagnosticAction || 'resolved');
    if (!diagnosticId || !['open','resolved','ignored'].includes(status)) return;
    button.disabled = true;
    try {
      const response = await fetch(config.apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ csrf_token: config.csrf, game_id: config.gameId, diagnostic_id: diagnosticId, status })
      });
      await parseResponse(response);
      await load();
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Unable to update diagnostic status.');
      button.disabled = false;
    }
  }

  [days, release, diagnosticStatus, severity].forEach((field) => field?.addEventListener('change', () => { updateExportLink(); void load(); }));
  refresh?.addEventListener('click', () => void load());
  root.addEventListener('click', (event) => {
    const button = event.target.closest('[data-hga-diagnostic-action]');
    if (button) void updateDiagnostic(button);
  });

  updateExportLink();
  void load();
})();
