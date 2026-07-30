(() => {
  'use strict';
  const root = document.querySelector('[data-admin-investor-access]');
  if (!root) return;
  const list = root.querySelector('[data-access-list]');
  const filter = root.querySelector('[data-access-filter]');
  const summary = root.querySelector('[data-access-summary]');
  const notice = root.querySelector('[data-access-list-notice]');
  const layer = document.querySelector('[data-access-drawer-layer]');
  const detail = layer.querySelector('[data-access-detail]');
  const title = layer.querySelector('[data-access-drawer-title]');
  const subtitle = layer.querySelector('[data-access-drawer-subtitle]');
  const canManage = root.dataset.canManage === '1';
  let items = [];

  const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const readable = (value) => String(value || '').replace(/_/g,' ').replace(/\b\w/g,(c)=>c.toUpperCase());
  const formatDate = (value) => value ? new Date(String(value).replace(' ','T')).toLocaleString() : '—';
  const request = async (url, options={}) => { const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrfToken||''}:{})},...options});const payload=await response.json().catch(()=>null);if(!response.ok||!payload?.ok)throw new Error(payload?.message||'Request failed.');return payload.data; };
  const setNotice = (message='',type='info') => { notice.textContent=message;notice.dataset.type=type; };
  const sourceHtml = (item) => item.source === 'admin_invitation'
    ? `<span class="mg-status-badge is-approved">Invited</span>${item.invitation_round_name ? `<small>${escape(item.invitation_round_name)}</small>` : '<small>General invitation</small>'}`
    : '<span class="mg-status-badge">Profile request</span>';
  const renderList = () => {
    list.innerHTML = items.map((item) => `<tr><td><strong>${escape(item.display_name)}</strong><small>${escape(item.email)}</small></td><td><strong>${escape(item.firm_name)}</strong><small>${escape(item.website_url || 'No website')}</small></td><td>${escape(readable(item.investor_type))}<br><small>${escape(readable(item.expected_investment_range))}</small></td><td>${sourceHtml(item)}</td><td><span class="mg-status-badge is-${escape(item.status)}">${escape(readable(item.status))}</span></td><td>${escape(formatDate(item.requested_at))}</td><td><button class="mg-btn mg-btn-soft" type="button" data-open-request="${escape(item.id)}">Review</button></td></tr>`).join('');
    summary.textContent = `${items.length} request${items.length===1?'':'s'} loaded.`;
    list.querySelectorAll('[data-open-request]').forEach((button)=>button.addEventListener('click',()=>open(button.dataset.openRequest)));
  };
  const load = async () => { const status=new FormData(filter).get('status')||'';setNotice('Loading investor-access requests…');try{items=(await request(`/api/admin/investor-access.php?status=${encodeURIComponent(status)}`)).items||[];renderList();setNotice('');}catch(error){setNotice(error.message,'error');} };
  const field = (label,value,link=false) => `<div><dt>${escape(label)}</dt><dd>${link&&value?`<a href="${escape(value)}" target="_blank" rel="noopener">${escape(value)}</a>`:escape(value||'—')}</dd></div>`;
  const open = (id) => {
    const item=items.find((entry)=>entry.id===id);if(!item)return;
    title.textContent=item.display_name;subtitle.textContent=`${item.email} · ${readable(item.status)}`;
    detail.innerHTML=`<dl class="mg-detail-grid">${field('Request source',item.source==='admin_invitation'?'Super Admin invitation':'Super Admin profile request')}${field('Invitation round',item.invitation_round_name)}${field('Firm',item.firm_name)}${field('Job title',item.job_title)}${field('Website',item.website_url,true)}${field('Primary social',item.primary_social_url,true)}${field('LinkedIn',item.linkedin_url,true)}${field('Investor type',readable(item.investor_type))}${field('Expected range',readable(item.expected_investment_range))}${field('Phone',item.phone)}${field('Referral',item.referral_source)}${field('Requested',formatDate(item.requested_at))}</dl><section class="mg-investment-panel" style="margin-top:16px"><header><div><span>Applicant statement</span><h2>Reason for access</h2></div></header><p>${escape(item.request_reason)}</p>${item.more_information_message?`<p><strong>More information requested:</strong> ${escape(item.more_information_message)}</p>`:''}</section>${canManage?`<section class="mg-review-actions"><label>Required review notes<textarea data-review-notes maxlength="4000" placeholder="Document the decision or information needed."></textarea></label><label class="mg-investment-check"><input type="checkbox" data-reapply checked><span>Allow reapplication</span></label><label>Reapplication available after<input type="datetime-local" data-reapply-after></label><div class="mg-review-buttons"><button class="mg-btn mg-btn-primary" data-review-action="approve">Approve Investor Access</button><button class="mg-btn mg-btn-soft" data-review-action="request_information">Request More Information</button><button class="mg-btn mg-btn-ghost" data-review-action="deny">Deny</button>${item.status==='approved'?'<button class="mg-btn mg-btn-ghost" data-review-action="revoke">Revoke Access</button>':''}</div><div class="mg-investment-notice" data-review-notice></div></section>`:''}`;
    if(canManage)detail.querySelectorAll('[data-review-action]').forEach((button)=>button.addEventListener('click',()=>decide(item,button.dataset.reviewAction)));
    layer.hidden=false;document.body.style.overflow='hidden';layer.querySelector('.mg-investment-drawer').focus();
  };
  const decide = async (item,action) => {
    const notes=detail.querySelector('[data-review-notes]').value.trim();const actionNotice=detail.querySelector('[data-review-notice]');
    if(action!=='approve'&&notes.length<8){actionNotice.textContent='Enter review notes of at least 8 characters.';actionNotice.dataset.type='error';return;}
    if(!confirm(`${readable(action)} for ${item.display_name}?`))return;
    const payload={action,request_id:item.id,notes,reapplication_allowed:detail.querySelector('[data-reapply]').checked,reapplication_after:detail.querySelector('[data-reapply-after]').value};
    try{await request('/api/admin/investor-access.php',{method:'POST',body:JSON.stringify(payload)});layer.hidden=true;document.body.style.overflow='';await load();setNotice('Investor-access decision saved.','success');}catch(error){actionNotice.textContent=error.message;actionNotice.dataset.type='error';}
  };
  filter.addEventListener('submit',(event)=>{event.preventDefault();load();});root.querySelector('[data-access-refresh]').addEventListener('click',load);layer.querySelectorAll('[data-access-close]').forEach((button)=>button.addEventListener('click',()=>{layer.hidden=true;document.body.style.overflow='';}));
  load();
})();
