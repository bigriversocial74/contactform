window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG.get || MG.publicProfileRuntimeReady) return;

  MG.publicProfileRuntimeReady = true;
  MG.publicProfileData = null;
  var originalGet = MG.get;

  MG.get = async function (path, options) {
    var requestPath = String(path || '');
    var isProfileRead = requestPath.indexOf('/api/public/profile.php?') === 0;
    var isInitialRead = isProfileRead && requestPath.indexOf('_cursor=') === -1;

    if (isInitialRead) {
      requestPath = requestPath
        .replace('product_limit=1', 'product_limit=6')
        .replace('post_limit=1', 'post_limit=6')
        .replace('plan_limit=1', 'plan_limit=6');
    }

    var response = await originalGet(requestPath, options);
    if (isInitialRead) {
      MG.publicProfileData = response && response.data ? response.data : response;
      document.dispatchEvent(new CustomEvent('mg:public-profile:data', {
        detail: MG.publicProfileData,
      }));
    }
    return response;
  };
})(window, document);
