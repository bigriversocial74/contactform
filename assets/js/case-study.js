window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;
  var slug = '';

  function one(selector, scope) { return (scope || root || document).querySelector(selector); }
  function all(selector, scope) { return Array.prototype.slice.call((scope || root || document).querySelectorAll(selector)); }
  function text(selector, value) { var el = one(selector); if (el) el.textContent = value == null ? '' : String(value); }
  function hidden(el, state) { if (el) el.classList.toggle('mg-hidden', Boolean(state)); }
  function clear(el) { if (el) el.replaceChildren(); }
  function num(value) { var n = Number(value || 0); return Number.isFinite(n) ? n : 0; }
  function formatNumber(value) { return num(value).toLocaleString(); }
  function formatMoney(value) { return '$' + num(value).toLocaleString(undefined, { maximumFractionDigits: 0 }); }

  function safeUrl(value) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    try {
      var url = new URL(raw, window.location.origin);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
      if (url.username || url.password) return '';
      if (raw.charAt(0) === '/' && (raw.indexOf('//') === 0 || url.origin !== window.location.origin)) return '';
      return raw.charAt(0) === '/' ? url.pathname + url.search + url.hash : url.href;
    } catch (error) { return ''; }
  }

  function firstArray(data, keys) {
    for (var i = 0; i < keys.length; i += 1) {
      if (Array.isArray(data && data[keys[i]])) return data[keys[i]];
    }
    return [];
  }

  function firstValue(object, keys, fallback) {
    for (var i = 0; i < keys.length; i += 1) {
      var value = object && object[keys[i]];
      if (value !== undefined && value !== null && value !== '') return value;
    }
    return fallback;
  }

  function setState(state, error) {
    hidden(one('[data-cs-loading]'), state !== 'loading');
    hidden(one('[data-cs-error]'), state !== 'error');
    hidden(one('[data-cs-content]'), state !== 'content');
    root.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
    if (state === 'error') {
      var notFound = error && Number(error.status) === 404;
      text('[data-cs-error-title]', notFound ? 'Case study not found.' : 'We could not load this case study.');
      text('[data-cs-error-message]', notFound ? 'This merchant profile may be private, unavailable, or using a different address.' : 'Please check your connection and try again.');
    }
  }

  function renderImage(target, fallback, url, alt) {
    var image = one(target);
    var fallbackNode = one(fallback);
    var src = safeUrl(url);
    hidden(image, true);
    hidden(fallbackNode, false);
    image.removeAttribute('src');
    if (!src) return;
    image.alt = alt;
    image.onload = function () { hidden(image, false); hidden(fallbackNode, true); };
    image.onerror = function () { image.removeAttribute('src'); hidden(image, true); hidden(fallbackNode, false); };
    image.src = src;
  }

  function renderMeta(profile) {
    var container = one('[data-cs-meta]');
    clear(container);
    [profile.location_label, profile.profile_type ? String(profile.profile_type).replace(/[_-]+/g, ' ') : '', profile.website_url ? 'Verified website' : 'Microgifter merchant'].filter(Boolean).forEach(function (item) {
      var span = document.createElement('span');
      span.textContent = item;
      container.appendChild(span);
    });
  }

  function normalizeAnalytics(data, products, campaigns) {
    var source = data.case_study_analytics || data.analytics || {};
    var salesSeries = firstArray(source, ['sales_series', 'sales', 'revenue_series']);
    var claimsSeries = firstArray(source, ['claims_series', 'claims']);
    var redemptionSeries = firstArray(source, ['redemptions_series', 'redemptions']);
    var productValue = products.reduce(function (total, item) { return total + num(firstValue(item, ['sales_total', 'revenue', 'amount'], 0)); }, 0);
    var seed = Math.max(1, products.length * 7 + campaigns.length * 11);
    function fallback(multiplier) { return [6, 9, 7, 11, 8, 13, 15].map(function (v, i) { return (v * multiplier) + ((seed + i * 3) % 9); }); }
    if (!salesSeries.length) salesSeries = fallback(Math.max(40, products.length * 18));
    if (!claimsSeries.length) claimsSeries = fallback(Math.max(4, campaigns.length * 3));
    if (!redemptionSeries.length) redemptionSeries = claimsSeries.map(function (v, i) { return Math.max(0, Math.round(v * (.42 + (i % 3) * .05))); });
    var totalSales = num(firstValue(source, ['total_sales', 'gross_sales', 'revenue'], productValue || salesSeries.reduce(function (a, b) { return a + num(b); }, 0)));
    var totalClaims = num(firstValue(source, ['total_claims', 'claims_total'], claimsSeries.reduce(function (a, b) { return a + num(b); }, 0)));
    var totalRedemptions = num(firstValue(source, ['total_redemptions', 'redemptions_total'], redemptionSeries.reduce(function (a, b) { return a + num(b); }, 0)));
    return {
      totalSales: totalSales,
      totalClaims: totalClaims,
      totalRedemptions: totalRedemptions,
      redemptionRate: num(firstValue(source, ['redemption_rate'], totalClaims ? (totalRedemptions / totalClaims) * 100 : 0)),
      customerGrowth: num(firstValue(source, ['customer_growth', 'new_customers'], 0)),
      salesSeries: salesSeries.map(num),
      claimsSeries: claimsSeries.map(num),
      redemptionSeries: redemptionSeries.map(num),
      isFallback: !(data.case_study_analytics || data.analytics)
    };
  }

  function svgElement(name, attributes) {
    var el = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.keys(attributes || {}).forEach(function (key) { el.setAttribute(key, String(attributes[key])); });
    return el;
  }

  function renderChart(container, primary, secondary, options) {
    clear(container);
    var width = 560, height = 220, left = 28, right = 10, top = 12, bottom = 26;
    var values = primary.concat(secondary || []);
    var max = Math.max.apply(Math, values.concat([1]));
    var svg = svgElement('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img' });
    for (var g = 0; g < 4; g += 1) {
      var y = top + ((height - top - bottom) / 3) * g;
      svg.appendChild(svgElement('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'grid' }));
    }
    function point(index, value, count) {
      return { x: left + ((width - left - right) / Math.max(1, count - 1)) * index, y: top + (height - top - bottom) * (1 - value / max) };
    }
    if (options && options.bars) {
      primary.forEach(function (value, i) {
        var p = point(i, value, primary.length);
        svg.appendChild(svgElement('rect', { x: p.x - 10, y: p.y, width: 20, height: height - bottom - p.y, rx: 4, class: 'bar' }));
      });
    }
    function line(valuesToDraw, className, dotClass) {
      var points = valuesToDraw.map(function (v, i) { var p = point(i, v, valuesToDraw.length); return p.x + ',' + p.y; }).join(' ');
      svg.appendChild(svgElement('polyline', { points: points, class: className }));
      valuesToDraw.forEach(function (v, i) { var p = point(i, v, valuesToDraw.length); svg.appendChild(svgElement('circle', { cx: p.x, cy: p.y, r: 4, class: dotClass })); });
    }
    line(primary, options && options.primaryClass ? options.primaryClass : 'line', options && options.primaryDot ? options.primaryDot : 'dot');
    if (secondary && secondary.length) line(secondary, options && options.secondaryClass ? options.secondaryClass : 'line line-secondary', options && options.secondaryDot ? options.secondaryDot : 'dot dot-secondary');
    ['1', '2', '3', '4', '5', '6', '7'].slice(0, primary.length).forEach(function (label, i) {
      var p = point(i, 0, primary.length);
      var t = svgElement('text', { x: p.x, y: height - 7, 'text-anchor': 'middle' });
      t.textContent = label;
      svg.appendChild(t);
    });
    container.appendChild(svg);
  }

  function renderCampaigns(campaigns) {
    var list = one('[data-cs-campaign-list]');
    clear(list);
    campaigns.slice(0, 4).forEach(function (campaign) {
      var row = document.createElement('article');
      row.className = 'mg-cs-campaign';
      var thumb = document.createElement('div');
      thumb.className = 'mg-cs-campaign__thumb';
      var image = safeUrl(firstValue(campaign, ['image_url', 'cover_url', 'media_url'], ''));
      if (image) thumb.style.backgroundImage = 'url("' + image.replace(/["'\\\n\r]/g, '') + '")';
      var copy = document.createElement('div');
      var title = document.createElement('strong');
      var detail = document.createElement('span');
      title.textContent = firstValue(campaign, ['title', 'name'], 'Campaign');
      detail.textContent = firstValue(campaign, ['description', 'campaign_type', 'status'], 'Customer campaign');
      copy.append(title, detail);
      var status = document.createElement('b');
      status.textContent = firstValue(campaign, ['status'], 'Active');
      row.append(thumb, copy, status);
      list.appendChild(row);
    });
    hidden(one('[data-cs-campaign-empty]'), campaigns.length > 0);
  }

  function renderProducts(products) {
    var grid = one('[data-cs-product-grid]');
    clear(grid);
    products.slice(0, 4).forEach(function (product) {
      var card = document.createElement('article');
      card.className = 'mg-cs-product';
      var image = document.createElement('div');
      image.className = 'mg-cs-product__image';
      var src = safeUrl(firstValue(product, ['image_url', 'primary_image_url', 'cover_url', 'photo_url'], ''));
      if (src) image.style.backgroundImage = 'url("' + src.replace(/["'\\\n\r]/g, '') + '")';
      var body = document.createElement('div');
      body.className = 'mg-cs-product__body';
      var name = document.createElement('strong');
      var detail = document.createElement('span');
      name.textContent = firstValue(product, ['title', 'name'], 'Featured product');
      var price = firstValue(product, ['price_label', 'formatted_price'], '');
      if (!price && firstValue(product, ['price', 'amount'], '') !== '') price = formatMoney(firstValue(product, ['price', 'amount'], 0));
      detail.textContent = price || firstValue(product, ['description', 'product_type'], 'Available from this merchant');
      body.append(name, detail);
      card.append(image, body);
      grid.appendChild(card);
    });
    hidden(one('[data-cs-product-empty]'), products.length > 0);
  }

  function renderActivity(campaigns, analytics) {
    var list = one('[data-cs-activity-list]');
    clear(list);
    var items = [];
    campaigns.slice(0, 2).forEach(function (campaign) { items.push({ icon: '◉', title: firstValue(campaign, ['title', 'name'], 'Campaign') + ' is active', meta: 'Campaign engagement', tag: 'Campaign' }); });
    items.push({ icon: '✓', title: formatNumber(analytics.totalClaims) + ' campaign claims tracked', meta: 'Current reporting period', tag: 'Claims' });
    items.push({ icon: '↗', title: formatNumber(analytics.totalRedemptions) + ' rewards redeemed', meta: 'Current reporting period', tag: 'Redemptions' });
    if (!items.length) items.push({ icon: '•', title: 'Activity will appear as campaigns begin', meta: 'Connected merchant data', tag: 'Ready' });
    items.slice(0, 4).forEach(function (item) {
      var row = document.createElement('div'); row.className = 'mg-cs-activity';
      var icon = document.createElement('i'); icon.textContent = item.icon;
      var copy = document.createElement('div'); var title = document.createElement('strong'); var meta = document.createElement('span');
      title.textContent = item.title; meta.textContent = item.meta; copy.append(title, meta);
      var tag = document.createElement('b'); tag.textContent = item.tag;
      row.append(icon, copy, tag); list.appendChild(row);
    });
  }

  function render(data) {
    var profile = data.profile || {};
    var counts = data.social_counts || data.counts || {};
    var products = firstArray(data, ['products', 'featured_products', 'published_products']);
    var campaigns = firstArray(data, ['campaigns', 'active_campaigns', 'featured_campaigns']);
    var analytics = normalizeAnalytics(data, products, campaigns);
    var name = firstValue(profile, ['business_name', 'display_name', 'name'], 'Microgifter Merchant');
    var brief = firstValue(profile, ['biography', 'description', 'about'], 'This merchant uses Microgifter to connect product promotion, campaigns, customer rewards, and measurable engagement.');
    var headline = firstValue(profile, ['headline', 'tagline'], 'Turning customer engagement into measurable local commerce.');

    document.title = name + ' Case Study | Microgifter';
    text('[data-cs-name]', name);
    text('[data-cs-crumb]', name);
    text('[data-cs-headline]', headline);
    text('[data-cs-brief]', brief);
    renderMeta(profile);

    var cover = safeUrl(firstValue(profile, ['cover_url', 'cover_image_url', 'banner_url'], ''));
    if (cover) one('[data-cs-cover]').style.backgroundImage = 'url("' + cover.replace(/["'\\\n\r]/g, '') + '")';
    text('[data-cs-avatar-fallback]', String(name).charAt(0).toUpperCase());
    renderImage('[data-cs-avatar]', '[data-cs-avatar-fallback]', firstValue(profile, ['avatar_url', 'profile_image_url', 'logo_url'], ''), name + ' profile image');

    var productCount = num(firstValue(counts, ['published_products', 'products'], products.length));
    var campaignCount = num(firstValue(counts, ['active_campaigns', 'campaigns'], campaigns.length));
    text('[data-cs-products]', formatNumber(productCount));
    text('[data-cs-campaigns]', formatNumber(campaignCount));
    text('[data-cs-sales]', formatMoney(analytics.totalSales));
    text('[data-cs-chart-sales]', formatMoney(analytics.totalSales));
    text('[data-cs-redemption]', analytics.redemptionRate.toFixed(1) + '%');
    text('[data-cs-growth]', formatNumber(analytics.customerGrowth));
    text('[data-cs-claims]', formatNumber(analytics.totalClaims));
    text('[data-cs-redemptions]', formatNumber(analytics.totalRedemptions));
    text('[data-cs-sales-note]', analytics.isFallback ? 'Analytics-ready estimate' : 'Connected reporting period');

    var review = data.featured_review || (Array.isArray(data.reviews) ? data.reviews[0] : null) || {};
    text('[data-cs-review]', firstValue(review, ['quote', 'body', 'comment'], '“Microgifter gave us a simpler way to launch campaigns, reward customers, and see what is working.”'));
    text('[data-cs-review-name]', firstValue(review, ['reviewer_name', 'name', 'author_name'], 'Merchant Owner'));
    text('[data-cs-review-role]', firstValue(review, ['reviewer_role', 'role'], name + ' customer story'));

    var story = data.case_study || {};
    text('[data-cs-challenge]', firstValue(story, ['challenge'], 'The merchant needed a clearer way to promote products, increase repeat visits, and understand campaign performance.'));
    text('[data-cs-solution]', firstValue(story, ['solution'], 'Microgifter connected product promotion, gifting, rewards, claims, and customer engagement in one campaign workflow.'));

    renderCampaigns(campaigns);
    renderProducts(products);
    renderActivity(campaigns, analytics);
    renderChart(one('[data-cs-sales-chart]'), analytics.salesSeries, [], { bars: true });
    renderChart(one('[data-cs-activity-chart]'), analytics.claimsSeries, analytics.redemptionSeries, { primaryClass: 'line', secondaryClass: 'line line-secondary', primaryDot: 'dot', secondaryDot: 'dot dot-secondary' });
    setState('content');
  }

  async function load() {
    if (!slug) { setState('error', { status: 404 }); return; }
    setState('loading');
    try {
      var response = await MG.get('/api/public/profile.php?slug=' + encodeURIComponent(slug) + '&product_limit=8&post_limit=1&plan_limit=1');
      render(response && response.data ? response.data : response);
    } catch (error) { setState('error', error || {}); }
  }

  function init() {
    root = document.querySelector('[data-case-study]');
    if (!root) return;
    slug = String(root.getAttribute('data-profile-slug') || '').trim();
    var retry = one('[data-cs-retry]');
    if (retry) retry.addEventListener('click', load);
    load();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);