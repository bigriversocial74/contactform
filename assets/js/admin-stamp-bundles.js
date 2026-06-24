document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var bundleForm=document.querySelector('[data-admin-stamp-bundle-form]');
  var bundleList=document.querySelector('[data-admin-stamp-bundle-list]');
  var bundleStatus=document.querySelector('[data-admin-stamp-bundle-status]');
  var monthlyForm=document.querySelector('[data-admin-monthly-stamps-form]');
  var monthlyStatus=document.querySelector('[data-admin-monthly-stamps-status]');
  function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setStatus(el,msg,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(el,msg,type);return;}if(el)el.textContent=msg||'';}
  function money(cents,currency){return String(currency||'USD')+' '+(Number(cents||0)/100).toFixed(2);}
  function row(b){return '<tr data-bundle-row data-bundle="'+esc(JSON.stringify(b))+'"><td><strong>'+esc(b.label)+'</strong></td><td>'+esc(b.bundle_key)+'</td><td>'+Number(b.stamps||0).toLocaleString()+'</td><td>'+esc(money(b.price_cents,b.currency))+'</td><td>'+esc(b.status)+'</td><td><button class="mg-btn mg-btn-soft" type="button" data-edit-bundle>Edit</button></td></tr>';}
  function bindEdits(){if(!bundleList||!bundleForm)return;bundleList.querySelectorAll('[data-edit-bundle]').forEach(function(btn){btn.addEventListener('click',function(){var tr=btn.closest('[data-bundle-row]');if(!tr)return;var data={};try{data=JSON.parse(tr.getAttribute('data-bundle')||'{}');}catch(e){}Object.keys(data).forEach(function(k){var el=bundleForm.elements[k==='id'?'bundle_id':k];if(el)el.value=data[k]==null?'':data[k];});setStatus(bundleStatus,'Editing '+(data.label||'Stamp bundle')+'.');});});}
  async function loadBundles(){if(!bundleList)return;try{var r=await Microgifter.get('/api/stamps/bundles.php');var bundles=(r.data||r).bundles||[];bundleList.innerHTML=bundles.length?bundles.map(row).join(''):'<tr><td colspan="6">No Stamp bundles found.</td></tr>';bindEdits();}catch(e){bindEdits();}}
  if(bundleForm){bundleForm.addEventListener('submit',async function(ev){ev.preventDefault();var data=Object.fromEntries(new FormData(bundleForm).entries());try{setStatus(bundleStatus,'Saving Stamp bundle...');var r=await Microgifter.post('/api/stamps/bundles.php',data);setStatus(bundleStatus,r.message||'Stamp bundle saved.','success');bundleForm.reset();await loadBundles();}catch(error){setStatus(bundleStatus,error.message||'Unable to save Stamp bundle.','error');}});}
  if(monthlyForm){monthlyForm.addEventListener('submit',async function(ev){ev.preventDefault();var data=Object.fromEntries(new FormData(monthlyForm).entries());try{setStatus(monthlyStatus,'Crediting monthly Stamps...');var r=await Microgifter.post('/api/stamps/monthly-credit.php',data);var entry=(r.data&&r.data.entry)||{};setStatus(monthlyStatus,(r.message||'Monthly Stamps credited.')+' Balance: '+(entry.balance_after==null?'updated':entry.balance_after),'success');}catch(error){setStatus(monthlyStatus,error.message||'Unable to credit monthly Stamps.','error');}});}
  loadBundles();
});
