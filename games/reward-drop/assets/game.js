(function () {
  'use strict';

  var config = window.RewardDropConfig || {};
  var root = document.querySelector('[data-reward-drop]');
  if (!root) return;

  var arena = root.querySelector('[data-rd-arena]');
  var overlay = root.querySelector('[data-rd-overlay]');
  var scoreNode = root.querySelector('[data-rd-score]');
  var timeNode = root.querySelector('[data-rd-time]');
  var statusNode = root.querySelector('[data-rd-status]');
  var resultActions = root.querySelector('[data-rd-result-actions]');
  var linkButton = root.querySelector('[data-rd-link]');
  var startButton = root.querySelector('[data-rd-start]');
  var resetButton = root.querySelector('[data-rd-reset]');

  var state = {
    active: false,
    submitting: false,
    score: 0,
    run: null,
    startedAt: 0,
    finishTimer: null,
    clockTimer: null,
    spawnTimer: null,
    pollTimer: null,
    gifts: []
  };

  function setStatus(message, type) {
    if (!statusNode) return;
    statusNode.textContent = message || '';
    statusNode.classList.toggle('is-success', type === 'success');
    statusNode.classList.toggle('is-error', type === 'error');
  }

  async function request(url, options) {
    var response = await fetch(url, Object.assign({
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }, options || {}));
    var payload;
    try {
      payload = await response.json();
    } catch (error) {
      payload = { ok: false, message: 'The server returned an unreadable response.' };
    }
    if (!response.ok || payload.ok === false) {
      var failure = new Error(payload.message || 'Request failed.');
      failure.payload = payload;
      failure.status = response.status;
      throw failure;
    }
    return payload;
  }

  function post(url, body) {
    return request(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': config.csrfToken || ''
      },
      body: JSON.stringify(Object.assign({ csrf_token: config.csrfToken || '' }, body || {}))
    });
  }

  function clearTimers() {
    [state.finishTimer, state.clockTimer, state.spawnTimer, state.pollTimer].forEach(function (timer) {
      if (timer) window.clearTimeout(timer);
    });
    state.finishTimer = null;
    state.clockTimer = null;
    state.spawnTimer = null;
    state.pollTimer = null;
  }

  function clearGifts() {
    state.gifts.forEach(function (gift) {
      if (gift && gift.parentNode) gift.parentNode.removeChild(gift);
    });
    state.gifts = [];
  }

  function updateScore() {
    if (scoreNode) scoreNode.textContent = String(state.score);
  }

  function random(min, max) {
    return Math.random() * (max - min) + min;
  }

  function removeGift(gift) {
    var index = state.gifts.indexOf(gift);
    if (index >= 0) state.gifts.splice(index, 1);
    if (gift && gift.parentNode) gift.parentNode.removeChild(gift);
  }

  function spawnGift() {
    if (!state.active || !arena) return;
    var gift = document.createElement('button');
    gift.type = 'button';
    gift.className = 'rd-drop';
    gift.setAttribute('aria-label', 'Collect gift');
    gift.innerHTML = '<span aria-hidden="true">✦</span>';
    gift.style.left = random(7, 86) + '%';
    gift.style.top = random(11, 78) + '%';
    gift.style.setProperty('--rd-rotate', random(-12, 12) + 'deg');
    gift.addEventListener('click', function () {
      if (!state.active || gift.classList.contains('is-collected')) return;
      gift.classList.add('is-collected');
      state.score += 1;
      updateScore();
      setStatus(state.score >= Number(state.run.target_score) ? 'Target reached. Verifying your play…' : 'Gift collected — keep going!', state.score >= Number(state.run.target_score) ? 'success' : '');
      window.setTimeout(function () { removeGift(gift); }, 230);
      maybeFinish();
    });
    arena.appendChild(gift);
    state.gifts.push(gift);
    window.setTimeout(function () { removeGift(gift); }, 1450);
    state.spawnTimer = window.setTimeout(spawnGift, random(430, 720));
  }

  function remainingSeconds() {
    if (!state.run) return 0;
    var elapsed = (Date.now() - state.startedAt) / 1000;
    return Math.max(0, Math.ceil(Number(state.run.duration_seconds) - elapsed));
  }

  function updateClock() {
    if (!state.active || !state.run) return;
    var remaining = remainingSeconds();
    if (timeNode) timeNode.textContent = remaining + 's';
    if (remaining <= 0) {
      finishGame();
      return;
    }
    state.clockTimer = window.setTimeout(updateClock, 250);
  }

  function maybeFinish() {
    if (!state.active || !state.run || state.score < Number(state.run.target_score)) return;
    var elapsed = (Date.now() - state.startedAt) / 1000;
    var wait = Math.max(0, Number(state.run.minimum_play_seconds || 0) - elapsed);
    if (wait <= 0) finishGame();
    else if (!state.finishTimer) state.finishTimer = window.setTimeout(finishGame, Math.ceil(wait * 1000));
  }

  async function startGame() {
    if (state.active || state.submitting) return;
    startButton.disabled = true;
    setStatus('Creating a secure game run…');
    try {
      var payload = await post(config.endpoints.start, {});
      state.run = payload.run;
      state.score = 0;
      state.active = true;
      state.submitting = false;
      state.startedAt = Date.now();
      updateScore();
      if (timeNode) timeNode.textContent = state.run.duration_seconds + 's';
      if (overlay) overlay.hidden = true;
      if (resultActions) resultActions.hidden = true;
      arena.classList.add('is-playing');
      setStatus('Collect ' + state.run.target_score + ' gifts to earn the reward.');
      spawnGift();
      updateClock();
      state.finishTimer = window.setTimeout(finishGame, Number(state.run.duration_seconds) * 1000);
    } catch (error) {
      startButton.disabled = false;
      setStatus(error.message, 'error');
      if (error.payload && error.payload.needs_link && linkButton) linkButton.hidden = false;
    }
  }

  async function finishGame() {
    if (!state.active || state.submitting || !state.run) return;
    state.active = false;
    state.submitting = true;
    clearTimers();
    clearGifts();
    arena.classList.remove('is-playing');

    if (state.score < Number(state.run.target_score)) {
      state.submitting = false;
      setStatus('Time is up. You collected ' + state.score + ' of ' + state.run.target_score + ' gifts.', 'error');
      showReplay('Try Reward Drop again', startGame);
      return;
    }

    setStatus('Score verified. Requesting your campaign reward…');
    try {
      var payload = await post(config.endpoints.complete, {
        run_id: state.run.run_id,
        run_token: state.run.run_token,
        score: state.score
      });
      var run = payload.run || {};
      setStatus(payload.message || 'Reward queued for your Microgifter Inbox.', 'success');
      if (resultActions) resultActions.hidden = false;
      pollStatus(run.run_id || state.run.run_id, 0);
    } catch (error) {
      setStatus(error.message, 'error');
      showReplay('Refresh game', function () { window.location.reload(); });
    } finally {
      state.submitting = false;
    }
  }

  function showReplay(label, handler) {
    if (!overlay) return;
    overlay.hidden = false;
    overlay.innerHTML = '<span class="rd-gift-mark" aria-hidden="true">✦</span><h2>' + label + '</h2><p>Your account remains connected. Start a fresh secure run when ready.</p><button class="rd-primary" type="button" data-rd-replay>Play again</button>';
    var replay = overlay.querySelector('[data-rd-replay]');
    if (replay) replay.addEventListener('click', handler, { once: true });
  }

  async function pollStatus(runId, attempt) {
    if (!runId || attempt > 12) return;
    try {
      var payload = await request(config.endpoints.status + '?run_id=' + encodeURIComponent(runId));
      var run = payload.run || {};
      if (run.status === 'delivered') {
        setStatus('Reward delivered — it is now in your Microgifter Inbox.', 'success');
        return;
      }
      if (run.status === 'failed') {
        setStatus(run.message || 'Reward delivery needs attention.', 'error');
        return;
      }
    } catch (error) {
      if (attempt > 2) return;
    }
    state.pollTimer = window.setTimeout(function () { pollStatus(runId, attempt + 1); }, 2500);
  }

  async function linkAccount() {
    if (!linkButton) return;
    linkButton.disabled = true;
    setStatus('Preparing the secure Microgifter connection…');
    try {
      var payload = await post(config.endpoints.link, {});
      if (payload.linked) {
        window.location.reload();
        return;
      }
      if (!payload.link_url) throw new Error('The connection URL was not returned.');
      window.location.assign(payload.link_url);
    } catch (error) {
      linkButton.disabled = false;
      setStatus(error.message, 'error');
    }
  }

  if (linkButton) linkButton.addEventListener('click', linkAccount);
  if (startButton) startButton.addEventListener('click', startGame);
  if (resetButton) resetButton.addEventListener('click', function () { window.location.reload(); });

  var available = document.querySelector('[data-rd-available]');
  if (available && available.getAttribute('datetime')) {
    var date = new Date(available.getAttribute('datetime'));
    if (!Number.isNaN(date.getTime())) available.textContent = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  }
})();
