<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
$required=['api/merchant/distribution-dashboard.php','api/merchant/distribution-program.php','api/merchant/distribution-eligibility.php','api/merchant/distribution-batch.php','includes/merchant-distribution-view.php','includes/merchant-distribution-program-view.php','assets/js/merchant-distribution.js','assets/css/merchant-distribution.css'];
foreach($required as $file){if(!is_file(dirname(__DIR__).'/'.$file)){fwrite(STDERR,"Missing {$file}\n");exit(1);}}
echo "Stage 5E merchant distribution files present.\n";
