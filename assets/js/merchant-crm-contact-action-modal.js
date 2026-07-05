document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  var state = {
    activeContact: null,
    campaigns: [],
    selectedCampaignId: '',
    filter: 'recommended',
    tab: 'campaigns',
    confirmed: false,
    contactsCache: [],
    lastContactLoad: 0,
    history: [],
    receipt: null,
    reason: 'manual_promo',
    note: '',
    sendMessage: false,
    messageBody: '',
    allowDuplicate: false
  };

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }
  function notify(message) { if (Microgifter.toast) Microgifter.toast(message); else alert(message); }
  function fmt(value) { if (!value) return '—'; try { return new Date(String(value).replace(' ', 'T')).toLocaleString(); } catch (e) { return String(value); } }
  function remainingLabel(value) { return value == null ? 'Unlimited' : String(Math.max(0, Number(value || 0))) + ' remaining'; }
  function setStatus(message, type) {
    var node = qs('[data-crm-action-status]');
    if (!node) return;
    node.textContent = message || '';
    node.dataset.type = type || '';
  }
  function setBusy(button, on, text) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (text || 'Working...') : button.dataset.originalText;
  }
  function isRecommended(campaign) { return ['customer_refund', 'referral_reward'].indexOf(String(campaign.campaign_type || '')) !== -1; }
  function campaignById(id) { return state.campaigns.find(function (campaign) { return String(campaign.id) === String(id); }) || null; }
  function eligibleCampaigns() { return state.campaigns.filter(function (campaign) { return !!campaign.eligible; }); }
  function selectedCampaign() { return campaignById(state.selectedCampaignId); }
  function hasReward(campaign) { return !!(campaign && campaign.reward_template_id && String(campaign.reward_template_status || '') === 'active'); }
  function duplicateForSelected() {
    var campaign = selectedCampaign();
    if (!campaign) return null;
    return state.history.find(function (item) {
      return String(item.campaign_id || '') === String(campaign.id || '') ||
        (campaign.reward_template_id && String(item.reward_template_id || '') === String(campaign.reward_template_id));
    }) || null;
  }
  function resetFormState() {
    state.confirmed = false;
    state.receipt = null;
    state.reason = 'manual_promo';
    state.note = '';
    state.sendMessage = false;
    state.messageBody = '';
    state.allowDuplicate = false;
  }

  function ensureActionCenter() {
    var shell = qs('[data-crm-action-center]');
    if (shell) return shell;
    shell = document.createElement('div');
    shell.className = 'mg-crm-action-center';
    shell.dataset.crmActionCenter = '';
    shell.hidden = true;
    shell.innerHTML = '' +
      '<button class="mg-crm-action-backdrop" type="button" data-crm-action-close aria-label="Close CRM action center"></button>' +
      '<aside class="mg-crm-action-panel" role="dialog" aria-modal="true" aria-labelledby="crmActionTitle">' +
        '<header class="mg-crm-action-head"><div><span class="mg-eyebrow">CRM Action Center</span><h2 id="crmActionTitle" data-crm-action-title>Send campaign reward</h2><p data-crm-action-subtitle>Select a customer.</p></div><button class="mg-crm-action-close" type="button" data-crm-action-close aria-label="Close">×</button></header>' +
        '<nav class="mg-crm-action-tabs" aria-label="CRM action sections"><button type="button" data-crm-action-tab="campaigns">Campaigns</button><button type="button" data-crm-action-tab="eligibility">Eligibility</button><button type="button" data-crm-action-tab="history">History</button><button type="button" data-crm-action-tab="notes">Notes</button></nav>' +
        '<main class="mg-crm-action-body"><section class="mg-crm-action-section" data-crm-action-section="campaigns"></section><section class="mg-crm-action-section" data-crm-action-section="eligibility" hidden></section><section class="mg-crm-action-section" data-crm-action-section="history" hidden></section><section class="mg-crm-action-section" data-crm-action-section="notes" hidden></section></main>' +
        '<footer class="mg-crm-action-footer"><div class="mg-crm-action-footer-summary" data-crm-action-footer-summary></div><div class="mg-crm-action-footer-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Manage campaigns</a><button class="mg-btn mg-btn-primary" type="button" data-crm-action-send>Send to customer</button></div><p class="mg-crm-action-status" data-crm-action-status aria-live="polite"></p></footer>' +
      '</aside>';
    document.body.appendChild(shell);
    return shell;
  }

  function openShell() {
    var shell = ensureActionCenter();
    shell.hidden = false;
    document.body.classList.add('mg-crm-action-center-open');
  }
  function closeShell() {
    var shell = ensureActionCenter();
    shell.hidden = true;
    document.body.classList.remove('mg-crm-action-center-open');
    state.activeContact = null;
    state.selectedCampaignId = '';
    state.confirmed = false;
    setStatus('', '');
  }
  function setTab(tab) {
    state.tab = tab || 'campaigns';
    qsa('[data-crm-action-tab]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.crmActionTab === state.tab);
    });
    qsa('[data-crm-action-section]').forEach(function (section) {
      section.hidden = section.dataset.crmActionSection !== state.tab;
    });
  }

  async function loadContacts(force) {
    var now = Date.now();
    if (!force && state.contactsCache.length && now - state.lastContactLoad < 2500) return state.contactsCache;
    var response = await Microgifter.get('/api/merchant/campaign-contacts.php');
    var data = response.data || response;
    state.contactsCache = data.contacts || [];
    state.lastContactLoad = now;
    decorateRows();
    return state.contactsCache;
  }
  async function contactByRow(row) {
    var id = row && row.getAttribute('data-contact-id');
    if (!id) return null;
    var contacts = await loadContacts(false);
    return contacts.find(function (contact) { return String(contact.id) === String(id); }) || null;
  }
  async function contactByDrawer() {
    var subtitle = qs('[data-crm-drawer-subtitle]');
    var email = subtitle ? String(subtitle.textContent || '').split(' · ')[0].trim().toLowerCase() : '';
    if (!email) return null;
    var contacts = await loadContacts(false);
    return contacts.find(function (contact) { return String(contact.email || '').toLowerCase() === email; }) || null;
  }
  async function loadCampaigns() {
    var response = await Microgifter.get('/api/merchant/crm-reward-campaigns.php');
    var data = response.data || response;
    state.campaigns = data.campaigns || [];
    if (!state.selectedCampaignId) {
      var firstRecommended = state.campaigns.find(function (campaign) { return campaign.eligible && isRecommended(campaign); });
      var firstEligible = state.campaigns.find(function (campaign) { return campaign.eligible; });
      var firstWithReward = state.campaigns.find(hasReward);
      state.selectedCampaignId = (firstRecommended || firstEligible || firstWithReward || {}).id || '';
    }
    return state.campaigns;
  }
  async function loadHistory() {
    if (!state.activeContact) return [];
    try {
      var response = await Microgifter.get('/api/merchant/crm-campaign-send-history.php?contact_id=' + encodeURIComponent(state.activeContact.id));
      var data = response.data || response;
      state.history = data.history || [];
      return state.history;
    } catch (error) {
      state.history = [];
      return [];
    }
  }

  function filterCampaigns() {
    return state.campaigns.filter(function (campaign) {
      if (state.filter === 'recommended') return campaign.eligible && isRecommended(campaign);
      if (state.filter === 'all') return true;
      if (state.filter === 'eligible') return !!campaign.eligible;
      if (state.filter === 'blocked') return !campaign.eligible;
      if (state.filter === 'other') return !isRecommended(campaign);
      return String(campaign.campaign_type || '') === state.filter;
    });
  }
  function renderCampaignCard(campaign) {
    var selected = String(campaign.id) === String(state.selectedCampaignId);
    var duplicate = state.history.find(function (item) { return String(item.campaign_id || '') === String(campaign.id || '') || String(item.reward_template_id || '') === String(campaign.reward_template_id || ''); });
    return '<button type="button" class="mg-crm-campaign-card' + (selected ? ' is-selected' : '') + (campaign.eligible ? '' : ' is-blocked') + '" data-crm-campaign-select="' + esc(campaign.id) + '">' +
      '<strong>' + esc(campaign.title || 'Campaign') + '</strong>' +
      '<p>' + esc(campaign.description || campaign.reason || 'Reward-backed campaign available for CRM action.') + '</p>' +
      '<div class="mg-crm-campaign-meta"><span>' + esc(campaign.campaign_type_label || campaign.campaign_type || 'Campaign') + '</span><span>' + esc(campaign.reward_template_title || 'No reward assigned') + '</span><span>' + esc(remainingLabel(campaign.campaign_remaining)) + '</span><span>' + esc(remainingLabel(campaign.reward_remaining)) + '</span><span class="' + (campaign.eligible ? 'mg-crm-action-pill is-ok' : 'mg-crm-action-pill is-bad') + '">' + esc(campaign.eligible ? 'Eligible' : 'Blocked') + '</span>' + (duplicate ? '<span class="mg-crm-action-pill is-warn">Already sent</span>' : '') + '</div>' +
    '</button>';
  }
  function renderNoCampaigns() {
    return '<div class="mg-crm-action-empty"><strong>No campaigns in this view</strong><p>Create or activate a reward-backed campaign, then return here to send it to this customer.</p><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="/merchant-campaigns.php#campaign-create">Create campaign</a><a class="mg-btn mg-btn-soft" href="/merchant-products.php">Assign reward</a></div></div>';
  }
  function renderCampaigns() {
    var section = qs('[data-crm-action-section="campaigns"]');
    if (!section) return;
    var list = filterCampaigns();
    section.innerHTML = '<div class="mg-crm-action-filter"><button type="button" data-crm-action-filter="recommended">Recommended</button><button type="button" data-crm-action-filter="customer_refund">Customer Refund</button><button type="button" data-crm-action-filter="referral_reward">Referral Reward</button><button type="button" data-crm-action-filter="eligible">Eligible</button><button type="button" data-crm-action-filter="all">All Active</button><button type="button" data-crm-action-filter="blocked">Blocked</button></div>' +
      (list.length ? list.map(renderCampaignCard).join('') : renderNoCampaigns());
    qsa('[data-crm-action-filter]', section).forEach(function (button) { button.classList.toggle('is-active', button.dataset.crmActionFilter === state.filter); });
  }

  function eligibilityItem(ok, title, text) {
    return '<article class="mg-crm-action-check' + (ok ? ' is-ok' : '') + '"><b></b><div><strong>' + esc(title) + '</strong><span>' + esc(text) + '</span></div></article>';
  }
  function repairActions(campaign) {
    var actions = [];
    if (!campaign) return '';
    if (!hasReward(campaign)) actions.push('<a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Assign reward</a>');
    if (String(campaign.status || '') !== 'active') actions.push('<a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Activate campaign</a>');
    if (String(campaign.reason || '').toLowerCase().indexOf('date') !== -1 || String(campaign.reason || '').toLowerCase().indexOf('ended') !== -1) actions.push('<a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Extend dates</a>');
    if (String(campaign.reason || '').toLowerCase().indexOf('inventory') !== -1) actions.push('<a class="mg-btn mg-btn-soft" href="/merchant-products.php">Add inventory</a>');
    if (!actions.length && !campaign.eligible) actions.push('<a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Repair campaign</a>');
    return actions.length ? '<div class="mg-heading-actions">' + actions.join('') + '</div>' : '';
  }
  function renderEligibility() {
    var section = qs('[data-crm-action-section="eligibility"]');
    if (!section) return;
    var contact = state.activeContact || {};
    var campaign = selectedCampaign();
    var hasAccount = !!contact.has_account;
    var duplicate = duplicateForSelected();
    var content = '<div class="mg-crm-action-grid">' +
      eligibilityItem(hasAccount, 'Customer wallet account', hasAccount ? 'This customer can receive a wallet item.' : 'Customer account required before wallet delivery. Use the wallet invite fallback below.') +
      eligibilityItem(!!campaign, 'Campaign selected', campaign ? campaign.title : 'Choose a campaign in the Campaigns tab.') +
      eligibilityItem(!!(campaign && campaign.eligible), 'Campaign ready', campaign ? (campaign.reason || 'Campaign is ready.') : 'No campaign selected.') +
      eligibilityItem(!!hasReward(campaign), 'Reward assigned', campaign ? (campaign.reward_template_title || 'Assign a reward template.') : 'Assign a reward template to the campaign.') +
      eligibilityItem(!duplicate || state.allowDuplicate, 'Duplicate protection', duplicate ? (state.allowDuplicate ? 'Override enabled for this duplicate send.' : 'This customer already received this campaign/reward. Enable duplicate override in Notes to send again.') : 'No duplicate send found for this selected campaign/reward.') +
      '</div>';
    if (!hasAccount && hasReward(campaign)) content += '<div class="mg-crm-action-empty"><strong>Account invite fallback</strong><p>This contact does not have a wallet account. Send a wallet invite for the selected reward instead of a direct wallet item.</p></div>';
    if (campaign && !campaign.eligible) content += '<div class="mg-crm-action-empty"><strong>Blocked reason</strong><p>' + esc(campaign.reason || 'This campaign is not currently eligible.') + '</p>' + repairActions(campaign) + '</div>';
    section.innerHTML = content;
  }

  function renderReceipt() {
    if (!state.receipt) return '';
    return '<div class="mg-crm-action-receipt"><strong>' + esc(state.receipt.title || 'Action complete') + '</strong><p>' + esc(state.receipt.body || '') + '</p><div class="mg-crm-campaign-meta">' +
      (state.receipt.wallet_item_id ? '<span>Wallet ' + esc(state.receipt.wallet_item_id) + '</span>' : '') +
      (state.receipt.invite_id ? '<span>Invite ' + esc(state.receipt.invite_id) + '</span>' : '') +
      (state.receipt.message ? '<span>' + esc(state.receipt.message) + '</span>' : '') +
      '<span>' + esc(fmt(new Date().toISOString())) + '</span></div></div>';
  }
  function renderHistory() {
    var section = qs('[data-crm-action-section="history"]');
    if (!section) return;
    var receipt = renderReceipt();
    if (!state.history.length) {
      section.innerHTML = receipt + '<div class="mg-crm-action-empty"><strong>No previous campaign sends</strong><p>This customer has not received a CRM campaign reward yet.</p></div>';
      return;
    }
    section.innerHTML = receipt + '<div class="mg-crm-action-history">' + state.history.map(function (item) {
      return '<article><strong>' + esc(item.campaign_title || item.campaign_type_label || 'Campaign reward sent') + '</strong><p>Wallet: ' + esc(item.wallet_item_id || 'issued') + ' · Status: ' + esc(item.wallet_status || 'issued') + ' · ' + esc(fmt(item.created_at)) + '</p>' + (item.note ? '<p>Note: ' + esc(item.note) + '</p>' : '') + '</article>';
    }).join('') + '</div>';
  }
  function renderNotes() {
    var section = qs('[data-crm-action-section="notes"]');
    if (!section) return;
    section.innerHTML = '<div class="mg-crm-action-note"><label>Send reason<select data-crm-action-reason><option value="customer_refund">Customer refund</option><option value="loyalty_reward">Loyalty reward</option><option value="referral_thank_you">Referral thank-you</option><option value="make_good">Make-good voucher</option><option value="manual_promo">Manual promotion</option><option value="other">Other</option></select></label><label>Internal note<textarea data-crm-action-note maxlength="1000" placeholder="Add context for the CRM timeline and send history."></textarea></label><label class="mg-crm-action-toggle"><input type="checkbox" data-crm-action-send-message> Send customer message with this reward</label><label data-crm-action-message-wrap>Customer message<textarea data-crm-action-message maxlength="1000" placeholder="Thanks for supporting us. We added a reward to your Microgifter wallet."></textarea></label><label class="mg-crm-action-toggle"><input type="checkbox" data-crm-action-allow-duplicate> Allow duplicate send for this campaign/reward</label></div>';
    qs('[data-crm-action-reason]', section).value = state.reason;
    qs('[data-crm-action-note]', section).value = state.note;
    qs('[data-crm-action-send-message]', section).checked = !!state.sendMessage;
    qs('[data-crm-action-message]', section).value = state.messageBody;
    qs('[data-crm-action-allow-duplicate]', section).checked = !!state.allowDuplicate;
  }

  function footerSummary() {
    var node = qs('[data-crm-action-footer-summary]');
    var send = qs('[data-crm-action-send]');
    if (!node) return;
    var campaign = selectedCampaign();
    var contact = state.activeContact || {};
    var duplicate = duplicateForSelected();
    var directReady = !!(contact.has_account && campaign && campaign.eligible && (!duplicate || state.allowDuplicate));
    var inviteReady = !!(!contact.has_account && hasReward(campaign));
    node.innerHTML = '<span class="mg-crm-action-pill ' + (contact.has_account ? 'is-ok' : 'is-warn') + '">' + esc(contact.has_account ? 'Wallet ready' : 'Invite fallback') + '</span>' +
      '<span class="mg-crm-action-pill ' + (campaign && campaign.eligible ? 'is-ok' : 'is-warn') + '">' + esc(campaign ? (campaign.eligible ? 'Campaign eligible' : 'Campaign blocked') : 'No campaign') + '</span>' +
      (duplicate ? '<span class="mg-crm-action-pill ' + (state.allowDuplicate ? 'is-warn' : 'is-bad') + '">' + esc(state.allowDuplicate ? 'Duplicate override' : 'Duplicate blocked') + '</span>' : '') +
      '<span>' + esc(campaign ? campaign.title : 'Choose campaign') + '</span>';
    if (send) {
      send.disabled = !(directReady || inviteReady);
      send.textContent = !contact.has_account ? (state.confirmed ? 'Confirm invite' : 'Send wallet invite') : (state.confirmed ? 'Confirm send' : 'Send to customer');
    }
  }
  function renderAll() {
    setTab(state.tab);
    renderCampaigns();
    renderEligibility();
    renderHistory();
    renderNotes();
    footerSummary();
  }

  async function openActionCenter(contact) {
    state.activeContact = contact;
    state.selectedCampaignId = '';
    state.filter = 'recommended';
    state.tab = 'campaigns';
    resetFormState();
    openShell();
    if (!contact) { notify('CRM contact not found.'); return; }
    qs('[data-crm-action-title]').textContent = 'Send campaign reward';
    qs('[data-crm-action-subtitle]').textContent = (contact.name || contact.email || 'Customer') + ' · ' + (contact.email || 'No email');
    setStatus('Loading active campaigns...', '');
    await Promise.all([loadCampaigns(), loadHistory()]);
    if (!state.campaigns.length) setStatus('No active reward-backed campaigns found.', 'error');
    else if (!eligibleCampaigns().length && contact.has_account) setStatus('Campaigns found, but none are currently eligible.', 'error');
    else if (!contact.has_account) setStatus('Customer account is missing. Use the wallet invite fallback.', '');
    else setStatus('Choose a campaign, review eligibility, add notes, then send.', '');
    renderAll();
  }

  function decorateRows() {
    qsa('tr[data-contact-id]').forEach(function (row) {
      var contact = state.contactsCache.find(function (item) { return String(item.id) === String(row.getAttribute('data-contact-id')); });
      var button = qs('[data-crm-gift],[data-crm-reward]', row);
      if (!button || !contact) return;
      button.textContent = contact.has_account ? 'Send reward' : 'Send invite';
      button.title = contact.has_account ? 'Open CRM Action Center' : 'Send a wallet invite fallback.';
    });
  }

  async function sendInvite(campaign, contact) {
    var response = await Microgifter.post('/api/merchant/crm-send-reward-invite.php', {
      contact_id: contact.id,
      reward_template_id: campaign.reward_template_id,
      note: 'Reason: ' + String(state.reason || 'manual_promo').replace(/_/g, ' ') + (state.note ? '\n' + state.note : ''),
      idempotency_key: 'crm-action-center-invite:' + contact.id + ':' + campaign.reward_template_id + ':' + Date.now()
    });
    var data = response.data || response;
    state.receipt = { title: 'Wallet invite sent', body: 'The customer was invited to create a wallet account and claim the selected reward.', invite_id: data.invite_id || '', message: data.email_delivery && data.email_delivery.queued ? 'Email queued' : 'Invite created' };
    notify('Wallet invite sent.');
    return data;
  }
  async function sendOptionalMessage(contact, campaign) {
    if (!state.sendMessage || !state.messageBody.trim()) return null;
    try {
      var response = await Microgifter.post('/api/merchant/crm-message.php', {
        contact_id: contact.id,
        message: state.messageBody.trim(),
        idempotency_key: 'crm-action-center-message:' + contact.id + ':' + campaign.id + ':' + Date.now()
      });
      return response.data || response;
    } catch (error) {
      return { error: error.message || 'Message failed' };
    }
  }
  async function submitSend() {
    var campaign = selectedCampaign();
    var contact = state.activeContact;
    var send = qs('[data-crm-action-send]');
    var duplicate = duplicateForSelected();
    if (!contact) { setStatus('CRM contact not found.', 'error'); return; }
    if (!campaign) { setStatus('Choose an active campaign first.', 'error'); setTab('campaigns'); return; }
    if (!contact.has_account && !hasReward(campaign)) { setStatus('Select a campaign with an active reward before sending an invite.', 'error'); setTab('eligibility'); return; }
    if (contact.has_account && !campaign.eligible) { setStatus(campaign.reason || 'This campaign is blocked.', 'error'); setTab('eligibility'); return; }
    if (contact.has_account && duplicate && !state.allowDuplicate) { setStatus('Duplicate blocked. Enable duplicate override in Notes to send again.', 'error'); setTab('notes'); return; }
    if (!state.confirmed) { state.confirmed = true; setStatus(contact.has_account ? 'Review complete. Click Confirm send to issue this wallet reward.' : 'Review complete. Click Confirm invite to send wallet invite.', ''); footerSummary(); return; }
    setBusy(send, true, contact.has_account ? 'Sending...' : 'Inviting...');
    setStatus(contact.has_account ? 'Issuing wallet reward and pushing into PPPM flow...' : 'Creating wallet invite...', '');
    try {
      if (!contact.has_account) {
        await sendInvite(campaign, contact);
      } else {
        var response = await Microgifter.post('/api/merchant/crm-campaign-send.php', {
          contact_id: contact.id,
          campaign_id: campaign.id,
          note: 'Reason: ' + String(state.reason || 'manual_promo').replace(/_/g, ' ') + (state.note ? '\n' + state.note : ''),
          idempotency_key: 'crm-action-center:' + contact.id + ':' + campaign.id + ':' + Date.now()
        });
        var data = response.data || response;
        var messageResult = await sendOptionalMessage(contact, campaign);
        state.receipt = { title: 'Reward sent', body: 'Wallet reward issued and pushed into the PPPM flow.', wallet_item_id: data.wallet_item_id || '', message: messageResult ? (messageResult.error ? 'Message failed: ' + messageResult.error : 'Customer message sent') : '' };
        notify((data.campaign_type_label || 'Campaign') + ' reward sent.');
      }
      state.confirmed = false;
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      await loadHistory();
      setStatus(state.receipt.title + '.', 'success');
      setTab('history');
      renderAll();
      var refresh = qs('[data-crm-refresh]');
      if (refresh) refresh.click();
    } catch (error) {
      state.confirmed = false;
      setStatus(error.message || 'Unable to complete CRM action.', 'error');
      footerSummary();
    } finally {
      setBusy(send, false);
    }
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-crm-gift],[data-crm-reward]');
    if (button) {
      var row = button.closest('tr[data-contact-id]');
      if (row) {
        event.preventDefault();
        event.stopImmediatePropagation();
        contactByRow(row).then(openActionCenter).catch(function () { notify('CRM contact not found.'); });
        return;
      }
    }
    var action = event.target.closest('[data-crm-action="reward"]');
    if (action) {
      event.preventDefault();
      event.stopImmediatePropagation();
      contactByDrawer().then(openActionCenter).catch(function () { notify('CRM contact not found.'); });
      return;
    }
    var close = event.target.closest('[data-crm-action-close]');
    if (close) { event.preventDefault(); closeShell(); return; }
    var tab = event.target.closest('[data-crm-action-tab]');
    if (tab) { event.preventDefault(); setTab(tab.dataset.crmActionTab); renderAll(); return; }
    var filter = event.target.closest('[data-crm-action-filter]');
    if (filter) { event.preventDefault(); state.filter = filter.dataset.crmActionFilter; state.confirmed = false; renderAll(); return; }
    var campaign = event.target.closest('[data-crm-campaign-select]');
    if (campaign) { event.preventDefault(); state.selectedCampaignId = campaign.dataset.crmCampaignSelect || ''; state.confirmed = false; renderAll(); return; }
    var send = event.target.closest('[data-crm-action-send]');
    if (send) { event.preventDefault(); submitSend(); }
  }, true);

  document.addEventListener('input', function (event) {
    if (!event.target) return;
    if (event.target.matches('[data-crm-action-note]')) state.note = event.target.value;
    if (event.target.matches('[data-crm-action-message]')) state.messageBody = event.target.value;
    if (event.target.matches('[data-crm-action-note], [data-crm-action-message]')) { state.confirmed = false; footerSummary(); }
  });
  document.addEventListener('change', function (event) {
    if (!event.target) return;
    if (event.target.matches('[data-crm-action-reason]')) state.reason = event.target.value;
    if (event.target.matches('[data-crm-action-send-message]')) state.sendMessage = event.target.checked;
    if (event.target.matches('[data-crm-action-allow-duplicate]')) state.allowDuplicate = event.target.checked;
    if (event.target.matches('[data-crm-action-reason], [data-crm-action-send-message], [data-crm-action-allow-duplicate]')) { state.confirmed = false; renderAll(); }
  });

  loadContacts(true).catch(function () {});
});
