<?php declare(strict_types=1); ?>
      <section id="onboarding-step-4" class="mg-onboarding-step-card">
        <header><i>4</i><div><span>Financial guardrails</span><h2>Compensation and maximum exposure</h2><p>Set reusable planning defaults. Native campaign compensation rules and budgets remain the authoritative financial records.</p></div></header>
        <form method="post" class="mg-onboarding-form" data-onboarding-financial-form>
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="save_financials"><input type="hidden" name="return_step" value="4">
          <div class="mg-onboarding-grid-3">
            <label>Currency<input name="currency" maxlength="3" value="<?= mg_e((string)($financials['currency'] ?? $workspace['default_currency'] ?? 'USD')) ?>"></label>
            <label>Flat fee per Creator<input name="flat_fee" inputmode="decimal" value="<?= mg_e($minorInput($financials['flat_fee_minor'] ?? 0)) ?>" data-financial-flat></label>
            <label>Attributed-sale commission, bps<input type="number" name="commission_bps" min="0" max="10000" value="<?= (int)($financials['commission_bps'] ?? 0) ?>"><small>1500 = 15%</small></label>
            <label>Campaign budget ceiling<input name="campaign_budget" required inputmode="decimal" value="<?= mg_e($minorInput($financials['campaign_budget_minor'] ?? 0)) ?>" data-financial-budget></label>
            <label>Per-Creator limit<input name="per_creator_limit" inputmode="decimal" value="<?= mg_e($minorInput($financials['per_creator_limit_minor'] ?? 0)) ?>" data-financial-per-creator></label>
            <label>Maximum Creators<input type="number" name="maximum_creators" min="1" max="100000" value="<?= (int)($financials['maximum_creators'] ?? 10) ?>" data-financial-creators></label>
            <label>Earning hold, days<input type="number" name="earning_hold_days" min="0" max="365" value="<?= (int)($financials['earning_hold_days'] ?? 7) ?>"></label>
            <label>Dispute window, days<input type="number" name="dispute_window_days" min="1" max="365" value="<?= (int)($financials['dispute_window_days'] ?? 30) ?>"></label>
            <label class="mg-onboarding-check"><input type="checkbox" name="product_compensation" value="1"<?= !empty($financials['product_compensation']) ? ' checked' : '' ?>><span>Products or experiences may be compensation</span></label>
            <label class="is-wide">Dispute and hold policy<textarea name="dispute_policy" required maxlength="1500" rows="3"><?= mg_e((string)($financials['dispute_policy'] ?? 'Merchant review is required before an earning can move to payout-record processing. Disputes pause the affected earning until resolved.')) ?></textarea></label>
          </div>
          <aside class="mg-onboarding-exposure" data-financial-preview>
            <span>Planning exposure preview</span>
            <strong><?= mg_e($money((int)($financialExposure['configured_campaign_ceiling_minor'] ?? 0), (string)($financialExposure['currency'] ?? 'USD'))) ?></strong>
            <p>All earnings remain subject to the native campaign budget, evidence, approval, hold, reversal, and dispute controls.</p>
          </aside>
          <button type="submit">Save financial guardrails</button>
        </form>
      </section>

      <section id="onboarding-step-5" class="mg-onboarding-step-card">
        <header><i>5</i><div><span>Creator fit</span><h2>Eligibility preferences</h2><p>Define reusable filters. These preferences do not approve a Creator automatically.</p></div></header>
        <form method="post" class="mg-onboarding-form">
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="save_eligibility"><input type="hidden" name="return_step" value="5">
          <div class="mg-onboarding-grid-2">
            <label>Participation method<select name="access_mode"><option value="open"<?= ($preferences['access_mode'] ?? '') === 'open' ? ' selected' : '' ?>>Applications</option><option value="invite_only"<?= ($preferences['access_mode'] ?? '') === 'invite_only' ? ' selected' : '' ?>>Invite only</option><option value="hybrid"<?= ($preferences['access_mode'] ?? 'hybrid') === 'hybrid' ? ' selected' : '' ?>>Applications and invitations</option></select></label>
            <label>Minimum profile completeness<input type="number" name="minimum_profile_completeness" min="0" max="100" value="<?= (int)($preferences['minimum_profile_completeness'] ?? 80) ?>"></label>
            <label>Locations<textarea name="locations" rows="3" placeholder="Phoenix&#10;Scottsdale"><?= mg_e($listText($preferences['locations'] ?? [])) ?></textarea></label>
            <label>Platforms<textarea name="platforms" rows="3" placeholder="Instagram&#10;TikTok"><?= mg_e($listText($preferences['platforms'] ?? [])) ?></textarea></label>
            <label>Creator categories<textarea name="categories" rows="3" placeholder="Food&#10;Travel&#10;Local lifestyle"><?= mg_e($listText($preferences['categories'] ?? [])) ?></textarea></label>
            <label>Competitor restrictions<textarea name="competitor_restrictions" rows="3"><?= mg_e($listText($preferences['competitor_restrictions'] ?? [])) ?></textarea></label>
            <label>Minimum audience<input type="number" name="minimum_audience" min="0" value="<?= (int)($preferences['minimum_audience'] ?? 0) ?>"></label>
            <label>Maximum audience<input type="number" name="maximum_audience" min="0" value="<?= (int)($preferences['maximum_audience'] ?? 0) ?>"></label>
          </div>
          <div class="mg-onboarding-fixed-boundaries"><strong>Fixed controls</strong><span>Approved Microgifter Creator profile required</span><span>Merchant decision required</span><span>No automatic outreach</span></div>
          <label class="mg-onboarding-check"><input type="checkbox" name="prior_campaign_history" value="1"<?= !empty($preferences['prior_campaign_history']) ? ' checked' : '' ?>><span>Prefer Creators with prior campaign history</span></label>
          <button type="submit">Save Creator preferences</button>
        </form>
      </section>

      <section id="onboarding-step-6" class="mg-onboarding-step-card">
        <header><i>6</i><div><span>Human ownership</span><h2>Operator and approval roles</h2><p>Assign responsibility without changing the underlying permission system.</p></div></header>
        <form method="post" class="mg-onboarding-form">
          <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="save_roles"><input type="hidden" name="return_step" value="6">
          <div class="mg-onboarding-grid-2">
            <?php foreach (MG_CREATOR_CAMPAIGN_ONBOARDING_ROLE_KEYS as $key=>$label): $savedMember = (string)($roles[$key]['member_public_id'] ?? 'owner'); ?>
              <label><?= mg_e($label) ?><select name="<?= mg_e($key) ?>"><?php foreach ($team as $member): ?><option value="<?= mg_e((string)$member['public_id']) ?>"<?= $savedMember === (string)$member['public_id'] ? ' selected' : '' ?>><?= mg_e((string)$member['display_name']) ?> · <?= mg_e((string)$member['role_key']) ?></option><?php endforeach; ?></select></label>
            <?php endforeach; ?>
          </div>
          <p class="mg-onboarding-note">These assignments document operational ownership. Native Microgifter permissions still determine who can perform each action.</p>
          <button type="submit">Save operator roles</button>
        </form>
      </section>

