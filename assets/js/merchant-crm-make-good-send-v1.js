document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  var state = {
    activeContact: null,
    campaigns: [],
    selectedCampaignId: '',
    contactsCache: [],
    lastContactLoad: 0,
    history: [],
    receipt: null,
    messageBody: ''
  };

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }
  function notify(message) { if (Microgifter.toast) Microgifter.toast(message); else alert(message); }
  function fmt(value) {
    if (!value) return '—';
    try { return new Date(String(value).replace(' ', 'T')).toLocaleString(); } catch (error) { return String(value); }
  }
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
  function campaignById(id) {
    return state.campaigns.find(function (campaign) { return String(campaign.id) === String(id); }) || null;
  }
  function selectedCampaign() { return campaignById(state.selectedCampaignId); }
  function duplicateFor(campaign) {
    if (!campaign) return null;
    return state.history.find(function (item) {
      return String(item.campaign_id || '') === String(campaign.id || '')
        || (campaign.reward_template_id && String(item.reward_template_id || '') === String(campaign.reward_template_id));
    }) || null;
  }
  function selectableCampaigns() {
    return state.campaigns.filter(function (campaign) { return campaign.eligible && !duplicateFor(campaign); });
  }

  function ensureActionCenter() {
    var shell = qs('[data-crm-action-center]');
    if (shell) return shell;
    shell = document.createElement('div');
    shell.className = 'mg-crm-action-center mg-crm-make-good-center';
    shell.dataset.crmActionCenter = '';
    shell.hidden = true;
    shell.innerHTML =
      '<button class="mg-crm-action-backdrop" type="button" data-crm-action-close aria-label="Close make-good sender"></button>' +
      '<aside class="mg-crm-action-panel" role="dialog" aria-modal="true" aria-labelledby="crmActionTitle">' +
        '<header class="mg-crm-action-head"><div><span class="mg-eyebrow">CRM Make Good</span><h2 id="crmActionTitle" data-crm-action-title>Send customer refund / make good</h2><p data-crm-action-subtitle>Select a customer.</p></div><button class="mg-crm-action-close" type="button" data-crm-action-close aria-label="Close">×</button></header>' +
        '<main class="mg-crm-action-body" data-crm-action-body></main>' +
        '<footer class="mg-crm-action-footer"><div class="mg-crm-action-footer-summary" data-crm-action-footer-summary></div><div class="mg-crm-action-footer-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php?type=customer_refund">Manage make-good campaigns</a><button class="mg-btn mg-btn-primary" type="button" data-crm-action-send>Send make good</button></div><p class="mg-crm-action-status" data-crm-action-status aria-live="polite"></p></footer>' +
      '</aside>';
    document.body.appendChild(shell);
    return shell;
  }
  function openShell() {
    ensureActionCenter().hidden = false;
    document.body.classList.add('mg-crm-action-center-open');
  }
  function closeShell() {
    ensureActionCenter().hidden = true;
    document.body.classList.remove('mg-crm-action-center-open');
    state.activeContact = null;
    state.selectedCampaignId = '';
    state.receipt = null;
    state.messageBody = '';
    setStatus('', '');
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
    var response = await Microgifter.get('/api/merchant/crm-reward-campaigns.php?type=customer_refund');
    var data = response.data || response;
    state.campaigns = (data.campaigns || []).filter(function (campaign) {
      return String(campaign.campaign_type || '') === 'customer_refund';
    });
    if (!state.selectedCampaignId) {
      var first = selectableCampaigns()[0] || state.campaigns.find(function (campaign) { return campaign.eligible; }) || null;
      state.selectedCampaignId = first ? String(first.id || '') : '';
    }
    return state.campaigns;
  }
  async function loadHistory() {
    if (!state.activeContact) return [];
    try {
      var response = await Microgifter.get('/api/merchant/crm-campaign-send-history.php?contact_id=' + encodeURIComponent(state.activeContact.id));
      var data = response.data || response;
      state.history = data.history || [];
    } catch (error) {
      state.history = [];
    }
    return state.history;
  }

  function campaignCard(campaign) {
    var selected = String(campaign.id) === String(state.selectedCampaignId);
    var duplicate = duplicateFor(campaign);
    var blocked = !campaign.eligible || !!duplicate;
    var reason = duplicate
      ? 'This customer already received this make-good campaign or reward.'
      : (campaign.reason || 'Ready to send.');
    return '<button type="button" class="mg-crm-campaign-card' + (selected ? ' is-selected' : '') + (blocked ? ' is-blocked' : '') + '" data-crm-campaign-select="' + esc(campaign.id) + '"' + (blocked ? ' disabled' : '') + '>' +
      '<span class="mg-crm-campaign-choice" aria-hidden="true"></span>' +
      '<div><strong>' + esc(campaign.title || 'Customer Refund / Make Good') + '</strong>' +
      '<p>' + esc(campaign.description || reason) + '</p>' +
      '<div class="mg-crm-campaign-meta"><span>' + esc(campaign.reward_template_title || 'No reward assigned') + '</span><span>' + esc(remainingLabel(campaign.campaign_remaining)) + '</span><span class="' + (blocked ? 'mg-crm-action-pill is-bad' : 'mg-crm-action-pill is-ok') + '">' + esc(duplicate ? 'Already sent' : (campaign.eligible ? 'Ready' : 'Blocked')) + '</span></div>' +
      (blocked ? '<small class="mg-crm-campaign-blocked-reason">' + esc(reason) + '</small>' : '') +
      '</div></button>';
  }
  function receiptHtml() {
    if (!state.receipt) return '';
    return '<section class="mg-crm-action-receipt"><strong>' + esc(state.receipt.title || 'Make good sent') + '</strong><p>' + esc(state.receipt.body || '') + '</p><div class="mg-crm-campaign-meta">' +
      (state.receipt.wallet_item_id ? '<span>Wallet ' + esc(state.receipt.wallet_item_id) + '</span>' : '') +
      (state.receipt.invite_id ? '<span>Invite ' + esc(state.receipt.invite_id) + '</span>' : '') +
      (state.receipt.message ? '<span>' + esc(state.receipt.message) + '</span>' : '') +
      '<span>' + esc(fmt(new Date().toISOString())) + '</span></div></section>';
  }
  function emptyCampaigns() {
    return '<section class="mg-crm-action-empty"><strong>No available Customer Refund / Make Good campaigns</strong><p>Create or activate a customer refund campaign with an active reward. Only that campaign type can be sent from this CRM action.</p><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="/merchant-campaigns.php?type=customer_refund#campaign-create">Create make-good campaign</a><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php?type=customer_refund">Manage campaigns</a></div></section>';
  }
  function render() {
    var body = qs('[data-crm-action-body]');
    if (!body) return;
    var contact = state.activeContact || {};
    var campaignList = state.campaigns.length ? state.campaigns.map(campaignCard).join('') : emptyCampaigns();
    body.innerHTML = receiptHtml() +
      '<section class="mg-crm-make-good-intro"><span>Customer Refund / Make Good</span><h3>Choose the campaign to send</h3><p>Only active Customer Refund / Make Good campaigns are available here. The assigned campaign reward is delivered to the customer wallet or through the account-invite fallback.</p></section>' +
      '<section class="mg-crm-make-good-list" aria-label="Customer Refund and Make Good campaigns">' + campaignList + '</section>' +
      '<section class="mg-crm-action-note"><label>Message to customer <span>Optional</span><textarea data-crm-action-message maxlength="1000" placeholder="We are sorry about your experience. We added this make-good reward to your Microgifter account.">' + esc(state.messageBody) + '</textarea></label><small>This message is sent through Microgifter Messages. Contacts without an account use the existing email fallback.</small></section>';
    footerSummary();

    var textarea = qs('[data-crm-action-message]', body);
    if (textarea) textarea.value = state.messageBody;
    var selected = selectedCampaign();
    if (selected) {
      var selectedNode = qs('[data-crm-campaign-select="' + CSS.escape(String(selected.id)) + '"]', body);
      if (selectedNode) selectedNode.scrollIntoView({ block: 'nearest' });
    }
    qs('[data-crm-action-subtitle]').textContent = (contact.name || contact.email || 'Customer') + ' · ' + (contact.email || 'No email');
  }

  function footerSummary() {
    var node = qs('[data-crm-action-footer-summary]');
    var send = qs('[data-crm-action-send]');
    var campaign = selectedCampaign();
    var contact = state.activeContact || {};
    var duplicate = duplicateFor(campaign);
    var ready = !!(campaign && campaign.eligible && !duplicate);
    if (node) {
      node.innerHTML =
        '<span class="mg-crm-action-pill ' + (contact.has_account ? 'is-ok' : 'is-warn') + '">' + esc(contact.has_account ? 'Wallet delivery' : 'Account invite') + '</span>' +
        '<span class="mg-crm-action-pill ' + (ready ? 'is-ok' : 'is-warn') + '">' + esc(ready ? 'Make-good ready' : 'Choose campaign') + '</span>' +
        '<span>' + esc(campaign ? campaign.title : 'No campaign selected') + '</span>';
    }
    if (send) {
      send.disabled = !ready;
      send.textContent = contact.has_account ? 'Send make good' : 'Send make-good invite';
    }
  }

  async function openActionCenter(contact) {
    state.activeContact = contact;
    state.selectedCampaignId = '';
    state.receipt = null;
    state.messageBody = '';
    openShell();
    if (!contact) { notify('CRM contact not found.'); return; }
    setStatus('Loading Customer Refund / Make Good campaigns...', '');
    await Promise.all([loadHistory(), loadCampaigns()]);
    if (!state.campaigns.length) setStatus('No active Customer Refund / Make Good campaigns found.', 'error');
    else if (!selectableCampaigns().length) setStatus('No unsent make-good campaigns are currently available for this customer.', 'error');
    else setStatus('Choose a campaign, add an optional message, and send.', '');
    render();
  }

  function decorateRows() {
    qsa('tr[data-contact-id]').forEach(function (row) {
      var contact = state.contactsCache.find(function (item) { return String(item.id) === String(row.getAttribute('data-contact-id')); });
      var button = qs('[data-crm-gift],[data-crm-reward]', row);
      if (!button || !contact) return;
      button.textContent = contact.has_account ? 'Make good' : 'Make-good invite';
      button.title = 'Send a Customer Refund / Make Good campaign';
      button.setAttribute('aria-label', button.title);
    });
  }

  async function sendInvite(campaign, contact) {
    var response = await Microgifter.post('/api/merchant/crm-send-reward-invite.php', {
      contact_id: contact.id,
      reward_template_id: campaign.reward_template_id,
      campaign_id: campaign.id,
      required_campaign_type: 'customer_refund',
      note: 'Customer Refund / Make Good campaign: ' + campaign.title,
      idempotency_key: 'crm-make-good-invite:' + contact.id + ':' + campaign.id + ':' + Date.now()
    });
    return response.data || response;
  }
  async function sendMessage(contact, campaign) {
    var message = state.messageBody.trim();
    if (!message) return null;
    try {
      var response = await Microgifter.post('/api/merchant/crm-message.php', {
        contact_id: contact.id,
        message: message,
        idempotency_key: 'crm-make-good-message:' + contact.id + ':' + campaign.id + ':' + Date.now()
      });
      return response.data || response;
    } catch (error) {
      return { error: error.message || 'Message failed' };
    }
  }
  async function submitSend() {
    var campaign = selectedCampaign();
    var contact = state.activeContact;
    var button = qs('[data-crm-action-send]');
    if (!contact) { setStatus('CRM contact not found.', 'error'); return; }
    if (!campaign || !campaign.eligible || duplicateFor(campaign)) {
      setStatus('Choose an available Customer Refund / Make Good campaign.', 'error');
      return;
    }

    setBusy(button, true, contact.has_account ? 'Sending...' : 'Inviting...');
    setStatus(contact.has_account ? 'Issuing make-good reward to the customer wallet...' : 'Creating make-good account invite...', '');
    try {
      var data;
      if (contact.has_account) {
        var response = await Microgifter.post('/api/merchant/crm-campaign-send.php', {
          contact_id: contact.id,
          campaign_id: campaign.id,
          required_campaign_type: 'customer_refund',
          note: state.messageBody.trim() ? 'Customer message sent with make-good reward.' : 'Customer Refund / Make Good issued from Merchant CRM.',
          idempotency_key: 'crm-make-good:' + contact.id + ':' + campaign.id + ':' + Date.now()
        });
        data = response.data || response;
      } else {
        data = await sendInvite(campaign, contact);
      }

      var messageResult = await sendMessage(contact, campaign);
      state.receipt = {
        title: contact.has_account ? 'Make good sent' : 'Make-good invite sent',
        body: contact.has_account ? 'The campaign reward was issued to the customer wallet and PPPM lifecycle.' : 'The customer received the account-invite path for this make-good reward.',
        wallet_item_id: data.wallet_item_id || '',
        invite_id: data.invite_id || '',
        message: messageResult ? (messageResult.error ? 'Message failed: ' + messageResult.error : 'Customer message sent') : 'No message added'
      };
      notify(state.receipt.title + '.');
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      document.dispatchEvent(new CustomEvent('mg:crm-messages:refresh'));
      await loadHistory();
      setStatus(state.receipt.title + '.', 'success');
      render();
      var refresh = qs('[data-crm-refresh]');
      if (refresh) refresh.click();
    } catch (error) {
      setStatus(error.message || 'Unable to send the Customer Refund / Make Good reward.', 'error');
    } finally {
      setBusy(button, false);
      footerSummary();
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
    if (event.target.closest('[data-crm-action-close]')) { event.preventDefault(); closeShell(); return; }
    var campaign = event.target.closest('[data-crm-campaign-select]');
    if (campaign) {
      event.preventDefault();
      state.selectedCampaignId = campaign.dataset.crmCampaignSelect || '';
      state.receipt = null;
      setStatus('Campaign selected. Add an optional message, then send.', '');
      render();
      return;
    }
    if (event.target.closest('[data-crm-action-send]')) { event.preventDefault(); submitSend(); }
  }, true);

  document.addEventListener('input', function (event) {
    if (event.target && event.target.matches('[data-crm-action-message]')) {
      state.messageBody = event.target.value;
      footerSummary();
    }
  });
  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    state.contactsCache = (event.detail && event.detail.contacts) || state.contactsCache;
    state.lastContactLoad = Date.now();
    decorateRows();
  });

  loadContacts(true).catch(function () {});
});
