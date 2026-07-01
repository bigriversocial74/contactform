(() => {
  'use strict';
  if (window.__mgLoadEnvelopeV2) return;
  window.__mgLoadEnvelopeV2 = true;
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
  function meta(row,label){const key=String(label||'').toLowerCase()+':';for(const span of row.querySelectorAll('.mg-gift-row-meta span')){const text=span.textContent.trim();if(text.toLowerCase().indexOf(key)===0)return text.slice(text.indexOf(':')+1).trim();}return '';}
  function openEnvelope(app,row){
    const drawer=app.querySelector('[data-gift-drawer]');
    const body=app.querySelector('[data-gift-drawer-content]');
    const titleNode=app.querySelector('[data-gift-drawer-title]');
    const backdrop=app.querySelector('[data-gift-drawer-backdrop]');
    if(!drawer||!body||!backdrop)return;
    const title=(row.querySelector('.mg-gift-row-main h3')||{}).textContent||'Microgift';
    const message=(row.querySelector('.mg-gift-row-main p')||{}).textContent||'A gift is waiting for you.';
    const value=meta(row,'Value')||'$0.00';
    const status=meta(row,'Status')||'Received';
    const giftId=meta(row,'Gift ID')||row.getAttribute('data-gift-id')||'';
    const type=meta(row,'Type')||'Gift envelope';
    if(titleNode)titleNode.textContent='Loaded gift envelope';
    body.innerHTML='<div class="mg-envelope-card" data-envelope-card data-envelope-state="closed"><section class="mg-envelope-stage"><div class="mg-envelope-book"><div class="mg-envelope-page mg-envelope-page-left"><div class="mg-envelope-media"></div><div class="mg-envelope-content mg-envelope-cover-content"><div class="mg-envelope-icon">✉</div><h2>'+esc(title)+'</h2><p>'+esc(type)+'</p><button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Gift</button></div></div><div class="mg-envelope-page mg-envelope-page-right"><div class="mg-envelope-inside-media"></div><div class="mg-envelope-content mg-envelope-inside"><span class="mg-eyebrow">Gift message</span><h3>'+esc(title)+'</h3><p>'+esc(message)+'</p><div class="mg-envelope-value">'+esc(value)+'</div></div></div></div></section><div class="mg-envelope-controls"><button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Card</button><button class="mg-envelope-close-button" type="button" data-envelope-action="hide">Close Card</button></div><div class="mg-envelope-voucher-strip"><div><span>Protected claim controls</span><strong>'+esc(status)+'</strong><small>Gift ID '+esc(giftId||'available after issue')+'. Product details stay in the main feed card.</small></div><div class="mg-envelope-claim-pill">'+esc(value)+'</div></div></div>';
    drawer.classList.add('is-open','mg-load-envelope-drawer');
    drawer.setAttribute('aria-hidden','false');
    backdrop.hidden=false;
    document.body.classList.add('mg-modal-lock');
    body.scrollTop=0;
  }
  document.addEventListener('click',event=>{
    const button=event.target.closest('[data-gift-action="load"]');
    if(!button)return;
    const app=document.querySelector('[data-gift-center]');
    const row=button.closest('[data-gift-id]');
    if(!app||!row||!app.contains(row))return;
    event.preventDefault();event.stopPropagation();event.stopImmediatePropagation();
    openEnvelope(app,row);
  },true);
})();
