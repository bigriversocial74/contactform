<?php
declare(strict_types=1);

require_once __DIR__ . '/_list.php';

mg_require_method('GET');
try {
    $domain = strtolower(trim((string)($_GET['domain'] ?? 'all')));
    $allowedDomains = ['all','order','refund','dispute','subscription','tip','microgift','case'];
    if (!in_array($domain, $allowedDomains, true)) {
        throw new MgAdminCommerceException('Invalid commerce domain filter.', 422);
    }
    $actor = mg_admin_commerce_require_domain_user($domain);
    mg_rate_limit('admin.commerce.queue','user:'.(int)$actor['id'],180,60);
    $data = mg_admin_commerce_list(mg_db(), $_GET);
} catch (MgAdminCommerceException $error) {
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    mg_security_log('error','admin.commerce.queue_failed','Admin commerce queue failed.',['exception_class'=>$error::class],isset($actor) ? (int)$actor['id'] : null);
    mg_fail('Unable to load commerce operations.',500);
}
header('Cache-Control: private, no-store, max-age=0');
mg_ok($data,'Commerce operations loaded.');
