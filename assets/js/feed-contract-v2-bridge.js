window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG.get || MG.feedContractV2BridgeInstalled) return;
  MG.feedContractV2BridgeInstalled = true;

  var originalGet = MG.get;

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function isPublicFeedPath(path) {
    return /^\/api\/public\/feed\.php(?:\?|$)/.test(String(path || ''));
  }

  function publish(response) {
    var data = payload(response);
    if (!data || Number(data.contract_version || 0) < 2) return;
    window.MicrogifterFeedContractV2Latest = data;
    window.setTimeout(function () {
      document.dispatchEvent(new CustomEvent('mg:feed-contract-v2', { detail: data }));
    }, 0);
  }

  MG.get = function feedContractGet(path, options) {
    var request = originalGet.call(MG, path, options);
    if (!isPublicFeedPath(path)) return request;
    return request.then(function (response) {
      publish(response);
      return response;
    });
  };
})(window, document);
