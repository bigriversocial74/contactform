(() => {
'use strict';
if(window.__mgActionCenterRuntimeV4Booted)return;
window.__mgActionCenterRuntimeV4Booted=true;
const FOLDERS=['inbox','sent','claimed'],PAGE_SIZE=15;
function boot(){
 const app=document.querySelector('[data-gift-center]'),list=app&&app.querySelector('[data-gift-list]');
 if(!app||!list)return;
 const pagination=app.querySelector('[data-gift-feed-pagination]');
 const loadMore=pagination&&pagination.querySelector('[data-gift-load-more]');
 const endState=pagination&&pagination.querySelector('[data-gift-feed-end]');
 const refresh=app.querySelector('[data-gift-refresh]');
 const drawer=app.querySelector('[data-gift-drawer]');
 const drawerContent=app.querySelector('[data-gift-drawer-content]');
 const drawerTitle=app.querySelector('[data-gift-drawer-title]');
 const drawerBackdrop=app.querySelector('[data-gift-drawer-backdrop]');
 const drawerClose=app.querySelector('[data-gift-drawer-close]');
 const modal=app.querySelector('[data-action-modal]');
 const modalBody=app.querySelector('[data-action-modal-body]');
 const modalTitle=app.querySelector('[data-action-modal-title]');
 const modalEyebrow=app.querySelector('[data-action-modal-eyebrow]');
 const modalBackdrop=app.querySelector('[data-action-modal-backdrop]');
 const state={
  folder:FOLDERS.includes(app.dataset.initialFolder)?app.dataset.initialFolder:'inbox',
  contracts:new Map(),order:[],page:{has_more:false,next_cursor:null},
  counts:{inbox:{total:0,unread:0},sent:{total:0,unread:0},claimed:{total:0,unread:0}},
  loading:false,selectedId:'',demoEnabled:app.dataset.demoEnabled==='true'
 };
 const object=v=>v&&typeof v==='object'&&!Array.isArray(v)?v:{};
 const text=(v,f='')=>String(v==null?'':v).trim()||String(f==null?'':f);
 const bool=v=>v===true||v===1||v==='1'||v==='true';
 const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
 function safeUrl(v){
  const raw=text(v);if(!raw||/[\u0000-\u001f\u007f]/.test(raw))return '';
  try{
   const u=new URL(raw,location.origin);
   if(!['http:','https:'].includes(u.protocol)||u.username||u.password)return '';
   if(raw.startsWith('/'))return raw.startsWith('//')||u.origin!==location.origin?'':u.pathname+u.search+u.hash;
   return u.href;
  }catch(e){return '';}
 }
 function money(cents,currency){
  try{return new Intl.NumberFormat(undefined,{style:'currency',currency:text(currency,'USD').toUpperCase()}).format(Math.max(0,Number(cents||0))/100);}
  catch(e){return text(currency,'USD').toUpperCase()+' '+(Math.max(0,Number(cents||0))/100).toFixed(2);}
 }
 function relativeTime(v){
  if(!v)return 'Recently';const d=new Date(v);if(Number.isNaN(d.getTime()))return text(v,'Recently');
  const s=Math.max(0,Math.floor((Date.now()-d.getTime())/1000));if(s<60)return 'Just now';
  const m=Math.floor(s/60);if(m<60)return m+'m ago';const h=Math.floor(m/60);if(h<24)return h+'h ago';
  const days=Math.floor(h/24);if(days<7)return days+'d ago';
  return d.toLocaleDateString(undefined,{month:'short',day:'numeric',year:d.getFullYear()!==new Date().getFullYear()?'numeric':undefined});
 }
 function isContract(item){
  const a=window.MicrogifterActionCenterContract;
  return a&&typeof a.isContract==='function'?a.isContract(item):Number(item&&item.contract_version)===2&&item.kind==='action_center_gift'&&!!object(object(item).gift).snapshot;
 }
 function contractFrom(v){return isContract(v)?v:(isContract(v&&v._contract)?v._contract:null);}
 function viewFrom(c){const a=window.MicrogifterActionCenterContract;return a&&typeof a.view==='function'?a.view(c):{};}
 function parts(c){const gift=object(c.gift);return{gift,snapshot:object(gift.snapshot),presentation:object(c.presentation),linked:object(c.linked_resource),source:object(c.source),participants:object(c.participants),merchant:object(c.merchant),location:object(c.location),redemption:object(c.redemption),activity:object(c.activity),capabilities:object(c.capabilities),reasons:object(c.capability_reasons),media:object(c.media),flags:object(c.flags)};}
 const titleFor=c=>text(parts(c).snapshot.title,'Microgift');
 const messageFor=c=>text(parts(c).snapshot.description,'Gift ready to open');
 function merchantFor(c){const p=parts(c);return text(p.merchant.name,text(object(p.participants.sender).name,'Microgifter'));}
 function senderFor(c){const p=parts(c);return text(object(p.participants.sender).name,merchantFor(c));}
 const recipientFor=c=>text(object(parts(c).participants.recipient).name,'Recipient');
 function timestampFor(c){const a=parts(c).activity;if(state.folder==='inbox')return a.received_at||a.sent_at||a.updated_at||'';if(state.folder==='sent')return a.sent_at||a.last_delivery_at||a.updated_at||'';return a.redeemed_at||a.claimed_at||a.updated_at||'';}
 function statusFor(c){const g=parts(c).gift;return text(g.state,text(g.status,state.folder)).replace(/[_-]+/g,' ');}
 const capability=(c,n)=>bool(parts(c).capabilities[n]);
 const reason=(c,n,f)=>text(parts(c).reasons[n],f);
 function demoContract(id,folder,title,merchant,value,message,opt={}){
  const now=new Date().toISOString(),st=opt.state||(folder==='claimed'?'redeemed':(folder==='sent'?'claimable':'redeemable'));
  return{contract_version:2,kind:'action_center_gift',action_item_id:id,folder,
   gift:{id:'MG-'+id.toUpperCase(),template_id:null,template_type:'demo',status:st,state:st,snapshot:{title,description:message,value_cents:value,currency:'USD',expires_at:null}},
   presentation:{title_source:'gift_snapshot',image_url:null,image_source:'none'},linked_resource:null,
   source:{system:'action_center',type:'demo_preview',label:'Action Center Demo',detail:'Safe Super Admin preview',reference:id},
   participants:{sender:{name:opt.sender||merchant},recipient:{name:opt.recipient||'Super Admin'}},merchant:{name:merchant,avatar_url:null},
   location:{public_id:null,name:opt.location||'Phoenix, AZ'},redemption:{public_id:null,status:folder==='claimed'?'redeemed':null,redeemed_at:folder==='claimed'?now:null},
   activity:{received_at:folder==='inbox'?now:null,sent_at:folder==='sent'?now:null,claimed_at:folder==='claimed'?now:null,redeemed_at:folder==='claimed'?now:null,updated_at:now,last_delivery_at:folder==='sent'?now:null,resend_count:0,last_follow_up_at:null,follow_up_count:0,read_at:null},
   capabilities:{send:folder==='inbox',claim:folder==='inbox',redeem:false,follow_up:folder==='sent',message:folder==='claimed',tip:folder==='claimed',load:true},capability_reasons:{},
   media:{posts:[{type:'message',title,body:message,meta:'Safe demo content'},{type:'offer',title:money(value,'USD')+' voucher',body:'Protected demo voucher. No transaction is created.',meta:'Demo offer'}],count:2,has_media:true},
   flags:{wallet_fallback:false,demo_preview:true,system_demo:true}};
 }
 function demoItems(folder){
  if(folder==='inbox')return[demoContract('demo-coffee-001','inbox','Coffee for two','Local Coffee House',2500,'A local coffee experience with a protected voucher underneath.'),demoContract('demo-music-002','inbox','Dinner and a playlist','Roosevelt Row Kitchen',5000,'A dinner voucher delivered with a music experience.')];
  if(folder==='sent')return[demoContract('demo-sent-001','sent','Neighborhood bookstore credit','Changing Hands Bookstore',3000,'A book recommendation and store voucher sent together.',{recipient:'Jordan Lee'})];
  return[demoContract('demo-claimed-001','claimed','Farmers market basket','Uptown Farmers Market',4000,'Successfully redeemed at an authorized merchant location.')];
 }
 function payload(r){return r&&r.data&&typeof r.data==='object'?r.data:r;}
 async function rawGet(path){
  if(window.Microgifter&&typeof window.Microgifter.api==='function')return window.Microgifter.api(path,{method:'GET'});
  const r=await fetch(path,{method:'GET',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
  let d={};try{d=await r.json();}catch(e){}
  if(!r.ok)throw new Error(d&&(d.message||d.error)||'Unable to load Action Center.');return d;
 }

 function setCounts(counts){
  FOLDERS.forEach(folder=>{
   const incoming=object(counts&&counts[folder]);
   state.counts[folder]={total:Math.max(0,Number(incoming.total||(counts&&counts[folder])||0)),unread:Math.max(0,Number(incoming.unread||0))};
   document.querySelectorAll('[data-gift-count="'+folder+'"],[data-gift-nav-count="'+folder+'"]').forEach(n=>n.textContent=String(state.counts[folder].total));
   document.querySelectorAll('[data-gift-nav-unread="'+folder+'"]').forEach(n=>{const v=state.counts[folder].unread;n.textContent=String(v);n.hidden=v<=0;n.classList.toggle('has-unread',v>0);});
  });
 }
 function updatePagination(){
  if(!pagination||!loadMore||!endState)return;
  if(!state.order.length){pagination.hidden=true;loadMore.hidden=true;endState.hidden=true;return;}
  pagination.hidden=false;loadMore.hidden=!state.page.has_more;endState.hidden=state.page.has_more;
  loadMore.disabled=state.loading;loadMore.textContent=state.loading?'Loading…':'Load 15 more gifts';endState.textContent='No more gifts to show.';
 }
 function icon(type){
  const p={sender:'<circle cx="12" cy="8" r="3"/><path d="M5.5 20c.8-4 3-6 6.5-6s5.7 2 6.5 6"/>',time:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',source:'<path d="M4 7h16M4 12h16M4 17h10"/>'};
  return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'+p[type]+'</svg>';
 }
 function actionButton(c,action,label,cap,fallback){
  const enabled=capability(c,cap),why=enabled?'':reason(c,cap,fallback);
  return '<button class="mg-gift-row-action'+(['send','follow-up','message'].includes(action)?' is-primary':'')+'" type="button" data-gift-action="'+esc(action)+'"'+(enabled?'':' disabled aria-disabled="true" title="'+esc(why)+'"')+'>'+esc(label)+'</button>';
 }
 function actionsMarkup(c){
  if(state.folder==='inbox')return actionButton(c,'send','Regift','send','This gift cannot be transferred.')+actionButton(c,'claim','Claim','claim','This gift cannot be claimed.')+actionButton(c,'load','Load','load','Gift content is unavailable.');
  if(state.folder==='sent')return actionButton(c,'follow-up','Follow Up','follow_up','Only the most recent sender can follow up.')+actionButton(c,'load','Load','load','Gift content is unavailable.');
  return actionButton(c,'message','Message','message','Messaging is unavailable for this gift.')+actionButton(c,'tip','Tip','tip','Tip is unavailable for this gift.')+actionButton(c,'load','Load','load','Gift content is unavailable.');
 }
 function rowMarkup(c){
  const p=parts(c),id=text(c.action_item_id),title=titleFor(c),merchant=merchantFor(c),sender=senderFor(c),timestamp=timestampFor(c),sourceLabel=text(p.source.label,'Microgifter');
  const image=safeUrl(p.presentation.image_url||p.merchant.avatar_url),unread=!p.activity.read_at&&state.folder==='inbox',active=state.selectedId===id,demo=bool(p.flags.demo_preview)||bool(p.flags.system_demo);
  const followMeta=state.folder==='sent'?(p.activity.last_follow_up_at?'<span>Last Follow Up: '+esc(relativeTime(p.activity.last_follow_up_at))+'</span>':'')+(Number(p.activity.follow_up_count||0)>0?'<span>Follow Ups: '+esc(String(Number(p.activity.follow_up_count||0)))+'</span>':''):'';
  const classes=['mg-gift-row','mg-gift-card-v3','mg-action-center-contract-v2',active?'is-active':'',unread?'is-unread':'',demo?'is-demo':''].filter(Boolean).join(' ');
  const thumb=image?'<img src="'+esc(image)+'" alt="" loading="lazy">':'<span>'+esc(title.charAt(0).toUpperCase()||'M')+'</span>';
  return '<article class="'+classes+'" data-gift-id="'+esc(id)+'" data-contract-version="2" data-gift-source-system="'+esc(p.source.system||'')+'" data-gift-source-label="'+esc(sourceLabel)+'" data-gift-source-detail="'+esc(p.source.detail||'')+'" data-gift-source-reference="'+esc(p.source.reference||'')+'">'+
   '<div class="mg-gift-thumb mg-gift-card-v3-thumb" aria-hidden="true">'+thumb+'</div>'+
   '<div class="mg-gift-row-main mg-gift-card-v3-copy"><div class="mg-gift-card-v3-title"><h3>'+esc(title)+'</h3><span class="mg-gift-status">'+esc(statusFor(c))+'</span></div>'+
   '<span class="mg-gift-business-name">'+esc(merchant)+'</span><p class="mg-gift-card-message">'+esc(messageFor(c))+'</p>'+
   '<div class="mg-gift-row-meta mg-gift-card-v3-meta">'+
    '<span class="mg-feed-meta-item is-sender">'+icon('sender')+'<span>'+esc(state.folder==='sent'?'To: '+recipientFor(c):'From: '+sender)+'</span></span>'+
    '<span class="mg-feed-meta-item is-time">'+icon('time')+'<span>'+esc(relativeTime(timestamp))+'</span></span>'+
    '<span class="mg-feed-meta-item is-source">'+icon('source')+'<span>'+esc(sourceLabel)+'</span></span>'+followMeta+
   '</div></div><div class="mg-gift-row-actions mg-gift-card-v3-actions" aria-label="Gift actions">'+actionsMarkup(c)+'</div></article>';
 }
 function render(){
  if(state.loading&&!state.order.length){list.innerHTML='<div class="mg-gift-empty-list"><strong>Loading gifts…</strong><p>Reading the Action Center contract.</p></div>';updatePagination();return;}
  if(!state.order.length){list.innerHTML='<div class="mg-gift-empty-list"><strong>No '+esc(state.folder)+' gifts</strong><p>Items matching this folder will appear here.</p></div>';updatePagination();return;}
  list.innerHTML=state.order.map(id=>state.contracts.has(id)?rowMarkup(state.contracts.get(id)):'').join('');
  list.dataset.actionCenterRuntime='4';updatePagination();
  document.dispatchEvent(new CustomEvent('mg:action-center:rendered',{detail:{folder:state.folder,count:state.order.length,contract_version:2}}));
 }
 function addContracts(items,reset){
  if(reset){state.contracts.clear();state.order=[];}
  (Array.isArray(items)?items:[]).forEach(item=>{const c=contractFrom(item),id=text(c&&c.action_item_id);if(!c||!id)return;if(!state.contracts.has(id))state.order.push(id);state.contracts.set(id,c);});
 }
 async function load(reset){
  if(state.loading)return;const append=!reset;if(append&&(!state.page.has_more||!state.page.next_cursor))return;
  state.loading=true;if(reset){state.page={has_more:false,next_cursor:null};render();}else updatePagination();
  let path='/api/account/action-center.php?folder='+encodeURIComponent(state.folder)+'&limit='+PAGE_SIZE;if(append)path+='&cursor='+encodeURIComponent(state.page.next_cursor);
  try{
   const data=payload(await rawGet(path))||{};if(Number(data.contract_version||0)!==2)throw new Error('Unsupported Action Center response.');
   addContracts(data.items,reset);if(reset&&!state.order.length&&state.demoEnabled)addContracts(demoItems(state.folder),true);
   const page=object(data.page);state.page={has_more:bool(page.has_more),next_cursor:text(page.next_cursor)||null};setCounts(data.counts||state.counts);render();
   app.dispatchEvent(new CustomEvent('mg:action-center:loaded',{bubbles:true,detail:{folder:state.folder,contracts:state.order.map(id=>state.contracts.get(id)),page:Object.assign({},state.page),counts:state.counts}}));
  }catch(error){
   if(!state.order.length&&state.demoEnabled){addContracts(demoItems(state.folder),true);state.page={has_more:false,next_cursor:null};render();}
   else if(!state.order.length)list.innerHTML='<div class="mg-gift-empty-list is-error"><strong>Unable to load gifts</strong><p>'+esc(error&&error.message||'Please refresh and try again.')+'</p></div>';
   if(window.Microgifter&&window.Microgifter.toast)window.Microgifter.toast(error&&error.message||'Unable to load Action Center.','error');
  }finally{state.loading=false;updatePagination();}
 }
 const currentContract=id=>state.contracts.get(text(id))||null;
 const currentView=id=>{const c=currentContract(id);return c?viewFrom(c):null;};
 function select(id){state.selectedId=text(id);list.querySelectorAll(':scope > [data-gift-id]').forEach(row=>row.classList.toggle('is-active',row.dataset.giftId===state.selectedId));}
 function closeDrawer(){if(!drawer)return;drawer.classList.remove('is-open','mg-load-envelope-drawer');drawer.setAttribute('aria-hidden','true');if(drawerBackdrop)drawerBackdrop.hidden=true;document.body.classList.remove('mg-modal-lock');}
 function mediaPostMarkup(post,index,total,fallbackTitle){
  post=object(post);const type=text(post.type,'message').toLowerCase(),url=safeUrl(post.url);let media='';
  if(url&&['cover','image'].includes(type))media='<div class="mg-pppm-post-media"><img src="'+esc(url)+'" alt="" loading="lazy"></div>';
  else if(url&&type==='audio')media='<div class="mg-pppm-post-media"><audio controls preload="metadata" src="'+esc(url)+'"></audio></div>';
  else if(url&&type==='video')media='<div class="mg-pppm-post-media"><video controls preload="metadata" src="'+esc(url)+'"></video></div>';
  else if(url)media='<div class="mg-pppm-post-media"><a href="'+esc(url)+'" target="_blank" rel="noopener noreferrer">Open media</a></div>';
  else media='<div class="mg-pppm-post-media" aria-hidden="true">'+(type==='offer'?'🎟':type==='message'?'✉':type==='audio'?'♪':type==='video'?'▶':'🎁')+'</div>';
  return '<article class="mg-pppm-post" data-media-type="'+esc(type)+'">'+media+'<span class="mg-eyebrow">Content '+(index+1)+' of '+total+'</span><h3>'+esc(text(post.title,fallbackTitle))+'</h3><p>'+esc(text(post.body)).replace(/\n/g,'<br>')+'</p><div class="mg-pppm-post-meta"><span>'+esc(text(post.meta,'Gift content'))+'</span></div></article>';
 }

 function voucherMarkup(c){
  const p=parts(c),image=safeUrl(p.presentation.image_url),linked=safeUrl(p.linked.url);
  return '<div class="mg-gift-drawer-card mg-load-envelope-card" data-load-envelope-card><span class="mg-eyebrow">Protected voucher</span><section class="mg-gift-card-preview">'+
   (image?'<div class="mg-gift-card-envelope-media"><img src="'+esc(image)+'" alt="" loading="lazy"></div>':'')+
   '<div class="mg-gift-card-hero"><span class="mg-eyebrow">'+esc(merchantFor(c))+'</span><h2>'+esc(titleFor(c))+'</h2><p>'+esc(messageFor(c))+'</p></div>'+
   '<div class="mg-gift-card-body"><div class="mg-gift-value">'+esc(money(p.snapshot.value_cents,p.snapshot.currency))+'</div><div class="mg-gift-meta">'+
    '<div><span>Status</span><strong>'+esc(statusFor(c))+'</strong></div><div><span>Location</span><strong>'+esc(text(p.location.name,'Participating locations'))+'</strong></div>'+
    '<div><span>Gift ID</span><strong>'+esc(text(p.gift.id))+'</strong></div><div><span>Expires</span><strong>'+esc(text(p.snapshot.expires_at,'No expiration'))+'</strong></div></div>'+
    (linked?'<a class="mg-btn mg-btn-soft" href="'+esc(linked)+'">View current product</a>':'')+
   '</div></section></div>';
 }
 function openDrawer(c){
  if(!drawer||!drawerContent)return;const p=parts(c);let posts=Array.isArray(p.media.posts)?p.media.posts.slice():[];
  if(!posts.length)posts=[{type:'message',title:titleFor(c),body:messageFor(c),meta:text(p.source.label,'Microgifter')}];
  if(drawerTitle)drawerTitle.textContent=titleFor(c);
  drawerContent.innerHTML='<div class="mg-pppm-post-stack">'+posts.map((post,i)=>mediaPostMarkup(post,i,posts.length,titleFor(c))).join('')+'</div>'+voucherMarkup(c);
  drawer.classList.add('is-open');drawer.setAttribute('aria-hidden','false');if(drawerBackdrop)drawerBackdrop.hidden=false;document.body.classList.add('mg-modal-lock');drawerContent.scrollTop=0;
 }
 function closeModal(){if(!modal||!modalBody)return;modal.classList.remove('is-open','mg-send-product-modal','mg-send-exact-modal');modal.setAttribute('aria-hidden','true');if(modalBackdrop)modalBackdrop.hidden=true;modalBody.replaceChildren();document.body.classList.remove('mg-modal-lock');}
 function field(label,name,type,placeholder,required){
  if(type==='textarea')return '<label>'+esc(label)+'<textarea name="'+esc(name)+'" placeholder="'+esc(placeholder)+'"'+(required?' required':'')+'></textarea></label>';
  return '<label>'+esc(label)+'<input type="'+esc(type)+'" name="'+esc(name)+'" placeholder="'+esc(placeholder)+'"'+(required?' required':'')+'></label>';
 }
 function modalForm(action,view){
  const note='<div class="mg-action-form-note">This action is recorded against the same Action Center gift and revalidated by the server.</div>';
  if(action==='send')return '<form class="mg-action-form" data-action-form="send">'+field('Recipient','recipient','text','Search and select a recipient',true)+field('Message','message','textarea','Add a note to travel with the gift',false)+note+'<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Regift Microgift</button></div></form>';
  if(action==='follow-up')return '<form class="mg-action-form" data-action-form="follow-up"><div class="mg-action-form-note"><strong>Follow up with '+esc(text(view.recipient_name,'the current recipient'))+'</strong><br>Ownership and delivery history do not change.</div>'+field('Message','message','textarea','Write a helpful follow-up',true)+'<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Send Follow Up</button></div></form>';
  if(action==='tip')return '<form class="mg-action-form" data-action-form="tip">'+field('Tip amount','amount','number','5.00',true)+field('Message','message','textarea','Add a thank-you note',false)+note+'<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Send tip</button></div></form>';
  return '<form class="mg-action-form" data-action-form="message">'+field('To','recipient','text',text(view.sender_name||view.recipient_name,'Gift participant'),true)+field('Message','message','textarea','Write a message',true)+note+'<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Send message</button></div></form>';
 }
 function openModal(action,c){
  if(!modal||!modalBody)return;const view=viewFrom(c),titles={send:'Regift Microgift','follow-up':'Follow Up',tip:'Send a tip',message:'Message participant'};
  if(modalEyebrow)modalEyebrow.textContent=titleFor(c);if(modalTitle)modalTitle.textContent=titles[action]||'Gift action';modalBody.innerHTML=modalForm(action,view);
  modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');if(modalBackdrop)modalBackdrop.hidden=false;document.body.classList.add('mg-modal-lock');
 }
 if(modalBody)modalBody.addEventListener('submit',event=>{
  const selected=currentContract(state.selectedId),flags=selected?parts(selected).flags:{};
  if(!selected||(!bool(flags.demo_preview)&&!bool(flags.system_demo)))return;
  const form=event.target.closest('[data-action-form]');if(!form)return;
  event.preventDefault();event.stopPropagation();if(event.stopImmediatePropagation)event.stopImmediatePropagation();
  modalBody.innerHTML='<div class="mg-action-success"><strong>Demo preview only</strong><p>No real payment, ownership transfer, regift, Follow Up, claim, message, tip, notification, ledger entry, payout, or webhook was created.</p><button class="mg-btn mg-btn-primary" type="button" data-action-modal-close>Done</button></div>';
 },true);
 list.addEventListener('click',event=>{
  const row=event.target.closest('[data-gift-id]');if(!row)return;const id=text(row.dataset.giftId),c=currentContract(id);if(!c)return;select(id);
  const button=event.target.closest('[data-gift-action]');if(!button||button.disabled||button.getAttribute('aria-disabled')==='true')return;
  const action=text(button.dataset.giftAction);
  if(action==='load'){event.preventDefault();openDrawer(c);return;}
  if(action==='claim'){event.preventDefault();app.dispatchEvent(new CustomEvent('mg:gift-claim:open',{bubbles:true,cancelable:true,detail:{item:viewFrom(c),contract:c,row}}));return;}
  if(['send','follow-up','message','tip'].includes(action))openModal(action,c);
 });
 if(loadMore)loadMore.addEventListener('click',()=>load(false));
 if(refresh)refresh.addEventListener('click',()=>load(true));
 if(drawerClose)drawerClose.addEventListener('click',closeDrawer);
 if(drawerBackdrop)drawerBackdrop.addEventListener('click',closeDrawer);
 if(modalBackdrop)modalBackdrop.addEventListener('click',closeModal);
 app.addEventListener('click',event=>{if(event.target.closest('[data-action-modal-close]'))closeModal();});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'){closeDrawer();closeModal();}});
 document.addEventListener('mg:action-center:voucher-claimed',()=>setTimeout(()=>load(true),250));
 document.addEventListener('mg:action-center:regift-sent',()=>setTimeout(()=>load(true),250));
 window.MicrogifterActionCenterRuntime=Object.freeze({
  version:4,contractVersion:2,getFolder:()=>state.folder,getContract:id=>currentContract(id),getView:id=>currentView(id),
  getContracts:()=>state.order.map(id=>state.contracts.get(id)).filter(Boolean),refresh:()=>load(true),loadMore:()=>load(false),select
 });
 window.MicrogifterGiftFeedV3={getFolder:()=>state.folder,getItem:id=>currentView(id),loadFolder:()=>load(true),relativeTime,rebuild:render};
 load(true);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
