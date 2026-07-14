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
      status(error.message);
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
      await MicrogifterGame.track('game.started', { mode: 'starter' });
    } catch (error) {
      status(error.message);
      startButton.disabled = false;
    }
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
      const response = await MicrogifterGame.completeRun({
        runId: run.run_id,
        runToken: run.run_token,
        qualified: true,
        score,
        result: { target, mode: 'starter' }
      });
      status(response.run?.status === 'delivered' ? 'Reward delivered.' : 'Reward earned and queued for your Inbox.');
      inboxLink.hidden = false;
      startButton.textContent = 'Play again';
      startButton.disabled = false;
    } catch (error) {
      status(error.message);
      startButton.disabled = false;
    }
  }

  giftButton.addEventListener('click', () => {
    if (!run || finished) return;
    score += 1;
    scoreNode.textContent = String(score);
    if (score >= target) void finish();
  });

  startButton.addEventListener('click', () => void start());
  inboxLink.addEventListener('click', (event) => {
    event.preventDefault();
    MicrogifterGame.openInbox();
  });

  window.addEventListener('microgifter:bridge-ready', () => void initialize(), { once: true });
  if (window.MicrogifterGame) void initialize();
})();
