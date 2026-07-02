(function(){
  'use strict';
  var root=document.querySelector('[data-public-profile-page]');
  if(!root)return;
  var ownerFetchStarted=false,storiesFetchStarted=false,isProfileOwner=false,canManageHighlights=false,manageMode=false,currentHighlights=[];
  var slug=root.getAttribute('data-profile-slug')||new URLSearchParams(location.search).get('slug')||'';

  function qs(sel,ctx){return (ctx||root).querySelector(sel);}
  function qsa(sel,ctx){return Array.prototype.slice.call((ctx||root).querySelectorAll(sel));}
  function clear(node){if(node)node.replaceChildren();}
  function hide(node,value){if(node)node.classList.toggle('mg-hidden',!!value);}
  function payload(response){return response&&response.data?response.data:response;}
  function safeUrl(value){try{if(!value)return null;var url=new URL(String(value),location.origin);if(url.protocol!=='http:'&&url.protocol!=='https:')return null;if(url.username||url.password)return null;return url.origin===location.origin?url.pathname+url.search+url.hash:url.href;}catch(e){return null;}}
  function label(value){return String(value||'update').replace(/[_-]+/g,' ').replace(/\b\w/g,function(l){return l.toUpperCase();});}
  function date(value){if(!value)return '';var raw=String(value);var parsed=new Date(raw.replace(' ','T')+(raw.indexOf('T')===-1?'Z':''));return Number.isNaN(parsed.getTime())?raw:new Intl.DateTimeFormat(undefined,{dateStyle:'medium'}).format(parsed);}
  function stat(name,value){var span=document.createElement('span');span.setAttribute('data-post-stat',name);span.textContent=Number(value||0).toLocaleString()+' '+name;return span;}
  function button(text,attr,value){var b=document.createElement('button');b.type='button';b.className=attr==='data-post-comments'?'mg-profile-comment-toggle':'mg-profile-reaction-button';b.textContent=text;b.setAttribute(attr,value||'1');return b;}

  function mediaNode(media,title){
    var wrap=document.createElement('div');
    wrap.className='mg-profile-post-media';
    (Array.isArray(media)?media:[]).slice(0,6).forEach(function(item){
      var src=safeUrl(item&&item.url);
      if(!src)return;
      var type=String(item.type||'image');
      var fig=document.createElement('figure');
      if(type==='image'){
        var img=document.createElement('img');
        img.src=src;
        img.alt=String(item.alt||title||'Post image');
        img.loading='lazy';
        fig.appendChild(img);
      }else{
        var a=document.createElement('a');
        a.href=src;
        a.target='_blank';
        a.rel='noopener noreferrer';
        a.textContent=item.caption||'Open attachment';
        fig.appendChild(a);
      }
      wrap.appendChild(fig);
    });
    return wrap;
  }

  function postCard(raw){
    var post=raw||{};
    var id=String(post.id||post.public_id||'');
    if(!id)return null;
    var card=document.createElement('article');
    card.className='mg-profile-post-card';
    card.dataset.postId=id;
    card.dataset.viewerReaction=String(post.engagement&&post.engagement.viewer_reaction||'');
    var meta=document.createElement('div');
    meta.className='mg-profile-post-meta';
    var type=document.createElement('span');
    type.textContent=label(post.type||post.post_type||post.status||'update');
    var time=document.createElement('time');
    time.textContent=date(post.published_at||post.created_at||post.updated_at);
    meta.append(type,time);
    card.appendChild(meta);
    if(post.headline){var h=document.createElement('h3');h.textContent=String(post.headline);card.appendChild(h);}
    if(post.body){var p=document.createElement('p');p.textContent=String(post.body);card.appendChild(p);}
    var media=mediaNode(post.media,post.headline);
    if(media.children.length)card.appendChild(media);
    var engagement=post.engagement||{};
    var stats=document.createElement('div');
    stats.className='mg-profile-post-stats';
    stats.append(stat('comments',engagement.comments),stat('reactions',engagement.reactions),stat('shares',engagement.shares));
    var actions=document.createElement('div');
    actions.className='mg-profile-post-actions';
    ['like','love','celebrate','support'].forEach(function(reaction){
      var b=button(label(reaction),'data-post-reaction',reaction);
      var active=reaction===card.dataset.viewerReaction;
      b.classList.toggle('is-active',active);
      b.setAttribute('aria-pressed',active?'true':'false');
      actions.appendChild(b);
    });
    actions.appendChild(button('Comments','data-post-comments','1'));
    var comments=document.createElement('div');
    comments.className='mg-profile-comments mg-hidden';
    comments.dataset.commentsPanel='1';
    comments.innerHTML='<div class="mg-profile-comments-status" data-comments-status role="status"></div><div class="mg-profile-comment-list" data-comment-list></div><button class="mg-btn mg-btn-soft mg-hidden" type="button" data-comments-more>Load more comments</button><form class="mg-profile-comment-form" data-comment-form><label><span>Join the conversation</span><textarea name="comment_body" maxlength="2000" rows="3" required></textarea></label><button class="mg-btn mg-btn-primary" type="submit">Post comment</button></form>';
    card.append(stats,actions,comments);
    return card;
  }

  function renderCollection(collection){
    var section=qs('[data-profile-posts-section]');
    var list=qs('[data-profile-posts-list]');
    var empty=qs('[data-profile-posts-empty]');
    if(!section||!list)return 0;
    var items=collection&&Array.isArray(collection.items)?collection.items:[];
    clear(list);
    items.forEach(function(post){var card=postCard(post);if(card)list.appendChild(card);});
    var count=list.children.length;
    section.classList.remove('mg-hidden');
    hide(empty,count>0);
    return count;
  }

  async function loadOwnerPosts(){
    if(ownerFetchStarted)return;
    ownerFetchStarted=true;
    if(!window.Microgifter||!window.Microgifter.get)return;
    try{var data=payload(await window.Microgifter.get('/api/social/posts.php?scope=mine&limit=12&status='));if(data&&data.posts)renderCollection(data.posts);}catch(e){}
  }

  function ensure(data){
    var posts=data&&data.posts?data.posts:null;
    var count=posts?renderCollection(posts):0;
    isProfileOwner=!!(data&&data.profile&&data.profile.availability&&data.profile.availability.is_owner);
    if(isProfileOwner&&count===0)loadOwnerPosts();
    loadStories(false);
  }

  function installStoryStyles(){
    if(document.getElementById('mg-profile-story-highlights-style'))return;
    var style=document.createElement('style');
    style.id='mg-profile-story-highlights-style';
    style.textContent='.mg-profile-story-highlight-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.mg-profile-story-highlight-toolbar button{border:1px solid rgba(15,23,42,.12);border-radius:999px;background:#fff;color:#0f172a;font-weight:900;padding:9px 14px;cursor:pointer}.mg-profile-story-highlight-toolbar button.is-active{background:#0f172a;color:#fff}.mg-profile-story-highlight-status{font-size:.8rem;color:#64748b;font-weight:800}.mg-invest-card [data-profile-story-highlights]{padding:0 18px 18px}.mg-profile-story-highlight-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px}.mg-profile-story-highlight-card{position:relative;min-height:250px;border:1px solid rgba(15,23,42,.08);border-radius:24px;overflow:hidden;background:#0f172a;color:#fff;box-shadow:0 16px 40px rgba(15,23,42,.12)}.mg-profile-story-highlight-card img,.mg-profile-story-highlight-card video{width:100%;height:100%;min-height:250px;object-fit:cover;display:block}.mg-profile-story-highlight-card:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,23,42,0) 35%,rgba(15,23,42,.84) 100%)}.mg-profile-story-highlight-copy{position:absolute;z-index:2;left:14px;right:14px;bottom:14px}.mg-profile-story-highlight-copy span{display:inline-flex;padding:4px 9px;border-radius:999px;background:rgba(255,255,255,.18);font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.mg-profile-story-highlight-copy strong{display:block;margin-top:8px;font-size:1rem;line-height:1.1}.mg-profile-story-highlight-copy small{display:block;margin-top:5px;color:rgba(255,255,255,.76);font-weight:750}.mg-profile-story-highlight-empty{border:1px dashed rgba(15,23,42,.2);border-radius:22px;padding:22px;text-align:center;color:#64748b;background:rgba(248,250,252,.75);margin:0 18px 18px}.mg-profile-story-highlight-actions{position:absolute;z-index:3;top:10px;left:10px;right:10px;display:flex;flex-wrap:wrap;gap:6px}.mg-profile-story-highlight-actions button{border:0;border-radius:999px;background:rgba(255,255,255,.9);color:#0f172a;font-size:.72rem;font-weight:950;padding:7px 9px;cursor:pointer;box-shadow:0 8px 20px rgba(15,23,42,.18)}.mg-profile-story-highlight-actions button.is-danger{background:#fee2e2;color:#991b1b}.mg-profile-story-highlight-card.is-managing{outline:3px solid rgba(250,204,21,.75)}';
    document.head.appendChild(style);
  }

  function setStoryStatus(text,type){var node=qs('[data-profile-story-highlights-status]');if(node){node.textContent=text||'';node.className='mg-profile-story-highlight-status'+(type?' is-'+type:'');}}

  function syncToolbar(card){
    var head=qs('.mg-invest-section-head',card);
    if(!head)return;
    var sub=head.querySelector('span');
    if(sub)sub.textContent='Saved story highlights from this profile.';
    var old=qs('[data-profile-story-highlights-toolbar]',head);
    if(old)old.remove();
    if(!canManageHighlights)return;
    var tools=document.createElement('div');
    tools.className='mg-profile-story-highlight-toolbar';
    tools.dataset.profileStoryHighlightsToolbar='1';
    tools.innerHTML='<button type="button" data-highlight-manage>'+(manageMode?'Done':'Manage Highlights')+'</button><span class="mg-profile-story-highlight-status" data-profile-story-highlights-status role="status" aria-live="polite"></span>';
    var btn=tools.querySelector('button');
    if(btn)btn.classList.toggle('is-active',manageMode);
    head.appendChild(tools);
  }

  function storyCard(item,index){
    var card=document.createElement('article');
    card.className='mg-profile-story-highlight-card'+(manageMode&&canManageHighlights?' is-managing':'');
    card.dataset.highlightId=String(item.id||'');
    card.dataset.highlightIndex=String(index);
    var mediaUrl=safeUrl(item.media_url),thumb=safeUrl(item.thumbnail_url)||mediaUrl;
    if(item.media_type==='video'){
      var video=document.createElement('video');
      video.src=mediaUrl;
      video.poster=thumb||'';
      video.muted=true;
      video.playsInline=true;
      video.controls=true;
      card.appendChild(video);
    }else{
      var img=document.createElement('img');
      img.src=thumb||mediaUrl;
      img.alt=String(item.title||'Story highlight');
      img.loading='lazy';
      card.appendChild(img);
    }
    var copy=document.createElement('div');
    copy.className='mg-profile-story-highlight-copy';
    copy.innerHTML='<span>'+label(item.story_type||'story')+'</span><strong></strong><small></small>';
    copy.querySelector('strong').textContent=String(item.title||item.caption||'Story Highlight');
    copy.querySelector('small').textContent='Saved '+date(item.highlighted_at||item.created_at);
    card.appendChild(copy);
    if(manageMode&&canManageHighlights){
      var actions=document.createElement('div');
      actions.className='mg-profile-story-highlight-actions';
      actions.innerHTML='<button type="button" data-highlight-move="up">↑</button><button type="button" data-highlight-move="down">↓</button><button type="button" data-highlight-rename>Rename</button><button type="button" class="is-danger" data-highlight-remove>Remove</button>';
      card.appendChild(actions);
    }else if(item.cta_url){
      card.style.cursor='pointer';
      card.addEventListener('click',function(event){if(event.target.closest('video'))return;location.href=safeUrl(item.cta_url)||'#';});
    }
    return card;
  }

  function renderStories(items){
    installStoryStyles();
    currentHighlights=Array.isArray(items)?items:[];
    var panel=qs('[data-invest-panel="stories"]');
    if(!panel)return;
    var card=qs('.mg-invest-card',panel)||panel;
    syncToolbar(card);
    var old=qs('[data-profile-story-highlights]',card);
    if(old)old.remove();
    var oldEmpty=qs('[data-profile-story-highlights-empty]',card);
    if(oldEmpty)oldEmpty.remove();
    var defaultEmpty=qsa('.mg-invest-empty-state',card).filter(function(node){return !node.hasAttribute('data-profile-story-highlights-empty');})[0]||null;
    if(!currentHighlights.length){
      hide(defaultEmpty,false);
      if(!defaultEmpty){
        var empty=document.createElement('div');
        empty.className='mg-profile-story-highlight-empty';
        empty.dataset.profileStoryHighlightsEmpty='1';
        empty.innerHTML='<strong>No story highlights yet.</strong><small>Saved stories will appear here after the profile owner highlights them.</small>';
        card.appendChild(empty);
      }
      return;
    }
    hide(defaultEmpty,true);
    var grid=document.createElement('div');
    grid.className='mg-profile-story-highlight-grid';
    grid.dataset.profileStoryHighlights='1';
    currentHighlights.forEach(function(item,index){grid.appendChild(storyCard(item,index));});
    card.insertBefore(grid,defaultEmpty||null);
  }

  async function loadStories(force){
    if((storiesFetchStarted&&!force)||!slug)return;
    storiesFetchStarted=true;
    try{
      var data=payload(await fetch('/api/public/profile-stories.php?slug='+encodeURIComponent(slug),{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.ok?r.json():null;}));
      canManageHighlights=!!(data&&data.permissions&&data.permissions.can_manage)||isProfileOwner;
      renderStories(data&&Array.isArray(data.highlights)?data.highlights:[]);
    }catch(e){renderStories([]);}
  }

  async function postHighlight(body){
    var MG=window.Microgifter||{};
    if(MG.post)return payload(await MG.post('/api/stories/highlight.php',body));
    var token=document.querySelector('meta[name="csrf-token"]');
    body.csrf_token=token?token.content:'';
    return payload(await fetch('/api/stories/highlight.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body)}).then(function(r){return r.json();}));
  }
  function findHighlight(id){return currentHighlights.find(function(item){return String(item.id)===String(id);})||null;}
  async function manageAction(button,body,success){if(!button)return;var old=button.textContent;button.disabled=true;button.textContent='Saving…';try{await postHighlight(body);setStoryStatus(success||'Highlights updated.','success');await loadStories(true);}catch(e){setStoryStatus(e&&e.message?e.message:'Unable to update highlight.','error');button.disabled=false;button.textContent=old;}}

  root.addEventListener('click',function(event){
    var manage=event.target.closest('[data-highlight-manage]');
    if(manage){event.preventDefault();manageMode=!manageMode;renderStories(currentHighlights);return;}
    var card=event.target.closest('[data-highlight-id]');
    if(!card)return;
    var id=card.dataset.highlightId||'',item=findHighlight(id);
    if(!item)return;
    var rename=event.target.closest('[data-highlight-rename]');
    if(rename){event.preventDefault();var next=prompt('Highlight title',String(item.title||item.caption||'Story Highlight'));if(next===null)return;next=String(next).trim();if(!next){setStoryStatus('Highlight title is required.','error');return;}manageAction(rename,{action:'rename',highlight_id:id,title:next},'Highlight renamed.');return;}
    var remove=event.target.closest('[data-highlight-remove]');
    if(remove){event.preventDefault();if(!confirm('Remove this highlight from the profile?'))return;manageAction(remove,{action:'remove',highlight_id:id},'Highlight removed.');return;}
    var move=event.target.closest('[data-highlight-move]');
    if(move){event.preventDefault();manageAction(move,{action:'reorder',highlight_id:id,direction:move.getAttribute('data-highlight-move')||'down'},'Highlight order updated.');}
  });

  document.addEventListener('mg:public-profile:links-rendered',function(){loadStories(false);});
  document.addEventListener('mg:public-profile:data',function(event){ensure(event.detail||{});});
  if(window.Microgifter&&window.Microgifter.publicProfileData)ensure(window.Microgifter.publicProfileData);else loadStories(false);
})();
