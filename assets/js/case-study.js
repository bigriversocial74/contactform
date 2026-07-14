window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = null;
  var slug = '';

  function one(selector, scope) {
    return (scope || root || document).querySelector(selector);
  }

  function text(selector, value) {
    var element = one(selector);
    if (element) element.textContent = value == null ? '' : String(value);
  }

  function hidden(element, state) {
    if (element) element.classList.toggle('mg-hidden', Boolean(state));
  }

  function clear(element) {
    if (element) element.replaceChildren();
  }

  function num(value) {
    var parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function formatNumber(value) {
    return num(value).toLocaleString();
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      maximumFractionDigits: 0
    }).format(num(value));
  }

  function firstValue(object, keys, fallback) {
    for (var index = 0; index < keys.length; index += 1) {
      var value = object && object[keys[index]];
      if (value !== undefined && value !== null && value !== '') return value;
    }
    return fallback;
  }

  function safeUrl(value) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    try {
      var url = new URL(raw, window.location.origin);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
      if (url.username || url.password) return '';
      if (raw.charAt(0) === '/' && (raw.indexOf('//') === 0 || url.origin !== window.location.origin)) return '';
      return raw.charAt(0) === '/' ? url.pathname + url.search + url.hash : url.href;
    } catch (error) {
      return '';
    }
  }

  function normalizeSeries(value) {
    var series = Array.isArray(value) ? value.slice(0, 7).map(num) : [];
    while (series.length < 7) series.unshift(0);
    return series;
  }

  function setState(state, error) {
    hidden(one('[data-cs-loading]'), state !== 'loading');
    hidden(one('[data-cs-error]'), state !== 'error');
    hidden(one('[data-cs-content]'), state !== 'content');
    root.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');

    if (state === 'error') {
      var notFound = error && Number(error.status) === 404;
      text('[data-cs-error-title]', notFound ? 'Case study not found.' : 'We could not load this case study.');
      text(
        '[data-cs-error-message]',
        notFound
          ? 'This merchant profile may be private, unavailable, or using a different address.'
          : 'The connected database report could not be loaded. Please try again.'
      );
    }
  }

  function renderImage(target, fallback, url, alt) {
    var image = one(target);
    var fallbackNode = one(fallback);
    var source = safeUrl(url);
    if (!image || !fallbackNode) return;

    hidden(image, true);
    hidden(fallbackNode, false);
    image.removeAttribute('src');
    if (!source) return;

    image.alt = alt;
    image.onload = function () {
      hidden(image, false);
      hidden(fallbackNode, true);
    };
    image.onerror = function () {
      image.removeAttribute('src');
      hidden(image, true);
      hidden(fallbackNode, false);
    };
    image.src = source;
  }

  function renderMeta(profile) {
    var container = one('[data-cs-meta]');
    clear(container);
    if (!container) return;

    [
      profile.location_label,
      profile.profile_type ? String(profile.profile_type).replace(/[_-]+/g, ' ') : '',
      profile.website_url ? 'Verified website' : 'Microgifter merchant'
    ].filter(Boolean).forEach(function (item) {
      var span = document.createElement('span');
      span.textContent = item;
      container.appendChild(span);
    });
  }

  function svgElement(name, attributes) {
    var element = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.keys(attributes || {}).forEach(function (key) {
      element.setAttribute(key, String(attributes[key]));
    });
    return element;
  }

  function renderChart(container, primary, secondary, options) {
    if (!container) return;
    clear(container);

    var width = 560;
    var height = 220;
    var left = 28;
    var right = 10;
    var top = 12;
    var bottom = 26;
    var primarySeries = normalizeSeries(primary);
    var secondarySeries = secondary ? normalizeSeries(secondary) : [];
    var barSeries = options && options.bars ? normalizeSeries(options.bars) : [];
    var lineValues = primarySeries.concat(secondarySeries);
    var lineMax = Math.max.apply(Math, lineValues.concat([1]));
    var barMax = Math.max.apply(Math, barSeries.concat([1]));
    var svg = svgElement('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img' });

    for (var gridIndex = 0; gridIndex < 4; gridIndex += 1) {
      var gridY = top + ((height - top - bottom) / 3) * gridIndex;
      svg.appendChild(svgElement('line', {
        x1: left,
        y1: gridY,
        x2: width - right,
        y2: gridY,
        class: 'grid'
      }));
    }

    function point(index, value, count, maximum) {
      return {
        x: left + ((width - left - right) / Math.max(1, count - 1)) * index,
        y: top + (height - top - bottom) * (1 - num(value) / Math.max(1, maximum))
      };
    }

    if (barSeries.length) {
      barSeries.forEach(function (value, index) {
        var barPoint = point(index, value, barSeries.length, barMax);
        svg.appendChild(svgElement('rect', {
          x: barPoint.x - 10,
          y: barPoint.y,
          width: 20,
          height: Math.max(0, height - bottom - barPoint.y),
          rx: 4,
          class: 'bar'
        }));
      });
    }

    function drawLine(values, className, dotClass) {
      if (!values.length) return;
      var points = values.map(function (value, index) {
        var linePoint = point(index, value, values.length, lineMax);
        return linePoint.x + ',' + linePoint.y;
      }).join(' ');
      svg.appendChild(svgElement('polyline', { points: points, class: className }));
      values.forEach(function (value, index) {
        var dotPoint = point(index, value, values.length, lineMax);
        svg.appendChild(svgElement('circle', {
          cx: dotPoint.x,
          cy: dotPoint.y,
          r: 4,
          class: dotClass
        }));
      });
    }

    drawLine(primarySeries, options && options.primaryClass ? options.primaryClass : 'line', options && options.primaryDot ? options.primaryDot : 'dot');
    if (secondarySeries.length) {
      drawLine(
        secondarySeries,
        options && options.secondaryClass ? options.secondaryClass : 'line line-secondary',
        options && options.secondaryDot ? options.secondaryDot : 'dot dot-secondary'
      );
    }

    ['1', '2', '3', '4', '5', '6', '7'].forEach(function (label, index) {
      var labelPoint = point(index, 0, 7, 1);
      var labelNode = svgElement('text', {
        x: labelPoint.x,
        y: height - 7,
        'text-anchor': 'middle'
      });
      labelNode.textContent = label;
      svg.appendChild(labelNode);
    });

    container.appendChild(svg);
  }

  function renderCampaigns(campaigns) {
    var list = one('[data-cs-campaign-list]');
    clear(list);
    if (!list) return;

    campaigns.slice(0, 4).forEach(function (campaign) {
      var row = document.createElement('article');
      row.className = 'mg-cs-campaign';

      var thumb = document.createElement('div');
      thumb.className = 'mg-cs-campaign__thumb';

      var copy = document.createElement('div');
      var title = document.createElement('strong');
      var detail = document.createElement('span');
      title.textContent = firstValue(campaign, ['title', 'name'], 'Campaign');

      var issued = num(campaign.issued_count);
      var campaignType = String(firstValue(campaign, ['campaign_type'], 'Customer campaign')).replace(/[_-]+/g, ' ');
      detail.textContent = campaignType + (issued > 0 ? ' · ' + formatNumber(issued) + ' issued' : '');
      copy.append(title, detail);

      var status = document.createElement('b');
      status.textContent = String(firstValue(campaign, ['status'], 'active')).replace(/[_-]+/g, ' ');
      row.append(thumb, copy, status);
      list.appendChild(row);
    });

    hidden(one('[data-cs-campaign-empty]'), campaigns.length > 0);
  }

  function renderProducts(products) {
    var grid = one('[data-cs-product-grid]');
    clear(grid);
    if (!grid) return;

    products.slice(0, 4).forEach(function (product) {
      var card = document.createElement('article');
      card.className = 'mg-cs-product';

      var image = document.createElement('div');
      image.className = 'mg-cs-product__image';
      var source = safeUrl(firstValue(product, ['cover_url', 'image_url', 'primary_image_url'], ''));
      if (source) image.style.backgroundImage = 'url("' + source.replace(/["'\\\n\r]/g, '') + '")';

      var body = document.createElement('div');
      body.className = 'mg-cs-product__body';
      var name = document.createElement('strong');
      var detail = document.createElement('span');
      name.textContent = firstValue(product, ['title', 'name'], 'Featured product');
      detail.textContent = firstValue(product, ['price_label'], '') || formatMoney(firstValue(product, ['amount'], 0));
      body.append(name, detail);
      card.append(image, body);
      grid.appendChild(card);
    });

    hidden(one('[data-cs-product-empty]'), products.length > 0);
  }

  function renderReview(review, merchantName) {
    var card = one('.mg-cs-review');
    if (!review || !String(review.body || '').trim()) {
      hidden(card, true);
      return;
    }

    hidden(card, false);
    text('[data-cs-review]', '“' + String(review.body).replace(/^[“"]|[”"]$/g, '') + '”');
    text('[data-cs-review-name]', firstValue(review, ['reviewer_name', 'name'], 'Merchant customer'));
    text('[data-cs-review-role]', firstValue(review, ['reviewer_role', 'role'], merchantName + ' customer'));

    var rating = Math.max(1, Math.min(5, Math.round(num(review.rating) || 5)));
    text('[data-cs-review-stars]', '★★★★★'.slice(0, rating) + '☆☆☆☆☆'.slice(0, 5 - rating));
    var stars = one('[data-cs-review-stars]');
    if (stars) stars.setAttribute('aria-label', rating + ' out of 5 stars');
  }

  function renderOutcomes(story, analytics, campaignCount) {
    var list = one('[data-cs-outcomes]');
    clear(list);
    if (!list) return;

    var outcomes = Array.isArray(story.outcomes) ? story.outcomes.filter(Boolean).slice(0, 5) : [];
    if (!outcomes.length) {
      outcomes = [
        formatNumber(campaignCount) + ' active campaigns',
        formatNumber(analytics.total_claims) + ' claims tracked',
        formatNumber(analytics.total_redemptions) + ' rewards redeemed'
      ];
    }

    outcomes.forEach(function (outcome) {
      var item = document.createElement('li');
      item.textContent = String(outcome);
      list.appendChild(item);
    });
  }

  function renderActivity(campaigns, analytics) {
    var list = one('[data-cs-activity-list]');
    clear(list);
    if (!list) return;

    var items = [];
    campaigns.filter(function (campaign) {
      return String(campaign.status || '').toLowerCase() === 'active';
    }).slice(0, 2).forEach(function (campaign) {
      items.push({
        icon: '◉',
        title: firstValue(campaign, ['title', 'name'], 'Campaign') + ' is active',
        meta: formatNumber(campaign.issued_count) + ' issued',
        tag: 'Campaign'
      });
    });

    items.push({
      icon: '✓',
      title: formatNumber(analytics.total_claims) + ' campaign claims tracked',
      meta: 'Database reporting period',
      tag: 'Claims'
    });
    items.push({
      icon: '↗',
      title: formatNumber(analytics.total_redemptions) + ' rewards redeemed',
      meta: 'Database reporting period',
      tag: 'Redemptions'
    });

    items.slice(0, 4).forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'mg-cs-activity';
      var icon = document.createElement('i');
      icon.textContent = item.icon;
      var copy = document.createElement('div');
      var title = document.createElement('strong');
      var meta = document.createElement('span');
      title.textContent = item.title;
      meta.textContent = item.meta;
      copy.append(title, meta);
      var tag = document.createElement('b');
      tag.textContent = item.tag;
      row.append(icon, copy, tag);
      list.appendChild(row);
    });
  }

  function render(data) {
    var profile = data.profile || {};
    var counts = data.counts || {};
    var products = Array.isArray(data.products) ? data.products : [];
    var campaigns = Array.isArray(data.campaigns) ? data.campaigns : [];
    var analytics = data.case_study_analytics || {};
    var story = data.case_study || {};
    var merchantName = firstValue(profile, ['display_name', 'business_name', 'name'], 'Microgifter Merchant');
    var biography = firstValue(profile, ['biography', 'description', 'about'], '');
    var headline = firstValue(profile, ['headline', 'tagline'], '');
    var activeCampaignCount = num(counts.active_campaigns);

    document.title = merchantName + ' Case Study | Microgifter';
    text('[data-cs-name]', merchantName);
    text('[data-cs-crumb]', merchantName);
    text('[data-cs-headline]', headline);
    text('[data-cs-brief]', biography);
    renderMeta(profile);

    var cover = safeUrl(firstValue(profile, ['cover_url', 'cover_image_url', 'banner_url'], ''));
    var coverNode = one('[data-cs-cover]');
    if (coverNode) coverNode.style.backgroundImage = cover ? 'url("' + cover.replace(/["'\\\n\r]/g, '') + '")' : '';

    text('[data-cs-avatar-fallback]', String(merchantName).charAt(0).toUpperCase());
    renderImage(
      '[data-cs-avatar]',
      '[data-cs-avatar-fallback]',
      firstValue(profile, ['avatar_url', 'profile_image_url', 'logo_url'], ''),
      merchantName + ' profile image'
    );

    text('[data-cs-products]', formatNumber(counts.published_products));
    text('[data-cs-campaigns]', formatNumber(activeCampaignCount));
    text('[data-cs-sales]', formatMoney(analytics.total_sales));
    text('[data-cs-chart-sales]', formatMoney(analytics.total_sales));
    text('[data-cs-redemption]', num(analytics.redemption_rate).toFixed(1) + '%');
    text('[data-cs-growth]', formatNumber(analytics.customer_growth));
    text('[data-cs-claims]', formatNumber(analytics.total_claims));
    text('[data-cs-redemptions]', formatNumber(analytics.total_redemptions));
    text('[data-cs-sales-note]', 'Captured commerce orders · cash and online');

    renderReview(data.featured_review, merchantName);
    text('[data-cs-challenge]', firstValue(story, ['challenge'], biography || 'No curated challenge has been published yet.'));
    text('[data-cs-solution]', firstValue(story, ['solution'], headline || 'No curated solution has been published yet.'));
    renderOutcomes(story, analytics, activeCampaignCount);
    renderCampaigns(campaigns);
    renderProducts(products);
    renderActivity(campaigns, analytics);
    renderChart(one('[data-cs-sales-chart]'), analytics.sales_series, null, { bars: analytics.orders_series });
    renderChart(one('[data-cs-activity-chart]'), analytics.claims_series, analytics.redemptions_series, {
      primaryClass: 'line',
      secondaryClass: 'line line-secondary',
      primaryDot: 'dot',
      secondaryDot: 'dot dot-secondary'
    });

    root.dataset.statsSource = 'database';
    root.dataset.salesSource = String(analytics.sales_source || 'commerce_orders');
    setState('content');
  }

  async function load() {
    if (!slug) {
      setState('error', { status: 404 });
      return;
    }

    setState('loading');
    try {
      var response = await fetch('/api/public/case-studies/stats.php?slug=' + encodeURIComponent(slug), {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      });
      var payload = await response.json();
      if (!response.ok || !payload || payload.ok !== true) {
        var requestError = new Error(payload && payload.message ? payload.message : 'Unable to load case study.');
        requestError.status = response.status;
        throw requestError;
      }
      render(payload.data || {});
    } catch (error) {
      root.dataset.statsSource = 'unavailable';
      setState('error', error || {});
    }
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
