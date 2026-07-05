document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter || !document.querySelector('[data-merchant-crm-app]')) return;

  var state = {
    campaigns: [],
    contacts: [],
    selectedCampaign: '',
    selected: {},
    segment: 'all',
    activeContact: null,
    bulkMode: 'message',
    entryActionHandled: false
  };

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }
  function set(selector, value) {
    var element = qs(selector);
    if (element) element.textContent = String(value == null ? '—' : value);
  }
  function badge(text, good) {
    return '<span class="mg-crm-badge ' + (good ? 'is-good' : '') + '">' + esc(text) + '</span>';
  }
  function toast(message) { Microgifter.toast ? Microgifter.toast(message) : alert(message); }
  function busy(button, on, text) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (text || 'Working…') : button.dataset.originalText;
  }
  function profileUrl(contact) {
    return '/merchant-customer.php?campaign_contact_id=' + encodeURIComponent(contact.id || '');
  }
  function label(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (match) { return match.toUpperCase(); });
  }
  function initials(contact) {
    var name = String(contact.name || contact.email || 'C').trim();
    var parts = name.split(/\s+/).filter(Boolean);
    if (parts.length > 1) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 1).toUpperCase();
  }
  function scoreClass(score) {
    score = Number(score || 0);
    if (score >= 75) return 'is-high';
    if (score >= 50) return 'is-medium';
    if (score >= 30) return 'is-warm';
    return 'is-low';
  }
  function contactScore(contact) {
    var stats = contact.crm_stats || {};
    return Number(contact.crm_score || stats.score || 0);
  }
  function metric(value, text) {
    return '<span><strong>' + esc(value || 0) + '</strong>' + esc(text) + '</span>';
  }
  function contactMatchesSegment(contact) {
    if (state.segment === 'accounts') return !!contact.has_account;
    if (state.segment === 'no_accounts') return !contact.has_account;
    if (state.segment === 'verified') return !!contact.email_verified;
    if (state.segment === 'reward_issued') return Number(contact.issued_count || 0) > 0 || Number(contact.wallet_count || 0) > 0;
    if (state.segment === 'reward_claimed') return Number(contact.claimed_count || 0) > 0 || Number(contact.redeemed_count || 0) > 0;
    if (state.segment === 'invite_pending') return Number(contact.invite_pending_count || 0) > 0;
    if (state.segment === 'no_recent_activity') return !!contact.no_recent_activity;
    return true;
  }
  function visibleContacts() { return state.contacts.filter(contactMatchesSegment); }
  function selectedContacts() {
    return state.contacts.filter(function (contact) { return !!state.selected[String(contact.id)]; });
  }
  function rewardState(contact) {
    return Number(contact.redeemed_count || 0) > 0 ? 'redeemed' : (Number(contact.claimed_count || 0) > 0 ? 'claimed' : (Number(contact.issued_count || 0) > 0 ? 'issued' : 'none'));
  }
  function updateBulkState() {
    var selected = selectedContacts();
    var visible = visibleContacts();
    var box = qs('[data-crm-select-visible]');
    if (box) {
      var all = visible.length && visible.every(function (contact) { return !!state.selected[String(contact.id)]; });
      var some = visible.some(function (contact) { return !!state.selected[String(contact.id)]; });
      box.checked = !!all;
      box.indeterminate = some && !all;
    }
    set('[data-crm-selected-count]', selected.length + ' selected');
    qsa('[data-crm-bulk-action]').forEach(function (button) { button.disabled = !selected.length; });
  }
  function contactRow(contact) {
    var id = esc(contact.id);
    var reward = rewardState(contact);
    var checked = state.selected[String(contact.id)] ? ' checked' : '';
    var accountText = contact.has_account ? (contact.account_resolved_by_email ? 'Account/email' : 'Account') : 'No account';
    var stats = contact.crm_stats || {};
    var score = contactScore(contact);
    var result = contact.result_status || stats.result_status || 'no_action_yet';
    var next = contact.next_best_action || stats.next_best_action || 'Review contact';
    var scoreLabel = contact.crm_score_label || stats.score_label || 'score';
    var emailStatus = contact.emails_delivered_count > 0 ? 'delivered' : (contact.emails_queued_count > 0 ? 'queued' : 'none');
    return '<tr class="mg-crm-contact-card-row" data-contact-id="' + id + '" data-contact-email="' + esc(contact.email || '') + '" data-crm-stats-ready="1">' +
      '<td class="mg-crm-select-cell"><input type="checkbox" data-crm-contact-check aria-label="Select contact"' + checked + '></td>' +
      '<td><div class="mg-crm-contact-main"><div class="mg-crm-contact-avatar" aria-hidden="true">' + esc(initials(contact)) + '</div><div class="mg-crm-contact-copy"><strong>' + esc(contact.name || 'Unnamed') + '</strong><small>' + esc(contact.email || '') + '</small>' + (contact.no_recent_activity ? '<small class="mg-crm-segment-note">No recent activity</small>' : '') + '</div></div></td>' +
      '<td class="mg-crm-score-cell"><div class="mg-crm-contact-score ' + scoreClass(score) + '"><b>' + esc(score) + '</b><span>' + esc(label(scoreLabel)) + '</span><small>' + esc(label(result)) + '</small></div></td>' +
      '<td>' + esc(contact.campaign_title || '—') + '<br><small>' + esc(contact.campaign_type || contact.source || '') + '</small></td>' +
      '<td>' + badge(accountText, !!contact.has_account) + ' ' + badge(contact.email_verified ? 'Verified' : 'Unverified', !!contact.email_verified) + '</td>' +
      '<td>' + badge(reward, reward === 'claimed' || reward === 'redeemed') + (Number(contact.invite_pending_count || 0) > 0 ? ' ' + badge('Invite pending', true) : '') + '</td>' +
      '<td class="mg-crm-activity-cell"><div class="mg-crm-contact-insights"><div>' +
        metric(stats.issued || contact.issued_count || 0, 'Issued') +
        metric(stats.claimed || contact.claimed_count || 0, 'Claimed') +
        metric(stats.redeemed || contact.redeemed_count || 0, 'Redeemed') +
        metric(stats.messages || contact.emails_delivered_count || contact.message_count || 0, 'Messages') +
        '</div><p><strong>Next:</strong> ' + esc(next) + '</p><p><strong>Email:</strong> ' + esc(emailStatus) + '</p></div></td>' +
      '<td><div class="mg-crm-row-actions"><a class="mg-btn mg-btn-soft" href="' + esc(profileUrl(contact)) + '" data-crm-view-customer>View Customer</a><button class="mg-btn mg-btn-soft" type="button" data-view-timeline>Timeline</button><button class="mg-btn mg-btn-soft" type="button" data-crm-message>Message</button><button class="mg-btn mg-btn-soft" type="button" data-crm-gift>Send reward</button></div></td>' +
      '</tr>';
  }
  function renderContacts() {
    var wrapper = qs('[data-merchant-crm-table]');
    var rows = visibleContacts();
    if (!wrapper) return;
    qsa('[data-crm-segment]').forEach(function (button) {
      button.classList.toggle('is-active', button.getAttribute('data-crm-segment') === state.segment);
    });
    if (!state.contacts.length) {
      wrapper.innerHTML = '<div class="mg-empty-state"><strong>No contacts yet</strong><p>Campaign signups will appear here.</p></div>';
      updateBulkState();
      return;
    }
    if (!rows.length) {
      wrapper.innerHTML = '<div class="mg-empty-state"><strong>No contacts in this segment</strong><p>Choose another segment.</p></div>';
      updateBulkState();
      return;
    }
    wrapper.innerHTML = '<table class="mg-crm-table"><thead><tr><th class="mg-crm-select-cell">Select</th><th>Contact</th><th>Score</th><th>Campaign</th><th>Account</th><th>Reward Status</th><th>Activity Summary</th><th>Actions</th></tr></thead><tbody>' + rows.map(contactRow).join('') + '</tbody></table>';
    updateBulkState();
    document.dispatchEvent(new CustomEvent('mg:crm-contacts:rendered', { detail: { contacts: state.contacts, visible: rows } }));
    maybeRunEntryAction();
  }
  async function loadCampaigns() {
    try {
      var response = await Microgifter.get('/api/merchant/campaign-activity.php');
      var data = response.data || response;
      var select = qs('[data-crm-campaign-filter]');
      state.campaigns = data.campaigns || [];
      if (select) {
        select.innerHTML = '<option value="">All campaigns</option>' + state.campaigns.map(function (campaign) {
          return '<option value="' + esc(campaign.id) + '">' + esc(campaign.title || campaign.id) + '</option>';
        }).join('');
        select.value = state.selectedCampaign;
      }
    } catch (error) {
      state.campaigns = [];
    }
  }
  async function loadContacts() {
    var url = '/api/merchant/campaign-contacts.php' + (state.selectedCampaign ? '?campaign=' + encodeURIComponent(state.selectedCampaign) : '');
    var response = await Microgifter.get(url);
    var data = response.data || response;
    var totals = data.totals || {};
    state.contacts = data.contacts || [];
    set('[data-merchant-crm-total]', Number(totals.contacts || data.count || 0).toLocaleString());
    set('[data-merchant-crm-accounts]', Number(totals.accounts || 0).toLocaleString());
    set('[data-merchant-crm-verified]', Number(totals.verified || 0).toLocaleString());
    set('[data-merchant-crm-wallets]', Number(totals.wallets || 0).toLocaleString());
    renderContacts();
  }
  function titleForEvent(event) {
    var type = String(event.type || '');
    if (type === 'crm.reward_invite.sent') return 'Reward invite sent';
    if (type === 'crm.reward_invite.resent') return 'Reward invite resent';
    if (type === 'crm.reward_invite.delivered') return 'Invite converted to wallet reward';
    if (type === 'crm.reward_invite.revoked') return 'Reward invite revoked';
    if (type === 'crm.gift.issued') return 'Direct reward sent';
    if (type === 'crm.customer_refund.sent') return 'Customer Refund voucher sent';
    if (type === 'crm.message.sent') return 'CRM message sent';
    if (type === 'crm.followup.created') return 'CRM follow-up created';
    return event.title || type;
  }
  function detailForEvent(event) {
    var context = event.context || {};
    var parts = [];
    ['reward_template_id', 'invite_id', 'wallet_item_id', 'thread_id', 'message_id', 'due_at'].forEach(function (key) {
      if (context[key]) parts.push(key.replace(/_/g, ' ') + ' ' + context[key]);
    });
    return parts.length ? '<p>' + parts.map(esc).join(' · ') + '</p>' : '';
  }
  function eventRow(event) {
    var type = String(event.type || '');
    var highlight = type.indexOf('crm.reward_invite') === 0 || ['crm.gift.issued', 'crm.customer_refund.sent', 'crm.message.sent', 'crm.followup.created'].indexOf(type) >= 0;
    return '<article class="mg-crm-timeline-item ' + (highlight ? 'is-highlight' : '') + '"><span></span><div><strong>' + esc(titleForEvent(event)) + '</strong><small>' + esc(type) + '</small>' + (event.status ? '<em>' + esc(event.status) + '</em>' : '') + detailForEvent(event) + '</div></article>';
  }
  async function openTimeline(contact) {
    state.activeContact = contact;
    var drawer = qs('[data-crm-drawer]');
    var list = qs('[data-crm-timeline-list]');
    if (drawer) drawer.hidden = false;
    set('[data-crm-drawer-title]', contact.name || contact.email || 'Contact timeline');
    set('[data-crm-drawer-subtitle]', (contact.email || '') + ' · ' + (contact.campaign_title || 'Campaign'));
    if (list) list.innerHTML = '<div class="mg-empty-state"><strong>Loading timeline</strong></div>';
    try {
      var response = await Microgifter.get('/api/merchant/campaign-timeline.php?campaign=' + encodeURIComponent(contact.campaign_id) + '&contact=' + encodeURIComponent(contact.id));
      var data = response.data || response;
      var items = data.timeline || [];
      if (list) list.innerHTML = items.length ? items.map(eventRow).join('') : '<div class="mg-empty-state"><strong>No timeline yet</strong></div>';
    } catch (error) {
      if (list) list.innerHTML = '<div class="mg-empty-state"><strong>Unable to load timeline</strong></div>';
    }
  }
  function messageModal(on, contact) {
    var modal = qs('[data-crm-message-modal]');
    if (!modal) return;
    if (contact) {
      state.activeContact = contact;
      set('[data-crm-message-title]', 'Message ' + (contact.name || contact.email || 'contact'));
      var body = qs('[data-crm-message-body]');
      if (body) body.value = '';
      set('[data-crm-message-status]', '');
    }
    modal.hidden = !on;
  }
  async function submitMessageForm(event) {
    event.preventDefault();
    var bodyElement = qs('[data-crm-message-body]', event.target);
    var status = qs('[data-crm-message-status]', event.target);
    var body = ((bodyElement || {}).value || '').trim();
    var button = qs('[data-crm-message-submit]', event.target);
    if (!state.activeContact || !body) return;
    if (status) {
      status.textContent = 'Sending message...';
      status.dataset.statusType = '';
    }
    busy(button, true, 'Sending...');
    try {
      var response = await Microgifter.post('/api/merchant/crm-message.php', { contact_id: state.activeContact.id, message: body, idempotency_key: 'crm-message-ui:' + state.activeContact.id + ':' + Date.now() });
      var data = response.data || response;
      var message = data.message || {};
      var sent = message.delivered_via === 'microgifter_thread';
      var text = sent ? 'Message delivered to customer Messages.' : 'Message queued for email fallback.';
      if (bodyElement) bodyElement.value = '';
      if (status) {
        status.textContent = text + ' Thread: ' + (data.thread_id || message.thread_id || '');
        status.dataset.statusType = 'success';
      }
      document.dispatchEvent(new CustomEvent('mg:crm-messages:refresh', { detail: { thread_id: data.thread_id || '' } }));
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      await loadContacts();
      toast(text);
    } catch (error) {
      if (status) {
        status.textContent = error.message || 'Unable to send CRM message.';
        status.dataset.statusType = 'error';
      }
    } finally {
      busy(button, false);
    }
  }
  function bulkModal(on) {
    var modal = qs('[data-crm-bulk-modal]');
    if (modal) modal.hidden = !on;
  }
  function renderBulkPreview() {
    var contacts = selectedContacts();
    var accounts = contacts.filter(function (contact) { return !!contact.has_account; }).length;
    var pending = contacts.filter(function (contact) { return Number(contact.invite_pending_count || 0) > 0; }).length;
    var element = qs('[data-crm-bulk-preview]');
    if (element) element.innerHTML = '<span><strong>' + contacts.length + '</strong>Selected</span><span><strong>' + accounts + '</strong>Account contacts</span><span><strong>' + (contacts.length - accounts) + '</strong>No-account contacts</span><span><strong>' + pending + '</strong>Invite pending</span>';
  }
  async function loadBulkTemplates() {
    var select = qs('[data-crm-bulk-template]');
    if (select) select.innerHTML = '<option value="">Loading rewards...</option>';
    var response = await Microgifter.get('/api/merchant/reward-templates.php?status=active');
    var data = response.data || response;
    var items = data.templates || [];
    if (select) select.innerHTML = '<option value="">Choose reward</option>' + items.map(function (template) {
      return '<option value="' + esc(template.id) + '">' + esc(template.title || 'Reward') + '</option>';
    }).join('');
  }
  async function openBulkAction(mode) {
    if (!selectedContacts().length) {
      toast('Select CRM contacts first.');
      return;
    }
    state.bulkMode = mode;
    set('[data-crm-bulk-title]', mode === 'message' ? 'Message selected contacts' : (mode === 'reward' ? 'Send or invite selected rewards' : 'Create selected follow-ups'));
    set('[data-crm-bulk-subtitle]', mode === 'reward' ? 'Account contacts receive direct rewards. No-account contacts receive reward invite links.' : 'Preview the selected contacts before processing.');
    qs('[data-crm-bulk-message-field]').hidden = mode !== 'message';
    qs('[data-crm-bulk-reward-field]').hidden = mode !== 'reward';
    qs('[data-crm-bulk-note-field]').hidden = mode === 'message';
    qs('[data-crm-bulk-due-field]').hidden = mode !== 'followup';
    renderBulkPreview();
    bulkModal(true);
    if (mode === 'reward') await loadBulkTemplates();
  }
  function renderBulkResults(data) {
    var summary = (data && data.summary) || {};
    var element = qs('[data-crm-bulk-results]');
    if (element) {
      element.hidden = false;
      element.innerHTML = '<strong>Result summary</strong><p>Sent: ' + Number(summary.sent || 0) + ' · Issued: ' + Number(summary.issued || 0) + ' · Invited: ' + Number(summary.invited || 0) + ' · Skipped: ' + Number(summary.skipped || 0) + ' · Failed: ' + Number(summary.failed || 0) + ' · Duplicates: ' + Number(summary.duplicates || 0) + '</p>';
    }
  }
  async function submitBulkForm(event) {
    event.preventDefault();
    var ids = selectedContacts().map(function (contact) { return contact.id; });
    var payload = { contact_ids: ids, idempotency_key: 'crm-bulk-' + state.bulkMode + '-ui:' + Date.now() };
    var endpoint = '/api/merchant/crm-followup.php';
    var button = qs('[data-crm-bulk-submit]');
    if (state.bulkMode === 'message') {
      endpoint = '/api/merchant/crm-bulk-message.php';
      payload.message = ((qs('[data-crm-bulk-message]') || {}).value || '').trim();
    } else if (state.bulkMode === 'reward') {
      endpoint = '/api/merchant/crm-bulk-reward.php';
      payload.reward_template_id = (qs('[data-crm-bulk-template]') || {}).value || '';
      payload.note = ((qs('[data-crm-bulk-note]') || {}).value || '').trim();
    } else {
      payload.note = ((qs('[data-crm-bulk-note]') || {}).value || '').trim();
      payload.due_at = ((qs('[data-crm-bulk-due]') || {}).value || '').trim();
    }
    busy(button, true, 'Processing...');
    try {
      var response = await Microgifter.post(endpoint, payload);
      var data = response.data || response;
      renderBulkResults(data);
      await loadContacts();
      toast('Bulk CRM action complete.');
    } catch (error) {
      set('[data-crm-bulk-status]', error.message || 'Unable to process bulk action.');
    } finally {
      busy(button, false);
    }
  }
  function csv(value) {
    value = String(value == null ? '' : value);
    return /[",\n\r]/.test(value) ? '"' + value.replace(/"/g, '""') + '"' : value;
  }
  function exportSelected() {
    var rows = selectedContacts();
    var head = ['Name', 'Email', 'Campaign', 'Campaign type', 'Account', 'Email verified', 'Reward issued', 'Reward claimed', 'Reward redeemed', 'Invite pending', 'Last activity'];
    if (!rows.length) return toast('Select CRM contacts first.');
    var body = rows.map(function (contact) {
      return [contact.name, contact.email, contact.campaign_title, contact.campaign_type, contact.has_account ? 'yes' : 'no', contact.email_verified ? 'yes' : 'no', contact.issued_count || 0, contact.claimed_count || 0, contact.redeemed_count || 0, contact.invite_pending_count || 0, contact.last_activity_at || ''].map(csv).join(',');
    });
    var blob = new Blob([[head.map(csv).join(',')].concat(body).join('\n')], { type: 'text/csv' });
    var url = URL.createObjectURL(blob);
    var anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'microgifter-crm-selected-contacts.csv';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 500);
  }
  function findEntryContact() {
    var params = new URLSearchParams(location.search || '');
    var id = params.get('campaign_contact_id') || params.get('contact_id') || params.get('contact') || '';
    var email = String(params.get('email') || '').toLowerCase();
    if (id) {
      var byId = state.contacts.find(function (contact) { return String(contact.id) === String(id); });
      if (byId) return byId;
    }
    if (email) return state.contacts.find(function (contact) { return String(contact.email || '').toLowerCase() === email; }) || null;
    return null;
  }
  function maybeRunEntryAction() {
    if (state.entryActionHandled) return;
    var params = new URLSearchParams(location.search || '');
    var action = String(params.get('action') || '').toLowerCase();
    if (!action) return;
    var contact = findEntryContact();
    if (!contact) return;
    state.entryActionHandled = true;
    if (action === 'message') messageModal(true, contact);
    if (action === 'reward' || action === 'gift') document.dispatchEvent(new CustomEvent('mg:crm:open-reward', { detail: { contact: contact } }));
    if (action === 'timeline') openTimeline(contact);
  }
  async function refresh() {
    try {
      await loadCampaigns();
      await loadContacts();
    } catch (error) {
      var wrapper = qs('[data-merchant-crm-table]');
      if (wrapper) wrapper.innerHTML = '<div class="mg-empty-state"><strong>Unable to load contacts</strong></div>';
    }
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    var row = target.closest && target.closest('tr[data-contact-id]');
    var id = row && row.getAttribute('data-contact-id');
    var contact = id && state.contacts.find(function (item) { return String(item.id) === String(id); });
    if (target.closest && target.closest('[data-view-timeline]') && contact) openTimeline(contact);
    if (target.closest && target.closest('[data-crm-message]') && contact) messageModal(true, contact);
    if (target.closest && target.closest('[data-crm-drawer-close]')) {
      var drawer = qs('[data-crm-drawer]');
      if (drawer) drawer.hidden = true;
    }
    if (target.closest && target.closest('[data-crm-message-close]')) messageModal(false);
    if (target.closest && target.closest('[data-crm-bulk-close]')) bulkModal(false);
    var segment = target.closest && target.closest('[data-crm-segment]');
    if (segment) {
      state.segment = segment.getAttribute('data-crm-segment') || 'all';
      renderContacts();
    }
    var bulk = target.closest && target.closest('[data-crm-bulk-action]');
    if (bulk) {
      var mode = bulk.getAttribute('data-crm-bulk-action');
      mode === 'export' ? exportSelected() : openBulkAction(mode);
    }
    if (target.closest && target.closest('[data-crm-refresh]')) refresh();
  });

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (target && target.matches && target.matches('[data-crm-contact-check]')) {
      var row = target.closest('tr[data-contact-id]');
      var id = row && row.getAttribute('data-contact-id');
      if (target.checked) state.selected[id] = true;
      else delete state.selected[id];
      updateBulkState();
    }
    if (target && target.matches && target.matches('[data-crm-select-visible]')) {
      var on = !!target.checked;
      visibleContacts().forEach(function (contact) {
        if (on) state.selected[String(contact.id)] = true;
        else delete state.selected[String(contact.id)];
      });
      renderContacts();
    }
  });

  document.addEventListener('submit', function (event) {
    if (event.target && event.target.matches('[data-crm-message-form]')) submitMessageForm(event);
    if (event.target && event.target.matches('[data-crm-bulk-form]')) submitBulkForm(event);
  });

  var filter = qs('[data-crm-campaign-filter]');
  if (filter) filter.addEventListener('change', function () {
    state.selectedCampaign = this.value;
    state.selected = {};
    state.entryActionHandled = true;
    loadContacts();
  });

  refresh();
});
