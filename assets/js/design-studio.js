(() => {
  const app = document.querySelector('[data-design-studio-app]');
  if (!app) return;

  const qs = (selector, root = app) => root.querySelector(selector);
  const qsa = (selector, root = app) => Array.from(root.querySelectorAll(selector));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const qrApi = app.dataset.qrApi || '/api/merchant/qr-library.php';
  const designApi = app.dataset.designApi || '/api/merchant/design-studio-assets.php';
  const brandApi = app.dataset.brandApi || '/api/merchant/brand-kit.php';
  const exportApi = app.dataset.exportApi || '/api/merchant/design-export.php';

  const template = qs('[data-design-template]');
  const formatLabel = qs('[data-preview-format-label]');
  const previewSize = qs('[data-preview-size]');
  const exportFormat = qs('[data-export-format]');
  const exportQr = qs('[data-export-qr]');
  const exportProject = qs('[data-export-project]');
  const exportBrand = qs('[data-export-brand]');
  const exportJob = qs('[data-export-job]');
  const brandKitStatus = qs('[data-brand-kit-status]');
  const templateHeadline = qs('[data-template-headline]');
  const templateOffer = qs('[data-template-offer]');
  const templateCta = qs('[data-template-cta]');
  const templateQrLabel = qs('[data-template-qr-label]');
  const templateQrKind = qs('[data-template-qr-kind]');
  const templateQrPayload = qs('[data-template-qr-payload]');
  const templateMedia = qs('[data-template-media]');
  const proofEstimate = qs('[data-proof-estimate]');
  const qrLibrary = qs('[data-qr-library]');
  const savedTemplateList = qs('[data-saved-template-list]');
  const templateCount = qs('[data-template-count]');
  const brandWebsite = qs('[data-brand-website]');
  const brandLogo = qs('[data-brand-logo]');
  const brandPalette = qs('[data-brand-palette]');

  const setActive = (button, selector) => {
    qsa(selector).forEach((item) => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      if (item.hasAttribute('aria-selected')) item.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  };

  const postJson = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ csrf_token: csrf, ...body }),
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Request failed.');
    return payload.data || {};
  };

  const activeFormat = () => qs('[data-format].is-active') || qs('[data-format]');
  const activeFormatInfo = () => {
    const button = activeFormat();
    return {
      button,
      format_key: button?.dataset.format || 'table-tent',
      template_type: button?.dataset.templateType || 'print',
      title: button?.dataset.title || 'Table Tent',
      size: button?.dataset.size || '4 × 6 in folded',
      ratio: button?.dataset.ratio || 'portrait',
      width_px: button?.dataset.widthPx ? Number(button.dataset.widthPx) : null,
      height_px: button?.dataset.heightPx ? Number(button.dataset.heightPx) : null,
      print_width_in: button?.dataset.printWidth ? Number(button.dataset.printWidth) : null,
      print_height_in: button?.dataset.printHeight ? Number(button.dataset.printHeight) : null,
    };
  };

  const currentCopy = () => ({
    headline: templateHeadline?.textContent?.trim() || 'Give local. Claim instantly.',
    offer: templateOffer?.textContent?.trim() || 'Scan to unlock today’s featured microgift, local reward, or pre-sale offer.',
    cta: templateCta?.textContent?.trim() || 'Scan to claim your reward',
  });

  const currentLayout = () => {
    const info = activeFormatInfo();
    return {
      version: 1,
      source: 'design_studio_ui',
      format_key: info.format_key,
      template_type: info.template_type,
      title: info.title,
      size: info.size,
      ratio: info.ratio,
      width_px: info.width_px,
      height_px: info.height_px,
      print_width_in: info.print_width_in,
      print_height_in: info.print_height_in,
      media_swatch: templateMedia?.dataset.swatch || 'gradient',
      selected_brand_kit_id: app.dataset.selectedBrandKitId || null,
      selected_qr_id: app.dataset.selectedQrId || null,
      selected_qr_payload: templateQrPayload?.textContent?.trim() || null,
      preview_side: app.dataset.previewSide || 'front',
      safe_zone: true,
    };
  };

  const renderBrandKit = (kit) => {
    if (!kit) {
      if (brandKitStatus) brandKitStatus.textContent = 'Not scanned';
      if (exportBrand) exportBrand.textContent = 'Not scanned yet';
      return;
    }
    app.dataset.selectedBrandKitId = kit.id || '';
    if (brandKitStatus) brandKitStatus.textContent = kit.name || 'Brand kit ready';
    if (exportBrand) exportBrand.textContent = kit.name || 'Brand kit ready';
    if (brandWebsite && kit.source_url) brandWebsite.value = kit.source_url;
    if (brandLogo) {
      brandLogo.textContent = kit.logo_url ? 'Logo candidate' : 'Logo pending';
      brandLogo.style.backgroundImage = kit.logo_url ? `url("${kit.logo_url.replace(/"/g, '%22')}")` : '';
      brandLogo.classList.toggle('has-logo', Boolean(kit.logo_url));
    }
    const colors = Array.isArray(kit.palette) && kit.palette.length ? kit.palette : [kit.primary_color, kit.secondary_color, kit.accent_color].filter(Boolean);
    if (brandPalette) {
      brandPalette.innerHTML = '';
      colors.slice(0, 6).forEach((color) => {
        const swatch = document.createElement('span');
        swatch.style.background = color;
        swatch.title = color;
        brandPalette.appendChild(swatch);
      });
    }
    if (template && colors[0]) template.style.setProperty('--mg-design-brand-primary', colors[0]);
    if (template && colors[1]) template.style.setProperty('--mg-design-brand-secondary', colors[1]);
  };

  const loadBrandKit = async () => {
    if (app.dataset.merchantOnly !== 'true') return;
    try {
      const response = await fetch(brandApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to load brand kit.');
      renderBrandKit((payload.data?.items || [])[0]);
    } catch (error) {
      renderBrandKit(null);
    }
  };

  const scanBrandKit = async () => {
    const website = brandWebsite?.value?.trim() || '';
    if (!website) throw new Error('Website URL is required.');
    const data = await postJson(brandApi, {
      action: 'scan_website',
      id: app.dataset.selectedBrandKitId || '',
      website_url: website,
      name: 'Website Brand Kit',
    });
    renderBrandKit(data.brand_kit);
    return data.brand_kit;
  };

  const qrFallback = () => ({ id: '', label: 'Featured Gift', kind_label: 'Claim QR', qr_type: 'claim', status: 'draft', destination_url: app.dataset.defaultQrDestination || '/store.php', qr_payload_url: '/qr.php?c=preview', scan_count: 0 });

  const selectQr = (button, item) => {
    if (button) setActive(button, '[data-qr-label]');
    if (templateQrLabel) templateQrLabel.textContent = item.label || 'Featured Gift';
    if (templateQrKind) templateQrKind.textContent = item.kind_label || 'Claim QR';
    if (templateQrPayload) templateQrPayload.textContent = item.qr_payload_url || 'QR payload pending';
    if (exportQr) exportQr.textContent = `${item.label || 'Featured Gift'} · ${item.kind_label || 'QR Code'}`;
    app.dataset.selectedQrId = item.id || '';
  };

  const renderQrLibrary = (items) => {
    if (!qrLibrary) return;
    const list = Array.isArray(items) && items.length ? items : [qrFallback()];
    qrLibrary.innerHTML = '';
    list.forEach((item, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.qrLabel = item.label || 'Untitled QR';
      button.dataset.qrKind = item.kind_label || 'QR Code';
      button.dataset.qrId = item.id || '';
      button.dataset.qrPayload = item.qr_payload_url || '';
      if (index === 0) button.classList.add('is-active');
      const status = item.status || 'draft';
      const scans = Number(item.scan_count || 0);
      button.innerHTML = '<strong></strong><span></span>';
      button.querySelector('strong').textContent = item.label || 'Untitled QR';
      button.querySelector('span').textContent = `${item.kind_label || 'QR Code'} · ${status}${scans ? ` · ${scans} scans` : ''}`;
      button.addEventListener('click', () => selectQr(button, item));
      qrLibrary.appendChild(button);
      if (index === 0) selectQr(button, item);
    });
  };

  const renderSavedTemplates = (templates) => {
    if (!savedTemplateList) return;
    const list = Array.isArray(templates) ? templates : [];
    if (templateCount) templateCount.textContent = `${list.length} saved`;
    savedTemplateList.innerHTML = '';
    if (!list.length) {
      const empty = document.createElement('button');
      empty.type = 'button';
      empty.disabled = true;
      empty.innerHTML = '<strong>No saved templates yet</strong><span>Save this canvas as a template.</span>';
      savedTemplateList.appendChild(empty);
      return;
    }
    list.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.templateId = item.id || '';
      button.innerHTML = '<strong></strong><span></span>';
      button.querySelector('strong').textContent = item.name || 'Saved template';
      button.querySelector('span').textContent = `${item.template_type || 'print'} · ${item.format_key || 'custom'}${item.is_presigned ? ' · signed' : ''}`;
      button.addEventListener('click', () => applyTemplate(item, button));
      savedTemplateList.appendChild(button);
    });
  };

  const applyTemplate = (item, button) => {
    if (button) setActive(button, '[data-saved-template-list] button');
    app.dataset.selectedTemplateId = item.id || '';
    const layout = item.layout || {};
    const match = qsa('[data-format]').find((formatButton) => formatButton.dataset.format === (item.format_key || layout.format_key));
    if (match) match.click();
    const copy = item.default_copy || {};
    const headline = qs('[data-design-field="headline"]');
    const offer = qs('[data-design-field="offer"]');
    const cta = qs('[data-design-field="cta"]');
    if (headline && copy.headline) headline.value = copy.headline;
    if (offer && copy.offer) offer.value = copy.offer;
    if (cta && copy.cta) cta.value = copy.cta;
    qsa('[data-design-field]').forEach((field) => field.dispatchEvent(new Event('input')));
    if (templateMedia && layout.media_swatch) templateMedia.dataset.swatch = layout.media_swatch;
    if (proofEstimate) proofEstimate.textContent = 'Saved template loaded';
  };

  const loadQrLibrary = async () => {
    if (!qrLibrary || app.dataset.merchantOnly !== 'true') return;
    qrLibrary.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(`${qrApi}?status=open`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to load QR library.');
      renderQrLibrary(payload.data?.items || []);
    } catch (error) {
      renderQrLibrary([]);
      if (proofEstimate) proofEstimate.textContent = 'QR library offline';
    } finally {
      qrLibrary.removeAttribute('aria-busy');
    }
  };

  const loadDesignAssets = async () => {
    if (app.dataset.merchantOnly !== 'true') return;
    const info = activeFormatInfo();
    try {
      const response = await fetch(`${designApi}?mode=bootstrap&template_type=${encodeURIComponent(info.template_type)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to load templates.');
      renderSavedTemplates(payload.data?.templates || []);
    } catch (error) {
      renderSavedTemplates([]);
      if (templateCount) templateCount.textContent = 'Offline';
    }
  };

  const createQr = async () => {
    const info = activeFormatInfo();
    const data = await postJson(qrApi, {
      action: 'create',
      label: `${info.title} QR`,
      qr_type: 'claim',
      status: 'active',
      destination_url: app.dataset.defaultQrDestination || '/store.php',
      metadata: { source: 'design_studio', format: info.format_key, template_type: info.template_type, brand_kit_id: app.dataset.selectedBrandKitId || null },
    });
    await loadQrLibrary();
    return data.item;
  };

  const saveTemplate = async () => {
    const info = activeFormatInfo();
    const data = await postJson(designApi, {
      action: 'save_template',
      brand_kit_id: app.dataset.selectedBrandKitId || null,
      template_type: info.template_type,
      format_key: info.format_key,
      name: `${info.title} Template`,
      description: `${info.title} template saved from Design Studio.`,
      status: 'active',
      width_px: info.width_px,
      height_px: info.height_px,
      print_width_in: info.print_width_in,
      print_height_in: info.print_height_in,
      bleed_in: info.template_type === 'print' ? 0.125 : null,
      qr_required: true,
      layout: currentLayout(),
      default_copy: currentCopy(),
      render_config: { output: info.template_type === 'social' ? 'png' : 'print_pdf', size: info.size },
    });
    app.dataset.selectedTemplateId = data.template?.id || '';
    await loadDesignAssets();
    return data.template;
  };

  const saveProject = async () => {
    const info = activeFormatInfo();
    const data = await postJson(designApi, {
      action: 'save_project',
      id: app.dataset.selectedProjectId || '',
      brand_kit_id: app.dataset.selectedBrandKitId || null,
      template_id: app.dataset.selectedTemplateId || null,
      qr_code_id: app.dataset.selectedQrId || null,
      project_type: info.template_type,
      format_key: info.format_key,
      name: `${info.title} Project`,
      status: 'draft',
      canvas: currentLayout(),
      copy: currentCopy(),
      media: { swatch: templateMedia?.dataset.swatch || 'gradient', brand_kit_id: app.dataset.selectedBrandKitId || null },
      print_options: { source: 'design_studio', size: info.size },
      export_manifest: { qr_required: true, template_type: info.template_type, format_key: info.format_key, brand_kit_id: app.dataset.selectedBrandKitId || null },
    });
    app.dataset.selectedProjectId = data.project?.id || '';
    if (exportProject) exportProject.textContent = data.project?.name || 'Saved project';
    return data.project;
  };

  const queueExport = async (exportType = null) => {
    const info = activeFormatInfo();
    const project = await saveProject();
    const type = exportType || (info.template_type === 'social' ? 'social_png' : 'proof');
    const data = await postJson(exportApi, {
      action: 'queue_export',
      project_id: project?.id || app.dataset.selectedProjectId || null,
      export_type: type,
      options: { size: info.size, template_type: info.template_type, format_key: info.format_key, verify_qr: true },
      manifest: { source: 'design_studio', selected_qr_id: app.dataset.selectedQrId || null, selected_brand_kit_id: app.dataset.selectedBrandKitId || null },
    });
    if (exportJob) exportJob.textContent = `${data.export_type || type} · ${data.status || 'queued'}`;
    return data;
  };

  const createQrAsset = async () => {
    if (!app.dataset.selectedQrId) throw new Error('Select or create a QR first.');
    return postJson(exportApi, { action: 'create_qr_asset', qr_code_id: app.dataset.selectedQrId, asset_type: 'qr_svg', name: `${templateQrLabel?.textContent || 'Design Studio'} QR SVG` });
  };

  const linkCampaign = async () => {
    const campaignRef = qs('[data-campaign-ref]')?.value?.trim() || '';
    const campaignType = qs('[data-campaign-type]')?.value || 'custom';
    if (!campaignRef) throw new Error('Campaign reference is required.');
    await saveProject();
    return postJson(exportApi, {
      action: 'link_campaign',
      project_id: app.dataset.selectedProjectId || null,
      qr_code_id: app.dataset.selectedQrId || null,
      campaign_type: campaignType,
      campaign_ref: campaignRef,
      label: `${activeFormatInfo().title} campaign asset`,
      metadata: { source: 'design_studio', brand_kit_id: app.dataset.selectedBrandKitId || null },
    });
  };

  const queueAiImage = async () => {
    const info = activeFormatInfo();
    return postJson(designApi, {
      action: 'queue_ai_job',
      project_id: app.dataset.selectedProjectId || null,
      brand_kit_id: app.dataset.selectedBrandKitId || null,
      generation_type: 'image',
      provider_key: 'agent_api',
      model_key: 'design-image-default',
      prompt: { source: 'design_studio', format_key: info.format_key, template_type: info.template_type, copy: currentCopy(), brand_kit_id: app.dataset.selectedBrandKitId || null, instruction: 'Create a merchant-safe promotional image concept for this asset.' },
    });
  };

  qsa('[data-format]').forEach((button) => {
    button.addEventListener('click', () => {
      const info = { title: button.dataset.title || button.textContent.trim(), size: button.dataset.size || '', ratio: button.dataset.ratio || 'portrait', format: button.dataset.format || 'custom' };
      setActive(button, '[data-format]');
      if (formatLabel) formatLabel.textContent = info.title;
      if (previewSize) previewSize.textContent = info.size;
      if (exportFormat) exportFormat.textContent = `${info.title} · ${info.size}`;
      if (template) {
        template.dataset.ratio = info.ratio;
        template.className = `mg-design-template is-${info.format}`;
      }
      if (proofEstimate) proofEstimate.textContent = `${info.title} proof ready`;
      loadDesignAssets();
    });
  });

  qsa('[data-design-field]').forEach((field) => {
    const sync = () => {
      const value = field.value.trim();
      if (field.dataset.designField === 'headline' && templateHeadline) templateHeadline.textContent = value || 'Give local. Claim instantly.';
      if (field.dataset.designField === 'offer' && templateOffer) templateOffer.textContent = value || 'Scan to unlock today’s featured microgift, local reward, or pre-sale offer.';
      if (field.dataset.designField === 'cta' && templateCta) templateCta.textContent = value || 'Scan to claim your reward';
    };
    field.addEventListener('input', sync);
    sync();
  });

  qsa('[data-media-swatch]').forEach((button) => {
    button.addEventListener('click', () => {
      setActive(button, '[data-media-swatch]');
      if (templateMedia) templateMedia.dataset.swatch = button.dataset.mediaSwatch || 'gradient';
    });
  });

  qsa('[data-qr-label]').forEach((button) => {
    button.addEventListener('click', () => selectQr(button, { id: button.dataset.qrId || '', label: button.dataset.qrLabel || 'Featured Gift', kind_label: button.dataset.qrKind || 'Claim QR', qr_payload_url: button.dataset.qrPayload || '' }));
  });

  qsa('[data-preview-side]').forEach((button) => {
    button.addEventListener('click', () => {
      setActive(button, '[data-preview-side]');
      const side = button.dataset.previewSide || 'front';
      app.dataset.previewSide = side;
      if (proofEstimate) proofEstimate.textContent = `${side === 'back' ? 'Back' : 'Front'} side preview`;
    });
  });

  const brandScanButton = qs('[data-brand-scan]');
  if (brandScanButton) brandScanButton.addEventListener('click', async () => {
    brandScanButton.disabled = true;
    brandScanButton.textContent = 'Scanning website…';
    try {
      await scanBrandKit();
      brandScanButton.textContent = 'Brand kit scanned';
      if (proofEstimate) proofEstimate.textContent = 'Brand kit ready';
    } catch (error) {
      brandScanButton.textContent = 'Scan failed';
      if (proofEstimate) proofEstimate.textContent = error.message || 'Unable to scan brand kit';
    } finally {
      setTimeout(() => { brandScanButton.disabled = false; brandScanButton.textContent = 'Scan website for brand kit'; }, 1800);
    }
  });

  const saveButton = qs('[data-design-save]');
  if (saveButton) saveButton.addEventListener('click', async () => {
    saveButton.disabled = true;
    saveButton.textContent = 'Saving project…';
    try {
      await saveProject();
      saveButton.textContent = 'Project saved';
      if (proofEstimate) proofEstimate.textContent = 'Project saved';
    } catch (error) {
      saveButton.textContent = 'Save failed';
      if (proofEstimate) proofEstimate.textContent = 'Unable to save project';
    } finally {
      setTimeout(() => { saveButton.disabled = false; saveButton.textContent = 'Save project'; }, 1400);
    }
  });

  const templateButton = qs('[data-design-save-template]');
  if (templateButton) templateButton.addEventListener('click', async () => {
    templateButton.disabled = true;
    templateButton.textContent = 'Saving template…';
    try {
      await saveTemplate();
      templateButton.textContent = 'Template saved';
      if (proofEstimate) proofEstimate.textContent = 'Reusable template saved';
    } catch (error) {
      templateButton.textContent = 'Save failed';
      if (proofEstimate) proofEstimate.textContent = 'Unable to save template';
    } finally {
      setTimeout(() => { templateButton.disabled = false; templateButton.textContent = 'Save as template'; }, 1500);
    }
  });

  const exportButton = qs('[data-design-export]');
  if (exportButton) exportButton.addEventListener('click', async () => {
    exportButton.disabled = true;
    exportButton.textContent = 'Queueing export…';
    try {
      const info = activeFormatInfo();
      await queueExport(info.template_type === 'social' ? 'social_png' : 'zip_package');
      exportButton.textContent = 'Export queued';
      if (proofEstimate) proofEstimate.textContent = app.dataset.selectedQrId ? 'Export queued with live QR' : 'Export queued; QR still recommended';
    } catch (error) {
      exportButton.textContent = 'Export blocked';
      if (proofEstimate) proofEstimate.textContent = 'Export queue failed';
    } finally {
      setTimeout(() => { exportButton.disabled = false; exportButton.textContent = 'Export package'; }, 1600);
    }
  });

  const proofButton = qs('[data-design-proof]');
  if (proofButton) proofButton.addEventListener('click', async () => {
    proofButton.disabled = true;
    proofButton.textContent = 'Queueing proof…';
    try {
      await queueExport('proof');
      if (proofEstimate) proofEstimate.textContent = app.dataset.selectedQrId ? 'Proof queued with live QR' : 'Proof queued; QR still recommended';
      proofButton.textContent = 'Proof queued';
    } catch (error) {
      proofButton.textContent = 'Proof failed';
      if (proofEstimate) proofEstimate.textContent = 'Unable to queue proof';
    } finally {
      setTimeout(() => { proofButton.disabled = false; proofButton.textContent = 'Generate proof'; }, 1500);
    }
  });

  const importButton = qs('[data-design-import-media]');
  if (importButton) importButton.addEventListener('click', () => {
    importButton.textContent = 'Media library sync pending';
    setTimeout(() => { importButton.textContent = 'Import from media library'; }, 1600);
  });

  const aiButton = qs('[data-design-ai-image]');
  if (aiButton) aiButton.addEventListener('click', async () => {
    aiButton.disabled = true;
    aiButton.textContent = 'Queueing AI job…';
    try {
      await saveProject();
      await queueAiImage();
      aiButton.textContent = 'AI job queued';
      if (proofEstimate) proofEstimate.textContent = 'AI image job queued for approval';
    } catch (error) {
      aiButton.textContent = 'AI queue failed';
      if (proofEstimate) proofEstimate.textContent = 'Unable to queue AI image';
    } finally {
      setTimeout(() => { aiButton.disabled = false; aiButton.textContent = 'Queue AI image concept'; }, 1600);
    }
  });

  const qrButton = qs('[data-design-create-qr]');
  if (qrButton) qrButton.addEventListener('click', async () => {
    if (app.dataset.merchantOnly !== 'true') return;
    qrButton.disabled = true;
    qrButton.textContent = 'Creating QR code…';
    try {
      const item = await createQr();
      if (item && proofEstimate) proofEstimate.textContent = 'Live QR code created';
    } catch (error) {
      if (proofEstimate) proofEstimate.textContent = 'Unable to create QR';
    } finally {
      qrButton.disabled = false;
      qrButton.textContent = 'Create new QR code';
    }
  });

  const qrAssetButton = qs('[data-design-qr-asset]');
  if (qrAssetButton) qrAssetButton.addEventListener('click', async () => {
    qrAssetButton.disabled = true;
    qrAssetButton.textContent = 'Queueing QR asset…';
    try {
      await createQrAsset();
      qrAssetButton.textContent = 'QR asset queued';
      if (proofEstimate) proofEstimate.textContent = 'QR image asset queued';
    } catch (error) {
      qrAssetButton.textContent = 'QR asset failed';
      if (proofEstimate) proofEstimate.textContent = 'Select or create a QR first';
    } finally {
      setTimeout(() => { qrAssetButton.disabled = false; qrAssetButton.textContent = 'Queue QR image asset'; }, 1600);
    }
  });

  const campaignButton = qs('[data-campaign-link]');
  if (campaignButton) campaignButton.addEventListener('click', async () => {
    campaignButton.disabled = true;
    campaignButton.textContent = 'Saving campaign link…';
    try {
      await linkCampaign();
      campaignButton.textContent = 'Campaign linked';
      if (proofEstimate) proofEstimate.textContent = 'Campaign link saved';
    } catch (error) {
      campaignButton.textContent = 'Link failed';
      if (proofEstimate) proofEstimate.textContent = 'Campaign reference required';
    } finally {
      setTimeout(() => { campaignButton.disabled = false; campaignButton.textContent = 'Save campaign link'; }, 1600);
    }
  });

  const fitButton = qs('[data-preview-fit]');
  if (fitButton) fitButton.addEventListener('click', () => {
    const stage = qs('.mg-design-canvas-stage');
    if (stage) stage.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
  });

  loadBrandKit();
  loadQrLibrary();
  loadDesignAssets();
})();
