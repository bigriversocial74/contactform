<?php
declare(strict_types=1);

require_once __DIR__ . '/_list.php';

mg_require_method('GET');
$actor=mg_admin_mc_require_user();
mg_rate_limit('admin.merchant_catalog.queue','user:'.(int)$actor['id'],180,60);
try{$data=mg_admin_mc_list(mg_db(),$_GET);}catch(MgAdminMerchantCatalogException $error){mg_fail($error->getMessage(),$error->httpStatus());}catch(Throwable $error){mg_security_log('error','admin.merchant_catalog.queue_failed','Merchant catalog queue failed.',['exception_class'=>$error::class],(int)$actor['id']);mg_fail('Unable to load merchant catalog operations.',500);}
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($data,'Merchant catalog operations loaded.');
