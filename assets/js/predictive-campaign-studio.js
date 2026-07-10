window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var MG = window.Microgifter;
    var root = document.querySelector('[data-campaign-command-center]');
    if (!root || !MG || typeof MG.get !== 'function' || typeof MG.post !== 'function') return;

    var nav = root.querySelector('.mg-campaign-tabs');
    var panels = root.querySelector('.mg-campaign-tab-panels');
    var overviewLink = nav ? nav.querySelector('[data-campaign-tab="overview"]') : null;
    var overviewPanel = panels ? panels.querySelector('[data-campaign-tab-panel="overview"]') : null;
    if (!nav || !panels || !overviewLink || !overviewPanel) return;

    var state = {
      loaded: false,
      loading: false,
      filter: 'open',
      data: null
    };

    function unwrap(response) {
      return response && response.data ? response.data : response;
    }

    function esc(value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
      });
    }

    function label(value) {
      return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    }

    function number(value) {
      return Number(value || 0).toLocaleString();
    }

    function percent(value) {
      return Math.max(0, Math.min(100, Number(value || 0))).toFixed(1) + '%';
    }

    function money(cents) {
      return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(Number(cents || 0) / 100);
    }

    function csrf() {
      return typeof MG.getCsrfToken === 'function' ? MG.getCsrfToken() : '';
    }

    var link = document.createElement('a');
    link.href = '#campaign-recommendations';
    link.textContent = 'Recommendations';
    link.setAttribute('data-predictive-campaign-tab-link', '');
    overviewLink.insertAdjacentElement('afterend', link);

    var panel = document.createElement('section');
    panel.className = 'mg-campaign-tab-panel';
    panel.id = 'campaign-recommendations';
    panel.hidden = true;
    panel.setAttribute('data-campaign-tab-panel', 'predictive_recommendations');
    panel.setAttribute('aria-label', 'Predictive campaign recommendations');
    panel.innerHTML = '' +
      '<div class="mg-predictive-studio" data-predictive-campaign-studio>' +
        '<section class="mg-predictive-hero">' +
          '<div class="mg-predictive-hero-copy"><span class="mg-eyebrow">Predictive Commerce Intelligence</span><h2>Campaign and reward recommendations</h2><p>Store trends create the strategy. Existing Reward Inventory supplies approved offers. Existing Campaigns define the experience. Customer behavior controls eligibility, timing, and personalization.</p></div>' +
          '<div class="mg-predictive-hero-actions"><select class="mg-input" data-predictive-filter aria-label="Recommendation status"><option value="open">Open</option><option value="materialized">Drafts created</option><option value="dismissed">Dismissed</option><option value="all">All</option></select><button class="mg-btn mg-btn-soft" type="button" data-predictive-refresh>Refresh</button><button class="mg-btn mg-btn-primary" type="button" data-predictive-generate>Generate recommendations</button></div>' +
        '</section>' +
        '<div class="mg-predictive-status" data-predictive-status>Open this tab to load current store trends.</div>' +
        '<section class="mg-predictive-kpis" data-predictive-kpis></section>' +
        '<section class="mg-predictive-authority" data-predictive-authority></section>' +
        '<section class="mg-predictive-list" data-predictive-list><div class="mg-predictive-empty"><h3>Loading Predictive Campaign Studio</h3><p>Reading merchant trends and current reward/campaign inventory.</p></div></section>' +
      '</div>';
    overviewPanel.insertAdjacentElement('afterend', panel);

    var listNode = panel.querySelector('[data-predictive-list]');
    var kpiNode = panel.querySelector('[data-predictive-kpis]');
    var authorityNode = panel.querySelector('[data-predictive-authority]');
    var statusNode = panel.querySelector('[data-predictive-status]');
    var filterNode = panel.querySelector('[data-predictive-filter]');
    var refreshButton = panel.querySelector('[data-predictive-refresh]');
    var generateButton = panel.querySelector('[data-predictive-generate]');

    function setStatus(message, type) {
      statusNode.textContent = message || '';
      statusNode.classList.toggle('is-error', type === 'error');
      statusNode.classList.toggle('is-success', type === 'success');
    }

    function activate() {
      root.querySelectorAll('[data-campaign-tab-panel]').forEach(function (item) {
        var active = item === panel;
        item.classList.toggle('is-active', active);
        item.hidden = !active;
      });
      root.querySelectorAll('[data-campaign-tab-link]').forEach(function (item) {
        item.classList.remove('is-active');
        item.removeAttribute('aria-current');
      });
      link.classList.add('is-active');
      link.setAttribute('aria-current', 'page');
      if (history.replaceState) history.replaceState(null, '', '#campaign-recommendations');
      if (!state.loaded && !state.loading) load();
    }

    link.addEventListener('click', function (event) {
      event.preventDefault();
      activate();
    });

    root.querySelectorAll('[data-campaign-tab-link]').forEach(function (item) {
      item.addEventListener('click', function () {
        link.classList.remove('is-active');
        link.removeAttribute('aria-current');
      });
    });

    function renderKpis(snapshot) {
      snapshot = snapshot || {};
      var behavior = snapshot.behavior || {};
      var wallet = snapshot.wallet || {};
      var campaigns = snapshot.campaigns || {};
      var rewards = snapshot.rewards || {};
      var items = [
        ['Behavior profiles', behavior.total_profiles, 'Merchant/customer memories'],
        ['High inactivity risk', behavior.dormant_or_high_risk, 'Comeback opportunity'],
        ['Engaged or loyal', behavior.engaged_or_loyal, 'Loyalty opportunity'],
        ['Unclaimed 7+ days', wallet.unclaimed_7d, 'Wallet recovery signal'],
        ['Active rewards', rewards.active, 'Current Reward Inventory'],
        ['Active campaigns', campaigns.active, 'Current Campaigns system']
      ];
      kpiNode.innerHTML = items.map(function (item) {
        return '<article><span>' + esc(item[0]) + '</span><strong>' + esc(number(item[1])) + '</strong><small>' + esc(item[2]) + '</small></article>';
      }).join('');
    }

    function renderAuthority(authority) {
      authority = authority || {};
      authorityNode.innerHTML = '' +
        '<span>Rewards remain in <b>reward_templates</b></span>' +
        '<span>Campaigns remain in <b>campaigns</b></span>' +
        '<span>Approval creates drafts only</span>' +
        '<span>No automatic launch, message, or reward issue</span>';
    }

    function evidenceHtml(items) {
      items = Array.isArray(items) ? items : [];
      if (!items.length) return '<p>More store and customer history is needed before evidence factors can be displayed.</p>';
      return '<div class="mg-predictive-evidence">' + items.map(function (item) {
        return '<article><div><strong>' + esc(item.label || item.key || 'Evidence') + '</strong><p>' + esc(item.reason || '') + '</p></div><b>' + esc(number(item.value)) + '</b></article>';
      }).join('') + '</div>';
    }

    function rewardValue(reward) {
      reward = reward || {};
      if (reward.value_type === 'percent') return Number(reward.value_percent || 0).toFixed(0) + '% discount';
      if (reward.value_type === 'fixed_amount') return money(reward.value_amount_cents || 0) + ' credit';
      if (reward.value_type === 'free_item') return 'Free item';
      return label(reward.reward_type || 'custom reward');
    }

    function recommendationHtml(item) {
      var projection = item.projections || {};
      var reward = item.reward || {};
      var campaign = item.campaign || {};
      var materialized = item.status === 'materialized' || !!item.materialized_campaign;
      var rewardMode = item.reward_strategy === 'reuse_existing' ? 'Uses current reward' : 'Creates reward draft';
      var createdLinks = '';
      if (materialized) {
        createdLinks = '<div class="mg-predictive-created-links"><a class="mg-btn mg-btn-soft" href="/merchant-reward-templates.php">Open Reward Inventory</a><a class="mg-btn mg-btn-primary" href="/merchant-campaigns.php#campaign-drafts">Open Campaign Drafts</a></div>';
      }
      var actions = materialized ? createdLinks : '' +
        '<button class="mg-btn mg-btn-ghost" type="button" data-predictive-action="dismiss" data-recommendation-id="' + esc(item.id) + '">Dismiss</button>' +
        '<button class="mg-btn mg-btn-primary" type="button" data-predictive-action="materialize" data-recommendation-id="' + esc(item.id) + '">Create reward + campaign drafts</button>';
      if (item.status === 'dismissed') actions = '<span class="mg-predictive-badge">Dismissed</span>';

      return '<article class="mg-predictive-card' + (materialized ? ' is-materialized' : '') + '" data-recommendation-card="' + esc(item.id) + '">' +
        '<header class="mg-predictive-card-head"><div><div class="mg-predictive-badges"><span class="mg-predictive-badge is-blue">' + esc(label(item.scope_type)) + '</span><span class="mg-predictive-badge">' + esc(item.campaign_type_label || label(item.campaign_type)) + '</span><span class="mg-predictive-badge ' + (materialized ? 'is-green' : 'is-warn') + '">' + esc(materialized ? 'Drafts created' : rewardMode) + '</span></div><h3>' + esc(item.title) + '</h3><p>' + esc(item.summary) + '</p></div><div class="mg-predictive-confidence"><strong>' + esc(percent(item.confidence_score)) + '</strong><span>Confidence</span></div></header>' +
        '<div class="mg-predictive-card-body"><div class="mg-predictive-main">' +
          '<section class="mg-predictive-section"><h4>Why this opportunity</h4><p>' + esc(item.rationale || '') + '</p></section>' +
          '<div class="mg-predictive-plan-grid"><article><span>Campaign draft</span><strong>' + esc(campaign.title || item.title) + '</strong><small>' + esc(item.campaign_type_label || label(item.campaign_type)) + ' · existing Campaigns system</small></article><article><span>Reward plan</span><strong>' + esc(reward.title || 'Merchant reward draft') + '</strong><small>' + esc(rewardValue(reward)) + ' · ' + esc(rewardMode) + '</small></article></div>' +
          '<section class="mg-predictive-section"><h4>Probability and economics</h4><div class="mg-predictive-projections"><article><span>Engagement</span><strong>' + esc(percent(projection.engagement_probability)) + '</strong></article><article><span>Estimated reward cost</span><strong>' + esc(money(projection.estimated_reward_cost_cents)) + '</strong></article><article><span>Estimated revenue range</span><strong>' + esc(money(projection.estimated_revenue_low_cents)) + '–' + esc(money(projection.estimated_revenue_high_cents)) + '</strong></article></div></section>' +
          '<section class="mg-predictive-section"><h4>Evidence</h4>' + evidenceHtml(item.evidence) + '</section>' +
        '</div><aside class="mg-predictive-side">' +
          '<section class="mg-predictive-section"><h4>Audience</h4><div class="mg-predictive-segment"><strong>' + esc(item.audience_name) + '</strong><span>' + esc(number(item.audience_count)) + '</span></div></section>' +
          '<section class="mg-predictive-section"><h4>Targeting model</h4><p>Campaign strategy is created at the store or segment level. Customer history controls eligibility and timing. Individual reward creation is disabled in this foundation.</p></section>' +
          '<section class="mg-predictive-section"><h4>Merchant review</h4><p>Creating drafts does not activate the campaign, notify customers, issue Wallet items, or change Inbox/PPPM records.</p></section>' +
        '</aside></div>' +
        '<footer class="mg-predictive-actions"><span class="mg-predictive-policy">Projections are estimates using current merchant behavior and Wallet history. Review reward cost, margin, eligibility, copy, timing, and limits before activation.</span>' + actions + '</footer>' +
      '</article>';
    }

    function render() {
      var data = state.data || {};
      if (!data.schema_ready) {
        renderKpis(data.snapshot || {});
        renderAuthority(data.authority || {});
        listNode.innerHTML = '<div class="mg-predictive-empty"><h3>SQL setup required</h3><p>Import <code>database/predictive_campaign_studio_foundation_v1.sql</code>, then refresh this tab.</p></div>';
        setStatus('Predictive Campaign Studio schema is not installed.', 'error');
        return;
      }
      renderKpis(data.snapshot || {});
      renderAuthority(data.authority || {});
      var recommendations = Array.isArray(data.recommendations) ? data.recommendations : [];
      if (!recommendations.length) {
        listNode.innerHTML = '<div class="mg-predictive-empty"><h3>No recommendations in this view</h3><p>Generate recommendations to evaluate current store, CRM, behavior, Campaign, Reward Inventory, and Wallet trends.</p></div>';
      } else {
        listNode.innerHTML = recommendations.map(recommendationHtml).join('');
      }
      setStatus(recommendations.length + ' recommendation' + (recommendations.length === 1 ? '' : 's') + ' loaded. Current rewards and campaigns remain authoritative.', 'success');
    }

    async function load(force) {
      if (state.loading) return;
      state.loading = true;
      if (force) state.loaded = false;
      refreshButton.disabled = true;
      setStatus('Loading current store trends and recommendation history…');
      try {
        var response = unwrap(await MG.get('/api/merchant/predictive-campaign-studio.php?status=' + encodeURIComponent(state.filter))) || {};
        state.data = response;
        state.loaded = true;
        render();
      } catch (error) {
        listNode.innerHTML = '<div class="mg-predictive-empty"><h3>Predictive Campaign Studio unavailable</h3><p>' + esc(error.message || 'Unable to load recommendations.') + '</p></div>';
        setStatus(error.message || 'Unable to load Predictive Campaign Studio.', 'error');
      } finally {
        state.loading = false;
        refreshButton.disabled = false;
      }
    }

    async function act(action, recommendationId, button) {
      if (state.loading) return;
      state.loading = true;
      var previous = button ? button.textContent : '';
      if (button) {
        button.disabled = true;
        button.textContent = action === 'materialize' ? 'Creating drafts…' : 'Updating…';
      }
      if (action === 'generate') generateButton.disabled = true;
      setStatus(action === 'generate' ? 'Analyzing current merchant and customer trends…' : (action === 'materialize' ? 'Creating canonical reward and campaign drafts…' : 'Dismissing recommendation…'));
      try {
        var response = unwrap(await MG.post('/api/merchant/predictive-campaign-studio.php', {
          csrf_token: csrf(),
          action: action,
          recommendation_id: recommendationId || ''
        })) || {};
        state.data = response.studio || response;
        state.loaded = true;
        render();
        setStatus(action === 'materialize' ? 'Draft reward and campaign created. Review them before activation.' : (action === 'generate' ? 'Recommendations refreshed from current store trends.' : 'Recommendation dismissed.'), 'success');
      } catch (error) {
        setStatus(error.message || 'Unable to complete the recommendation action.', 'error');
      } finally {
        state.loading = false;
        generateButton.disabled = false;
        if (button) {
          button.disabled = false;
          button.textContent = previous;
        }
      }
    }

    generateButton.addEventListener('click', function () { act('generate', '', generateButton); });
    refreshButton.addEventListener('click', function () { load(true); });
    filterNode.addEventListener('change', function () {
      state.filter = filterNode.value || 'open';
      load(true);
    });
    listNode.addEventListener('click', function (event) {
      var button = event.target.closest('[data-predictive-action]');
      if (!button) return;
      var action = button.getAttribute('data-predictive-action') || '';
      var recommendationId = button.getAttribute('data-recommendation-id') || '';
      if (action === 'materialize') {
        var confirmed = window.confirm('Create a draft reward (when needed) and a draft campaign in the current merchant systems? Nothing will launch or be issued automatically.');
        if (!confirmed) return;
      }
      act(action, recommendationId, button);
    });

    if (window.location.hash === '#campaign-recommendations') window.setTimeout(activate, 0);
  });
})(window, document);
