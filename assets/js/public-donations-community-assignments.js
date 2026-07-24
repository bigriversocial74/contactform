document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  if (!root || !window.Microgifter) return;

  var nav = root.querySelector('.mg-campaign-tabs');
  var panelHost = root.querySelector('.mg-campaign-tab-panels');
  if (!nav || !panelHost) return;

  function node(tag, className, text) {
    var item = document.createElement(tag);
    if (className) item.className = className;
    if (text != null) item.textContent = String(text);
    return item;
  }

  function safeArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function count(value) {
    return Number(value || 0).toLocaleString();
  }

  function statusText(value) {
    var raw = String(value || '');
    return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : 'Unknown';
  }

  function initials(name) {
    return String(name || 'Community member').trim().split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0).toUpperCase();
    }).join('') || 'CM';
  }

  function setMessage(mount, message, tone) {
    if (!mount) return;
    mount.textContent = message || '';
    mount.classList.toggle('is-error', tone === 'error');
    mount.classList.toggle('is-success', tone === 'success');
  }

  function apiData(response) {
    return response && response.data ? response.data : (response || {});
  }

  function buildWorkspace() {
    var tab = node('a', 'mg-community-assignment-tab', 'Community');
    tab.href = '#campaign-community';
    tab.setAttribute('data-community-assignment-tab', '');
    var forms = nav.querySelector('[data-campaign-tab="forms"]');
    if (forms) nav.insertBefore(tab, forms);
    else nav.appendChild(tab);

    var panel = node('section', 'mg-community-assignment-panel');
    panel.id = 'campaign-community';
    panel.hidden = true;
    panel.setAttribute('data-community-assignment-panel', '');

    var head = node('header', 'mg-community-assignment-head');
    var headCopy = node('div');
    headCopy.appendChild(node('span', 'mg-eyebrow', 'Public Donations'));
    headCopy.appendChild(node('h2', '', 'Community campaign assignments'));
    headCopy.appendChild(node('p', '', 'Find active Community accounts, manage campaign relationships, and notify members without issuing rewards.'));
    head.appendChild(headCopy);
    var publicLink = node('a', 'mg-btn mg-btn-soft', 'Open public campaign');
    publicLink.href = '#';
    publicLink.hidden = true;
    publicLink.target = '_blank';
    publicLink.rel = 'noopener';
    publicLink.setAttribute('data-community-public-link', '');
    head.appendChild(publicLink);
    panel.appendChild(head);

    var controls = node('div', 'mg-community-assignment-controls');
    var campaignLabel = node('label');
    campaignLabel.appendChild(node('span', '', 'Public Donations campaign'));
    var campaignSelect = node('select');
    campaignSelect.setAttribute('data-community-campaign-select', '');
    campaignLabel.appendChild(campaignSelect);
    controls.appendChild(campaignLabel);

    var searchLabel = node('label');
    searchLabel.appendChild(node('span', '', 'Search Community accounts'));
    var searchWrap = node('div', 'mg-community-search-control');
    var searchInput = node('input');
    searchInput.type = 'search';
    searchInput.maxLength = 120;
    searchInput.placeholder = 'Name, username, or general location';
    searchInput.setAttribute('data-community-search-input', '');
    var searchButton = node('button', 'mg-btn mg-btn-primary', 'Search');
    searchButton.type = 'button';
    searchButton.setAttribute('data-community-search-button', '');
    searchWrap.appendChild(searchInput);
    searchWrap.appendChild(searchButton);
    searchLabel.appendChild(searchWrap);
    controls.appendChild(searchLabel);

    var filterLabel = node('label');
    filterLabel.appendChild(node('span', '', 'Assigned status'));
    var filter = node('select');
    filter.setAttribute('data-community-status-filter', '');
    [['all', 'All assignments'], ['active', 'Active'], ['paused', 'Paused'], ['removed', 'Removed']].forEach(function (option) {
      var item = node('option', '', option[1]);
      item.value = option[0];
      filter.appendChild(item);
    });
    filterLabel.appendChild(filter);
    controls.appendChild(filterLabel);
    panel.appendChild(controls);

    var status = node('div', 'mg-community-assignment-status', 'Choose a Public Donations campaign.');
    status.setAttribute('data-community-assignment-status', '');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    panel.appendChild(status);

    var summary = node('div', 'mg-community-assignment-summary');
    summary.setAttribute('data-community-assignment-summary', '');
    panel.appendChild(summary);

    var grid = node('div', 'mg-community-assignment-grid');
    var searchSection = node('section', 'mg-community-assignment-card');
    var searchHead = node('div', 'mg-community-section-head');
    var searchCopy = node('div');
    searchCopy.appendChild(node('span', 'mg-eyebrow', 'Discovery'));
    searchCopy.appendChild(node('h3', '', 'Community accounts'));
    searchCopy.appendChild(node('p', '', 'Only active accounts currently holding the Community role appear here.'));
    searchHead.appendChild(searchCopy);
    searchSection.appendChild(searchHead);
    var searchResults = node('div', 'mg-community-account-list');
    searchResults.setAttribute('data-community-search-results', '');
    searchSection.appendChild(searchResults);
    grid.appendChild(searchSection);

    var assignedSection = node('section', 'mg-community-assignment-card');
    var assignedHead = node('div', 'mg-community-section-head');
    var assignedCopy = node('div');
    assignedCopy.appendChild(node('span', 'mg-eyebrow', 'Management'));
    assignedCopy.appendChild(node('h3', '', 'Assigned accounts'));
    assignedCopy.appendChild(node('p', '', 'Pause or remove relationships without changing previously allocated rewards.'));
    assignedHead.appendChild(assignedCopy);
    var refresh = node('button', 'mg-btn mg-btn-soft', 'Refresh');
    refresh.type = 'button';
    refresh.setAttribute('data-community-refresh', '');
    assignedHead.appendChild(refresh);
    assignedSection.appendChild(assignedHead);
    var assignedResults = node('div', 'mg-community-account-list');
    assignedResults.setAttribute('data-community-assigned-results', '');
    assignedSection.appendChild(assignedResults);
    grid.appendChild(assignedSection);
    panel.appendChild(grid);

    var privacy = node('aside', 'mg-community-privacy-note');
    privacy.appendChild(node('strong', '', 'Privacy-safe discovery'));
    privacy.appendChild(node('span', '', 'Search returns public identity, Community badge, non-administrative roles, profile link, avatar, and general location only. Private contact and exact-location fields are excluded.'));
    panel.appendChild(privacy);
    panelHost.appendChild(panel);

    return {
      tab: tab,
      panel: panel,
      campaignSelect: campaignSelect,
      searchInput: searchInput,
      searchButton: searchButton,
      filter: filter,
      refresh: refresh,
      status: status,
      summary: summary,
      searchResults: searchResults,
      assignedResults: assignedResults,
      publicLink: publicLink
    };
  }

  function activate(parts) {
    root.querySelectorAll('.mg-campaign-tab-panels > [data-campaign-tab-panel]').forEach(function (panel) {
      panel.hidden = true;
      panel.classList.remove('is-active');
    });
    var analytics = root.querySelector('[data-campaign-analytics-shell]');
    if (analytics) analytics.hidden = true;
    nav.querySelectorAll('a').forEach(function (link) {
      link.classList.toggle('is-active', link === parts.tab);
      if (link === parts.tab) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    parts.panel.hidden = false;
    parts.panel.classList.add('is-active');
    if (history.replaceState) history.replaceState(null, '', '#campaign-community');
  }

  function leave(parts, link) {
    if (link === parts.tab) return;
    parts.panel.hidden = true;
    parts.panel.classList.remove('is-active');
    parts.tab.classList.remove('is-active');
    parts.tab.removeAttribute('aria-current');
  }

  function summaryCard(label, value) {
    var card = node('article');
    card.appendChild(node('span', '', label));
    card.appendChild(node('strong', '', count(value)));
    return card;
  }

  function renderSummary(parts, summary) {
    summary = summary || {};
    parts.summary.replaceChildren(
      summaryCard('Total', summary.total),
      summaryCard('Active', summary.active),
      summaryCard('Paused', summary.paused),
      summaryCard('Removed', summary.removed)
    );
  }

  function identityBlock(item) {
    var identity = node('div', 'mg-community-account-identity');
    var avatar = node('div', 'mg-community-avatar');
    if (item.avatar_url) {
      var image = node('img');
      image.src = item.avatar_url;
      image.alt = '';
      image.loading = 'lazy';
      avatar.appendChild(image);
    } else {
      avatar.appendChild(node('span', '', initials(item.display_name)));
    }
    identity.appendChild(avatar);

    var copy = node('div', 'mg-community-account-copy');
    var titleRow = node('div', 'mg-community-account-title');
    titleRow.appendChild(node('strong', '', item.display_name || 'Community member'));
    titleRow.appendChild(node('span', 'mg-community-badge', 'Community'));
    copy.appendChild(titleRow);

    var meta = node('div', 'mg-community-account-meta');
    if (item.username) meta.appendChild(node('span', '', '@' + item.username));
    if (item.general_location) meta.appendChild(node('span', '', item.general_location));
    safeArray(item.other_roles).forEach(function (role) {
      meta.appendChild(node('span', 'mg-community-role', role));
    });
    copy.appendChild(meta);

    if (item.public_profile_url) {
      var profile = node('a', 'mg-community-profile-link', 'View public profile');
      profile.href = item.public_profile_url;
      profile.target = '_blank';
      profile.rel = 'noopener';
      copy.appendChild(profile);
    }
    identity.appendChild(copy);
    return identity;
  }

  function empty(mount, message) {
    var box = node('div', 'mg-community-empty');
    box.appendChild(node('p', '', message));
    mount.replaceChildren(box);
  }

  function actionButton(label, action, item, tone) {
    var button = node('button', 'mg-btn ' + (tone || 'mg-btn-soft'), label);
    button.type = 'button';
    button.setAttribute('data-community-action', action);
    if (item.community_account_id) button.setAttribute('data-community-account-id', item.community_account_id);
    if (item.assignment && item.assignment.id) button.setAttribute('data-community-assignment-id', item.assignment.id);
    return button;
  }

  function resultCard(item, mode) {
    var card = node('article', 'mg-community-account-card');
    card.appendChild(identityBlock(item));
    var actions = node('div', 'mg-community-account-actions');
    var assignment = item.assignment || null;
    var state = assignment ? String(assignment.status || '') : '';
    if (assignment) actions.appendChild(node('span', 'mg-community-status is-' + state, statusText(state)));

    if (mode === 'search') {
      if (!assignment) actions.appendChild(actionButton('Add to campaign', 'add', item, 'mg-btn-primary'));
      else if (state !== 'active') actions.appendChild(actionButton('Reactivate', 'reactivate', item, 'mg-btn-primary'));
      else actions.appendChild(node('span', 'mg-community-already-assigned', 'Already assigned'));
    } else if (state === 'active') {
      actions.appendChild(actionButton('Pause', 'pause', item));
      actions.appendChild(actionButton('Remove', 'remove', item, 'mg-btn-ghost'));
    } else {
      actions.appendChild(actionButton('Reactivate', 'reactivate', item, 'mg-btn-primary'));
      if (state === 'paused') actions.appendChild(actionButton('Remove', 'remove', item, 'mg-btn-ghost'));
    }
    card.appendChild(actions);
    return card;
  }

  function renderItems(mount, items, mode) {
    items = safeArray(items);
    if (!items.length) {
      empty(mount, mode === 'search' ? 'Search for an active Community account.' : 'No assignments match this status.');
      return;
    }
    var fragment = document.createDocumentFragment();
    items.forEach(function (item) { fragment.appendChild(resultCard(item, mode)); });
    mount.replaceChildren(fragment);
  }

  function selectedCampaign(parts) {
    return String(parts.campaignSelect.value || '');
  }

  function endpoint(parts, includeSearch) {
    var params = new URLSearchParams();
    var campaignId = selectedCampaign(parts);
    if (campaignId) params.set('campaign_id', campaignId);
    params.set('status', parts.filter.value || 'all');
    if (includeSearch) {
      params.set('include_search', '1');
      params.set('q', String(parts.searchInput.value || '').trim());
    }
    return '/api/merchant/public-donations-community.php?' + params.toString();
  }

  function updatePublicLink(parts, campaigns) {
    var current = safeArray(campaigns).find(function (campaign) {
      return String(campaign.id) === selectedCampaign(parts);
    });
    if (current && current.public_url) {
      parts.publicLink.href = current.public_url;
      parts.publicLink.hidden = false;
    } else {
      parts.publicLink.hidden = true;
      parts.publicLink.removeAttribute('href');
    }
  }

  function populateCampaigns(parts, campaigns) {
    var current = selectedCampaign(parts);
    parts.campaignSelect.replaceChildren();
    var placeholder = node('option', '', campaigns.length ? 'Choose a campaign' : 'No Public Donations campaigns');
    placeholder.value = '';
    parts.campaignSelect.appendChild(placeholder);
    campaigns.forEach(function (campaign) {
      var option = node('option', '', campaign.title + ' · ' + statusText(campaign.status));
      option.value = campaign.id;
      parts.campaignSelect.appendChild(option);
    });
    if (current && campaigns.some(function (campaign) { return campaign.id === current; })) parts.campaignSelect.value = current;
    parts.searchButton.disabled = !selectedCampaign(parts);
    updatePublicLink(parts, campaigns);
  }

  async function load(parts, state, includeSearch) {
    if (!selectedCampaign(parts)) {
      renderSummary(parts, {});
      empty(parts.searchResults, 'Choose a campaign before searching.');
      empty(parts.assignedResults, 'Choose a campaign to manage assignments.');
      setMessage(parts.status, state.campaigns.length ? 'Choose a Public Donations campaign.' : 'Create a Public Donations campaign before assigning Community accounts.');
      return;
    }
    setMessage(parts.status, includeSearch ? 'Searching Community accounts…' : 'Loading assignments…');
    try {
      var response = await Microgifter.get(endpoint(parts, includeSearch));
      var data = apiData(response);
      state.schemaReady = data.schema_ready !== false;
      renderSummary(parts, data.summary);
      renderItems(parts.assignedResults, data.assigned, 'assigned');
      if (includeSearch) renderItems(parts.searchResults, data.search, 'search');
      else empty(parts.searchResults, 'Search by name, username, or general location.');
      setMessage(parts.status, state.schemaReady ? (response.message || 'Community assignments loaded.') : (response.message || 'Community assignment schema is unavailable.'), state.schemaReady ? 'success' : 'error');
      parts.searchButton.disabled = !state.schemaReady || !selectedCampaign(parts);
    } catch (error) {
      setMessage(parts.status, error.message || 'Unable to load Community assignments.', 'error');
    }
  }

  async function mutate(parts, state, button) {
    var action = button.getAttribute('data-community-action') || '';
    var payload = {
      action: action,
      campaign_id: selectedCampaign(parts),
      community_account_id: button.getAttribute('data-community-account-id') || '',
      assignment_id: button.getAttribute('data-community-assignment-id') || ''
    };
    if (!payload.campaign_id) return;
    button.disabled = true;
    setMessage(parts.status, statusText(action) + ' assignment…');
    try {
      var response = await Microgifter.post('/api/merchant/public-donations-community.php', payload);
      var data = apiData(response);
      renderSummary(parts, data.summary);
      renderItems(parts.assignedResults, data.assigned, 'assigned');
      setMessage(parts.status, response.message || 'Community assignment updated.', 'success');
      await load(parts, state, true);
    } catch (error) {
      setMessage(parts.status, error.message || 'Unable to update Community assignment.', 'error');
    } finally {
      button.disabled = false;
    }
  }

  async function boot() {
    var campaignResponse;
    try {
      campaignResponse = await Microgifter.get('/api/merchant/campaigns.php');
    } catch (error) {
      return;
    }
    var campaignData = apiData(campaignResponse);
    var feature = campaignData.public_donations_feature || {};
    if (!feature.enabled) return;

    var parts = buildWorkspace();
    var state = { campaigns: [], schemaReady: false };
    parts.tab.addEventListener('click', function (event) {
      event.preventDefault();
      activate(parts);
      load(parts, state, false);
    });
    nav.addEventListener('click', function (event) {
      var link = event.target.closest('a');
      if (link) leave(parts, link);
    }, true);
    parts.campaignSelect.addEventListener('change', function () {
      parts.searchButton.disabled = !selectedCampaign(parts) || !state.schemaReady;
      updatePublicLink(parts, state.campaigns);
      load(parts, state, false);
    });
    parts.searchButton.addEventListener('click', function () { load(parts, state, true); });
    parts.searchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        load(parts, state, true);
      }
    });
    parts.filter.addEventListener('change', function () { load(parts, state, false); });
    parts.refresh.addEventListener('click', function () { load(parts, state, false); });
    parts.panel.addEventListener('click', function (event) {
      var button = event.target.closest('[data-community-action]');
      if (button) mutate(parts, state, button);
    });

    try {
      var response = await Microgifter.get('/api/merchant/public-donations-community.php');
      var data = apiData(response);
      state.campaigns = safeArray(data.campaigns);
      state.schemaReady = data.schema_ready !== false;
      populateCampaigns(parts, state.campaigns);
      renderSummary(parts, data.summary);
      empty(parts.searchResults, state.campaigns.length ? 'Choose a campaign before searching.' : 'Create a Public Donations campaign first.');
      empty(parts.assignedResults, state.campaigns.length ? 'Choose a campaign to manage assignments.' : 'No Public Donations campaigns are available.');
      setMessage(parts.status, response.message || 'Community assignment workspace ready.', state.schemaReady ? 'success' : 'error');
      parts.searchButton.disabled = true;
      if (window.location.hash === '#campaign-community') {
        activate(parts);
      }
    } catch (error) {
      setMessage(parts.status, error.message || 'Unable to initialize Community assignments.', 'error');
    }
  }

  boot();
});
