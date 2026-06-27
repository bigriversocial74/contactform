document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var root=document.querySelector('[data-platform-package-billing]');
  var form=document.querySelector('[data-platform-package-form]');
  var select=document.querySelector('[data-platform-package-select]');
  var list=document.querySelector('[data-admin-platform-package-list]');
  var mappedCount=document.querySelector('[data-platform-package-mapped]');
  var status=document.querySelector('[data-platform-package-form-status]');
  var canManage=root&&root.getAttribute('data-can-manage')==='1';
  var packages=Array.isArray(window.MG_ADMIN_PLATFORM_PACKAGES)?window.MG_ADMIN_PLATFORM_PACKAGES.slice():[];
  function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setStatus(msg,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,msg,type);return;}if(status)status.textContent=msg||'';}
  function byId(id){return packages.find(function(p){return String(p.package_id||'')===String(id||'');})||null;}
  function money(cents,currency){return String(currency||'USD')+' '+(Number(cents||0)/100).toFixed(2);}
  function checkoutLabel(pkg){if(Number(pkg.requires_admin_review||0)===1)return 'Review only';if(Number(pkg.is_self_serve||0)!==1)return 'Disabled';if(pkg.checkout_ready||pkg.stripe_price_id_test||pkg.stripe_price_id_live)return 'Ready';return 'Inline fallback';}
  function mappedTotal(){return packages.filter(function(p){return String(p.stripe_price_id_test||'').trim()!==''||String(p.stripe_price_id_live||'').trim()!=='';}).length;}
  function row(pkg){return '<tr data-platform-package-row data-package-id="'+esc(pkg.package_id)+'"><td><strong>'+esc(pkg.name||pkg.package_id)+'</strong><small>'+esc(pkg.package_id)+'</small></td><td>'+esc(money(pkg.monthly_amount_cents,pkg.currency))+'</td><td>'+esc(pkg.stripe_price_id_test||'Not mapped')+'</td><td>'+esc(pkg.stripe_price_id_live||'Not mapped')+'</td><td>'+esc(checkoutLabel(pkg))+'</td><td><button class="mg-btn mg-btn-soft" type="button" data-edit-platform-package>Edit</button></td></tr>';}
  function render(){if(mappedCount)mappedCount.textContent=String(mappedTotal());if(list){list.innerHTML=packages.length?packages.map(row).join(''):'<tr><td colspan="6">No platform packages found.</td></tr>';list.querySelectorAll('[data-edit-platform-package]').forEach(function(btn){btn.addEventListener('click',function(){var tr=btn.closest('[data-platform-package-row]');selectPackage(tr?tr.getAttribute('data-package-id'):'');});});}}
  function fill(pkg){if(!form||!pkg)return;Object.keys(pkg).forEach(function(key){var el=form.elements[key];if(!el)return;if(el.type==='checkbox'){el.checked=Number(pkg[key]||0)===1;}else{el.value=pkg[key]==null?'':pkg[key];}});if(select)select.value=pkg.package_id||'';var cards=document.querySelectorAll('[data-platform-package-card]');cards.forEach(function(card){card.classList.toggle('is-active',card.getAttribute('data-package-id')===String(pkg.package_id||''));});if(String(pkg.package_id||'')==='enterprise'){var self=form.elements.is_self_serve;if(self){self.checked=false;self.disabled=true;}var review=form.elements.requires_admin_review;if(review){review.checked=true;review.disabled=true;}}else if(canManage){var self2=form.elements.is_self_serve;if(self2)self2.disabled=false;var review2=form.elements.requires_admin_review;if(review2)review2.disabled=false;}setStatus('Editing '+(pkg.name||pkg.package_id)+' billing IDs. '+checkoutLabel(pkg)+'.');}
  function selectPackage(id){var pkg=byId(id)||packages[0];fill(pkg);}
  function payload(){var data=Object.fromEntries(new FormData(form).entries());data.is_self_serve=form.elements.is_self_serve&&form.elements.is_self_serve.checked?1:0;data.requires_admin_review=form.elements.requires_admin_review&&form.elements.requires_admin_review.checked?1:0;return data;}
  if(!form)return;
  if(!canManage){Array.prototype.forEach.call(form.elements,function(el){el.disabled=true;});}
  if(select){select.addEventListener('change',function(){selectPackage(select.value);});}
  document.querySelectorAll('[data-platform-package-card]').forEach(function(card){card.addEventListener('click',function(){selectPackage(card.getAttribute('data-package-id'));});card.addEventListener('keydown',function(ev){if(ev.key==='Enter'||ev.key===' '){ev.preventDefault();selectPackage(card.getAttribute('data-package-id'));}});});
  form.addEventListener('submit',async function(ev){ev.preventDefault();if(!canManage)return;try{setStatus('Saving platform package billing IDs...');var r=await Microgifter.post('/api/admin/platform-packages.php',payload());var data=r.data||r;packages=data.packages||packages;if(data.package&&select)select.value=data.package.package_id;render();selectPackage(select?select.value:(data.package&&data.package.package_id));setStatus(r.message||'Platform package billing saved.','success');}catch(error){setStatus(error.message||'Unable to save platform package billing IDs.','error');}});
  render();selectPackage(select?select.value:(packages[0]&&packages[0].package_id));
});
