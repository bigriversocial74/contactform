window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !MG.get) return;

  var activeFilter = 'all';
  var selectedNodeId = '';
  var pollTimer = null;
  var drawer = root.querySelector('[data-world-drawer]');

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function clear(node) { if (node) node.replaceChildren(); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function number(value) { return Number(value || 0).toLocaleString(); }
  function timeLabel(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('T') === -1 ? 'Z' : ''));
    if (Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(parsed);
  }
  function initials(value) {
    return String(value || 'MG').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || 'MG';
  }

  function portalDrawer() {
    if (!drawer || !document.body) return null;
    if (drawer.parentElement !== document.body) document.body.appendChild(drawer);
    drawer.setAttribute('data-world-drawer-portal', 'body');
    return drawer;
  }
  function openDrawer() {
    var activeDrawer = portalDrawer();
    if (!activeDrawer) return;
    activeDrawer.classList.add('is-open');
    activeDrawer.setAttribute('aria-hidden', 'false');
  }
  function closeDrawer() {
    var activeDrawer = portalDrawer();
    if (!activeDrawer) return;
    activeDrawer.classList.remove('is-open');
    activeDrawer.setAttribute('aria-hidden', 'true');
  }

  function setLiveStatus(message, type) {
    var pill = qs('[data-world-live-pill]');
    if (!pill) return;
    pill.textContent = message;
    pill.classList.toggle('is-live', type === 'live');
    pill.classList.toggle('is-warn', type === 'warn');
    pill.classList.toggle('is-error', type === 'error');
  }
  function setState(message) {
    var state = qs('[data-world-state]');
    if (state) state.textContent = message;
  }

  function setStats(summary) {
    summary = summary || {};
    Object.keys(summary).forEach(function (key) {
      qsa('[data-world-stat="' + key + '"]').forEach(function (node) {
        node.textContent = number(summary[key]);
      });
    });
  }

  function nodeIcon(node) {
    if (node.avatar_url) return '<span class="mg-world-node-icon"><img src="' + escapeHtml(node.avatar_url) + '" alt=""></span>';
    var label = node.type === 'campaign' ? 'CA' : node.type === 'reward' ? 'RW' : node.type === 'claim' ? 'CL' : initials(node.title);
    return '<span class="mg-world-node-icon">' + escapeHtml(label) + '</span>';
  }

  function renderNode(node) {
    var type = node.type || 'node';
    var tone = node.owned ? 'owned' : (node.tone || 'soft');
    var hidden = activeFilter !== 'all' && activeFilter !== type;
    return '<button class="mg-world-node is-' + escapeHtml(type) + ' is-' + escapeHtml(tone) + (node.id === selectedNodeId ? ' is-active' : '') + '" type="button" data-world-node data-world-node-id="' + escapeHtml(node.id || '') + '" data-world-type="' + escapeHtml(type) + '" data-world-detail-id="' + escapeHtml(node.detail_id || '') + '" style="left:' + escapeHtml(node.x || 50) + '%;top:' + escapeHtml(node.y || 50) + '%"' + (hidden ? ' hidden' : '') + '>' +
      '<span class="mg-world-node-status" aria-hidden="true"></span>' +
      '<span class="mg-world-node-head">' + nodeIcon(node) + '<span class="mg-world-node-copy"><strong>' + escapeHtml(node.title || 'World signal') + '</strong><span>' + escapeHtml(node.subtitle || '') + '</span></span><span class="mg-world-node-value">' + escapeHtml(node.value || '') + '</span></span>' +
      '<small class="mg-world-node-meta">' + escapeHtml(node.meta || '') + '</small>' +
      '</button>';
  }

  function renderFlows(nodes) {
    var svg = qs('[data-world-flows]');
    if (!svg) return;
    clear(svg);
    if (!nodes || nodes.length < 2) return;
    var merchants = nodes.filter(function (node) { return node.type === 'merchant'; }).slice(0, 8);
    var movers = nodes.filter(function (node) { return node.type === 'reward' || node.type === 'claim' || node.type === 'campaign'; }).slice(0, 16);
    movers.forEach(function (node, index) {
      var from = merchants[index % Math.max(1, merchants.length)] || nodes[index % nodes.length];
      if (!from || from.id === node.id) return;
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1', String(from.x || 50));
      line.setAttribute('y1', String(from.y || 50));
      line.setAttribute('x2', String(node.x || 50));
      line.setAttribute('y2', String(node.y || 50));
      if (node.type === 'reward') line.classList.add('is-reward');
      if (node.type === 'claim') line.classList.add('is-claim');
      svg.appendChild(line);
    });
  }

  function renderEvents(events) {
    var list = qs('[data-world-events]');
    if (!list) return;
    clear(list);
    events = Array.isArray(events) ? events : [];
    if (!events.length) {
      list.innerHTML = '<p>No live network events yet.</p>';
      return;
    }
    list.innerHTML = events.slice(0, 12).map(function (event) {
      var type = event.type || 'session';
      var icon = type === 'campaign' ? 'CA' : type === 'reward' ? 'RW' : type === 'claim' ? 'CL' : 'EV';
      return '<article class="mg-world-event is-' + escapeHtml(type) + '"><b>' + escapeHtml(icon) + '</b><span><strong>' + escapeHtml(event.label || event.title || 'World activity') + '</strong><span>' + escapeHtml(event.title || '') + (event.meta ? ' · ' + escapeHtml(event.meta) : '') + '</span></span><time>' + escapeHtml(timeLabel(event.created_at)) + '</time></article>';
    }).join('');
  }

  function applyFilter() {
    qsa('[data-world-filter]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.worldFilter === activeFilter);
    });
    qsa('[data-world-node]').forEach(function (node) {
      node.hidden = activeFilter !== 'all' && node.dataset.worldType !== activeFilter;
    });
  }

  function renderWorld(data) {
    data = data || {};
    var summary = data.summary || {};
    var nodes = Array.isArray(data.nodes) ? data.nodes : [];
    setStats(summary);
    setLiveStatus(nodes.length ? 'World Canvas live' : (summary.schema_ready ? 'Network ready' : 'Setup pending'), nodes.length ? 'live' : (summary.schema_ready ? 'warn' : 'error'));
    setState(summary.schema_ready ? 'Showing aggregate Microgifter network signals. Customer identities are anonymized at world level.' : 'World Canvas is waiting on Store Canvas tables before live activity can appear.');

    var layer = qs('[data-world-nodes]');
    if (layer) {
      clear(layer);
      layer.insertAdjacentHTML('beforeend', nodes.map(renderNode).join(''));
    }
    var empty = qs('[data-world-empty]');
    if (empty) empty.classList.toggle('is-hidden', nodes.length > 0);
    renderFlows(nodes);
    renderEvents(data.events || []);
    applyFilter();
  }

  async function loadWorld() {
    try {
      var data = payload(await MG.get('/api/world-canvas/activity.php'));
      renderWorld(data || {});
    } catch (error) {
      setLiveStatus(error.message || 'Unable to load World Canvas', 'error');
      setState(error.message || 'Unable to load World Canvas activity.');
    }
  }

  function statMarkup(stats) {
    stats = Array.isArray(stats) ? stats : [];
    if (!stats.length) return '';
    return '<section class="mg-world-detail-grid">' + stats.map(function (stat) {
      return '<article class="mg-world-detail-stat"><span>' + escapeHtml(stat.label || 'Metric') + '</span><strong>' + escapeHtml(stat.value == null ? '—' : stat.value) + '</strong></article>';
    }).join('') + '</section>';
  }

  function actionsMarkup(actions) {
    actions = Array.isArray(actions) ? actions.filter(function (item) { return item && item.href; }) : [];
    if (!actions.length) return '';
    return '<section class="mg-world-detail-actions">' + actions.map(function (action) {
      return '<a href="' + escapeHtml(action.href) + '">' + escapeHtml(action.label || 'Open') + '</a>';
    }).join('') + '</section>';
  }

  function renderDetail(detail) {
    detail = detail || {};
    var body = drawer ? drawer.querySelector('[data-world-drawer-body]') : null;
    if (!body) return;
    var avatar = detail.avatar_url ? '<span class="mg-world-detail-avatar"><img src="' + escapeHtml(detail.avatar_url) + '" alt=""></span>' : '<span class="mg-world-detail-avatar">' + escapeHtml(initials(detail.title)) + '</span>';
    body.innerHTML = '<section class="mg-world-detail-hero">' + avatar + '<div><strong>' + escapeHtml(detail.title || 'World detail') + '</strong><span>' + escapeHtml(detail.subtitle || '') + '</span></div></section>' +
      statMarkup(detail.stats) + actionsMarkup(detail.actions) +
      (detail.note ? '<section class="mg-world-detail-note">' + escapeHtml(detail.note) + '</section>' : '');
  }

  async function loadDetail(type, detailId, nodeId) {
    selectedNodeId = nodeId || '';
    qsa('[data-world-node]').forEach(function (node) { node.classList.toggle('is-active', node.dataset.worldNodeId === selectedNodeId); });
    openDrawer();
    var title = drawer ? drawer.querySelector('[data-world-drawer-title]') : null;
    var subtitle = drawer ? drawer.querySelector('[data-world-drawer-subtitle]') : null;
    var kind = drawer ? drawer.querySelector('[data-world-drawer-type]') : null;
    var body = drawer ? drawer.querySelector('[data-world-drawer-body]') : null;
    if (title) title.textContent = 'Loading detail...';
    if (subtitle) subtitle.textContent = 'Pulling live World Canvas context.';
    if (kind) kind.textContent = String(type || 'world') + ' detail';
    if (body) body.innerHTML = '<div class="mg-world-drawer-empty"><strong>Loading node</strong><p>Reading the aggregate world layer.</p></div>';
    try {
      var data = payload(await MG.get('/api/world-canvas/detail.php?type=' + encodeURIComponent(type || '') + '&id=' + encodeURIComponent(detailId || '')));
      var detail = data.detail || data;
      if (title) title.textContent = detail.title || 'World detail';
      if (subtitle) subtitle.textContent = detail.subtitle || '';
      if (kind) kind.textContent = (detail.type || type || 'world') + ' detail';
      renderDetail(detail);
    } catch (error) {
      if (title) title.textContent = 'Detail unavailable';
      if (subtitle) subtitle.textContent = 'The selected world signal may have expired.';
      if (body) body.innerHTML = '<div class="mg-world-error">' + escapeHtml(error.message || 'Unable to load this World Canvas detail.') + '</div>';
    }
  }

  document.addEventListener('click', function (event) {
    var inRoot = root.contains(event.target);
    var inDrawer = drawer && drawer.contains(event.target);
    if (!inRoot && !inDrawer) return;

    var filter = event.target.closest('[data-world-filter]');
    if (filter) {
      activeFilter = filter.dataset.worldFilter || 'all';
      applyFilter();
      return;
    }

    var refresh = event.target.closest('[data-world-refresh]');
    if (refresh) {
      loadWorld();
      return;
    }

    var close = event.target.closest('[data-world-drawer-close]');
    if (close) {
      closeDrawer();
      return;
    }

    var node = event.target.closest('[data-world-node]');
    if (node) {
      loadDetail(node.dataset.worldType, node.dataset.worldDetailId, node.dataset.worldNodeId);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeDrawer();
  });

  portalDrawer();
  loadWorld();
  pollTimer = window.setInterval(loadWorld, 9000);
  window.addEventListener('beforeunload', function () { if (pollTimer) window.clearInterval(pollTimer); });
})(window, document);
