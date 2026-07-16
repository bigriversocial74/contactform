(() => {
  'use strict';

  const app = document.querySelector('[data-agent-design-studio]');
  if (!app) return;

  const MG = window.Microgifter || {};
  const modeButtons = Array.from(app.querySelectorAll('[data-design-mode]'));
  const modePanels = Array.from(app.querySelectorAll('[data-design-mode-panel]'));
  const productSelect = app.querySelector('[data-social-product-select]');
  const productMeta = app.querySelector('[data-social-product-meta]');
  const refreshButton = app.querySelector('[data-social-refresh]');
  const formatButtons = Array.from(app.querySelectorAll('[data-social-format]'));
  const layoutButtons = Array.from(app.querySelectorAll('[data-social-layout]'));
  const canvas = app.querySelector('[data-social-canvas]');
  const loading = app.querySelector('[data-social-loading]');
  const formatLabel = app.querySelector('[data-social-format-label]');
  const layoutLabel = app.querySelector('[data-social-layout-label]');
  const status = app.querySelector('[data-social-status]');
  const downloadButton = app.querySelector('[data-social-download]');
  const postButton = app.querySelector('[data-social-post-feed]');
  const productImage = app.querySelector('[data-social-product-image]');
  const photoPlaceholder = app.querySelector('[data-social-photo-placeholder]');
  const profileImage = app.querySelector('[data-social-profile-image]');
  const profileFallback = app.querySelector('[data-social-profile-fallback]');
  const merchantNameNode = app.querySelector('[data-social-merchant-name]');
  const productTitleNode = app.querySelector('[data-social-product-title]');
  const productDescriptionNode = app.querySelector('[data-social-product-description]');
  const productPriceNode = app.querySelector('[data-social-product-price]');

  const formats = {
    square: { label: 'Post · 1:1', filename: 'social-post-1x1' },
    portrait: { label: 'Portrait · 4:5', filename: 'social-post-4x5' },
    story: { label: 'Reel / Story · 9:16', filename: 'social-story-9x16' },
  };
  const layouts = {
    spotlight: 'Spotlight layout',
    split: 'Split Feature layout',
    bold: 'Bold Offer layout',
  };

  let activeMode = 'print';
  let currentFormat = 'square';
  let currentLayout = 'spotlight';
  let products = [];
  let currentProduct = null;
  let currentProfile = null;
  let socialLoaded = false;

  const payload = (response) => (response && response.data ? response.data : response);

  const setStatus = (message, type = '') => {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-success', type === 'success');
    status.classList.toggle('is-error', type === 'error');
  };

  const request = async (url, options = {}) => {
    if (typeof MG.api === 'function') return payload(await MG.api(url, options));

    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options,
    });
    const json = await response.json().catch(() => ({}));
    const data = payload(json);
    if (!response.ok || json.ok === false || json.success === false) {
      throw new Error(json.message || data.message || 'Request failed.');
    }
    return data;
  };

  const postJson = async (url, body) => {
    if (typeof MG.post === 'function') return payload(await MG.post(url, body));
    throw new Error('Secure posting is unavailable on this page.');
  };

  const setBusy = (button, busy, label) => {
    if (!button) return;
    if (busy) {
      button.dataset.originalLabel = button.textContent || '';
      button.disabled = true;
      button.textContent = label;
      return;
    }
    button.textContent = button.dataset.originalLabel || button.textContent;
    button.disabled = !currentProduct;
  };

  const setMode = (mode) => {
    activeMode = mode === 'social' ? 'social' : 'print';
    modeButtons.forEach((button) => {
      const active = button.dataset.designMode === activeMode;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    modePanels.forEach((panel) => {
      panel.hidden = panel.dataset.designModePanel !== activeMode;
    });
    if (activeMode === 'social' && !socialLoaded) loadSocialStudio();
  };

  const formatCurrency = (cents, currency) => {
    const value = Number(cents || 0) / 100;
    if (!Number.isFinite(value) || value <= 0) return '';
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: String(currency || 'USD').toUpperCase(),
        maximumFractionDigits: value % 1 === 0 ? 0 : 2,
      }).format(value);
    } catch (_) {
      return `$${value.toFixed(2)}`;
    }
  };

  const cleanDescription = (value) => {
    const text = String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return text || 'Discover this local product, service, or experience on Microgifter.';
  };

  const setImage = (image, url, fallback, alt = '') => {
    if (!image) return;
    if (!url) {
      image.hidden = true;
      image.removeAttribute('src');
      if (fallback) fallback.hidden = false;
      return;
    }

    image.hidden = false;
    image.alt = alt;
    if (fallback) fallback.hidden = true;
    image.onload = () => {
      image.hidden = false;
      if (fallback) fallback.hidden = true;
    };
    image.onerror = () => {
      image.hidden = true;
      if (fallback) fallback.hidden = false;
    };
    image.src = url;
  };

  const updateProfile = (profile) => {
    currentProfile = profile || currentProfile || {};
    const merchantName = String(
      currentProfile.display_name || app.dataset.merchantName || 'Your Business'
    ).trim();

    if (merchantNameNode) merchantNameNode.textContent = merchantName;
    if (profileFallback) profileFallback.textContent = merchantName.slice(0, 1).toUpperCase() || 'M';
    setImage(profileImage, currentProfile.avatar_url || '', profileFallback, `${merchantName} profile`);
  };

  const chooseProductImage = (detail) => {
    const assets = [
      ...(Array.isArray(detail.assets) ? detail.assets : []),
      ...(Array.isArray(detail.draft_assets) ? detail.draft_assets : []),
    ].filter((asset) => String(asset.asset_type || '').toLowerCase() === 'image');

    const priority = ['primary', 'cover', 'hero', 'product', 'front'];
    assets.sort((a, b) => {
      const aIndex = priority.indexOf(String(a.role || '').toLowerCase());
      const bIndex = priority.indexOf(String(b.role || '').toLowerCase());
      return (aIndex < 0 ? 99 : aIndex) - (bIndex < 0 ? 99 : bIndex);
    });
    return assets[0]?.preview_url || '';
  };

  const updateCanvas = (detail) => {
    const product = detail.product || {};
    currentProduct = { ...product, image_url: chooseProductImage(detail) };

    const title = String(product.title || product.slug || 'Untitled product').trim();
    const description = cleanDescription(product.description);
    const price = formatCurrency(product.unit_value_cents, product.currency);

    if (productTitleNode) productTitleNode.textContent = title;
    if (productDescriptionNode) productDescriptionNode.textContent = description;
    if (productPriceNode) {
      productPriceNode.textContent = price;
      productPriceNode.hidden = price === '';
    }

    setImage(productImage, currentProduct.image_url, photoPlaceholder, title);
    if (loading) loading.hidden = true;
    if (canvas) canvas.hidden = false;
    if (downloadButton) downloadButton.disabled = false;
    if (postButton) postButton.disabled = false;

    if (productMeta) {
      const productStatus = String(product.status || '').toLowerCase();
      productMeta.textContent = `${productStatus === 'published' ? 'Published' : 'Draft'} product · refreshed from your catalog.`;
    }
    setStatus('Design ready. Choose a format or layout.', 'success');
  };

  const loadProfile = async () => {
    try {
      const data = await request('/api/social/posts.php?scope=mine&limit=1');
      updateProfile(data.profile || {});
    } catch (_) {
      updateProfile({ display_name: app.dataset.merchantName || 'Your Business', avatar_url: '' });
    }
  };

  const showEmpty = (title, copy) => {
    currentProduct = null;
    if (canvas) canvas.hidden = true;
    if (loading) {
      loading.hidden = false;
      const strong = loading.querySelector('strong');
      const paragraph = loading.querySelector('p');
      if (strong) strong.textContent = title;
      if (paragraph) paragraph.textContent = copy;
    }
    if (downloadButton) downloadButton.disabled = true;
    if (postButton) postButton.disabled = true;
  };

  const loadProductDetail = async (productId) => {
    if (!productId) {
      showEmpty('No merchant product selected', 'Create or select a product to generate social media artwork.');
      return;
    }

    setStatus('Refreshing product details…');
    try {
      updateCanvas(await request(`/api/merchant/product.php?id=${encodeURIComponent(productId)}`));
    } catch (error) {
      showEmpty('Product could not be loaded', error.message || 'Refresh the product and try again.');
      setStatus(error.message || 'Unable to load product.', 'error');
    }
  };

  const populateProducts = (selectedId = '') => {
    if (!productSelect) return;
    productSelect.innerHTML = '';

    if (!products.length) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = 'No merchant products found';
      productSelect.appendChild(option);
      productSelect.disabled = true;
      return;
    }

    productSelect.disabled = false;
    products.forEach((product) => {
      const option = document.createElement('option');
      option.value = String(product.public_id || '');
      option.textContent = `${product.title || product.slug || 'Untitled product'}${product.status === 'published' ? '' : ' · Draft'}`;
      productSelect.appendChild(option);
    });

    const exists = products.some((product) => String(product.public_id) === selectedId);
    productSelect.value = exists ? selectedId : String(products[0].public_id || '');
  };

  const loadProducts = async ({ preserveSelection = false } = {}) => {
    const selectedId = preserveSelection ? String(productSelect?.value || '') : '';
    if (refreshButton) refreshButton.disabled = true;
    showEmpty('Loading merchant products', 'Your most recently updated product will appear here.');
    setStatus('Loading your merchant catalog…');

    try {
      const data = await request('/api/merchant/products.php?sort=updated_desc&limit=50');
      products = Array.isArray(data.products) ? data.products : [];
      populateProducts(selectedId);
      await loadProductDetail(String(productSelect?.value || ''));
    } catch (error) {
      products = [];
      populateProducts();
      showEmpty('Merchant products are unavailable', error.message || 'A merchant catalog is required for social templates.');
      setStatus(error.message || 'Unable to load merchant products.', 'error');
    } finally {
      socialLoaded = true;
      if (refreshButton) refreshButton.disabled = false;
    }
  };

  const loadSocialStudio = () => Promise.all([loadProfile(), loadProducts()]);

  const setFormat = (format) => {
    currentFormat = formats[format] ? format : 'square';
    formatButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.socialFormat === currentFormat);
    });
    if (canvas) {
      canvas.classList.toggle('is-square', currentFormat === 'square');
      canvas.classList.toggle('is-portrait', currentFormat === 'portrait');
      canvas.classList.toggle('is-story', currentFormat === 'story');
    }
    if (formatLabel) formatLabel.textContent = formats[currentFormat].label;
  };

  const setLayout = (layout) => {
    currentLayout = layouts[layout] ? layout : 'spotlight';
    layoutButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.socialLayout === currentLayout);
    });
    if (canvas) {
      Object.keys(layouts).forEach((key) => canvas.classList.remove(`layout-${key}`));
      canvas.classList.add(`layout-${currentLayout}`);
    }
    if (layoutLabel) layoutLabel.textContent = layouts[currentLayout];
  };

  const loadCanvasLibrary = () => new Promise((resolve, reject) => {
    if (typeof window.html2canvas === 'function') return resolve();
    const src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      existing.addEventListener('load', resolve, { once: true });
      existing.addEventListener('error', reject, { once: true });
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });

  const waitForImages = async () => {
    if (!canvas) return;
    const images = Array.from(canvas.querySelectorAll('img')).filter((image) => !image.hidden && image.src);
    await Promise.all(images.map((image) => {
      if (image.complete && image.naturalWidth > 0) return Promise.resolve();
      return new Promise((resolve) => {
        const finish = () => resolve();
        image.addEventListener('load', finish, { once: true });
        image.addEventListener('error', finish, { once: true });
        window.setTimeout(finish, 2500);
      });
    }));
  };

  const renderCanvas = async () => {
    if (!canvas || !currentProduct) throw new Error('Choose a product first.');
    await loadCanvasLibrary();
    await waitForImages();
    return window.html2canvas(canvas, {
      backgroundColor: '#0b1f3a',
      scale: 2.5,
      useCORS: true,
      allowTaint: false,
      logging: false,
    });
  };

  const safeFilename = (value) => String(value || 'product')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 56) || 'product';

  const downloadDesign = async () => {
    const rendered = await renderCanvas();
    const link = document.createElement('a');
    link.download = `microgifter-${safeFilename(currentProduct.title || currentProduct.slug)}-${formats[currentFormat].filename}.jpg`;
    link.href = rendered.toDataURL('image/jpeg', 0.96);
    link.click();
  };

  const canvasBlob = (rendered) => new Promise((resolve, reject) => {
    rendered.toBlob((blob) => {
      if (blob) resolve(blob);
      else reject(new Error('The social image could not be prepared.'));
    }, 'image/jpeg', 0.96);
  });

  const publishDesign = async () => {
    if (!currentProduct) throw new Error('Choose a product first.');

    const rendered = await renderCanvas();
    const blob = await canvasBlob(rendered);
    const title = String(currentProduct.title || currentProduct.slug || 'Microgifter product');
    const filename = `microgifter-${safeFilename(title)}-${formats[currentFormat].filename}.jpg`;
    const file = new File([blob], filename, { type: 'image/jpeg' });
    const upload = new FormData();
    upload.append('media', file, filename);
    upload.append('media_type', 'image');

    const media = await request('/api/social/media-upload.php', { method: 'POST', body: upload });
    if (!media.asset_id || !media.url) throw new Error('The generated social image was not uploaded.');

    const merchantName = String(currentProfile?.display_name || app.dataset.merchantName || 'our local business');
    const description = cleanDescription(currentProduct.description);

    return postJson('/api/social/posts.php', {
      action: 'create',
      post_id: '',
      headline: title,
      body: `${description}\n\nDiscover ${title} from ${merchantName} on Microgifter.`,
      visibility: 'public',
      post_type: 'image',
      product_id: String(currentProduct.public_id || ''),
      microgift_id: '',
      subscription_plan_id: '',
      link_url: String(currentProduct.public_url || ''),
      media: [{
        url: media.url,
        type: 'image',
        asset_id: media.asset_id,
        alt: `${title} promotional design`,
        caption: `${title} · ${formats[currentFormat].label}`,
      }],
      publish: true,
      idempotency_key: `design-studio:${window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`}`,
    });
  };

  modeButtons.forEach((button) => {
    button.addEventListener('click', () => setMode(button.dataset.designMode || 'print'));
  });

  productSelect?.addEventListener('change', () => loadProductDetail(String(productSelect.value || '')));
  refreshButton?.addEventListener('click', () => loadProducts({ preserveSelection: true }));

  formatButtons.forEach((button) => {
    button.addEventListener('click', () => setFormat(button.dataset.socialFormat || 'square'));
  });

  layoutButtons.forEach((button) => {
    button.addEventListener('click', () => setLayout(button.dataset.socialLayout || 'spotlight'));
  });

  downloadButton?.addEventListener('click', async () => {
    setBusy(downloadButton, true, 'Preparing JPG…');
    setStatus('Rendering your social media file…');
    try {
      await downloadDesign();
      setStatus('JPG download created.', 'success');
    } catch (error) {
      setStatus(error.message || 'The JPG export could not be completed.', 'error');
    } finally {
      setBusy(downloadButton, false);
    }
  });

  postButton?.addEventListener('click', async () => {
    setBusy(postButton, true, 'Posting…');
    setStatus('Rendering and publishing your feed post…');
    try {
      await publishDesign();
      setStatus('Your design was published to your Microgifter feed.', 'success');
    } catch (error) {
      setStatus(error.message || 'The design could not be posted to your feed.', 'error');
    } finally {
      setBusy(postButton, false);
    }
  });

  setMode('print');
  setFormat('square');
  setLayout('spotlight');
})();
