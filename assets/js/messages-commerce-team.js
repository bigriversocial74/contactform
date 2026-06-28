document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-messages-center]');
  if (!root || !window.Microgifter) return;

  var MG = window.Microgifter;
  var modal = null;
  var rewardCache = null;
  var teamCache = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function csrf() { return MG.getCsrfToken ? MG.getCsrfToken() : ''; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'success'); }
  function threadId() { var form = qs('[data-thread-reply]'); return form ? String(form.dataset.threadId || '') : ''; }
  function composer() { return qs('[data-thread-reply] textarea[name="body"]'); }
  function insertText(text) {
    var textarea = composer();
    if (!textarea) return;
    var current = textarea.value.trim();
    textarea.value = current ? current + '\n' + text : text;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
  }
  function openModal(title, body, footer) {
    closeModal();
    modal = document.createElement('div');
    modal.className = 'mg-message-commerce-modal';
    modal.innerHTML = '<div class="mg-message-commerce-backdrop" data-commerce-close></div><section class="mg-message-commerce-dialog" role="dialog" aria-modal="true"><header><div><span>Message tools</span><h2>' + esc(title) + '</h2></div><button type="button" data-commerce-close aria-label="Close">×</button></header><div class="mg-message-commerce-body">' + body + '</div><footer>' + (footer || '') + '</footer></section>';
    document.body.appendChild(modal);
  }
  function closeModal() {
    if (modal) modal.remove();
    modal = null;
  }
  function rewardValueLabel(item) {
    if (!item) return 'Reward';
    if (item.value_percent != null && item.value_percent !== '') return String(item.value_percent).replace(/\.0+$/, '') + '% off';
    if (item.value_amount_cents) return '$' + (Number(item.value_amount_cents || 0) / 100).toFixed(2);
    if (item.reward_type) return String(item.reward_type).replace(/_/g, ' ');
    return 'Reward';
  }
  function normalizeRewardRows(data) {
    data = data || {};
    var rows = [];
    (Array.isArray(data.templates) ? data.templates : []).forEach(function (item) {
      rows.push({ type: 'reward', id: item.public_id || item.id || item.slug || '', title: item.name || item.title || 'Reward template', subtitle: rewardValueLabel(item), source: 'Template' });
    });
    (Array.isArray(data.campaigns) ? data.campaigns : []).forEach(function (item) {
      rows.push({ type: 'campaign', id: item.public_id || item.id || item.slug || '', title: item.name || item.title || 'Campaign reward', subtitle: item.status || item.reward_type || 'Campaign', source: 'Campaign' });
    });
    if (!rows.length) {
      rows = [
        { type: 'reward', id: 'standard-reward', title: 'Standard visit reward', subtitle: 'Merchant reward', source: 'Draft' },
        { type: 'campaign', id: 'campaign-follow-up', title: 'Campaign follow-up reward', subtitle: 'Campaign reward', source: 'Draft' },
        { type: 'microgift', id: 'existing-microgift', title: 'Existing Microgift', subtitle: 'Attach a Microgift offer', source: 'Draft' }
      ];
    }
    return rows;
  }
  async function loadRewards() {
    if (rewardCache) return rewardCache;
    try {
      rewardCache = normalizeRewardRows(payload(await MG.get('/api/merchant-canvas/reward-options.php')) || {});
    } catch (error) {
      rewardCache = normalizeRewardRows({});
    }
    return rewardCache;
  }
  function rewardMessage(row) {
    if (!row) return '';
    var tag = row.type === 'campaign' ? 'Campaign reward' : (row.type === 'microgift' ? 'Microgift' : 'Reward');
    return tag + ': ' + row.title + '\n' + row.subtitle + '\n[Reward attachment: ' + row.id + ']';
  }
  async function showRewardModal() {
    var rows = await loadRewards();
    openModal('Attach reward', '<div class="mg-commerce-search"><input type="search" data-reward-search placeholder="Search rewards, campaigns, Microgifts..."></div><div class="mg-commerce-list" data-reward-list>' + rows.map(function (row, index) {
      return '<button type="button" data-reward-index="' + index + '"><strong>' + esc(row.title) + '</strong><span>' + esc(row.subtitle) + '</span><small>' + esc(row.source) + '</small></button>';
    }).join('') + '</div>', '<button type="button" data-commerce-close>Cancel</button>');
  }
  function showFileModal(kind) {
    var title = kind === 'pdf' ? 'Attach PDF' : 'Attach image';
    openModal(title, '<div class="mg-commerce-upload-box"><strong>' + esc(title) + '</strong><p>Upload wiring comes next. This adds a clean attachment placeholder to the composer now so the message flow is ready.</p><input type="file" ' + (kind === 'image' ? 'accept="image/*"' : 'accept="application/pdf"') + ' data-file-input></div>', '<button type="button" data-file-placeholder="' + esc(kind) + '">Add placeholder</button><button type="button" data-commerce-close>Cancel</button>');
  }
  async function loadTeam() {
    if (teamCache) return teamCache;
    teamCache = [
      { id: 'me', name: 'Assign to me', role: 'Owner' },
      { id: 'support', name: 'Support teammate', role: 'Team' },
      { id: 'manager', name: 'Manager review', role: 'Manager' }
    ];
    return teamCache;
  }
  async function showTeamModal() {
    var team = await loadTeam();
    openModal('Team workflow', '<div class="mg-commerce-team-grid">' + team.map(function (person) {
      return '<button type="button" data-team-member="' + esc(person.id) + '"><strong>' + esc(person.name) + '</strong><span>' + esc(person.role) + '</span></button>';
    }).join('') + '</div><label class="mg-commerce-note"><span>Internal note</span><textarea data-team-note rows="4" placeholder="Add private context for the team..."></textarea></label>', '<button type="button" data-save-team-note>Save note</button><button type="button" data-commerce-close>Cancel</button>');
  }
  async function assignToMe() {
    var id = threadId();
    if (!id) return;
    await MG.post('/api/messages/crm-ops.php', { thread_id: id, action: 'update_state', assign_to_self: true, csrf_token: csrf() });
    toast('Assigned to you.');
    document.dispatchEvent(new CustomEvent('mg:messages:refresh'));
  }
  async function saveInternalNote() {
    var id = threadId();
    var note = modal ? qs('[data-team-note]', modal) : null;
    var body = note ? note.value.trim() : '';
    if (!id || !body) return;
    await MG.post('/api/messages/crm-ops.php', { thread_id: id, action: 'save_note', body: body, csrf_token: csrf() });
    toast('Internal note saved.');
    closeModal();
  }
  function upgradeAttachmentTray() {
    qsa('[data-attachment-tray]').forEach(function (tray) {
      if (tray.dataset.commerceUpgraded === '1') return;
      tray.dataset.commerceUpgraded = '1';
      tray.innerHTML = '<button type="button" data-commerce-attach="image">Attach image</button><button type="button" data-commerce-attach="pdf">Attach PDF</button><button type="button" data-commerce-attach="reward">Send reward</button><button type="button" data-commerce-attach="microgift">Attach Microgift</button><button type="button" data-commerce-attach="campaign">Campaign reward</button><button type="button" data-commerce-attach="team">Team note</button>';
    });
  }
  function upgradeThreadMenu() {
    var menu = qs('[data-thread-menu]');
    if (!menu || menu.dataset.teamUpgraded === '1') return;
    menu.dataset.teamUpgraded = '1';
    menu.insertAdjacentHTML('beforeend', '<button type="button" data-thread-action="team-workflow">Team workflow</button><button type="button" data-thread-action="internal-note">Internal note</button>');
  }
  function refreshUpgrades() {
    upgradeAttachmentTray();
    upgradeThreadMenu();
  }

  root.addEventListener('click', function (event) {
    var attach = event.target.closest('[data-commerce-attach]');
    if (attach) {
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      var type = attach.dataset.commerceAttach;
      if (type === 'image' || type === 'pdf') showFileModal(type);
      else if (type === 'reward' || type === 'microgift' || type === 'campaign') showRewardModal();
      else if (type === 'team') showTeamModal();
      return;
    }
    var threadAction = event.target.closest('[data-thread-action="team-workflow"], [data-thread-action="internal-note"]');
    if (threadAction) {
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      showTeamModal();
      return;
    }
  }, true);

  document.addEventListener('click', function (event) {
    if (!modal) return;
    if (event.target.closest('[data-commerce-close]')) { closeModal(); return; }
    var rewardButton = event.target.closest('[data-reward-index]');
    if (rewardButton) {
      var row = rewardCache[Number(rewardButton.dataset.rewardIndex || 0)];
      insertText(rewardMessage(row));
      toast('Reward attached to composer.');
      closeModal();
      return;
    }
    var fileButton = event.target.closest('[data-file-placeholder]');
    if (fileButton) {
      var kind = fileButton.dataset.filePlaceholder;
      insertText(kind === 'pdf' ? '[PDF attachment pending]' : '[Image attachment pending]');
      toast(kind === 'pdf' ? 'PDF placeholder added.' : 'Image placeholder added.');
      closeModal();
      return;
    }
    var teamButton = event.target.closest('[data-team-member]');
    if (teamButton) {
      if (teamButton.dataset.teamMember === 'me') assignToMe().catch(function (error) { toast(error.message || 'Unable to assign.', 'error'); });
      else insertText('[Internal assignment: ' + teamButton.textContent.trim() + ']');
      return;
    }
    if (event.target.closest('[data-save-team-note]')) {
      saveInternalNote().catch(function (error) { toast(error.message || 'Unable to save note.', 'error'); });
    }
  });

  document.addEventListener('input', function (event) {
    if (!modal || !event.target.matches('[data-reward-search]')) return;
    var query = event.target.value.toLowerCase();
    var list = qs('[data-reward-list]', modal);
    if (!list) return;
    qsa('[data-reward-index]', list).forEach(function (button) {
      button.hidden = !button.textContent.toLowerCase().includes(query);
    });
  });

  var observer = new MutationObserver(refreshUpgrades);
  observer.observe(root, { childList: true, subtree: true });
  refreshUpgrades();
});
