/* Microgifter cookie consent manager v1. */
(function (window, document) {
  'use strict';

  var STORAGE_KEY = 'mg_cookie_consent_v1';
  var COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 180;
  var CATEGORIES = ['necessary', 'functional', 'analytics', 'marketing', 'external_media'];
  var OPTIONAL_CATEGORIES = ['functional', 'analytics', 'marketing', 'external_media'];
  var root = null;
  var banner = null;
  var overlay = null;
  var dialog = null;
  var statusNode = null;
  var policyVersion = '2026-07-23.1';
  var consent = null;
  var lastFocused = null;
  var statusTimer = 0;
  var observer = null;

  function defaultCategories() {
    return {
      necessary: true,
      functional: false,
      analytics: false,
      marketing: false,
      external_media: false
    };
  }

  function normalizeCategories(input) {
    var normalized = defaultCategories();
    if (!input || typeof input !== 'object') return normalized;
    OPTIONAL_CATEGORIES.forEach(function (category) {
      normalized[category] = input[category] === true;
    });
    return normalized;
  }

  function createConsentId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      var bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
      bytes[6] = (bytes[6] & 15) | 64;
      bytes[8] = (bytes[8] & 63) | 128;
      var hex = Array.prototype.map.call(bytes, function (byte) {
        return byte.toString(16).padStart(2, '0');
      }).join('');
      return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
    }
    return 'mg-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14);
  }

  function getCookie(name) {
    var target = name + '=';
    var parts = document.cookie ? document.cookie.split(';') : [];
    for (var i = 0; i < parts.length; i += 1) {
      var part = parts[i].trim();
      if (part.indexOf(target) === 0) return part.slice(target.length);
    }
    return '';
  }

  function safeParse(raw) {
    if (!raw) return null;
    try {
      return JSON.parse(decodeURIComponent(raw));
    } catch (error) {
      try {
        return JSON.parse(raw);
      } catch (ignored) {
        return null;
      }
    }
  }

  function storageGet() {
    try {
      return window.localStorage.getItem(STORAGE_KEY) || '';
    } catch (error) {
      return '';
    }
  }

  function storageSet(value) {
    try {
      window.localStorage.setItem(STORAGE_KEY, value);
    } catch (error) {
      // The first-party cookie remains the authority when storage is unavailable.
    }
  }

  function storageRemove() {
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch (error) {
      // Ignore unavailable storage.
    }
  }

  function isCurrent(record) {
    if (!record || typeof record !== 'object') return false;
    if (record.version !== policyVersion) return false;
    if (!record.id || !record.decided_at || !record.categories) return false;
    var decided = Date.parse(record.updated_at || record.decided_at);
    if (!Number.isFinite(decided)) return false;
    return Date.now() - decided <= COOKIE_MAX_AGE_SECONDS * 1000;
  }

  function readConsent() {
    var fromCookie = safeParse(getCookie(STORAGE_KEY));
    var fromStorage = safeParse(storageGet());
    var record = isCurrent(fromCookie) ? fromCookie : (isCurrent(fromStorage) ? fromStorage : null);
    if (!record) return null;
    record.categories = normalizeCategories(record.categories);
    return record;
  }

  function writeConsent(record) {
    var serialized = JSON.stringify(record);
    var secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = STORAGE_KEY + '=' + encodeURIComponent(serialized)
      + '; Path=/; Max-Age=' + COOKIE_MAX_AGE_SECONDS + '; SameSite=Lax' + secure;
    storageSet(serialized);
  }

  function eraseCookie(name) {
    var secure = window.location.protocol === 'https:' ? '; Secure' : '';
    var host = window.location.hostname;
    var domains = ['', host, host.indexOf('.') !== -1 ? '.' + host.replace(/^www\./, '') : ''];
    domains.forEach(function (domain) {
      var domainPart = domain ? '; Domain=' + domain : '';
      document.cookie = name + '=; Path=/; Max-Age=0; SameSite=Lax' + secure + domainPart;
    });
  }

  function clearCategoryStorage(category) {
    var cookiePatterns = {
      analytics: [/^_ga/i, /^_gid$/i, /^_gat/i, /^_pk_/i, /^amp_/i],
      marketing: [/^_fbp$/i, /^_fbc$/i, /^_gcl_/i, /^IDE$/i, /^NID$/i, /^ANONCHK$/i, /^MUID$/i],
      external_media: [/^YSC$/i, /^VISITOR_INFO1_LIVE$/i, /^vuid$/i]
    };
    var storagePatterns = {
      analytics: [/^_ga/i, /^_pk_/i, /analytics/i],
      marketing: [/^_fb/i, /^_gcl/i, /marketing/i, /retarget/i],
      external_media: [/youtube/i, /vimeo/i, /external[_-]?media/i]
    };

    (cookiePatterns[category] || []).forEach(function (pattern) {
      (document.cookie ? document.cookie.split(';') : []).forEach(function (part) {
        var name = part.split('=')[0].trim();
        if (pattern.test(name)) eraseCookie(name);
      });
    });

    try {
      Object.keys(window.localStorage).forEach(function (key) {
        if ((storagePatterns[category] || []).some(function (pattern) { return pattern.test(key); })) {
          window.localStorage.removeItem(key);
        }
      });
    } catch (error) {
      // Ignore storage that cannot be enumerated.
    }
  }

  function hasConsent(category) {
    if (category === 'necessary') return true;
    return Boolean(consent && consent.categories && consent.categories[category] === true);
  }

  function showStatus(message) {
    if (!statusNode) return;
    window.clearTimeout(statusTimer);
    statusNode.textContent = message;
    statusNode.classList.add('is-visible');
    statusTimer = window.setTimeout(function () {
      statusNode.classList.remove('is-visible');
    }, 3200);
  }

  function dispatch(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { detail: detail }));
  }

  function copyScriptAttributes(source, target) {
    Array.prototype.forEach.call(source.attributes, function (attribute) {
      if (['type', 'src', 'data-src', 'data-type', 'data-mg-consent', 'data-mg-consent-activated'].indexOf(attribute.name) !== -1) return;
      target.setAttribute(attribute.name, attribute.value);
    });
  }

  function activateNode(node) {
    if (!node || node.nodeType !== 1 || node.dataset.mgConsentActivated === 'true') return;
    var category = node.getAttribute('data-mg-consent') || '';
    if (CATEGORIES.indexOf(category) === -1 || !hasConsent(category)) {
      if (node.tagName !== 'SCRIPT') node.hidden = true;
      return;
    }

    var tag = node.tagName.toLowerCase();
    if (tag === 'script') {
      var script = document.createElement('script');
      copyScriptAttributes(node, script);
      script.type = node.getAttribute('data-type') || 'text/javascript';
      var source = node.getAttribute('data-src');
      if (source) script.src = source;
      else script.text = node.textContent || '';
      script.dataset.mgConsentActivated = 'true';
      node.parentNode.replaceChild(script, node);
      return;
    }

    var deferredSource = node.getAttribute('data-src');
    var deferredHref = node.getAttribute('data-href');
    if (deferredSource) {
      node.setAttribute('src', deferredSource);
      node.removeAttribute('data-src');
    }
    if (deferredHref) {
      node.setAttribute('href', deferredHref);
      node.removeAttribute('data-href');
    }
    node.hidden = false;
    node.dataset.mgConsentActivated = 'true';
  }

  function refreshDeferredContent(scope) {
    var host = scope && scope.querySelectorAll ? scope : document;
    Array.prototype.forEach.call(host.querySelectorAll('[data-mg-consent]'), activateNode);
    Array.prototype.forEach.call(document.querySelectorAll('[data-mg-consent-placeholder]'), function (placeholder) {
      var category = placeholder.getAttribute('data-mg-consent-placeholder') || '';
      placeholder.hidden = hasConsent(category);
    });
  }

  function fillPreferenceControls() {
    var categories = consent ? consent.categories : defaultCategories();
    Array.prototype.forEach.call(root.querySelectorAll('[data-mg-consent-category]'), function (input) {
      var category = input.getAttribute('data-mg-consent-category');
      input.checked = category === 'necessary' ? true : categories[category] === true;
    });
  }

  function setBannerVisibility() {
    if (!banner) return;
    banner.hidden = Boolean(consent);
  }

  function getFocusable(container) {
    if (!container) return [];
    return Array.prototype.filter.call(
      container.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'),
      function (node) { return !node.hidden && node.offsetParent !== null; }
    );
  }

  function openSettings(trigger) {
    if (!overlay || !dialog) return;
    lastFocused = trigger || document.activeElement;
    fillPreferenceControls();
    overlay.hidden = false;
    document.body.classList.add('mg-cookie-preferences-open');
    window.requestAnimationFrame(function () {
      dialog.focus({ preventScroll: true });
    });
  }

  function closeSettings() {
    if (!overlay) return;
    overlay.hidden = true;
    document.body.classList.remove('mg-cookie-preferences-open');
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus({ preventScroll: true });
  }

  function preferenceSelection() {
    var categories = defaultCategories();
    Array.prototype.forEach.call(root.querySelectorAll('[data-mg-consent-category]'), function (input) {
      var category = input.getAttribute('data-mg-consent-category');
      if (category !== 'necessary') categories[category] = input.checked === true;
    });
    return categories;
  }

  function save(categories, source) {
    var previous = consent;
    var now = new Date().toISOString();
    var normalized = normalizeCategories(categories);
    consent = {
      id: previous && previous.id ? previous.id : createConsentId(),
      version: policyVersion,
      decided_at: previous && previous.decided_at ? previous.decided_at : now,
      updated_at: now,
      source: source || 'preferences',
      categories: normalized
    };
    writeConsent(consent);
    setBannerVisibility();
    closeSettings();

    var revoked = previous && OPTIONAL_CATEGORIES.some(function (category) {
      return previous.categories[category] === true && normalized[category] !== true;
    });
    if (revoked) {
      OPTIONAL_CATEGORIES.forEach(function (category) {
        if (normalized[category] !== true) clearCategoryStorage(category);
      });
    }

    refreshDeferredContent(document);
    dispatch('mg:consent-changed', consent);
    showStatus('Your cookie preferences have been saved.');

    if (revoked) {
      window.setTimeout(function () { window.location.reload(); }, 220);
    }
  }

  function acceptAll() {
    save({ necessary: true, functional: true, analytics: true, marketing: true, external_media: true }, 'accept_all');
  }

  function rejectAll() {
    save(defaultCategories(), 'reject_non_essential');
  }

  function bindActions() {
    document.addEventListener('click', function (event) {
      var settingsTrigger = event.target.closest('[data-mg-cookie-settings]');
      if (settingsTrigger) {
        event.preventDefault();
        openSettings(settingsTrigger);
        return;
      }

      var actionNode = event.target.closest('[data-mg-consent-action]');
      if (!actionNode || !root.contains(actionNode)) return;
      event.preventDefault();
      var action = actionNode.getAttribute('data-mg-consent-action');
      if (action === 'accept') acceptAll();
      else if (action === 'reject') rejectAll();
      else if (action === 'settings') openSettings(actionNode);
      else if (action === 'close') closeSettings();
      else if (action === 'save') save(preferenceSelection(), 'custom_preferences');
    });

    document.addEventListener('keydown', function (event) {
      if (!overlay || overlay.hidden) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        closeSettings();
        return;
      }
      if (event.key !== 'Tab') return;
      var focusable = getFocusable(dialog);
      if (!focusable.length) return;
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) closeSettings();
    });
  }

  function observeDeferredContent() {
    if (!window.MutationObserver || observer) return;
    observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.prototype.forEach.call(mutation.addedNodes, function (node) {
          if (node.nodeType !== 1) return;
          if (node.matches && node.matches('[data-mg-consent]')) activateNode(node);
          refreshDeferredContent(node);
        });
      });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  function init() {
    root = document.querySelector('[data-mg-cookie-consent-root]');
    if (!root) return;
    banner = root.querySelector('[data-mg-cookie-banner]');
    overlay = root.querySelector('[data-mg-cookie-overlay]');
    dialog = root.querySelector('[data-mg-cookie-dialog]');
    statusNode = root.querySelector('[data-mg-cookie-status]');
    policyVersion = root.getAttribute('data-policy-version') || policyVersion;
    consent = readConsent();

    bindActions();
    setBannerVisibility();
    refreshDeferredContent(document);
    observeDeferredContent();
    dispatch('mg:consent-ready', consent || { version: policyVersion, categories: defaultCategories() });
  }

  window.MicrogifterConsent = {
    get: function () { return consent ? JSON.parse(JSON.stringify(consent)) : null; },
    has: hasConsent,
    open: function () { openSettings(document.activeElement); },
    acceptAll: acceptAll,
    rejectNonEssential: rejectAll,
    save: function (categories) { save(categories, 'api'); },
    policyVersion: function () { return policyVersion; },
    storageKey: STORAGE_KEY
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})(window, document);
