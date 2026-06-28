document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-dev-api-redesign]');
if(!root)return;
var tabs=Array.prototype.slice.call(root.querySelectorAll('[data-dev-tab]'));
var panels=Array.prototype.slice.call(root.querySelectorAll('[data-dev-tab-panel]'));
var aliases={
  '#developer-overview':'overview',
  '#developer-distribution':'distribution',
  '#developer-distribution-plan':'distribution',
  '#distribution-editor':'distribution',
  '#developer-distribution-editor':'distribution',
  '#developer-app-editor':'apps',
  '#developer-apps':'apps',
  '#developer-credentials':'credentials',
  '#developer-sandbox':'sandbox',
  '#developer-webhooks':'webhooks',
  '#developer-analytics':'analytics',
  '#developer-logs':'analytics',
  '#developer-launch':'launch',
  '#developer-launch-qa':'launch'
};
function currentFromHash(){
  var hash=window.location.hash||'';
  if(aliases[hash])return aliases[hash];
  if(hash.indexOf('#developer-tab-')===0)return hash.replace('#developer-tab-','');
  return 'overview';
}
function activate(name,updateHash){
  var exists=panels.some(function(panel){return panel.dataset.devTabPanel===name;});
  if(!exists)name='overview';
  tabs.forEach(function(tab){
    var active=tab.dataset.devTab===name;
    tab.classList.toggle('is-active',active);
    tab.setAttribute('aria-selected',active?'true':'false');
  });
  panels.forEach(function(panel){
    var active=panel.dataset.devTabPanel===name;
    panel.hidden=!active;
    panel.classList.toggle('is-active',active);
  });
  root.dataset.activeTab=name;
  if(updateHash&&window.history&&window.history.replaceState){
    window.history.replaceState(null,'','#developer-tab-'+name);
  }
}
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function money(c,cur){try{return new Intl.NumberFormat(undefined,{style:'currency',currency:cur||'USD'}).format(Number(c||0)/100);}catch(e){return '$'+(Number(c||0)/100).toFixed(2);}}
function setStatus(node,message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(node,message,type);return;}if(node){node.textContent=message||'';node.dataset.statusType=type||'';}}
function toDatetimeLocal(value){if(!value)return '';var s=String(value).replace('T',' ');return s.slice(0,16).replace(' ','T');}
function scrollEditor(){var editor=document.querySelector('#developer-distribution-editor');if(editor&&typeof editor.scrollIntoView==='function')editor.scrollIntoView({behavior:'smooth',block:'start'});}
function startNewPlan(){activate('distribution',true);setTimeout(function(){var btn=document.querySelector('[data-program-new]');if(btn)btn.click();var form=document.querySelector('[data-program-form]');if(form){form.elements.program_id.value='';if(form.elements.status)form.elements.status.value='draft';}scrollEditor();},160);}
function renderProductPicker(availableProducts,selectedProducts){
  var picker=document.querySelector('[data-program-product-picker]');
  if(!picker)return;
  var selected=new Set((selectedProducts||[]).filter(function(x){return x.status!=='inactive';}).map(function(x){return x.template_id;}));
  if(!availableProducts||!availableProducts.length){picker.innerHTML='<div class="mg-program-product-empty"><strong>No published products available.</strong><br>Publish at least one product before creating a distribution program.</div>';return;}
  picker.innerHTML=availableProducts.map(function(p){var checked=selected.has(p.template_id)?' checked':'';return '<label class="mg-program-product-option"><input type="checkbox" value="'+esc(p.template_id)+'" data-program-template'+checked+'><span><strong>'+esc(p.title||'Untitled product')+'</strong><span>'+esc(p.product_type||'product')+' · '+esc(p.template_id)+' · '+money(p.unit_value_cents,p.currency)+'</span><em>'+esc(p.product_status||'published')+'</em></span></label>';}).join('');
}
async function loadProgramProducts(programId){
  if(!window.Microgifter||!programId)return;
  try{var r=await Microgifter.get('/api/distribution/program-products.php?program_id='+encodeURIComponent(programId));var d=r.data||r;renderProductPicker(d.available_products||[],d.products||[]);}catch(err){}
}
async function editInlineProgram(programId){
  if(!window.Microgifter||!programId)return;
  activate('distribution',true);
  var form=document.querySelector('[data-program-form]'),status=document.querySelector('[data-program-status-message]');
  if(!form)return;
  try{
    setStatus(status,'Loading distribution program…');
    var r=await Microgifter.get('/api/merchant/distribution-program.php?id='+encodeURIComponent(programId));
    var d=r.data||r,p=d.program||{};
    form.elements.program_id.value=p.public_id||programId;
    form.elements.name.value=p.name||'';
    form.elements.program_type.value=p.program_type||'external_api';
    form.elements.status.value=p.status||'draft';
    form.elements.starts_at.value=toDatetimeLocal(p.starts_at);
    form.elements.ends_at.value=toDatetimeLocal(p.ends_at);
    form.elements.budget_cents.value=p.budget_cents==null?'':p.budget_cents;
    form.elements.max_items.value=p.max_items==null?'':p.max_items;
    form.elements.per_recipient_limit.value=p.per_recipient_limit==null?'':p.per_recipient_limit;
    await loadProgramProducts(p.public_id||programId);
    setStatus(status,'Editing '+(p.name||'distribution program')+'.','success');
    scrollEditor();
  }catch(err){setStatus(status,err.message||'Unable to load distribution program.','error');}
}
function enhanceProgramRows(){
  var list=document.querySelector('[data-program-list]');
  if(!list)return;
  list.querySelectorAll('.mg-program-row').forEach(function(row){
    if(row.querySelector('[data-dev-edit-program]'))return;
    var open=row.querySelector('.mg-program-actions a[href*="id="]');
    if(!open)return;
    var id='';
    try{id=new URL(open.href,window.location.origin).searchParams.get('id')||'';}catch(e){var m=(open.getAttribute('href')||'').match(/id=([^&]+)/);id=m?decodeURIComponent(m[1]):'';}
    if(!id)return;
    var actions=row.querySelector('.mg-program-actions')||open.parentElement;
    var btn=document.createElement('button');
    btn.type='button';
    btn.className='mg-btn mg-btn-soft';
    btn.dataset.devEditProgram=id;
    btn.textContent='Edit';
    btn.addEventListener('click',function(){editInlineProgram(id);});
    actions.insertBefore(btn,open);
  });
}
tabs.forEach(function(tab){tab.addEventListener('click',function(){activate(tab.dataset.devTab,true);});});
root.querySelectorAll('[data-dev-tab-trigger]').forEach(function(trigger){
  trigger.addEventListener('click',function(){
    activate(trigger.dataset.devTabTrigger,true);
    var nav=root.querySelector('.mg-dev-tabs');
    if(nav&&typeof nav.scrollIntoView==='function')nav.scrollIntoView({behavior:'smooth',block:'start'});
    if(trigger.hasAttribute('data-dev-new-plan'))startNewPlan();
  });
});
var list=document.querySelector('[data-program-list]');
if(list&&window.MutationObserver){new MutationObserver(enhanceProgramRows).observe(list,{childList:true,subtree:true});}
setInterval(enhanceProgramRows,1200);
window.addEventListener('hashchange',function(){activate(currentFromHash(),false);});
activate(currentFromHash(),false);
enhanceProgramRows();
});
