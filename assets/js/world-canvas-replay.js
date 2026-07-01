window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !MG) return;

  var activeWindow = 'now';
  var replayItems = [];
  var replayIndex = 0;
  var playTimer = null;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function payload(response) { return response && response.data ? response.data : response; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function apiGet(path) {
    if (MG.get) return MG.get(path);
    return fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); });
  }
  function point(seed, index, kind) {
    var hash = 0;
    for (var i = 0; i < seed.length; i++) hash = ((hash << 5) - hash + seed.charCodeAt(i)) | 0;
    hash = Math.abs(hash);
    var offset = kind === 'claim' ? 41 : kind === 'reward' ? 29 : kind === 'campaign' ? 17 : kind === 'conversation' ? 23 : 9;
    return { x: 8 + ((hash + index * 19 + offset) % 82), y: 10 + (((Math.floor(hash / 97)) + index * 23 + offset) % 72) };
  }
  function kind(type) {
    type = String(type || '').toLowerCase();
    if (type.indexOf('claim') !== -1) return 'claim';
    if (type.indexOf('reward') !== -1 || type.indexOf('gift') !== -1) return 'reward';
    if (type.indexOf('campaign') !== -1) return 'campaign';
    if (type.indexOf('conversation') !== -1 || type.indexOf('message') !== -1) return 'conversation';
    return 'avatar';
  }
  function cutoffFor(windowKey) {
    var now = Date.now();
    if (windowKey === 'last15') return now - 15 * 60 * 1000;
    if (windowKey === 'today') { var d = new Date(); d.setHours(0,0,0,0); return d.getTime(); }
    if (windowKey === 'week') return now - 7 * 24 * 60 * 60 * 1000;
    if (windowKey === 'campaign') return now - 30 * 24 * 60 * 60 * 1000;
    return now - 60 * 60 * 1000;
  }
  function buildItem(event, index) {
    var k = kind(event.type);
    var id = event.id || ('event-' + index);
    var title = event.label || 'World movement';
    var summary = [event.title, event.meta].filter(Boolean).join(' · ');
    var seed = [id, title, summary].join('|');
    var fromKind = k === 'claim' ? 'avatar' : (k === 'campaign' ? 'campaign' : 'merchant');
    var toKind = k === 'claim' ? 'merchant' : (k === 'conversation' ? 'avatar' : k);
    return {
      id: id,
      type: k,
      title: title,
      summary: summary || 'World Canvas signal',
      from: point(seed + ':from', index, fromKind),
      to: point(seed + ':to', index + 7, toKind),
      occurred_at: event.created_at || ''
    };
  }
  function ensureShell() {
    var stage = qs('.mg-world-stage');
    if (!stage || qs('[data-world-replay-shell]')) return;
    var shell = document.createElement('section');
    shell.className = 'mg-world-replay-shell';
    shell.dataset.worldReplayShell = '1';
    shell.innerHTML = '<div class="mg-world-replay-head"><div><span class="mg-world-eyebrow">Gift Movement Timeline</span><strong data-world-replay-title>Now</strong></div><div class="mg-world-replay-actions"><button type="button" data-world-replay-play>Play</button><button type="button" data-world-replay-step>Step</button></div></div><div class="mg-world-replay-tabs"><button type="button" class="is-active" data-world-replay-window="now">Now</button><button type="button" data-world-replay-window="last15">Last 15 Minutes</button><button type="button" data-world-replay-window="today">Today</button><button type="button" data-world-replay-window="week">This Week</button><button type="button" data-world-replay-window="campaign">Campaign Window</button></div><div class="mg-world-replay-meter"><span data-world-replay-meter></span></div><div class="mg-world-replay-summary" data-world-replay-summary>Loading replay signals...</div>';
    stage.appendChild(shell);
  }
  function ensureLayer() {
    var map = qs('[data-world-map]');
    if (!map) return null;
    var layer = qs('[data-world-replay-layer]');
    if (layer) return layer;
    layer = document.createElement('div');
    layer.className = 'mg-world-replay-layer';
    layer.dataset.worldReplayLayer = '1';
    layer.innerHTML = '<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><g data-world-replay-lines></g></svg><div data-world-replay-points></div>';
    map.appendChild(layer);
    return layer;
  }
  function renderSummary() {
    var summary = qs('[data-world-replay-summary]');
    var title = qs('[data-world-replay-title]');
    if (title) title.textContent = activeWindow === 'last15' ? 'Last 15 Minutes' : activeWindow === 'today' ? 'Today' : activeWindow === 'week' ? 'This Week' : activeWindow === 'campaign' ? 'Campaign Window' : 'Now';
    if (!summary) return;
    var counts = replayItems.reduce(function (acc, item) { acc[item.type] = (acc[item.type] || 0) + 1; return acc; }, {});
    summary.innerHTML = '<span><b>' + replayItems.length + '</b> signals</span><span><b>' + (counts.reward || 0) + '</b> rewards</span><span><b>' + (counts.claim || 0) + '</b> claims</span><span><b>' + (counts.campaign || 0) + '</b> campaigns</span><span><b>' + (counts.conversation || 0) + '</b> conversations</span>';
  }
  function renderFrame() {
    var layer = ensureLayer();
    if (!layer) return;
    var lines = qs('[data-world-replay-lines]', layer);
    var points = qs('[data-world-replay-points]', layer);
    var visible = replayItems.slice(0, Math.max(0, replayIndex + 1));
    if (lines) lines.innerHTML = visible.map(function (item) {
      return '<line class="is-' + escapeHtml(item.type) + '" x1="' + item.from.x + '" y1="' + item.from.y + '" x2="' + item.to.x + '" y2="' + item.to.y + '"></line>';
    }).join('');
    if (points) points.innerHTML = visible.slice(-18).map(function (item) {
      return '<article class="mg-world-replay-point is-' + escapeHtml(item.type) + '" style="left:' + item.to.x + '%;top:' + item.to.y + '%"><strong>' + escapeHtml(item.title) + '</strong><span>' + escapeHtml(item.summary) + '</span></article>';
    }).join('');
    var meter = qs('[data-world-replay-meter]');
    if (meter) meter.style.width = replayItems.length ? (((replayIndex + 1) / replayItems.length) * 100) + '%' : '0%';
  }
  function stepReplay() {
    if (!replayItems.length) return;
    replayIndex = Math.min(replayItems.length - 1, replayIndex + 1);
    renderFrame();
  }
  function togglePlay(button) {
    if (playTimer) {
      window.clearInterval(playTimer);
      playTimer = null;
      if (button) button.textContent = 'Play';
      return;
    }
    if (button) button.textContent = 'Pause';
    playTimer = window.setInterval(function () {
      if (replayIndex >= replayItems.length - 1) replayIndex = -1;
      stepReplay();
    }, 900);
  }
  async function loadReplay() {
    ensureShell();
    ensureLayer();
    try {
      var data = payload(await apiGet('/api/world-canvas/activity.php'));
      var cutoff = cutoffFor(activeWindow);
      replayItems = (data.events || []).filter(function (event) {
        var t = Date.parse(String(event.created_at || '').replace(' ', 'T'));
        return !Number.isNaN(t) && t >= cutoff;
      }).sort(function (a, b) { return String(a.created_at || '').localeCompare(String(b.created_at || '')); }).map(buildItem);
      replayIndex = replayItems.length ? Math.min(4, replayItems.length - 1) : 0;
      renderSummary();
      renderFrame();
    } catch (error) {
      var summary = qs('[data-world-replay-summary]');
      if (summary) summary.textContent = 'Unable to load movement replay.';
    }
  }
  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-world-replay-window]');
    if (tab && root.contains(tab)) {
      activeWindow = tab.dataset.worldReplayWindow || 'now';
      root.querySelectorAll('[data-world-replay-window]').forEach(function (button) { button.classList.toggle('is-active', button === tab); });
      loadReplay();
      return;
    }
    var play = event.target.closest('[data-world-replay-play]');
    if (play && root.contains(play)) { togglePlay(play); return; }
    var step = event.target.closest('[data-world-replay-step]');
    if (step && root.contains(step)) { stepReplay(); }
  });
  loadReplay();
  window.setInterval(loadReplay, 30000);
})(window, document);
