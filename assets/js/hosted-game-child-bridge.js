(() => {
  'use strict';

  const config = window.MicrogifterHostedGameConfig || {};
  const channel = 'microgifter-hosted-game';
  const pending = new Map();
  let sequence = 0;
  let cachedSession = null;

  function request(action, payload = {}) {
    return new Promise((resolve, reject) => {
      const requestId = `hg_${Date.now()}_${++sequence}_${Math.random().toString(36).slice(2, 10)}`;
      const timeout = window.setTimeout(() => {
        pending.delete(requestId);
        reject(new Error('Microgifter game bridge request timed out.'));
      }, 30000);
      pending.set(requestId, { resolve, reject, timeout });
      window.parent.postMessage({
        channel,
        direction: 'game-to-shell',
        requestId,
        action,
        payload,
        slug: String(config.slug || '')
      }, '*');
    });
  }

  window.addEventListener('message', (event) => {
    if (event.source !== window.parent) return;
    const message = event.data;
    if (!message || message.channel !== channel || message.direction !== 'shell-to-game') return;

    if (message.type === 'session') {
      cachedSession = message.payload || null;
      window.dispatchEvent(new CustomEvent('microgifter:session', { detail: cachedSession }));
      return;
    }

    const item = pending.get(String(message.requestId || ''));
    if (!item) return;
    pending.delete(String(message.requestId));
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
    ready: async () => {
      const session = await request('session');
      cachedSession = session;
      return session;
    },
    getPlayer: async () => {
      const session = await request('session');
      cachedSession = session;
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
    openInbox: () => request('open_inbox'),
    signIn: () => request('sign_in')
  });

  Object.defineProperty(window, 'MicrogifterGame', {
    configurable: false,
    enumerable: true,
    writable: false,
    value: api
  });

  window.parent.postMessage({
    channel,
    direction: 'game-to-shell',
    type: 'child-ready',
    slug: String(config.slug || '')
  }, '*');
  window.dispatchEvent(new CustomEvent('microgifter:bridge-ready', { detail: api.game }));
})();
