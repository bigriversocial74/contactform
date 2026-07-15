(() => {
  'use strict';

  const config = window.MicrogifterHostedGameConfig || {};
  const channel = 'microgifter-hosted-game';
  const bridgeToken = String(config.bridgeToken || '');
  const manifest = config.manifest && typeof config.manifest === 'object' ? Object.freeze(config.manifest) : Object.freeze({});
  const pending = new Map();
  const sessionWaiters = new Set();
  const standardEvents = new Set([
    'game_loaded', 'run_started', 'level_started', 'score_updated', 'level_completed',
    'player_qualified', 'run_completed', 'run_abandoned', 'runtime_error'
  ]);
  const capabilities = new Set(Array.isArray(manifest.capabilities) ? manifest.capabilities.map(String) : []);
  let sequence = 0;
  let cachedSession = null;
  let handshakeTimer = 0;
  let loadedEventSent = false;
  let activeRun = null;
  let currentScore = null;
  let qualified = false;
  let qualificationResult = {};

  function post(message) {
    window.parent.postMessage({
      channel,
      direction: 'game-to-shell',
      bridgeToken,
      slug: String(config.slug || ''),
      sdkVersion: '1.1.0',
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

  function requireCapability(capability) {
    const compliance = String(manifest.standard?.compliance || 'legacy');
    if (compliance === 'standard' && !capabilities.has(capability)) {
      throw new Error(`This game package did not declare the ${capability} capability.`);
    }
  }

  function request(action, payload = {}) {
    return new Promise((resolve, reject) => {
      let encoded = '';
      try {
        encoded = JSON.stringify(payload);
      } catch {
        reject(new Error('Microgifter game bridge payload must be JSON-compatible.'));
        return;
      }
      if (encoded.length > 131072) {
        reject(new Error('Microgifter game bridge payload is too large.'));
        return;
      }
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

  async function emitStandardEvent(eventType, event = {}, run = activeRun) {
    requireCapability('events');
    const type = String(eventType || '').toLowerCase().trim();
    if (!standardEvents.has(type)) throw new Error('Unsupported Hosted Game Standard event.');
    const payload = {
      event_type: type,
      event: event && typeof event === 'object' ? event : {}
    };
    if (run?.run_id && run?.run_token) {
      payload.run_id = String(run.run_id);
      payload.run_token = String(run.run_token);
    }
    return request('event', payload);
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
    if (!loadedEventSent && cachedSession?.player?.signed_in && cachedSession?.player?.connected && capabilities.has('events')) {
      loadedEventSent = true;
      void emitStandardEvent('game_loaded', {
        game_version: String(manifest.version || ''),
        sdk_version: '1.1.0'
      }, null).catch(() => { loadedEventSent = false; });
    }
  }

  function validIncoming(message) {
    if (!message || typeof message !== 'object') return false;
    if (message.channel !== channel || message.direction !== 'shell-to-game') return false;
    if (String(message.bridgeToken || '') !== bridgeToken) return false;
    try {
      return JSON.stringify(message).length <= 131072;
    } catch {
      return false;
    }
  }

  window.addEventListener('message', (event) => {
    if (event.source !== window.parent) return;
    const message = event.data;
    if (!validIncoming(message)) return;

    if (message.type === 'session') {
      receiveSession(message.payload);
      return;
    }
    if (message.type === 'shell-ready') {
      window.dispatchEvent(new CustomEvent('microgifter:shell-ready', { detail: message.payload || {} }));
      return;
    }
    if (message.type === 'shell-closing') {
      window.dispatchEvent(new CustomEvent('microgifter:shell-closing'));
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

  async function startRun(metadata = {}) {
    requireCapability('runs');
    const result = await request('start', { metadata: metadata && typeof metadata === 'object' ? metadata : {} });
    activeRun = result?.run || null;
    currentScore = null;
    qualified = false;
    qualificationResult = {};
    return result;
  }

  async function updateScore(score, event = {}) {
    requireCapability('events');
    const integerRequired = manifest.scoring?.integer !== false;
    const numericScore = Number(score);
    if (!Number.isFinite(numericScore) || (integerRequired && !Number.isInteger(numericScore))) {
      throw new Error(integerRequired ? 'Score must be an integer.' : 'Score must be numeric.');
    }
    currentScore = numericScore;
    await emitStandardEvent('score_updated', { ...event, score: numericScore }, activeRun);
    window.dispatchEvent(new CustomEvent('microgifter:score', { detail: { score: numericScore } }));
    return { score: numericScore };
  }

  async function qualify(result = {}) {
    requireCapability('runs');
    qualified = true;
    qualificationResult = result && typeof result === 'object' ? result : {};
    if (capabilities.has('events')) await emitStandardEvent('player_qualified', qualificationResult, activeRun);
    window.dispatchEvent(new CustomEvent('microgifter:qualified', { detail: qualificationResult }));
    return { qualified: true };
  }

  async function complete(options = {}) {
    requireCapability('runs');
    const run = options.run || activeRun;
    if (!run?.run_id || !run?.run_token) throw new Error('Start a Microgifter game run before completing it.');
    const score = options.score ?? currentScore;
    const isQualified = options.qualified ?? qualified;
    const result = {
      ...qualificationResult,
      ...(options.result && typeof options.result === 'object' ? options.result : {})
    };
    const response = await request('complete', {
      run_id: String(run.run_id),
      run_token: String(run.run_token),
      qualified: Boolean(isQualified),
      score: score ?? null,
      result
    });
    activeRun = null;
    qualified = false;
    qualificationResult = {};
    return response;
  }

  async function abandonRun(options = {}) {
    requireCapability('runs');
    const run = options.run || activeRun;
    if (!run?.run_id || !run?.run_token) return { abandoned: false };
    const response = await request('abandon', {
      run_id: String(run.run_id),
      run_token: String(run.run_token),
      reason: String(options.reason || 'player_exit').slice(0, 120),
      result: options.result && typeof options.result === 'object' ? options.result : {}
    });
    activeRun = null;
    qualified = false;
    qualificationResult = {};
    return response;
  }

  const api = Object.freeze({
    version: '1.1.0',
    standardVersion: '1.0.0',
    game: Object.freeze({
      id: String(config.gameId || ''),
      slug: String(config.slug || ''),
      name: String(config.name || ''),
      manifest
    }),
    ready: waitForSession,
    getManifest: () => manifest,
    getPlayer: async () => {
      requireCapability('player');
      const session = await waitForSession();
      return session.player || session;
    },
    getProgram: async () => {
      const session = await waitForSession();
      return session.program || null;
    },
    getReward: async () => {
      const session = await waitForSession();
      return session.reward || null;
    },
    getCachedSession: () => cachedSession,
    getActiveRun: () => activeRun,
    connectPlayer: () => {
      requireCapability('player');
      return request('connect');
    },
    startRun,
    updateScore,
    emitEvent: emitStandardEvent,
    levelStarted: (level, event = {}) => emitStandardEvent('level_started', { ...event, level }, activeRun),
    levelCompleted: (level, event = {}) => emitStandardEvent('level_completed', { ...event, level }, activeRun),
    qualify,
    complete,
    abandonRun,
    reportError: (error, context = {}) => emitStandardEvent('runtime_error', {
      message: error instanceof Error ? error.message : String(error || 'Unknown runtime error'),
      name: error instanceof Error ? error.name : 'Error',
      context: context && typeof context === 'object' ? context : {}
    }, activeRun),
    completeRun: (options = {}) => request('complete', {
      run_id: options.runId || options.run_id || '',
      run_token: options.runToken || options.run_token || '',
      qualified: Boolean(options.qualified),
      score: options.score ?? null,
      result: options.result && typeof options.result === 'object' ? options.result : {}
    }),
    getRun: (runId) => request('status', { run_id: String(runId || '') }),
    loadState: (key = 'default') => {
      requireCapability('state');
      return request('state_load', { key });
    },
    saveState: (key = 'default', state = null) => {
      requireCapability('state');
      return request('state_save', { key, state });
    },
    submitScore: (options = {}) => {
      requireCapability('scores');
      return request('score_submit', {
        run_id: options.runId || options.run_id || activeRun?.run_id || '',
        run_token: options.runToken || options.run_token || activeRun?.run_token || '',
        score: options.score ?? currentScore,
        metadata: options.metadata && typeof options.metadata === 'object' ? options.metadata : {}
      });
    },
    getLeaderboard: (limit = 20) => {
      requireCapability('leaderboard');
      return request('leaderboard', { limit });
    },
    track: (eventType, event = {}) => {
      requireCapability('events');
      return request('track', { event_type: String(eventType || ''), event });
    },
    openInbox: () => {
      requireCapability('inbox');
      command('open_inbox');
    },
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
