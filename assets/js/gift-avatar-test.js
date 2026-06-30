document.addEventListener('DOMContentLoaded',function(){
  var list=document.querySelector('[data-gift-list]');
  if(!list)return;
  var cache=Object.create(null),busy=false;
  function ids(){return Array.from(list.querySelectorAll('[data-gift-id]')).map(function(row){return row.getAttribute('data-gift-id')||'';}).filter(Boolean);}
  function apply(){Array.from(list.querySelectorAll('[data-gift-id]')).forEach(function(row){var item=cache[row.getAttribute('data-gift-id')];if(!item||!item.merchant_avatar_url)return;var box=row.querySelector('.mg-gift-thumb');if(!box||box.dataset.avatarReady==='true')return;box.textContent='';var image=document.createElement('img');image.src=item.merchant_avatar_url;image.alt=(item.merchant_name||'Merchant')+' profile';box.appendChild(image);box.classList.add('has-merchant-avatar');box.dataset.avatarReady='true';box.removeAttribute('aria-hidden');});}
  function hydrate(){if(busy)return;var found=ids().filter(function(id){return !cache[id];});if(!found.length){apply();return;}busy=true;fetch('/api/account/action-center-merchant-avatar.php?ids='+encodeURIComponent(found.join(',')),{credentials:'same-origin'}).then(function(response){return response.json();}).then(function(payload){var data=payload.data||payload;Object.keys(data.items||{}).forEach(function(id){cache[id]=data.items[id]||{};});apply();}).catch(function(error){console.error(error);}).finally(function(){busy=false;});}
  new MutationObserver(hydrate).observe(list,{childList:true});
  hydrate();
});
