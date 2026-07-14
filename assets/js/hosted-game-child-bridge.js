(() => {
  'use strict';

  const config = window.MicrogifterHostedGameConfig || {};
  const channel = 'microgifter-hosted-game';
  const pending = new Map();
  const sessionWaiters = new Set();
  let sequence = 0;
  let cachedSession = null;
  let handshakeTimer = 0;

  function post(message) {
    window.parent.postMessage({
      channel,
      direction: 'game-to-shell',
      slug: String(config.slug || ''),
      ...message
    }, '*');
  }

  function announce() {
    post({ type: 'child-ready' });
  }

  function startHandshake() {
    announce();
    if (cachedSession || handshakeTimer) return;
    handshakeTimer = window.setInterval(() => {
      if (cachedSession) {
        window.clearInterval(handshakeTimer);
        handshakeTimer = 0;
        return;
      }
      announce();
    }, 750);
  }

  function request(action, payload = {}) {
    return new Promise((resolve, reject) => {
      const requestId = `hg_${Date.now()}_${++sequence}_${Math.random().toString(36).slice(2, 10)}`;
      const timeout = window.setTimeout(() => {
        pending.delete(requestId);
        reject(new Error('Microgifter game bridge request timed out.'));
      }, 30000);
      pending.set(requestId, { resolve, reject, timeout });
      post({ requestId, action, payload });
    });
  }

  function command(action, payload = {}) {
    post({ action, payload, command: true });
  }

  function waitForSession() {
    if (cachedSession) return Promise.resolve(cachedSession);
    startHandshake();
    return new Promise((resolve, reject) => {
      const waiter = { resolve, reject, timeout: 0 };
      waiter.timeout = window.setTimeout(() => {
        sessionWaiters.delete(waiter);
        reject(new Error('Microgifter session handshake timed out.'));
      }, 30000);
      sessionWaiters.add(waiter);
    });
  }

  function receiveSession(payload) {
    cachedSession = payload || null;
    if (handshakeTimer) {
      window.clearInterval(handshakeTimer);
      handshakeTimer = 0;
    }
    sessionWaiters.forEach((waiter) => {
      window.clearTimeout(waiter.timeout);
      waiter.resolve(cachedSession);
    });
    sessionWaiters.clear();
    window.dispatchEvent(new CustomEvent('microgifter:session', { detail: cachedSession }));
  }

  window.addEventListener('message', (event) => {
    if (event.source !== window.parent) return;
    const message = event.data;
    if (!message || message.channel !== channel || message.direction !== 'shell-to-game') return;

    if (message.type === 'session') {
      receiveSession(message.payload);
      return;
    }

    const requestId = String(message.requestId || '');
    const item = pending.get(requestId);
    if (!item) return;
    pending.delete(requestId);
    window.clearTimeout(item.timeout);
    if (message.ok) item.resolve(message.payload);
    else item.reject(new Error(String(message.error || 'Microgifter game bridge request failed.')));
  });

  const api = Object.freeze({
    version: '1.0.0',
    game: Object.freeze({
      id: String(config.gameId || ''),
      slug: String(config.slug || ''),
      name: String(config.name || '')
    }),
    ready: waitForSession,
    getPlayer: async () => {
      const session = await waitForSession();
      return session.player || session;
    },
    getCachedSession: () => cachedSession,
    connectPlayer: () => request('connect'),
    startRun: (metadata = {}) => request('start', { metadata }),
    completeRun: (options = {}) => request('complete', {
      run_id: options.runId || options.run_id || '',
      run_token: options.runToken || options.run_token || '',
      qualified: Boolean(options.qualified),
      score: options.score ?? null,
      result: options.result && typeof options.result === 'object' ? options.result : {}
    }),
    getRun: (runId) => request('status', { run_id: String(runId || '') }),
    loadState: (key = 'default') => request('state_load', { key }),
    saveState: (key = 'default', state = null) => request('state_save', { key, state }),
    submitScore: (options = {}) => request('score_submit', {
      run_id: options.runId || options.run_id || '',
      run_token: options.runToken || options.run_token || '',
      score: options.score,
      metadata: options.metadata && typeof options.metadata === 'object' ? options.metadata : {}
    }),
    getLeaderboard: (limit = 20) => request('leaderboard', { limit }),
    track: (eventType, event = {}) => request('track', { event_type: String(eventType || ''), event }),
    openInbox: () => command('open_inbox'),
    signIn: () => command('sign_in')
  });

  Object.defineProperty(window, 'MicrogifterGame', {
    configurable: false,
    enumerable: true,
    writable: false,
    value: api
  });

  startHandshake();
  window.dispatchEvent(new CustomEvent('microgifter:bridge-ready', { detail: api.game }));
})();
