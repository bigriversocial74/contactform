(function(window, document){
  'use strict';

  var root = document.querySelector('[data-ads-manager]');
  if (!root) return;

  /*
   * Root-cause rollback:
   * The grouped product/reward/campaign picker override made merchant-ad-manager.php unstable.
   * Leave this file loaded for compatibility, but do not replace the base product picker.
   * The original picker behavior in merchant-ad-manager.js remains the source of truth.
   */
  root.setAttribute('data-product-picker-groups-disabled', 'true');
})(window, document);
