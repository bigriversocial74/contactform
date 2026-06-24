<?php
declare(strict_types=1);

require_once __DIR__ . '/_detail.php';

mg_require_method('GET');
try{
    $type = mg_admin_commerce_subject_type($_GET['type'] ?? null);
    $reference = mg_admin_commerce_public_reference($_GET['reference'] ?? null);
    $actor = mg_admin_commerce_require_domain_user($type);
    mg_rate_limit('admin.commerce.inspect','user:'.(int)$actor['id'],240,60);
    $data=mg_admin_commerce_detail(mg_db(),$actor,$type,$reference);
}catch(MgAdminCommerceException $error){
    mg_fail($error->getMessage(),$error->httpStatus());
}catch(Throwable $error){
    mg_security_log('error','admin.commerce.inspect_failed','Admin commerce detail failed.',['exception_class'=>$error::class],isset($actor) ? (int)$actor['id'] : null);
    mg_fail('Unable to load commerce details.',500);
}
header('Cache-Control: private, no-store, max-age=0');
mg_ok($data,'Commerce details loaded.');
