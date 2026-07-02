window.Microgifter=window.Microgifter||{};

(function(window,document){
'use strict';
var MG=window.Microgifter;
var root=document.querySelector('[data-social-feed]');
var list=root&&root.querySelector('[data-feed-list]');
if(!root||!list||!MG.get)return;

var pending=new Set();
var loading=new Set();
var failed=new Set();
var cache=new Map();
var timer=null;

function ensureStyles(){
    if(document.querySelector('link[href="/assets/css/social-feed-attachment-cards.css"]'))return;
    var link=document.createElement('link');
    link.rel='stylesheet';
    link.href='/assets/css/social-feed-attachment-cards.css';
    document.head.appendChild(link);
}

function safeUrl(value){
    var raw=String(value||'').trim();
    if(!raw)return null;
    try{
        var parsed=new URL(raw,window.location.origin);
        if(!['http:','https:'].includes(parsed.protocol)||parsed.username||parsed.password)return null;
        if(raw.startsWith('/')){
            if(raw.startsWith('//')||parsed.origin!==window.location.origin)return null;
            return parsed.pathname+parsed.search+parsed.hash;
        }
        return parsed.href;
    }catch(error){return null;}
}

function label(value){
    return String(value||'').replace(/[_-]+/g,' ').replace(/\b\w/g,function(letter){return letter.toUpperCase();});
}

function safeClass(value){
    return String(value||'').toLowerCase().replace(/[^a-z0-9_-]/g,'');
}

function money(card){
    var cents=Number(card&&card.value_cents||0);
    var currency=String(card&&card.currency||'USD').toUpperCase();
    try{return new Intl.NumberFormat(undefined,{style:'currency',currency:currency}).format(cents/100);}
    catch(error){return currency+' '+(cents/100).toFixed(2);}
}

function valueLabel(card){
    var value=money(card);
    if(card.kind!=='plan')return value;
    var count=Math.max(1,Number(card.interval_count||1));
    var unit=String(card.interval_unit||'month');
    var interval=count===1?unit:count+' '+unit+'s';
    return value+' / '+interval;
}

function element(tag,className,text){
    var node=document.createElement(tag);
    if(className)node.className=className;
    if(text!==undefined)node.textContent=text;
    return node;
}

function fallbackLabel(card){
    if(card.variant==='greeting_card')return 'Card';
    if(card.variant==='multimedia_card')return 'Media';
    return card.kind==='microgift'?'Gift':(card.kind==='plan'?'Member':'Product');
}

function imageNode(url,className,alt){
    url=safeUrl(url);
    if(!url)return null;
    var image=document.createElement('img');
    image.className=className||'';
    image.src=url;
    image.alt=alt||'';
    image.loading='lazy';
    return image;
}

function mediaNode(card){
    var videoUrl=safeUrl(card.video_url);
    var audioUrl=safeUrl(card.audio_url);
    if(videoUrl){
        var video=document.createElement('video');
        video.className='mg-feed-card-media-control';
        video.controls=true;
        video.playsInline=true;
        video.preload='metadata';
        video.src=videoUrl;
        if(card.video_mime)video.type=String(card.video_mime);
        return video;
    }
    if(audioUrl){
        var audio=document.createElement('audio');
        audio.className='mg-feed-card-media-control';
        audio.controls=true;
        audio.preload='metadata';
        audio.src=audioUrl;
        return audio;
    }
    return null;
}

function preview(card){
    var classes='mg-feed-linked-preview is-'+String(card.kind||'item');
    var variant=safeClass(card.variant||'');
    if(variant)classes+=' is-'+variant;
    var wrap=element('div',classes);
    var url=safeUrl(card.image_url);
    if(url){
        var image=document.createElement('img');
        image.src=url;
        image.alt='';
        image.loading='lazy';
        image.addEventListener('error',function(){image.remove();wrap.textContent=fallbackLabel(card);},{once:true});
        wrap.appendChild(image);
    }else{
        wrap.textContent=fallbackLabel(card);
    }
    return wrap;
}

function actionNode(action){
    action=action||{};
    var url=safeUrl(action.url);
    if(url){
        var link=element('a','mg-btn mg-btn-primary mg-feed-linked-action',String(action.label||'Open'));
        link.href=url;
        if(!url.startsWith('/')){link.target='_blank';link.rel='noopener noreferrer';}
        return link;
    }
    var button=element('button','mg-btn mg-btn-soft mg-feed-linked-action',String(action.label||'Unavailable'));
    button.type='button';
    button.disabled=true;
    return button;
}

function isCardProduct(card){
    return card&&card.kind==='product'&&(card.variant==='greeting_card'||card.variant==='multimedia_card');
}

function cardUrl(card){
    return safeUrl(card&&card.action&&card.action.url)||'#';
}

function buildCardPage(name,extraClass){
    var page=element('section','mg-feed-card-page '+(extraClass||''));
    page.dataset.feedCardPage=name;
    page.hidden=name!=='cover';
    return page;
}

function cardExperienceNode(card){
    var variant=safeClass(card.variant||'greeting_card');
    var article=element('article','mg-feed-card-experience is-'+variant);
    article.dataset.cardVariant=variant;
    article.dataset.cardPage='cover';
    article.setAttribute('aria-label',card.variant==='multimedia_card'?'Multimedia greeting card preview':'Greeting card preview');

    var frame=element('div','mg-feed-card-frame');

    var cover=buildCardPage('cover','is-cover');
    var coverImage=imageNode(card.cover_url||card.image_url,'mg-feed-card-cover-image','');
    if(coverImage)cover.appendChild(coverImage);
    else cover.appendChild(element('div','mg-feed-card-fallback','Gift Card'));
    var coverOverlay=element('div','mg-feed-card-cover-overlay');
    coverOverlay.appendChild(element('span','mg-feed-card-kicker',card.variant==='multimedia_card'?'Multimedia Gift':'Greeting Card'));
    coverOverlay.appendChild(element('h4','',String(card.title||'Open your gift')));
    if(card.description)coverOverlay.appendChild(element('p','',String(card.description)));
    var openButton=element('button','mg-feed-card-open',card.variant==='multimedia_card'?'Open Multimedia Gift':'Open Gift');
    openButton.type='button';
    openButton.dataset.feedCardOpen='1';
    coverOverlay.appendChild(openButton);
    cover.appendChild(coverOverlay);

    var inside=buildCardPage('inside','is-inside');
    var insideArt=element('div','mg-feed-card-inside-art');
    var insideImage=imageNode(card.inside_url,'mg-feed-card-inside-image','');
    if(insideImage)insideArt.appendChild(insideImage);
    else insideArt.appendChild(element('div','mg-feed-card-inside-fallback','✦'));
    inside.appendChild(insideArt);
    var insideCopy=element('div','mg-feed-card-inside-copy');
    insideCopy.appendChild(element('span','mg-feed-card-kicker','Inside Message'));
    insideCopy.appendChild(element('h4','',String(card.title||'Gift Card')));
    insideCopy.appendChild(element('p','mg-feed-card-message',String(card.description||'Open the full card to view the complete gift.')));
    insideCopy.appendChild(element('strong','mg-feed-card-value',valueLabel(card)));
    var insideActions=element('div','mg-feed-card-actions');
    var hasMedia=Boolean(safeUrl(card.video_url)||safeUrl(card.audio_url));
    if(hasMedia){
        var mediaButton=element('button','mg-feed-card-secondary','View Media');
        mediaButton.type='button';
        mediaButton.dataset.feedCardPageTarget='media';
        insideActions.appendChild(mediaButton);
    }
    var fullLink=element('a','mg-feed-card-primary','Open Full Card');
    fullLink.href=cardUrl(card);
    insideActions.appendChild(fullLink);
    insideCopy.appendChild(insideActions);
    inside.appendChild(insideCopy);

    frame.appendChild(cover);
    frame.appendChild(inside);

    if(hasMedia){
        var media=buildCardPage('media','is-media');
        var mediaWrap=element('div','mg-feed-card-media-wrap');
        mediaWrap.appendChild(element('span','mg-feed-card-kicker',safeUrl(card.video_url)?'Video Message':'Audio Message'));
        var mediaControl=mediaNode(card);
        if(mediaControl)mediaWrap.appendChild(mediaControl);
        var mediaActions=element('div','mg-feed-card-actions');
        var backInside=element('button','mg-feed-card-secondary','Back to Card');
        backInside.type='button';
        backInside.dataset.feedCardPageTarget='inside';
        var mediaFull=element('a','mg-feed-card-primary','Open Full Card');
        mediaFull.href=cardUrl(card);
        mediaActions.append(backInside,mediaFull);
        mediaWrap.appendChild(mediaActions);
        media.appendChild(mediaWrap);
        frame.appendChild(media);
    }

    var nav=element('div','mg-feed-card-nav');
    var close=element('button','mg-feed-card-nav-btn','Cover');
    close.type='button';
    close.dataset.feedCardPageTarget='cover';
    var next=element('button','mg-feed-card-nav-btn',hasMedia?'Media':'Inside');
    next.type='button';
    next.dataset.feedCardPageTarget=hasMedia?'media':'inside';
    var external=element('a','mg-feed-card-nav-link','Full');
    external.href=cardUrl(card);
    nav.append(close,next,external);

    article.append(frame,nav);
    return article;
}

function setCardPage(card,pageName){
    if(!card)return;
    pageName=String(pageName||'cover');
    card.dataset.cardPage=pageName;
    card.querySelectorAll('[data-feed-card-page]').forEach(function(page){
        page.hidden=page.dataset.feedCardPage!==pageName;
    });
}

function cardNode(card){
    if(isCardProduct(card))return cardExperienceNode(card);
    var article=element('article','mg-feed-linked-card is-'+String(card.kind||'item'));
    var variant=safeClass(card.variant||'');
    if(variant){
        article.classList.add('is-'+variant);
        article.dataset.cardVariant=variant;
    }
    article.appendChild(preview(card));

    var body=element('div','mg-feed-linked-body');
    var top=element('div','mg-feed-linked-topline');
    top.append(element('span','mg-feed-linked-eyebrow',String(card.eyebrow||label(card.kind))));
    var status=element('span','mg-feed-linked-status is-'+String(card.status||'active').replace(/[^a-z0-9_-]/gi,''),label(card.status||'active'));
    top.appendChild(status);
    body.appendChild(top);
    body.appendChild(element('h4','',String(card.title||'Attached item')));
    if(card.description)body.appendChild(element('p','mg-feed-linked-description',String(card.description)));

    var details=element('div','mg-feed-linked-details');
    details.appendChild(element('strong','mg-feed-linked-value',valueLabel(card)));
    if(card.kind==='plan'&&Number(card.trial_days||0)>0)details.appendChild(element('span','mg-feed-linked-trial',Number(card.trial_days)+'-day trial'));
    body.appendChild(details);

    var footer=element('footer','mg-feed-linked-footer');
    var access=card.access||{};
    footer.appendChild(element('span','mg-feed-linked-access is-'+String(access.state||'available'),String(access.label||'Available')));
    footer.appendChild(actionNode(card.action));
    body.appendChild(footer);
    article.appendChild(body);
    return article;
}

function findPost(postId){
    return list.querySelector('.mg-feed-card[data-post-id="'+postId+'"]');
}

function render(postId,cards){
    var post=findPost(postId);
    if(!post)return;
    var row=post.querySelector('.mg-feed-attachments-row');
    if(!Array.isArray(cards)||!cards.length){
        if(row)row.remove();
        return;
    }
    if(!row){
        row=element('section','mg-feed-attachments-row');
        var stats=post.querySelector('.mg-feed-stats');
        post.insertBefore(row,stats||null);
    }
    row.className='mg-feed-attachments-row mg-feed-linked-grid';
    row.setAttribute('aria-label','Attached Microgifter items');
    row.replaceChildren();
    cards.forEach(function(item){row.appendChild(cardNode(item));});
}

async function flush(){
    timer=null;
    var ids=Array.from(pending).filter(function(id){return !loading.has(id)&&!failed.has(id)&&!cache.has(id);}).slice(0,36);
    ids.forEach(function(id){pending.delete(id);loading.add(id);});
    if(!ids.length)return;
    try{
        var response=await MG.get('/api/public/feed-attachments.php?post_ids='+encodeURIComponent(ids.join(',')));
        var data=response&&response.data?response.data:response;
        var cards=data&&data.cards?data.cards:{};
        ids.forEach(function(id){
            var items=Array.isArray(cards[id])?cards[id]:[];
            cache.set(id,items);
            render(id,items);
        });
    }catch(error){
        ids.forEach(function(id){failed.add(id);});
    }finally{
        ids.forEach(function(id){loading.delete(id);});
        if(pending.size)schedule();
    }
}

function schedule(){
    if(timer!==null)return;
    timer=window.setTimeout(flush,40);
}

function scan(scope){
    var posts=[];
    if(scope&&scope.matches&&scope.matches('.mg-feed-card[data-post-id]'))posts.push(scope);
    if(scope&&scope.querySelectorAll)posts=posts.concat(Array.from(scope.querySelectorAll('.mg-feed-card[data-post-id]')));
    posts.forEach(function(post){
        var id=String(post.dataset.postId||'');
        if(!id)return;
        if(cache.has(id)){render(id,cache.get(id));return;}
        if(!loading.has(id)&&!failed.has(id))pending.add(id);
    });
    if(pending.size)schedule();
}

root.addEventListener('click',function(event){
    var open=event.target.closest('[data-feed-card-open]');
    var target=event.target.closest('[data-feed-card-page-target]');
    if(!open&&!target)return;
    var card=event.target.closest('.mg-feed-card-experience');
    if(!card)return;
    event.preventDefault();
    setCardPage(card,open?'inside':target.dataset.feedCardPageTarget);
});

ensureStyles();
scan(list);
new MutationObserver(function(records){
    records.forEach(function(record){Array.from(record.addedNodes).forEach(scan);});
}).observe(list,{childList:true,subtree:true});
})(window,document);
