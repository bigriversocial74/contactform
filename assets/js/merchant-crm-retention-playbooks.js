document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var shell = document.querySelector('[data-merchant-crm-shell]');
  var panel = shell && shell.querySelector('[data-crm-tab-panel="retention"]');
  if (!shell || !panel || !window.Microgifter) return;

  var state = { playbooks: [], recommendations: [], summary: {} };
  var loaded = false;

  function qs(selector, root) {
    return (root || panel).querySelector(selector);
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function toast(message) {
    if (Microgifter.toast) Microgifter.toast(message);
    else window.alert(message);
  }

  function busy(button, on, label) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (label || 'Working…') : button.dataset.originalText;
  }

  function renderSummary() {
    var summary = state.summary || {};
    var box = qs('[data-retention-summary]');
    if (!box) return;
    box.innerHTML = [
      ['Recommendations', summary.total],
      ['Task-ready', summary.create_followup_task],
      ['Win-back', summary.suggest_reward_or_message],
      ['Reward invites', summary.suggest_reward_invite]
    ].map(function (item) {
      return '<article><strong>' + Number(item[1] || 0) + '</strong><span>' + item[0] + '</span></article>';
    }).join('');
  }

  function playbookCard(playbook) {
    return '<article class="mg-retention-playbook-card"><div><strong>' + esc(playbook.title) + '</strong><p>' + esc(playbook.trigger) + '</p></div><span>' + esc(playbook.automation_level).replace(/_/g, ' ') + '</span><small>Next: ' + esc(playbook.recommended_next_action) + '</small><button type="button" data-retention-run-playbook="' + esc(playbook.key) + '">Run</button></article>';
  }

  function recommendationCard(recommendation) {
    return '<article class="mg-retention-rec-card"><div><strong>' + esc(recommendation.customer_name || 'Customer') + '</strong><small>' + esc(recommendation.playbook_title || 'Playbook') + ' · ' + esc(recommendation.campaign_title || '') + '</small><p>' + esc(recommendation.reason || 'Matched retention rule.') + '</p><em>Recommended next action: ' + esc(recommendation.recommended_next_action || 'Create follow-up') + '</em><span>Triggered by playbook</span></div><div class="mg-retention-rec-actions"><a href="' + esc(recommendation.customer_url || '#') + '">Profile</a><a href="' + esc(recommendation.message_url || '#') + '">Message</a><a href="' + esc(recommendation.reward_url || '#') + '">Reward</a><a href="/merchant-agent-execution.php">Execute</a><a href="/merchant-agent-approvals.php">Review</a><a href="/merchant-agent-monitor.php">Monitor</a><a href="/merchant-automation.php">Controls</a><button type="button" data-retention-run-rec="' + esc(recommendation.id) + '" data-playbook-key="' + esc(recommendation.playbook_key) + '">Create task</button></div></article>';
  }

  function render() {
    renderSummary();
    var playbooks = qs('[data-retention-playbooks]');
    var recommendations = qs('[data-retention-recommendations]');
    if (playbooks) {
      playbooks.innerHTML = state.playbooks.length
        ? state.playbooks.map(playbookCard).join('')
        : '<div class="mg-empty-state"><strong>No playbooks available</strong></div>';
    }
    if (recommendations) {
      recommendations.innerHTML = state.recommendations.length
        ? state.recommendations.map(recommendationCard).join('')
        : '<div class="mg-empty-state"><strong>No recommendations right now</strong><p>The rules are active; matching customers will appear here.</p></div>';
    }
  }

  async function load(force) {
    if (loaded && !force) return;
    try {
      var response = await Microgifter.get('/api/merchant/crm-playbooks.php?limit=40');
      var data = response.data || response;
      state.playbooks = data.playbooks || [];
      state.recommendations = data.recommendations || [];
      state.summary = data.summary || {};
      loaded = true;
      render();
    } catch (error) {
      var box = qs('[data-retention-recommendations]');
      if (box) box.innerHTML = '<div class="mg-empty-state"><strong>Unable to load retention playbooks</strong><p>' + esc(error.message || 'Try again.') + '</p></div>';
    }
  }

  async function run(payload, button) {
    busy(button, true, 'Running…');
    try {
      var response = await Microgifter.post('/api/merchant/crm-playbook-runner.php', payload);
      var data = response.data || response;
      var summary = data.summary || {};
      toast('Playbook run complete: ' + Number(summary.created || 0) + ' created, ' + Number(summary.duplicates || 0) + ' duplicates, ' + Number(summary.approval_required || 0) + ' approval-gated.');
      loaded = false;
      await load(true);
      document.dispatchEvent(new CustomEvent('mg:crm-retention:updated', { detail: data }));
    } catch (error) {
      toast(error.message || 'Unable to run playbook.');
    } finally {
      busy(button, false);
    }
  }

  document.addEventListener('mg:crm-tab:changed', function (event) {
    if (event.detail && event.detail.tab === 'retention') load(false);
  });

  document.addEventListener('click', function (event) {
    var refresh = event.target.closest && event.target.closest('[data-retention-refresh]');
    if (refresh) {
      event.preventDefault();
      loaded = false;
      load(true);
      return;
    }

    var runAll = event.target.closest && event.target.closest('[data-retention-run-all]');
    if (runAll) {
      event.preventDefault();
      run({ playbook_key: '' }, runAll);
      return;
    }

    var playbook = event.target.closest && event.target.closest('[data-retention-run-playbook]');
    if (playbook) {
      event.preventDefault();
      run({ playbook_key: playbook.getAttribute('data-retention-run-playbook') || '' }, playbook);
      return;
    }

    var recommendation = event.target.closest && event.target.closest('[data-retention-run-rec]');
    if (recommendation) {
      event.preventDefault();
      run({
        playbook_key: recommendation.getAttribute('data-playbook-key') || '',
        recommendation_ids: [recommendation.getAttribute('data-retention-run-rec') || '']
      }, recommendation);
    }
  });

  var initial = (new URLSearchParams(location.search || '').get('tab') || (location.hash || '').replace(/^#crm-/, '')).trim();
  if (initial === 'retention') load(false);
});
