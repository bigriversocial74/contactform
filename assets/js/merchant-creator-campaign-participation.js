(() => {
  'use strict';

  const endpoint = '/api/merchant/creator-campaign-participation.php';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const root = document.querySelector('[data-ccp-merchant]');
  if (!root) return;

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[char]));
  const uuid = () => globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  const query = (params) => new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined)
  ).toString();
  const date = (value) => value ? new Intl.DateTimeFormat(undefined, {dateStyle:'medium', timeStyle:'short'}).format(new Date(String(value).replace(' ', 'T') + 'Z')) : '—';
  const statusClass = (status) => {
    if (['approved','accepted','active','completed'].includes(status)) return 'is-green';
    if (['declined','cancelled','removed','expired'].includes(status)) return 'is-red';
    if (['submitted','under_review','pending','agreement_pending'].includes(status)) return 'is-blue';
    if (['information_requested','suspended'].includes(status)) return 'is-amber';
    return '';
  };

  async function apiGet(params) {
    const response = await fetch(`${endpoint}?${query(params)}`, {credentials:'same-origin', headers:{Accept:'application/json'}});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
    return payload.data || {};
  }
  async function apiPost(body) {
    const response = await fetch(endpoint, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/json', Accept:'application/json'},
      body:JSON.stringify({...body, csrf_token:csrf}),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
    return payload.data || {};
  }

  const elements = {
    metrics: root.querySelector('[data-ccp-metrics]'),
    loading: root.querySelector('[data-ccp-loading]'),
    error: root.querySelector('[data-ccp-error]'),
    errorMessage: root.querySelector('[data-ccp-error-message]'),
    list: root.querySelector('[data-ccp-list]'),
    live: root.querySelector('[data-ccp-live]'),
    filters: root.querySelector('[data-ccp-filters]'),
    campaignFilter: root.querySelector('[data-ccp-campaign-filter]'),
    statusFilter: root.querySelector('[data-ccp-status-filter]'),
    pagination: root.querySelector('[data-ccp-pagination]'),
    pageLabel: root.querySelector('[data-ccp-page-label]'),
    inviteDialog: root.querySelector('[data-ccp-invite-dialog]'),
    inviteForm: root.querySelector('[data-ccp-invite-form]'),
    inviteCampaign: root.querySelector('[data-ccp-invite-campaign]'),
    inviteCreator: root.querySelector('[data-ccp-invite-creator]'),
    reviewDialog: root.querySelector('[data-ccp-review-dialog]'),
    reviewTitle: root.querySelector('[data-ccp-review-title]'),
    reviewContent: root.querySelector('[data-ccp-review-content]'),
    reviewForm: root.querySelector('[data-ccp-review-form]'),
  };

  const state = {
    tab:'applications',
    page:1,
    pages:1,
    dashboard:null,
    directory:[],
  };

  function announce(message, error = false) {
    elements.live.textContent = message;
    elements.live.style.color = error ? '#b42318' : '';
  }

  function setLoading(loading) {
    elements.loading.classList.toggle('mg-hidden', !loading);
    if (loading) {
      elements.error.classList.add('mg-hidden');
      elements.list.classList.add('mg-hidden');
      elements.pagination.classList.add('mg-hidden');
    }
  }

  function showError(error) {
    elements.loading.classList.add('mg-hidden');
    elements.list.classList.add('mg-hidden');
    elements.pagination.classList.add('mg-hidden');
    elements.error.classList.remove('mg-hidden');
    elements.errorMessage.textContent = error.message || String(error);
  }

  function renderMetrics(metrics) {
    const cards = [
      ['Campaigns', metrics.campaigns],
      ['Pending Applications', metrics.pending_applications],
      ['Pending Invitations', metrics.pending_invitations],
      ['Participants', metrics.participants],
      ['Agreement Pending', metrics.agreement_pending],
    ];
    elements.metrics.innerHTML = cards.map(([label, value]) =>
      `<article class="mg-ccp-metric"><span>${esc(label)}</span><strong>${Number(value) || 0}</strong></article>`
    ).join('');
  }

  function campaignOptions() {
    const campaigns = state.dashboard?.campaigns || [];
    const options = campaigns.map((campaign) =>
      `<option value="${esc(campaign.public_id)}">${esc(campaign.title)} · ${esc(campaign.status)}</option>`
    ).join('');
    elements.campaignFilter.innerHTML = `<option value="">All campaigns</option>${options}`;
    elements.inviteCampaign.innerHTML = `<option value="">Choose campaign</option>${options}`;
  }

  const statuses = {
    applications:['draft','submitted','under_review','information_requested','approved','declined','withdrawn'],
    invitations:['pending','accepted','declined','cancelled','expired'],
    participants:['approved','agreement_pending','active','completed','removed','suspended'],
    directory:[],
    timeline:[],
  };

  function statusOptions() {
    const current = elements.statusFilter.value;
    elements.statusFilter.innerHTML = '<option value="">All statuses</option>' + (statuses[state.tab] || [])
      .map((status) => `<option value="${esc(status)}">${esc(status.replaceAll('_',' '))}</option>`).join('');
    if ([...elements.statusFilter.options].some((option) => option.value === current)) elements.statusFilter.value = current;
    elements.statusFilter.disabled = !(statuses[state.tab] || []).length;
  }

  function row({title, subtitle, status, meta, actions}) {
    return `<article class="mg-ccp-row">
      <div class="mg-ccp-row-main"><h3>${esc(title)}</h3><p>${esc(subtitle)}</p></div>
      <div><span class="mg-ccp-badge ${statusClass(status)}">${esc(String(status || '').replaceAll('_',' '))}</span></div>
      <div class="mg-ccp-row-meta">${meta}</div>
      <div class="mg-ccp-row-actions">${actions}</div>
    </article>`;
  }

  function renderApplications(items) {
    return items.map((item) => row({
      title:item.creator_name || 'Creator',
      subtitle:`${item.campaign_title || ''}${item.creator_email ? ` · ${item.creator_email}` : ''}`,
      status:item.status,
      meta:`<span>Submitted</span><strong>${esc(date(item.submitted_at))}</strong>`,
      actions:`<button class="mg-btn mg-btn-soft" type="button" data-review-application="${esc(item.public_id)}">Review</button>`,
    })).join('');
  }

  function renderInvitations(items) {
    return items.map((item) => row({
      title:item.creator_name || 'Creator',
      subtitle:item.campaign_title || '',
      status:item.status,
      meta:`<span>Deadline</span><strong>${esc(date(item.response_deadline_at))}</strong>`,
      actions:item.status === 'pending'
        ? `<button class="mg-btn mg-btn-ghost" type="button" data-cancel-invitation="${esc(item.public_id)}" data-lock="${Number(item.lock_version)||0}">Cancel</button>`
        : '',
    })).join('');
  }

  function renderParticipants(items) {
    return items.map((item) => {
      const actions = [];
      if (item.status === 'suspended') {
        actions.push(`<button class="mg-btn mg-btn-soft" type="button" data-participant-action="agreement_pending" data-id="${esc(item.public_id)}" data-lock="${Number(item.lock_version)||0}">Restore</button>`);
      } else if (['approved','agreement_pending','active'].includes(item.status)) {
        actions.push(`<button class="mg-btn mg-btn-ghost" type="button" data-participant-action="suspended" data-id="${esc(item.public_id)}" data-lock="${Number(item.lock_version)||0}">Suspend</button>`);
      }
      if (!['completed','removed'].includes(item.status)) {
        actions.push(`<button class="mg-btn mg-btn-danger" type="button" data-participant-action="removed" data-id="${esc(item.public_id)}" data-lock="${Number(item.lock_version)||0}">Remove</button>`);
      }
      return row({
        title:item.creator_name || 'Creator',
        subtitle:item.campaign_title || '',
        status:item.status,
        meta:`<span>Source</span><strong>${esc(item.source_type || '—')}</strong>`,
        actions:actions.join(''),
      });
    }).join('');
  }

  function renderDirectory(items) {
    state.directory = items;
    elements.inviteCreator.innerHTML = '<option value="">Choose creator</option>' + items.map((item) =>
      `<option value="${esc(item.creator_profile_public_id)}">${esc(item.display_name || item.slug || 'Creator')}</option>`
    ).join('');
    return items.map((item) => row({
      title:item.display_name || item.slug || 'Creator',
      subtitle:[item.headline,item.location_label].filter(Boolean).join(' · ') || item.bio || 'Approved Creator',
      status:'approved',
      meta:`<span>Profile</span><strong>${Number(item.completion_score)||0}% complete</strong>`,
      actions:`<button class="mg-btn mg-btn-primary" type="button" data-invite-creator="${esc(item.creator_profile_public_id)}">Invite</button>`,
    })).join('');
  }

  function renderTimeline(items) {
    return items.map((item) => row({
      title:String(item.event_type || '').replaceAll('.',' · '),
      subtitle:item.reason || `${item.from_status || 'created'} → ${item.to_status || 'updated'}`,
      status:item.to_status || 'event',
      meta:`<span>${esc(item.actor_display_name || item.actor_full_name || 'System')}</span><strong>${esc(date(item.created_at))}</strong>`,
      actions:'',
    })).join('');
  }

  async function loadDashboard() {
    state.dashboard = await apiGet({action:'dashboard'});
    renderMetrics(state.dashboard.metrics || {});
    campaignOptions();
  }

  async function loadTab() {
    setLoading(true);
    try {
      const form = new FormData(elements.filters);
      const params = {
        action:state.tab === 'directory' ? 'directory' : state.tab,
        page:state.page,
        search:form.get('search') || '',
        campaign_id:form.get('campaign_id') || '',
        status:form.get('status') || '',
      };
      if (state.tab === 'timeline') {
        params.action = 'timeline';
        if (!params.campaign_id) {
          elements.loading.classList.add('mg-hidden');
          elements.list.classList.remove('mg-hidden');
          elements.list.innerHTML = '<section class="mg-ccp-state"><strong>Choose a campaign</strong><span>Activity is scoped to one creator campaign at a time.</span></section>';
          return;
        }
      }
      const data = await apiGet(params);
      const items = data.items || [];
      const renderer = {
        applications:renderApplications,
        invitations:renderInvitations,
        participants:renderParticipants,
        directory:renderDirectory,
        timeline:renderTimeline,
      }[state.tab];
      elements.list.innerHTML = items.length
        ? renderer(items)
        : '<section class="mg-ccp-state"><strong>No records found</strong><span>This participation view has no matching records.</span></section>';
      elements.loading.classList.add('mg-hidden');
      elements.error.classList.add('mg-hidden');
      elements.list.classList.remove('mg-hidden');

      const pagination = data.pagination || {page:1,pages:1,total:items.length};
      state.pages = Math.max(1, Number(pagination.pages) || 1);
      elements.pagination.classList.toggle('mg-hidden', state.pages <= 1);
      elements.pageLabel.textContent = `Page ${state.page} of ${state.pages} · ${Number(pagination.total)||items.length} records`;
      root.querySelector('[data-ccp-prev]').disabled = state.page <= 1;
      root.querySelector('[data-ccp-next]').disabled = state.page >= state.pages;
    } catch (error) {
      showError(error);
    }
  }

  async function openApplication(publicId) {
    try {
      announce('Loading application…');
      const item = await apiGet({action:'application_detail', application_id:publicId});
      elements.reviewTitle.textContent = `${item.creator_display_name || item.creator_full_name || 'Creator'} · Application`;
      const snapshot = item.creator_snapshot || {};
      const answers = item.answers || [];
      elements.reviewContent.innerHTML = `
        <div class="mg-ccp-review-profile">
          <div class="mg-ccp-review-avatar" style="${snapshot.avatar_url ? `background-image:url('${esc(snapshot.avatar_url)}')` : ''}"></div>
          <div><strong>${esc(snapshot.display_name || item.creator_display_name || item.creator_full_name || 'Creator')}</strong><p>${esc(snapshot.headline || snapshot.location_label || item.creator_email || '')}</p></div>
        </div>
        <p>${esc(item.cover_note || 'No cover note provided.')}</p>
        ${item.portfolio_url ? `<p><a href="${esc(item.portfolio_url)}" target="_blank" rel="noopener">Open portfolio</a></p>` : ''}
        <div class="mg-ccp-answer-list">${answers.map((answer) =>
          `<article class="mg-ccp-answer"><strong>${esc(answer.prompt)}</strong><p>${esc(Array.isArray(answer.answer) ? answer.answer.join(', ') : answer.answer ?? 'No answer')}</p></article>`
        ).join('')}</div>`;
      elements.reviewForm.elements.application_id.value = item.public_id;
      elements.reviewForm.elements.expected_lock_version.value = item.lock_version;
      elements.reviewForm.elements.reason.value = item.decision_note || '';
      elements.reviewForm.elements.internal_note.value = item.internal_note || '';
      elements.reviewDialog.showModal();
      announce('');
    } catch (error) {
      announce(error.message, true);
    }
  }

  function openInvite(creatorPublicId = '') {
    if (creatorPublicId) elements.inviteCreator.value = creatorPublicId;
    elements.inviteDialog.showModal();
  }

  root.addEventListener('click', async (event) => {
    const tab = event.target.closest('[data-ccp-tab]');
    if (tab) {
      state.tab = tab.dataset.ccpTab;
      state.page = 1;
      root.querySelectorAll('[data-ccp-tab]').forEach((button) => button.classList.toggle('is-active', button === tab));
      statusOptions();
      await loadTab();
      return;
    }
    if (event.target.closest('[data-ccp-open-invite]')) return openInvite();
    const inviteCreator = event.target.closest('[data-invite-creator]');
    if (inviteCreator) return openInvite(inviteCreator.dataset.inviteCreator);
    if (event.target.closest('[data-ccp-close-invite]')) return elements.inviteDialog.close();
    if (event.target.closest('[data-ccp-close-review]')) return elements.reviewDialog.close();
    const review = event.target.closest('[data-review-application]');
    if (review) return openApplication(review.dataset.reviewApplication);
    const cancel = event.target.closest('[data-cancel-invitation]');
    if (cancel) {
      const reason = prompt('Why is this invitation being cancelled?');
      if (!reason) return;
      try {
        await apiPost({
          action:'cancel_invitation',
          invitation_id:cancel.dataset.cancelInvitation,
          expected_lock_version:Number(cancel.dataset.lock),
          reason,
          idempotency_key:uuid(),
        });
        announce('Invitation cancelled.');
        await Promise.all([loadDashboard(), loadTab()]);
      } catch (error) { announce(error.message, true); }
      return;
    }
    const participant = event.target.closest('[data-participant-action]');
    if (participant) {
      const reason = prompt(`Reason to ${participant.dataset.participantAction.replaceAll('_',' ')} this participant:`);
      if (!reason) return;
      try {
        await apiPost({
          action:'transition_participant',
          participant_id:participant.dataset.id,
          to_status:participant.dataset.participantAction,
          expected_lock_version:Number(participant.dataset.lock),
          reason,
          idempotency_key:uuid(),
        });
        announce('Participant updated.');
        await Promise.all([loadDashboard(), loadTab()]);
      } catch (error) { announce(error.message, true); }
      return;
    }
    const reviewAction = event.target.closest('[data-review-action]');
    if (reviewAction) {
      const form = new FormData(elements.reviewForm);
      try {
        await apiPost({
          action:'review_application',
          application_id:form.get('application_id'),
          expected_lock_version:Number(form.get('expected_lock_version')),
          review_action:reviewAction.dataset.reviewAction,
          reason:form.get('reason') || '',
          internal_note:form.get('internal_note') || '',
          idempotency_key:uuid(),
        });
        elements.reviewDialog.close();
        announce('Application review saved.');
        await Promise.all([loadDashboard(), loadTab()]);
      } catch (error) { announce(error.message, true); }
      return;
    }
    if (event.target.closest('[data-ccp-retry]')) loadTab();
    if (event.target.closest('[data-ccp-prev]') && state.page > 1) { state.page--; loadTab(); }
    if (event.target.closest('[data-ccp-next]') && state.page < state.pages) { state.page++; loadTab(); }
  });

  elements.filters.addEventListener('submit', (event) => {
    event.preventDefault();
    state.page = 1;
    loadTab();
  });

  elements.inviteForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(elements.inviteForm);
    try {
      await apiPost({
        action:'invite',
        campaign_id:form.get('campaign_id'),
        creator_profile_id:form.get('creator_profile_id'),
        response_deadline_at:form.get('response_deadline_at') || '',
        invitation_message:form.get('invitation_message') || '',
        internal_note:form.get('internal_note') || '',
        idempotency_key:uuid(),
      });
      elements.inviteDialog.close();
      elements.inviteForm.reset();
      announce('Creator invitation sent.');
      state.tab = 'invitations';
      root.querySelectorAll('[data-ccp-tab]').forEach((button) => button.classList.toggle('is-active', button.dataset.ccpTab === state.tab));
      statusOptions();
      await Promise.all([loadDashboard(), loadTab()]);
    } catch (error) { announce(error.message, true); }
  });

  (async () => {
    try {
      await loadDashboard();
      statusOptions();
      await loadTab();
    } catch (error) {
      showError(error);
    }
  })();
})();