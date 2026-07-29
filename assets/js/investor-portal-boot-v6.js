(() => {
  'use strict';

  const nativeFetch = window.fetch.bind(window);
  let portalGet = null;

  const portalRequest = (input, init = {}) => {
    try {
      const request = input instanceof Request ? input : null;
      const url = new URL(request ? request.url : String(input), window.location.origin);
      const method = String(init.method || request?.method || 'GET').toUpperCase();
      return url.origin === window.location.origin
        && url.pathname === '/api/investment/portal.php'
        && method === 'GET';
    } catch (_) {
      return false;
    }
  };

  const cloneResponse = (snapshot) => new Response(snapshot.body, {
    status: snapshot.status,
    statusText: snapshot.statusText,
    headers: snapshot.headers,
  });

  window.fetch = (input, init = {}) => {
    if (!portalRequest(input, init)) {
      return nativeFetch(input, init);
    }

    if (!portalGet) {
      portalGet = nativeFetch(input, init)
        .then(async (response) => ({
          body: await response.text(),
          status: response.status,
          statusText: response.statusText,
          headers: Array.from(response.headers.entries()),
        }))
        .catch((error) => {
          portalGet = null;
          throw error;
        });
    }

    return portalGet.then(cloneResponse);
  };

  window.MicrogifterInvestorPortal = window.MicrogifterInvestorPortal || {};
  window.MicrogifterInvestorPortal.clearBootCache = () => { portalGet = null; };
})();
