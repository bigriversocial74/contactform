(function(window, document){
  'use strict';

  var root = document.querySelector('[data-ads-manager]');
  if (!root) return;

  var picker = root.querySelector('[data-product-picker]');
  if (!picker) return;

  var products = [];
  var rendering = false;
  var groupOrder = ['reward_template', 'campaign', 'catalog_product', 'other'];
  var groupLabels = {
    reward_template: 'Rewards',
    campaign: 'Campaigns',
    catalog_product: 'Products',
    other: 'Other options'
  };
  var sourceLabels = {
    reward_template: 'Reward',
    campaign: 'Campaign',
    catalog_product: 'Product',
    other: 'Item'
  };

  function sourceKey(product){
    var source = String(product && product.source || '').toLowerCase();
    if (source === 'reward_template') return 'reward_template';
    if (source === 'campaign') return 'campaign';
    if (source === 'catalog_product') return 'catalog_product';
    return 'other';
  }

  function titleCase(value){
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function(letter){ return letter.toUpperCase(); });
  }

  function cleanDetail(product, label){
    var detail = String(product && product.value_label || '').trim();
    if (detail) {
      detail = detail.replace(new RegExp('^' + label + '\\s*[·:-]\\s*', 'i'), '').trim();
    }
    if (!detail) detail = titleCase(product && product.status || '');
    return detail;
  }

  function optionLabel(product){
    var key = sourceKey(product);
    var label = product.source_label || sourceLabels[key] || sourceLabels.other;
    var detail = cleanDetail(product, label);
    var title = String(product.title || 'Untitled item').trim();
    return label + (detail ? ' · ' + detail : '') + ' — ' + title;
  }

  function render(){
    if (!products.length) return;
    rendering = true;
    var selected = picker.value || '';
    picker.innerHTML = '';

    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Choose a reward, campaign, or product…';
    picker.appendChild(placeholder);

    groupOrder.forEach(function(key){
      var items = products.filter(function(product){ return sourceKey(product) === key; });
      if (!items.length) return;
      var group = document.createElement('optgroup');
      group.label = groupLabels[key] + ' (' + items.length + ')';
      items.forEach(function(product){
        var option = document.createElement('option');
        option.value = String(product.id || '');
        option.textContent = optionLabel(product);
        option.dataset.source = sourceKey(product);
        option.dataset.sourceLabel = product.source_label || sourceLabels[key] || sourceLabels.other;
        group.appendChild(option);
      });
      picker.appendChild(group);
    });

    if (selected && Array.prototype.some.call(picker.options, function(option){ return option.value === selected; })) {
      picker.value = selected;
    }
    rendering = false;
  }

  function loadGroupedProducts(){
    return fetch('/api/ads/merchant-products.php?status=active', {credentials:'same-origin', headers:{Accept:'application/json'}})
      .then(function(response){ return response.json(); })
      .then(function(out){
        var data = out && out.data || {};
        if (!out || !out.ok || !data.schema_ready || !Array.isArray(data.products)) return;
        products = data.products;
        render();
      })
      .catch(function(){ /* Keep the base picker untouched if the grouping request fails. */ });
  }

  var observer = new MutationObserver(function(){
    if (rendering || !products.length) return;
    render();
  });
  observer.observe(picker, {childList:true, subtree:true});

  loadGroupedProducts();
})(window, document);
