<?php
declare(strict_types=1);

require_once __DIR__ . '/_detail.php';

mg_require_method('GET');
$actor=mg_admin_commerce_require_user();
mg_rate_limit('admin.commerce.inspect','user:'.(int)$actor['id'],240,60);
try{
    $data=mg_admin_commerce_detail(mg_db(),$actor,mg_admin_commerce_subject_type($_GET['type']??null),mg_admin_commerce_public_reference($_GET['reference']??null));
}catch(MgAdminCommerceException $error){
    mg_fail($error->getMessage(),$error->httpStatus());
}catch(Throwable $error){
    mg_security_log('error','admin.commerce.inspect_failed','Admin commerce detail failed.',['exception_class'=>$error::class],(int)$actor['id']);
    mg_fail('Unable to load commerce details.',500);
}
header('Cache-Control: private, no-store, max-age=0');
mg_ok($data,'Commerce details loaded.');
