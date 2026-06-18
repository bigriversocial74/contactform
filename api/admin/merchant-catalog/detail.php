<?php
declare(strict_types=1);

require_once __DIR__ . '/_detail.php';

mg_require_method('GET');
$actor=mg_admin_mc_require_user();
mg_rate_limit('admin.merchant_catalog.detail','user:'.(int)$actor['id'],240,60);
try{
    $type=mg_admin_mc_subject_type($_GET['type']??null);
    $reference=mg_admin_mc_reference($_GET['reference']??null);
    $data=mg_admin_mc_detail(mg_db(),$actor,$type,$reference);
}catch(MgAdminMerchantCatalogException $error){
    mg_fail($error->getMessage(),$error->httpStatus());
}catch(Throwable $error){
    mg_security_log('error','admin.merchant_catalog.detail_failed','Merchant catalog detail failed.',['exception_class'=>$error::class],(int)$actor['id']);
    mg_fail('Unable to load merchant catalog details.',500);
}
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($data,'Merchant catalog details loaded.');
