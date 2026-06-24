document.addEventListener('DOMContentLoaded',function(){
'use strict';
var inputs=Array.prototype.slice.call(document.querySelectorAll('[data-agent-global-search]'));
if(!inputs.length)return;
function currentQuery(){var active=inputs.find(function(input){return document.activeElement===input;});return (active||inputs[0]).value.trim().toLowerCase();}
function syncInputs(source){inputs.forEach(function(input){if(input!==source)input.value=source.value;});}
function filterRows(){var query=currentQuery();document.querySelectorAll('[data-gift-list] .mg-gift-row').forEach(function(row){row.hidden=query!==''&&!row.textContent.toLowerCase().includes(query);});}
inputs.forEach(function(input){input.addEventListener('input',function(){syncInputs(input);filterRows();});});
var list=document.querySelector('[data-gift-list]');
if(list)new MutationObserver(filterRows).observe(list,{childList:true,subtree:true});
});
