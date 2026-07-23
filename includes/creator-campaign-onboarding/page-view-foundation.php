<?php declare(strict_types=1); ?>
      <section id="onboarding-step-1" class="mg-onboarding-step-card">
        <header><i>1</i><div><span>Pilot enrollment</span><h2>Define the merchant launch</h2><p>Record the operator, support path, expected usage, target date, and pilot operating agreement.</p></div></header>
        <form method="post" class="mg-onboarding-form">
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="save_enrollment"><input type="hidden" name="return_step" value="1">
          <div class="mg-onboarding-grid-2">
            <label>Primary operator<select name="primary_operator" required><?php foreach ($team as $member): ?><option value="<?= mg_e((string)$member['public_id']) ?>"<?= (int)($onboarding['primary_operator_user_id'] ?? 0) === (int)$member['user_id'] ? ' selected' : '' ?>><?= mg_e((string)$member['display_name']) ?> · <?= mg_e((string)$member['role_key']) ?></option><?php endforeach; ?></select></label>
            <label>Support or escalation contact<input name="support_contact" maxlength="255" required value="<?= mg_e((string)($onboarding['support_contact'] ?? $pilot['support_contact'] ?? '')) ?>" placeholder="Name, email, phone, or support channel"></label>
            <label class="is-wide">Pilot goal<textarea name="pilot_goal" maxlength="1000" rows="3" required placeholder="What should the first Creator Campaign prove?"><?= mg_e((string)($onboarding['pilot_goal'] ?? '')) ?></textarea></label>
            <label>Expected campaign volume<input name="expected_campaign_volume" maxlength="120" required value="<?= mg_e((string)($onboarding['expected_campaign_volume'] ?? '')) ?>" placeholder="Example: 1 pilot, 10 Creators"></label>
            <label>Intended launch date<input type="date" name="intended_launch_date" required value="<?= mg_e((string)($onboarding['intended_launch_date'] ?? '')) ?>"></label>
          </div>
          <label class="mg-onboarding-consent"><input type="checkbox" name="pilot_boundaries_accepted" value="1" required<?= (string)$onboarding['status'] !== 'invited' ? ' checked' : '' ?>><span><strong>I accept the pilot operating boundaries.</strong><small>Campaign publication, Creator decisions, content decisions, earnings, payout records, and payment-provider actions remain separate explicit merchant actions.</small></span></label>
          <button type="submit">Save enrollment</button>
        </form>
      </section>

      <section id="onboarding-step-2" class="mg-onboarding-step-card">
        <header><i>2</i><div><span>Reusable defaults</span><h2>Business and campaign profile</h2><p>Save defaults that can guide future campaign setup without duplicating canonical campaign records.</p></div></header>
        <form method="post" class="mg-onboarding-form">
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="save_business"><input type="hidden" name="return_step" value="2">
          <div class="mg-onboarding-grid-2">
            <label>Business category<input name="business_category" required maxlength="100" value="<?= mg_e((string)($business['business_category'] ?? '')) ?>" placeholder="Restaurant, hospitality, artist, fitness…"></label>
            <label>Review turnaround, hours<input type="number" name="review_turnaround_hours" min="1" max="720" value="<?= (int)($business['review_turnaround_hours'] ?? 48) ?>"></label>
            <label class="is-wide">Brand description<textarea name="brand_description" required maxlength="2000" rows="4"><?= mg_e((string)($business['brand_description'] ?? '')) ?></textarea></label>
            <label class="is-wide">Target customer<textarea name="target_customer" required maxlength="1000" rows="3"><?= mg_e((string)($business['target_customer'] ?? '')) ?></textarea></label>
            <label>Service area<input name="service_area" maxlength="500" value="<?= mg_e((string)($business['service_area'] ?? '')) ?>" placeholder="Phoenix metro, national, online"></label>
            <label>Preferred Creator types<textarea name="preferred_creator_types" rows="3" placeholder="Food creators&#10;Local lifestyle&#10;Arizona travel"><?= mg_e($listText($business['preferred_creator_types'] ?? [])) ?></textarea></label>
            <label>Supported platforms<textarea name="platforms" rows="3" required placeholder="Instagram&#10;TikTok&#10;YouTube"><?= mg_e($listText($business['platforms'] ?? [])) ?></textarea></label>
            <label>Content restrictions<textarea name="content_restrictions" rows="3" placeholder="No health claims&#10;No competitor comparisons"><?= mg_e($listText($business['content_restrictions'] ?? [])) ?></textarea></label>
            <label class="is-wide">Required disclosures<textarea name="required_disclosures" rows="3" placeholder="#ad&#10;Paid partnership&#10;Offer terms apply"><?= mg_e($listText($business['required_disclosures'] ?? [])) ?></textarea></label>
          </div>
          <button type="submit">Save business defaults</button>
        </form>
      </section>

      <section id="onboarding-step-3" class="mg-onboarding-step-card">
        <header><i>3</i><div><span>Catalog readiness</span><h2>Products and offers</h2><p>Select the canonical products the pilot may promote. Readiness checks are calculated from the product, version, image, and PPPM claim records.</p></div></header>
        <form method="post" class="mg-onboarding-form">
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="save_products"><input type="hidden" name="return_step" value="3">
          <div class="mg-onboarding-product-grid">
            <?php if ($products === []): ?><div class="mg-onboarding-inline-empty">No catalog products are available. <a href="/merchant-products.php">Create a product first.</a></div><?php endif; ?>
            <?php foreach ($products as $product): ?>
              <label class="mg-onboarding-product<?= !empty($product['ready']) ? ' is-ready' : ' is-blocked' ?>">
                <input type="checkbox" name="product_ids[]" value="<?= mg_e((string)$product['public_id']) ?>"<?= in_array((string)$product['public_id'], $selectedProductIds, true) ? ' checked' : '' ?>>
                <span><strong><?= mg_e((string)($product['title'] ?? $product['slug'])) ?></strong><small><?= mg_e(strtoupper((string)$product['status'])) ?> · <?= mg_e($money((int)$product['unit_value_cents'], (string)$product['currency'])) ?></small></span>
                <ul>
                  <?php foreach (['published'=>'Published version','price'=>'Valid price','image'=>'Ready image','claim_rules'=>'Claim/redemption rules'] as $key=>$label): ?><li class="<?= !empty($product['checks'][$key]) ? 'is-pass' : 'is-fail' ?>"><?= !empty($product['checks'][$key]) ? '✓' : '!' ?> <?= mg_e($label) ?></li><?php endforeach; ?>
                </ul>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="mg-onboarding-actions"><a class="mg-btn mg-btn-soft" href="/merchant-products.php">Manage products</a><button type="submit">Save product selection</button></div>
        </form>
      </section>

