document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var root=document.querySelector('[data-stage12-redemptions]');
  if(!root){return;}
  var form=root.querySelector('[data-redemption-form]');
  var status=root.querySelector('[data-redemption-status]');
  function note(message){if(status){status.textContent=message||'';}}
  if(form){form.addEventListener('submit',async function(event){
    event.preventDefault();
    var data=Object.fromEntries(new FormData(form).entries());
    try{note('Completing wallet item...');var response=await Microgifter.post('/api/merchant/wallet-redeem.php',data);note(response.message||'Wallet item completed.');form.reset();}
    catch(error){note(error.message||'Unable to complete wallet item.');}
  });}
});
