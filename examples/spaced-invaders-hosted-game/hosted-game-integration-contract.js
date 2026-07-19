(() => {
  'use strict';

  const TARGET_WAVE = 5;
  const CLIENT_VERSION = '1.2.0';

  window.createSpacedInvadersHostedIntegration = function createIntegration(options) {
    const showToast = typeof options.showToast === 'function' ? options.showToast : () => {};
    const getScore = options.getScore;
    const getWave = options.getWave;
    const getResult = options.getResult;
    const getSettlementsRemaining = options.getSettlementsRemaining;
    const getSettlementCareer = options.getSettlementCareer;

    const runtime = {
      session: null,
      player: null,
      program: null,
      reward: null,
      runActive: false,
      completed: false,
      lastScore: -1,
    };

    function sdk() {
      return window.MicrogifterGame || null;
    }

    function rewardName() {
      return runtime.reward?.title || runtime.reward?.name || runtime.reward?.product_name || 'Microgift reward';
    }

    async function initialize() {
      if (!sdk()) return { standalone: true };
      runtime.session = await sdk().ready();
      const [player, program, reward] = await Promise.allSettled([
        sdk().getPlayer(),
        sdk().getProgram(),
        sdk().getReward(),
      ]);
      runtime.player = player.status === 'fulfilled' ? player.value : runtime.session?.player || null;
      runtime.program = program.status === 'fulfilled' ? program.value : runtime.session?.program || null;
      runtime.reward = reward.status === 'fulfilled' ? reward.value : runtime.session?.reward || null;
      await sdk().emitEvent('game_loaded', {
        game: 'spaced-invaders',
        version: CLIENT_VERSION,
        target_wave: TARGET_WAVE,
        settlement_model: 'specialty-v1',
      }).catch(() => {});
      return runtime;
    }

    async function connect() {
      if (!sdk()) return true;
      let session = runtime.session || await sdk().ready();
      let player = runtime.player || session?.player || {};
      if (!player.signed_in) {
        sdk().signIn();
        return false;
      }
      if (!player.connected) {
        await sdk().connectPlayer();
        session = await sdk().ready();
        player = session?.player || {};
      }
      runtime.session = session;
      runtime.player = player;
      return Boolean(player.connected);
    }

    async function loadCareer() {
      if (!sdk() || typeof sdk().loadState !== 'function') return null;
      const response = await sdk().loadState('career');
      return response?.state || response?.value || response?.data || response || null;
    }

    async function saveCareer(baseCareer = {}) {
      if (!sdk() || typeof sdk().saveState !== 'function') return null;
      return sdk().saveState('career', {
        ...baseCareer,
        settlements: typeof getSettlementCareer === 'function' ? getSettlementCareer() : {},
        updatedAt: new Date().toISOString(),
      });
    }

    async function startRun() {
      if (!sdk()) return true;
      if (!(await connect())) return false;
      await sdk().startRun({
        mode: 'settlement-siege',
        clientVersion: CLIENT_VERSION,
        targetWave: TARGET_WAVE,
      });
      runtime.runActive = true;
      runtime.completed = false;
      runtime.lastScore = -1;
      await sdk().levelStarted(1, { mode: 'settlement-siege' }).catch(() => {});
      return true;
    }

    async function updateScore(force = false) {
      if (!sdk() || !runtime.runActive || runtime.completed) return;
      const score = Math.max(0, Math.floor(getScore()));
      if (!force && score === runtime.lastScore) return;
      runtime.lastScore = score;
      await sdk().updateScore(score, {
        wave: getWave(),
        settlements_remaining: getSettlementsRemaining(),
      });
    }

    function giftToast(status) {
      const normalized = String(status || '').toLowerCase();
      const delivered = ['delivered', 'issued', 'sandbox_delivered', 'simulated_delivered'].includes(normalized);
      showToast(
        delivered
          ? `Gift sent to your Microgifter Inbox: ${rewardName()}.`
          : `Gift earned! ${rewardName()} was sent for delivery to your Microgifter Inbox.`,
        'success',
        7600
      );
    }

    async function completeReward(completedWave) {
      if (!sdk() || !runtime.runActive || runtime.completed) return null;
      if (completedWave < TARGET_WAVE || getSettlementsRemaining() < 1) return null;

      await updateScore(true);
      await sdk().qualify({
        target_wave: TARGET_WAVE,
        achieved_wave: completedWave,
        settlements_remaining: getSettlementsRemaining(),
      });
      await sdk().submitScore({
        score: Math.floor(getScore()),
        metadata: getResult(),
      }).catch(() => {});
      const response = await sdk().complete({
        score: Math.floor(getScore()),
        result: getResult(),
      });
      runtime.completed = true;
      runtime.runActive = false;
      giftToast(response?.run?.status || response?.reward?.status || response?.status || 'queued');
      return response;
    }

    async function waveAdvanced(completedWave) {
      if (!sdk() || !runtime.runActive || runtime.completed) return;
      await updateScore(true);
      await sdk().levelCompleted(completedWave, getResult()).catch(() => {});
      if (completedWave >= TARGET_WAVE) {
        await completeReward(completedWave);
      } else {
        await sdk().levelStarted(getWave(), { mode: 'settlement-siege' }).catch(() => {});
      }
    }

    async function abandon(reason = 'player_exit') {
      if (!sdk() || !runtime.runActive || runtime.completed) return;
      runtime.runActive = false;
      await sdk().abandonRun({ reason, result: getResult() }).catch(() => {});
    }

    return {
      targetWave: TARGET_WAVE,
      version: CLIENT_VERSION,
      runtime,
      initialize,
      connect,
      loadCareer,
      saveCareer,
      startRun,
      updateScore,
      waveAdvanced,
      completeReward,
      abandon,
    };
  };
})();
