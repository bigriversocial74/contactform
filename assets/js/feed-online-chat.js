window.Microgifter=window.Microgifter||{};

(function(window,document){
'use strict';
var MG=window.Microgifter;
var rail=document.querySelector('[data-online-chat-rail]');
var dock=document.querySelector('[data-feed-chat-dock]');
if(!rail||!dock||!MG.get||!MG.post)return;
if(!window.matchMedia||!window.matchMedia('(min-width:1024px)').matches)return;

var activeProfile=null;
var profiles=[];
var refreshTimer=null;

function payload(response){return response&&response.data?response.data:response;}
function qs(selector,scope){return(scope||document).querySelector(selector);}
function clear(node){if(node)node.replaceChildren();}
function initials(name){return String(name||'M').split(/\s+/).filter(Boolean).slice(0,2).map(function(part){return part[0];}).join('').toUpperCase()||'M';}
function safeText(value){return String(value==null?'':value);}
function status(text){var node=qs('[data-online-chat-status]',rail);if(node)node.textContent=text||'';}
function busy(button,value,label){if(!button)return;if(MG.setBusy)return MG.setBusy(button,value,label);if(value)button.dataset.originalLabel=button.textContent;button.disabled=value;button.textContent=value?(label||'Working…'):(button.dataset.originalLabel||button.textContent);if(!value)delete button.dataset.originalLabel;}

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

function renderRail(){
  clear(rail);
  var statusNode=document.createElement('div');
  statusNode.className='mg-online-chat-rail-status';
  statusNode.dataset.onlineChatStatus='1';
  statusNode.textContent=profiles.length?profiles.length+' online':'No online followers';
  rail.appendChild(statusNode);
  var list=document.createElement('div');
  list.className='mg-online-chat-list';
  list.dataset.onlineChatList='1';
  profiles.forEach(function(profile){
    var btn=document.createElement('button');
    btn.type='button';
    btn.className='mg-online-chat-avatar'+(activeProfile&&activeProfile.id===profile.id?' is-active':'');
    btn.dataset.profileId=profile.id;
    btn.title='Chat with '+safeText(profile.name);
    btn.setAttribute('aria-label','Chat with '+safeText(profile.name));
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
  var bubble=document.createElement('div');
  bubble.className='mg-feed-chat-bubble';
  bubble.textContent=safeText(message.body);
  row.appendChild(bubble);
  return row;
}

function renderChat(profile,data){
  activeProfile=profile;
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
  var small=document.createElement('small');small.textContent='Active now';
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
  var messages=Array.isArray(data&&data.messages)?data.messages:[];
  if(messages.length){messages.forEach(function(message){body.appendChild(messageNode(message));});}
  else{var empty=document.createElement('div');empty.className='mg-feed-chat-empty';empty.textContent='Start a quick chat. Messages notify the other user.';body.appendChild(empty);}

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
  renderRail();
  body.scrollTop=body.scrollHeight;
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

async function openChat(profileId){
  var profile=profiles.find(function(item){return item.id===profileId;});
  if(!profile)return;
  status('Opening chat…');
  try{
    var data=payload(await MG.get('/api/social/online-chat.php?profile_id='+encodeURIComponent(profile.id)));
    renderChat(data.profile||profile,data);
    profile.unread=0;
    status(profiles.length?profiles.length+' online':'No online followers');
  }catch(error){status(error.message||'Unable to open chat.');}
}

async function sendMessage(form){
  var win=form.closest('[data-chat-profile-id]');
  if(!win)return;
  var profileId=win.dataset.chatProfileId;
  var input=form.elements.body;
  var body=safeText(input.value).trim();
  if(!body)return;
  var button=qs('button[type="submit"]',form);
  busy(button,true,'Sending…');
  try{
    var data=payload(await MG.post('/api/social/online-chat.php',{profile_id:profileId,body:body}));
    input.value='';
    var box=qs('[data-chat-messages]',win);
    var empty=qs('.mg-feed-chat-empty',box);if(empty)empty.remove();
    if(data.message)box.appendChild(messageNode(data.message));
    box.scrollTop=box.scrollHeight;
  }catch(error){errorInChat(error.message||'Unable to send message.');}
  finally{busy(button,false);}
}

async function loadProfiles(){
  try{
    var data=payload(await MG.get('/api/social/online-chat.php'));
    profiles=Array.isArray(data&&data.profiles)?data.profiles:[];
    renderRail();
  }catch(error){rail.hidden=true;}
}

rail.addEventListener('click',function(event){
  var btn=event.target.closest('[data-profile-id]');
  if(!btn)return;
  openChat(btn.dataset.profileId);
});

dock.addEventListener('click',function(event){
  if(event.target.closest('[data-chat-close]')){
    clear(dock);activeProfile=null;renderRail();
  }
});

dock.addEventListener('submit',function(event){
  var form=event.target.closest('[data-chat-form]');
  if(!form)return;
  event.preventDefault();
  sendMessage(form);
});

loadProfiles();
refreshTimer=window.setInterval(loadProfiles,60000);
window.addEventListener('beforeunload',function(){if(refreshTimer)window.clearInterval(refreshTimer);});
})(window,document);
