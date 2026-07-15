(() => {
  'use strict';

  const config = window.MicrogifterHostedGameShell || {};
  const iframe = document.getElementById(String(config.iframeId || 'hosted-game-frame'));
  if (!iframe) return;

  const channel = 'microgifter-hosted-game';
  const bridgeToken = String(config.bridgeToken || '');
  const overlay = document.querySelector('[data-hg-shell-overlay]');
  const overlayTitle = document.querySelector('[data-hg-shell-title]');
  const overlayText = document.querySelector('[data-hg-shell-text]');
  const overlayAction = document.querySelector('[data-hg-shell-action]');
  const statusNode = document.querySelector('[data-hg-shell-status]');
  const fullscreenButton = document.querySelector('[data-hg-fullscreen]');
  const allowedActions = new Set([
    'session', 'connect', 'start', 'complete', 'status', 'abandon', 'event', 'telemetry',
    'state_load', 'state_save', 'score_submit', 'leaderboard', 'track'
  ]);
  let session = null;
  let childReady = false;

  function setStatus(message, type = 'info') {
    if (!statusNode) return;
    statusNode.textContent = String(message || '');
    statusNode.dataset.type = type;
  }

  function showOverlay(title, text, label = '', handler = null) {
    if (!overlay) return;
    overlay.hidden = false;
    if (overlayTitle) overlayTitle.textContent = title;
    if (overlayText) overlayText.textContent = text;
    if (overlayAction) {
      overlayAction.hidden = !label;
      overlayAction.textContent = label;
      overlayAction.onclick = typeof handler === 'function' ? handler : null;
    }
  }

  function hideOverlay() {
    if (overlay) overlay.hidden = true;
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

  function sendSession() {
    if (!childReady || !session) return;
    sendToGame({ type: 'session', payload: session });
  }

  async function parseResponse(response) {
    const payload = await response.json().catch(() => ({}));
    const data = payload && typeof payload.data === 'object' ? payload.data : payload;
    if (!response.ok || payload.ok === false) {
      throw new Error(String(payload.message || data.message || 'Hosted game request failed.'));
    }
    return data;
  }

  async function loadSession() {
    const url = new URL(String(config.runtimeUrl || '/api/hosted-games/runtime.php'), window.location.origin);
    url.searchParams.set('slug', String(config.slug || ''));
    const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    session = await parseResponse(response);
    renderSession();
    sendSession();
    return session;
  }

  async function runtime(action, payload = {}) {
    const response = await fetch(String(config.runtimeUrl || '/api/hosted-games/runtime.php'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        action,
        slug: String(config.slug || ''),
        csrf_token: String(config.csrfToken || ''),
        sdk_version: String(config.sdkVersion || '1.1.0'),
        ...payload
      })
    });
    return parseResponse(response);
  }

  async function telemetry(payload = {}) {
    const eventType = String(payload.event_type || '').trim();
    if (!eventType) throw new Error('Hosted game telemetry event type is required.');
    const response = await fetch(String(config.telemetryUrl || '/api/hosted-games/telemetry.php'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        slug: String(config.slug || ''),
        csrf_token: String(config.csrfToken || ''),
        release_id: config.releaseId || null,
        release_version: config.releaseVersion || null,
        sdk_version: String(payload.sdk_version || config.sdkVersion || '1.1.0'),
        game_version: String(payload.game_version || config.manifest?.version || ''),
        ...payload
      })
    });
    return parseResponse(response);
  }

  async function connectPlayer() {
    if (!session?.player?.signed_in) {
      window.location.assign(String(config.signInUrl || '/signin.php'));
      return;
    }
    showOverlay('Connecting your Inbox', 'Microgifter is creating the game-specific reward connection.', '', null);
    setStatus('Connecting player…');
    try {
      await runtime('connect');
      await loadSession();
      setStatus('Player connected', 'success');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Connection failed.';
      showOverlay('Connection failed', message, 'Try again', connectPlayer);
      setStatus(message, 'error');
    }
  }

  function renderSession() {
    if (!session) return;
    if (!session.ready) {
      showOverlay('Game setup incomplete', 'This game is not ready for live play yet. The merchant or Microgifter Admin must finish setup.');
      setStatus('Setup required', 'warning');
      return;
    }
    if (!session.player?.signed_in) {
      showOverlay('Sign in to play', 'Use your Microgifter account so earned rewards can be delivered to your Inbox.', 'Sign in with Microgifter', () => {
        window.location.assign(String(config.signInUrl || '/signin.php'));
      });
      setStatus('Sign in required', 'warning');
      return;
    }
    if (!session.player?.connected) {
      showOverlay('Connect this game', 'Approve this game to deliver earned rewards to your Microgifter Inbox.', 'Connect & play', connectPlayer);
      setStatus('Inbox connection required', 'warning');
      return;
    }
    hideOverlay();
    setStatus(`Ready · ${session.player.display_name || 'Player'}`, 'success');
  }

  function validMessage(message) {
    if (!message || typeof message !== 'object') return false;
    if (message.channel !== channel || message.direction !== 'game-to-shell') return false;
    if (String(message.bridgeToken || '') !== bridgeToken) return false;
    if (String(message.slug || config.slug || '') !== String(config.slug || '')) return false;
    try {
      return JSON.stringify(message).length <= 131072;
    } catch {
      return false;
    }
  }

  async function handleRequest(message) {
    const requestId = String(message.requestId || '');
    const action = String(message.action || '');
    const payload = message.payload && typeof message.payload === 'object' ? message.payload : {};
    if (!requestId || !allowedActions.has(action)) {
      sendToGame({ requestId, ok: false, error: 'Unsupported Microgifter game bridge action.' });
      return;
    }
    try {
      let result;
      if (action === 'telemetry') {
        result = await telemetry(payload);
      } else if (action === 'session') {
        result = await loadSession();
      } else {
        if (!session) await loadSession();
        if (!session?.player?.signed_in) throw new Error('Sign in to Microgifter before using this game feature.');
        if (!session?.player?.connected && action !== 'connect') await connectPlayer();
        if (!session?.player?.connected && action !== 'connect') throw new Error('Connect this game to your Microgifter Inbox before continuing.');
        result = await runtime(action, payload);
        if (action === 'connect') await loadSession();
      }
      sendToGame({ requestId, ok: true, payload: result });
    } catch (error) {
      const messageText = error instanceof Error ? error.message : 'Hosted game request failed.';
      sendToGame({ requestId, ok: false, error: messageText });
      if (action !== 'telemetry') setStatus(messageText, 'error');
    }
  }

  window.addEventListener('message', (event) => {
    if (event.source !== iframe.contentWindow) return;
    const message = event.data;
    if (!validMessage(message)) return;
    if (message.type === 'child-ready') {
      childReady = true;
      sendToGame({
        type: 'shell-ready',
        payload: {
          sdkVersion: String(config.sdkVersion || '1.1.0'),
          releaseId: config.releaseId || null,
          releaseVersion: config.releaseVersion || null,
          manifest: config.manifest || null
        }
      });
      sendSession();
      return;
    }
    if (message.action === 'open_inbox') {
      window.location.assign(String(config.inboxUrl || '/inbox.php'));
      return;
    }
    if (message.action === 'sign_in') {
      window.location.assign(String(config.signInUrl || '/signin.php'));
      return;
    }
    void handleRequest(message);
  });

  if (fullscreenButton) {
    fullscreenButton.addEventListener('click', async () => {
      try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else await document.documentElement.requestFullscreen();
      } catch {
        setStatus('Fullscreen is unavailable in this browser.', 'warning');
      }
    });
  }

  window.addEventListener('pagehide', () => {
    sendToGame({ type: 'shell-closing' });
  });

  loadSession().catch((error) => {
    const message = error instanceof Error ? error.message : 'Game unavailable.';
    showOverlay('Game unavailable', message, 'Reload', () => window.location.reload());
    setStatus(message, 'error');
  });
})();
