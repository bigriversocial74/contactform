document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-admin-payments]');
  if(!root)return;
  var form=root.querySelector('[data-payment-settings-form]');
  if(!form)return;

  var mode=form.querySelector('[data-payment-mode]'),
      status=root.querySelector('[data-payment-settings-status]'),
      badge=root.querySelector('[data-payment-readiness]'),
      checks=root.querySelector('[data-payment-checks]'),
      webhook=root.querySelector('[data-payment-webhook-url]'),
      accounts=root.querySelector('[data-payment-connect-counts]'),
      cashForm=root.querySelector('[data-admin-cash-payment-form]'),
      cashToggle=root.querySelector('[data-admin-cash-payment-toggle]'),
      cashStatus=root.querySelector('[data-admin-cash-payment-status]'),
      credentialState=root.querySelector('[data-payment-credential-state]'),
      keyButton=root.querySelector('[data-payment-key-generate]'),
      copyButton=root.querySelector('[data-payment-key-copy]'),
      keyOutput=root.querySelector('[data-payment-key-output]'),
      saveButton=root.querySelector('[data-payment-save-button]'),
      saveLabel=root.querySelector('[data-payment-save-label]'),
      saveState=root.querySelector('[data-payment-save-state]');

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function setMessage(node,text,type){if(!node)return;node.textContent=text||'';node.classList.toggle('is-error',type==='error');node.classList.toggle('is-success',type==='success');node.classList.toggle('is-loading',type==='loading')}
  function msg(text,type){setMessage(status,text,type);setMessage(saveState,text,type)}
  function clearSecrets(){if(form.elements.secret_key)form.elements.secret_key.value='';if(form.elements.webhook_secret)form.elements.webhook_secret.value=''}
  function expectedPrefix(){return mode&&mode.value==='live'?'live':'test'}
  function setSaving(isSaving,label){
    form.classList.toggle('is-saving',!!isSaving);
    if(saveButton){saveButton.disabled=!!isSaving;saveButton.classList.toggle('is-saving',!!isSaving)}
    if(saveLabel)saveLabel.textContent=label||(isSaving?'Saving…':'Save Stripe configuration');
  }
  function validatePayload(payload){
    var prefix=expectedPrefix();
    if(payload.publishable_key&&payload.publishable_key.indexOf('pk_'+prefix+'_')!==0)return 'This page is in '+prefix+' mode. Publishable key must start with pk_'+prefix+'_. Switch mode or paste the matching key.';
    if(payload.secret_key&&payload.secret_key.indexOf('sk_'+prefix+'_')!==0)return 'This page is in '+prefix+' mode. Secret key must start with sk_'+prefix+'_. Switch mode or paste the matching key.';
    if(payload.webhook_secret&&payload.webhook_secret.indexOf('whsec_')!==0)return 'Webhook signing secret must start with whsec_.';
    if(payload.connect_client_id&&payload.connect_client_id.indexOf('ca_')!==0)return 'Connect client ID must start with ca_. A whsec_ value belongs in Webhook signing secret, not Connect client ID.';
    return '';
  }
  function ensureHint(input,attr){
    if(!input)return null;
    var label=input.closest('label');
    if(!label)return null;
    var node=label.querySelector('['+attr+']');
    if(!node){node=document.createElement('small');node.setAttribute(attr,'');label.appendChild(node)}
    return node;
  }
  function setCredentialHints(provider){
    provider=provider||{};
    var secretInput=form.elements.secret_key;
    var webhookInput=form.elements.webhook_secret;
    var secretHint=String(provider.secret_hint||'');
    var webhookHint=String(provider.webhook_hint||'');
    var secretNode=ensureHint(secretInput,'data-payment-secret-key-hint');
    var webhookNode=ensureHint(webhookInput,'data-payment-webhook-secret-hint');
    if(secretInput)secretInput.placeholder=secretHint?'Saved encrypted value: '+secretHint+' — paste a new matching value to replace':'sk_'+expectedPrefix()+'_…';
    if(webhookInput)webhookInput.placeholder=webhookHint?'Saved encrypted value: '+webhookHint+' — paste a new value to replace':'whsec_…';
    if(secretNode){secretNode.textContent=secretHint?'Saved encrypted secret key: '+secretHint+'. Blank keeps this value.':'No saved secret key for this mode.';secretNode.classList.toggle('is-missing',!secretHint)}
    if(webhookNode){webhookNode.textContent=webhookHint?'Saved encrypted webhook secret: '+webhookHint+'. Blank keeps this value.':'No saved webhook signing secret for this mode.';webhookNode.classList.toggle('is-missing',!webhookHint)}
  }
  function setCredentialState(check){
    if(!credentialState)return;
    var ok=!!(check&&check.ok);
    credentialState.classList.toggle('is-ready',ok);
    credentialState.classList.toggle('is-missing',!ok);
    credentialState.textContent=ok?'Credential encryption is ready. You can save Stripe secret values.':((check&&check.detail)||'MG_PAYMENT_CREDENTIAL_KEY is missing. Add api/config.local.php before saving Stripe secret values.');
  }
  function currentBlockers(data){
    var out=[];
    var c=data&&data.checks?data.checks:{};
    ['publishable_key','secret_key','webhook_secret'].forEach(function(key){
      var item=c[key];
      if(item&&!item.ok)out.push(item.label+': '+item.detail);
    });
    return out;
  }
  function fill(data){
    data=data||{};
    var provider=data.provider||{};
    form.elements.enabled.checked=!!provider.enabled;
    form.elements.publishable_key.value=provider.publishable_key||'';
    form.elements.connect_client_id.value=provider.connect_client_id||'';
    form.elements.platform_fee_bps.value=Number(provider.platform_fee_bps||1500);
    form.elements.fixed_fee_cents.value=Number(provider.fixed_fee_cents||0);
    clearSecrets();
    setCredentialHints(provider);
    if(badge){
      badge.textContent=data.ready?'Ready for '+provider.mode:'Not ready for '+provider.mode;
      badge.classList.toggle('is-ready',!!data.ready);
      badge.classList.toggle('is-missing',!data.ready);
    }
    if(checks)checks.innerHTML=Object.keys(data.checks||{}).map(function(key){var item=data.checks[key];return '<article class="mg-payment-check '+(item.ok?'is-ready':'is-missing')+'"><span>'+(item.ok?'✓':'!')+'</span><div><strong>'+esc(item.label)+'</strong><p>'+esc(item.detail)+'</p></div></article>'}).join('');
    if(webhook)webhook.textContent=data.webhook_url||'';
    var connected=data.connected_accounts||{};
    if(accounts)accounts.innerHTML='<strong>Connected accounts</strong><span>'+Number(connected.ready||0)+' ready of '+Number(connected.total||0)+' total</span><small>Credential source: '+esc(provider.credential_source||'missing')+' · secret '+(provider.secret_configured?(provider.secret_hint?esc(provider.secret_hint):'configured'):'missing')+' · webhook '+(provider.webhook_configured?(provider.webhook_hint?esc(provider.webhook_hint):'configured'):'missing')+'</small>';
    setCredentialState(data.checks&&data.checks.credential_encryption);
  }
  function base64Key(bytes){
    var binary='';
    for(var i=0;i<bytes.length;i++)binary+=String.fromCharCode(bytes[i]);
    return btoa(binary);
  }
  function generatedConfigBlock(key){
    return "<?php\n"+
      "// Local Microgifter server secrets. This file is ignored by Git.\n"+
      "// Keep this file private and do not paste this key into chat, GitHub, or email.\n"+
      "$mgPaymentCredentialKey = '"+key+"';\n"+
      "putenv('MG_PAYMENT_CREDENTIAL_KEY=' . $mgPaymentCredentialKey);\n\n"+
      "return [\n"+
      "    'payments' => [\n"+
      "        'credential_key' => $mgPaymentCredentialKey,\n"+
      "    ],\n"+
      "];\n";
  }
  function generateKey(){
    if(!keyOutput)return;
    var bytes=new Uint8Array(32);
    if(window.crypto&&window.crypto.getRandomValues){
      window.crypto.getRandomValues(bytes);
      keyOutput.textContent=generatedConfigBlock(base64Key(bytes));
      if(copyButton)copyButton.disabled=false;
      return;
    }
    keyOutput.textContent='This browser cannot generate a secure key. Use a modern browser or ask the host to generate a 32-byte base64 key.';
    if(copyButton)copyButton.disabled=true;
  }
  async function copyKeyBlock(){
    if(!keyOutput)return;
    try{
      await navigator.clipboard.writeText(keyOutput.textContent);
      if(copyButton)copyButton.textContent='Copied';
      setTimeout(function(){if(copyButton)copyButton.textContent='Copy config block'},1400);
    }catch(error){
      keyOutput.focus&&keyOutput.focus();
    }
  }
  async function loadCash(){
    if(!cashForm||!cashToggle||!window.Microgifter)return;
    setMessage(cashStatus,'Loading cash option…','loading');
    try{
      var response=await Microgifter.get('/api/admin/payment-methods.php');
      var data=response.data||response;
      cashToggle.checked=!!(data.payment_methods&&data.payment_methods.cash&&data.payment_methods.cash.enabled);
      setMessage(cashStatus,cashToggle.checked?'Cash payments are enabled for testing.':'Cash payments are disabled.','success')
    }catch(error){setMessage(cashStatus,error.message||'Unable to load cash payment setting.','error')}
  }
  async function saveCash(button){
    if(!cashForm||!cashToggle||!window.Microgifter)return;
    if(button)button.disabled=true;
    cashToggle.disabled=true;
    setMessage(cashStatus,'Saving cash option…','loading');
    try{
      var response=await Microgifter.post('/api/admin/payment-methods.php',{cash_enabled:cashToggle.checked?1:0});
      var data=response.data||response;
      cashToggle.checked=!!(data.payment_methods&&data.payment_methods.cash&&data.payment_methods.cash.enabled);
      setMessage(cashStatus,response.message||'Cash payment setting saved.','success')
    }catch(error){setMessage(cashStatus,error.message||'Unable to save cash payment setting.','error')}
    finally{cashToggle.disabled=false;if(button)button.disabled=false}
  }
  async function load(){
    if(!window.Microgifter){
      msg('Payment client is not loaded. Refresh the page and try again.','error');
      if(saveButton)saveButton.disabled=true;
      return;
    }
    msg('Loading payment settings…','loading');
    try{
      var response=await Microgifter.get('/api/admin/payment-settings.php?mode='+encodeURIComponent(mode.value));
      var data=response.data||response;
      fill(data);
      var blockers=currentBlockers(data);
      msg(blockers.length?'Payment settings loaded, but this mode still has key issues: '+blockers.join(' '):'Payment settings loaded.','success');
    }
    catch(error){msg(error.message||'Unable to load payment settings.','error');setCredentialState(null)}
  }
  async function saveSettings(){
    if(!window.Microgifter){msg('Payment client is not loaded. Refresh the page and try again.','error');return;}
    if(form.reportValidity&&!form.reportValidity()){msg('Please complete the required fields before saving.','error');return;}
    var payload=Object.fromEntries(new FormData(form).entries());
    payload.enabled=form.elements.enabled.checked;
    payload.platform_fee_bps=Number(payload.platform_fee_bps||0);
    payload.fixed_fee_cents=Number(payload.fixed_fee_cents||0);
    var validationError=validatePayload(payload);
    if(validationError){msg(validationError,'error');return;}
    setSaving(true,'Saving…');
    msg('Saving Stripe configuration…','loading');
    try{
      var response=await Microgifter.post('/api/admin/payment-settings.php',payload);
      var data=response.data||response;
      fill(data);
      if(data.save_warning){msg(data.save_warning,'error');}
      else{msg(response.message||'Stripe configuration saved successfully.','success');}
    }catch(error){
      msg(error.message||'Unable to save payment settings.','error');
    }finally{
      setSaving(false,'Save Stripe configuration');
    }
  }
  if(mode)mode.addEventListener('change',load);
  form.addEventListener('submit',function(event){event.preventDefault();saveSettings();});
  if(keyButton)keyButton.addEventListener('click',generateKey);
  if(copyButton)copyButton.addEventListener('click',copyKeyBlock);
  if(cashForm&&cashToggle){cashToggle.addEventListener('change',function(){saveCash(null)});cashForm.addEventListener('submit',function(event){event.preventDefault();saveCash(cashForm.querySelector('button[type="submit"]'))})}
  load();
  loadCash();
});
