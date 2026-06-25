<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-products-heading">
  <div>
    <span class="mg-eyebrow">Catalog operations</span>
    <h1>Product management</h1>
    <p>Review drafts, publish products, and turn published offers into reusable reward templates for campaigns.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-ghost" href="/merchant-reward-templates.php">Reward templates</a>
    <a class="mg-btn mg-btn-ghost" href="/merchant-storefront.php">Manage storefront</a>
    <a class="mg-btn mg-btn-soft" href="/merchant-media.php">Media library</a>
    <a class="mg-btn mg-btn-primary" href="/build.php">Create product</a>
  </div>
</section>

<div class="mg-product-kpi-grid" data-product-kpis aria-live="polite"></div>

<section class="mg-app-panel mg-product-filter-panel">
  <div class="mg-app-panel-body">
    <form class="mg-product-toolbar" data-product-filters>
      <label class="mg-product-search">Search products
        <input type="search" data-product-search placeholder="Title, slug, or product ID" autocomplete="off">
      </label>
      <label>Status
        <select data-product-status>
          <option value="all">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </label>
      <label>Product type
        <select data-product-type><option value="all">All product types</option></select>
      </label>
      <label>Builder
        <select data-builder-type>
          <option value="all">All builder types</option>
          <option value="simple_product">Simple product</option>
          <option value="greeting_card">Greeting card</option>
          <option value="multimedia_greeting_card">Multimedia card</option>
          <option value="simple_collab">Simple collaboration</option>
        </select>
      </label>
      <label>Sort
        <select data-product-sort>
          <option value="updated_desc">Recently updated</option>
          <option value="updated_asc">Oldest updated</option>
          <option value="title_asc">Title A–Z</option>
          <option value="title_desc">Title Z–A</option>
          <option value="value_desc">Highest value</option>
          <option value="value_asc">Lowest value</option>
        </select>
      </label>
      <button class="mg-btn mg-btn-ghost" type="button" data-product-filters-reset>Reset filters</button>
    </form>
  </div>
</section>

<section class="mg-products-loading" data-products-loading>
  <div class="mg-product-row-skeleton"></div>
  <div class="mg-product-row-skeleton"></div>
  <div class="mg-product-row-skeleton"></div>
</section>

<section class="mg-app-panel mg-hidden" data-products-error role="alert">
  <div class="mg-app-panel-body mg-products-error">
    <div><strong>Products could not be loaded.</strong><p data-products-error-message>Try the request again.</p></div>
    <button class="mg-btn mg-btn-primary" type="button" data-products-retry>Retry</button>
  </div>
</section>

<section class="mg-app-panel mg-hidden" data-products-content>
  <div class="mg-product-list-header">
    <div><strong data-products-result-count>0 products</strong><span data-products-page-summary></span></div>
    <span>Published products can become reward templates for Promotional CRM campaigns.</span>
  </div>
  <div class="mg-product-list" data-product-list></div>
  <div class="mg-product-empty mg-hidden" data-products-empty>
    <strong>No products match these filters.</strong>
    <p>Change the filters or create a new product.</p>
    <a class="mg-btn mg-btn-primary" href="/build.php">Create product</a>
  </div>
  <nav class="mg-product-pagination" data-product-pagination aria-label="Product pages">
    <button class="mg-btn mg-btn-ghost" type="button" data-product-page="previous">Previous</button>
    <span data-product-page-label>Page 1 of 1</span>
    <button class="mg-btn mg-btn-ghost" type="button" data-product-page="next">Next</button>
  </nav>
</section>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var list=document.querySelector('[data-product-list]');
  if(!list)return;
  function addTemplateLinks(){
    list.querySelectorAll('.mg-product-row').forEach(function(row){
      if(row.querySelector('[data-product-template-link]'))return;
      var id=row.getAttribute('data-product-id');
      var actions=row.querySelector('.mg-product-actions');
      var publicLink=actions&&Array.from(actions.querySelectorAll('a')).find(function(a){return a.textContent.trim()==='Public page';});
      if(!id||!actions||!publicLink)return;
      var link=document.createElement('a');
      link.href='/merchant-reward-templates.php?source_product_id='+encodeURIComponent(id);
      link.textContent='Make reward template';
      link.setAttribute('data-product-template-link','');
      actions.insertBefore(link, publicLink);
    });
  }
  new MutationObserver(addTemplateLinks).observe(list,{childList:true,subtree:true});
  addTemplateLinks();
});
</script>
