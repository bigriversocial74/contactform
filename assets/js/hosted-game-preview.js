(() => {
  'use strict';

  const config = window.MicrogifterHostedGamePreview || {};
  const iframe = document.getElementById(String(config.iframeId || 'hosted-game-preview-frame'));
  const app = document.querySelector('[data-preview-app]');
  if (!iframe || !app) return;

  const channel = 'microgifter-hosted-game';
  const bridgeToken = String(config.bridgeToken || '');
  const allowedActions = new Set([
    'session', 'connect', 'start', 'complete', 'status', 'abandon', 'event', 'telemetry',
    'state_load', 'state_save', 'score_submit', 'leaderboard', 'track'
  ]);
  const statusNode = document.querySelector('[data-preview-status]');
  const listNode = document.querySelector('[data-console-list]');
  const stage = document.querySelector('[data-viewport-stage]');
  const records = [];
  let session = null;
  let childReady = false;
  let activeTab = 'events';
  let lastSequence = 0;
  let pollTimer = 0;

  function setStatus(message, kind = 'info') {
    if (!statusNode) return;
    statusNode.textContent = String(message || '');
    statusNode.dataset.kind = kind;
  }

  function sendToGame(message) {
    if (!iframe.contentWindow) return;
    iframe.contentWindow.postMessage({
      channel,
      direction: 'shell-to-game',
      bridgeToken,
      sdkVersion: String(config.sdkVersion || '1.1.0'),
      ...message
    }, '*');
  }

  async function parseResponse(response) {
    const payload = await response.json().catch(() => ({}));
    const data = payload && typeof payload.data === 'object' ? payload.data : payload;
    if (!response.ok || payload.ok === false) throw new Error(String(payload.message || data.message || 'Preview request failed.'));
    return data;
  }

  async function loadSession() {
    const url = new URL(String(config.runtimeUrl), window.location.origin);
    url.searchParams.set('session_id', String(config.sessionId || ''));
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    session = await parseResponse(response);
    if (childReady) sendToGame({ type: 'session', payload: session });
    setStatus(`Test player ready · release v${session?.game?.release_version || ''}`, 'success');
    return session;
  }

  async function runtime(action, payload = {}) {
    const started = performance.now();
    try {
      const response = await fetch(String(config.runtimeUrl), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          session_id: String(config.sessionId || ''),
          action,
          csrf_token: String(config.csrfToken || ''),
          sdk_version: String(config.sdkVersion || '1.1.0'),
          ...payload
        })
      });
      return await parseResponse(response);
    } finally {
      if (performance.now() - started > 1500) setStatus(`Slow preview action: ${action}`, 'warning');
    }
  }

  function validMessage(message) {
    if (!message || typeof message !== 'object') return false;
    if (message.channel !== channel || message.direction !== 'game-to-shell') return false;
    if (String(message.bridgeToken || '') !== bridgeToken) return false;
    if (String(message.slug || config.slug || '') !== String(config.slug || '')) return false;
    try { return JSON.stringify(message).length <= 131072; } catch { return false; }
  }

  async function handleRequest(message) {
    const requestId = String(message.requestId || '');
    const action = String(message.action || '');
    const payload = message.payload && typeof message.payload === 'object' ? message.payload : {};
    if (!requestId || !allowedActions.has(action)) {
      sendToGame({ requestId, ok: false, error: 'Unsupported preview bridge action.' });
      return;
    }
    try {
      let result;
      if (action === 'session') {
        result = await loadSession();
      } else if (action === 'telemetry') {
        const telemetryType = String(payload.event_type || 'telemetry_event').toLowerCase().replace(/[^a-z0-9_.:-]/g, '_').slice(0, 120);
        result = await runtime('event', {
          event_type: telemetryType,
          event: {
            ...(payload.event && typeof payload.event === 'object' ? payload.event : {}),
            client: payload.client && typeof payload.client === 'object' ? payload.client : {},
            sdk_version: payload.sdk_version || null,
            game_version: payload.game_version || null,
            telemetry: true
          },
          run_id: payload.run_id || '',
          run_token: payload.run_token || ''
        });
      } else {
        result = await runtime(action, payload);
      }
      sendToGame({ requestId, ok: true, payload: result });
    } catch (error) {
      const text = error instanceof Error ? error.message : 'Preview request failed.';
      sendToGame({ requestId, ok: false, error: text });
      setStatus(text, 'error');
    }
  }

  window.addEventListener('message', (event) => {
    if (event.source !== iframe.contentWindow) return;
    const message = event.data;
    if (!validMessage(message)) return;
    if (message.type === 'child-ready') {
      childReady = true;
      sendToGame({ type: 'shell-ready', payload: { sdkVersion: String(config.sdkVersion || '1.1.0'), manifest: config.manifest || null, testMode: true } });
      if (session) sendToGame({ type: 'session', payload: session });
      else void loadSession();
      return;
    }
    if (message.action === 'open_inbox') {
      setStatus('Inbox navigation is disabled in test mode.', 'warning');
      return;
    }
    if (message.action === 'sign_in') {
      setStatus('The protected test player is already signed in.', 'info');
      return;
    }
    void handleRequest(message);
  });

  function category(record) {
    if (record.severity === 'error' || /error|failed/i.test(record.event_type)) return 'errors';
    if (record.event_type === 'sdk_request' || record.action) return 'requests';
    return 'events';
  }

  function updateCounts() {
    ['events', 'requests', 'errors'].forEach((key) => {
      const node = document.querySelector(`[data-count="${key}"]`);
      if (node) node.textContent = String(records.filter((record) => category(record) === key || (key === 'events' && category(record) !== 'requests')).length);
    });
  }

  function render() {
    const filtered = records.filter((record) => {
      const value = category(record);
      if (activeTab === 'events') return value !== 'requests';
      return value === activeTab;
    });
    if (!filtered.length) {
      listNode.innerHTML = '<div class="hgp-empty">No console records in this view yet.</div>';
      updateCounts();
      return;
    }
    listNode.innerHTML = filtered.slice().reverse().map((record) => {
      const detail = record.event && Object.keys(record.event).length ? JSON.stringify(record.event, null, 2) : (record.action ? `Action: ${record.action}${record.duration_ms !== null ? ` · ${record.duration_ms} ms` : ''}` : '');
      const time = String(record.created_at || '').split(' ').pop() || '';
      return `<article class="hgp-log" data-severity="${escapeHtml(record.severity || 'info')}"><time>${escapeHtml(time)}</time><div><strong>${escapeHtml(record.event_type || 'event')}</strong>${detail ? `<p>${escapeHtml(detail)}</p>` : ''}</div></article>`;
    }).join('');
    updateCounts();
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  }

  async function pollEvents() {
    try {
      const url = new URL(String(config.eventsUrl), window.location.origin);
      url.searchParams.set('session_id', String(config.sessionId || ''));
      url.searchParams.set('after', String(lastSequence));
      const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await parseResponse(response);
      (Array.isArray(data.events) ? data.events : []).forEach((record) => {
        records.push(record);
        lastSequence = Math.max(lastSequence, Number(record.sequence || 0));
      });
      if (records.length > 1000) records.splice(0, records.length - 1000);
      render();
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Console polling failed.', 'error');
    } finally {
      pollTimer = window.setTimeout(pollEvents, 1200);
    }
  }

  document.querySelectorAll('[data-console-tab]').forEach((button) => {
    button.addEventListener('click', () => {
      activeTab = String(button.dataset.consoleTab || 'events');
      document.querySelectorAll('[data-console-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
      render();
    });
  });

  document.querySelectorAll('[data-viewport]').forEach((button) => {
    button.addEventListener('click', () => {
      const viewport = String(button.dataset.viewport || 'desktop');
      stage.dataset.viewportStage = viewport;
      document.querySelectorAll('[data-viewport]').forEach((item) => item.classList.toggle('is-active', item === button));
      setStatus(`${viewport[0].toUpperCase()}${viewport.slice(1)} viewport`, 'info');
    });
  });

  function reloadFrame() {
    childReady = false;
    const current = new URL(iframe.src, window.location.origin);
    current.searchParams.set('_reload', String(Date.now()));
    iframe.src = current.toString();
  }

  document.querySelector('[data-preview-reload]')?.addEventListener('click', () => {
    reloadFrame();
    setStatus('Reloading release preview…');
  });

  document.querySelector('[data-preview-clear]')?.addEventListener('click', () => {
    records.length = 0;
    render();
  });

  document.querySelector('[data-preview-reset]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    if (!window.confirm('Reset all runs, scores, events, and saved state for this preview session?')) return;
    button.disabled = true;
    try {
      const response = await fetch(String(config.resetUrl), {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ session_id: String(config.sessionId || ''), csrf_token: String(config.csrfToken || '') })
      });
      await parseResponse(response);
      records.length = 0;
      lastSequence = 0;
      render();
      reloadFrame();
      setStatus('Preview test data reset.', 'success');
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Unable to reset preview data.', 'error');
    } finally { button.disabled = false; }
  });

  iframe.addEventListener('load', () => setStatus('Release loaded. Waiting for game SDK…'));
  window.addEventListener('pagehide', () => {
    if (pollTimer) window.clearTimeout(pollTimer);
    sendToGame({ type: 'shell-closing' });
  });

  void loadSession();
  void pollEvents();
})();
