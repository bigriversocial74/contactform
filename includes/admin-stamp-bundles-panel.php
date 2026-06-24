<?php
declare(strict_types=1);
$stampBundles = [
    ['id'=>'','bundle_key'=>'stamps_1000','label'=>'1,000 Stamps','stamps'=>1000,'price_cents'=>1500,'currency'=>'USD','status'=>'active','sort_order'=>10],
    ['id'=>'','bundle_key'=>'stamps_5000','label'=>'5,000 Stamps','stamps'=>5000,'price_cents'=>6500,'currency'=>'USD','status'=>'active','sort_order'=>20],
    ['id'=>'','bundle_key'=>'stamps_25000','label'=>'25,000 Stamps','stamps'=>25000,'price_cents'=>25000,'currency'=>'USD','status'=>'active','sort_order'=>30],
];
?>
<section class="mg-stamp-panel" data-admin-stamp-bundles>
  <header>
    <div>
      <span class="mg-eyebrow">Stamp bundle packages</span>
      <h2>Create and adjust bulk Stamp bundles</h2>
      <p>Use this tab to manage the packages merchants can purchase when they need extra send volume beyond their monthly included Stamps.</p>
    </div>
    <span class="mg-package-status">API backed</span>
  </header>
  <div class="mg-admin-package-review-grid">
    <article>
      <h3>Bundle editor</h3>
      <form class="mg-merchant-form" data-admin-stamp-bundle-form>
        <input type="hidden" name="bundle_id" value="">
        <label>Bundle key<input name="bundle_key" value="stamps_1000" maxlength="120" required></label>
        <label>Label<input name="label" value="1,000 Stamps" maxlength="190" required></label>
        <div class="mg-grid-2"><label>Stamps<input name="stamps" type="number" min="1" value="1000" required></label><label>Price cents<input name="price_cents" type="number" min="0" value="1500" required></label></div>
        <div class="mg-grid-2"><label>Currency<input name="currency" value="USD" maxlength="3" required></label><label>Status<select name="status"><option value="active">Active</option><option value="disabled">Disabled</option><option value="archived">Archived</option></select></label></div>
        <label>Sort order<input name="sort_order" type="number" value="10"></label>
        <div class="mg-form-status" data-admin-stamp-bundle-status>Ready to save a Stamp bundle.</div>
        <button class="mg-btn mg-btn-primary" type="submit">Save Stamp bundle</button>
      </form>
    </article>
    <article>
      <h3>Monthly allowance credit</h3>
      <form class="mg-merchant-form" data-admin-monthly-stamps-form>
        <label>Merchant account user ID<input name="account_user_id" type="number" min="1" placeholder="Merchant user ID" required></label>
        <label>Pricing plan<select name="plan_id"><option value="starter">Starter</option><option value="growth">Growth</option><option value="pro">Pro</option><option value="enterprise">Enterprise</option></select></label>
        <label>Override Stamps optional<input name="stamps" type="number" min="1" placeholder="Leave blank to use plan allowance"></label>
        <div class="mg-form-status" data-admin-monthly-stamps-status>Ready to credit monthly included Stamps.</div>
        <button class="mg-btn mg-btn-soft" type="submit">Credit monthly allowance</button>
      </form>
    </article>
  </div>
  <div class="mg-stamp-action-table-wrap" style="margin-top:16px">
    <table class="mg-stamp-table">
      <thead><tr><th>Bundle</th><th>Key</th><th>Stamps</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
      <tbody data-admin-stamp-bundle-list>
        <?php foreach ($stampBundles as $bundle): ?>
          <tr data-bundle-row data-bundle='<?= mg_e(json_encode($bundle, JSON_UNESCAPED_SLASHES)) ?>'>
            <td><strong><?= mg_e((string)$bundle['label']) ?></strong></td>
            <td><?= mg_e((string)$bundle['bundle_key']) ?></td>
            <td><?= number_format((int)$bundle['stamps']) ?></td>
            <td><?= mg_e((string)$bundle['currency']) ?> <?= number_format(((int)$bundle['price_cents'])/100,2) ?></td>
            <td><?= mg_e((string)$bundle['status']) ?></td>
            <td><button class="mg-btn mg-btn-soft" type="button" data-edit-bundle>Edit</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script src="/assets/js/admin-stamp-bundles.js" defer></script>
