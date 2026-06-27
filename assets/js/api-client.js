window.Microgifter = window.Microgifter || {};

(function () {
  'use strict';

  function isFormData(value) {
    return typeof FormData !== 'undefined' && value instanceof FormData;
  }

  function normalizeHeaders(headers) {
    return Object.assign({
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': Microgifter.getCsrfToken ? Microgifter.getCsrfToken() : ''
    }, headers || {});
  }

  async function parseResponse(response) {
    var contentType = response.headers.get('content-type') || '';
    if (contentType.indexOf('application/json') !== -1) {
      return response.json().catch(function () { return {}; });
    }
    return response.text();
  }

  function detectHumanChallenge(data) {
    if (typeof data !== 'string') return null;
    var text = data.trim();
    if (!text || text.indexOf('document.cookie') === -1 || text.indexOf('humans_') === -1) return null;
    var match = text.match(/document\.cookie\s*=\s*["']([^"']+)["']/i);
    if (!match || !match[1]) return { cookie: '', raw: text };
    return { cookie: match[1], raw: text };
  }

  function applyHumanChallenge(challenge) {
    if (!challenge || !challenge.cookie) return false;
    try {
      var cookie = challenge.cookie;
      if (cookie.indexOf('path=') === -1) cookie += '; path=/';
      if (location.protocol === 'https:' && cookie.indexOf('Secure') === -1) cookie += '; Secure';
      document.cookie = cookie;
      document.dispatchEvent(new CustomEvent('mg:human-challenge', { detail: { cookie: challenge.cookie } }));
      return true;
    } catch (error) {
      return false;
    }
  }

  function cleanStringError(data) {
    var challenge = detectHumanChallenge(data);
    if (challenge) return 'Browser verification refreshed. Please try again.';
    return String(data || '')
      .replace(/<script[\s\S]*?<\/script>/gi, '')
      .replace(/<[^>]+>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 300) || 'Request failed.';
  }

  function apiError(response, data) {
    var message = 'Request failed.';
    if (data && typeof data === 'object') {
      message = data.message || data.error || message;
    } else if (typeof data === 'string' && data.trim()) {
      message = cleanStringError(data);
    }
    var error = new Error(message);
    error.status = response.status;
    error.data = data;
    error.isHumanChallenge = Boolean(detectHumanChallenge(data));
    return error;
  }

  function cloneRequestForRetry(request) {
    var retry = Object.assign({}, request);
    retry.headers = Object.assign({}, request.headers || {});
    return retry;
  }

  Microgifter.api = async function api(path, options) {
    var request = Object.assign({
      method: 'GET',
      credentials: 'same-origin',
      headers: {}
    }, options || {});

    request.headers = normalizeHeaders(request.headers);

    if (request.body && !isFormData(request.body)) {
      request.headers['Content-Type'] = request.headers['Content-Type'] || 'application/json';
      if (request.headers['Content-Type'].indexOf('application/json') !== -1 && typeof request.body !== 'string') {
        request.body = JSON.stringify(request.body);
      }
    }

    var response = await fetch(path, request);
    var data = await parseResponse(response);
    var challenge = detectHumanChallenge(data);

    if (!response.ok && challenge && !request.__mgHumanRetry && applyHumanChallenge(challenge)) {
      var retryRequest = cloneRequestForRetry(request);
      retryRequest.__mgHumanRetry = true;
      response = await fetch(path, retryRequest);
      data = await parseResponse(response);
    }

    if (!response.ok) {
      if (response.status === 401) document.dispatchEvent(new CustomEvent('mg:unauthorized', { detail: data }));
      if (response.status === 403) document.dispatchEvent(new CustomEvent('mg:forbidden', { detail: data }));
      throw apiError(response, data);
    }

    if (challenge) {
      applyHumanChallenge(challenge);
      throw apiError(response, data);
    }

    return data;
  };

  Microgifter.get = function get(path, options) {
    return Microgifter.api(path, Object.assign({}, options || {}, { method: 'GET' }));
  };

  Microgifter.post = function post(path, body, options) {
    return Microgifter.api(path, Object.assign({}, options || {}, { method: 'POST', body: body }));
  };

  Microgifter.patch = function patch(path, body, options) {
    return Microgifter.api(path, Object.assign({}, options || {}, { method: 'PATCH', body: body }));
  };

  Microgifter.delete = function deleteRequest(path, body, options) {
    return Microgifter.api(path, Object.assign({}, options || {}, { method: 'DELETE', body: body || {} }));
  };

  Microgifter.submitForm = async function submitForm(form) {
    return Microgifter.api(form.action, {
      method: form.method || 'POST',
      body: new FormData(form)
    });
  };
})();