document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var center = document.querySelector('[data-merchant-contact-action-center]');
  var workspace = center && center.querySelector('[data-contact-workspace]');
  if (!center || !workspace || !window.Microgifter) return;

  var idInput = center.querySelector('[data-contact-center-id]');
  var statusNode = workspace.querySelector('[data-contact-workspace-status]');
  var tabs = Array.prototype.slice.call(workspace.querySelectorAll('[data-contact-workspace-tab]'));
  var panels = Array.prototype.slice.call(workspace.querySelectorAll('[data-contact-workspace-panel]'));
  var filters = Array.prototype.slice.call(workspace.querySelectorAll('[data-contact-timeline-filter]'));
  var timelineNode = workspace.querySelector('[data-contact-workspace-timeline]');
  var noteInput = workspace.querySelector('[data-contact-note-input]');
  var noteSave = workspace.querySelector('[data-contact-note-save]');
  var noteList = workspace.querySelector('[data-contact-note-list]');
  var followupType = workspace.querySelector('[data-contact-followup-type]');
  var followupPriority = workspace.querySelector('[data-contact-followup-priority]');
  var followupDue = workspace.querySelector('[data-contact-followup-due]');
  var followupNote = workspace.querySelector('[data-contact-followup-note]');
  var followupReview = workspace.querySelector('[data-contact-followup-review]');
  var draftChannels = Array.prototype.slice.call(workspace.querySelectorAll('[data-contact-draft-channel]'));
  var draftSubject = workspace.querySelector('[data-contact-draft-subject]');
  var draftBody = workspace.querySelector('[data-contact-draft-body]');
  var draftReview = workspace.querySelector('[data-contact-draft-review]');
  var reviewSummary = workspace.querySelector('[data-contact-review-summary]');
  var reviewList = workspace.querySelector('[data-contact-review-list]');

  var currentCenter = null;
  var activeTab = 'timeline';
  var activeFilter = 'all';
  var activeChannel = 'crm_message';
  var busy = false;
  var draftKey = '';
  var followupKey = '';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function human(value) {
    return String(value || '').replace(/[._-]+/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); });
  }

  function compactDate(value) {
    var stamp = Date.parse(value || '');
    return stamp ? new Date(stamp).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : 'No date';
  }

  function threadId() {
    var select = document.querySelector('[data-agent-thread-select]');
    return select && select.value ? select.value : '';
  }

  function contactId() {
    return idInput && idInput.value ? String(idInput.value) : String(currentCenter && currentCenter.contact && currentCenter.contact.id || '');
  }

  function setStatus(message, type) {
    if (!statusNode) return;
    statusNode.textContent = message || '';
    statusNode.className = 'mg-contact-workspace-status' + (type ? ' is-' + type : '');
  }

  function setBusy(value) {
    busy = !!value;
    [noteSave, followupReview, draftReview].forEach(function (button) {
      if (button) button.disabled = busy;
    });
    workspace.classList.toggle('is-busy', busy);
  }

  function newKey(prefix) {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return prefix + ':' + window.crypto.randomUUID();
    return prefix + ':' + Date.now() + ':' + Math.random().toString(36).slice(2);
  }

  function extractState(response) {
    var data = response && response.data ? response.data : response || {};
    return data.state || data;
  }

  function applyResponse(response) {
    var state = extractState(response);
    if (state && state.contact_action_center) render(state.contact_action_center);
    document.dispatchEvent(new CustomEvent('mg:merchant-agent:state', { detail: { state: state } }));
    return state;
  }

  function setTab(key) {
    activeTab = key || 'timeline';
    tabs.forEach(function (button) {
      var selected = button.getAttribute('data-contact-workspace-tab') === activeTab;
      button.setAttribute('aria-selected', selected ? 'true' : 'false');
      button.classList.toggle('is-active', selected);
    });
    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-contact-workspace-panel') !== activeTab;
    });
  }

  function activityCategory(item) {
    var type = String(item && (item.type || item.event_type) || '').toLowerCase();
    if (/purchase|order|payment|checkout|commerce/.test(type)) return 'purchases';
    if (/reward|claim|redeem|redemption|gift|wallet/.test(type)) return 'rewards';
    if (/message|email|sms|dm/.test(type)) return 'messages';
    if (/campaign|contest|social|referral/.test(type)) return 'campaigns';
    if (/followup|follow_up|task|note/.test(type)) return 'tasks_notes';
    return 'other';
  }

  function renderTimeline() {
    if (!timelineNode) return;
    var items = Array.isArray(currentCenter && currentCenter.recent_activity) ? currentCenter.recent_activity : [];
    var visible = items.filter(function (item) {
      return activeFilter === 'all' || activityCategory(item) === activeFilter;
    });
    timelineNode.innerHTML = visible.length ? visible.map(function (item) {
      var category = activityCategory(item);
      var title = item.title || human(item.event_type || item.type || 'CRM activity');
      var body = item.body || [human(item.campaign_type || ''), human(item.source_type || '')].filter(Boolean).join(' · ');
      return '<article data-contact-activity-category="' + esc(category) + '"><div><strong>' + esc(title) + '</strong><span>' + esc(body || 'Merchant CRM activity') + '</span></div><small>' + esc(compactDate(item.created_at || item.at)) + '</small></article>';
    }).join('') : '<span class="is-empty">No activity matches this timeline filter.</span>';
  }

  function setFilter(key) {
    activeFilter = key || 'all';
    filters.forEach(function (button) {
      var selected = button.getAttribute('data-contact-timeline-filter') === activeFilter;
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      button.classList.toggle('is-active', selected);
    });
    renderTimeline();
  }

  function renderNotes() {
    if (!noteList) return;
    var workspaceData = currentCenter && currentCenter.workspace || {};
    var noteData = workspaceData.notes || {};
    var items = Array.isArray(noteData.items) ? noteData.items : (Array.isArray(currentCenter && currentCenter.recent_notes) ? currentCenter.recent_notes : []);
    noteList.innerHTML = items.length ? items.map(function (item) {
      return '<article><div><strong>Internal CRM note</strong><span>' + esc(item.note || '') + '</span></div><small>' + esc(compactDate(item.created_at)) + '</small></article>';
    }).join('') : '<span class="is-empty">No internal notes have been added for this contact.</span>';
  }

  function renderReviews() {
    if (!reviewList || !reviewSummary) return;
    var review = currentCenter && currentCenter.workspace && currentCenter.workspace.review_status || { count: 0, waiting: 0, items: [] };
    var items = Array.isArray(review.items) ? review.items : [];
    reviewSummary.textContent = Number(review.waiting || 0).toLocaleString() + ' waiting · ' + Number(review.count || 0).toLocaleString() + ' recent';
    reviewList.innerHTML = items.length ? items.map(function (item) {
      var meta = [human(item.draft_kind || item.action_key), item.channel ? human(item.channel) : '', item.due_at ? 'Due ' + compactDate(item.due_at) : ''].filter(Boolean).join(' · ');
      return '<article><div><strong>' + esc(item.title || 'Contact review item') + '</strong><span>' + esc(meta) + '</span></div><div class="mg-contact-review-state"><span class="is-' + esc(item.status || 'recommended') + '">' + esc(item.status_label || human(item.status)) + '</span><a href="' + esc(item.action_url || '/merchant-agent-approvals.php') + '">Review</a></div></article>';
    }).join('') : '<span class="is-empty">No Contact Action Center drafts are waiting in Agent Review.</span>';
  }

  function defaultDueDate() {
    if (!followupDue || followupDue.value) return;
    var date = new Date();
    date.setDate(date.getDate() + 3);
    followupDue.value = date.toISOString().slice(0, 10);
  }

  function render(centerState) {
    currentCenter = centerState && centerState.selected ? centerState : null;
    if (!currentCenter || !currentCenter.workspace) {
      workspace.hidden = true;
      return;
    }
    workspace.hidden = false;
    renderTimeline();
    renderNotes();
    renderReviews();
    defaultDueDate();
    setTab(activeTab);
  }

  async function saveNote() {
    if (busy || !currentCenter) return;
    var note = noteInput ? noteInput.value.trim() : '';
    if (!note) {
      setStatus('Enter a CRM note before saving.', 'error');
      return;
    }
    setBusy(true);
    setStatus('Saving internal CRM note…', '');
    try {
      var response = await Microgifter.post('/api/ai/merchant-agent-chat.php', {
        action: 'contact_note',
        contact_id: contactId(),
        thread_id: threadId(),
        note: note
      });
      applyResponse(response);
      if (noteInput) noteInput.value = '';
      setStatus('CRM note saved.', 'success');
    } catch (error) {
      setStatus(String(error && error.message || 'Unable to save the CRM note.'), 'error');
    } finally {
      setBusy(false);
    }
  }

  async function sendFollowupToReview() {
    if (busy || !currentCenter) return;
    var note = followupNote ? followupNote.value.trim() : '';
    var dueAt = followupDue ? followupDue.value : '';
    if (!note || !dueAt) {
      setStatus('Add a follow-up objective and due date.', 'error');
      return;
    }
    if (!followupKey) followupKey = newKey('contact-followup');
    setBusy(true);
    setStatus('Preparing follow-up task for Agent Review…', '');
    try {
      var response = await Microgifter.post('/api/ai/merchant-agent-chat.php', {
        action: 'contact_review_draft',
        draft_kind: 'followup',
        contact_id: contactId(),
        thread_id: threadId(),
        task_type: followupType ? followupType.value : 'customer_service',
        priority: followupPriority ? followupPriority.value : 'medium',
        due_at: dueAt,
        note: note,
        idempotency_key: followupKey
      });
      applyResponse(response);
      setTab('review');
      setStatus('Follow-up task added to Agent Review.', 'success');
    } catch (error) {
      setStatus(String(error && error.message || 'Unable to prepare the follow-up task.'), 'error');
    } finally {
      setBusy(false);
    }
  }

  async function sendDraftToReview() {
    if (busy || !currentCenter) return;
    var body = draftBody ? draftBody.value.trim() : '';
    if (!body) {
      setStatus('Write a message draft before sending it to review.', 'error');
      return;
    }
    if (!draftKey) draftKey = newKey('contact-message');
    setBusy(true);
    setStatus('Preparing editable message draft for Agent Review…', '');
    try {
      var response = await Microgifter.post('/api/ai/merchant-agent-chat.php', {
        action: 'contact_review_draft',
        draft_kind: 'message',
        contact_id: contactId(),
        thread_id: threadId(),
        channel: activeChannel,
        subject: draftSubject ? draftSubject.value.trim() : '',
        body: body,
        idempotency_key: draftKey
      });
      applyResponse(response);
      setTab('review');
      setStatus('Message draft added to Agent Review.', 'success');
    } catch (error) {
      setStatus(String(error && error.message || 'Unable to prepare the message draft.'), 'error');
    } finally {
      setBusy(false);
    }
  }

  tabs.forEach(function (button) {
    button.addEventListener('click', function () { setTab(button.getAttribute('data-contact-workspace-tab') || 'timeline'); });
  });
  filters.forEach(function (button) {
    button.addEventListener('click', function () { setFilter(button.getAttribute('data-contact-timeline-filter') || 'all'); });
  });
  draftChannels.forEach(function (button) {
    button.addEventListener('click', function () {
      activeChannel = button.getAttribute('data-contact-draft-channel') || 'crm_message';
      draftKey = '';
      draftChannels.forEach(function (candidate) {
        var selected = candidate === button;
        candidate.setAttribute('aria-pressed', selected ? 'true' : 'false');
        candidate.classList.toggle('is-active', selected);
      });
    });
  });
  if (noteSave) noteSave.addEventListener('click', saveNote);
  if (followupReview) followupReview.addEventListener('click', sendFollowupToReview);
  if (draftReview) draftReview.addEventListener('click', sendDraftToReview);
  [followupType, followupPriority, followupDue, followupNote].forEach(function (field) {
    if (field) field.addEventListener('input', function () { followupKey = ''; });
  });
  [draftSubject, draftBody].forEach(function (field) {
    if (field) field.addEventListener('input', function () { draftKey = ''; });
  });

  document.addEventListener('mg:merchant-agent:state', function (event) {
    var state = event.detail && event.detail.state;
    if (state) render(state.contact_action_center || null);
  });
  document.addEventListener('mg:merchant-agent:apply-state', function (event) {
    var state = event.detail && event.detail.state;
    if (state) render(state.contact_action_center || null);
  });

  window.MicrogifterMerchantContactWorkspace = Object.freeze({
    open: setTab,
    getActiveTab: function () { return activeTab; },
    getFilter: function () { return activeFilter; }
  });
});
