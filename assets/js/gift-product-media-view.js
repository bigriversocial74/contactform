document.addEventListener('DOMContentLoaded',function(){
  var app=document.querySelector('[data-gift-center]');
  if(!app)return;
  var list=app.querySelector('[data-gift-list]');
  var drawer=app.querySelector('[data-gift-drawer-content]');
  if(!list)return;
  var cache=Object.create(null),busy=false,pending='';
  function ids(){return Array.from(list.querySelectorAll('[data-gift-id]')).map(function(row){return row.getAttribute('data-gift-id')||'';}).filter(Boolean);}
  function icon(kind){return kind==='audio'?'♪':kind==='video'?'▶':kind==='download'?'↓':'▣';}
  function apply(){Array.from(list.querySelectorAll('[data-gift-id]')).forEach(function(row){var item=cache[row.getAttribute('data-gift-id')];if(!item)return;var box=row.querySelector('.mg-gift-thumb');if(!box||box.dataset.productMediaReady==='true')return;box.textContent='';box.classList.remove('has-merchant-avatar');if(item.cover_url){var image=document.createElement('img');image.src=item.cover_url;image.alt=(item.title||'Gift')+' product image';box.appendChild(image);box.classList.add('has-product-media');}else if(item.primary_media_kind&&item.primary_media_kind!=='none'){box.textContent=icon(item.primary_media_kind);box.classList.add('has-product-media-kind');}else{box.textContent=String(item.title||'G').charAt(0).toUpperCase();}box.dataset.productMediaReady='true';box.removeAttribute('aria-hidden');});}
  function inject(id){if(!drawer||!id||!cache[id]||!cache[id].cover_url)return;var old=drawer.querySelector('[data-product-media-drawer]');if(old)old.remove();var panel=document.createElement('section');panel.className='mg-product-media-drawer';panel.setAttribute('data-product-media-drawer','');var label=document.createElement('span');label.className='mg-eyebrow';label.textContent='Product media';var h=document.createElement('h3');h.textContent=cache[id].title||'Gift media';var fig=document.createElement('figure');fig.className='mg-product-media-asset';var img=document.createElement('img');img.src=cache[id].cover_url;img.alt=h.textContent;var cap=document.createElement('figcaption');cap.textContent='Product image';fig.appendChild(img);fig.appendChild(cap);panel.appendChild(label);panel.appendChild(h);panel.appendChild(fig);drawer.insertBefore(panel,drawer.firstChild);}
  function load(wanted){var missing=wanted.filter(function(id){return id&&!cache[id];});if(!missing.length){apply();if(pending)inject(pending);return Promise.resolve();}return fetch('/api/account/action-center-product-media.php?ids='+encodeURIComponent(missing.join(',')),{credentials:'same-origin'}).then(function(response){return response.json();}).then(function(payload){var data=payload.data||payload;Object.keys(data.items||{}).forEach(function(id){cache[id]=data.items[id]||{};});apply();if(pending)inject(pending);}).catch(function(error){console.error(error);});}
  function run(){if(busy)return;busy=true;load(ids()).finally(function(){busy=false;});}
  new MutationObserver(function(){window.requestAnimationFrame(run);}).observe(list,{childList:true});
  app.addEventListener('click',function(event){var button=event.target.closest('[data-gift-action="load"]'),row=event.target.closest('[data-gift-id]');if(!button||!row)return;pending=row.getAttribute('data-gift-id')||'';window.setTimeout(function(){load([pending]).then(function(){inject(pending);});},150);},true);
  run();
});
