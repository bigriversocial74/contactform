<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
$required=['api/merchant/orders.php','api/merchant/pppm-items.php','api/merchant/pppm-item.php','api/merchant/pppm-note.php','includes/merchant-pppm-view.php','includes/merchant-pppm-item-view.php','assets/js/merchant-pppm.js','assets/css/merchant-pppm.css'];
foreach($required as $file){if(!is_file(dirname(__DIR__).'/'.$file)){fwrite(STDERR,"Missing {$file}\n");exit(1);}}
echo "Stage 5D merchant operations files present.\n";
