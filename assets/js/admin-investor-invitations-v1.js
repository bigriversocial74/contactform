(() => {
  'use strict';
  const root = document.querySelector('[data-admin-investor-invitations]');
  if (!root) return;
  const canManage = root.dataset.canManage === '1';
  const schemaReady = root.dataset.schemaReady === '1';
  const createForm = root.querySelector('[data-invitation-create-form]');
  const createNotice = root.querySelector('[data-invitation-create-notice]');
  const createButton = root.querySelector('[data-invitation-create]');
  const roundSelect = root.querySelector('[data-invitation-round]');
  const filter = root.querySelector('[data-invitations-filter]');
  const list = root.querySelector('[data-invitations-list]');
  const summary = root.querySelector('[data-invitations-summary]');
  const notice = root.querySelector('[data-invitations-notice]');
  const share = root.querySelector('[data-invitation-share]');
  const shareUrl = root.querySelector('[data-invitation-share-url]');
  const shareStatus = root.querySelector('[data-invitation-share-status]');
  let items = [];
  let roundsLoaded = false;

  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const read = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  const dt = (value) => value ? new Date(String(value).replace(' ', 'T')).toLocaleString() : '—';
  const set = (target, message = '', type = 'info') => { target.textContent = message; target.dataset.type = type; };
  const request = async (url, options = {}) => {
    const response = await fetch(url, { credentials:'same-origin', headers:{Accept:'application/json', ...(options.body ? {'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrfToken || ''} : {})}, ...options });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload.data;
  };
  const actionButtons = (item) => {
    if (!canManage) return '';
    if (item.status === 'accepted') return '<span class="mg-invite-locked">In review workflow</span>';
    if (item.status === 'revoked') return '<span class="mg-invite-locked">Revoked</span>';
    return `<div class="mg-invite-row-actions"><button class="mg-btn mg-btn-soft" type="button" data-invite-resend="${esc(item.id)}">Resend</button><button class="mg-btn mg-btn-ghost" type="button" data-invite-revoke="${esc(item.id)}">Revoke</button></div>`;
  };
  const render = () => {
    list.innerHTML = items.map((item) => `<tr><td><strong>${esc(item.contact_name || item.email)}</strong><small>${esc(item.email)}</small><small>${esc(item.firm_name || 'No firm supplied')}</small></td><td><strong>${esc(item.round_name || 'General invitation')}</strong><small>${esc(read(item.investor_type))} · ${esc(read(item.expected_investment_range))}</small></td><td><span class="mg-status-badge is-${esc(item.status)}">${esc(read(item.status))}</span><small>${Number(item.view_count || 0)} view${Number(item.view_count || 0) === 1 ? '' : 's'}</small></td><td><strong>${esc(read(item.delivery_status))}</strong><small>${Number(item.send_count || 0)} send${Number(item.send_count || 0) === 1 ? '' : 's'}</small></td><td>${esc(dt(item.expires_at))}</td><td>${item.request_id ? `<a href="/admin/investor-access-requests.php">${esc(read(item.request_status || 'pending'))}</a>` : '<span>Not submitted</span>'}</td><td>${actionButtons(item)}</td></tr>`).join('') || '<tr><td colspan="7"><div class="mg-investment-empty"><h2>No Investor invitations match this view.</h2></div></td></tr>';
    summary.textContent = `${items.length} invitation${items.length === 1 ? '' : 's'} loaded.`;
    list.querySelectorAll('[data-invite-resend]').forEach((button) => button.addEventListener('click', () => resend(button.dataset.inviteResend)));
    list.querySelectorAll('[data-invite-revoke]').forEach((button) => button.addEventListener('click', () => revoke(button.dataset.inviteRevoke)));
  };
  const load = async () => {
    const params = new URLSearchParams(new FormData(filter));
    set(notice, 'Loading Investor invitations…');
    try {
      const data = await request(`/api/admin/investor-invitations.php?${params.toString()}`);
      if (!data.ready) throw new Error(`Import ${data.migration || root.dataset.migration} before creating Investor invitations.`);
      items = data.items || [];
      if (!roundsLoaded) {
        (data.rounds || []).forEach((round) => {
          const option = document.createElement('option');
          option.value = round.public_id;
          option.textContent = `${round.public_name} · ${read(round.status)}`;
          roundSelect.appendChild(option);
        });
        roundsLoaded = true;
      }
      render();
      set(notice, '');
    } catch (error) {
      set(notice, error.message, 'error');
      items = [];
      render();
    }
  };
  const exposeLink = (data) => {
    if (!data?.share_url) return;
    share.hidden = false;
    shareUrl.value = data.share_url;
    shareStatus.textContent = data.email_sent ? 'Email delivered; link available for manual copy.' : 'Email was not delivered; copy this link securely.';
  };
  createForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!canManage || !schemaReady || !createForm.reportValidity()) return;
    const payload = Object.fromEntries(new FormData(createForm).entries());
    payload.action = 'create';
    createButton.disabled = true;
    set(createNotice, 'Creating token-hashed Investor invitation…');
    try {
      const data = await request('/api/admin/investor-invitations.php', {method:'POST', body:JSON.stringify(payload)});
      exposeLink(data);
      createForm.reset();
      set(createNotice, data.email_sent ? 'Investor invitation created and emailed.' : 'Investor invitation created. Email delivery was not confirmed; use the secure link below.', data.email_sent ? 'success' : 'error');
      await load();
    } catch (error) {
      set(createNotice, error.message, 'error');
    } finally {
      createButton.disabled = false;
    }
  });
  const resend = async (id) => {
    if (!confirm('Rotate the secure token, extend this invitation for 14 days, and resend it? The previous link will stop working.')) return;
    set(notice, 'Rotating and resending Investor invitation…');
    try {
      const data = await request('/api/admin/investor-invitations.php', {method:'POST', body:JSON.stringify({action:'resend', invitation_id:id, expires_in_days:14})});
      exposeLink(data);
      set(notice, data.email_sent ? 'Invitation resent with a new secure link.' : 'Invitation link rotated, but email delivery was not confirmed.', data.email_sent ? 'success' : 'error');
      await load();
    } catch (error) { set(notice, error.message, 'error'); }
  };
  const revoke = async (id) => {
    const reason = prompt('Enter the reason for revoking this invitation:');
    if (reason === null) return;
    if (reason.trim().length < 8) { set(notice, 'Revocation reason must be at least 8 characters.', 'error'); return; }
    try {
      await request('/api/admin/investor-invitations.php', {method:'POST', body:JSON.stringify({action:'revoke', invitation_id:id, reason:reason.trim()})});
      set(notice, 'Investor invitation revoked.', 'success');
      await load();
    } catch (error) { set(notice, error.message, 'error'); }
  };
  root.querySelector('[data-invitations-refresh]').addEventListener('click', load);
  filter.addEventListener('submit', (event) => { event.preventDefault(); load(); });
  root.querySelector('[data-invitation-copy]').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(shareUrl.value); shareStatus.textContent = 'Secure invitation link copied.'; }
    catch (_) { shareUrl.select(); document.execCommand('copy'); shareStatus.textContent = 'Secure invitation link copied.'; }
  });
  load();
})();
