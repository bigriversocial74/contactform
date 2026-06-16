<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
$required=['api/merchant/storefront.php','api/merchant/storefront-preview.php','api/storefront/profile.php','includes/merchant-storefront-view.php','assets/js/merchant-storefront.js','assets/css/merchant-storefront.css'];
foreach($required as $file){if(!is_file(dirname(__DIR__).'/'.$file)){fwrite(STDERR,"Missing {$file}\n");exit(1);}}
echo "Stage 5C storefront files present.\n";
