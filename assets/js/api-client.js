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

  function apiError(response, data) {
    var message = 'Request failed.';
    if (data && typeof data === 'object') {
      message = data.message || data.error || message;
    } else if (typeof data === 'string' && data.trim()) {
      message = data.trim();
    }
    var error = new Error(message);
    error.status = response.status;
    error.data = data;
    return error;
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

    if (!response.ok) {
      if (response.status === 401) document.dispatchEvent(new CustomEvent('mg:unauthorized', { detail: data }));
      if (response.status === 403) document.dispatchEvent(new CustomEvent('mg:forbidden', { detail: data }));
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
