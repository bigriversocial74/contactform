<?php
declare(strict_types=1);

require_once __DIR__ . '/pricing-packages.php';

/**
 * Render the canonical public pricing cards from the published package authority.
 *
 * @param array<string,mixed> $options
 */
function mg_render_public_pricing_cards(array $options = []): void
{
    $plans = mg_public_pricing_packages();
    $gridClass = trim((string)($options['grid_class'] ?? 'mg-price-grid'));
    $ariaLabel = trim((string)($options['aria_label'] ?? 'Microgifter pricing plans'));

    if ($gridClass === '') {
        $gridClass = 'mg-price-grid';
    }
    if ($ariaLabel === '') {
        $ariaLabel = 'Microgifter pricing plans';
    }
    ?>
    <div class="<?= mg_e($gridClass) ?>" aria-label="<?= mg_e($ariaLabel) ?>" data-public-pricing-cards>
      <?php foreach ($plans as $plan): ?>
        <article class="mg-price-card<?= !empty($plan['featured']) ? ' is-featured' : '' ?>" data-package-id="<?= mg_e((string)$plan['id']) ?>">
          <?php if (!empty($plan['featured'])): ?><div class="mg-price-popular">Most Popular</div><?php endif; ?>

          <header class="mg-price-card-head">
            <span class="mg-price-plan-index"><?= str_pad((string)max(1, (int)(($plan['sort_order'] ?? 10) / 10)), 2, '0', STR_PAD_LEFT) ?></span>
            <div>
              <h2><?= mg_e((string)$plan['name']) ?></h2>
              <p><?= mg_e((string)$plan['description']) ?></p>
            </div>
          </header>

          <div class="mg-price-amount"><strong><?= mg_e((string)$plan['price_label']) ?></strong><span><?= mg_e((string)$plan['billing_label']) ?></span></div>
          <a class="mg-price-plan-action" href="<?= mg_e((string)$plan['cta_href']) ?>"><?= mg_e((string)$plan['cta_label']) ?><span aria-hidden="true">→</span></a>

          <div class="mg-price-fit">
            <span>Best fit</span>
            <p><?= mg_e((string)$plan['fit']) ?></p>
          </div>

          <div class="mg-price-includes">
            <h3><?= mg_e((string)$plan['included_label']) ?></h3>
            <ul>
              <?php foreach (($plan['included_features'] ?? []) as $feature): ?>
                <li><span aria-hidden="true">✓</span><?= mg_e((string)$feature) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>

          <?php if (!empty($plan['excluded_features'])): ?>
            <div class="mg-price-upgrade">
              <span>Available in higher plans</span>
              <ul>
                <?php foreach (($plan['excluded_features'] ?? []) as $feature): ?><li><?= mg_e((string)$feature) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}
