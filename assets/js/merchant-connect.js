document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var panel=document.querySelector('[data-connect-panel]');
  if(!panel||!window.Microgifter)return;
  var facts=panel.querySelector('[data-connect-facts]');
  var status=panel.querySelector('[data-connect-status]');
  var onboard=panel.querySelector('[data-connect-onboard]');
  var sync=panel.querySelector('[data-connect-sync]');

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}

  function render(data){
    var account=data.account||{};
    var platform=data.platform||{};
    panel.classList.toggle('is-ready',!!account.ready);
    facts.innerHTML=[
      ['Mode',platform.mode||account.mode||'test'],
      ['Account',account.account_id||'Not created'],
      ['Onboarding',account.onboarding_status||'not started'],
      ['Charges',account.charges_enabled?'Enabled':'Disabled'],
      ['Payouts',account.payouts_enabled?'Enabled':'Disabled'],
      ['Platform share',Number(platform.platform_fee_bps||0)/100+'%']
    ].map(function(item){return '<div><span>'+esc(item[0])+'</span><strong>'+esc(item[1])+'</strong></div>';}).join('');
    if(account.requirements_due&&account.requirements_due.length){
      facts.innerHTML+='<div class="mg-connect-requirements"><span>Still required</span><strong>'+esc(account.requirements_due.join(', '))+'</strong></div>';
    }
    onboard.textContent=account.account_id?(account.ready?'Open Stripe onboarding':'Continue Stripe onboarding'):'Start Stripe onboarding';
    onboard.disabled=!platform.enabled||!platform.secret_configured;
    sync.disabled=!account.account_id;
    status.textContent=account.ready?'Stripe Connect is ready for customer payments.':(!platform.enabled?'The platform must enable Stripe first.':'Connect onboarding is incomplete.');
  }

  async function load(doSync){
    status.textContent=doSync?'Refreshing Stripe account…':'Loading Stripe account…';
    if(doSync){
      var response=await Microgifter.post('/api/merchant/payment-connect.php',{action:'sync'});
      return {account:(response.data||response).account,platform:(await Microgifter.get('/api/merchant/payment-account.php')).data.platform};
    }
    var response=await Microgifter.get('/api/merchant/payment-account.php');
    return response.data||response;
  }

  onboard.addEventListener('click',async function(){
    onboard.disabled=true;
    status.textContent='Creating secure Stripe onboarding link…';
    try{
      var response=await Microgifter.post('/api/merchant/payment-connect.php',{action:'onboard'});
      var account=(response.data||response).account||{};
      if(account.onboarding_url){location.href=account.onboarding_url;return;}
      status.textContent='Stripe onboarding link was not returned.';
    }catch(error){status.textContent=error.message;onboard.disabled=false;}
  });

  sync.addEventListener('click',function(){
    load(true).then(render).catch(function(error){status.textContent=error.message;});
  });

  var query=new URLSearchParams(location.search);
  load(query.has('connect')).then(render).catch(function(error){status.textContent=error.message;});
});
