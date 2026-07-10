<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{$value=file_get_contents($root.'/'.$path);if(!is_string($value))throw new RuntimeException('Missing '.$path);return $value;};
$view=$read('includes/merchant-products-view.php');
$list=$read('api/merchant/products.php');
$detail=$read('api/merchant/product.php');
$archive=$read('api/merchant/product-archive.php');
$builder=$read('api/catalog/builder-draft.php');
$listPage=$read('merchant-products.php');
$detailPage=$read('merchant-product.php');
$js=$read('assets/js/merchant-catalog-management-v1.js');
$css=$read('assets/css/products-catalog-extra.css');
$checks=[
'functional_tabs'=>str_contains($view,'data-product-catalog-tab="published"')&&str_contains($view,'data-product-catalog-tab="draft"')&&str_contains($view,'data-product-catalog-tab="archived"')&&str_contains($js,'function setTab(value)')&&!str_contains($view,'>Inventory<'),
'bounded_catalog_query'=>str_contains($list,'min(50')&&str_contains($list,"ESCAPE '='")&&str_contains($list,'p.public_id LIKE ?')&&str_contains($list,"'pages'=>max(1")&&str_contains($js,'data-product-page="next"'),
'health_projection'=>str_contains($list,'needs_review')&&str_contains($view,'data-catalog-review-count')&&str_contains($view,'data-catalog-health-label')&&str_contains($js,'counts.needs_review'),
'scoped_controller'=>str_contains($listPage,'merchant-catalog-management-v1.js')&&str_contains($detailPage,'merchant-catalog-management-v1.js')&&!str_contains($listPage,'merchant-products.js')&&!str_contains($detailPage,'merchant-products.js'),
'upload_gating'=>str_contains($js,'pendingUploads')&&str_contains($js,'Wait for all media uploads to finish.')&&str_contains($js,'pendingUploads++')&&str_contains($js,'pendingUploads = Math.max(0, pendingUploads - 1)'),
'publish_parity'=>str_contains($js,'item.value_cents < 1')&&str_contains($js,"item.visibility !== 'public'")&&str_contains($builder,"visibility'] !== 'public'")&&str_contains($builder,'$valueCents < 1'),
'immutable_versions'=>str_contains($builder,"VALUES (?,?,?,'published'")&&str_contains($builder,"version_status='retired'")&&str_contains($builder,"current_version_id=?,product_type=?,slug=?,status='published'")&&str_contains($detail,'ORDER BY v.version_number DESC'),
'lock_and_archive_readonly'=>str_contains($builder,'lockVersion !== (int)$existing')&&str_contains($builder,'Archived products cannot be edited.')&&str_contains($js,"product.status === 'archived'")&&str_contains($js,'beforeunload'),
'archive_reconciliation'=>str_contains($archive,'catalog.products.manage')&&str_contains($archive,'mg_require_csrf_for_write')&&str_contains($archive,'merchant_storefront_revision_products')&&str_contains($archive,"revision_status='retired'")&&str_contains($archive,'catalog_pppm_templates')&&str_contains($archive,"feed_posts SET status='archived'")&&str_contains($archive,"catalog_products SET status='archived'"),
'accessibility_and_safe_dom'=>str_contains($view,'aria-live="polite"')&&str_contains($view,'aria-pressed="true"')&&str_contains($js,'document.createElement')&&str_contains($js,'replaceChildren')&&str_contains($css,'.mg-products-tabs button:focus-visible'),
];
$score=0;foreach($checks as $name=>$passed){echo($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if($passed)$score++;}echo 'Score: '.$score.'/10'.PHP_EOL;exit($score===10?0:1);
