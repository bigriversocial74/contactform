window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG.get || MG.publicProfileRuntimeReady) return;

  MG.publicProfileRuntimeReady = true;
  MG.publicProfileData = null;
  var originalGet = MG.get;
  var originalFetch = window.fetch;

  function profileCampaignUrl(item) {
    if (!item || typeof item !== 'object') return '';
    var ref = String(item.public_slug || item.slug || item.id || item.public_id || '').trim();
    if (!ref) return String(item.url || '');
    var page = '';
    switch (String(item.type || item.campaign_type || '')) {
      case 'watch_video_reward':
        page = '/watch-reward.php';
        break;
      case 'listen_music_reward':
        page = '/listen-reward.php';
        break;
      default:
        return String(item.url || '');
    }
    return page + '?campaign=' + encodeURIComponent(ref);
  }

  function normalizeProfileInvestmentCampaignLinks(data) {
    var items = data && data.data && data.data.campaigns && Array.isArray(data.data.campaigns.items)
      ? data.data.campaigns.items
      : (data && data.campaigns && Array.isArray(data.campaigns.items) ? data.campaigns.items : []);
    items.forEach(function (item) {
      var nextUrl = profileCampaignUrl(item);
      if (nextUrl) item.url = nextUrl;
    });
    return data;
  }

  if (typeof originalFetch === 'function' && !MG.publicProfileInvestmentFetchReady) {
    MG.publicProfileInvestmentFetchReady = true;
    window.fetch = function (input, init) {
      var requestUrl = '';
      try { requestUrl = String(input && input.url ? input.url : input || ''); } catch (error) { requestUrl = ''; }
      var isInvestmentRead = requestUrl.indexOf('/api/public/profile-investment.php?') === 0 || requestUrl.indexOf(location.origin + '/api/public/profile-investment.php?') === 0;
      return originalFetch.apply(this, arguments).then(function (response) {
        if (!isInvestmentRead || !response || !response.ok || typeof response.clone !== 'function') return response;
        return response.clone().json().then(function (json) {
          var headers = new Headers(response.headers);
          headers.set('content-type', 'application/json; charset=utf-8');
          return new Response(JSON.stringify(normalizeProfileInvestmentCampaignLinks(json)), {
            status: response.status,
            statusText: response.statusText,
            headers: headers,
          });
        }).catch(function () { return response; });
      });
    };
  }

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

(function (document) {
  'use strict';
  if (document.querySelector('script[data-public-profile-reviews-loader]')) return;
  var script = document.createElement('script');
  script.src = '/assets/js/public-profile-reviews.js?v=1.0.0';
  script.defer = true;
  script.setAttribute('data-public-profile-reviews-loader', '1');
  document.head.appendChild(script);
})(document);
