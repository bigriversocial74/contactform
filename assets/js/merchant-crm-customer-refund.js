(function () {
  'use strict';

  if (!window.Microgifter) return;

  var activeContact = null;
  var contacts = [];
  var campaigns = [];
  var confirmed = false;
  var loadedAt = 0;

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function toast(message) { if (Microgifter.toast) Microgifter.toast(message); else alert(message); }
  function busy(button, on, text) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (text || 'Working...') : button.dataset.originalText;
  }
  function set(selector, value, root) { var node = qs(selector, root); if (node) node.textContent = String(value == null ? '' : value); }
  function remaining(value) { return value == null ? 'Unlimited' : String(Math.max(0, Number(value || 0))) + ' remaining'; }

  function ensureModal() {
    if (qs('[data-crm-customer-refund-modal]')) return;
    var wrap = document.createElement('div');
    wrap.className = 'mg-crm-modal';
    wrap.hidden = true;
    wrap.setAttribute('data-crm-customer-refund-modal', '');
    wrap.innerHTML = '<div class="mg-crm-drawer-backdrop" data-crm-customer-refund-close></div><form class="mg-crm-modal-panel" data-crm-customer-refund-form><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Customer Refund</span><h2 data-crm-customer-refund-title>Send make-good voucher</h2><p data-crm-customer-refund-subtitle>Choose an active Customer Refund campaign and issue its assigned reward into the customer wallet / Inbox PPPM flow.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-customer-refund-close>Close</button></header><label class="mg-crm-field"><span>Customer Refund campaign</span><select data-crm-customer-refund-campaign required><option value="">Loading campaigns...</option></select></label><div class="mg-crm-reward-preview" data-crm-customer-refund-preview><strong>Customer Refund voucher</strong><p>Choose a refund campaign with an assigned active reward.</p></div><label class="mg-crm-field"><span>Internal / customer note</span><textarea data-crm-customer-refund-note maxlength="1000" placeholder="Example: Sorry for the issue — here is a make-good voucher from our team."></textarea></label><div class="mg-crm-reward-confirm" data-crm-customer-refund-confirm hidden><strong>Confirm Customer Refund</strong><p data-crm-customer-refund-confirm-text>Review before sending.</p></div><p class="mg-form-status" data-crm-customer-refund-status></p><div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php#campaign-create">Create Customer Refund campaign</a><button class="mg-btn mg-btn-soft" type="button" data-crm-customer-refund-close>Cancel</button><button class="mg-btn" type="submit" data-crm-customer-refund-submit>Send Customer Refund</button></div></form>';
    document.body.appendChild(wrap);
  }

  function campaignById(id) {
    return campaigns.find(function (campaign) { return String(campaign.id) === String(id); }) || null;
  }

  function campaignOption(campaign) {
    var label = (campaign.title || 'Customer Refund') + ' · ' + (campaign.reward_template_title || 'No reward') + ' · ' + remaining(campaign.campaign_remaining) + ' campaign · ' + remaining(campaign.reward_remaining) + ' reward';
    return '<option value="' + esc(campaign.id) + '"' + (campaign.eligible ? '' : ' disabled') + '>' + esc(label + (campaign.eligible ? '' : ' · ' + (campaign.reason || 'Unavailable'))) + '</option>';
  }

  function renderPreview(campaign) {
    var box = qs('[data-crm-customer-refund-preview]');
    if (!box) return;
    if (!campaign) {
      box.innerHTML = '<strong>Customer Refund voucher</strong><p>Choose an active Customer Refund campaign with an assigned active reward.</p>';
      return;
    }
    box.innerHTML = '<strong>' + esc(campaign.title || 'Customer Refund') + '</strong><dl><dt>Reward</dt><dd>' + esc(campaign.reward_template_title || 'No reward assigned') + '</dd><dt>Status</dt><dd>' + esc(campaign.status || '') + '</dd><dt>Campaign inventory</dt><dd>' + esc(remaining(campaign.campaign_remaining)) + '</dd><dt>Reward inventory</dt><dd>' + esc(remaining(campaign.reward_remaining)) + '</dd><dt>Readiness</dt><dd>' + esc(campaign.reason || 'Ready') + '</dd></dl>';
  }

  async function loadContacts(force) {
    var now = Date.now();
    if (!force && contacts.length && now - loadedAt < 2500) return contacts;
    var response = await Microgifter.get('/api/merchant/campaign-contacts.php');
    var data = response.data || response;
    contacts = data.contacts || [];
    loadedAt = now;
    decorateRows();
    return contacts;
  }

  async function loadCampaigns() {
    var response = await Microgifter.get('/api/merchant/crm-reward-campaigns.php?type=customer_refund');
    var data = response.data || response;
    campaigns = data.campaigns || [];
    return campaigns;
  }

  function decorateRows() {
    var byId = {};
    contacts.forEach(function (contact) { byId[String(contact.id)] = contact; });
    qsa('tr[data-contact-id]').forEach(function (row) {
      if (qs('[data-crm-customer-refund]', row)) return;
      var contact = byId[String(row.getAttribute('data-contact-id'))];
      var actions = qs('.mg-crm-row-actions', row);
      if (!contact || !actions) return;
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'mg-crm-icon-btn mg-crm-customer-refund-btn';
      button.setAttribute('data-crm-customer-refund', '');
      button.title = contact.has_account ? 'Send Customer Refund voucher' : 'Customer account required for wallet refund voucher';
      button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v10H4z"/><path d="M7 11h10"/><path d="M9 15h6"/></svg><span>' + (contact.has_account ? 'Refund' : 'Account needed') + '</span>';
      if (!contact.has_account) button.disabled = true;
      actions.insertBefore(button, actions.lastElementChild || null);
    });
  }

  function contactByRow(target) {
    var row = target && target.closest ? target.closest('tr[data-contact-id]') : null;
    var id = row ? row.getAttribute('data-contact-id') : '';
    return contacts.find(function (contact) { return String(contact.id) === String(id); }) || null;
  }

  function drawerContact() {
    var subtitle = qs('[data-crm-drawer-subtitle]');
    var email = subtitle ? String(subtitle.textContent || '').split(' · ')[0].trim().toLowerCase() : '';
    return contacts.find(function (contact) { return String(contact.email || '').toLowerCase() === email; }) || null;
  }

  function resetConfirm() {
    confirmed = false;
    var box = qs('[data-crm-customer-refund-confirm]');
    if (box) box.hidden = true;
    var button = qs('[data-crm-customer-refund-submit]');
    if (button) button.textContent = 'Send Customer Refund';
  }

  function showConfirm(campaign) {
    confirmed = true;
    var box = qs('[data-crm-customer-refund-confirm]');
    if (box) box.hidden = false;
    set('[data-crm-customer-refund-confirm-text]', 'Send ' + (campaign.reward_template_title || 'this reward') + ' from ' + (campaign.title || 'Customer Refund') + ' to ' + (activeContact.name || activeContact.email || 'this customer') + '?');
    var button = qs('[data-crm-customer-refund-submit]');
    if (button) button.textContent = 'Confirm Customer Refund';
    set('[data-crm-customer-refund-status]', 'Review the refund voucher and confirm to send.');
  }

  async function openModal(contact) {
    ensureModal();
    activeContact = contact;
    if (!activeContact) { toast('CRM contact not found.'); return; }
    var modal = qs('[data-crm-customer-refund-modal]');
    modal.hidden = false;
    document.body.classList.add('mg-crm-modal-open');
    set('[data-crm-customer-refund-title]', 'Customer Refund for ' + (activeContact.name || activeContact.email || 'customer'));
    set('[data-crm-customer-refund-subtitle]', (activeContact.email || '') + ' · make-good voucher');
    var note = qs('[data-crm-customer-refund-note]');
    if (note) note.value = '';
    resetConfirm();
    set('[data-crm-customer-refund-status]', 'Loading active Customer Refund campaigns...');
    await loadCampaigns();
    var select = qs('[data-crm-customer-refund-campaign]');
    if (select) select.innerHTML = campaigns.length ? '<option value="">Choose Customer Refund campaign</option>' + campaigns.map(campaignOption).join('') : '<option value="">No Customer Refund campaigns available</option>';
    var submit = qs('[data-crm-customer-refund-submit]');
    if (submit) submit.disabled = !campaigns.some(function (campaign) { return !!campaign.eligible; });
    set('[data-crm-customer-refund-status]', campaigns.length ? 'Choose an eligible Customer Refund campaign.' : 'Create and activate a Customer Refund campaign with an assigned reward first.');
    renderPreview(null);
  }

  function closeModal() {
    var modal = qs('[data-crm-customer-refund-modal]');
    if (modal) modal.hidden = true;
    document.body.classList.remove('mg-crm-modal-open');
    resetConfirm();
  }

  async function submit(event) {
    event.preventDefault();
    if (!activeContact || !activeContact.has_account) { toast('Customer account required.'); return; }
    var select = qs('[data-crm-customer-refund-campaign]');
    var note = qs('[data-crm-customer-refund-note]');
    var button = qs('[data-crm-customer-refund-submit]');
    var campaign = campaignById(select ? select.value : '');
    if (!campaign) { set('[data-crm-customer-refund-status]', 'Choose a Customer Refund campaign first.'); return; }
    if (!campaign.eligible) { set('[data-crm-customer-refund-status]', campaign.reason || 'This Customer Refund campaign is not ready.'); return; }
    if (!confirmed) { showConfirm(campaign); return; }
    busy(button, true, 'Sending...');
    set('[data-crm-customer-refund-status]', 'Sending Customer Refund voucher into wallet / Inbox PPPM...');
    try {
      var response = await Microgifter.post('/api/merchant/crm-campaign-send.php', {
        contact_id: activeContact.id,
        campaign_id: campaign.id,
        required_campaign_type: 'customer_refund',
        note: note ? note.value : '',
        idempotency_key: 'crm-customer-refund-ui:' + activeContact.id + ':' + campaign.id + ':' + Date.now()
      });
      var data = response.data || response;
      set('[data-crm-customer-refund-status]', 'Customer Refund sent: ' + (data.wallet_item_id || 'issued'));
      toast('Customer Refund voucher sent.');
      document.dispatchEvent(new CustomEvent('mg:crm:reward-sent', { detail: { contact: activeContact, contact_id: activeContact.id, campaign_id: data.campaign_id, wallet_item_id: data.wallet_item_id, campaign_type: 'customer_refund' } }));
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      setTimeout(function () { closeModal(); loadContacts(true).catch(function () {}); }, 650);
    } catch (error) {
      set('[data-crm-customer-refund-status]', error.message || 'Unable to send Customer Refund voucher.');
      resetConfirm();
    } finally {
      busy(button, false);
    }
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest && event.target.closest('[data-crm-customer-refund]');
    if (trigger) {
      event.preventDefault();
      openModal(contactByRow(trigger));
    }
    var drawerAction = event.target.closest && event.target.closest('[data-crm-action="customer_refund"]');
    if (drawerAction) {
      event.preventDefault();
      loadContacts(false).then(function () { openModal(drawerContact()); });
    }
    if (event.target.closest && event.target.closest('[data-crm-customer-refund-close]')) closeModal();
  });

  document.addEventListener('submit', function (event) {
    if (event.target && event.target.matches && event.target.matches('[data-crm-customer-refund-form]')) submit(event);
  });

  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches && event.target.matches('[data-crm-customer-refund-campaign]')) {
      resetConfirm();
      renderPreview(campaignById(event.target.value));
    }
  });

  document.addEventListener('input', function (event) {
    if (event.target && event.target.matches && event.target.matches('[data-crm-customer-refund-note]')) resetConfirm();
  });

  setTimeout(function () { ensureModal(); loadContacts(true).catch(function () {}); }, 300);
  var table = qs('[data-merchant-crm-table]');
  if (table && window.MutationObserver) new MutationObserver(function () { loadContacts(false).catch(function () {}); }).observe(table, { childList: true, subtree: true });
})();
