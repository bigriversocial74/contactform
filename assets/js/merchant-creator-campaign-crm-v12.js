(() => {
  'use strict';
  const root = document.querySelector('[data-cccrm]');
  if (!root) return;
  const endpoint = '/api/merchant/creator-campaign-crm.php';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const label = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  const query = (params) => new URLSearchParams(Object.entries(params).filter(([,value]) => value !== '' && value !== null && value !== undefined)).toString();
  const state = {page:1,busy:false};
  const form = root.querySelector('[data-cccrm-filters]');
  const loading = root.querySelector('[data-cccrm-loading]');
  const error = root.querySelector('[data-cccrm-error]');
  const empty = root.querySelector('[data-cccrm-empty]');
  const content = root.querySelector('[data-cccrm-content]');
  const pagination = root.querySelector('[data-cccrm-pagination]');
  const live = root.querySelector('[data-cccrm-live]');

  async function apiGet(params) {
    const response = await fetch(`${endpoint}?${query(params)}`, {credentials:'same-origin',headers:{Accept:'application/json'}});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
    return payload.data || payload;
  }
  async function apiPost(body) {
    const response = await fetch(endpoint, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({...body,csrf_token:csrf})});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
    return payload.data || payload;
  }
  function initials(contact) {
    const base = String(contact.name || contact.email || 'C').trim().split(/\s+/).filter(Boolean);
    return ((base[0]?.[0] || 'C') + (base[1]?.[0] || '')).toUpperCase();
  }
  function renderMetrics(summary) {
    const projections = summary.projections || {};
    const items = [
      ['Contacts',summary.contacts,'Canonical CRM identities'],
      ['Creator partners',summary.creator_partners,'Campaign relationship'],
      ['Customer leads',summary.customer_contacts,'Lead and customer roles'],
      ['Claimants',summary.claimants,'Claim lifecycle'],
      ['Redeemers',summary.redeemers,'Verified redemption'],
      ['Projected',projections.completed,'Completed CRM events'],
      ['Needs review',(Number(projections.failed)||0)+(Number(projections.pending)||0),'Failed or pending'],
    ];
    root.querySelector('[data-cccrm-metrics]').innerHTML = items.map(([name,value,detail]) => `<article class="mg-cccrm-metric"><span>${esc(name)}</span><strong>${Number(value)||0}</strong><small>${esc(detail)}</small></article>`).join('');
  }
  function populateCampaigns(campaigns) {
    const select = root.querySelector('[data-cccrm-campaign]');
    const selected = select.value;
    select.innerHTML = '<option value="">All campaigns</option>' + (campaigns || []).map((campaign) => `<option value="${esc(campaign.public_id)}">${esc(campaign.title)} · ${esc(label(campaign.status))}</option>`).join('');
    select.value = selected;
  }
  function renderContacts(contacts) {
    if (!contacts.length) {
      empty.classList.remove('mg-hidden'); content.classList.add('mg-hidden'); return;
    }
    empty.classList.add('mg-hidden'); content.classList.remove('mg-hidden');
    root.querySelector('[data-cccrm-list]').innerHTML = contacts.map((contact) => `<tr>
      <td><div class="mg-cccrm-contact"><b class="mg-cccrm-avatar">${esc(initials(contact))}</b><div><strong>${esc(contact.name || 'Unnamed contact')}</strong><span>${esc(contact.email || contact.mention || 'Microgifter account')}</span></div></div></td>
      <td><span class="mg-cccrm-badge is-${esc(contact.relationship_type)}">${esc(label(contact.relationship_type))}</span><div class="mg-cccrm-cell"><span>${esc(label(contact.relationship_status))}</span></div></td>
      <td><div class="mg-cccrm-cell"><strong>${esc(contact.creator_campaign_title)}</strong><span>${esc(label(contact.creator_campaign_status))}</span></div></td>
      <td><div class="mg-cccrm-cell"><strong>${esc(label(contact.stage))}</strong><span>${esc(label(contact.status))} · score ${Number(contact.score)||0}</span></div></td>
      <td><div class="mg-cccrm-cell"><strong>${Number(contact.relationship_event_count)||0} events</strong><span>${esc(label(contact.relationship_last_event))} · ${esc(contact.relationship_last_at || '')}</span></div></td>
      <td><div class="mg-cccrm-row-actions"><a href="${esc(contact.profile_url)}">Customer profile</a><a href="${esc(contact.creator_campaign_url)}">Campaign</a><a href="/merchant-crm.php?q=${encodeURIComponent(contact.mention || contact.email || contact.name || '')}">CRM</a></div></td>
    </tr>`).join('');
  }
  function renderPagination(meta) {
    const pages = Number(meta.pages || 1); const page = Number(meta.page || 1);
    pagination.classList.toggle('mg-hidden', pages <= 1);
    pagination.querySelector('[data-cccrm-page-label]').textContent = `Page ${page} of ${pages} · ${Number(meta.total)||0} relationships`;
    pagination.querySelector('[data-cccrm-prev]').disabled = page <= 1;
    pagination.querySelector('[data-cccrm-next]').disabled = page >= pages;
  }
  function renderRuns(runs) {
    const target = root.querySelector('[data-cccrm-runs]');
    if (!runs.length) { target.innerHTML = '<p class="mg-cccrm-empty-copy">No reconciliation runs have been recorded.</p>'; return; }
    target.innerHTML = '<div class="mg-cccrm-run-list">' + runs.map((run) => `<article class="mg-cccrm-run"><div><strong>${esc(run.campaign_title || label(run.run_mode) + ' reconciliation')}</strong><span>${esc(label(run.status))} · ${esc(run.started_at || '')}</span></div><div><strong>${Number(run.participation_scanned)||0}</strong><small>Participation</small></div><div><strong>${Number(run.tracking_scanned)||0}</strong><small>Tracking</small></div><div><strong>${Number(run.projected_count)||0}</strong><small>Projected</small></div><div><strong>${Number(run.skipped_count)||0}</strong><small>Skipped</small></div><div><strong>${Number(run.failed_count)||0}</strong><small>Failed</small></div></article>`).join('') + '</div>';
  }
  async function loadRuns() {
    try { const data = await apiGet({action:'runs',limit:12}); renderRuns(data.runs || []); }
    catch (err) { root.querySelector('[data-cccrm-runs]').innerHTML = `<p class="mg-cccrm-empty-copy">${esc(err.message)}</p>`; }
  }
  async function load() {
    loading.classList.remove('mg-hidden'); error.classList.add('mg-hidden'); empty.classList.add('mg-hidden'); content.classList.add('mg-hidden');
    try {
      const filters = Object.fromEntries(new FormData(form).entries());
      const data = await apiGet({action:'list',page:state.page,limit:25,...filters});
      if (data.schema_ready === false) throw new Error('Phase 12 SQL has not been imported yet.');
      renderMetrics(data.summary || {}); populateCampaigns(data.campaigns || []); renderContacts(data.contacts || []); renderPagination(data.pagination || {});
      live.textContent = `${(data.contacts || []).length} Creator Campaign CRM relationship${(data.contacts || []).length === 1 ? '' : 's'} loaded.`;
    } catch (err) {
      error.classList.remove('mg-hidden'); error.querySelector('[data-cccrm-error-message]').textContent = err.message; live.textContent = '';
    } finally { loading.classList.add('mg-hidden'); }
  }
  async function sync() {
    if (state.busy) return;
    const button = root.querySelector('[data-cccrm-sync]'); state.busy = true; button.disabled = true; button.textContent = 'Reconciling…';
    try {
      const campaignId = root.querySelector('[data-cccrm-campaign]').value;
      const data = await apiPost({action:'sync',campaign_id:campaignId,limit:500});
      const result = data.reconciliation || {};
      live.textContent = `Reconciliation complete: ${Number(result.projected_count)||0} projected, ${Number(result.skipped_count)||0} skipped, ${Number(result.failed_count)||0} failed.`;
      await Promise.all([load(),loadRuns()]);
    } catch (err) { live.textContent = err.message; }
    finally { state.busy = false; button.disabled = false; button.textContent = 'Reconcile CRM'; }
  }
  form.addEventListener('submit',(event) => { event.preventDefault(); state.page=1; load(); });
  root.querySelector('[data-cccrm-prev]').addEventListener('click',() => { state.page=Math.max(1,state.page-1); load(); });
  root.querySelector('[data-cccrm-next]').addEventListener('click',() => { state.page+=1; load(); });
  root.querySelector('[data-cccrm-retry]').addEventListener('click',load);
  root.querySelector('[data-cccrm-sync]').addEventListener('click',sync);
  root.querySelector('[data-cccrm-refresh-runs]').addEventListener('click',loadRuns);
  load(); loadRuns();
})();
