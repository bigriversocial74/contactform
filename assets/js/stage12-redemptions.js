document.addEventListener('DOMContentLoaded',function(){
  var root=document.querySelector('[data-stage12-redemptions]');
  if(!root||!window.Microgifter){return;}
  var form=root.querySelector('form');
  var status=root.querySelector('[data-redemption-status]');
  function note(message){if(status){status.textContent=message||'';}}
  form&&form.addEventListener('submit',async function(event){
    event.preventDefault();
    var data=Object.fromEntries(new FormData(form).entries());
    try{note('Saving...');var response=await Microgifter.post('/api/merchant/wallet-redeem.php',data);note(response.message||'Saved.');form.reset();}
    catch(error){note(error.message||'Unable to save.');}
  });
});
