document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter||!document.querySelector('[data-merchant-crm-total]'))return;
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function money(c){return '$'+((Number(c||0)/100).toFixed(2));}
function set(sel,val){var el=document.querySelector(sel);if(el)el.textContent=String(val==null?'—':val);}
function row(c){return '<article class="mg-product-card"><button type="button"><span><strong>'+esc(c.display_name||'Unnamed contact')+'</strong><span>'+esc(c.email||c.phone||'No contact method')+'</span><small>'+esc(c.campaign_type||'unknown')+' · '+esc(c.source_type||'unknown')+' · '+Number(c.total_rewards_issued||0)+' rewards · '+money(c.total_purchase_cents)+'</small></span><span class="mg-card-meta"><em>'+esc(c.stage||'lead')+'</em></span></button></article>';}
function ensureList(){var list=document.querySelector('[data-merchant-crm-list]');if(list)return list;var body=document.querySelector('.mg-app-panel .mg-app-panel-body');if(!body)return null;body.innerHTML='<div data-merchant-crm-list></div>';return body.querySelector('[data-merchant-crm-list]');}
async function load(){
 try{
  var res=await Microgifter.get('/api/merchant/merchant-crm.php');var data=res.data||res;var totals=data.totals||{};
  set('[data-merchant-crm-total]',Number(totals.total_contacts||0).toLocaleString());
  set('[data-merchant-crm-campaigns]',Number(totals.campaign_contacts||0).toLocaleString());
  set('[data-merchant-crm-purchases]',Number(totals.purchasing_customers||0).toLocaleString());
  set('[data-merchant-crm-followers]',Number(totals.followers||0).toLocaleString());
  var list=ensureList();if(list)list.innerHTML=(data.contacts||[]).map(row).join('')||'<div class="mg-empty-state"><strong>No CRM contacts yet</strong><p>Campaign entries, purchases, followers, claims, and redemptions will appear here.</p></div>';
 }catch(error){var list=ensureList();if(list)list.innerHTML='<div class="mg-empty-state"><strong>Unable to load CRM</strong><p>'+esc(error.message||'Request failed')+'</p></div>';}
}
load();
});
