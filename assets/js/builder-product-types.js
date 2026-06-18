document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;

  var labels={
    simple_product:'Simple product',
    greeting_card:'Greeting card',
    multimedia_greeting_card:'Multimedia greeting card',
    simple_collab:'Simple collaboration'
  };

  function selectedType(){
    var input=root.querySelector('input[name="builder_type"]:checked');
    return input?input.value:'simple_product';
  }

  function supports(node,type){
    var types=String(node.dataset.builderTypes||'').split(/\s+/).filter(Boolean);
    return types.length===0||types.includes(type);
  }

  function updateTypeControls(){
    var type=selectedType();
    root.querySelectorAll('[data-builder-types]').forEach(function(node){
      var visible=supports(node,type);
      node.hidden=!visible;
      node.setAttribute('aria-hidden',visible?'false':'true');
      node.querySelectorAll('input,textarea,select,button').forEach(function(control){
        control.disabled=!visible;
      });
    });
    var label=root.querySelector('[data-preview-template-label]');
    if(label)label.textContent=labels[type]||'Product';
    var headline=root.querySelector('#headline');
    var message=root.querySelector('#message');
    if(headline)headline.required=type==='greeting_card';
    if(message)message.required=type==='greeting_card';
  }

  root.querySelectorAll('input[name="builder_type"]').forEach(function(input){
    input.addEventListener('change',updateTypeControls);
  });

  var publish=root.querySelector('[data-publish-product]');
  if(publish){
    publish.addEventListener('click',function(event){
      if(selectedType()!=='greeting_card')return;
      var headline=root.querySelector('#headline');
      var message=root.querySelector('#message');
      if(headline&&!headline.value.trim()){
        event.preventDefault();
        event.stopImmediatePropagation();
        headline.reportValidity();
        headline.focus();
        return;
      }
      if(message&&!message.value.trim()){
        event.preventDefault();
        event.stopImmediatePropagation();
        message.reportValidity();
        message.focus();
      }
    },true);
  }

  updateTypeControls();
});
