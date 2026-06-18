(() => {
  'use strict';
  const root=document.querySelector('[data-commerce-root]');
  if(!root)return;
  root.dataset.commerceClient='loading';
})();
