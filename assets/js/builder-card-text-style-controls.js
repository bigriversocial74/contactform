document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-builder-app]');
  if (!root) return;

  var defaults = {
    bg: '#ffffff',
    color: '#071225',
    font: 'system',
    headlineSize: '52',
    messageSize: '24',
    opacity: '100',
    align: 'center',
    vertical: 'center'
  };
  var fontStacks = {
    system: "Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    serif: "Georgia, 'Times New Roman', serif",
    script: "'Brush Script MT', 'Segoe Script', cursive",
    handwritten: "'Comic Sans MS', 'Bradley Hand', cursive",
    display: "Impact, 'Arial Black', sans-serif"
  };

  function field(id) { return root.querySelector('#' + id); }
  function value(id) { var node = field(id); return node ? String(node.value || '').trim() : ''; }
  function setValue(id, next) { var node = field(id); if (node) node.value = next; }
  function clampNumber(raw, fallback, min, max) {
    var parsed = Number(raw);
    if (!Number.isFinite(parsed)) parsed = fallback;
    return Math.max(min, Math.min(max, parsed));
  }
  function setText(node, next) {
    if (node && node.textContent !== next) node.textContent = next;
  }
  function cardStyle() {
    var align = value('cardTextAlign') || defaults.align;
    var vertical = value('cardTextVertical') || defaults.vertical;
    return {
      background_color: value('cardBgColor') || defaults.bg,
      text_color: value('cardTextColor') || defaults.color,
      font_family: value('cardFontFamily') || defaults.font,
      headline_size: clampNumber(value('headlineFontSize'), 52, 28, 84),
      message_size: clampNumber(value('messageFontSize'), 24, 14, 42),
      opacity: clampNumber(value('fontOpacity'), 100, 35, 100),
      align: ['left', 'center', 'right'].includes(align) ? align : defaults.align,
      vertical: ['top', 'center', 'bottom'].includes(vertical) ? vertical : defaults.vertical
    };
  }
  function applyTextContent() {
    var headline = value('headline') || 'HAPPY BIRTHDAY!';
    var message = value('message') || 'Add the message the recipient will see inside the card.';
    var signature = value('signature');
    root.querySelectorAll('[data-preview-card-headline]').forEach(function (node) { setText(node, headline); });
    root.querySelectorAll('[data-preview-message]').forEach(function (node) { setText(node, message); });
    root.querySelectorAll('[data-preview-signature]').forEach(function (node) {
      setText(node, signature);
      node.hidden = !signature;
    });
  }
  function applyStyle() {
    var style = cardStyle();
    var justify = style.vertical === 'top' ? 'flex-start' : (style.vertical === 'bottom' ? 'flex-end' : 'center');
    var family = fontStacks[style.font_family] || fontStacks.system;
    var alpha = style.opacity / 100;
    root.querySelectorAll('.mg-card-inside-right').forEach(function (page) {
      page.style.background = style.background_color;
      page.style.color = style.text_color;
      page.style.justifyContent = justify;
    });
    root.querySelectorAll('.mg-card-message-copy').forEach(function (copy) {
      copy.style.fontFamily = family;
      copy.style.textAlign = style.align;
      copy.style.opacity = String(alpha);
      copy.style.color = style.text_color;
    });
    root.querySelectorAll('[data-preview-card-headline]').forEach(function (node) {
      node.style.fontSize = style.headline_size + 'px';
      node.style.color = style.text_color;
      node.style.textAlign = style.align;
    });
    root.querySelectorAll('[data-preview-message]').forEach(function (node) {
      node.style.fontSize = style.message_size + 'px';
      node.style.color = style.text_color;
      node.style.textAlign = style.align;
    });
    root.querySelectorAll('[data-preview-signature]').forEach(function (node) {
      node.style.fontSize = Math.max(12, Math.round(style.message_size * 0.72)) + 'px';
      node.style.color = style.text_color;
      node.style.textAlign = style.align;
    });
  }
  function renderCardText() {
    applyTextContent();
    applyStyle();
  }
  function resetControls() {
    setValue('cardBgColor', defaults.bg);
    setValue('cardTextColor', defaults.color);
    setValue('cardFontFamily', defaults.font);
    setValue('headlineFontSize', defaults.headlineSize);
    setValue('messageFontSize', defaults.messageSize);
    setValue('fontOpacity', defaults.opacity);
    setValue('cardTextAlign', defaults.align);
    setValue('cardTextVertical', defaults.vertical);
    renderCardText();
    var changed = field('cardBgColor');
    if (changed) changed.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function loadSavedStyle() {
    var productId = root.dataset.productId || new URLSearchParams(window.location.search).get('id') || '';
    if (!productId || !window.fetch) return;
    window.fetch('/api/catalog/builder-draft.php?id=' + encodeURIComponent(productId), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        var data = payload && (payload.data || payload);
        var draft = data && data.draft;
        var draftPayload = draft && draft.payload;
        if (!draftPayload) return;
        if (draftPayload.signature) setValue('signature', draftPayload.signature);
        var style = draftPayload.card_style || {};
        if (style.background_color) setValue('cardBgColor', style.background_color);
        if (style.text_color) setValue('cardTextColor', style.text_color);
        if (style.font_family) setValue('cardFontFamily', style.font_family);
        if (style.headline_size) setValue('headlineFontSize', style.headline_size);
        if (style.message_size) setValue('messageFontSize', style.message_size);
        if (style.opacity) setValue('fontOpacity', style.opacity);
        if (style.align) setValue('cardTextAlign', style.align);
        if (style.vertical) setValue('cardTextVertical', style.vertical);
        renderCardText();
      })
      .catch(function () {});
  }

  var nativeFetch = window.fetch;
  if (nativeFetch && !window.__mgCardTextStyleFetchPatched) {
    window.__mgCardTextStyleFetchPatched = true;
    window.fetch = function (input, init) {
      try {
        var url = typeof input === 'string' ? input : (input && input.url) || '';
        if (url.indexOf('/api/catalog/builder-draft.php') !== -1 && init && typeof init.body === 'string') {
          var body = JSON.parse(init.body);
          if (body && body.payload && (body.action === 'save' || body.action === 'publish')) {
            body.payload.signature = value('signature');
            body.payload.card_style = cardStyle();
            init = Object.assign({}, init, { body: JSON.stringify(body) });
          }
        }
      } catch (error) {}
      return nativeFetch.call(this, input, init);
    };
  }

  ['headline', 'message', 'signature', 'cardBgColor', 'cardTextColor', 'cardFontFamily', 'headlineFontSize', 'messageFontSize', 'fontOpacity', 'cardTextAlign', 'cardTextVertical'].forEach(function (id) {
    var node = field(id);
    if (!node) return;
    node.addEventListener('input', renderCardText);
    node.addEventListener('change', renderCardText);
  });
  var reset = root.querySelector('[data-card-style-reset]');
  if (reset) reset.addEventListener('click', resetControls);

  renderCardText();
  window.setTimeout(renderCardText, 50);
  window.setTimeout(loadSavedStyle, 500);
  window.setTimeout(renderCardText, 900);
});
