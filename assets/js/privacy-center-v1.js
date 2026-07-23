(() => {
  'use strict';
  const root=document.querySelector('[data-privacy-center]');
  if(!root||root.dataset.authenticated!=='true')return;
  const statusNode=root.querySelector('[data-privacy-request-status]');
  const panel=root.querySelector('[data-privacy-delete-panel]');
  const form=root.querySelector('[data-privacy-delete-form]');
  const notice=root.querySelector('[data-privacy-notice]');

  const escapeHtml=(value)=>String(value??'').replace(/[&<>"]/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));
  const formatDate=(value)=>{if(!value)return '—';const d=new Date(String(value).replace(' ','T')+'Z');return Number.isNaN(d.getTime())?String(value):new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(d);};
  const setNotice=(message,type='info')=>{if(!notice)return;notice.textContent=message||'';notice.dataset.type=type;};

  function render(request){
    if(!statusNode)return;
    if(!request){
      statusNode.innerHTML='<div class="mg-privacy-empty"><strong>No active request.</strong><br>Your account is active and no privacy-erasure workflow is pending.</div>';
      if(panel)panel.hidden=false;
      return;
    }
    const due=request.extended_due_at||request.response_due_at;
    statusNode.innerHTML=`<div class="mg-privacy-status"><span class="mg-privacy-status-badge">${escapeHtml(request.status)}</span><div class="mg-privacy-status-row"><span>Request ID</span><strong>${escapeHtml(request.public_id)}</strong></div><div class="mg-privacy-status-row"><span>Jurisdiction</span><strong>${escapeHtml(request.jurisdiction)}</strong></div><div class="mg-privacy-status-row"><span>Submitted</span><strong>${escapeHtml(formatDate(request.requested_at))}</strong></div><div class="mg-privacy-status-row"><span>Response due</span><strong>${escapeHtml(formatDate(due))}</strong></div><div class="mg-privacy-status-row"><span>Finalization date</span><strong>${escapeHtml(formatDate(request.grace_ends_at))}</strong></div></div>`;
    if(panel)panel.hidden=true;
  }

  async function load(){
    try{
      const response=await fetch('/api/me/privacy-request.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
      const payload=await response.json();
      if(!response.ok||!payload?.ok)throw new Error(payload?.message||'Unable to load privacy status.');
      render(payload.data?.request||null);
    }catch(error){
      if(statusNode)statusNode.innerHTML=`<div class="mg-privacy-empty">${escapeHtml(error.message||'Unable to load privacy status.')}</div>`;
    }
  }

  form?.addEventListener('submit',async(event)=>{
    event.preventDefault();
    if(!window.confirm('Close this account immediately and begin the governed deletion process?'))return;
    const button=form.querySelector('button[type="submit"]');
    button.disabled=true;setNotice('Verifying ownership and closing the account…');
    const data=Object.fromEntries(new FormData(form).entries());
    try{
      const payload=window.Microgifter?.post
        ? await window.Microgifter.post('/api/me/privacy-request.php',data)
        : await fetch('/api/me/privacy-request.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(data)}).then((response)=>response.json().then((body)=>{if(!response.ok)throw new Error(body?.message||'Request failed.');return body;}));
      if(!payload?.ok)throw new Error(payload?.message||'Unable to submit request.');
      setNotice(payload.message||'Account closed. Redirecting…');
      window.setTimeout(()=>{window.location.href='/signin.php?privacy_request=submitted';},900);
    }catch(error){
      button.disabled=false;setNotice(error.message||'Unable to submit request.','error');
    }
  });
  load();
})();
