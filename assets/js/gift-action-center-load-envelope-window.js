(() => {
  'use strict';
  if (window.__mgLoadEnvelopeWindow) return;
  window.__mgLoadEnvelopeWindow = true;
  const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function meta(row,label){const key=String(label||'').toLowerCase()+':';for(const span of row.querySelectorAll('.mg-gift-row-meta span')){const text=span.textContent.trim();if(text.toLowerCase().indexOf(key)===0)return text.slice(text.indexOf(':')+1).trim();}return '';}
  function load(app,row){
    const drawer=app.querySelector('[data-gift-drawer]'),body=app.querySelector('[data-gift-drawer-content]'),head=app.querySelector('[data-gift-drawer-title]'),backdrop=app.querySelector('[data-gift-drawer-backdrop]');
    if(!drawer||!body||!backdrop)return;
    const title=(row.querySelector('.mg-gift-row-main h3')||{}).textContent||'Microgift',msg=(row.querySelector('.mg-gift-row-main p')||{}).textContent||'A gift is waiting for you.',value=meta(row,'Value')||'$0.00',status=meta(row,'Status')||'Received',giftId=meta(row,'Gift ID')||row.dataset.giftId||'',type=meta(row,'Type')||'Gift envelope';
    if(head)head.textContent='Loaded gift envelope';
    body.innerHTML='<div class="mg-envelope-card" data-envelope-card data-envelope-state="closed"><section class="mg-envelope-stage"><div class="mg-envelope-book"><div class="mg-envelope-page mg-envelope-page-left"><div class="mg-envelope-media"></div><div class="mg-envelope-content mg-envelope-cover-content"><div class="mg-envelope-icon">✉</div><h2>'+esc(title)+'</h2><p>'+esc(type)+'</p><button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Gift</button></div></div><div class="mg-envelope-page mg-envelope-page-right"><div class="mg-envelope-inside-media"></div><div class="mg-envelope-content mg-envelope-inside"><span class="mg-eyebrow">Gift message</span><h3>'+esc(title)+'</h3><p>'+esc(msg)+'</p><div class="mg-envelope-value">'+esc(value)+'</div></div></div></div></section><div class="mg-envelope-controls"><button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Card</button><button class="mg-envelope-close-button" type="button" data-envelope-action="hide">Close Card</button></div><div class="mg-envelope-voucher-strip"><div><span>Protected claim controls</span><strong>'+esc(status)+'</strong><small>Gift ID '+esc(giftId||'available after issue')+'. Product details stay in the main feed card.</small></div><div class="mg-envelope-claim-pill">'+esc(value)+'</div></div></div>';
    drawer.classList.add('is-open','mg-load-envelope-drawer');drawer.setAttribute('aria-hidden','false');backdrop.hidden=false;document.body.classList.add('mg-modal-lock');body.scrollTop=0;
  }
  window.addEventListener('click',e=>{const b=e.target.closest('[data-gift-action="load"]');if(!b)return;const app=document.querySelector('[data-gift-center]'),row=b.closest('[data-gift-id]');if(!app||!row||!app.contains(row))return;e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();load(app,row);},true);
})();
