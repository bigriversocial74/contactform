# Microgifter Cookie Consent Integration v1

## Purpose

Microgifter uses a default-deny consent manager for optional cookies and similar technologies. Strictly necessary platform services remain active. Functional, analytics, marketing, and external-media technologies must be explicitly categorized and must not load before consent.

## Categories

- `necessary` — authentication, security, CSRF protection, cart and checkout continuity, claims, redemption, transactions, requested account workflows, and consent storage.
- `functional` — optional convenience, interface, personalization, or remembered display choices.
- `analytics` — optional traffic, performance, product-use, engagement, and error measurement.
- `marketing` — optional advertising measurement, retargeting, cross-site promotion, and non-essential campaign attribution.
- `external_media` — optional video, audio, maps, social embeds, and third-party widgets.

Optional categories default to `false`.

## Deferred external scripts

Do not add an optional script with a normal executable `type` or `src`. Use an inert script element:

```html
<script
  type="text/plain"
  data-type="text/javascript"
  data-mg-consent="analytics"
  data-src="https://example.test/analytics.js"></script>
```

Inline optional code uses the same inert type:

```html
<script type="text/plain" data-mg-consent="analytics">
  window.exampleAnalytics && window.exampleAnalytics.start();
</script>
```

The consent runtime replaces the inert element with an executable script only when the selected category is active.

## Deferred external media

```html
<div data-mg-consent-placeholder="external_media">
  This media is blocked until external media is allowed.
  <button type="button" data-mg-cookie-settings>Review cookie settings</button>
</div>

<iframe
  title="Example video"
  hidden
  data-mg-consent="external_media"
  data-src="https://example.test/embed/video"></iframe>
```

The same `data-src` pattern is supported for images, sources, and other elements. Deferred links may use `data-href`.

## Runtime API

The global API is available as `window.MicrogifterConsent`:

```js
MicrogifterConsent.has('analytics');
MicrogifterConsent.open();
MicrogifterConsent.get();
MicrogifterConsent.save({
  functional: true,
  analytics: false,
  marketing: false,
  external_media: false
});
```

Events:

- `mg:consent-ready`
- `mg:consent-changed`

## Required review for new providers

Before adding an optional provider:

1. Identify the provider, purpose, data collected, recipients, duration, and category.
2. Confirm that the provider does not load before consent.
3. Add the provider or technology to `/cookies.php` when the inventory requires it.
4. Confirm that rejection leaves the core service usable.
5. Confirm that withdrawal removes known first-party optional identifiers and prevents the provider from loading after refresh.
6. Revisit the consent-policy version when purposes or categories materially change.

## Prohibited patterns

- Prechecked optional categories.
- Consent inferred from scrolling, inactivity, or continued browsing.
- Optional scripts loaded before an affirmative choice.
- An accept action that is substantially easier or more prominent than rejection.
- Hiding withdrawal controls.
- Treating analytics, advertising, retargeting, or third-party embeds as strictly necessary without documented legal and technical justification.

## Storage

The consent manager stores `mg_cookie_consent_v1` as a first-party cookie and a local-storage mirror for up to 180 days. The record contains an anonymous consent ID, policy version, timestamps, source, and category choices. It does not contain a name, email address, or merchant CRM data.
