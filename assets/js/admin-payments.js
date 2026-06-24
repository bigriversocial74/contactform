document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-admin-payments]');
  if(!root||!window.Microgifter)return;
  var form=root.querySelector('[data-payment-settings-form]'),
      mode=form.querySelector('[data-payment-mode]'),
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
      keyOutput=root.querySelector('[data-payment-key-output]');

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function setMessage(node,text,type){if(!node)return;node.textContent=text||'';node.classList.toggle('is-error',type==='error');node.classList.toggle('is-success',type==='success')}
  function msg(text,type){setMessage(status,text,type)}
  function clearSecrets(){if(form.elements.secret_key)form.elements.secret_key.value='';if(form.elements.webhook_secret)form.elements.webhook_secret.value=''}
  function setCredentialState(check){
    if(!credentialState)return;
    var ok=!!(check&&check.ok);
    credentialState.classList.toggle('is-ready',ok);
    credentialState.classList.toggle('is-missing',!ok);
    credentialState.textContent=ok?'Credential encryption is ready. You can save Stripe secret values.':((check&&check.detail)||'MG_PAYMENT_CREDENTIAL_KEY is missing. Add api/config.local.php before saving Stripe secret values.');
  }
  function fill(data){
    var provider=data.provider||{};
    form.elements.enabled.checked=!!provider.enabled;
    form.elements.publishable_key.value=provider.publishable_key||'';
    form.elements.connect_client_id.value=provider.connect_client_id||'';
    form.elements.platform_fee_bps.value=Number(provider.platform_fee_bps||1500);
    form.elements.fixed_fee_cents.value=Number(provider.fixed_fee_cents||0);
    clearSecrets();
    badge.textContent=data.ready?'Ready for '+provider.mode:'Not ready for '+provider.mode;
    badge.classList.toggle('is-ready',!!data.ready);
    badge.classList.toggle('is-missing',!data.ready);
    checks.innerHTML=Object.keys(data.checks||{}).map(function(key){var item=data.checks[key];return '<article class="mg-payment-check '+(item.ok?'is-ready':'is-missing')+'"><span>'+(item.ok?'✓':'!')+'</span><div><strong>'+esc(item.label)+'</strong><p>'+esc(item.detail)+'</p></div></article>'}).join('');
    webhook.textContent=data.webhook_url||'';
    var connected=data.connected_accounts||{};
    accounts.innerHTML='<strong>Connected accounts</strong><span>'+Number(connected.ready||0)+' ready of '+Number(connected.total||0)+' total</span><small>Credential source: '+esc(provider.credential_source||'missing')+' · secret '+(provider.secret_configured?'configured':'missing')+' · webhook '+(provider.webhook_configured?'configured':'missing')+'</small>';
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
    if(!cashForm||!cashToggle)return;
    setMessage(cashStatus,'Loading cash option…');
    try{
      var response=await Microgifter.get('/api/admin/payment-methods.php');
      var data=response.data||response;
      cashToggle.checked=!!(data.payment_methods&&data.payment_methods.cash&&data.payment_methods.cash.enabled);
      setMessage(cashStatus,cashToggle.checked?'Cash payments are enabled for testing.':'Cash payments are disabled.','success')
    }catch(error){setMessage(cashStatus,error.message||'Unable to load cash payment setting.','error')}
  }
  async function saveCash(button){
    if(!cashForm||!cashToggle)return;
    if(button)button.disabled=true;
    cashToggle.disabled=true;
    setMessage(cashStatus,'Saving cash option…');
    try{
      var response=await Microgifter.post('/api/admin/payment-methods.php',{cash_enabled:cashToggle.checked?1:0});
      var data=response.data||response;
      cashToggle.checked=!!(data.payment_methods&&data.payment_methods.cash&&data.payment_methods.cash.enabled);
      setMessage(cashStatus,response.message||'Cash payment setting saved.','success')
    }catch(error){setMessage(cashStatus,error.message||'Unable to save cash payment setting.','error')}
    finally{cashToggle.disabled=false;if(button)button.disabled=false}
  }
  async function load(){
    msg('Loading…');
    try{var response=await Microgifter.get('/api/admin/payment-settings.php?mode='+encodeURIComponent(mode.value));fill(response.data||response);msg('')}
    catch(error){msg(error.message||'Unable to load payment settings.','error');setCredentialState(null)}
  }
  mode.addEventListener('change',load);
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var payload=Object.fromEntries(new FormData(form).entries());
    payload.enabled=form.elements.enabled.checked;
    payload.platform_fee_bps=Number(payload.platform_fee_bps||0);
    payload.fixed_fee_cents=Number(payload.fixed_fee_cents||0);
    msg('Saving…');
    try{var response=await Microgifter.post('/api/admin/payment-settings.php',payload);fill(response.data||response);msg(response.message||'Stripe payment settings saved.','success')}
    catch(error){msg(error.message||'Unable to save payment settings.','error')}
  });
  if(keyButton)keyButton.addEventListener('click',generateKey);
  if(copyButton)copyButton.addEventListener('click',copyKeyBlock);
  if(cashForm&&cashToggle){cashToggle.addEventListener('change',function(){saveCash(null)});cashForm.addEventListener('submit',function(event){event.preventDefault();saveCash(cashForm.querySelector('button[type="submit"]'))})}
  load();
  loadCash();
});
