document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var forms = document.querySelectorAll('[data-check-in-reward-form]');
  if (!forms.length) return;

  function setStatus(form, message, type) {
    var node = form.querySelector('[data-check-in-status]') || form.querySelector('[data-campaign-status]');
    if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
      Microgifter.setStatus(node, message, type || '');
      return;
    }
    if (node) node.textContent = message || '';
  }

  function setField(form, name, value) {
    var field = form.elements[name];
    if (field) field.value = value == null ? '' : String(value);
  }

  function setGeoButton(form, busy) {
    var button = form.querySelector('[data-check-in-geolocate]');
    if (!button) return;
    button.disabled = !!busy;
    button.textContent = busy ? 'Finding your location…' : (form.getAttribute('data-geo-ready') === '1' ? 'Location captured' : 'Use my location');
  }

  function capture(form) {
    if (!navigator.geolocation) {
      setStatus(form, 'This browser does not support location check-in.', 'error');
      return Promise.reject(new Error('Geolocation unavailable'));
    }
    setGeoButton(form, true);
    setStatus(form, 'Requesting location permission…');
    return new Promise(function (resolve, reject) {
      navigator.geolocation.getCurrentPosition(function (position) {
        var coords = position.coords || {};
        setField(form, 'entry_latitude', coords.latitude);
        setField(form, 'entry_longitude', coords.longitude);
        setField(form, 'entry_accuracy_meters', coords.accuracy || '');
        setField(form, 'entry_location_permission', 'granted');
        form.setAttribute('data-geo-ready', '1');
        setGeoButton(form, false);
        setStatus(form, 'Location captured. Submit to match the nearest registered merchant location.', 'success');
        resolve(position);
      }, function (error) {
        setField(form, 'entry_location_permission', 'denied');
        form.setAttribute('data-geo-ready', '0');
        setGeoButton(form, false);
        var message = error && error.code === 1 ? 'Location permission is required for this check-in reward.' : 'Unable to capture your location. Move closer and try again.';
        setStatus(form, message, 'error');
        reject(new Error(message));
      }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
    });
  }

  forms.forEach(function (form) {
    var button = form.querySelector('[data-check-in-geolocate]');
    if (button) button.addEventListener('click', function (event) { event.preventDefault(); capture(form).catch(function () {}); });
    form.addEventListener('submit', function (event) {
      if (form.getAttribute('data-geo-ready') === '1') return;
      event.preventDefault();
      capture(form).then(function () {
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      }).catch(function () {});
    }, true);
  });
});
