window.Microgifter=window.Microgifter||{};

(function(window,document){
'use strict';
var MG=window.Microgifter;
var rail=document.querySelector('[data-online-chat-rail]');
var dock=document.querySelector('[data-feed-chat-dock]');
if(!rail||!dock||!MG.get||!MG.post)return;

var desktopQuery=window.matchMedia?window.matchMedia('(min-width:1024px)'):null;
var RAIL_POLL_MS=15000;
var CHAT_POLL_MS=5000;
var HEARTBEAT_MS=60000;
var activeProfile=null;
var profiles=[];
var railTimer=null;
var chatTimer=null;
var heartbeatTimer=null;
var railLoading=false;
var chatLoading=false;
var heartbeatLoading=false;
var railSignature='';
var messageSignature='';
var audioContext=null;
var audioUnlocked=false;
var lastSoundAt=0;
var knownIncomingMessages={};
var railUnreadSnapshot={};
var railSoundPrimed=false;
var params=new URLSearchParams(window.location.search||'');
var deepLinkProfileId=params.get('chat')||'';
var deepLinkThreadId=params.get('thread')||'';
var deepLinkAttempted=false;

function payload(response){return response&&response.data?response.data:response;}
function qs(selector,scope){return(scope||document).querySelector(selector);}
function clear(node){if(node)node.replaceChildren();}
function initials(name){return String(name||'M').split(/\s+/).filter(Boolean).slice(0,2).map(function(part){return part[0];}).join('').toUpperCase()||'M';}
function safeText(value){return String(value==null?'':value);}
function isDesktop(){return !desktopQuery||desktopQuery.matches;}
function isVisible(){return document.visibilityState!=='hidden';}
function busy(button,value,label){if(!button)return;if(MG.setBusy)return MG.setBusy(button,value,label);if(value)button.dataset.originalLabel=button.textContent;button.disabled=value;button.textContent=value?(label||'Working…'):(button.dataset.originalLabel||button.textContent);if(!value)delete button.dataset.originalLabel;}
function profileById(id){return profiles.find(function(item){return item.id===id;})||null;}

function unlockChatAudio(){
  if(audioUnlocked)return;
  try{
    var AudioCtor=window.AudioContext||window.webkitAudioContext;
    if(!AudioCtor)return;
    audioContext=audioContext||new AudioCtor();
    if(audioContext.state==='suspended'&&audioContext.resume)audioContext.resume().catch(function(){});
    audioUnlocked=true;
  }catch(error){}
}

function playChatSound(){
  var now=Date.now();
  if(now-lastSoundAt<1800)return;
  lastSoundAt=now;
  unlockChatAudio();
  if(!audioContext||audioContext.state==='suspended')return;
  try{
    var start=audioContext.currentTime;
    var gain=audioContext.createGain();
    gain.gain.setValueAtTime(0.0001,start);
    gain.gain.exponentialRampToValueAtTime(0.055,start+0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001,start+0.22);
    gain.connect(audioContext.destination);
    [880,1175].forEach(function(freq,index){
      var osc=audioContext.createOscillator();
      osc.type='sine';
      osc.frequency.setValueAtTime(freq,start+(index*0.09));
      osc.connect(gain);
      osc.start(start+(index*0.09));
      osc.stop(start+(index*0.09)+0.09);
    });
    window.setTimeout(function(){try{gain.disconnect();}catch(error){}},360);
  }catch(error){}
}

function rememberIncomingMessages(messages){
  (Array.isArray(messages)?messages:[]).forEach(function(message){
    if(!message||message.mine||!message.id)return;
    knownIncomingMessages[message.id]=true;
  });
}

function hasNewIncomingMessage(messages){
  return (Array.isArray(messages)?messages:[]).some(function(message){
    return message&&!message.mine&&message.id&&!knownIncomingMessages[message.id];
  });
}

function hasUnreadIncrease(list){
  return (Array.isArray(list)?list:[]).some(function(profile){
    if(!profile||!profile.id)return false;
    return Number(profile.unread||0)>Number(railUnreadSnapshot[profile.id]||0);
  });
}

function rememberRailUnread(list){
  railUnreadSnapshot={};
  (Array.isArray(list)?list:[]).forEach(function(profile){
    if(profile&&profile.id)railUnreadSnapshot[profile.id]=Number(profile.unread||0);
  });
  railSoundPrimed=true;
}

function setHeaderOffset(){
  var header=document.querySelector('.mg-site-header[data-public-header],.mg-site-header,header[role="banner"]');
  var fallback=64;
  var offset=fallback;
  if(header){
    var rect=header.getBoundingClientRect();
    if(rect&&Number.isFinite(rect.bottom))offset=Math.max(56,Math.ceil(rect.bottom));
  }
  document.documentElement.style.setProperty('--mg-online-chat-header-offset',offset+'px');
}

function avatar(profile){
  var name=safeText(profile&&profile.name)||'Microgifter member';
  if(profile&&profile.avatar_url){
    var img=document.createElement('img');
    img.src=profile.avatar_url;
    img.alt='';
    img.loading='lazy';
    img.addEventListener('error',function(){var repl=document.createElement('span');repl.textContent=initials(name);img.replaceWith(repl);},{once:true});
    return img;
  }
  var span=document.createElement('span');
  span.textContent=initials(name);
  return span;
}

function profileSignature(list){
  return JSON.stringify((Array.isArray(list)?list:[]).map(function(profile){
    return [profile.id,profile.name,profile.avatar_url,profile.online?1:0,Number(profile.unread||0),profile.last_seen_at||''];
  }));
}

function messagesSignature(messages){
  return JSON.stringify((Array.isArray(messages)?messages:[]).map(function(message){
    return [message.id,message.mine?1:0,message.created_at||'',message.body||''];
  }));
}

function chatBoxNearBottom(){
  var box=qs('[data-chat-messages]',dock);
  if(!box)return false;
  return box.scrollHeight-box.scrollTop-box.clientHeight<80;
}

function shouldMarkRead(force){
  return Boolean(force)||(isVisible()&&chatBoxNearBottom());
}

function renderRail(force){
  if(!isDesktop()){rail.hidden=true;return;}
  setHeaderOffset();
  var nextSignature=profileSignature(profiles)+(activeProfile?':active:'+activeProfile.id:'');
  if(!force&&nextSignature===railSignature)return;
  railSignature=nextSignature;
  clear(rail);
  if(!profiles.length){rail.hidden=true;return;}
  var list=document.createElement('div');
  list.className='mg-online-chat-list';
  list.dataset.onlineChatList='1';
  profiles.forEach(function(profile){
    var btn=document.createElement('button');
    btn.type='button';
    btn.className='mg-online-chat-avatar'+(profile.online?' is-online':'')+(activeProfile&&activeProfile.id===profile.id?' is-active':'');
    btn.dataset.profileId=profile.id;
    btn.title='Chat with '+safeText(profile.name)+(profile.online?' · online':' · recently active');
    btn.setAttribute('aria-label','Chat with '+safeText(profile.name)+(profile.online?' online':' recently active'));
    btn.appendChild(avatar(profile));
    if(Number(profile.unread||0)>0){
      var unread=document.createElement('span');
      unread.className='mg-online-chat-unread';
      unread.textContent=Number(profile.unread)>9?'9+':String(profile.unread);
      btn.appendChild(unread);
    }
    list.appendChild(btn);
  });
  rail.appendChild(list);
  rail.hidden=false;
}

function messageNode(message){
  var row=document.createElement('div');
  row.className='mg-feed-chat-message'+(message.mine?' is-mine':'');
  row.dataset.messageId=message.id||'';
  var bubble=document.createElement('div');
  bubble.className='mg-feed-chat-bubble';
  bubble.textContent=safeText(message.body);
  row.appendChild(bubble);
  return row;
}

function renderMessages(messages,force){
  var win=qs('.mg-feed-chat-window',dock);
  if(!win)return;
  var box=qs('[data-chat-messages]',win);
  if(!box)return;
  messages=Array.isArray(messages)?messages:[];
  var nextSignature=messagesSignature(messages);
  if(!force&&nextSignature===messageSignature){rememberIncomingMessages(messages);return;}
  var shouldSound=!force&&hasNewIncomingMessage(messages);
  messageSignature=nextSignature;
  var nearBottom=box.scrollHeight-box.scrollTop-box.clientHeight<56;
  clear(box);
  if(messages.length){messages.forEach(function(message){box.appendChild(messageNode(message));});}
  else{var empty=document.createElement('div');empty.className='mg-feed-chat-empty';empty.textContent='Start a quick chat. Messages notify the other user.';box.appendChild(empty);}
  rememberIncomingMessages(messages);
  if(shouldSound)playChatSound();
  if(nearBottom||force)box.scrollTop=box.scrollHeight;
}

function updateChatPresence(profile){
  var win=qs('.mg-feed-chat-window',dock);
  if(!win||!profile)return;
  var small=qs('.mg-feed-chat-user small',win);
  if(small)small.textContent=profile.online?'Active now':'Recently active';
}

function renderChat(profile,data){
  activeProfile=profile;
  messageSignature='';
  clear(dock);
  var win=document.createElement('section');
  win.className='mg-feed-chat-window';
  win.dataset.chatProfileId=profile.id;
  win.setAttribute('role','dialog');
  win.setAttribute('aria-label','Chat with '+safeText(profile.name));

  var head=document.createElement('header');
  head.className='mg-feed-chat-head';
  var user=document.createElement('div');
  user.className='mg-feed-chat-user';
  user.appendChild(avatar(profile));
  var meta=document.createElement('div');
  var strong=document.createElement('strong');strong.textContent=safeText(profile.name)||'Microgifter member';
  var small=document.createElement('small');small.textContent=profile.online?'Active now':'Recently active';
  meta.append(strong,small);
  user.appendChild(meta);
  var close=document.createElement('button');
  close.type='button';
  close.className='mg-feed-chat-close';
  close.dataset.chatClose='1';
  close.setAttribute('aria-label','Close chat');
  close.textContent='×';
  head.append(user,close);

  var body=document.createElement('div');
  body.className='mg-feed-chat-body';
  body.dataset.chatMessages='1';

  var form=document.createElement('form');
  form.className='mg-feed-chat-form';
  form.dataset.chatForm='1';
  var input=document.createElement('textarea');
  input.name='body';
  input.rows=1;
  input.maxLength=2000;
  input.required=true;
  input.placeholder='Write a message…';
  var submit=document.createElement('button');
  submit.type='submit';
  submit.textContent='Send';
  form.append(input,submit);

  win.append(head,body,form);
  dock.appendChild(win);
  renderMessages(data&&data.messages,true);
  renderRail(true);
  startChatPolling();
  window.setTimeout(function(){input.focus();},40);
}

function errorInChat(message){
  var win=qs('.mg-feed-chat-window',dock);
  if(!win)return;
  var old=qs('.mg-feed-chat-error',win);if(old)old.remove();
  var err=document.createElement('div');
  err.className='mg-feed-chat-error';
  err.textContent=message||'Unable to send message.';
  win.insertBefore(err,qs('.mg-feed-chat-form',win));
}

async function openChat(profileId,options){
  unlockChatAudio();
  var profile=profileById(profileId)||{id:profileId,name:'Chat',online:false};
  var markRead=shouldMarkRead(Boolean(options&&options.markRead));
  try{
    var url='/api/social/online-chat.php?profile_id='+encodeURIComponent(profile.id)+(markRead?'&mark_read=1':'');
    var data=payload(await MG.get(url));
    var liveProfile=data.profile||profile;
    var existing=profileById(liveProfile.id);
    if(!existing){profiles.unshift(liveProfile);profiles=profiles.slice(0,10);}
    else Object.assign(existing,liveProfile);
    renderChat(liveProfile,data);
    if(markRead){var local=profileById(liveProfile.id);if(local)local.unread=0;rememberRailUnread(profiles);}
    renderRail(true);
  }catch(error){errorInChat(error.message||'Unable to open chat.');}
}

async function pollActiveChat(options){
  if(chatLoading||!activeProfile||!isDesktop())return;
  chatLoading=true;
  try{
    var markRead=shouldMarkRead(Boolean(options&&options.markRead));
    var data=payload(await MG.get('/api/social/online-chat.php?profile_id='+encodeURIComponent(activeProfile.id)+(markRead?'&mark_read=1':'')));
    if(data.profile){activeProfile=data.profile;updateChatPresence(activeProfile);}
    renderMessages(data.messages||[],false);
    if(markRead){var local=profileById(activeProfile.id);if(local)local.unread=0;rememberRailUnread(profiles);}
    renderRail(false);
  }catch(error){}
  finally{chatLoading=false;}
}

function startChatPolling(){
  stopChatPolling();
  if(isDesktop())chatTimer=window.setInterval(pollActiveChat,CHAT_POLL_MS);
}

function stopChatPolling(){
  if(chatTimer)window.clearInterval(chatTimer);
  chatTimer=null;
}

async function sendMessage(form){
  unlockChatAudio();
  var win=form.closest('[data-chat-profile-id]');
  if(!win)return;
  var profileId=win.dataset.chatProfileId;
  var input=form.elements.body;
  var body=safeText(input.value).trim();
  if(!body)return;
  var button=qs('button[type="submit"]',form);
  busy(button,true,'Sending…');
  try{
    await MG.post('/api/social/online-chat.php',{profile_id:profileId,body:body});
    input.value='';
    await pollActiveChat({markRead:true});
  }catch(error){errorInChat(error.message||'Unable to send message.');}
  finally{busy(button,false);}
}

async function heartbeat(){
  if(heartbeatLoading||!isVisible())return;
  heartbeatLoading=true;
  try{await MG.post('/api/social/presence-heartbeat.php',{});}catch(error){}
  finally{heartbeatLoading=false;}
}

function startHeartbeat(){
  stopHeartbeat();
  heartbeat();
  heartbeatTimer=window.setInterval(heartbeat,HEARTBEAT_MS);
}

function stopHeartbeat(){
  if(heartbeatTimer)window.clearInterval(heartbeatTimer);
  heartbeatTimer=null;
}

async function loadProfiles(force){
  if(railLoading||!isDesktop())return;
  railLoading=true;
  try{
    var data=payload(await MG.get('/api/social/online-chat.php'));
    var nextProfiles=Array.isArray(data&&data.profiles)?data.profiles:[];
    if(railSoundPrimed&&!force&&hasUnreadIncrease(nextProfiles))playChatSound();
    profiles=nextProfiles;
    if(activeProfile){
      var updated=profileById(activeProfile.id);
      if(updated){activeProfile=Object.assign({},activeProfile,updated);updateChatPresence(activeProfile);}
    }
    renderRail(Boolean(force));
    rememberRailUnread(profiles);
    maybeOpenDeepLink();
  }catch(error){rail.hidden=true;}
  finally{railLoading=false;}
}

function maybeOpenDeepLink(){
  if(deepLinkAttempted||!deepLinkProfileId)return;
  if(!isDesktop()){
    if(deepLinkThreadId)window.location.href='/messages.php?thread='+encodeURIComponent(deepLinkThreadId);
    return;
  }
  deepLinkAttempted=true;
  openChat(deepLinkProfileId,{markRead:true});
}

function startRailPolling(){
  stopRailPolling();
  if(!isDesktop()){rail.hidden=true;clear(dock);activeProfile=null;maybeOpenDeepLink();return;}
  setHeaderOffset();
  loadProfiles(true);
  railTimer=window.setInterval(function(){loadProfiles(false);},RAIL_POLL_MS);
}

function stopRailPolling(){
  if(railTimer)window.clearInterval(railTimer);
  railTimer=null;
}

function handleViewportChange(){
  setHeaderOffset();
  if(isDesktop()){
    startRailPolling();
    if(activeProfile)startChatPolling();
  }else{
    stopRailPolling();
    stopChatPolling();
    rail.hidden=true;
    clear(dock);
    activeProfile=null;
    maybeOpenDeepLink();
  }
}

rail.addEventListener('click',function(event){
  unlockChatAudio();
  var btn=event.target.closest('[data-profile-id]');
  if(!btn)return;
  openChat(btn.dataset.profileId,{markRead:true});
});

dock.addEventListener('click',function(event){
  unlockChatAudio();
  if(event.target.closest('[data-chat-close]')){
    clear(dock);activeProfile=null;stopChatPolling();renderRail(true);
  }
});

dock.addEventListener('submit',function(event){
  var form=event.target.closest('[data-chat-form]');
  if(!form)return;
  event.preventDefault();
  sendMessage(form);
});

document.addEventListener('pointerdown',unlockChatAudio,{once:true,capture:true,passive:true});
document.addEventListener('keydown',unlockChatAudio,{once:true,capture:true});
window.addEventListener('resize',setHeaderOffset,{passive:true});
window.addEventListener('orientationchange',setHeaderOffset,{passive:true});
document.addEventListener('visibilitychange',function(){if(isVisible()){heartbeat();if(activeProfile)pollActiveChat({markRead:true});}});
if(desktopQuery){
  if(desktopQuery.addEventListener)desktopQuery.addEventListener('change',handleViewportChange);
  else if(desktopQuery.addListener)desktopQuery.addListener(handleViewportChange);
}
startHeartbeat();
handleViewportChange();
window.addEventListener('beforeunload',function(){stopRailPolling();stopChatPolling();stopHeartbeat();});
})(window,document);
