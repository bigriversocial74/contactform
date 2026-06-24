document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var tabs=document.querySelector('[data-package-tabs]');
  if(!tabs||document.getElementById('pkg-tab-stamp-tests'))return;
  var nav=tabs.querySelector('.mg-package-tab-nav');
  var input=document.createElement('input');
  input.id='pkg-tab-stamp-tests';input.name='pkg-tab';input.type='radio';
  var label=document.createElement('label');
  label.setAttribute('for','pkg-tab-stamp-tests');label.textContent='Stamp tests';
  var panel=document.createElement('section');
  panel.className='mg-package-tab-panel is-stamp-tests';
  panel.innerHTML='<section class="mg-stamp-panel" data-stamp-test-runner><header><div><span class="mg-eyebrow">Browser test runner</span><h2>Test the Stamp economy before upload</h2><p>Enter a merchant user ID, then run each test from the browser. Green means pass. Red means copy the message and fix before deploy.</p></div><span class="mg-package-status">No Bash</span></header><div class="mg-admin-package-review-grid"><article><h3>Test setup</h3><form class="mg-merchant-form" data-stamp-test-form><label>Merchant account user ID<input name="account_user_id" type="number" min="1" required placeholder="Merchant user ID"></label><label>Package<select name="package_id"><option value="starter">Starter · 1,000 Stamps</option><option value="growth">Growth · 10,000 Stamps</option><option value="pro">Pro · 50,000 Stamps</option></select></label><label>Bundle key<select name="bundle_key"><option value="stamps_1000">1,000 Stamps</option><option value="stamps_5000">5,000 Stamps</option><option value="stamps_25000">25,000 Stamps</option></select></label><label>Debit entry ID for failure return<input name="entry_id" placeholder="Paste debit ledger entry ID after test debit"></label></form></article><article><h3>Run tests</h3><div class="mg-heading-actions" style="flex-wrap:wrap;gap:8px"><button class="mg-btn mg-btn-soft" type="button" data-stamp-test="health">1. Health</button><button class="mg-btn mg-btn-soft" type="button" data-stamp-test="bundles">2. Bundles</button><button class="mg-btn mg-btn-soft" type="button" data-stamp-test="assign_package">3. Assign package</button><button class="mg-btn mg-btn-soft" type="button" data-stamp-test="renewal_preview">4. Preview renewal</button><button class="mg-btn mg-btn-primary" type="button" data-stamp-test="renewal_run">5. Run renewal</button><button class="mg-btn mg-btn-soft" type="button" data-stamp-test="balance">6. Balance</button><button class="mg-btn mg-btn-primary" type="button" data-stamp-test="purchase_sandbox">7. Sandbox purchase</button><button class="mg-btn mg-btn-soft" type="button" data-stamp-test="test_debit">8. Test debit</button><button class="mg-btn mg-btn-soft" type="button" data-stamp-test="delivery_failure">9. Failed-send return</button></div><div class="mg-form-status" data-stamp-test-status>Ready. Start with Health.</div></article></div><div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>Step</th><th>Status</th><th>Message</th><th>Details</th></tr></thead><tbody data-stamp-test-results><tr><td colspan="4">No browser tests run yet.</td></tr></tbody></table></div></section>';
  tabs.insertBefore(input,nav);
  if(nav)nav.appendChild(label);
  tabs.appendChild(panel);
  var form=panel.querySelector('[data-stamp-test-form]');
  var status=panel.querySelector('[data-stamp-test-status]');
  var results=panel.querySelector('[data-stamp-test-results]');
  var rows=[];
  function esc(v){return String(v==null?'':v).replace(/[&<>'\"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'})[c];});}
  function val(name){var el=form.querySelector('[name="'+name+'"]');return el?el.value:'';}
  function details(data){try{return JSON.stringify(data||{},null,2).slice(0,500);}catch(e){return '';}}
  function render(){results.innerHTML=rows.length?rows.map(function(r){return '<tr><td><strong>'+esc(r.step)+'</strong></td><td><span class="mg-package-status">'+(r.ok?'GREEN':'RED')+'</span></td><td>'+esc(r.message)+'</td><td><pre style="white-space:pre-wrap;margin:0;max-width:420px">'+esc(details(r.data))+'</pre></td></tr>';}).join(''):'<tr><td colspan="4">No browser tests run yet.</td></tr>';}
  async function run(action){
    var payload={action:action,account_user_id:val('account_user_id'),package_id:val('package_id'),bundle_key:val('bundle_key'),entry_id:val('entry_id')};
    if(['health','bundles','renewal_preview'].indexOf(action)>=0){delete payload.account_user_id;}
    if(action==='delivery_failure'&&!payload.entry_id){status.textContent='Paste a debit ledger entry ID before running failed-send return.';return;}
    try{
      status.textContent='Running '+action+'...';
      var r=await Microgifter.post('/api/stamps/test-runner.php',payload);
      var item=(r.data||r);
      rows.unshift(item);
      status.textContent=item.message||'Test completed.';
      render();
      if(action==='test_debit'&&item.data&&item.data.entry&&item.data.entry.entry_id){var entry=form.querySelector('[name="entry_id"]');if(entry)entry.value=item.data.entry.entry_id;}
    }catch(error){rows.unshift({step:action,ok:false,message:error.message||'Test failed.',data:{error:error.message||''}});status.textContent=error.message||'Test failed.';render();}
  }
  panel.querySelectorAll('[data-stamp-test]').forEach(function(btn){btn.addEventListener('click',function(){run(btn.getAttribute('data-stamp-test'));});});
});
