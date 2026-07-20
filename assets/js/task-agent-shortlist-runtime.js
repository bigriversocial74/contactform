(function () {
  'use strict';

  function text(value) { return String(value == null ? '' : value); }
  function esc(value) {
    return text(value).replace(/[&<>"']/g, function (ch) {
      return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' })[ch];
    });
  }
  function internalUrl(value) {
    try {
      var parsed = new URL(text(value), window.location.origin);
      if (parsed.origin !== window.location.origin || ['http:','https:'].indexOf(parsed.protocol) === -1) return '';
      return parsed.pathname + parsed.search + parsed.hash;
    } catch (error) { return ''; }
  }
  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style:'currency', currency:text(currency || 'USD').toUpperCase() }).format(Number(cents || 0) / 100);
    } catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }

  function media(product, href) {
    var tag = href ? 'a' : 'div';
    var attrs = href ? ' href="' + esc(href) + '" data-agent-open-link' : '';
    var image = internalUrl(product.cover_url || product.image_url || '');
    return '<' + tag + ' class="mg-agent-shortlist-media"' + attrs + '>'
      + (image ? '<img src="' + esc(image) + '" alt="" loading="lazy" decoding="async">' : esc(text(product.title || 'G').charAt(0).toUpperCase()))
      + '</' + tag + '>';
  }

  function renderMarketplaceCard(card) {
    var href = internalUrl(card.url || '');
    var payload = esc(JSON.stringify(card.review_payload || {}));
    var action = '';
    if (card.action === 'shortlist_product') action = '<button type="button" data-shortlist-product data-action-payload="' + payload + '">' + esc(card.action_label || 'Shortlist') + '</button>';
    if (card.action === 'remove_shortlist') action = '<button type="button" data-shortlist-remove data-action-payload="' + payload + '">' + esc(card.action_label || 'Remove from shortlist') + '</button>';
    return '<article class="is-marketplace_product" data-agent-product-card>'
      + media({ title:card.title, cover_url:card.image_url }, href)
      + '<div class="mg-agent-shortlist-copy"><span>Published local gift</span><h4>' + esc(card.title || 'Local gift') + '</h4><p>' + esc(card.body || '') + '</p>'
      + '<div class="mg-agent-shortlist-meta"><strong>' + esc(money(card.price_cents,card.currency)) + '</strong><span>' + esc(card.merchant_name || 'Local merchant') + '</span>' + (card.location ? '<span>' + esc(card.location) + '</span>' : '') + '</div></div>'
      + '<div class="mg-agent-shortlist-actions">' + (href ? '<a href="' + esc(href) + '" data-agent-open-link>Review product</a>' : '') + action + '</div></article>';
  }

  function renderPlanCard(card) {
    var product = card.product || {};
    var plan = card.plan || {};
    var href = internalUrl(product.url || '');
    var payload = esc(JSON.stringify(card.review_payload || {}));
    var primary = '';
    if (card.action === 'select_plan_product') primary = '<button type="button" data-plan-product-select data-action-payload="' + payload + '">' + esc(card.action_label || 'Add to gift plan') + '</button>';
    if (card.action === 'remove_plan_product') primary = '<button type="button" data-plan-product-remove data-action-payload="' + payload + '">' + esc(card.action_label || 'Remove from gift plan') + '</button>';
    if (card.action === 'cart_handoff') primary = '<button type="button" data-plan-cart-handoff data-action-payload="' + payload + '">' + esc(card.action_label || 'Add to cart') + '</button>';
    var secondary = card.action === 'cart_handoff'
      ? '<button type="button" data-plan-product-remove data-action-payload="' + payload + '">Change selection</button>'
      : '';
    return '<article class="is-plan_product_selection" data-agent-plan-product-card>'
      + media(product, href)
      + '<div class="mg-agent-shortlist-copy"><span>' + esc(plan.title || 'Gift plan') + '</span><h4>' + esc(card.title || product.title || 'Selected product') + '</h4><p>' + esc(card.body || '') + '</p>'
      + '<div class="mg-agent-shortlist-meta"><strong>' + esc(money(product.value_cents,product.currency)) + '</strong><span>' + esc(product.merchant_name || 'Local merchant') + '</span></div></div>'
      + '<div class="mg-agent-shortlist-actions">' + (href ? '<a href="' + esc(href) + '" data-agent-open-link>Review product</a>' : '') + primary + secondary + '</div></article>';
  }

  function renderCard(card) {
    if (!card) return '';
    if (card.type === 'marketplace_product') return renderMarketplaceCard(card);
    if (card.type === 'plan_product_selection') return renderPlanCard(card);
    return '';
  }

  window.MicrogifterTaskAgentShortlist = { renderCard: renderCard };

  document.addEventListener('DOMContentLoaded', function () {
    var selectedNode = document.getElementById('mg-selected-agent-id');
    var agentId = selectedNode ? JSON.parse(selectedNode.textContent || '""') : '';
    var root = document.querySelector('[data-agent-instance-canvas]');
    if (!agentId || !root) return;
    var status = root.querySelector('[data-agent-runtime-status]');

    function csrf() { var node=document.querySelector('meta[name="csrf-token"]');return node?node.content:''; }
    function payload(button) { try { return JSON.parse(button.getAttribute('data-action-payload') || '{}'); } catch (error) { return {}; } }
    async function post(url, body) {
      var response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':csrf()},body:JSON.stringify(body)});
      var json=await response.json();
      if(!response.ok||!json.ok)throw new Error(json.message||'Unable to complete the requested action.');
      return json.data||json;
    }

    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-shortlist-product],[data-shortlist-remove],[data-plan-product-select],[data-plan-product-remove],[data-plan-cart-handoff]');
      if (!button) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      var data = payload(button);
      button.disabled = true;

      if (button.matches('[data-shortlist-product]')) {
        post('/api/agents/runtime.php',{id:agentId,action:'add_shortlist',product_id:data.product_id||'',recipient_context:data.recipient_context||{}})
          .then(function(){button.textContent='Shortlisted';if(status)status.textContent='Product added to this agent’s shortlist. No AI credits used.';})
          .catch(function(error){button.disabled=false;if(status)status.textContent=error.message;});
        return;
      }
      if (button.matches('[data-shortlist-remove]')) {
        post('/api/agents/runtime.php',{id:agentId,action:'remove_shortlist',shortlist_id:data.shortlist_id||''})
          .then(function(){var card=button.closest('[data-agent-product-card]');if(card)card.remove();if(status)status.textContent='Product removed from this agent’s shortlist. No AI credits used.';})
          .catch(function(error){button.disabled=false;if(status)status.textContent=error.message;});
        return;
      }
      if (button.matches('[data-plan-product-select]')) {
        post('/api/agents/runtime.php',{id:agentId,action:'select_plan_product',shortlist_id:data.shortlist_id||'',plan_id:data.plan_id||''})
          .then(function(result){var card=button.closest('[data-agent-plan-product-card]');if(card&&result.card)card.outerHTML=renderPlanCard(result.card);if(status)status.textContent='Product added to the gift plan. No cart change and no AI credits used.';})
          .catch(function(error){button.disabled=false;if(status)status.textContent=error.message;});
        return;
      }
      if (button.matches('[data-plan-product-remove]')) {
        post('/api/agents/runtime.php',{id:agentId,action:'remove_plan_product',shortlist_id:data.shortlist_id||'',plan_id:data.plan_id||''})
          .then(function(){var card=button.closest('[data-agent-plan-product-card]');if(card)card.remove();if(status)status.textContent='Product removed from the gift plan. The shortlist remains unchanged.';})
          .catch(function(error){button.disabled=false;if(status)status.textContent=error.message;});
        return;
      }
      post('/api/commerce/cart-items.php',{product_version_id:data.product_version_id||'',quantity:Number(data.quantity||1),agent_action:'gift_plan'})
        .then(function(){window.location.assign('/cart.php');})
        .catch(function(error){button.disabled=false;if(status)status.textContent=error.message;});
    }, true);
  });
})();
