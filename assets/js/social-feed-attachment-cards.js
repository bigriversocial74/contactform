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
        if(!['http:','https:'].includes(parsed.protocol))return null;
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

function cardCoverNode(card){
    var variant=safeClass(card.variant||'greeting_card');
    var article=element('article','mg-feed-linked-card mg-feed-card-cover-preview is-product is-'+variant);
    article.dataset.cardVariant=variant;

    var art=element('div','mg-feed-card-cover-art');
    var imageUrl=safeUrl(card.image_url);
    if(imageUrl){
        var image=document.createElement('img');
        image.src=imageUrl;
        image.alt='';
        image.loading='lazy';
        image.addEventListener('error',function(){image.remove();art.appendChild(element('div','mg-feed-card-cover-fallback',fallbackLabel(card)));},{once:true});
        art.appendChild(image);
    }else{
        art.appendChild(element('div','mg-feed-card-cover-fallback',fallbackLabel(card)));
    }
    art.appendChild(element('span','mg-feed-card-cover-badge',card.variant==='multimedia_card'?'Multimedia Card':'Greeting Card'));

    var body=element('div','mg-feed-card-cover-body');
    body.appendChild(element('h4','',String(card.title||'Greeting card')));
    body.appendChild(element('strong','mg-feed-card-cover-value',valueLabel(card)));
    var footer=element('footer','mg-feed-card-cover-footer');
    footer.appendChild(actionNode(card.action));
    body.appendChild(footer);

    article.append(art,body);
    return article;
}

function cardNode(card){
    if(isCardProduct(card))return cardCoverNode(card);

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

ensureStyles();
scan(list);
new MutationObserver(function(records){
    records.forEach(function(record){Array.from(record.addedNodes).forEach(scan);});
}).observe(list,{childList:true,subtree:true});
})(window,document);
