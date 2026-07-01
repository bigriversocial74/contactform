document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;

  function field(id){return root.querySelector('#'+id);}
  function textValue(id){var node=field(id);return node?String(node.value||'').trim():'';}
  function hasPlayableMedia(node){return !!(node&&node.getAttribute('src')&&!node.hidden);}
  function cardHeadline(){return textValue('headline')||'HAPPY BIRTHDAY!';}
  function activeMediaRole(){
    if(root.dataset.cardMediaMode==='audio'||root.dataset.cardMediaMode==='video')return root.dataset.cardMediaMode;
    var audio=root.querySelector('[data-preview-audio]');
    var video=root.querySelector('[data-preview-video]');
    if(hasPlayableMedia(video))return 'video';
    if(hasPlayableMedia(audio))return 'audio';
    return '';
  }
  function clearPreview(role){
    var input=root.querySelector('[data-asset-role="'+role+'"]');
    var preview=root.querySelector('[data-media-preview="'+role+'"]');
    var media=preview&&preview.querySelector('audio,video');
    var meta=preview&&preview.querySelector('[data-media-meta]');
    if(input)input.value='';
    if(media){media.pause&&media.pause();media.removeAttribute('src');media.hidden=true;media.load&&media.load();}
    if(meta)meta.textContent='';
    if(preview)preview.classList.remove('is-visible');
  }
  function replaceStaticGiftMessageLabels(headline){
    root.querySelectorAll('.mg-card-inside-right .mg-eyebrow').forEach(function(node){
      if(String(node.textContent||'').trim().toLowerCase()==='gift message'){
        node.textContent=headline;
        node.classList.add('mg-card-message-title');
        node.setAttribute('data-preview-card-headline','');
      }
    });
  }
  function syncCardMessage(){
    var headline=cardHeadline();
    var audioLabel=textValue('audioLabel')||'Sample audio section';
    var videoLabel=textValue('videoLabel')||'Sample video section';
    var mode=activeMediaRole();
    replaceStaticGiftMessageLabels(headline);
    root.querySelectorAll('[data-preview-card-headline]').forEach(function(node){node.textContent=headline;});
    root.querySelectorAll('[data-preview-audio-label]').forEach(function(node){node.textContent=audioLabel;});
    root.querySelectorAll('[data-preview-video-label]').forEach(function(node){node.textContent=videoLabel;});
    root.querySelectorAll('.mg-card-inside-right').forEach(function(page){page.classList.toggle('has-card-media',!!mode);});
    root.querySelectorAll('[data-card-media-stack]').forEach(function(stack){
      var audio=stack.querySelector('[data-card-media-choice="audio"]');
      var video=stack.querySelector('[data-card-media-choice="video"]');
      if(audio)audio.hidden=mode==='video';
      if(video)video.hidden=mode==='audio';
      if(audio)audio.classList.toggle('is-selected',mode==='audio');
      if(video)video.classList.toggle('is-selected',mode==='video');
    });
    root.querySelectorAll('[data-preview-audio]').forEach(function(node){if(mode==='video'){node.removeAttribute('src');node.hidden=true;}});
    root.querySelectorAll('[data-preview-video]').forEach(function(node){if(mode==='audio'){node.removeAttribute('src');node.hidden=true;}});
  }

  root.addEventListener('change',function(event){
    var input=event.target&&event.target.closest&&event.target.closest('[data-asset-role="audio"],[data-asset-role="video"]');
    if(!input||!input.files||!input.files[0])return;
    var role=input.dataset.assetRole;
    var opposite=role==='audio'?'video':'audio';
    root.dataset.cardMediaMode=role;
    clearPreview(opposite);
    window.setTimeout(syncCardMessage,0);
    window.setTimeout(syncCardMessage,150);
  },true);

  ['headline','message','audioLabel','videoLabel'].forEach(function(id){
    var node=field(id);
    if(!node)return;
    node.addEventListener('input',syncCardMessage);
    node.addEventListener('change',syncCardMessage);
  });

  var nativeFetch=window.fetch;
  if(nativeFetch&&!window.__mgCardMediaFetchPatched){
    window.__mgCardMediaFetchPatched=true;
    window.fetch=function(input,init){
      try{
        var url=typeof input==='string'?input:(input&&input.url)||'';
        if(url.indexOf('/api/catalog/builder-draft.php')!==-1&&init&&typeof init.body==='string'){
          var payload=JSON.parse(init.body);
          if(payload&&payload.assets&&payload.assets.audio&&payload.assets.video){
            var mode=activeMediaRole()||'video';
            if(mode==='audio')delete payload.assets.video;
            else delete payload.assets.audio;
            init=Object.assign({},init,{body:JSON.stringify(payload)});
          }
        }
      }catch(error){}
      return nativeFetch.call(this,input,init);
    };
  }

  var observer=new MutationObserver(function(){window.requestAnimationFrame(syncCardMessage);});
  root.querySelectorAll('[data-preview-audio],[data-preview-video]').forEach(function(node){
    observer.observe(node,{attributes:true,attributeFilter:['src','hidden']});
  });
  syncCardMessage();
  var deadline=Date.now()+5000;
  (function watch(){syncCardMessage();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
});
