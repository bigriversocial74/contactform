<?php
declare(strict_types=1);

require_once __DIR__ . '/public-product-foundation.php';

function mg_public_product_money(int $cents, string $currency): string
{
    $currency = strtoupper(substr(trim($currency), 0, 3));
    $amount = number_format(max(0, $cents) / 100, 2, '.', ',');
    return match ($currency) {
        'USD' => '$' . $amount,
        'CAD' => 'CA$' . $amount,
        'EUR' => '€' . $amount,
        'GBP' => '£' . $amount,
        default => $currency . ' ' . $amount,
    };
}

function mg_public_product_type_label(array $product): string
{
    return match ((string) ($product['builder_type'] ?? 'simple_product')) {
        'greeting_card' => 'Digital greeting card',
        'multimedia_greeting_card' => 'Multimedia greeting card',
        'simple_collab' => 'Collaborative reward',
        default => ucfirst(str_replace('_', ' ', (string) ($product['product_type'] ?? 'voucher'))),
    };
}

function mg_public_product_initial(string $value): string
{
    $value = trim($value);
    return $value !== '' ? strtoupper(mb_substr($value, 0, 1)) : 'M';
}

function mg_public_product_location_line(array $location): string
{
    $parts = array_values(array_filter([
        trim((string) ($location['address_line1'] ?? '')),
        trim(implode(', ', array_values(array_filter([
            trim((string) ($location['city'] ?? '')),
            trim((string) ($location['region'] ?? '')),
        ])))),
        trim((string) ($location['postal_code'] ?? '')),
    ], static fn(string $part): bool => $part !== ''));
    return implode(' · ', $parts);
}

function mg_public_product_render_merchant(array $product): void
{
    $merchant = is_array($product['merchant'] ?? null) ? $product['merchant'] : [];
    $name = trim((string) ($merchant['name'] ?? $product['merchant_name'] ?? 'Local merchant'));
    $headline = trim((string) ($merchant['headline'] ?? ''));
    $avatar = trim((string) ($merchant['avatar_url'] ?? ''));
    $profileUrl = trim((string) ($merchant['profile_url'] ?? ''));
    ?>
    <section class="mg-public-product-merchant" aria-label="Merchant">
      <?php if ($avatar !== ''): ?>
        <img class="mg-public-product-avatar" src="<?= mg_e($avatar) ?>" alt="<?= mg_e($name) ?>">
      <?php else: ?>
        <span class="mg-public-product-avatar is-initial" aria-hidden="true"><?= mg_e(mg_public_product_initial($name)) ?></span>
      <?php endif; ?>
      <div class="mg-public-product-merchant-copy">
        <span>Offered by</span>
        <strong><?= mg_e($name) ?></strong>
        <?php if ($headline !== ''): ?><small><?= mg_e($headline) ?></small><?php endif; ?>
      </div>
      <?php if ($profileUrl !== ''): ?>
        <a class="mg-public-product-profile-link" href="<?= mg_e($profileUrl) ?>">View profile</a>
      <?php endif; ?>
    </section>
    <?php
}

function mg_public_product_render_media(array $product): void
{
    $builderType = (string) ($product['builder_type'] ?? 'simple_product');
    $cover = mg_public_product_asset($product, 'cover') ?? mg_public_product_asset($product, 'thumbnail');
    $inside = mg_public_product_asset($product, 'inside_cover');
    $audio = mg_public_product_asset($product, 'audio');
    $video = mg_public_product_asset($product, 'video');
    $isGreeting = in_array($builderType, ['greeting_card', 'multimedia_greeting_card'], true);

    if (!$isGreeting) {
        ?>
        <div class="mg-public-product-media-frame">
          <?php if ($cover): ?>
            <img src="<?= mg_e((string) $cover['url']) ?>" alt="<?= mg_e((string) $product['title']) ?>" fetchpriority="high">
          <?php else: ?>
            <div class="mg-public-product-media-fallback" aria-hidden="true">MG</div>
          <?php endif; ?>
        </div>
        <?php
        return;
    }

    $message = mg_public_product_text((array) ($product['metadata'] ?? []), ['message']);
    if ($message === '') $message = trim((string) ($product['description'] ?? ''));
    ?>
    <div class="mg-public-greeting" data-public-greeting>
      <section class="mg-public-greeting-face is-cover" data-greeting-cover>
        <?php if ($cover): ?>
          <img src="<?= mg_e((string) $cover['url']) ?>" alt="<?= mg_e((string) $product['title']) ?>" fetchpriority="high">
        <?php else: ?>
          <div class="mg-public-product-media-fallback" aria-hidden="true">MG</div>
        <?php endif; ?>
        <div class="mg-public-greeting-overlay">
          <span><?= $builderType === 'multimedia_greeting_card' ? 'Includes a media message' : 'A digital gift card' ?></span>
          <strong><?= mg_e((string) $product['title']) ?></strong>
          <button type="button" data-greeting-open aria-expanded="false">Open card</button>
        </div>
      </section>
      <section class="mg-public-greeting-face is-inside" data-greeting-inside aria-hidden="true" tabindex="-1">
        <div class="mg-public-greeting-art">
          <?php if ($inside): ?>
            <img src="<?= mg_e((string) $inside['url']) ?>" alt="">
          <?php else: ?>
            <div class="mg-public-product-media-fallback" aria-hidden="true">✦</div>
          <?php endif; ?>
        </div>
        <div class="mg-public-greeting-message">
          <span>Inside message</span>
          <p><?= mg_e($message !== '' ? $message : 'A message will be included with this gift.') ?></p>
          <?php if ($video): ?>
            <video controls playsinline preload="metadata" src="<?= mg_e((string) $video['url']) ?>"></video>
          <?php elseif ($audio): ?>
            <audio controls preload="metadata" src="<?= mg_e((string) $audio['url']) ?>"></audio>
          <?php endif; ?>
          <button type="button" data-greeting-close>Close card</button>
        </div>
      </section>
    </div>
    <?php
}

function mg_public_product_render_locations(array $product): void
{
    $locations = is_array($product['locations'] ?? null) ? $product['locations'] : [];
    ?>
    <section class="mg-public-product-info-card" aria-labelledby="product-availability-title">
      <span class="mg-public-product-info-icon" aria-hidden="true">⌖</span>
      <div>
        <h2 id="product-availability-title">Where it can be used</h2>
        <?php if ($locations === []): ?>
          <p>Available through the merchant’s online Microgifter experience.</p>
        <?php else: ?>
          <ul class="mg-public-product-location-list">
            <?php foreach ($locations as $location): ?>
              <li>
                <strong><?= mg_e((string) ($location['name'] ?? 'Merchant location')) ?></strong>
                <?php $line = mg_public_product_location_line($location); ?>
                <?php if ($line !== ''): ?><span><?= mg_e($line) ?></span><?php endif; ?>
                <?php if (!empty($location['is_primary'])): ?><small>Primary location</small><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
    <?php
}

function mg_public_product_render(array $product): void
{
    $metadata = is_array($product['metadata'] ?? null) ? $product['metadata'] : [];
    $description = trim((string) ($product['description'] ?? ''));
    if ($description === '') $description = mg_public_product_text($metadata, ['description', 'message', 'headline']);
    $offer = mg_public_product_text($metadata, ['offer']);
    if ($offer === '') $offer = mg_public_product_text((array) ($product['offer'] ?? []), ['offer']);
    $expiration = mg_public_product_text((array) ($product['expiration_policy'] ?? []), ['label', 'description', 'type']);
    $terms = mg_public_product_text((array) ($product['terms'] ?? []), ['note', 'description', 'label']);
    $typeLabel = mg_public_product_type_label($product);
    $price = mg_public_product_money((int) ($product['unit_value_cents'] ?? 0), (string) ($product['currency'] ?? 'USD'));
    ?>
    <div class="mg-public-product-page" data-public-product-page data-product-version-id="<?= mg_e((string) $product['version_id']) ?>">
      <div class="mg-public-product-wrap">
        <nav class="mg-public-product-breadcrumb" aria-label="Breadcrumb">
          <a href="/discover.php">Discover</a><span aria-hidden="true">/</span><span><?= mg_e($typeLabel) ?></span>
        </nav>

        <article class="mg-public-product-hero">
          <div class="mg-public-product-media-column">
            <?php mg_public_product_render_media($product); ?>
          </div>
          <div class="mg-public-product-copy-column">
            <?php mg_public_product_render_merchant($product); ?>
            <p class="mg-public-product-eyebrow"><?= mg_e($typeLabel) ?></p>
            <h1><?= mg_e((string) $product['title']) ?></h1>
            <?php if ($description !== ''): ?><p class="mg-public-product-description"><?= nl2br(mg_e($description)) ?></p><?php endif; ?>
            <?php if ($offer !== ''): ?><p class="mg-public-product-offer"><?= mg_e($offer) ?></p><?php endif; ?>
            <div class="mg-public-product-value" aria-label="Product value"><?= mg_e($price) ?></div>
            <div class="mg-public-product-actions">
              <button class="mg-public-product-buy" type="button" data-cart-add data-product-version-id="<?= mg_e((string) $product['version_id']) ?>" data-cart-quantity="1">
                Add to cart
              </button>
            </div>
            <p class="mg-public-product-cart-status" data-product-cart-status role="status" aria-live="polite"></p>
            <div class="mg-public-product-trust" aria-label="Purchase information">
              <span>Secure checkout</span><span>Versioned product terms</span><span>Tracked delivery</span>
            </div>
          </div>
        </article>

        <section class="mg-public-product-info-grid" aria-label="Product information">
          <?php mg_public_product_render_locations($product); ?>
          <section class="mg-public-product-info-card" aria-labelledby="product-expiration-title">
            <span class="mg-public-product-info-icon" aria-hidden="true">◷</span>
            <div><h2 id="product-expiration-title">Expiration</h2><p><?= mg_e($expiration !== '' ? $expiration : 'No expiration policy was specified for this product.') ?></p></div>
          </section>
          <section class="mg-public-product-info-card" aria-labelledby="product-terms-title">
            <span class="mg-public-product-info-icon" aria-hidden="true">✓</span>
            <div><h2 id="product-terms-title">Terms</h2><p><?= mg_e($terms !== '' ? $terms : 'The merchant has not added additional product terms.') ?></p></div>
          </section>
        </section>
      </div>
    </div>
    <?php
}
