(() => {
  'use strict';

  if (window.MicrogifterActionCenterContract) return;

  const VERSION = 2;

  function object(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  }

  function text(value, fallback) {
    const result = String(value == null ? '' : value).trim();
    return result || String(fallback == null ? '' : fallback);
  }

  function bool(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
  }

  function isContract(item) {
    return Number(item && item.contract_version) === VERSION
      && item.kind === 'action_center_gift'
      && !!object(item.gift).snapshot;
  }

  function capability(item, name) {
    if (isContract(item)) return bool(object(item.capabilities)[name]);
    const key = 'can_' + String(name || '').replace(/-/g, '_');
    return bool(item && item[key]);
  }

  function view(item) {
    item = object(item);
    if (!isContract(item)) {
      const legacy = Object.assign({}, item);
      legacy.can_send = bool(legacy.can_send);
      legacy.can_claim = bool(legacy.can_claim);
      legacy.can_redeem = bool(legacy.can_redeem);
      legacy.can_follow_up = bool(legacy.can_follow_up);
      legacy.can_message = bool(legacy.can_message);
      legacy.can_tip = bool(legacy.can_tip);
      legacy.can_load = legacy.can_load === undefined ? true : bool(legacy.can_load);
      legacy._contract = null;
      return legacy;
    }

    const gift = object(item.gift);
    const snapshot = object(gift.snapshot);
    const presentation = object(item.presentation);
    const linked = object(item.linked_resource);
    const source = object(item.source);
    const participants = object(item.participants);
    const sender = object(participants.sender);
    const recipient = object(participants.recipient);
    const merchant = object(item.merchant);
    const location = object(item.location);
    const redemption = object(item.redemption);
    const activity = object(item.activity);
    const capabilities = object(item.capabilities);
    const reasons = object(item.capability_reasons);
    const media = object(item.media);
    const flags = object(item.flags);

    return {
      contract_version: VERSION,
      action_item_id: text(item.action_item_id),
      folder: text(item.folder, 'inbox'),
      state: text(gift.state),
      instance_id: text(gift.id),
      instance_status: text(gift.status),
      template_id: text(gift.template_id),
      template_name: text(snapshot.title, 'Microgift'),
      title: text(snapshot.title, 'Microgift'),
      message: text(snapshot.description),
      description: text(snapshot.description),
      face_value_cents: Math.max(0, Number(snapshot.value_cents || 0)),
      currency: text(snapshot.currency, 'USD'),
      expires_at: snapshot.expires_at || null,
      product_type: text(linked.product_type, gift.template_type || 'gift'),
      product_id: text(linked.public_id),
      product_version_id: text(linked.version_id),
      product_title: text(linked.title),
      product_status: text(linked.status),
      product_is_public: bool(linked.is_public),
      product_version_basis: text(linked.version_basis),
      product_url: text(linked.url),
      product_image_url: text(presentation.image_url),
      image_source: text(presentation.image_source, 'none'),
      image_url: text(presentation.image_url),
      avatar_url: text(presentation.image_url, merchant.avatar_url),
      sender_name: text(sender.name),
      recipient_name: text(recipient.name),
      merchant_name: text(merchant.name, 'Microgifter'),
      business_name: text(merchant.name, 'Microgifter'),
      merchant_avatar_url: text(merchant.avatar_url),
      location_id: text(location.public_id),
      location_name: text(location.name),
      redemption_id: text(redemption.public_id),
      redemption_status: text(redemption.status),
      merchant_redeemed_at: redemption.redeemed_at || null,
      received_at: activity.received_at || null,
      first_received_at: activity.received_at || null,
      sent_at: activity.sent_at || null,
      claimed_at: activity.claimed_at || null,
      redeemed_at: activity.redeemed_at || null,
      updated_at: activity.updated_at || null,
      last_delivery_event_at: activity.last_delivery_at || null,
      resend_count: Math.max(0, Number(activity.resend_count || 0)),
      last_follow_up_at: activity.last_follow_up_at || null,
      follow_up_count: Math.max(0, Number(activity.follow_up_count || 0)),
      read_at: activity.read_at || null,
      source_system: text(source.system),
      source_type: text(source.type),
      source_label: text(source.label),
      source_detail: text(source.detail),
      source_reference: text(source.reference),
      can_send: bool(capabilities.send),
      can_claim: bool(capabilities.claim),
      can_redeem: bool(capabilities.redeem),
      can_follow_up: bool(capabilities.follow_up),
      can_message: bool(capabilities.message),
      can_tip: bool(capabilities.tip),
      can_load: bool(capabilities.load),
      capability_reasons: reasons,
      posts: Array.isArray(media.posts) ? media.posts.slice() : [],
      media_count: Math.max(0, Number(media.count || 0)),
      has_media: bool(media.has_media),
      is_wallet_reward: bool(flags.wallet_fallback),
      is_demo: bool(flags.demo_preview),
      is_system_demo: bool(flags.system_demo),
      _contract: item
    };
  }

  function mediaView(item) {
    item = object(item);
    const presentation = object(item.presentation);
    const media = object(item.media);
    const gift = object(item.gift);
    const snapshot = object(gift.snapshot);
    const assets = Array.isArray(media.assets) ? media.assets.slice() : [];
    return {
      contract_version: Number(item.contract_version || VERSION),
      action_item_id: text(item.action_item_id),
      title: text(snapshot.title, 'Gift'),
      description: text(snapshot.description),
      cover_url: text(presentation.image_url),
      media_assets: assets,
      media_count: Math.max(0, Number(media.count || assets.length || 0)),
      primary_media_kind: text(media.primary_kind, assets.length ? 'media' : 'none'),
      linked_resource: item.linked_resource || null
    };
  }

  function normalizePayload(payload) {
    payload = object(payload);
    const copy = Object.assign({}, payload);
    if (Array.isArray(payload.items)) copy.items = payload.items.map(view);
    return copy;
  }

  function normalizeResponse(response, path) {
    const wrapped = object(response);
    const hasDataEnvelope = wrapped.data && typeof wrapped.data === 'object';
    const payload = hasDataEnvelope ? wrapped.data : wrapped;
    let normalized = payload;

    if (/\/api\/account\/action-center\.php(?:\?|$)/.test(String(path || ''))) {
      normalized = normalizePayload(payload);
    }

    if (!hasDataEnvelope) return normalized;
    return Object.assign({}, wrapped, { data: normalized });
  }

  function installGetAdapter() {
    const client = window.Microgifter;
    if (!client || typeof client.get !== 'function') return false;
    if (client.__actionCenterContractV2Wrapped) return true;

    const originalGet = client.get;
    client.__actionCenterContractV2Wrapped = true;
    client.get = function (path) {
      const result = originalGet.apply(this, arguments);
      if (!/\/api\/account\/action-center\.php(?:\?|$)/.test(String(path || ''))) return result;
      return Promise.resolve(result).then((response) => normalizeResponse(response, path));
    };
    return true;
  }

  const api = Object.freeze({
    version: VERSION,
    isContract,
    capability,
    view,
    views(items) {
      return Array.isArray(items) ? items.map(view) : [];
    },
    mediaView,
    normalizePayload,
    normalizeResponse,
    install: installGetAdapter,
    image(item) {
      return text(view(item).product_image_url);
    }
  });

  window.MicrogifterActionCenterContract = api;

  let attempts = 0;
  function installSoon() {
    if (installGetAdapter()) return;
    attempts += 1;
    if (attempts < 40) window.setTimeout(installSoon, 25);
  }

  installSoon();
  document.addEventListener('DOMContentLoaded', installSoon, { once: true });
})();
