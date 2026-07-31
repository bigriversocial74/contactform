(() => {
  'use strict';

  const app = document.querySelector('[data-agent-design-studio]');
  if (!app) return;

  const MG = window.Microgifter || {};
  const modeButtons = Array.from(app.querySelectorAll('[data-design-mode]'));
  const modePanels = Array.from(app.querySelectorAll('[data-design-mode-panel]'));
  const socialPanel = app.querySelector('[data-design-mode-panel="social"]');
  const registryNode = document.getElementById('mg-social-design-registry');

  const parseRegistry = () => {
    try {
      const parsed = JSON.parse(registryNode?.textContent || '{}');
      if (!parsed.templates || !parsed.formats || !parsed.variants) throw new Error('Registry incomplete.');
      return parsed;
    } catch (_) {
      return { version: 0, templates: {}, formats: {}, variants: {}, fallbacks: {} };
    }
  };

  const registry = parseRegistry();
  const templateEntries = Object.values(registry.templates || {});
  const formatEntries = Object.values(registry.formats || {});
  const variantEntries = Object.values(registry.variants || {});

  if (!socialPanel || !templateEntries.length || !formatEntries.length || !variantEntries.length) return;

  socialPanel.innerHTML = `
    <div class="mg-social-v2-shell" data-social-v2-shell>
      <aside class="mg-social-v2-product-panel" aria-label="Selected merchant product">
        <div class="mg-social-v2-panel-heading">
          <span class="mg-agent-design-step">Product</span>
          <h2>Choose what to promote</h2>
          <p>Social templates use one primary product image and your real catalog data.</p>
        </div>
        <label class="mg-social-v2-field">
          <span>Merchant product</span>
          <select data-social-product-select aria-label="Select a merchant product">
            <option value="">Loading products…</option>
          </select>
        </label>
        <button type="button" class="mg-social-v2-refresh" data-social-refresh>
          <span aria-hidden="true">↻</span> Refresh catalog
        </button>
        <article class="mg-social-v2-product-summary" data-social-product-summary hidden>
          <div class="mg-social-v2-product-thumb">
            <img data-social-summary-image alt="">
            <span data-social-summary-fallback>MG</span>
          </div>
          <div>
            <strong data-social-summary-title>Choose a product</strong>
            <span data-social-summary-price></span>
            <small data-social-product-meta>Catalog data loads here.</small>
          </div>
        </article>
        <div class="mg-social-v2-data-note">
          <strong>Data-grounded creative</strong>
          <span>No invented reviews, product claims, ingredients, or merchandising details.</span>
        </div>
      </aside>

      <main class="mg-social-v2-builder">
        <header class="mg-social-v2-builder-head">
          <div>
            <span>Social Design Center v2</span>
            <h1>Build one design across every social format.</h1>
          </div>
          <nav class="mg-social-v2-progress" aria-label="Design progress">
            <button type="button" class="is-current" data-social-step-jump="template"><b>1</b><span>Template</span></button>
            <button type="button" disabled data-social-step-jump="format"><b>2</b><span>Format</span></button>
            <button type="button" disabled data-social-step-jump="variant"><b>3</b><span>Layout</span></button>
            <button type="button" disabled data-social-step-jump="preview"><b>4</b><span>Preview</span></button>
          </nav>
        </header>

        <section class="mg-social-v2-step is-active" data-social-step="template">
          <div class="mg-social-v2-step-heading">
            <div><span>Step 1</span><h2>Choose a template family</h2></div>
            <p>Each family adapts to Post, Portrait, and Reel / Story formats.</p>
          </div>
          <div class="mg-social-v2-template-grid" data-social-template-picker></div>
        </section>

        <section class="mg-social-v2-step" data-social-step="format" hidden>
          <div class="mg-social-v2-step-heading">
            <div><span>Step 2</span><h2>Choose a format</h2></div>
            <button type="button" data-social-back="template">Change template</button>
          </div>
          <div class="mg-social-v2-format-grid" data-social-format-picker></div>
        </section>

        <section class="mg-social-v2-step" data-social-step="variant" hidden>
          <div class="mg-social-v2-step-heading">
            <div><span>Step 3</span><h2>Choose an ad layout</h2></div>
            <button type="button" data-social-back="format">Change format</button>
          </div>
          <div class="mg-social-v2-variant-grid" data-social-layout-picker></div>
        </section>

        <section class="mg-social-v2-step mg-social-v2-review-step" data-social-step="preview" hidden>
          <div class="mg-social-v2-step-heading">
            <div><span>Step 4</span><h2>Review and publish</h2></div>
            <button type="button" data-social-back="variant">Change layout</button>
          </div>
          <div class="mg-social-v2-selection-summary">
            <span><b>Template</b><strong data-social-template-label>—</strong></span>
            <span><b>Format</b><strong data-social-format-label>—</strong></span>
            <span><b>Layout</b><strong data-social-layout-label>—</strong></span>
          </div>
        </section>
      </main>

      <aside class="mg-social-v2-preview-column" aria-label="Live social creative preview">
        <header class="mg-social-v2-preview-head">
          <div>
            <span>Live preview</span>
            <strong data-social-preview-title>Choose a template</strong>
          </div>
          <span class="mg-social-v2-live-badge"><i></i> Live</span>
        </header>

        <div class="mg-social-v2-stage">
          <div class="mg-agent-social-loading" data-social-loading>
            <span class="mg-agent-design-empty-icon" aria-hidden="true">✦</span>
            <strong>Start with a template</strong>
            <p>Your selected product will appear after template, format, and layout choices are complete.</p>
          </div>

          <article class="mg-agent-social-canvas" data-social-canvas hidden>
            <div class="mg-social-v2-photo">
              <img data-social-product-image alt="">
              <div class="mg-social-v2-photo-fallback" data-social-photo-placeholder>
                <img src="/images/logo_main_drk.png" alt="">
              </div>
            </div>
            <div class="mg-social-v2-overlay"></div>
            <header class="mg-social-v2-brand">
              <span class="mg-social-v2-avatar">
                <img data-social-profile-image alt="">
                <span data-social-profile-fallback>MG</span>
              </span>
              <span>
                <strong data-social-merchant-name>Your Business</strong>
                <small>Available on Microgifter</small>
              </span>
            </header>
            <div class="mg-social-v2-copy">
              <span class="mg-social-v2-kicker" data-social-kicker>Support local. Gift better.</span>
              <h2 data-social-product-title>Choose a product</h2>
              <p data-social-product-description>Product details load from your merchant catalog.</p>
              <strong class="mg-social-v2-price" data-social-product-price></strong>
              <div class="mg-social-v2-review" data-social-review hidden>
                <span data-social-review-stars></span>
                <blockquote data-social-review-quote></blockquote>
                <cite data-social-review-author></cite>
              </div>
              <div class="mg-social-v2-review-fallback" data-social-review-fallback hidden>
                <strong data-social-review-fallback-copy>Support local. Gift better.</strong>
                <span>Give a local experience worth sharing.</span>
              </div>
            </div>
            <footer class="mg-social-v2-footer">
              <span data-social-cta-label>Shop this product</span>
              <img src="/images/logo_main_drk.png" alt="Microgifter">
            </footer>
          </article>
        </div>

        <div class="mg-agent-social-actions">
          <button type="button" class="mg-btn mg-btn-soft" data-social-download disabled>Download JPG</button>
          <button type="button" class="mg-btn mg-btn-primary" data-social-post-feed disabled>Post to Feed</button>
          <span data-social-status role="status" aria-live="polite">Choose a template to begin.</span>
        </div>
      </aside>
    </div>`;

  const q = (selector) => app.querySelector(selector);
  const qa = (selector) => Array.from(app.querySelectorAll(selector));
  const productSelect = q('[data-social-product-select]');
  const productMeta = q('[data-social-product-meta]');
  const productSummary = q('[data-social-product-summary]');
  const summaryImage = q('[data-social-summary-image]');
  const summaryFallback = q('[data-social-summary-fallback]');
  const summaryTitle = q('[data-social-summary-title]');
  const summaryPrice = q('[data-social-summary-price]');
  const refreshButton = q('[data-social-refresh]');
  const templatePicker = q('[data-social-template-picker]');
  const formatPicker = q('[data-social-format-picker]');
  const variantPicker = q('[data-social-layout-picker]');
  const canvas = q('[data-social-canvas]');
  const loading = q('[data-social-loading]');
  const status = q('[data-social-status]');
  const downloadButton = q('[data-social-download]');
  const postButton = q('[data-social-post-feed]');
  const productImage = q('[data-social-product-image]');
  const photoPlaceholder = q('[data-social-photo-placeholder]');
  const profileImage = q('[data-social-profile-image]');
  const profileFallback = q('[data-social-profile-fallback]');
  const merchantNameNode = q('[data-social-merchant-name]');
  const productTitleNode = q('[data-social-product-title]');
  const productDescriptionNode = q('[data-social-product-description]');
  const productPriceNode = q('[data-social-product-price]');
  const ctaNode = q('[data-social-cta-label]');
  const kickerNode = q('[data-social-kicker]');
  const reviewNode = q('[data-social-review]');
  const reviewStars = q('[data-social-review-stars]');
  const reviewQuote = q('[data-social-review-quote]');
  const reviewAuthor = q('[data-social-review-author]');
  const reviewFallback = q('[data-social-review-fallback]');
  const reviewFallbackCopy = q('[data-social-review-fallback-copy]');
  const previewTitle = q('[data-social-preview-title]');

  let activeMode = 'print';
  let products = [];
  let currentProduct = null;
  let currentDetail = null;
  let currentProfile = null;
  let selectedTemplate = '';
  let selectedFormat = '';
  let selectedVariant = '';
  let socialLoaded = false;

  const payload = (response) => (response && response.data ? response.data : response);

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

  const setStatus = (message, type = '') => {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-success', type === 'success');
    status.classList.toggle('is-error', type === 'error');
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
    button.disabled = !isReady();
  };

  const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

  const renderPickers = () => {
    templatePicker.innerHTML = templateEntries.map((template, index) => `
      <button type="button" class="mg-social-v2-template-card template-${esc(template.id)}"
              data-social-template="${esc(template.id)}" aria-pressed="false">
        <span class="mg-social-v2-template-preview" aria-hidden="true">
          <i></i><b></b><em></em><small>${String(index + 1).padStart(2, '0')}</small>
        </span>
        <span class="mg-social-v2-template-copy">
          <strong>${esc(template.label)}</strong>
          <small>${esc(template.description)}</small>
        </span>
        <span class="mg-social-v2-template-check" aria-hidden="true">✓</span>
      </button>`).join('');

    formatPicker.innerHTML = formatEntries.map((format) => `
      <button type="button" data-social-format="${esc(format.id)}" aria-pressed="false">
        <span class="mg-social-v2-format-icon is-${esc(format.id)}"><i></i></span>
        <strong>${esc(format.label)}</strong>
        <small>${esc(format.ratio_label)} · ${Number(format.width)} × ${Number(format.height)}</small>
      </button>`).join('');

    variantPicker.innerHTML = variantEntries.map((variant) => `
      <button type="button" data-social-layout="${esc(variant.id)}" aria-pressed="false">
        <span class="mg-social-v2-variant-icon is-${esc(variant.id)}"><i></i><b></b></span>
        <span><strong>${esc(variant.label)}</strong><small>${esc(variant.description)}</small></span>
      </button>`).join('');
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
    return text || registry.fallbacks?.description || 'Discover this local product, service, or experience on Microgifter.';
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

  const chooseProductImage = (detail) => {
    const assets = [
      ...(Array.isArray(detail.assets) ? detail.assets : []),
      ...(Array.isArray(detail.draft_assets) ? detail.draft_assets : []),
    ].filter((asset) => String(asset.asset_type || '').toLowerCase() === 'image');

    const priority = ['primary', 'cover', 'hero', 'product', 'front'];
    assets.sort((a, b) => {
      const ai = priority.indexOf(String(a.role || '').toLowerCase());
      const bi = priority.indexOf(String(b.role || '').toLowerCase());
      return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
    });
    return assets[0]?.preview_url || '';
  };

  const normalizeReview = (review) => {
    if (!review || typeof review !== 'object') return null;
    const quote = String(review.quote || '').replace(/\s+/g, ' ').trim();
    const reviewer = String(review.reviewer || '').replace(/\s+/g, ' ').trim();
    if (!quote || !reviewer || !['product', 'merchant'].includes(String(review.source || ''))) return null;
    const rating = Number(review.rating || 0);
    return {
      source: String(review.source),
      quote: quote.slice(0, 180),
      reviewer: reviewer.slice(0, 60),
      rating: rating >= 1 && rating <= 5 ? Math.round(rating) : 0,
    };
  };

  const updateProfile = (profile) => {
    currentProfile = profile || currentProfile || {};
    const merchantName = String(currentProfile.display_name || app.dataset.merchantName || 'Your Business').trim();
    merchantNameNode.textContent = merchantName;
    profileFallback.textContent = merchantName.slice(0, 1).toUpperCase() || 'M';
    setImage(profileImage, currentProfile.avatar_url || '', profileFallback, `${merchantName} profile`);
  };

  const updateProductSummary = () => {
    if (!currentProduct) {
      productSummary.hidden = true;
      return;
    }
    productSummary.hidden = false;
    summaryTitle.textContent = currentProduct.title || currentProduct.slug || 'Untitled product';
    summaryPrice.textContent = formatCurrency(currentProduct.unit_value_cents, currentProduct.currency);
    setImage(summaryImage, currentProduct.image_url, summaryFallback, summaryTitle.textContent);
    productMeta.textContent = `${String(currentProduct.status || '').toLowerCase() === 'published' ? 'Published' : 'Draft'} product · one primary image selected.`;
  };

  const templateFallback = (template) => {
    if (template?.id === 'social-proof-promo') return 'Local products are better when they are shared.';
    return registry.fallbacks?.review || 'Support local. Gift better.';
  };

  const renderReview = () => {
    const template = registry.templates[selectedTemplate];
    const review = normalizeReview(currentDetail?.design_review);
    const supportsReview = Boolean(template?.supports_review);

    reviewNode.hidden = true;
    reviewFallback.hidden = true;
    canvas.classList.remove('has-real-review', 'has-review-fallback');

    if (!supportsReview) return;

    if (review) {
      reviewStars.textContent = review.rating ? '★'.repeat(review.rating) : '';
      reviewQuote.textContent = `“${review.quote}”`;
      reviewAuthor.textContent = `— ${review.reviewer}`;
      reviewNode.hidden = false;
      canvas.classList.add('has-real-review');
      return;
    }

    reviewFallbackCopy.textContent = templateFallback(template);
    reviewFallback.hidden = false;
    canvas.classList.add('has-review-fallback');
  };

  const isReady = () => Boolean(currentProduct && selectedTemplate && selectedFormat && selectedVariant);

  const updateCanvas = () => {
    if (!currentProduct || !isReady()) {
      canvas.hidden = true;
      loading.hidden = false;
      downloadButton.disabled = true;
      postButton.disabled = true;
      return;
    }

    const template = registry.templates[selectedTemplate];
    const format = registry.formats[selectedFormat];
    const variant = registry.variants[selectedVariant];
    const title = String(currentProduct.title || currentProduct.slug || 'Untitled product').trim();
    const description = cleanDescription(currentProduct.description);
    const price = formatCurrency(currentProduct.unit_value_cents, currentProduct.currency);
    const metadata = currentProduct.metadata && typeof currentProduct.metadata === 'object' ? currentProduct.metadata : {};
    const cta = String(metadata.cta || registry.fallbacks?.cta || 'Shop this product').trim();

    canvas.className = `mg-agent-social-canvas template-${selectedTemplate} format-${selectedFormat} layout-${selectedVariant}`;
    canvas.dataset.socialTemplate = selectedTemplate;
    canvas.dataset.socialFormat = selectedFormat;
    canvas.dataset.socialLayout = selectedVariant;
    canvas.dataset.registryVersion = String(registry.version || 2);
    canvas.style.setProperty('--social-aspect-ratio', String(format.aspect_ratio || '1 / 1'));

    productTitleNode.textContent = title;
    productDescriptionNode.textContent = description;
    productPriceNode.textContent = price;
    productPriceNode.hidden = price === '';
    ctaNode.textContent = cta;
    kickerNode.textContent = template.preview?.eyebrow || 'Support local. Gift better.';
    setImage(productImage, currentProduct.image_url, photoPlaceholder, title);
    renderReview();

    q('[data-social-template-label]').textContent = template.label;
    q('[data-social-format-label]').textContent = `${format.label} · ${format.ratio_label}`;
    q('[data-social-layout-label]').textContent = variant.label;
    previewTitle.textContent = `${template.label} · ${format.label}`;

    loading.hidden = true;
    canvas.hidden = false;
    downloadButton.disabled = false;
    postButton.disabled = false;
    setStatus('Creative ready. Save, download, or post it.', 'success');
  };

  const showLoadingMessage = (title, copy) => {
    canvas.hidden = true;
    loading.hidden = false;
    const strong = loading.querySelector('strong');
    const paragraph = loading.querySelector('p');
    if (strong) strong.textContent = title;
    if (paragraph) paragraph.textContent = copy;
    downloadButton.disabled = true;
    postButton.disabled = true;
  };

  const updateStepProgress = (step) => {
    const order = ['template', 'format', 'variant', 'preview'];
    const currentIndex = order.indexOf(step);
    qa('[data-social-step-jump]').forEach((button) => {
      const index = order.indexOf(button.dataset.socialStepJump);
      const unlocked = index === 0
        || (index === 1 && selectedTemplate)
        || (index === 2 && selectedTemplate && selectedFormat)
        || (index === 3 && isReady());
      button.disabled = !unlocked;
      button.classList.toggle('is-current', index === currentIndex);
      button.classList.toggle('is-complete', index < currentIndex && unlocked);
    });
  };

  const showStep = (step, { scroll = true } = {}) => {
    const allowed = step === 'template'
      || (step === 'format' && selectedTemplate)
      || (step === 'variant' && selectedTemplate && selectedFormat)
      || (step === 'preview' && isReady());
    if (!allowed) return;

    qa('[data-social-step]').forEach((section) => {
      const active = section.dataset.socialStep === step;
      section.hidden = !active;
      section.classList.toggle('is-active', active);
    });
    updateStepProgress(step);
    if (scroll) q(`[data-social-step="${step}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  const selectTemplate = (id) => {
    if (!registry.templates[id]) return;
    selectedTemplate = id;
    qa('[data-social-template]').forEach((button) => {
      const active = button.dataset.socialTemplate === id;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    showStep('format');
    setStatus('Template selected. Choose a social format.');
    updateCanvas();
  };

  const selectFormat = (id) => {
    if (!registry.formats[id] || !selectedTemplate) return;
    selectedFormat = id;
    qa('[data-social-format]').forEach((button) => {
      const active = button.dataset.socialFormat === id;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    showStep('variant');
    setStatus('Format selected. Choose a layout.');
    updateCanvas();
  };

  const selectVariant = (id) => {
    if (!registry.variants[id] || !selectedTemplate || !selectedFormat) return;
    selectedVariant = id;
    qa('[data-social-layout]').forEach((button) => {
      const active = button.dataset.socialLayout === id;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    updateCanvas();
    showStep('preview');
  };

  const loadProfile = async () => {
    try {
      const data = await request('/api/social/posts.php?scope=mine&limit=1');
      updateProfile(data.profile || {});
    } catch (_) {
      updateProfile({ display_name: app.dataset.merchantName || 'Your Business', avatar_url: '' });
    }
  };

  const loadProductDetail = async (productId) => {
    if (!productId) {
      currentProduct = null;
      currentDetail = null;
      updateProductSummary();
      showLoadingMessage('No merchant product selected', 'Create or select a product to generate social artwork.');
      setStatus('Choose a merchant product.', 'error');
      return;
    }

    setStatus('Refreshing product details…');
    try {
      const detail = await request(`/api/merchant/product.php?id=${encodeURIComponent(productId)}`);
      const product = detail.product || {};
      currentDetail = detail;
      currentProduct = { ...product, image_url: chooseProductImage(detail) };
      updateProductSummary();
      if (isReady()) updateCanvas();
      else {
        showLoadingMessage('Choose a template', 'Your product is ready. Start with one of the ten template families.');
        setStatus('Product ready. Choose a template.', 'success');
      }
    } catch (error) {
      currentProduct = null;
      currentDetail = null;
      updateProductSummary();
      showLoadingMessage('Product could not be loaded', error.message || 'Refresh the product and try again.');
      setStatus(error.message || 'Unable to load product.', 'error');
    }
  };

  const populateProducts = (selectedId = '') => {
    productSelect.innerHTML = '';
    if (!products.length) {
      productSelect.append(new Option('No merchant products found', ''));
      productSelect.disabled = true;
      return;
    }

    productSelect.disabled = false;
    products.forEach((product) => {
      const title = product.title || product.slug || 'Untitled product';
      productSelect.append(new Option(`${title}${product.status === 'published' ? '' : ' · Draft'}`, String(product.public_id || '')));
    });
    const exists = products.some((product) => String(product.public_id) === selectedId);
    productSelect.value = exists ? selectedId : String(products[0].public_id || '');
  };

  const loadProducts = async ({ preserveSelection = false } = {}) => {
    const selectedId = preserveSelection ? String(productSelect.value || '') : '';
    refreshButton.disabled = true;
    showLoadingMessage('Loading merchant products', 'Your most recently updated product will appear here.');
    setStatus('Loading your merchant catalog…');

    try {
      const data = await request('/api/merchant/products.php?sort=updated_desc&limit=50');
      products = Array.isArray(data.products) ? data.products : [];
      populateProducts(selectedId);
      await loadProductDetail(String(productSelect.value || ''));
    } catch (error) {
      products = [];
      populateProducts();
      showLoadingMessage('Merchant products are unavailable', error.message || 'A merchant catalog is required for social templates.');
      setStatus(error.message || 'Unable to load merchant products.', 'error');
    } finally {
      socialLoaded = true;
      refreshButton.disabled = false;
    }
  };

  const loadSocialStudio = () => Promise.all([loadProfile(), loadProducts()]);

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
    if (!isReady() || canvas.hidden) throw new Error('Complete the template, format, and layout steps first.');
    await loadCanvasLibrary();
    await waitForImages();
    return window.html2canvas(canvas, {
      backgroundColor: null,
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
    const format = registry.formats[selectedFormat];
    const link = document.createElement('a');
    link.download = `microgifter-${safeFilename(currentProduct.title || currentProduct.slug)}-${selectedTemplate}-${format.width}x${format.height}.jpg`;
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
    if (!isReady()) throw new Error('Complete the design steps first.');
    const rendered = await renderCanvas();
    const blob = await canvasBlob(rendered);
    const format = registry.formats[selectedFormat];
    const title = String(currentProduct.title || currentProduct.slug || 'Microgifter product');
    const filename = `microgifter-${safeFilename(title)}-${selectedTemplate}-${format.width}x${format.height}.jpg`;
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
        caption: `${title} · ${registry.templates[selectedTemplate].label} · ${format.label}`,
      }],
      publish: true,
      idempotency_key: `design-studio-v2:${window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`}`,
    });
  };

  renderPickers();

  modeButtons.forEach((button) => {
    button.addEventListener('click', () => setMode(button.dataset.designMode || 'print'));
  });
  productSelect.addEventListener('change', () => loadProductDetail(String(productSelect.value || '')));
  refreshButton.addEventListener('click', () => loadProducts({ preserveSelection: true }));

  templatePicker.addEventListener('click', (event) => {
    const button = event.target.closest('[data-social-template]');
    if (button) selectTemplate(button.dataset.socialTemplate || '');
  });
  formatPicker.addEventListener('click', (event) => {
    const button = event.target.closest('[data-social-format]');
    if (button) selectFormat(button.dataset.socialFormat || '');
  });
  variantPicker.addEventListener('click', (event) => {
    const button = event.target.closest('[data-social-layout]');
    if (button) selectVariant(button.dataset.socialLayout || '');
  });

  qa('[data-social-back]').forEach((button) => {
    button.addEventListener('click', () => showStep(button.dataset.socialBack || 'template'));
  });
  qa('[data-social-step-jump]').forEach((button) => {
    button.addEventListener('click', () => showStep(button.dataset.socialStepJump || 'template'));
  });

  downloadButton.addEventListener('click', async () => {
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

  postButton.addEventListener('click', async () => {
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

  document.addEventListener('design-studio:schedule-context', (event) => {
    const item = event.detail?.item;
    if (!item) return;
    const template = String(item.template_key || item.template || '');
    const format = String(item.post_format || item.format_key || '');
    const variant = String(item.layout_key || '');
    if (registry.templates[template]) selectTemplate(template);
    if (registry.formats[format]) selectFormat(format);
    if (registry.variants[variant]) selectVariant(variant);
  });

  setMode('print');
  showStep('template', { scroll: false });
})();
