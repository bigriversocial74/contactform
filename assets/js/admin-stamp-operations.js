document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var adjustmentForm=document.querySelector('[data-admin-stamp-adjustment-form]');
  var adjustmentStatus=document.querySelector('[data-admin-stamp-adjustment-status]');
  var voidForm=document.querySelector('[data-admin-stamp-void-form]');
  var voidStatus=document.querySelector('[data-admin-stamp-void-status]');
  function setStatus(el,msg,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(el,msg,type);return;}if(el)el.textContent=msg||'';}
  function key(prefix){return prefix+'-'+Date.now()+'-'+Math.random().toString(16).slice(2);}
  if(adjustmentForm){adjustmentForm.addEventListener('submit',async function(ev){ev.preventDefault();var data=Object.fromEntries(new FormData(adjustmentForm).entries());data.idempotency_key=key('admin-adjustment');data.allow_negative=adjustmentForm.elements.allow_negative&&adjustmentForm.elements.allow_negative.checked?1:0;try{setStatus(adjustmentStatus,'Recording Stamp adjustment...');var r=await Microgifter.post('/api/stamps/adjustment.php',data);var entry=(r.data&&r.data.entry)||{};setStatus(adjustmentStatus,(r.message||'Adjustment recorded.')+' Balance: '+(entry.balance_after==null?'updated':entry.balance_after),'success');adjustmentForm.reset();}catch(error){setStatus(adjustmentStatus,error.message||'Unable to record adjustment.','error');}});}
  if(voidForm){voidForm.addEventListener('submit',async function(ev){ev.preventDefault();var data=Object.fromEntries(new FormData(voidForm).entries());data.idempotency_key=key('admin-void');try{setStatus(voidStatus,'Voiding Stamp debit...');var r=await Microgifter.post('/api/stamps/void.php',data);var entry=(r.data&&r.data.entry)||{};setStatus(voidStatus,(r.message||'Debit voided.')+' Balance: '+(entry.balance_after==null?'updated':entry.balance_after),'success');voidForm.reset();}catch(error){setStatus(voidStatus,error.message||'Unable to void debit.','error');}});}
});
