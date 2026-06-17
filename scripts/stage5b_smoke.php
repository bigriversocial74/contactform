<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
$required=['api/merchant/products.php','api/merchant/product.php','api/merchant/assets.php','includes/merchant-products-view.php','includes/merchant-product-detail-view.php','includes/merchant-media-view.php','assets/js/merchant-products.js','assets/css/merchant-products.css'];
foreach($required as $file){$path=dirname(__DIR__).'/'.$file;if(!is_file($path)){fwrite(STDERR,"Missing {$file}\n");exit(1);}}
echo "Stage 5B product management files present.\n";
