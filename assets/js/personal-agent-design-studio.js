(() => {
  const app = document.querySelector('[data-agent-design-studio]');
  if (!app) return;

  const agentRoot = document.querySelector('[data-personal-gifting-agent]');
  const designLink = document.querySelector('.mg-personal-chat-action[href="/design-studio.php"]');
  const views = Array.from(document.querySelectorAll('[data-personal-agent-view]'));
  const objectPicker = app.querySelector('[data-design-object-picker]');
  const workspace = app.querySelector('[data-design-workspace]');
  const templateBrowser = app.querySelector('[data-design-template-browser]');
  const loadedSection = app.querySelector('[data-design-loaded-section]');
  const headerActions = app.querySelector('[data-design-header-actions]');
  const objectButtons = Array.from(app.querySelectorAll('[data-design-object]'));
  const templateButtons = Array.from(app.querySelectorAll('[data-design-template]'));
  const backButton = app.querySelector('[data-design-back]');
  const closeTemplateButton = app.querySelector('[data-design-close-template]');
  const canvas = app.querySelector('[data-design-canvas]');
  const qrTarget = app.querySelector('[data-design-qr]');
  const pageTitle = app.querySelector('[data-design-page-title]');
  const pageDescription = app.querySelector('[data-design-page-description]');
  const formatLabels = Array.from(app.querySelectorAll('[data-design-format-label]'));
  const templateLabel = app.querySelector('[data-design-template-label]');
  const status = app.querySelector('[data-design-status]');
  const downloadButton = app.querySelector('[data-design-download]');

  const formats = {
    poster: {
      label: '5 × 5 Poster Card',
      filename: '5x5-poster',
    },
    tent: {
      label: 'Table Tent',
      filename: 'table-tent',
    },
  };

  let currentFormat = null;
  let currentTemplate = null;

  const setStatus = (message) => {
    if (status) status.textContent = message || '';
  };

  const setPageCopy = (title, description) => {
    if (pageTitle) pageTitle.textContent = title;
    if (pageDescription) pageDescription.textContent = description;
  };

  const showDesign = () => {
    views.forEach((view) => { view.hidden = view !== app; });
    designLink?.classList.add('is-active');
    showObjectPicker();
    app.scrollIntoView({ block: 'start' });
  };

  const showHeaderActions = (show) => {
    if (headerActions) headerActions.hidden = !show;
  };

  const resetTemplate = (showBrowser = false) => {
    currentTemplate = null;
    templateButtons.forEach((button) => button.classList.remove('is-active'));
    if (canvas) canvas.hidden = true;
    if (loadedSection) loadedSection.hidden = true;
    if (templateBrowser) templateBrowser.hidden = !showBrowser;
    showHeaderActions(false);
    setStatus('');
  };

  function showObjectPicker() {
    currentFormat = null;
    resetTemplate(false);
    if (objectPicker) objectPicker.hidden = false;
    if (workspace) workspace.hidden = true;
    setPageCopy(
      'Choose what you want to design.',
      'Select a print object first. Template choices open after you choose the format.'
    );
  }

  const setFormat = (format) => {
    currentFormat = formats[format] ? format : 'poster';
    const config = formats[currentFormat];

    canvas?.classList.toggle('is-poster', currentFormat === 'poster');
    canvas?.classList.toggle('is-tent', currentFormat === 'tent');
    formatLabels.forEach((node) => { node.textContent = config.label; });

    templateButtons.forEach((button) => {
      const allowed = String(button.dataset.templateFormats || '')
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean);
      button.hidden = allowed.length > 0 && !allowed.includes(currentFormat);
    });
  };

  const showWorkspace = (format) => {
    setFormat(format);
    resetTemplate(true);
    if (objectPicker) objectPicker.hidden = true;
    if (workspace) workspace.hidden = false;
    const config = formats[currentFormat] || formats.poster;
    setPageCopy(
      `Choose a template for your ${config.label}.`,
      'Select a template to open the finished design workspace.'
    );
    workspace?.scrollIntoView({ block: 'start' });
  };

  designLink?.addEventListener('click', (event) => {
    event.preventDefault();
    showDesign();
  });

  document.querySelector('[data-personal-agent-new-chat]')?.addEventListener('click', () => {
    designLink?.classList.remove('is-active');
    app.hidden = true;
    showObjectPicker();
  });

  objectButtons.forEach((button) => {
    button.addEventListener('click', () => showWorkspace(button.dataset.designObject || 'poster'));
  });

  backButton?.addEventListener('click', showObjectPicker);
  closeTemplateButton?.addEventListener('click', () => {
    resetTemplate(true);
    templateBrowser?.scrollIntoView({ block: 'start' });
  });

  const loadScript = (src, test) => new Promise((resolve, reject) => {
    if (test()) return resolve();
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

  const ensureLibraries = async () => {
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', () => typeof window.QRCode === 'function');
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', () => typeof window.html2canvas === 'function');
  };

  const profileDestination = () => {
    const raw = (app.dataset.defaultDestination || '/profile.php').trim();
    try { return new URL(raw, window.location.origin).href; }
    catch (_) { return new URL('/profile.php', window.location.origin).href; }
  };

  const renderQr = async () => {
    if (!qrTarget) return;
    await ensureLibraries();
    qrTarget.innerHTML = '';
    new window.QRCode(qrTarget, {
      text: profileDestination(),
      width: 220,
      height: 220,
      colorDark: '#10213b',
      colorLight: '#ffffff',
      correctLevel: window.QRCode.CorrectLevel.H,
    });
  };

  const selectTemplate = async (button) => {
    currentTemplate = button.dataset.designTemplate || 'support-local';
    templateButtons.forEach((item) => item.classList.toggle('is-active', item === button));
    if (templateLabel) templateLabel.textContent = button.querySelector('strong')?.textContent || 'Template selected';
    if (templateBrowser) templateBrowser.hidden = true;
    if (loadedSection) loadedSection.hidden = false;
    if (canvas) canvas.hidden = false;
    showHeaderActions(true);
    setStatus('Ready to save or download.');
    loadedSection?.scrollIntoView({ block: 'start' });
    try { await renderQr(); }
    catch (_) { setStatus('Template loaded. QR preview will retry during export.'); }
  };

  templateButtons.forEach((button) => {
    button.addEventListener('click', () => selectTemplate(button));
  });

  app.querySelectorAll('[data-design-mode]').forEach((button) => {
    button.addEventListener('click', () => {
      showHeaderActions(button.dataset.designMode === 'print' && Boolean(currentTemplate));
    });
  });

  const downloadJpg = async () => {
    if (!canvas || !currentTemplate || !currentFormat) return;
    await ensureLibraries();
    await renderQr();
    const result = await window.html2canvas(canvas, {
      backgroundColor: '#eef3f8',
      scale: 3,
      useCORS: true,
      logging: false,
    });
    const link = document.createElement('a');
    const config = formats[currentFormat] || formats.poster;
    link.download = `microgifter-${config.filename}.jpg`;
    link.href = result.toDataURL('image/jpeg', 0.96);
    link.click();
  };

  downloadButton?.addEventListener('click', async () => {
    if (!currentTemplate || !currentFormat) return;
    const original = downloadButton.textContent;
    downloadButton.disabled = true;
    downloadButton.textContent = 'Preparing JPG…';
    setStatus('Rendering your print file…');
    try {
      await downloadJpg();
      setStatus('JPG download created.');
    } catch (_) {
      setStatus('The JPG export could not be completed. Please try again.');
    } finally {
      downloadButton.disabled = false;
      downloadButton.textContent = original;
    }
  });

  if (agentRoot?.dataset.activeView === 'design') showDesign();
  else showObjectPicker();
})();
