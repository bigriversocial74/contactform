document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-agent-control-center]');
  var panel = root ? root.querySelector('[data-agent-control-panel="approvals"]') : null;
  if (!root || !panel || !window.Microgifter) return;

  var MG = window.Microgifter;
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-agent-control-tab]'));
  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-agent-control-panel]'));
  var statusFilter = panel.querySelector('[data-approval-status]');
  var summaryNode = panel.querySelector('[data-approval-summary]');
  var statusNode = panel.querySelector('[data-approval-status-text]');
  var loadingNode = panel.querySelector('[data-approval-loading]');
  var emptyNode = panel.querySelector('[data-approval-empty]');
  var errorNode = panel.querySelector('[data-approval-error]');
  var errorMessage = panel.querySelector('[data-approval-error-message]');
  var listNode = panel.querySelector('[data-approval-list]');
  var paginationNode = panel.querySelector('[data-approval-pagination]');
  var moreButton = panel.querySelector('[data-approval-more]');
  var review = panel.querySelector('[data-plan-review]');
  var reviewTitle = panel.querySelector('[data-plan-review-title]');
  var reviewEyebrow = panel.querySelector('[data-plan-review-eyebrow]');
  var contextNode = panel.querySelector('[data-plan-context]');
  var actionsNode = panel.querySelector('[data-plan-actions]');
  var reviewStatus = panel.querySelector('[data-plan-review-status]');
  var approvals = [];
  var summary = {};
  var nextCursor = null;
  var loading = false;
  var loadedOnce = false;
  var currentPlanId = null;

  function payload(response) { return response && response.data ? response.data : response; }
  function hide(node, value) { if (node) node.hidden = Boolean(value); }
  function text(node, value) { if (node) node.textContent = value == null ? '' : String(value); }
  function element(tag, className, value) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined && value !== null) node.textContent = String(value);
    return node;
  }
  function label(value) { return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
  function formatDate(value) {
    if (!value) return 'Not set';
    var date = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString([], { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
  }
  function relativeExpiry(value) {
    if (!value) return 'No expiration';
    var seconds = Math.round((new Date(String(value).replace(' ', 'T') + 'Z').getTime() - Date.now()) / 1000);
    if (seconds <= 0) return 'Expired';
    if (seconds < 3600) return 'Expires in ' + Math.max(1, Math.round(seconds / 60)) + ' min';
    if (seconds < 86400) return 'Expires in ' + Math.round(seconds / 3600) + ' hr';
    return 'Expires in ' + Math.round(seconds / 86400) + ' days';
  }

  function setView(view, updateUrl) {
    var allowed = ['builder','strategies','approvals'];
    var active = allowed.includes(view) ? view : 'builder';
    tabs.forEach(function (tab) {
      var selected = tab.dataset.agentControlTab === active;
      if (tab.dataset.agentControlTab) {
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      }
    });
    panels.forEach(function (candidate) { candidate.hidden = candidate.dataset.agentControlPanel !== active; });
    if (updateUrl) {
      var url = new URL(window.location.href);
      if (active === 'builder') url.searchParams.delete('view'); else url.searchParams.set('view', active);
      window.history.replaceState({}, '', url);
    }
    if (active === 'approvals' && !loadedOnce) loadApprovals(false);
  }

  tabs.forEach(function (tab) {
    if (!tab.dataset.agentControlTab) return;
    tab.addEventListener('click', function () { setView(tab.dataset.agentControlTab, true); });
  });

  function renderSummary() {
    summaryNode.replaceChildren();
    ['pending','approved','rejected','expired','canceled'].forEach(function (status) {
      var button = element('button', 'mg-approval-summary-card is-' + status);
      button.type = 'button';
      button.dataset.approvalSummaryStatus = status;
      button.append(element('strong', '', Number(summary[status] || 0)), element('span', '', label(status)));
      if (statusFilter.value === status) button.classList.add('is-active');
      summaryNode.appendChild(button);
    });
  }

  function requestSummary(request) {
    if (!request || typeof request !== 'object') return '';
    return request.expected_effect || request.summary || request.message || request.title || '';
  }

  function approvalCard(item) {
    var card = element('article', 'mg-approval-card is-' + item.status + ' risk-' + item.action.risk);
    card.dataset.approvalId = item.id;
    var header = element('header');
    var identity = element('div');
    identity.append(element('span', 'mg-approval-eyebrow', item.plan.agent.name + ' · ' + item.plan.strategy.name), element('h3', '', label(item.action.type)));
    header.append(identity, element('span', 'mg-approval-state', label(item.status)));
    card.appendChild(header);

    var reason = item.action.approval ? item.action.approval.requested_reason : '';
    if (reason) card.appendChild(element('p', 'mg-approval-reason', reason));

    var facts = element('dl', 'mg-approval-facts');
    [
      ['Risk', label(item.action.risk)],
      ['Target', label(item.action.target.type)],
      ['Trigger', label(item.plan.trigger.type)],
      ['Strategy version', 'v' + item.plan.strategy.version],
      ['Plan actions', item.plan.summary.total],
      ['Expiration', relativeExpiry(item.action.approval && item.action.approval.expires_at)]
    ].forEach(function (fact) {
      var entry = element('div');
      entry.append(element('dt', '', fact[0]), element('dd', '', fact[1]));
      facts.appendChild(entry);
    });
    card.appendChild(facts);

    var effect = requestSummary(item.action.request);
    if (effect) {
      var effectNode = element('div', 'mg-approval-effect');
      effectNode.append(element('strong', '', 'Expected effect'), element('span', '', effect));
      card.appendChild(effectNode);
    }

    var footer = element('footer');
    var reviewButton = element('button', 'mg-btn mg-btn-primary', item.permissions.can_decide ? 'Review plan' : 'View plan');
    reviewButton.type = 'button';
    reviewButton.dataset.approvalReview = item.id;
    reviewButton.dataset.runId = item.plan.id;
    footer.appendChild(reviewButton);
    card.appendChild(footer);
    return card;
  }

  function renderApprovals(append) {
    if (!append) listNode.replaceChildren();
    approvals.forEach(function (item) {
      if (!listNode.querySelector('[data-approval-id="' + CSS.escape(item.id) + '"]')) listNode.appendChild(approvalCard(item));
    });
    hide(listNode, approvals.length === 0);
    hide(emptyNode, approvals.length !== 0);
    hide(paginationNode, !nextCursor);
    renderSummary();
  }

  async function loadApprovals(append) {
    if (loading) return;
    loading = true;
    hide(errorNode, true);
    hide(emptyNode, true);
    if (!append) { hide(listNode, true); hide(loadingNode, false); }
    text(statusNode, 'Loading approval requests…');
    var query = new URLSearchParams({ status: statusFilter.value, limit:'20' });
    if (append && nextCursor) query.set('cursor', nextCursor);
    try {
      var response = await MG.get('/api/agents/approvals.php?' + query.toString());
      var data = payload(response);
      if (append) approvals = approvals.concat(data.approvals.items || []); else approvals = data.approvals.items || [];
      nextCursor = data.approvals.next_cursor || null;
      summary = data.summary || {};
      loadedOnce = true;
      renderApprovals(append);
      text(statusNode, approvals.length ? approvals.length + ' approval requests loaded.' : 'No approvals in this view.');
      var requested = new URLSearchParams(window.location.search).get('approval');
      if (requested) {
        var match = approvals.find(function (item) { return item.id === requested; });
        if (match) openPlan(match.plan.id, match.id);
      }
    } catch (error) {
      hide(errorNode, false);
      text(errorMessage, error.message || 'Unable to load approval requests.');
      text(statusNode, '');
    } finally {
      loading = false;
      hide(loadingNode, true);
      moreButton.disabled = false;
    }
  }

  function contextItem(term, description) {
    var node = element('div');
    node.append(element('dt', '', term), element('dd', '', description));
    return node;
  }

  function renderPlanContext(plan) {
    contextNode.replaceChildren();
    var heading = element('div', 'mg-plan-context-heading');
    heading.append(element('span', '', plan.agent.name), element('h3', '', plan.strategy.name), element('p', '', plan.strategy.objective));
    contextNode.appendChild(heading);
    var facts = element('dl', 'mg-plan-context-facts');
    facts.append(
      contextItem('Plan status', label(plan.status)),
      contextItem('Strategy version', 'v' + plan.strategy.version),
      contextItem('Trigger', label(plan.trigger.type)),
      contextItem('Requested', formatDate(plan.requested_at)),
      contextItem('Actions', plan.summary.total),
      contextItem('Approval required', plan.summary.approval_required)
    );
    contextNode.appendChild(facts);
    var why = plan.input.reason || plan.input.summary || plan.input.prompt || '';
    if (why) {
      var whyNode = element('div', 'mg-plan-why');
      whyNode.append(element('strong', '', 'Why this plan exists'), element('p', '', why));
      contextNode.appendChild(whyNode);
    }
  }

  function decisionButton(action, approval, title, className) {
    var button = element('button', className, title);
    button.type = 'button';
    button.dataset.approvalDecision = action;
    button.dataset.approvalId = approval.id;
    return button;
  }

  function renderPlanAction(action) {
    var card = element('article', 'mg-plan-action risk-' + action.risk + ' is-' + action.status);
    card.dataset.planActionId = action.id;
    var header = element('header');
    var identity = element('div');
    identity.append(element('span', '', 'Action ' + action.sequence + ' · ' + label(action.risk) + ' risk'), element('h4', '', label(action.type)));
    header.append(identity, element('span', 'mg-plan-action-state', label(action.status)));
    card.appendChild(header);

    var target = element('div', 'mg-plan-target');
    target.append(element('strong', '', label(action.target.type)), element('code', '', action.target.reference));
    card.appendChild(target);

    var effect = requestSummary(action.request);
    if (effect) card.appendChild(element('p', 'mg-plan-action-effect', effect));

    if (action.approval) {
      var approval = action.approval;
      var approvalBox = element('section', 'mg-plan-approval-box');
      approvalBox.append(element('strong', '', approval.requested_reason || 'Approval required.'), element('span', '', relativeExpiry(approval.expires_at)));
      if (approval.decision_reason) approvalBox.appendChild(element('p', '', 'Decision reason: ' + approval.decision_reason));
      var pending = approval.status === 'pending' && !approval.expired;
      if (pending) {
        var field = element('label', 'mg-plan-reason-field');
        field.appendChild(element('span', '', ['high','critical'].includes(action.risk) ? 'Decision reason required' : 'Decision reason (optional)'));
        var textarea = document.createElement('textarea');
        textarea.rows = 3;
        textarea.maxLength = 1000;
        textarea.dataset.approvalReason = approval.id;
        textarea.placeholder = 'Record why you approve or reject this action.';
        field.appendChild(textarea);
        approvalBox.appendChild(field);
        var controls = element('div', 'mg-plan-decision-controls');
        controls.append(decisionButton('reject', approval, 'Reject action', 'mg-btn mg-btn-danger'), decisionButton('approve', approval, 'Approve action', 'mg-btn mg-btn-primary'));
        approvalBox.appendChild(controls);
      }
      card.appendChild(approvalBox);
    }
    return card;
  }

  async function openPlan(runId, approvalId) {
    currentPlanId = runId;
    review.hidden = false;
    actionsNode.replaceChildren();
    contextNode.replaceChildren();
    text(reviewEyebrow, 'Workflow plan');
    text(reviewTitle, 'Loading plan…');
    text(reviewStatus, 'Loading complete plan context…');
    review.scrollIntoView({ behavior:'smooth', block:'start' });
    try {
      var response = await MG.get('/api/agents/plans.php?run_id=' + encodeURIComponent(runId));
      var plan = payload(response).plan;
      text(reviewTitle, plan.strategy.name);
      renderPlanContext(plan);
      plan.actions.forEach(function (action) { actionsNode.appendChild(renderPlanAction(action)); });
      text(reviewStatus, 'Review each action separately. No action executes from this screen.');
      if (approvalId) {
        var target = actionsNode.querySelector('[data-approval-id="' + CSS.escape(approvalId) + '"]');
        if (target) target.scrollIntoView({ behavior:'smooth', block:'center' });
      }
    } catch (error) {
      text(reviewTitle, 'Unable to load plan');
      text(reviewStatus, error.message || 'Unable to load workflow plan.');
    }
  }

  function closePlan() {
    review.hidden = true;
    currentPlanId = null;
    contextNode.replaceChildren();
    actionsNode.replaceChildren();
    text(reviewStatus, '');
  }

  async function decide(button) {
    var approvalId = button.dataset.approvalId;
    var decision = button.dataset.approvalDecision;
    var reasonField = review.querySelector('[data-approval-reason="' + CSS.escape(approvalId) + '"]');
    var reason = reasonField ? reasonField.value.trim() : '';
    var actionCard = button.closest('[data-plan-action-id]');
    var riskText = actionCard ? actionCard.querySelector('header span').textContent.toLowerCase() : '';
    if ((riskText.includes('high risk') || riskText.includes('critical risk')) && !reason) {
      text(reviewStatus, 'A decision reason is required for high-risk actions.');
      if (reasonField) reasonField.focus();
      return;
    }
    if (decision === 'reject' && !window.confirm('Reject this action? It will not execute.')) return;
    if (MG.setBusy) MG.setBusy(button, true, decision === 'approve' ? 'Approving…' : 'Rejecting…'); else button.disabled = true;
    text(reviewStatus, 'Recording decision…');
    try {
      var response = await MG.post('/api/agents/approvals.php', { approval_id:approvalId, decision:decision, reason:reason });
      text(reviewStatus, response.message || 'Decision recorded.');
      await loadApprovals(false);
      if (currentPlanId) await openPlan(currentPlanId, approvalId);
    } catch (error) {
      text(reviewStatus, error.message || 'Unable to record decision.');
    } finally {
      if (MG.setBusy) MG.setBusy(button, false); else button.disabled = false;
    }
  }

  listNode.addEventListener('click', function (event) {
    var button = event.target.closest('[data-approval-review]');
    if (button) openPlan(button.dataset.runId, button.dataset.approvalReview);
  });
  actionsNode.addEventListener('click', function (event) {
    var button = event.target.closest('[data-approval-decision]');
    if (button) decide(button);
  });
  summaryNode.addEventListener('click', function (event) {
    var button = event.target.closest('[data-approval-summary-status]');
    if (!button) return;
    statusFilter.value = button.dataset.approvalSummaryStatus;
    nextCursor = null;
    loadApprovals(false);
  });
  statusFilter.addEventListener('change', function () { nextCursor = null; loadApprovals(false); });
  panel.querySelector('[data-approval-refresh]').addEventListener('click', function () { nextCursor = null; loadApprovals(false); });
  panel.querySelector('[data-approval-retry]').addEventListener('click', function () { loadApprovals(false); });
  moreButton.addEventListener('click', function () { moreButton.disabled = true; loadApprovals(true); });
  panel.querySelector('[data-plan-review-close]').addEventListener('click', closePlan);

  setView(new URLSearchParams(window.location.search).get('view'), false);
});
