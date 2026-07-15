(() => {
  'use strict';

  const target = 5;
  const scoreNode = document.querySelector('[data-score]');
  const statusNode = document.querySelector('[data-status]');
  const startButton = document.querySelector('[data-start]');
  const giftButton = document.querySelector('[data-gift]');
  const inboxLink = document.querySelector('[data-inbox]');
  let score = 0;
  let run = null;
  let finished = false;

  function status(message) {
    statusNode.textContent = message;
  }

  async function initialize() {
    try {
      const session = await MicrogifterGame.ready();
      if (!session.player.signed_in) {
        status('Sign in to Microgifter to play.');
        startButton.textContent = 'Sign in with Microgifter';
        return;
      }
      if (!session.player.connected) {
        status('Connect this game to your Microgifter Inbox.');
        startButton.textContent = 'Connect and start';
        return;
      }
      status(`Ready, ${session.player.display_name || 'player'}.`);
      startButton.textContent = 'Start game';
    } catch (error) {
      status(error instanceof Error ? error.message : 'Unable to initialize the game.');
      startButton.disabled = true;
    }
  }

  async function start() {
    startButton.disabled = true;
    try {
      let session = await MicrogifterGame.ready();
      if (!session.player.signed_in) {
        MicrogifterGame.signIn();
        return;
      }
      if (!session.player.connected) {
        await MicrogifterGame.connectPlayer();
        session = await MicrogifterGame.ready();
      }
      const response = await MicrogifterGame.startRun({ mode: 'starter', target });
      run = response.run;
      score = 0;
      finished = false;
      scoreNode.textContent = '0';
      giftButton.disabled = false;
      status('Tap the gift five times.');
    } catch (error) {
      status(error instanceof Error ? error.message : 'Unable to start the game.');
      startButton.disabled = false;
    }
  }

  async function completeQualifiedRun() {
    const result = { target, mode: 'starter' };
    if (typeof MicrogifterGame.complete === 'function') {
      return MicrogifterGame.complete({ score, result });
    }
    return MicrogifterGame.completeRun({
      runId: run?.run_id || '',
      runToken: run?.run_token || '',
      qualified: true,
      score,
      result
    });
  }

  async function finish() {
    if (!run || finished) return;
    finished = true;
    giftButton.disabled = true;
    status('Submitting your qualified run…');
    try {
      await MicrogifterGame.submitScore({
        runId: run.run_id,
        runToken: run.run_token,
        score,
        metadata: { mode: 'starter' }
      });
      await MicrogifterGame.qualify({ target, achieved: score });
      const response = await completeQualifiedRun();
      run = null;
      status(response.run?.status === 'delivered' ? 'Reward delivered.' : 'Reward earned and queued for your Inbox.');
      inboxLink.hidden = false;
      startButton.textContent = 'Play again';
      startButton.disabled = false;
    } catch (error) {
      status(error instanceof Error ? error.message : 'Unable to complete the game.');
      await MicrogifterGame.reportError(error, { phase: 'complete' }).catch(() => {});
      startButton.disabled = false;
    }
  }

  giftButton.addEventListener('click', () => {
    if (finished || !run) return;
    score += 1;
    scoreNode.textContent = String(score);
    void MicrogifterGame.updateScore(score, { target }).catch(() => {});
    if (score >= target) void finish();
  });

  startButton.addEventListener('click', () => void start());
  inboxLink.addEventListener('click', (event) => {
    event.preventDefault();
    MicrogifterGame.openInbox();
  });

  window.addEventListener('pagehide', () => {
    if (!finished && run) {
      void MicrogifterGame.abandonRun({ run, reason: 'page_hidden', result: { score } }).catch(() => {});
    }
  });

  window.addEventListener('microgifter:bridge-ready', () => void initialize(), { once: true });
  if (window.MicrogifterGame) void initialize();
})();
