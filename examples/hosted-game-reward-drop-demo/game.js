(() => {
  'use strict';

  const VERSION = '2.0.0';
  const TARGET = 8;
  const DURATION_SECONDS = 20;
  const MIN_PLAY_MS = 3500;
  const root = document.querySelector('[data-reward-drop]');
  if (!root) return;

  const ui = {
    arena: root.querySelector('[data-arena]'),
    overlay: root.querySelector('[data-overlay]'),
    overlayTitle: root.querySelector('[data-overlay-title]'),
    overlayCopy: root.querySelector('[data-overlay-copy]'),
    start: root.querySelector('[data-start]'),
    score: root.querySelector('[data-score]'),
    target: root.querySelector('[data-target]'),
    time: root.querySelector('[data-time]'),
    status: root.querySelector('[data-status]'),
    actions: root.querySelector('[data-result-actions]'),
    inbox: root.querySelector('[data-inbox]'),
    replay: root.querySelector('[data-replay]'),
    player: root.querySelector('[data-player]'),
    program: root.querySelector('[data-program]'),
    reward: root.querySelector('[data-reward]'),
    delivery: root.querySelector('[data-delivery]'),
    plays: root.querySelector('[data-plays]'),
    best: root.querySelector('[data-best]'),
    leaderboard: root.querySelector('[data-leaderboard]'),
    modeBadge: root.querySelector('[data-mode-badge]'),
    sdkVersion: root.querySelector('[data-sdk-version]')
  };

  const state = {
    session: null,
    player: null,
    program: null,
    reward: null,
    profile: { plays: 0, best_score: 0 },
    run: null,
    score: 0,
    active: false,
    finishing: false,
    startedAt: 0,
    deadlineAt: 0,
    spawnTimer: 0,
    clockTimer: 0,
    minimumTimer: 0,
    drops: []
  };

  function setStatus(message, type = '') {
    ui.status.textContent = String(message || '');
    ui.status.classList.toggle('is-success', type === 'success');
    ui.status.classList.toggle('is-error', type === 'error');
  }

  function setOverlay(title, copy, buttonText, enabled = true) {
    ui.overlay.hidden = false;
    ui.overlayTitle.textContent = title;
    ui.overlayCopy.textContent = copy;
    ui.start.textContent = buttonText;
    ui.start.disabled = !enabled;
  }

  function random(min, max) {
    return Math.random() * (max - min) + min;
  }

  function clearTimers() {
    [state.spawnTimer, state.clockTimer, state.minimumTimer].forEach((timer) => {
      if (timer) window.clearTimeout(timer);
    });
    state.spawnTimer = 0;
    state.clockTimer = 0;
    state.minimumTimer = 0;
  }

  function clearDrops() {
    state.drops.forEach((drop) => drop.remove());
    state.drops = [];
  }

  function updateHud() {
    ui.score.textContent = String(state.score);
    ui.target.textContent = String(TARGET);
    const seconds = state.active
      ? Math.max(0, Math.ceil((state.deadlineAt - Date.now()) / 1000))
      : DURATION_SECONDS;
    ui.time.textContent = `${seconds}s`;
  }

  function displayName(value, fallback) {
    const text = String(value || '').trim();
    return text || fallback;
  }

  function detectTestMode() {
    return Boolean(
      state.session?.runtime?.test_mode ||
      state.player?.test_player ||
      state.program?.mode === 'test'
    );
  }

  function renderContext() {
    const testMode = detectTestMode();
    ui.modeBadge.textContent = testMode ? 'Protected preview' : 'Live runtime';
    ui.modeBadge.classList.toggle('is-test', testMode);
    ui.modeBadge.classList.toggle('is-live', !testMode);
    ui.sdkVersion.textContent =
      `${MicrogifterGame.version} / Standard ${MicrogifterGame.standardVersion}`;
    ui.player.textContent = displayName(
      state.player?.display_name,
      state.player?.signed_in ? 'Signed-in player' : 'Sign-in required'
    );
    ui.program.textContent = displayName(
      state.program?.name || state.program?.id,
      testMode ? 'Test Distribution Program' : 'Program pending'
    );
    ui.reward.textContent = displayName(
      state.reward?.name || state.reward?.template_id,
      testMode ? 'Simulated reward' : 'Reward pending'
    );
    ui.delivery.textContent = testMode
      ? 'Simulated · no inventory'
      : 'Live Program delivery';
    ui.plays.textContent = String(Number(state.profile.plays || 0));
    ui.best.textContent = String(Number(state.profile.best_score || 0));
  }

  async function loadProfile() {
    try {
      const response = await MicrogifterGame.loadState('reward_drop_profile');
      if (response?.state && typeof response.state === 'object') {
        state.profile = {
          plays: Number(response.state.plays || 0),
          best_score: Number(response.state.best_score || 0)
        };
      }
    } catch (error) {
      await MicrogifterGame.reportError(error, {
        phase: 'load_profile'
      }).catch(() => {});
    }
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#039;',
      '"': '&quot;'
    }[char]));
  }

  async function refreshLeaderboard() {
    try {
      const response = await MicrogifterGame.getLeaderboard(5);
      const rows = Array.isArray(response?.leaderboard)
        ? response.leaderboard
        : [];
      ui.leaderboard.innerHTML = rows.length
        ? rows.map((row) =>
            `<li><span>#${Number(row.rank || 0)} ${escapeHtml(row.player || 'Player')}</span><b>${Number(row.score || 0)}</b></li>`
          ).join('')
        : '<li><span>No completed runs yet.</span></li>';
    } catch {
      ui.leaderboard.innerHTML =
        '<li><span>Leaderboard becomes available after a scored run.</span></li>';
    }
  }

  async function initialize() {
    if (!window.MicrogifterGame) return;
    try {
      state.session = await MicrogifterGame.ready();
      [state.player, state.program, state.reward] = await Promise.all([
        MicrogifterGame.getPlayer(),
        MicrogifterGame.getProgram(),
        MicrogifterGame.getReward()
      ]);
      await Promise.all([loadProfile(), refreshLeaderboard()]);
      renderContext();
      updateHud();

      if (!state.player?.signed_in) {
        setOverlay(
          'Sign in to play',
          'Use your Microgifter account to create a protected game run.',
          'Sign in with Microgifter'
        );
        setStatus('Microgifter sign-in is required.');
      } else if (!state.player?.connected) {
        setOverlay(
          'Connect your Inbox',
          'Approve this hosted game once so earned rewards can be delivered.',
          'Connect and play'
        );
        setStatus('Player connection is required.');
      } else {
        const label = detectTestMode()
          ? 'Start protected test'
          : 'Start Reward Drop';
        setOverlay(
          `Ready, ${displayName(state.player.display_name, 'player')}?`,
          `Collect ${TARGET} gifts before the timer reaches zero.`,
          label
        );
        setStatus(
          detectTestMode()
            ? 'Preview is isolated from live inventory.'
            : 'Ready for a live reward run.',
          'success'
        );
      }
    } catch (error) {
      setOverlay(
        'Runtime unavailable',
        error instanceof Error
          ? error.message
          : 'The Hosted Game SDK could not initialize.',
        'Unavailable',
        false
      );
      setStatus('Unable to initialize the game.', 'error');
      await MicrogifterGame.reportError(error, {
        phase: 'initialize',
        game_version: VERSION
      }).catch(() => {});
    }
  }

  async function ensurePlayerReady() {
    state.session = await MicrogifterGame.ready();
    state.player = await MicrogifterGame.getPlayer();
    if (!state.player?.signed_in) {
      MicrogifterGame.signIn();
      return false;
    }
    if (!state.player?.connected) {
      await MicrogifterGame.connectPlayer();
      state.session = await MicrogifterGame.ready();
      state.player = await MicrogifterGame.getPlayer();
    }
    return Boolean(state.player?.signed_in && state.player?.connected);
  }

  async function startGame() {
    if (state.active || state.finishing) return;
    ui.start.disabled = true;
    setStatus('Creating a protected SDK run…');
    try {
      if (!(await ensurePlayerReady())) return;
      const response = await MicrogifterGame.startRun({
        mode: 'reward_drop',
        clientVersion: VERSION,
        targetScore: TARGET,
        durationSeconds: DURATION_SECONDS
      });
      state.run = response?.run || null;
      if (!state.run) {
        throw new Error('The Hosted Game runtime did not return a run.');
      }
      state.score = 0;
      state.active = true;
      state.finishing = false;
      state.startedAt = Date.now();
      state.deadlineAt = state.startedAt + DURATION_SECONDS * 1000;
      ui.actions.hidden = true;
      ui.overlay.hidden = true;
      ui.delivery.textContent = detectTestMode()
        ? 'Test run active'
        : 'Live run active';
      updateHud();
      await MicrogifterGame.levelStarted(1, {
        mode: 'reward_drop',
        target: TARGET,
        duration_seconds: DURATION_SECONDS
      });
      setStatus(`Collect ${TARGET} gifts to qualify.`);
      spawnDrop();
      tickClock();
    } catch (error) {
      ui.start.disabled = false;
      setStatus(
        error instanceof Error ? error.message : 'Unable to start the game.',
        'error'
      );
      await MicrogifterGame.reportError(error, {
        phase: 'start',
        game_version: VERSION
      }).catch(() => {});
    }
  }

  function removeDrop(drop) {
    const index = state.drops.indexOf(drop);
    if (index >= 0) state.drops.splice(index, 1);
    drop.remove();
  }

  function spawnDrop() {
    if (!state.active) return;
    const drop = document.createElement('button');
    drop.type = 'button';
    drop.className = 'rd-drop';
    drop.setAttribute('aria-label', 'Collect gift');
    drop.textContent = '✦';
    drop.style.left = `${random(7, 84)}%`;
    drop.style.top = `${random(12, 78)}%`;
    drop.style.setProperty('--rotation', `${random(-12, 12)}deg`);
    drop.addEventListener('click', () => collectDrop(drop), { once: true });
    ui.arena.appendChild(drop);
    state.drops.push(drop);
    window.setTimeout(() => removeDrop(drop), 1400);
    state.spawnTimer = window.setTimeout(spawnDrop, random(360, 620));
  }

  function collectDrop(drop) {
    if (!state.active || drop.classList.contains('is-collected')) return;
    drop.classList.add('is-collected');
    state.score += 1;
    updateHud();
    void MicrogifterGame.updateScore(state.score, {
      target: TARGET,
      elapsed_ms: Date.now() - state.startedAt
    }).catch((error) =>
      MicrogifterGame.reportError(error, { phase: 'score_update' })
    );

    if (state.score >= TARGET) {
      setStatus(
        'Target reached. Verifying the minimum play window…',
        'success'
      );
      const wait = Math.max(
        0,
        MIN_PLAY_MS - (Date.now() - state.startedAt)
      );
      if (wait === 0) {
        void finishGame(true);
      } else if (!state.minimumTimer) {
        state.minimumTimer = window.setTimeout(
          () => finishGame(true),
          wait
        );
      }
    } else {
      setStatus(`Gift collected · ${TARGET - state.score} remaining.`);
    }
  }

  function tickClock() {
    if (!state.active) return;
    updateHud();
    if (Date.now() >= state.deadlineAt) {
      void finishGame(state.score >= TARGET);
      return;
    }
    state.clockTimer = window.setTimeout(tickClock, 200);
  }

  async function saveProfile() {
    state.profile.plays = Number(state.profile.plays || 0) + 1;
    state.profile.best_score = Math.max(
      Number(state.profile.best_score || 0),
      state.score
    );
    await MicrogifterGame.saveState('reward_drop_profile', {
      plays: state.profile.plays,
      best_score: state.profile.best_score,
      last_score: state.score,
      last_played_at: new Date().toISOString()
    });
    renderContext();
  }

  async function finishGame(qualified) {
    if (!state.active || state.finishing || !state.run) return;
    state.active = false;
    state.finishing = true;
    clearTimers();
    clearDrops();
    updateHud();
    setStatus(
      qualified
        ? 'Submitting qualified run through the SDK…'
        : 'Submitting completed run…'
    );

    try {
      const elapsedMs = Date.now() - state.startedAt;
      await MicrogifterGame.levelCompleted(1, {
        score: state.score,
        target: TARGET,
        qualified,
        elapsed_ms: elapsedMs
      });
      await MicrogifterGame.submitScore({
        score: state.score,
        metadata: {
          mode: 'reward_drop',
          target: TARGET,
          qualified
        }
      });
      if (qualified) {
        await MicrogifterGame.qualify({
          target: TARGET,
          achieved: state.score,
          elapsed_ms: elapsedMs,
          game_version: VERSION
        });
      }

      const response = await MicrogifterGame.complete({
        score: state.score,
        qualified,
        result: {
          mode: 'reward_drop',
          target: TARGET,
          duration_seconds: DURATION_SECONDS,
          elapsed_ms: elapsedMs,
          game_version: VERSION
        }
      });

      await saveProfile();
      await refreshLeaderboard();
      state.run = null;

      if (!qualified) {
        ui.delivery.textContent = 'Not qualified';
        setOverlay(
          'Time is up',
          `You collected ${state.score} of ${TARGET} gifts. Your score was saved.`,
          'Play again'
        );
        setStatus(
          'Run completed without issuing a reward.',
          'error'
        );
      } else if (response?.simulated || response?.run?.test_mode) {
        ui.delivery.textContent = 'Simulated delivered';
        setOverlay(
          'Test reward delivered',
          'The full SDK flow completed. No live inventory was consumed.',
          'Run another test'
        );
        setStatus(
          'Protected preview success · simulated reward delivered.',
          'success'
        );
        ui.actions.hidden = false;
      } else if (response?.reward_issued) {
        ui.delivery.textContent =
          response?.run?.reward_status || 'Reward issued';
        setOverlay(
          'Reward earned',
          'Your Distribution Program reward was issued through the Hosted Game runtime.',
          'Play again'
        );
        setStatus(
          'Reward earned and sent to your Microgifter Inbox.',
          'success'
        );
        ui.actions.hidden = false;
      } else {
        ui.delivery.textContent = 'Completed';
        setOverlay(
          'Run complete',
          'The game completed successfully. Review release diagnostics for delivery details.',
          'Play again'
        );
        setStatus('Run completed successfully.', 'success');
      }
    } catch (error) {
      setOverlay(
        'Completion needs attention',
        error instanceof Error
          ? error.message
          : 'The run could not be completed.',
        'Try again'
      );
      setStatus('The SDK reported a completion error.', 'error');
      await MicrogifterGame.reportError(error, {
        phase: 'complete',
        score: state.score,
        target: TARGET,
        game_version: VERSION
      }).catch(() => {});
    } finally {
      state.finishing = false;
      ui.start.disabled = false;
    }
  }

  ui.start.addEventListener('click', () => void startGame());
  ui.replay.addEventListener('click', () => {
    ui.actions.hidden = true;
    void startGame();
  });
  ui.inbox.addEventListener('click', () => MicrogifterGame.openInbox());

  window.addEventListener('pagehide', () => {
    if (state.run && state.active) {
      void MicrogifterGame.abandonRun({
        reason: 'page_hidden',
        result: {
          score: state.score,
          target: TARGET,
          game_version: VERSION
        }
      }).catch(() => {});
    }
  });

  window.addEventListener(
    'microgifter:bridge-ready',
    () => void initialize(),
    { once: true }
  );
  if (window.MicrogifterGame) void initialize();
})();
