document.addEventListener('DOMContentLoaded',function(){
'use strict';
var sidebar=document.querySelector('[data-account-sidebar]');
var toggle=document.querySelector('[data-account-sidebar-toggle]');
var close=document.querySelector('[data-account-sidebar-close]');
var backdrop=document.querySelector('[data-account-sidebar-backdrop]');
if(!sidebar||!toggle)return;
function setOpen(open){sidebar.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',open?'true':'false');if(backdrop)backdrop.hidden=!open;document.body.classList.toggle('mg-account-menu-open',open);}
function setActive(link){sidebar.querySelectorAll('[data-account-nav]').forEach(function(item){item.classList.toggle('is-active',item===link);});}
function navKeyForLocation(){
var path=window.location.pathname||'';
var hash=(window.location.hash||'').replace('#','');
if(path.indexOf('/account-commerce.php')!==-1){if(['overview','orders','items'].indexOf(hash)!==-1)return hash;return 'overview';}
if(path.indexOf('/cart.php')!==-1)return 'cart';
if(path.indexOf('/notifications.php')!==-1)return 'notifications';
if(path.indexOf('/notification-preferences.php')!==-1)return 'preferences';
if(path.indexOf('/account-subscriptions.php')!==-1)return 'subscriptions';
if(path.indexOf('/account.php')!==-1)return 'settings';
return '';
}
function findNav(key){var match=null;sidebar.querySelectorAll('[data-account-nav]').forEach(function(item){if(item.getAttribute('data-account-nav')===key)match=item;});return match;}
function syncActiveFromLocation(){var key=navKeyForLocation();var link=key?findNav(key):null;if(link){setActive(link);activateView(key);}}
function activateView(key){
if(key==='inbox'){var received=document.querySelector('[data-gifts-scope="received"]');if(received)received.click();}
if(key==='sent'){var sent=document.querySelector('[data-gifts-scope="sent"]');if(sent)sent.click();}
if(key==='claimed'){var status=document.querySelector('[data-claims-status]');if(status){status.value='redeemed';status.dispatchEvent(new Event('change',{bubbles:true}));}}
}
toggle.addEventListener('click',function(){setOpen(!sidebar.classList.contains('is-open'));});
if(close)close.addEventListener('click',function(){setOpen(false);});
if(backdrop)backdrop.addEventListener('click',function(){setOpen(false);});
sidebar.querySelectorAll('[data-account-nav]').forEach(function(link){link.addEventListener('click',function(){setActive(link);activateView(link.getAttribute('data-account-nav')||'');if(window.innerWidth<=900)setOpen(false);});});
window.addEventListener('hashchange',syncActiveFromLocation);
syncActiveFromLocation();
document.addEventListener('keydown',function(event){if(event.key==='Escape')setOpen(false);});
window.addEventListener('resize',function(){if(window.innerWidth>900)setOpen(false);});
});