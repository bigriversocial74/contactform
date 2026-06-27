document.addEventListener('DOMContentLoaded',function(){
'use strict';
var shell=document.querySelector('[data-merchant-crm-shell]');if(!shell)return;
function qs(s,r){return(r||document).querySelector(s)}
function qsa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))}
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c]})}
function safeSel(v){return window.CSS&&CSS.escape?CSS.escape(v):String(v).replace(/[^a-zA-Z0-9_-]/g,'\\$&')}
function toast(m){window.Microgifter&&Microgifter.toast?Microgifter.toast(m):alert(m)}
function ensureActionHistoryPanel(){
  var nav=qs('.mg-crm-tabs',shell),campaignTab=qs('[data-crm-tab-target="campaigns"]',shell);
  if(nav&&!qs('[data-crm-tab-target="actions"]',shell)){
    var btn=document.createElement('button');btn.type='button';btn.setAttribute('role','tab');btn.setAttribute('aria-selected','false');btn.setAttribute('data-crm-tab-target','actions');btn.textContent='Actions';
    if(campaignTab&&campaignTab.nextSibling)nav.insertBefore(btn,campaignTab.nextSibling);else nav.appendChild(btn);
  }
  if(!qs('[data-crm-tab-panel="actions"]',shell)){
    var panel=document.createElement('section');panel.className='mg-crm-tab-panel mg-crm-action-history-panel';panel.setAttribute('data-crm-tab-panel','actions');panel.setAttribute('role','tabpanel');panel.hidden=true;
    panel.innerHTML='<div class="mg-crm-tab-title"><div><h2>CRM Campaign Actions</h2><p>Review bulk message, reward, invite, and follow-up runs with recipient results and retryable contacts.</p></div><div class="mg-crm-tab-actions"><select class="mg-input" data-crm-history-type aria-label="Action type"><option value="">All actions</option><option value="message">Messages</option><option value="reward">Rewards / invites</option><option value="followup">Follow-ups</option></select><select class="mg-input" data-crm-history-status aria-label="Result status"><option value="">All results</option><option value="sent">Sent</option><option value="issued">Issued</option><option value="invited">Invited</option><option value="skipped">Skipped</option><option value="failed">Failed</option><option value="duplicate">Duplicate</option></select><button class="mg-btn mg-btn-soft" type="button" data-crm-history-refresh>Refresh</button></div></div><div class="mg-crm-history-kpis" data-crm-history-totals></div><section class="mg-app-panel mg-crm-card mg-crm-history-card" data-crm-action-history><div class="mg-empty-state"><strong>Loading campaign actions</strong><p>Bulk CRM runs will appear here after merchants send messages, rewards, invites, or follow-ups.</p></div></section>';
    var campaigns=qs('[data-crm-tab-panel="campaigns"]',shell);if(campaigns&&campaigns.nextSibling)shell.insertBefore(panel,campaigns.nextSibling);else shell.appendChild(panel);
  }
}
ensureActionHistoryPanel();
var tabs=qsa('[data-crm-tab-target]',shell);
var panels=qsa('[data-crm-tab-panel]',shell);
var historyLoaded=false;
function activate(id,updateHash){
  if(!id)id='overview';
  var found=panels.some(function(p){return p.getAttribute('data-crm-tab-panel')===id;});
  if(!found)id='overview';
  tabs.forEach(function(t){var on=t.getAttribute('data-crm-tab-target')===id;t.classList.toggle('is-active',on);t.setAttribute('aria-selected',on?'true':'false');});
  panels.forEach(function(p){p.hidden=p.getAttribute('data-crm-tab-panel')!==id;});
  shell.setAttribute('data-crm-active-tab',id);
  if(updateHash&&history.replaceState)history.replaceState(null,'','#crm-'+id);
  document.dispatchEvent(new CustomEvent('mg:crm-tab:changed',{detail:{tab:id}}));
  if(id==='actions')loadActionHistory(false);
}
function historyUrl(){
  var params=[];
  var type=(qs('[data-crm-history-type]',shell)||{}).value||'';
  var status=(qs('[data-crm-history-status]',shell)||{}).value||'';
  var campaign=(qs('[data-crm-campaign-filter]')||{}).value||'';
  if(type)params.push('type='+encodeURIComponent(type));
  if(status)params.push('status='+encodeURIComponent(status));
  if(campaign)params.push('campaign='+encodeURIComponent(campaign));
  return '/api/merchant/crm-action-history.php'+(params.length?'?'+params.join('&'):'');
}
function metric(label,value){return '<article><span>'+esc(label)+'</span><strong>'+Number(value||0).toLocaleString()+'</strong></article>'}
function renderTotals(t){var e=qs('[data-crm-history-totals]',shell);if(!e)return;e.innerHTML=metric('Selected',t.selected)+metric('Sent',t.sent)+metric('Issued',t.issued)+metric('Invited',t.invited)+metric('Skipped',t.skipped)+metric('Failed',t.failed)+metric('Duplicates',t.duplicates)}
function statusBadge(s){var good=['complete','sent','issued','invited'].indexOf(String(s))>=0;return '<span class="mg-crm-badge '+(good?'is-good':'')+'">'+esc(s||'unknown')+'</span>'}
function recipientRow(r){return '<tr><td><strong>'+esc(r.name||'Unnamed')+'</strong><br><small>'+esc(r.contact_id||'')+'</small></td><td>'+statusBadge(r.has_account?'account':'no account')+'</td><td>'+statusBadge(r.status)+'</td><td>'+esc(r.reason||'—')+'</td></tr>'}
function renderAction(action){var summary=action.summary||{},campaigns=action.campaigns||[],recipients=action.recipients||[],retry=action.retry_contact_ids||[];var campaignText=campaigns.length>1?campaigns.length+' campaigns':(campaigns[0]?campaigns[0].title:'Campaign action');var rows=recipients.slice(0,8).map(recipientRow).join('');return '<article class="mg-crm-history-run" data-crm-history-run="'+esc(action.id)+'"><header><div><span class="mg-eyebrow">'+esc(action.title||action.action_type)+'</span><h3>'+esc(campaignText)+'</h3><p>'+esc(action.created_at||'')+' · '+esc(action.last_event_at||'')+'</p></div>'+statusBadge(action.status)+'</header><div class="mg-crm-history-summary"><span>Selected <strong>'+Number(summary.selected||0)+'</strong></span><span>Sent <strong>'+Number(summary.sent||0)+'</strong></span><span>Issued <strong>'+Number(summary.issued||0)+'</strong></span><span>Invited <strong>'+Number(summary.invited||0)+'</strong></span><span>Skipped <strong>'+Number(summary.skipped||0)+'</strong></span><span>Failed <strong>'+Number(summary.failed||0)+'</strong></span></div>'+(rows?'<div class="mg-crm-history-table"><table><thead><tr><th>Recipient</th><th>Account</th><th>Result</th><th>Reason</th></tr></thead><tbody>'+rows+'</tbody></table></div>':'')+(retry.length?'<div class="mg-crm-history-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-history-retry="'+esc(retry.join(','))+'">Select retryable contacts</button></div>':'')+'</article>'}
function renderHistory(data){var box=qs('[data-crm-action-history]',shell),actions=(data&&data.actions)||[];renderTotals((data&&data.totals)||{});if(!box)return;if(!actions.length){box.innerHTML='<div class="mg-empty-state"><strong>No campaign actions yet</strong><p>Run a bulk message, reward/invite, or follow-up from the Contacts tab to build history.</p></div>';return}box.innerHTML='<div class="mg-crm-history-list">'+actions.map(renderAction).join('')+'</div>'}
async function loadActionHistory(force){if(historyLoaded&&!force)return;if(!window.Microgifter)return;var box=qs('[data-crm-action-history]',shell);if(box)box.innerHTML='<div class="mg-empty-state"><strong>Loading campaign actions</strong></div>';try{var r=await Microgifter.get(historyUrl()),d=r.data||r;historyLoaded=true;renderHistory(d)}catch(e){if(box)box.innerHTML='<div class="mg-empty-state"><strong>Unable to load campaign actions</strong><p>'+esc(e.message||'Try again.')+'</p></div>'}}
function selectRetryable(ids){ids=(ids||[]).filter(Boolean);if(!ids.length)return;var contactTab=qs('[data-crm-tab-target="contacts"]',shell);if(contactTab)contactTab.click();setTimeout(function(){var found=0;ids.forEach(function(id){var cb=qs('tr[data-contact-id="'+safeSel(id)+'"] [data-crm-contact-check]');if(cb&&!cb.checked){cb.checked=true;cb.dispatchEvent(new Event('change',{bubbles:true}));found++;}});toast(found?found+' retryable contacts selected.':'Retry contacts are not visible in the current contact filter.');},250)}
tabs.forEach(function(tab){tab.addEventListener('click',function(ev){ev.preventDefault();activate(tab.getAttribute('data-crm-tab-target'),true);});});
document.addEventListener('click',function(ev){var refresh=ev.target&&ev.target.closest&&ev.target.closest('[data-crm-history-refresh]');if(refresh){historyLoaded=false;loadActionHistory(true)}var retry=ev.target&&ev.target.closest&&ev.target.closest('[data-crm-history-retry]');if(retry)selectRetryable(String(retry.getAttribute('data-crm-history-retry')||'').split(','));});
document.addEventListener('change',function(ev){if(ev.target&&ev.target.matches&&ev.target.matches('[data-crm-history-type],[data-crm-history-status]')){historyLoaded=false;loadActionHistory(true)}});
var query=new URLSearchParams(location.search||'');
var initial=(query.get('tab')||query.get('crm_tab')||(location.hash||'').replace(/^#crm-/,'').replace(/^#/,'')).trim();
activate(initial||'overview',false);
});
