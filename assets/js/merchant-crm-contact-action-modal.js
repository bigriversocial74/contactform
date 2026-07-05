document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  var activeContact = null;
  var campaigns = [];
  var confirmed = false;
  var contactsCache = [];
  var lastContactLoad = 0;

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }
  function setText(selector, value) { var node = qs(selector); if (node) node.textContent = String(value == null ? '' : value); }
  function notify(message) { if (Microgifter.toast) Microgifter.toast(message); else alert(message); }
  function remainingLabel(value) { return value == null ? 'Unlimited' : String(Math.max(0, Number(value || 0))) + ' remaining'; }
  function setBusy(button, on, text) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (text || 'Working...') : button.dataset.originalText;
  }

  function ensureModalUi() {
    var form = qs('[data-crm-reward-form]');
    if (!form) return;
    var template = qs('[data-crm-reward-template]');
    if (template) {
      var label = template.closest('label');
      var span = label && label.querySelector('span');
      if (span) span.textContent = 'Active campaign';
    }
    if (template && !qs('[data-crm-reward-preview]')) {
      var preview = document.createElement('div');
      preview.className = 'mg-crm-reward-preview';
      preview.dataset.crmRewardPreview = '';
      preview.innerHTML = '<strong>Campaign selection</strong><p>Choose an active Customer Refund or Referral Reward campaign with an assigned item and available inventory.</p>';
      template.closest('label').insertAdjacentElement('afterend', preview);
    }
    if (!qs('[data-crm-reward-confirm]')) {
      var confirm = document.createElement('div');
      confirm.className = 'mg-crm-reward-confirm';
      confirm.dataset.crmRewardConfirm = '';
      confirm.hidden = true;
      confirm.innerHTML = '<strong>Confirm</strong><p data-crm-reward-confirm-text>Review before processing.</p>';
      var status = qs('[data-crm-reward-status]');
      if (status) status.insertAdjacentElement('beforebegin', confirm);
    }
    var submit = qs('[data-crm-reward-submit]');
    if (submit) {
      submit.dataset.originalText = 'Send to customer';
      submit.textContent = 'Send to customer';
    }
  }

  async function loadContacts(force) {
    var now = Date.now();
    if (!force && contactsCache.length && now - lastContactLoad < 2500) return contactsCache;
    var response = await Microgifter.get('/api/merchant/campaign-contacts.php');
    var data = response.data || response;
    contactsCache = data.contacts || [];
    lastContactLoad = now;
    return contactsCache;
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
    campaigns = data.campaigns || [];
    return campaigns;
  }

  function campaignById(id) {
    return campaigns.find(function (campaign) { return String(campaign.id) === String(id); }) || null;
  }

  function renderPreview(campaign) {
    var preview = qs('[data-crm-reward-preview]');
    if (!preview) return;
    if (!campaign) {
      preview.innerHTML = '<strong>Campaign selection</strong><p>Choose an active Customer Refund or Referral Reward campaign with an assigned item and available inventory.</p>';
      return;
    }
    preview.innerHTML = '<strong>' + esc(campaign.title || 'Campaign') + '</strong><dl><dt>Type</dt><dd>' + esc(campaign.campaign_type_label || campaign.campaign_type || '') + '</dd><dt>Item</dt><dd>' + esc(campaign.reward_template_title || 'No assigned item') + '</dd><dt>Campaign inventory</dt><dd>' + esc(remainingLabel(campaign.campaign_remaining)) + '</dd><dt>Item inventory</dt><dd>' + esc(remainingLabel(campaign.reward_remaining)) + '</dd><dt>Status</dt><dd>' + esc(campaign.reason || 'Ready') + '</dd></dl>';
  }

  function renderCampaigns() {
    ensureModalUi();
    var select = qs('[data-crm-reward-template]');
    if (!select) return;
    select.innerHTML = campaigns.length ? '<option value="">Choose active campaign</option>' + campaigns.map(function (campaign) {
      var label = (campaign.title || campaign.campaign_type_label || 'Campaign') + ' · ' + (campaign.campaign_type_label || campaign.campaign_type || 'Campaign') + ' · ' + (campaign.reward_template_title || 'No assigned item') + (campaign.eligible ? '' : ' · ' + (campaign.reason || 'Unavailable'));
      return '<option value="' + esc(campaign.id) + '"' + (campaign.eligible ? '' : ' disabled') + '>' + esc(label) + '</option>';
    }).join('') : '<option value="">No active campaigns</option>';
    select.disabled = !campaigns.length;
    var submit = qs('[data-crm-reward-submit]');
    if (submit) submit.disabled = !campaigns.some(function (campaign) { return !!campaign.eligible; });
    setText('[data-crm-reward-status]', campaigns.length ? 'Choose an eligible active campaign.' : 'No eligible campaigns are available. Create or activate a Customer Refund or Referral Reward campaign and assign an item.');
    renderPreview(null);
  }

  function resetConfirm() {
    confirmed = false;
    var confirm = qs('[data-crm-reward-confirm]');
    if (confirm) confirm.hidden = true;
    var submit = qs('[data-crm-reward-submit]');
    if (submit) submit.textContent = 'Send to customer';
  }

  async function openModal(contact) {
    ensureModalUi();
    activeContact = contact;
    if (!activeContact) { notify('CRM contact not found.'); return; }
    var modal = qs('[data-crm-reward-modal]');
    if (!modal) { notify('Campaign action modal is not available on this page.'); return; }
    modal.hidden = false;
    document.body.classList.add('mg-crm-modal-open');
    setText('[data-crm-reward-title]', 'Send campaign item to ' + (activeContact.name || activeContact.email || 'contact'));
    setText('[data-crm-reward-subtitle]', (activeContact.email || '') + ' · choose an active campaign');
    var note = qs('[data-crm-reward-note]');
    if (note) note.value = '';
    resetConfirm();
    if (!activeContact.has_account) {
      var select = qs('[data-crm-reward-template]');
      if (select) { select.innerHTML = '<option value="">Customer account required</option>'; select.disabled = true; }
      var submit = qs('[data-crm-reward-submit]');
      if (submit) submit.disabled = true;
      setText('[data-crm-reward-status]', 'Customer account required before this can be placed into wallet.php.');
      renderPreview(null);
      return;
    }
    setText('[data-crm-reward-status]', 'Loading active campaigns...');
    await loadCampaigns();
    renderCampaigns();
  }

  function closeModal() {
    var modal = qs('[data-crm-reward-modal]');
    if (modal) modal.hidden = true;
    document.body.classList.remove('mg-crm-modal-open');
    resetConfirm();
  }

  async function submitModal(event) {
    event.preventDefault();
    event.stopImmediatePropagation();
    if (!activeContact || !activeContact.has_account) { notify('Customer account required.'); return; }
    var select = qs('[data-crm-reward-template]');
    var note = qs('[data-crm-reward-note]');
    var submit = qs('[data-crm-reward-submit]');
    var campaign = campaignById(select ? select.value : '');
    if (!campaign) { setText('[data-crm-reward-status]', 'Choose an active campaign first.'); return; }
    if (!campaign.eligible) { setText('[data-crm-reward-status]', campaign.reason || 'This campaign is not available.'); return; }
    if (!confirmed) {
      confirmed = true;
      var confirm = qs('[data-crm-reward-confirm]');
      if (confirm) confirm.hidden = false;
      setText('[data-crm-reward-confirm-text]', 'Send ' + (campaign.reward_template_title || 'this item') + ' from ' + (campaign.title || 'this campaign') + ' to ' + (activeContact.name || activeContact.email || 'this customer') + '?');
      if (submit) submit.textContent = 'Confirm send';
      setText('[data-crm-reward-status]', 'Review and confirm to continue.');
      return;
    }
    setBusy(submit, true, 'Sending...');
    setText('[data-crm-reward-status]', 'Processing campaign action...');
    try {
      var response = await Microgifter.post('/api/merchant/crm-campaign-send.php', {
        contact_id: activeContact.id,
        campaign_id: campaign.id,
        note: note ? note.value : '',
        idempotency_key: 'crm-campaign-ui:' + activeContact.id + ':' + campaign.id + ':' + Date.now()
      });
      var data = response.data || response;
      setText('[data-crm-reward-status]', 'Campaign item sent: ' + (data.wallet_item_id || 'issued'));
      notify((data.campaign_type_label || 'Campaign') + ' item sent.');
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      setTimeout(function () {
        closeModal();
        var refresh = qs('[data-crm-refresh]');
        if (refresh) refresh.click();
        loadContacts(true).catch(function () {});
      }, 600);
    } catch (error) {
      setText('[data-crm-reward-status]', error.message || 'Unable to process campaign action.');
      resetConfirm();
    } finally {
      setBusy(submit, false);
    }
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-crm-gift],[data-crm-reward]');
    if (button) {
      var row = button.closest('tr[data-contact-id]');
      if (row) {
        event.preventDefault();
        event.stopImmediatePropagation();
        contactByRow(row).then(openModal).catch(function () { notify('CRM contact not found.'); });
        return;
      }
    }
    var action = event.target.closest('[data-crm-action="reward"]');
    if (action) {
      event.preventDefault();
      event.stopImmediatePropagation();
      contactByDrawer().then(openModal).catch(function () { notify('CRM contact not found.'); });
      return;
    }
    if (event.target.closest('[data-crm-reward-close]')) closeModal();
  }, true);

  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches('[data-crm-reward-template]')) {
      resetConfirm();
      renderPreview(campaignById(event.target.value));
    }
  });

  document.addEventListener('input', function (event) {
    if (event.target && event.target.matches('[data-crm-reward-note]')) resetConfirm();
  });

  document.addEventListener('submit', function (event) {
    if (event.target && event.target.matches('[data-crm-reward-form]')) submitModal(event);
  }, true);
});
