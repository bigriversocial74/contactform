(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-admin-agent]');
  if (!root) return;

  var csrf = root.getAttribute('data-csrf-token') || '';
  var apiEndpoint = root.getAttribute('data-api-endpoint') || '/api/admin/admin-agent-phase2.php';
  var streamEndpoint = root.getAttribute('data-stream-endpoint') || '/api/admin/admin-agent-phase2-stream.php';
  var source = null;
  var pollTimer = null;
  var currentDomain = '';
  var state = {
    messages: [], events: [], findings: [], monitors: [], threads: [], domains: [],
    anomalies: [], correlations: [], deployments: [], escalations: [], executive_summaries: [],
    remediation: { adapters: [], reviews: [], executions: [] },
    permissions: { chat: false, manage: false, actions: false, escalations: false, deployments: false, execute: false },
    event_cursor: 0, phase2_ready: false
  };

  function q(selector, node) { return (node || root).querySelector(selector); }
  function qa(selector, node) { return Array.prototype.slice.call((node || root).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  }
  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); });
  }
  function num(value) { return Number(value || 0).toLocaleString(); }
  function date(value) {
    if (!value) return '—';
    var raw = String(value);
    var parsed = Date.parse(raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T') + 'Z');
    return isNaN(parsed) ? raw : new Date(parsed).toLocaleString();
  }
  function unwrap(payload) { return payload && payload.data !== undefined ? payload.data : payload; }
  function setStatus(message, error) {
    var node = q('[data-admin-agent-status]');
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-form-status mg-admin-agent-status' + (error ? ' is-error' : '');
  }
  function showError(error) { setStatus(error && error.message ? error.message : 'Main Admin Agent request failed.', true); }
  function pill(value) { return '<span class="mg-admin-agent-pill is-' + esc(value) + '">' + esc(label(value)) + '</span>'; }

  async function request(url, options) {
    var response = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, options || {}));
    var payload = await response.json().catch(function () { return { ok: false, message: 'Invalid server response.' }; });
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
    return unwrap(payload);
  }

  async function post(body) {
    body.csrf_token = csrf;
    return request(apiEndpoint, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(body)
    });
  }

  function renderOverview() {
    var box = q('[data-admin-agent-overview]');
    if (!box) return;
    var health = state.health || {};
    var scan = state.last_scan || {};
    var activeEscalations = (state.escalations || []).filter(function (item) { return item.status === 'scheduled' || item.status === 'sent'; });
    var cards = [
      ['System health', health.score == null ? '—' : health.score + '/100', label(health.status || 'unknown'), 'is-' + esc(health.status || 'unknown')],
      ['Correlations', num((state.correlations || []).length), 'Cross-system incidents', (state.correlations || []).some(function (item) { return item.severity === 'critical'; }) ? 'is-critical' : ''],
      ['Anomalies', num((state.anomalies || []).length), 'Learned baseline deviations', (state.anomalies || []).some(function (item) { return item.severity === 'critical'; }) ? 'is-critical' : ''],
      ['Escalations', num(activeEscalations.length), 'Scheduled or delivered', activeEscalations.length ? 'is-attention' : 'is-healthy'],
      ['Active findings', num(health.active_total), 'Deterministic monitor findings', ''],
      ['Monitors', num(health.enabled_monitors), num(health.failed_monitors) + ' failed', Number(health.failed_monitors) ? 'is-attention' : 'is-healthy'],
      ['Deployments', num((state.deployments || []).length), 'Recent release timeline', ''],
      ['Last analysis', scan.completed_at ? date(scan.completed_at) : 'Never', scan.status ? label(scan.status) : 'No completed scan', '']
    ];
    box.innerHTML = cards.map(function (card) {
      return '<article class="mg-admin-agent-score ' + card[3] + '"><span>' + esc(card[0]) + '</span><strong>' + esc(card[1]) + '</strong><small>' + esc(card[2]) + '</small></article>';
    }).join('');
  }

  function renderIntelligenceStrip() {
    var box = q('[data-admin-agent-intelligence-strip]');
    if (!box) return;
    if (!state.phase2_ready) {
      box.innerHTML = '<article class="is-schema"><strong>Phase 2 migration required</strong><span>Phase 1 monitoring remains available until the new SQL is imported.</span></article>';
      return;
    }
    var criticalCorrelations = (state.correlations || []).filter(function (item) { return item.severity === 'critical'; }).length;
    var criticalAnomalies = (state.anomalies || []).filter(function (item) { return item.severity === 'critical'; }).length;
    var latestDeploy = (state.deployments || [])[0];
    var latestSummary = (state.executive_summaries || [])[0];
    box.innerHTML = [
      '<article><span>Critical correlations</span><strong>' + num(criticalCorrelations) + '</strong></article>',
      '<article><span>Critical anomalies</span><strong>' + num(criticalAnomalies) + '</strong></article>',
      '<article><span>Latest deployment</span><strong>' + esc(latestDeploy ? latestDeploy.branch_name + ' · ' + latestDeploy.commit_sha.slice(0, 10) : 'Not recorded') + '</strong></article>',
      '<article><span>Executive summary</span><strong>' + esc(latestSummary ? date(latestSummary.generated_at) : 'Not generated') + '</strong></article>'
    ].join('');
  }

  function renderHealthBlock(block) {
    var health = block.health || {};
    return '<section class="mg-admin-agent-block"><h4>System health</h4><div class="mg-admin-agent-block-grid">' +
      '<span><strong>' + esc(health.score == null ? '—' : health.score + '/100') + '</strong><br>Overall score</span>' +
      '<span><strong>' + num(health.active_total) + '</strong><br>Active findings</span>' +
      '<span><strong>' + num(health.critical_total) + '</strong><br>Critical</span>' +
      '<span><strong>' + num(health.failed_monitors) + '</strong><br>Failed monitors</span>' +
      '</div></section>';
  }

  function renderDomainsBlock(block) {
    var items = Array.isArray(block.items) ? block.items : [];
    if (!items.length) return '';
    return '<section class="mg-admin-agent-block"><h4>System domains</h4><div class="mg-admin-agent-block-grid">' + items.slice(0, 10).map(function (item) {
      return '<span><strong>' + esc(label(item.domain)) + ' · ' + esc(item.score) + '/100</strong><br>' + num(item.active_total) + ' active</span>';
    }).join('') + '</div></section>';
  }

  function renderFindingList(items, title) {
    if (!items.length) return '';
    return '<section class="mg-admin-agent-block"><h4>' + esc(title) + '</h4><div class="mg-admin-agent-block-list">' + items.slice(0, 12).map(function (item) {
      return '<article class="is-' + esc(item.severity) + '"><strong>' + esc(item.title || item.metric_key || 'System condition') + '</strong><small>' + esc(label(item.severity)) + ' · ' + esc(item.summary || ('Observed ' + item.observed_value + ' vs baseline ' + item.baseline_mean)) + '</small></article>';
    }).join('') + '</div></section>';
  }

  function renderEventsBlock(block) {
    var items = Array.isArray(block.items) ? block.items : [];
    if (!items.length) return '';
    return '<section class="mg-admin-agent-block"><h4>Recent system activity</h4><div class="mg-admin-agent-block-list">' + items.slice(0, 12).map(function (item) {
      return '<article class="is-' + esc(item.severity) + '"><strong>' + esc(item.title || item.type || 'System event') + '</strong><small>' + esc(label(item.domain)) + ' · ' + esc(date(item.occurred_at)) + '</small></article>';
    }).join('') + '</div></section>';
  }

  function renderCorrelationBlock(items) {
    if (!items || !items.length) return '';
    return '<section class="mg-admin-agent-block"><h4>Cross-system correlations</h4><div class="mg-admin-agent-block-list">' + items.slice(0, 12).map(function (item) {
      var domains = Array.isArray(item.domains) ? item.domains.map(label).join(' + ') : '';
      return '<article class="is-' + esc(item.severity) + '"><strong>' + esc(item.title) + '</strong><small>' + esc(label(item.severity)) + ' · ' + esc(domains) + '<br>' + esc(item.summary) + '</small></article>';
    }).join('') + '</div></section>';
  }

  function renderAnomalyBlock(items) {
    if (!items || !items.length) return '';
    return '<section class="mg-admin-agent-block"><h4>Learned anomalies</h4><div class="mg-admin-agent-block-list">' + items.slice(0, 12).map(function (item) {
      var score = item.z_score != null ? 'z ' + Number(item.z_score).toFixed(2) : 'ratio ' + Number(item.deviation_ratio || 0).toFixed(2);
      return '<article class="is-' + esc(item.severity) + '"><strong>' + esc(label(item.monitor_key) + ' · ' + item.metric_key) + '</strong><small>' + esc(label(item.severity)) + ' · ' + esc(score) + ' · observed ' + esc(item.observed_value) + ' / baseline ' + esc(item.baseline_mean) + '</small></article>';
    }).join('') + '</div></section>';
  }

  function renderDeploymentBlock(items) {
    if (!items || !items.length) return '';
    return '<section class="mg-admin-agent-block"><h4>Deployment timeline</h4><div class="mg-admin-agent-block-list">' + items.slice(0, 10).map(function (item) {
      return '<article><strong>' + esc(item.branch_name) + ' · ' + esc(item.commit_sha.slice(0, 12)) + '</strong><small>' + esc(label(item.environment_key)) + ' · ' + esc(date(item.deployed_at)) + (item.release_label ? '<br>' + esc(item.release_label) : '') + '</small></article>';
    }).join('') + '</div></section>';
  }

  function renderEscalationBlock(items) {
    if (!items || !items.length) return '';
    return '<section class="mg-admin-agent-block"><h4>Escalation routing</h4><div class="mg-admin-agent-block-list">' + items.slice(0, 12).map(function (item) {
      return '<article class="is-' + esc(item.severity) + '"><strong>' + esc(label(item.source_type)) + ' escalation · Level ' + esc(item.level) + '</strong><small>' + esc(label(item.status)) + ' · due ' + esc(date(item.due_at)) + '</small></article>';
    }).join('') + '</div></section>';
  }

  function renderSummaryBlock(item) {
    if (!item) return '';
    return '<section class="mg-admin-agent-block mg-admin-agent-summary-block"><h4>' + esc(item.title || 'Executive summary') + '</h4><p>' + esc(item.summary_text || '').replace(/\n/g, '<br>') + '</p><small>' + esc(label(item.period_type)) + ' · ' + esc(date(item.generated_at)) + '</small></section>';
  }

  function renderRemediationBlock(data) {
    var items = data && Array.isArray(data.items) ? data.items : [];
    var adapters = items.adapters || [];
    var reviews = items.reviews || [];
    return '<section class="mg-admin-agent-block"><h4>Controlled remediation</h4><div class="mg-admin-agent-block-grid">' +
      '<span><strong>' + num(adapters.filter(function (item) { return item.enabled; }).length) + '</strong><br>Enabled adapters</span>' +
      '<span><strong>' + num(reviews.filter(function (item) { return item.status === 'pending'; }).length) + '</strong><br>Pending review</span>' +
      '<span><strong>' + num(reviews.filter(function (item) { return item.status === 'approved'; }).length) + '</strong><br>Approved</span>' +
      '<span><strong>' + num(reviews.filter(function (item) { return item.status === 'executed'; }).length) + '</strong><br>Executed</span>' +
      '</div></section>';
  }

  function renderBlocks(blocks) {
    if (!Array.isArray(blocks) || !blocks.length) return '';
    return '<div class="mg-admin-agent-blocks">' + blocks.map(function (block) {
      if (block.type === 'health') return renderHealthBlock(block);
      if (block.type === 'domains') return renderDomainsBlock(block);
      if (block.type === 'findings') return renderFindingList(Array.isArray(block.items) ? block.items : [], 'System findings');
      if (block.type === 'events') return renderEventsBlock(block);
      if (block.type === 'correlations') return renderCorrelationBlock(block.items || []);
      if (block.type === 'anomalies') return renderAnomalyBlock(block.items || []);
      if (block.type === 'deployments') return renderDeploymentBlock(block.items || []);
      if (block.type === 'escalations') return renderEscalationBlock(block.items || []);
      if (block.type === 'summary') return renderSummaryBlock(block.item || null);
      if (block.type === 'remediation') return renderRemediationBlock(block);
      if (block.type === 'commands') {
        return '<section class="mg-admin-agent-block"><h4>Available commands</h4><div class="mg-admin-agent-quick-prompts">' + (block.items || []).map(function (item) {
          return '<button type="button" data-admin-agent-prompt="' + esc(item) + '">' + esc(item) + '</button>';
        }).join('') + '</div></section>';
      }
      return '';
    }).join('') + '</div>';
  }

  function renderMessage(message) {
    var role = message.role === 'user' ? 'user' : 'assistant';
    var title = role === 'user' ? 'Administrator' : ((message.metadata && message.metadata.title) || 'Main Admin Agent');
    return '<article class="mg-admin-agent-message is-' + role + '"><div class="mg-admin-agent-message-head"><span>' + esc(title) + '</span><span>' + esc(date(message.created_at)) + '</span></div><p>' + esc(message.content || '').replace(/\n/g, '<br>') + '</p>' + renderBlocks(message.blocks || []) + '</article>';
  }

  function renderEvent(event) {
    return '<article class="mg-admin-agent-event-card"><header><strong>' + esc(event.title || event.type || 'System event') + '</strong>' + pill(event.severity || 'info') + '</header><p>' + esc(event.message || label(event.domain)) + '</p><small>' + esc(label(event.domain)) + ' · ' + esc(date(event.occurred_at)) + '</small></article>';
  }

  function renderFeed() {
    var box = q('[data-admin-agent-feed]');
    if (!box) return;
    var messages = state.messages || [];
    var events = (state.events || []).slice(-15);
    var html = messages.map(renderMessage).join('');
    if (events.length) html += '<div class="mg-admin-agent-feed-divider"><span>Live normalized system activity</span></div>' + events.slice().reverse().map(renderEvent).join('');
    if (!html) html = '<div class="mg-agent-chat-empty"><div class="mg-agent-chat-empty-icon">✦</div><strong>Main Admin Agent is ready</strong><p>Choose a quick report or ask about system intelligence.</p></div>';
    box.innerHTML = html;
    qa('[data-admin-agent-prompt]', box).forEach(bindPrompt);
    box.scrollTop = box.scrollHeight;
  }

  function renderThreads() {
    var select = q('[data-admin-agent-thread-select]');
    if (!select) return;
    var active = state.active_thread && state.active_thread.id || '';
    select.innerHTML = (state.threads || []).map(function (thread) {
      return '<option value="' + esc(thread.id) + '" ' + (thread.id === active ? 'selected' : '') + '>' + esc(thread.title || 'System chat') + (thread.status === 'archived' ? ' · Archived' : '') + '</option>';
    }).join('') || '<option value="">Current system chat</option>';
  }

  function monitorItem(item) {
    return '<article class="mg-admin-agent-rail-item"><div class="mg-admin-agent-rail-item-head"><strong>' + esc(item.label) + '</strong>' + pill(item.status) + '</div><p>' + esc(label(item.domain)) + ' · ' + (item.last_completed_at ? 'checked ' + esc(date(item.last_completed_at)) : 'not run yet') + '</p></article>';
  }

  function renderMonitors() {
    var box = q('[data-admin-agent-monitors]');
    var items = state.monitors || [];
    if (box) box.innerHTML = items.length ? items.map(monitorItem).join('') : '<div class="mg-admin-agent-empty">No monitors are registered.</div>';
    var count = q('[data-admin-agent-monitor-count]');
    if (count) count.textContent = items.length;
  }

  function findingActions(item) {
    var html = '';
    if (state.permissions.manage) {
      html += '<button type="button" data-finding-action="acknowledge" data-id="' + esc(item.id) + '">Acknowledge</button>';
      html += '<button type="button" data-finding-action="under_review" data-id="' + esc(item.id) + '">Review</button>';
      html += '<button type="button" data-finding-action="resolve" data-id="' + esc(item.id) + '">Resolve</button>';
    }
    if (state.permissions.actions) html += '<button type="button" data-request-action data-id="' + esc(item.id) + '" data-domain="' + esc(item.domain) + '">Request action</button>';
    return html;
  }

  function renderFindings() {
    var box = q('[data-admin-agent-findings]');
    var items = state.findings || [];
    if (box) {
      box.innerHTML = items.length ? items.slice(0, 40).map(function (item) {
        return '<article class="mg-admin-agent-rail-item"><button type="button" data-finding-focus="' + esc(item.id) + '"><div class="mg-admin-agent-rail-item-head"><strong>' + esc(item.title) + '</strong>' + pill(item.severity) + '</div><p>' + esc(item.summary) + '</p></button><div class="mg-admin-agent-finding-actions">' + findingActions(item) + '</div></article>';
      }).join('') : '<div class="mg-admin-agent-empty">No active findings in this domain.</div>';
      qa('[data-finding-action]', box).forEach(function (button) { button.onclick = function () { findingAction(button.dataset.id, button.dataset.findingAction).catch(showError); }; });
      qa('[data-request-action]', box).forEach(function (button) { button.onclick = function () { requestAction(button.dataset.id, button.dataset.domain).catch(showError); }; });
      qa('[data-finding-focus]', box).forEach(function (button) { button.onclick = function () { var found = items.find(function (item) { return item.id === button.dataset.findingFocus; }); if (found) insertPrompt('Explain active finding: ' + found.title); }; });
    }
    var count = q('[data-admin-agent-finding-count]');
    if (count) count.textContent = items.length;
  }

  function intelligenceActions(type, item) {
    if (!state.permissions.manage) return '';
    return '<div class="mg-admin-agent-finding-actions">' +
      '<button type="button" data-intelligence-action="acknowledge" data-source-type="' + esc(type) + '" data-id="' + esc(item.id) + '">Acknowledge</button>' +
      '<button type="button" data-intelligence-action="under_review" data-source-type="' + esc(type) + '" data-id="' + esc(item.id) + '">Review</button>' +
      '<button type="button" data-intelligence-action="resolve" data-source-type="' + esc(type) + '" data-id="' + esc(item.id) + '">Resolve</button>' +
      '</div>';
  }

  function bindIntelligenceActions(box) {
    qa('[data-intelligence-action]', box).forEach(function (button) {
      button.onclick = function () { intelligenceAction(button.dataset.sourceType, button.dataset.id, button.dataset.intelligenceAction).catch(showError); };
    });
    qa('[data-correlation-request]', box).forEach(function (button) {
      button.onclick = function () { requestCorrelationAction(button.dataset.actionKey, button.dataset.id).catch(showError); };
    });
  }

  function renderCorrelations() {
    var box = q('[data-admin-agent-correlations]');
    var items = state.correlations || [];
    if (box) {
      box.innerHTML = items.length ? items.slice(0, 40).map(function (item) {
        var action = state.permissions.actions && item.recommended_action_key ? '<button type="button" data-correlation-request data-id="' + esc(item.id) + '" data-action-key="' + esc(item.recommended_action_key) + '">Request ' + esc(label(item.recommended_action_key)) + '</button>' : '';
        return '<article class="mg-admin-agent-rail-item is-' + esc(item.severity) + '"><div class="mg-admin-agent-rail-item-head"><strong>' + esc(item.title) + '</strong>' + pill(item.severity) + '</div><p>' + esc(item.summary) + '</p><small>' + esc((item.domains || []).map(label).join(' + ')) + '</small>' + intelligenceActions('correlation', item) + (action ? '<div class="mg-admin-agent-finding-actions">' + action + '</div>' : '') + '</article>';
      }).join('') : '<div class="mg-admin-agent-empty">No active cross-system correlations.</div>';
      bindIntelligenceActions(box);
    }
    var count = q('[data-admin-agent-correlation-count]');
    if (count) count.textContent = items.length;
  }

  function renderAnomalies() {
    var box = q('[data-admin-agent-anomalies]');
    var items = state.anomalies || [];
    if (box) {
      box.innerHTML = items.length ? items.slice(0, 40).map(function (item) {
        var score = item.z_score != null ? 'z ' + Number(item.z_score).toFixed(2) : 'ratio ' + Number(item.deviation_ratio || 0).toFixed(2);
        return '<article class="mg-admin-agent-rail-item is-' + esc(item.severity) + '"><div class="mg-admin-agent-rail-item-head"><strong>' + esc(label(item.monitor_key)) + '</strong>' + pill(item.severity) + '</div><p>' + esc(item.metric_key) + '</p><small>' + esc(score) + ' · ' + esc(item.observed_value) + ' vs ' + esc(item.baseline_mean) + '</small>' + intelligenceActions('anomaly', item) + '</article>';
      }).join('') : '<div class="mg-admin-agent-empty">No active learned anomalies.</div>';
      bindIntelligenceActions(box);
    }
    var count = q('[data-admin-agent-anomaly-count]');
    if (count) count.textContent = items.length;
  }

  function renderEscalations() {
    var box = q('[data-admin-agent-escalations]');
    var items = (state.escalations || []).filter(function (item) { return item.status === 'scheduled' || item.status === 'sent'; });
    if (box) {
      box.innerHTML = items.length ? items.slice(0, 40).map(function (item) {
        var button = state.permissions.escalations ? '<button type="button" data-escalation-ack="' + esc(item.id) + '">Acknowledge</button>' : '';
        return '<article class="mg-admin-agent-rail-item is-' + esc(item.severity) + '"><div class="mg-admin-agent-rail-item-head"><strong>' + esc(label(item.source_type)) + ' · Level ' + esc(item.level) + '</strong>' + pill(item.status) + '</div><p>' + esc(label(item.policy_key)) + '</p><small>Due ' + esc(date(item.due_at)) + '</small>' + (button ? '<div class="mg-admin-agent-finding-actions">' + button + '</div>' : '') + '</article>';
      }).join('') : '<div class="mg-admin-agent-empty">No active escalations.</div>';
      qa('[data-escalation-ack]', box).forEach(function (button) { button.onclick = function () { acknowledgeEscalation(button.dataset.escalationAck).catch(showError); }; });
    }
    var count = q('[data-admin-agent-escalation-count]');
    if (count) count.textContent = items.length;
  }

  function renderDeployments() {
    var box = q('[data-admin-agent-deployments]');
    var items = state.deployments || [];
    if (box) box.innerHTML = items.length ? items.slice(0, 20).map(function (item) {
      return '<article class="mg-admin-agent-rail-item"><div class="mg-admin-agent-rail-item-head"><strong>' + esc(item.branch_name) + '</strong>' + pill(item.environment_key) + '</div><p><code>' + esc(item.commit_sha.slice(0, 16)) + '</code></p><small>' + esc(date(item.deployed_at)) + (item.release_label ? ' · ' + esc(item.release_label) : '') + '</small></article>';
    }).join('') : '<div class="mg-admin-agent-empty">No deployment records yet.</div>';
    var count = q('[data-admin-agent-deployment-count]');
    if (count) count.textContent = items.length;
  }

  function remediationReviewItem(item) {
    var actions = '';
    if (state.permissions.execute && item.status === 'pending') {
      actions = '<button type="button" data-review-decision="approve" data-id="' + esc(item.id) + '">Approve</button><button type="button" data-review-decision="reject" data-id="' + esc(item.id) + '">Reject</button>';
    }
    if (state.permissions.execute && item.status === 'approved' && item.execution_id) {
      actions = '<button type="button" data-execute-review data-execution-id="' + esc(item.execution_id) + '" data-action-key="' + esc(item.action_key) + '">Execute</button>';
    }
    return '<article class="mg-admin-agent-rail-item"><div class="mg-admin-agent-rail-item-head"><strong>' + esc(item.title) + '</strong>' + pill(item.status) + '</div><p>' + esc(item.rationale) + '</p><small>' + esc(label(item.risk_level)) + ' risk · ' + esc(date(item.created_at)) + '</small>' + (actions ? '<div class="mg-admin-agent-finding-actions">' + actions + '</div>' : '') + '</article>';
  }

  function renderRemediation() {
    var box = q('[data-admin-agent-remediation]');
    var remediation = state.remediation || { reviews: [], executions: [] };
    var reviews = remediation.reviews || [];
    if (box) {
      box.innerHTML = reviews.length ? reviews.slice(0, 40).map(remediationReviewItem).join('') : '<div class="mg-admin-agent-empty">No remediation actions are waiting.</div>';
      qa('[data-review-decision]', box).forEach(function (button) { button.onclick = function () { reviewAction(button.dataset.id, button.dataset.reviewDecision).catch(showError); }; });
      qa('[data-execute-review]', box).forEach(function (button) { button.onclick = function () { executeAction(button.dataset.executionId, button.dataset.actionKey).catch(showError); }; });
    }
    var count = q('[data-admin-agent-review-count]');
    if (count) count.textContent = reviews.filter(function (item) { return item.status === 'pending' || item.status === 'approved'; }).length;
  }

  function renderSchema() {
    var phase1 = q('[data-admin-agent-schema]');
    var phase2 = q('[data-admin-agent-phase2-schema]');
    if (phase1) {
      if (state.schema_ready === false) {
        phase1.hidden = false;
        phase1.innerHTML = '<strong>Main Admin Agent Phase 1 SQL is required</strong><span>Import <code>' + esc((state.schema && state.schema.migration) || 'database/20260718_main_admin_agent_phase1.sql') + '</code>, then refresh.</span>';
      } else phase1.hidden = true;
    }
    if (phase2) {
      if (state.schema_ready !== false && !state.phase2_ready) {
        phase2.hidden = false;
        phase2.innerHTML = '<strong>Main Admin Agent Phase 2 SQL is required</strong><span>Import <code>' + esc((state.phase2_schema && state.phase2_schema.migration) || 'database/20260718_main_admin_agent_phase2.sql') + '</code> to enable baselines, correlation, escalation, deployment awareness, summaries, and controlled execution.</span>';
      } else phase2.hidden = true;
    }
    var scan = q('[data-admin-agent-scan]');
    var deployment = q('[data-admin-agent-deployment]');
    var summary = q('[data-admin-agent-summary]');
    if (scan) scan.disabled = state.schema_ready === false || !state.permissions.manage;
    if (deployment) deployment.disabled = !state.phase2_ready || !state.permissions.deployments;
    if (summary) summary.disabled = !state.phase2_ready || !state.permissions.manage;
  }

  function renderAll() {
    renderSchema();
    if (state.schema_ready === false) return;
    renderOverview();
    renderIntelligenceStrip();
    renderFeed();
    renderThreads();
    renderCorrelations();
    renderAnomalies();
    renderEscalations();
    renderDeployments();
    renderRemediation();
    renderMonitors();
    renderFindings();
  }

  function mergeState(next) {
    if (!next) return;
    Object.keys(next).forEach(function (key) { state[key] = next[key]; });
    state.permissions = next.permissions || state.permissions || {};
    state.remediation = next.remediation || state.remediation || { adapters: [], reviews: [], executions: [] };
    renderAll();
    startStream();
  }

  async function load(options) {
    options = options || {};
    setStatus('Loading Main Admin Agent intelligence…');
    var thread = options.thread_id || (state.active_thread && state.active_thread.id) || '';
    var params = new URLSearchParams({ after: '0', domain: currentDomain, thread_id: thread, skip_scan: options.skip_scan ? '1' : '0' });
    var data = await request(apiEndpoint + '?' + params.toString());
    mergeState(data);
    setStatus('Main Admin Agent loaded. Monitoring ' + (state.monitors || []).length + ' systems.');
    return data;
  }

  async function send(message) {
    if (!state.permissions.chat) throw new Error('Main Admin Agent chat permission is not active.');
    setStatus('Preparing database-grounded intelligence report…');
    var data = await post({ action: 'send_message', message: message, thread_id: state.active_thread && state.active_thread.id || '', domain: currentDomain });
    mergeState(data.state || data);
    setStatus('System intelligence report completed. No AI credits used.');
  }

  async function runScan() {
    if (!state.permissions.manage) throw new Error('Monitor management permission is required.');
    var button = q('[data-admin-agent-scan]');
    var text = button.textContent;
    button.disabled = true;
    button.textContent = state.phase2_ready ? 'Analyzing…' : 'Scanning…';
    setStatus(state.phase2_ready ? 'Running monitors, baselines, correlations, escalations, and summaries…' : 'Running registered system monitors…');
    try {
      var data = await post({ action: 'run_scan', domain: currentDomain, thread_id: state.active_thread && state.active_thread.id || '' });
      mergeState(data.state || data);
      setStatus(state.phase2_ready ? 'Full system analysis completed.' : 'System scan completed.');
    } finally {
      button.disabled = false;
      button.textContent = text;
    }
  }

  async function newThread() {
    var data = await post({ action: 'new_thread', domain: currentDomain });
    mergeState(data.state || data);
    setStatus('New Main Admin Agent chat created.');
  }

  async function findingAction(id, action) {
    var note = '';
    if (action === 'resolve' || action === 'dismiss') {
      note = window.prompt(action === 'resolve' ? 'Resolution note:' : 'Dismissal note:', '');
      if (!note) return;
    }
    setStatus('Updating system finding…');
    var data = await post({ action: 'finding_action', finding_id: id, action_key: action, note: note, domain: currentDomain, thread_id: state.active_thread && state.active_thread.id || '' });
    mergeState(data.state || data);
    setStatus('System finding updated.');
  }

  async function intelligenceAction(type, id, action) {
    var note = '';
    if (action === 'resolve' || action === 'dismiss') {
      note = window.prompt(action === 'resolve' ? 'Resolution note:' : 'Dismissal note:', '');
      if (!note) return;
    }
    setStatus('Updating ' + type + '…');
    var data = await post({ action: 'intelligence_action', source_type: type, source_id: id, action_key: action, note: note, domain: currentDomain });
    mergeState(data.state || data);
    setStatus(label(type) + ' updated.');
  }

  function actionForDomain(domain) {
    return { security: 'investigate_security_events', operations: 'declare_operations_incident', support: 'run_queue_automation', automation: 'run_queue_automation', notifications: 'retry_failed_notifications', database: 'generate_migration_plan', ai_accounting: 'run_ai_credit_reconciliation', system: 'run_admin_agent_scan', intelligence: 'run_admin_agent_scan' }[domain] || 'run_admin_agent_scan';
  }

  async function requestAction(id, domain) {
    var actionKey = actionForDomain(domain);
    var rationale = window.prompt('Why should this action enter Admin Review?', 'Review the recommended response for this system finding.');
    if (!rationale) return;
    setStatus('Creating review-gated action request…');
    var data = await post({ action: 'request_action', action_key: actionKey, finding_id: id, rationale: rationale, domain: currentDomain });
    mergeState(data.state || data);
    setStatus('Action request added to review. Nothing was executed.');
  }

  async function requestCorrelationAction(actionKey, correlationId) {
    var rationale = window.prompt('Why should this correlated response enter Admin Review?', 'Review the recommended action for correlation ' + correlationId + '.');
    if (!rationale) return;
    var data = await post({ action: 'request_action', action_key: actionKey, rationale: rationale, payload: { correlation_id: correlationId }, domain: currentDomain });
    mergeState(data.state || data);
    setStatus('Correlated response added to Admin Review. Nothing was executed.');
  }

  async function acknowledgeEscalation(id) {
    var data = await post({ action: 'acknowledge_escalation', escalation_id: id, domain: currentDomain });
    mergeState(data.state || data);
    setStatus('Escalation acknowledged.');
  }

  async function reviewAction(id, decision) {
    var note = window.prompt(decision === 'approve' ? 'Approval note:' : 'Rejection note:', decision === 'approve' ? 'Reviewed and approved for controlled execution.' : 'Rejected after administrative review.');
    if (!note) return;
    var data = await post({ action: 'review_action', review_id: id, decision: decision, note: note, domain: currentDomain });
    mergeState(data.state || data);
    setStatus(decision === 'approve' ? 'Remediation approved. A separate typed confirmation is required to execute.' : 'Remediation rejected.');
  }

  async function executeAction(executionId, actionKey) {
    var expected = 'EXECUTE ' + actionKey;
    var confirmation = window.prompt('Type the exact confirmation to execute the approved adapter:', expected);
    if (!confirmation) return;
    setStatus('Executing approved remediation adapter…');
    var data = await post({ action: 'execute_action', execution_id: executionId, confirmation: confirmation, domain: currentDomain });
    mergeState(data.state || data);
    setStatus('Approved remediation completed.');
  }

  async function recordDeployment() {
    if (!state.permissions.deployments) throw new Error('Deployment recording permission is required.');
    var commit = window.prompt('Deployment commit SHA:', '');
    if (!commit) return;
    var branch = window.prompt('Deployment branch:', 'integration-from-repair-20260628');
    if (!branch) return;
    var labelText = window.prompt('Release label or note:', 'Production deployment');
    var data = await post({ action: 'record_deployment', commit_sha: commit, branch_name: branch, environment_key: 'production', source_type: 'manual', release_label: labelText || '', domain: currentDomain });
    mergeState(data.state || data);
    setStatus('Deployment recorded and correlation analysis refreshed.');
  }

  async function generateSummary() {
    var data = await post({ action: 'generate_summary', period_type: 'manual', domain: currentDomain });
    mergeState(data.state || data);
    setStatus('Executive system summary generated.');
    insertPrompt('Executive summary');
  }

  function insertPrompt(text) {
    var textarea = q('[data-admin-agent-textarea]');
    if (!textarea) return;
    textarea.value = text;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
  }

  function bindPrompt(button) {
    button.onclick = function () { insertPrompt(button.getAttribute('data-admin-agent-prompt') || button.textContent); };
  }

  function startPolling() {
    if (pollTimer) return;
    var live = q('[data-admin-agent-live-label]');
    if (live) live.textContent = 'Polling';
    pollTimer = window.setInterval(function () { load({ skip_scan: true }).catch(showError); }, 15000);
  }

  function startStream() {
    if (state.schema_ready === false) return;
    if (source) { source.close(); source = null; }
    if (!window.EventSource) { startPolling(); return; }
    var live = q('[data-admin-agent-live-label]');
    var liveWrap = live && live.parentElement;
    var params = new URLSearchParams({ after: String(state.event_cursor || 0), domain: currentDomain });
    source = new EventSource(streamEndpoint + '?' + params.toString(), { withCredentials: true });
    source.addEventListener('open', function () {
      if (live) live.textContent = 'Live';
      if (liveWrap) liveWrap.classList.remove('is-offline');
      if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    });
    source.addEventListener('snapshot', function (event) {
      var data = JSON.parse(event.data || '{}');
      ['phase2_ready','phase2_schema','health','last_scan','findings','anomalies','correlations','deployments','escalations','executive_summaries','remediation'].forEach(function (key) {
        if (data[key] !== undefined) state[key] = data[key];
      });
      renderAll();
    });
    source.addEventListener('events', function (event) {
      var data = JSON.parse(event.data || '{}');
      var known = {};
      (state.events || []).forEach(function (item) { known[item.id] = true; });
      (data.events || []).forEach(function (item) { if (!known[item.id]) state.events.push(item); });
      state.events = state.events.slice(-200);
      state.event_cursor = Number(data.cursor || state.event_cursor || 0);
      renderFeed();
    });
    source.onerror = function () {
      if (live) live.textContent = 'Reconnecting';
      if (liveWrap) liveWrap.classList.add('is-offline');
      if (source) { source.close(); source = null; }
      startPolling();
    };
  }

  function bindDrawer() {
    var drawer = q('[data-admin-agent-drawer]');
    var backdrop = document.querySelector('[data-admin-agent-drawer-close]');
    function open() {
      drawer.setAttribute('aria-hidden', 'false');
      drawer.classList.add('is-open');
      qa('[data-admin-agent-drawer-close]', document).forEach(function (item) { if (item.classList.contains('mg-agent-chat-drawer-backdrop')) item.hidden = false; });
      var trigger = q('[data-admin-agent-controls]'); if (trigger) trigger.setAttribute('aria-expanded', 'true');
    }
    function close() {
      drawer.setAttribute('aria-hidden', 'true');
      drawer.classList.remove('is-open');
      qa('[data-admin-agent-drawer-close]', document).forEach(function (item) { if (item.classList.contains('mg-agent-chat-drawer-backdrop')) item.hidden = true; });
      var trigger = q('[data-admin-agent-controls]'); if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }
    var trigger = q('[data-admin-agent-controls]'); if (trigger) trigger.onclick = open;
    qa('[data-admin-agent-drawer-close]', document).forEach(function (item) { item.addEventListener('click', close); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
    if (backdrop) backdrop.hidden = true;
  }

  function bindPage() {
    qa('[data-admin-agent-prompt]').forEach(bindPrompt);
    var form = q('[data-admin-agent-form]');
    var textarea = q('[data-admin-agent-textarea]');
    var sendButton = q('[data-admin-agent-send]');
    if (textarea) {
      textarea.addEventListener('input', function () {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 160) + 'px';
        if (sendButton) sendButton.disabled = !textarea.value.trim() || !state.permissions.chat;
      });
      textarea.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); if (form) form.requestSubmit(); }
      });
    }
    if (form) form.addEventListener('submit', function (event) {
      event.preventDefault();
      var message = textarea && textarea.value.trim();
      if (!message) return;
      if (sendButton) sendButton.disabled = true;
      send(message).then(function () { textarea.value = ''; textarea.dispatchEvent(new Event('input')); }).catch(showError);
    });
    var scan = q('[data-admin-agent-scan]'); if (scan) scan.onclick = function () { runScan().catch(showError); };
    var summary = q('[data-admin-agent-summary]'); if (summary) summary.onclick = function () { generateSummary().catch(showError); };
    var deployment = q('[data-admin-agent-deployment]'); if (deployment) deployment.onclick = function () { recordDeployment().catch(showError); };
    var refresh = q('[data-admin-agent-refresh]'); if (refresh) refresh.onclick = function () { load({ skip_scan: true }).catch(showError); };
    var newChat = q('[data-admin-agent-new-thread]'); if (newChat) newChat.onclick = function () { newThread().catch(showError); };
    var threadSelect = q('[data-admin-agent-thread-select]'); if (threadSelect) threadSelect.onchange = function () { load({ thread_id: threadSelect.value, skip_scan: true }).catch(showError); };
    var domainSelect = q('[data-admin-agent-domain]'); if (domainSelect) domainSelect.onchange = function () { currentDomain = domainSelect.value || ''; var context = q('[data-admin-agent-context]'); if (context) context.textContent = (currentDomain ? label(currentDomain) : 'All systems') + ' · Live correlations · Review-gated execution'; load({ skip_scan: true }).catch(showError); };
    var contextToggle = q('[data-admin-agent-context-toggle]');
    var contextMenu = q('[data-admin-agent-context-menu]');
    if (contextToggle && contextMenu) contextToggle.onclick = function () { var hidden = contextMenu.hidden; contextMenu.hidden = !hidden; contextToggle.setAttribute('aria-expanded', hidden ? 'true' : 'false'); };
    bindDrawer();
  }

  bindPage();
  load().catch(showError);
})(window, document);
