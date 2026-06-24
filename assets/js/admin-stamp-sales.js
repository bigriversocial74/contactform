document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var list=document.querySelector('[data-admin-stamp-purchase-list]');
  function esc(v){return String(v==null?'':v).replace(/[&<>'\"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'})[c];});}
  async function load(){
    if(!list)return;
    try{
      var r=await Microgifter.get('/api/stamps/purchase-report.php');
      var items=(r.data||r).purchases||[];
      list.innerHTML=items.length?items.map(function(p){return '<tr><td>'+esc(p.id)+'</td><td>'+Number(p.account_user_id||0)+'</td><td>'+esc(p.label||p.bundle_key)+'</td><td>'+Number(p.stamps||0).toLocaleString()+'</td><td>'+esc(p.status)+'</td><td>'+esc(p.credited_ledger_entry_id||'')+'</td></tr>';}).join(''):'<tr><td colspan="6">No records found.</td></tr>';
    }catch(e){list.innerHTML='<tr><td colspan="6">Report unavailable.</td></tr>';}
  }
  load();
});
