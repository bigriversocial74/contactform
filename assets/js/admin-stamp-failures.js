document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var list=document.querySelector('[data-admin-stamp-failure-list]');
  function esc(v){return String(v==null?'':v).replace(/[&<>'\"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'})[c];});}
  async function load(){
    if(!list)return;
    try{
      var r=await Microgifter.get('/api/stamps/delivery-failure-report.php');
      var items=(r.data||r).failures||[];
      list.innerHTML=items.length?items.map(function(f){return '<tr><td>'+esc(f.created_at)+'</td><td>'+Number(f.account_user_id||0)+'</td><td>'+esc(f.reason_code||'')+'</td><td>'+Number(f.delta||0).toLocaleString()+'</td><td>'+esc(f.source_id||'')+'</td><td>'+esc(f.note||'')+'</td></tr>';}).join(''):'<tr><td colspan="6">No delivery failure returns found.</td></tr>';
    }catch(e){list.innerHTML='<tr><td colspan="6">Delivery failure report unavailable.</td></tr>';}
  }
  load();
});
