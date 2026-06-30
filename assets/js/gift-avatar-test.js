document.addEventListener('DOMContentLoaded',function(){
  var list=document.querySelector('[data-gift-list]');
  if(!list)return;
  function ids(){return Array.from(list.querySelectorAll('[data-gift-id]')).map(function(row){return row.getAttribute('data-gift-id')||'';}).filter(Boolean);}
  function hydrate(){var found=ids();if(!found.length)return;fetch('/api/account/action-center-merchant-avatar.php?ids='+encodeURIComponent(found.join(',')),{credentials:'same-origin'}).then(function(response){return response.json();}).then(function(payload){console.log(payload);}).catch(function(error){console.error(error);});}
  new MutationObserver(hydrate).observe(list,{childList:true});
  hydrate();
});
