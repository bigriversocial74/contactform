window.Microgifter = window.Microgifter || {};
(function (window) {
  'use strict';
  var MG = window.Microgifter;
  if (!MG || typeof MG.post !== 'function' || MG.__canvasBehaviorPostPatched) return;
  var storageKey = 'mgCanvasMerchantBehavior:v1';
  function flag(value) { return value === true || value === 1 || value === '1' || value === 'true' || value === 'on' ? 1 : 0; }
  function settings() {
    var stored = {};
    try { stored = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {}; } catch (error) { stored = {}; }
    return Object.assign({ interaction_mode:'guided', response_tone:'warm_professional', greeting_message:'', auto_greet:1, recommend_campaigns:1, handoff_behavior:'offer_handoff', trigger_reaction:'message_when_triggered' }, stored, { auto_greet:flag(stored.auto_greet == null ? 1 : stored.auto_greet), recommend_campaigns:flag(stored.recommend_campaigns == null ? 1 : stored.recommend_campaigns) });
  }
  var basePost = MG.post.bind(MG);
  MG.__canvasBehaviorPostPatched = true;
  MG.post = function (url, data) {
    var requestUrl = String(url || '');
    if (requestUrl.indexOf('/api/merchant-canvas/auto-chat.php') !== -1) {
      var current = settings();
      var context = data && data.context ? String(data.context) : 'merchant_proximity';
      if (current.interaction_mode === 'observe_only') return Promise.resolve({ data:{ sent:false, disabled:true, context:context, reason:'merchant_observe_only' } });
      if (context === 'merchant_proximity' && !flag(current.auto_greet)) return Promise.resolve({ data:{ sent:false, disabled:true, context:context, reason:'merchant_auto_greet_disabled' } });
      data = Object.assign({}, data || {}, { merchant_behavior:current });
    }
    return basePost.apply(MG, [url, data].concat(Array.prototype.slice.call(arguments, 2)));
  };
})(window);
