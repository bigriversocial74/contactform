(() => {
  'use strict';

  const root = document.querySelector('[data-investment-closing]');
  if (!root) return;

  const notice = root.querySelector('[data-closing-notice]');
  const roundSelect = root.querySelector('[data-closing-round]');
  const drawerLayer = document.querySelector('[data-closing-drawer-layer]');
  const drawer = drawerLayer?.querySelector('.mg-closing-drawer');
  const drawerTitle = drawerLayer?.querySelector('[data-closing-drawer-title]');
  const drawerSubtitle = drawerLayer?.querySelector('[data-closing-drawer-subtitle]');
  const drawerBody = drawerLayer?.querySelector('[data-closing-drawer-body]');
  const permissions = {
    manage: root.dataset.canManage === '1',
    verify: root.dataset.canVerify === '1',
    compliance: root.dataset.canCompliance === '1',
    relations: root.dataset.canRelations === '1',
    ai: root.dataset.canAi === '1',
  };

  let dashboard = { rounds: [], records: [], batches: [], compliance: [], verifications: [], packets: [], reports: [], summary: {} };
  let relations = null;
  let selectedRound = '';
  let busy = false;

  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
  }[character]));
  const read = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
  const money = (cents) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(Number(cents || 0) / 100);
  const number = (value) => new Intl.NumberFormat().format(Number(value || 0));
  const dateTime = (value) => value ? new Date(String(value).replace(' ', 'T')).toLocaleString() : '—';
  const dateOnly = (value) => value ? String(value).slice(0, 10) : '';
  const dollars = (cents) => (Number(cents || 0) / 100).toFixed(2);
  const selected = (value, current) => String(value) === String(current) ? ' selected' : '';
  const checked = (value) => Number(value) === 1 || value === true ? ' checked' : '';
  const pill = (value) => `<span class="mg-closing-pill is-${esc(value)}">${esc(read(value))}</span>`;
  const round = () => dashboard.rounds.find((item) => item.public_id === selectedRound) || null;
  const filterRound = (items, key = 'round_public_id') => selectedRound ? items.filter((item) => item[key] === selectedRound) : items;

  const setNotice = (message = '', type = 'info') => {
    notice.textContent = message;
    notice.dataset.type = type;
  };

  const request = async (url, options = {}) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-Token': root.dataset.csrfToken || '' } : {}),
      },
      ...options,
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload.data;
  };

  const applyResult = (result) => {
    if (result?.dashboard) dashboard = result.dashboard;
    else if (result?.rounds) dashboard = result;
    if (result?.relations) relations = result.relations;
  };

  const post = async (payload, success = 'Saved.') => {
    if (busy) return null;
    busy = true;
    setNotice('Saving closing operation…');
    try {
      const result = await request('/api/admin/investment-closing.php', { method: 'POST', body: JSON.stringify(payload) });
      applyResult(result);
      render();
      setNotice(success, 'success');
      return result;
    } catch (error) {
      setNotice(error.message, 'error');
      throw error;
    } finally {
      busy = false;
    }
  };

  const loadRelations = async () => {
    if (!selectedRound) {
      relations = null;
      renderRelations();
      return;
    }
    try {
      relations = await request(`/api/admin/investment-closing.php?action=relations&round_id=${encodeURIComponent(selectedRound)}`);
      renderRelations();
    } catch (error) {
      setNotice(error.message, 'error');
    }
  };

  const load = async () => {
    setNotice('Loading Closing Command Center…');
    const query = selectedRound ? `?round_id=${encodeURIComponent(selectedRound)}` : '';
    try {
      dashboard = await request(`/api/admin/investment-closing.php${query}`);
      render();
      setNotice('');
      await loadRelations();
    } catch (error) {
      setNotice(error.message, 'error');
    }
  };

  const closeDrawer = () => {
    if (!drawerLayer) return;
    drawerLayer.hidden = true;
    drawerBody.innerHTML = '';
  };

  const openDrawer = (title, subtitle, html) => {
    if (!drawerLayer) return;
    drawerTitle.textContent = title;
    drawerSubtitle.textContent = subtitle || '';
    drawerBody.innerHTML = html;
    drawerLayer.hidden = false;
    drawer?.focus();
  };

  const formObject = (form) => {
    const values = Object.fromEntries(new FormData(form).entries());
    form.querySelectorAll('input[type="checkbox"]').forEach((input) => { values[input.name] = input.checked; });
    return values;
  };

  const renderRounds = () => {
    const current = selectedRound;
    roundSelect.innerHTML = `<option value="">All rounds</option>${dashboard.rounds.map((item) => `<option value="${esc(item.public_id)}"${selected(item.public_id, current)}>${esc(item.public_name)} · ${esc(read(item.status))}</option>`).join('')}`;
  };

  const stat = (label, value) => `<article class="mg-closing-stat"><span>${esc(label)}</span><strong>${esc(value)}</strong></article>`;
  const renderStats = () => {
    const summary = dashboard.summary || {};
    root.querySelector('[data-closing-stats]').innerHTML = [
      stat('Readiness', `${Number(summary.readiness_score || 0)}%`),
      stat('Closing investors', number(summary.investors)),
      stat('Verified funded', money(summary.verified_funded_cents)),
      stat('Pending verification', number(summary.pending_verifications)),
      stat('Open compliance', number(summary.open_compliance)),
      stat('Overdue compliance', number(summary.overdue_compliance)),
    ].join('');
  };

  const renderOverview = () => {
    const target = root.querySelector('[data-closing-overview]');
    const currentRound = round();
    if (!currentRound) {
      target.innerHTML = '<section class="mg-investment-panel mg-investment-empty"><h2>Select an official round.</h2><p>Closing readiness and blockers will appear here.</p></section>';
      return;
    }
    const summary = dashboard.summary || {};
    const score = Number(summary.readiness_score || currentRound.readiness_score || 0);
    const profileBlockers = [];
    if (currentRound.counsel_status !== 'approved') profileBlockers.push('Official round counsel status is not approved.');
    if (Number(summary.pending_verifications || 0) > 0) profileBlockers.push('Financial verification requests are pending.');
    if (Number(summary.overdue_compliance || 0) > 0) profileBlockers.push('Compliance deadlines are overdue.');
    if (Number(summary.awaiting_documents || 0) > 0) profileBlockers.push('Investors are still reviewing or assembling documents.');
    const signedProgress = currentRound.target_raise_cents ? Math.min(100, Math.round((Number(currentRound.signed_cents || 0) / Number(currentRound.target_raise_cents)) * 100)) : 0;
    const fundedProgress = currentRound.target_raise_cents ? Math.min(100, Math.round((Number(currentRound.funded_cents || 0) / Number(currentRound.target_raise_cents)) * 100)) : 0;
    target.innerHTML = `<section class="mg-investment-panel"><header><div><span>Deterministic readiness</span><h2>${esc(currentRound.public_name)}</h2><p>${esc(read(currentRound.closing_stage || 'planning'))} · ${esc(read(currentRound.instrument_type))}</p></div>${permissions.manage ? '<button class="mg-btn mg-btn-soft" type="button" data-edit-profile>Edit closing profile</button>' : ''}</header><div class="mg-closing-readiness"><div class="mg-closing-readiness-score" style="--readiness:${score}%"><strong>${score}%</strong></div><div class="mg-closing-readiness-list"><article><span>Signed progress</span><strong>${signedProgress}% · ${money(currentRound.signed_cents)}</strong></article><div class="mg-closing-progress"><span style="width:${signedProgress}%"></span></div><article><span>Funded progress</span><strong>${fundedProgress}% · ${money(currentRound.funded_cents)}</strong></article><div class="mg-closing-progress"><span style="width:${fundedProgress}%"></span></div><article><span>Target raise</span><strong>${money(currentRound.target_raise_cents)}</strong></article><article><span>Planned final close</span><strong>${dateTime(currentRound.planned_final_close_at)}</strong></article></div></div></section><section class="mg-investment-panel"><header><div><span>Current blockers</span><h2>Closing attention</h2></div></header><div class="mg-closing-blockers">${profileBlockers.map((item) => `<article>${esc(item)}</article>`).join('') || '<article style="border-color:#bbf7d0;background:#f0fdf4;color:#166534">No automatic blockers detected. Counsel and authorized administrators remain responsible for final review.</article>'}</div></section>`;
    target.querySelector('[data-edit-profile]')?.addEventListener('click', openProfileEditor);
  };

  const recordActions = (record) => permissions.manage ? `<div class="mg-closing-table-actions"><button class="mg-btn mg-btn-ghost" type="button" data-record-edit="${esc(record.public_id)}">Manage</button><button class="mg-btn mg-btn-soft" type="button" data-record-verify="${esc(record.public_id)}">Verify amount</button></div>` : '';
  const renderRecords = () => {
    const items = filterRound(dashboard.records || []);
    root.querySelector('[data-closing-record-list]').innerHTML = items.map((record) => `<tr><td><strong>${esc(record.display_name || record.full_name || record.email)}</strong><br><small>${esc(record.firm_name || record.email)}</small></td><td>${pill(record.status)}</td><td>${esc(read(record.instrument_type))}</td><td class="mg-closing-money">${money(record.signed_amount_cents)}</td><td class="mg-closing-money">${money(record.verified_funded_cents)}</td><td>${pill(record.kyc_status || 'not_started')}<br><small>${esc(read(record.accreditation_status || 'not_started'))}</small></td><td>${esc(record.batch_name || 'Unassigned')}</td><td>${Number(record.pending_verifications || 0) ? pill('pending') : '—'}</td><td>${recordActions(record)}</td></tr>`).join('') || '<tr><td colspan="9"><div class="mg-closing-empty">No closing records match this round.</div></td></tr>';
    root.querySelectorAll('[data-record-edit]').forEach((button) => button.onclick = () => openRecordEditor(items.find((record) => record.public_id === button.dataset.recordEdit)));
    root.querySelectorAll('[data-record-verify]').forEach((button) => button.onclick = () => openVerificationRequest(items.find((record) => record.public_id === button.dataset.recordVerify)));
  };

  const renderBatches = () => {
    const items = filterRound(dashboard.batches || []);
    root.querySelector('[data-closing-batch-list]').innerHTML = items.map((batch) => `<tr><td><strong>${esc(batch.batch_name)}</strong></td><td>${Number(batch.sequence_number)}</td><td>${pill(batch.status)}</td><td>${number(batch.investor_count)}</td><td class="mg-closing-money">${money(batch.included_amount_cents)}</td><td>${dateTime(batch.planned_close_at)}</td><td>${dateTime(batch.actual_close_at)}</td><td><small>Counsel: ${esc(read(batch.counsel_status))}<br>Board: ${esc(read(batch.board_status))}</small></td><td>${permissions.manage ? `<div class="mg-closing-table-actions"><button class="mg-btn mg-btn-ghost" data-batch-edit="${esc(batch.public_id)}">Edit</button>${batch.locked_at ? '<button class="mg-btn mg-btn-soft" data-batch-reopen="' + esc(batch.public_id) + '">Reopen</button>' : '<button class="mg-btn mg-btn-primary" data-batch-complete="' + esc(batch.public_id) + '">Complete</button>'}</div>` : ''}</td></tr>`).join('') || '<tr><td colspan="9"><div class="mg-closing-empty">No closing batches.</div></td></tr>';
    root.querySelectorAll('[data-batch-edit]').forEach((button) => button.onclick = () => openBatchEditor(items.find((batch) => batch.public_id === button.dataset.batchEdit)));
    root.querySelectorAll('[data-batch-complete]').forEach((button) => button.onclick = () => openBatchComplete(items.find((batch) => batch.public_id === button.dataset.batchComplete)));
    root.querySelectorAll('[data-batch-reopen]').forEach((button) => button.onclick = () => openBatchReopen(items.find((batch) => batch.public_id === button.dataset.batchReopen)));
  };

  const renderCompliance = () => {
    const items = filterRound(dashboard.compliance || []);
    root.querySelector('[data-compliance-list]').innerHTML = items.map((item) => `<tr><td><strong>${esc(item.title)}</strong><br><small>${esc(item.requirement_key)}</small></td><td>${esc(read(item.category))}</td><td>${pill(item.status)}</td><td>${Number(item.counsel_required) ? 'Required' : 'No'}</td><td>${dateTime(item.due_at)}</td><td>${esc(item.assigned_name || 'Unassigned')}</td><td>${item.external_url ? `<a href="${esc(item.external_url)}" target="_blank" rel="noopener">${esc(item.external_reference || 'Open')}</a>` : esc(item.external_reference || '—')}</td><td>${permissions.compliance ? `<button class="mg-btn mg-btn-ghost" data-compliance-edit="${esc(item.public_id)}">Edit</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="8"><div class="mg-closing-empty">No compliance requirements. Seed the standard checklist or add one.</div></td></tr>';
    root.querySelectorAll('[data-compliance-edit]').forEach((button) => button.onclick = () => openComplianceEditor(items.find((item) => item.public_id === button.dataset.complianceEdit)));
  };

  const renderVerifications = () => {
    const items = filterRound(dashboard.verifications || []);
    root.querySelector('[data-verification-list]').innerHTML = items.map((item) => `<tr><td><strong>${esc(item.display_name || item.full_name || item.email)}</strong></td><td>${esc(read(item.verification_type))}</td><td class="mg-closing-money">${money(item.previous_amount_cents)}</td><td class="mg-closing-money">${money(item.requested_amount_cents)}</td><td>${pill(item.status)}</td><td>${esc(item.submitted_by_name)}</td><td>${esc(item.reviewer_name || '—')}</td><td>${permissions.verify && item.status === 'pending' ? `<button class="mg-btn mg-btn-primary" data-verification-review="${esc(item.public_id)}">Review</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="8"><div class="mg-closing-empty">No financial verification requests.</div></td></tr>';
    root.querySelectorAll('[data-verification-review]').forEach((button) => button.onclick = () => openVerificationDecision(items.find((item) => item.public_id === button.dataset.verificationReview)));
  };

  const renderPackets = () => {
    const items = filterRound(dashboard.packets || []);
    root.querySelector('[data-packet-list]').innerHTML = items.map((packet) => `<tr><td><strong>${esc(packet.display_name || packet.full_name || packet.email)}</strong></td><td>${esc(packet.packet_name)}</td><td>${pill(packet.status)}</td><td>${number(packet.required_document_count)}</td><td>${number(packet.completed_document_count)}</td><td>${number(packet.document_count)}</td><td>${dateTime(packet.completed_at)}</td><td>${permissions.manage ? `<button class="mg-btn mg-btn-ghost" data-packet-edit="${esc(packet.public_id)}">Manage</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="8"><div class="mg-closing-empty">No closing document packets.</div></td></tr>';
    root.querySelectorAll('[data-packet-edit]').forEach((button) => button.onclick = () => openPacketEditor(items.find((packet) => packet.public_id === button.dataset.packetEdit)));
  };

  const renderRelations = () => {
    const reconTarget = root.querySelector('[data-reconciliation-list]');
    const periodTarget = root.querySelector('[data-report-period-list]');
    const actualTarget = root.querySelector('[data-use-actual-list]');
    if (!relations) {
      reconTarget.innerHTML = '<p>Select a round to load reconciliation history.</p>';
      periodTarget.innerHTML = '<p>Select a round to load reporting periods.</p>';
      actualTarget.innerHTML = '<p>Select a round to load use-of-funds actuals.</p>';
      renderAi();
      return;
    }
    reconTarget.innerHTML = `<div class="mg-closing-recon">${(relations.reconciliation || []).map((item) => `<article><header><strong>${esc(read(item.snapshot_type))}</strong><small>${dateTime(item.created_at)}</small></header><dl><div><dt>Signed</dt><dd>${money(item.signed_cents)}</dd></div><div><dt>Verified funded</dt><dd>${money(item.verified_funded_cents)}</dd></div><div><dt>Available capacity</dt><dd>${money(item.available_capacity_cents)}</dd></div><div><dt>Estimated dilution</dt><dd>${(Number(item.actual_estimated_dilution_bps || 0) / 100).toFixed(2)}%</dd></div></dl></article>`).join('') || '<p>No reconciliation snapshots.</p>'}</div>`;
    periodTarget.innerHTML = (relations.periods || []).map((period) => `<article class="mg-closing-report"><header><div><h3>${esc(period.period_name)}</h3><p>${esc(read(period.period_type))} · ${dateOnly(period.starts_at)} to ${dateOnly(period.ends_at)}</p></div>${pill(period.status)}</header><footer>${permissions.relations ? `<button class="mg-btn mg-btn-ghost" data-period-edit="${esc(period.public_id)}">Edit</button><button class="mg-btn mg-btn-primary" data-period-snapshot="${esc(period.public_id)}">New report version</button>` : ''}</footer></article>`).join('') || '<p>No reporting periods.</p>';
    actualTarget.innerHTML = (relations.use_of_funds_actuals || []).map((actual) => `<article class="mg-closing-report"><header><div><h3>${esc(actual.budget_category)}</h3><p>${dateOnly(actual.spent_at)} · ${esc(actual.description)}</p></div><strong>${money(actual.amount_cents)}</strong></header><footer><span class="mg-closing-pill">${Number(actual.investor_visible) ? 'Investor visible' : 'Internal'}</span>${permissions.relations ? `<button class="mg-btn mg-btn-ghost" data-actual-edit="${esc(actual.public_id)}">Edit</button>` : ''}</footer></article>`).join('') || '<p>No use-of-funds actuals.</p>';
    root.querySelectorAll('[data-period-edit]').forEach((button) => button.onclick = () => openPeriodEditor((relations.periods || []).find((period) => period.public_id === button.dataset.periodEdit)));
    root.querySelectorAll('[data-period-snapshot]').forEach((button) => button.onclick = () => openSnapshotEditor((relations.periods || []).find((period) => period.public_id === button.dataset.periodSnapshot)));
    root.querySelectorAll('[data-actual-edit]').forEach((button) => button.onclick = () => openActualEditor((relations.use_of_funds_actuals || []).find((actual) => actual.public_id === button.dataset.actualEdit)));
    renderAi();
  };

  const renderAi = () => {
    const target = root.querySelector('[data-closing-ai]');
    if (!permissions.ai) {
      target.innerHTML = '<p>Claude closing actions require the investment AI permission.</p>';
      return;
    }
    target.innerHTML = `<div class="mg-closing-ai-controls"><select data-ai-type>${['closing_readiness','missing_documents','investor_closing_briefing','compliance_deadlines','closing_instructions','closing_announcement','post_close_update','scenario_actual','use_of_funds_variance'].map((value) => `<option value="${value}">${esc(read(value))}</option>`).join('')}</select><textarea rows="5" data-ai-instruction placeholder="Optional drafting instructions"></textarea><button class="mg-btn mg-btn-primary" type="button" data-ai-run ${selectedRound ? '' : 'disabled'}>Create internal draft</button><div class="mg-closing-ai-result" data-ai-result hidden></div></div>`;
    target.querySelector('[data-ai-run]')?.addEventListener('click', async () => {
      const result = await post({ action: 'ai_draft', round_id: selectedRound, draft_type: target.querySelector('[data-ai-type]').value, instruction: target.querySelector('[data-ai-instruction]').value }, 'Claude draft created.');
      const output = target.querySelector('[data-ai-result]');
      output.hidden = false;
      output.textContent = result?.analysis?.response_text || result?.analysis?.error_message || 'No draft returned.';
    });
  };

  const render = () => {
    renderRounds();
    renderStats();
    renderOverview();
    renderRecords();
    renderBatches();
    renderCompliance();
    renderVerifications();
    renderPackets();
    renderRelations();
  };

  const roundRequired = () => {
    if (!selectedRound) {
      setNotice('Select an official round first.', 'error');
      return false;
    }
    return true;
  };

  const batchOptions = (current = '') => `<option value="">Unassigned</option>${filterRound(dashboard.batches || []).filter((batch) => !batch.locked_at).map((batch) => `<option value="${esc(batch.public_id)}"${selected(batch.public_id, current)}>${esc(batch.batch_name)}</option>`).join('')}`;
  const investorOptions = (current = '') => filterRound(dashboard.records || []).map((record) => `<option value="${Number(record.investor_user_id)}"${selected(record.investor_user_id, current)}>${esc(record.display_name || record.full_name || record.email)}</option>`).join('');
  const adminOptions = (current = '') => `<option value="">Unassigned</option>${(dashboard.admins || []).map((admin) => `<option value="${Number(admin.id)}"${selected(admin.id, current)}>${esc(admin.full_name || admin.email)}</option>`).join('')}`;

  const openProfileEditor = () => {
    if (!roundRequired()) return;
    const current = round();
    openDrawer('Closing profile', current.public_name, `<form class="mg-closing-form" data-profile-form><label><span>Closing stage</span><select name="stage">${['planning','pre_closing_review','documents_ready','investor_signing','funding_pending','rolling_close','final_close','post_close_review','complete','paused','cancelled'].map((value) => `<option value="${value}"${selected(value, current.closing_stage || 'planning')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Readiness score</span><input name="readiness_score" type="number" min="0" max="100" value="${Number(current.readiness_score || 0)}"></label><label><span>Counsel status</span><select name="counsel_status">${['not_started','requested','in_review','approved','changes_required','not_applicable'].map((value) => `<option value="${value}"${selected(value, current.counsel_status === 'approved' ? 'approved' : 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Board status</span><select name="board_status">${['not_started','requested','in_review','approved','changes_required','not_applicable'].map((value) => `<option value="${value}">${esc(read(value))}</option>`).join('')}</select></label><label><span>Planned first close</span><input name="planned_first_close_at" type="datetime-local" value="${esc(current.planned_first_close_at ? String(current.planned_first_close_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label><span>Planned final close</span><input name="planned_final_close_at" type="datetime-local" value="${esc(current.planned_final_close_at ? String(current.planned_final_close_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label class="is-wide"><span>Blockers, one per line</span><textarea name="blockers" rows="5"></textarea></label><label class="is-wide"><span>Closing notes</span><textarea name="closing_notes" rows="6"></textarea></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save closing profile</button></div></form>`);
    drawerBody.querySelector('[data-profile-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_profile'; payload.round_id = selectedRound; payload.blockers = String(payload.blockers || '').split('\n').map((value) => value.trim()).filter(Boolean); await post(payload); closeDrawer(); };
  };

  const openRecordEditor = (record) => {
    if (!record) return;
    openDrawer('Investor closing', record.display_name || record.full_name || record.email, `<form class="mg-closing-form" data-record-form><label><span>Status</span><select name="status">${['interested','soft_committed','documents_requested','documents_sent','investor_reviewing','signed','funding_pending','funds_reported','funds_verified','included_in_closing','closing_complete','withdrawn','declined'].map((value) => `<option value="${value}"${selected(value, record.status)}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Instrument</span><select name="instrument_type">${['not_finalized','post_money_safe','convertible_note','priced_equity','other'].map((value) => `<option value="${value}"${selected(value, record.instrument_type)}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Proposed amount</span><input name="proposed_amount" inputmode="decimal" value="${dollars(record.proposed_amount_cents)}"></label><label><span>Final administrative amount</span><input name="final_amount" inputmode="decimal" value="${dollars(record.final_amount_cents)}"></label><label><span>Closing batch</span><select name="batch_id">${batchOptions(record.batch_public_id)}</select></label><label><span>Agreement reference</span><input name="agreement_reference" maxlength="220" value="${esc(record.agreement_reference || '')}"></label><label><span>Funding reference</span><input name="funding_reference" maxlength="220" value="${esc(record.funding_reference || '')}"></label><label><span>Documents sent</span><input name="documents_sent_at" type="datetime-local" value="${esc(record.documents_sent_at ? String(record.documents_sent_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label><span>Investor signed</span><input name="investor_signed_at" type="datetime-local" value="${esc(record.investor_signed_at ? String(record.investor_signed_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label><span>Company countersigned</span><input name="company_countersigned_at" type="datetime-local" value="${esc(record.company_countersigned_at ? String(record.company_countersigned_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label class="is-wide"><span>Internal notes</span><textarea name="internal_notes" rows="5">${esc(record.internal_notes || '')}</textarea></label><label class="is-wide"><span>Status-change reason</span><input name="change_reason" maxlength="500"></label><div class="mg-closing-warning">Signed and verified funded amounts are read-only here. Use maker/checker verification to change official money totals.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save closing record</button></div></form><hr><form class="mg-closing-form" data-onboarding-form><h3 class="is-wide">External onboarding review</h3><label><span>Entity type</span><select name="investor_entity_type">${['individual','entity','trust','fund','other'].map((value) => `<option value="${value}">${esc(read(value))}</option>`).join('')}</select></label><label><span>Legal name</span><input name="legal_name" maxlength="220" required value="${esc(record.full_name || record.display_name || '')}"></label><label><span>Organization</span><input name="organization_name" maxlength="220" value="${esc(record.firm_name || '')}"></label><label><span>Authorized signatory</span><input name="authorized_signatory" maxlength="220"></label><label><span>Tax country</span><input name="tax_country" maxlength="120"></label><label><span>Tax documents</span><select name="tax_document_status">${['not_requested','requested','received','reviewed','expired','not_applicable'].map((value) => `<option value="${value}">${esc(read(value))}</option>`).join('')}</select></label><label><span>Beneficial owner review</span><select name="beneficial_owner_status">${['not_requested','requested','received','reviewed','issues_found','not_applicable'].map((value) => `<option value="${value}">${esc(read(value))}</option>`).join('')}</select></label><label><span>External KYC status</span><select name="kyc_status">${['not_started','submitted_external','pending_external','passed_external','failed_external','expired','not_applicable'].map((value) => `<option value="${value}"${selected(value, record.kyc_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>KYC provider reference</span><input name="kyc_provider_reference" maxlength="220"></label><label><span>External accreditation status</span><select name="accreditation_status">${['not_started','submitted_external','pending_external','verified_external','not_verified','expired','not_required'].map((value) => `<option value="${value}"${selected(value, record.accreditation_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Accreditation provider</span><input name="accreditation_provider" maxlength="220"></label><label><span>Counsel status</span><select name="counsel_status">${['not_started','in_review','approved','changes_required','not_applicable'].map((value) => `<option value="${value}"${selected(value, record.onboarding_counsel_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label class="is-wide"><span>Restrictions or review notes</span><textarea name="restriction_notes" rows="4"></textarea></label><div class="mg-closing-warning">Microgifter records statuses supplied by external providers or counsel. It does not perform KYC, AML, beneficial-owner, or accreditation verification.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-soft" type="submit">Save onboarding review</button></div></form>`);
    drawerBody.querySelector('[data-record-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_record'; payload.record_id = record.public_id; await post(payload); closeDrawer(); };
    drawerBody.querySelector('[data-onboarding-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_onboarding'; payload.round_id = record.round_public_id; payload.investor_user_id = record.investor_user_id; await post(payload, 'Onboarding review saved.'); closeDrawer(); };
  };

  const openVerificationRequest = (record) => {
    if (!record) return;
    openDrawer('Request financial verification', record.display_name || record.full_name || record.email, `<form class="mg-closing-form" data-verification-request-form><label><span>Verification type</span><select name="verification_type"><option value="signed_amount">Signed amount</option><option value="funded_amount">Funded amount</option><option value="signed_reversal">Signed reversal or correction</option><option value="funded_reversal">Funded reversal or correction</option></select></label><label><span>Requested resulting amount</span><input name="requested_amount" inputmode="decimal" required value="${dollars(record.verified_funded_cents || record.signed_amount_cents)}"></label><label class="is-wide"><span>Evidence reference</span><input name="evidence_reference" maxlength="220" placeholder="Bank receipt, countersigned document, provider reference…"></label><label class="is-wide"><span>Reason and supporting facts</span><textarea name="request_reason" rows="6" minlength="10" required></textarea></label><div class="mg-closing-warning">A different authorized administrator must review this request before official round totals change.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Submit verification request</button></div></form>`);
    drawerBody.querySelector('[data-verification-request-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'request_verification'; payload.record_id = record.public_id; await post(payload, 'Financial verification requested.'); closeDrawer(); };
  };

  const openVerificationDecision = (item) => {
    if (!item) return;
    openDrawer('Review financial verification', item.display_name || item.full_name || item.email, `<form class="mg-closing-form" data-verification-decision-form><div class="is-wide mg-closing-card"><p><strong>${esc(read(item.verification_type))}</strong></p><p>${money(item.previous_amount_cents)} → ${money(item.requested_amount_cents)}</p><p>${esc(item.request_reason)}</p><p>Evidence: ${esc(item.evidence_reference || 'None')}</p></div><label><span>Decision</span><select name="decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select></label><label class="is-wide"><span>Decision notes</span><textarea name="decision_notes" rows="6" minlength="10" required></textarea></label><div class="mg-closing-danger">Approval updates canonical investor-round and official round totals. Confirm the external evidence before approving.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Record decision</button></div></form>`);
    drawerBody.querySelector('[data-verification-decision-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'decide_verification'; payload.request_id = item.public_id; await post(payload, 'Financial verification resolved.'); closeDrawer(); };
  };

  const openBatchEditor = (batch = null) => {
    if (!roundRequired()) return;
    openDrawer(batch ? 'Edit closing batch' : 'Create closing batch', round().public_name, `<form class="mg-closing-form" data-batch-form><label><span>Batch name</span><input name="batch_name" maxlength="180" required value="${esc(batch?.batch_name || '')}"></label><label><span>Sequence</span><input name="sequence_number" type="number" min="1" value="${Number(batch?.sequence_number || 1)}"></label><label><span>Status</span><select name="status">${['planning','review','ready','closing','reopened','cancelled'].map((value) => `<option value="${value}"${selected(value, batch?.status || 'planning')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Planned close</span><input name="planned_close_at" type="datetime-local" value="${esc(batch?.planned_close_at ? String(batch.planned_close_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label><span>Counsel status</span><select name="counsel_status">${['not_started','in_review','approved','changes_required','not_applicable'].map((value) => `<option value="${value}"${selected(value, batch?.counsel_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Board status</span><select name="board_status">${['not_started','in_review','approved','changes_required','not_applicable'].map((value) => `<option value="${value}"${selected(value, batch?.board_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label class="is-wide"><span>Notes</span><textarea name="notes" rows="5">${esc(batch?.notes || '')}</textarea></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save batch</button></div></form>${batch ? `<hr><form class="mg-closing-form" data-batch-assign-form><h3 class="is-wide">Assign investor</h3><label><span>Investor</span><select name="record_id">${filterRound(dashboard.records || []).map((record) => `<option value="${esc(record.public_id)}">${esc(record.display_name || record.full_name || record.email)} · ${money(record.verified_funded_cents)}</option>`).join('')}</select></label><label><span>Included amount</span><input name="included_amount" inputmode="decimal"></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-soft" type="submit">Assign to batch</button></div></form>` : ''}`);
    drawerBody.querySelector('[data-batch-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_batch'; payload.round_id = selectedRound; if (batch) payload.batch_id = batch.public_id; await post(payload); closeDrawer(); };
    drawerBody.querySelector('[data-batch-assign-form]')?.addEventListener('submit', async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'assign_batch'; payload.batch_id = batch.public_id; await post(payload, 'Investor assigned to closing batch.'); closeDrawer(); });
  };

  const openBatchComplete = (batch) => {
    openDrawer('Complete closing batch', batch.batch_name, `<form class="mg-closing-form" data-batch-complete-form><div class="mg-closing-danger">Completing the batch locks it, records the actual close date, and marks included investors closing complete. Counsel and board must be approved, funded amounts verified, and document packets complete.</div><label class="is-wide"><span>Completion reason and evidence</span><textarea name="completion_reason" rows="6" minlength="10" required></textarea></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Complete and lock batch</button></div></form>`);
    drawerBody.querySelector('[data-batch-complete-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'complete_batch'; payload.batch_id = batch.public_id; await post(payload, 'Closing batch completed and locked.'); closeDrawer(); };
  };

  const openBatchReopen = (batch) => {
    openDrawer('Reopen completed batch', batch.batch_name, `<form class="mg-closing-form" data-batch-reopen-form><div class="mg-closing-danger">Reopening a completed batch is restricted to Super Admin and creates an immutable audit event.</div><label class="is-wide"><span>Reopen reason</span><textarea name="reason" rows="6" minlength="10" required></textarea></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Reopen batch</button></div></form>`);
    drawerBody.querySelector('[data-batch-reopen-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'reopen_batch'; payload.batch_id = batch.public_id; await post(payload, 'Closing batch reopened.'); closeDrawer(); };
  };

  const openComplianceEditor = (item = null) => {
    if (!roundRequired()) return;
    openDrawer(item ? 'Edit compliance requirement' : 'Add compliance requirement', round().public_name, `<form class="mg-closing-form" data-compliance-form><label><span>Requirement key</span><input name="requirement_key" maxlength="120" required value="${esc(item?.requirement_key || '')}" ${item ? 'readonly' : ''}></label><label><span>Category</span><select name="category">${['exemption','federal_filing','state_notice','board_approval','counsel_review','investor_notice','tax','other'].map((value) => `<option value="${value}"${selected(value, item?.category || 'other')}>${esc(read(value))}</option>`).join('')}</select></label><label class="is-wide"><span>Title</span><input name="title" maxlength="220" required value="${esc(item?.title || '')}"></label><label class="is-wide"><span>Description</span><textarea name="description" rows="4">${esc(item?.description || '')}</textarea></label><label><span>Status</span><select name="status">${['not_started','requested','in_progress','filed','confirmed','approved','changes_required','not_applicable','overdue'].map((value) => `<option value="${value}"${selected(value, item?.status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Due date</span><input name="due_at" type="datetime-local" value="${esc(item?.due_at ? String(item.due_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label><span>Assigned administrator</span><select name="assigned_user_id">${adminOptions(item?.assigned_user_id)}</select></label><label class="mg-closing-check"><input type="checkbox" name="counsel_required"${checked(item?.counsel_required)}><span>Counsel review required</span></label><label><span>External reference</span><input name="external_reference" maxlength="220" value="${esc(item?.external_reference || '')}"></label><label><span>External URL</span><input name="external_url" type="url" value="${esc(item?.external_url || '')}"></label><label class="is-wide"><span>Notes</span><textarea name="notes" rows="5">${esc(item?.notes || '')}</textarea></label><div class="mg-closing-warning">Record only information supplied or approved by counsel or authorized providers. This system does not determine legal eligibility or file notices.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save requirement</button></div></form>`);
    drawerBody.querySelector('[data-compliance-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_compliance'; payload.round_id = selectedRound; if (item) payload.requirement_id = item.public_id; await post(payload); closeDrawer(); };
  };

  const openPacketEditor = (packet = null) => {
    if (!roundRequired()) return;
    const packetDocuments = (relations?.documents || []).filter((document) => document.packet_public_id === packet?.public_id);
    openDrawer(packet ? 'Manage closing packet' : 'Create closing packet', packet ? (packet.display_name || packet.full_name || packet.email) : round().public_name, `<form class="mg-closing-form" data-packet-form><label><span>Investor</span><select name="investor_user_id">${investorOptions(packet?.investor_user_id)}</select></label><label><span>Packet name</span><input name="packet_name" maxlength="220" required value="${esc(packet?.packet_name || 'Closing Packet')}"></label><label><span>Status</span><select name="status">${['draft','assembling','investor_review','company_review','counsel_review','complete','archived'].map((value) => `<option value="${value}"${selected(value, packet?.status || 'draft')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>External packet reference</span><input name="external_packet_reference" maxlength="220" value="${esc(packet?.external_packet_reference || '')}"></label><label class="is-wide"><span>Notes</span><textarea name="notes" rows="4">${esc(packet?.notes || '')}</textarea></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save packet</button></div></form>${packet ? `<hr><div class="mg-closing-card-list">${packetDocuments.map((document) => `<article class="mg-closing-card"><header><div><h3>${esc(document.title)}</h3><p>${esc(read(document.document_type))} · Version ${Number(document.version_number)}</p></div>${pill(document.status)}</header><footer><button class="mg-btn mg-btn-ghost" type="button" data-document-edit="${esc(document.public_id)}">Edit</button></footer></article>`).join('') || '<p>No documents in this packet.</p>'}</div><button class="mg-btn mg-btn-soft" type="button" data-document-create>Add document</button>` : ''}`);
    drawerBody.querySelector('[data-packet-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_packet'; payload.round_id = selectedRound; if (packet) payload.packet_id = packet.public_id; await post(payload); closeDrawer(); await loadRelations(); };
    drawerBody.querySelector('[data-document-create]')?.addEventListener('click', () => openDocumentEditor(packet, null));
    drawerBody.querySelectorAll('[data-document-edit]').forEach((button) => button.onclick = () => openDocumentEditor(packet, packetDocuments.find((document) => document.public_id === button.dataset.documentEdit)));
  };

  const openDocumentEditor = (packet, document = null) => {
    openDrawer(document ? 'Edit closing document' : 'Add closing document', packet.packet_name, `<form class="mg-closing-form" data-document-form><label><span>Document type</span><select name="document_type">${['investor_questionnaire','subscription_agreement','safe_or_note','accreditation_evidence','tax_form','side_letter','board_consent','countersigned_agreement','funding_confirmation','closing_certificate','form_d_receipt','state_notice_receipt','other'].map((value) => `<option value="${value}"${selected(value, document?.document_type || 'other')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Title</span><input name="title" maxlength="220" required value="${esc(document?.title || '')}"></label><label><span>Status</span><select name="status">${['not_started','requested','received','review','approved','executed','complete','rejected','expired','not_applicable'].map((value) => `<option value="${value}"${selected(value, document?.status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Version</span><input name="version_number" type="number" min="1" value="${Number(document?.version_number || 1)}"></label><label><span>Investor signature</span><select name="investor_signature_status">${['not_required','not_started','sent','signed','declined'].map((value) => `<option value="${value}"${selected(value, document?.investor_signature_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Company signature</span><select name="company_signature_status">${['not_required','not_started','signed'].map((value) => `<option value="${value}"${selected(value, document?.company_signature_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Counsel status</span><select name="counsel_status">${['not_required','not_started','in_review','approved','changes_required'].map((value) => `<option value="${value}"${selected(value, document?.counsel_status || 'not_started')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Expires</span><input name="expires_at" type="datetime-local" value="${esc(document?.expires_at ? String(document.expires_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label class="is-wide"><span>Approved external URL</span><input name="external_url" type="url" value="${esc(document?.external_url || '')}"></label><label class="mg-closing-check is-wide"><input type="checkbox" name="required_document"${checked(document ? document.required_document : 1)}><span>Required closing document</span></label><label class="is-wide"><span>Notes</span><textarea name="notes" rows="5">${esc(document?.notes || '')}</textarea></label><div class="mg-closing-warning">Electronic signing is external. This record tracks approved external references and reported signature states only.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save document</button></div></form>`);
    drawerBody.querySelector('[data-document-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_document'; payload.packet_id = packet.public_id; if (document) payload.document_id = document.public_id; await post(payload, 'Closing document saved.'); closeDrawer(); await loadRelations(); };
  };

  const openReconciliation = () => {
    if (!roundRequired()) return;
    openDrawer('Create reconciliation snapshot', round().public_name, `<form class="mg-closing-form" data-reconciliation-form><label><span>Snapshot type</span><select name="snapshot_type">${['manual','pre_close','rolling_close','final_close','post_close'].map((value) => `<option value="${value}">${esc(read(value))}</option>`).join('')}</select></label><label><span>Source scenario public ID</span><input name="source_scenario_public_id" maxlength="36"></label><div class="mg-closing-warning">The output is an administrative estimate and does not replace counsel, accountant, cap-table software, or the official stock ledger.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Create snapshot</button></div></form>`);
    drawerBody.querySelector('[data-reconciliation-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'create_reconciliation'; payload.round_id = selectedRound; await post(payload, 'Reconciliation snapshot created.'); closeDrawer(); await loadRelations(); };
  };

  const openPeriodEditor = (period = null) => {
    if (!roundRequired()) return;
    openDrawer(period ? 'Edit reporting period' : 'Create reporting period', round().public_name, `<form class="mg-closing-form" data-period-form><label><span>Period name</span><input name="period_name" maxlength="180" required value="${esc(period?.period_name || '')}"></label><label><span>Period type</span><select name="period_type">${['monthly','quarterly','annual','milestone','closing','other'].map((value) => `<option value="${value}"${selected(value, period?.period_type || 'quarterly')}>${esc(read(value))}</option>`).join('')}</select></label><label><span>Starts</span><input name="starts_at" type="date" required value="${dateOnly(period?.starts_at)}"></label><label><span>Ends</span><input name="ends_at" type="date" required value="${dateOnly(period?.ends_at)}"></label><label><span>Due</span><input name="due_at" type="datetime-local" value="${esc(period?.due_at ? String(period.due_at).replace(' ', 'T').slice(0, 16) : '')}"></label><label><span>Status</span><select name="status">${['planning','collecting','draft','internal_review','approved','published','archived'].map((value) => `<option value="${value}"${selected(value, period?.status || 'planning')}>${esc(read(value))}</option>`).join('')}</select></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save reporting period</button></div></form>`);
    drawerBody.querySelector('[data-period-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_period'; payload.round_id = selectedRound; if (period) payload.period_id = period.public_id; await post(payload); closeDrawer(); await loadRelations(); };
  };

  const openSnapshotEditor = (period) => {
    openDrawer('Create report version', period.period_name, `<form class="mg-closing-form" data-snapshot-form><label class="is-wide"><span>Headline</span><input name="headline" maxlength="220" required></label><label class="is-wide"><span>Narrative</span><textarea name="narrative" rows="8"></textarea></label><label class="is-wide"><span>Metrics JSON</span><textarea name="metrics_json" rows="5" placeholder='[{"label":"MRR","value":"..."}]'>[]</textarea></label><label class="is-wide"><span>Use of funds JSON</span><textarea name="use_of_funds_json" rows="5">[]</textarea></label><label class="is-wide"><span>Milestones JSON</span><textarea name="milestones_json" rows="5">[]</textarea></label><label class="is-wide"><span>Risks JSON</span><textarea name="risks_json" rows="5">[]</textarea></label><label><span>Status</span><select name="status"><option value="draft">Draft</option><option value="internal_review">Internal Review</option><option value="approved">Approved</option><option value="published">Published</option></select></label><div class="mg-closing-warning">Publishing creates a new immutable version and supersedes any previously published version for this reporting period.</div><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save report version</button></div></form>`);
    drawerBody.querySelector('[data-snapshot-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); const parse = (name) => { try { return JSON.parse(payload[name] || '[]'); } catch { throw new Error(`${read(name)} must contain valid JSON.`); } }; try { payload.metrics = parse('metrics_json'); payload.use_of_funds = parse('use_of_funds_json'); payload.milestones = parse('milestones_json'); payload.risks = parse('risks_json'); } catch (error) { setNotice(error.message, 'error'); return; } payload.action = 'save_snapshot'; payload.period_id = period.public_id; await post(payload, 'Investor report version saved.'); closeDrawer(); await loadRelations(); };
  };

  const openActualEditor = (actual = null) => {
    if (!roundRequired()) return;
    openDrawer(actual ? 'Edit use-of-funds actual' : 'Add use-of-funds actual', round().public_name, `<form class="mg-closing-form" data-actual-form><label><span>Budget category</span><input name="budget_category" maxlength="180" required value="${esc(actual?.budget_category || '')}"></label><label><span>Amount</span><input name="amount" inputmode="decimal" required value="${actual ? dollars(actual.amount_cents) : ''}"></label><label><span>Spent date</span><input name="spent_at" type="date" required value="${dateOnly(actual?.spent_at)}"></label><label><span>Reporting period</span><select name="period_id"><option value="">No period</option>${(relations?.periods || []).map((period) => `<option value="${esc(period.public_id)}"${selected(period.public_id, actual?.period_public_id)}>${esc(period.period_name)}</option>`).join('')}</select></label><label class="is-wide"><span>Description</span><textarea name="description" rows="5" minlength="5" required>${esc(actual?.description || '')}</textarea></label><label><span>Evidence reference</span><input name="evidence_reference" maxlength="220" value="${esc(actual?.evidence_reference || '')}"></label><label class="mg-closing-check"><input type="checkbox" name="investor_visible"${checked(actual?.investor_visible)}><span>Show funded investors</span></label><div class="mg-closing-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Save actual</button></div></form>`);
    drawerBody.querySelector('[data-actual-form]').onsubmit = async (event) => { event.preventDefault(); const payload = formObject(event.currentTarget); payload.action = 'save_actual'; payload.round_id = selectedRound; if (actual) payload.actual_id = actual.public_id; await post(payload); closeDrawer(); await loadRelations(); };
  };

  root.querySelectorAll('[data-closing-tab]').forEach((button) => button.addEventListener('click', () => {
    root.querySelectorAll('[data-closing-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
    root.querySelectorAll('[data-closing-panel]').forEach((panel) => { panel.hidden = panel.dataset.closingPanel !== button.dataset.closingTab; });
  }));
  document.querySelectorAll('[data-closing-close]').forEach((button) => button.addEventListener('click', closeDrawer));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawerLayer && !drawerLayer.hidden) closeDrawer(); });
  roundSelect.addEventListener('change', async () => { selectedRound = roundSelect.value; await load(); });
  root.querySelector('[data-closing-refresh]').addEventListener('click', load);
  root.querySelector('[data-closing-sync]')?.addEventListener('click', () => post({ action: 'sync', round_id: selectedRound }, 'Closing records synchronized.'));
  root.querySelector('[data-refresh-readiness]')?.addEventListener('click', () => roundRequired() && post({ action: 'refresh_readiness', round_id: selectedRound }, 'Closing readiness recalculated.'));
  root.querySelector('[data-create-batch]')?.addEventListener('click', () => openBatchEditor());
  root.querySelector('[data-seed-compliance]')?.addEventListener('click', () => roundRequired() && post({ action: 'seed_compliance', round_id: selectedRound }, 'Standard compliance checklist seeded.'));
  root.querySelector('[data-create-compliance]')?.addEventListener('click', () => openComplianceEditor());
  root.querySelector('[data-create-packet]')?.addEventListener('click', () => openPacketEditor());
  root.querySelector('[data-create-reconciliation]')?.addEventListener('click', openReconciliation);
  root.querySelector('[data-create-period]')?.addEventListener('click', () => openPeriodEditor());
  root.querySelector('[data-create-actual]')?.addEventListener('click', () => openActualEditor());

  load();
})();
