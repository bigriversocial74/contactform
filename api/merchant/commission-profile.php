<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_commissions.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.payments.commissions.view');
$userId = (int)$user['id'];
$pdo = mg_db();
mg_rate_limit('merchant.commission_profile.read', 'user:' . $userId, 120, 60);

try {
    $pdo->beginTransaction();
    $profile = mg_commission_public_profile($pdo, $userId, true);
    $pdo->commit();
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok([
        'commission' => $profile,
        'merchant_editable' => false,
        'admin_managed' => true,
        'covered_services' => [
            'Payment processing','Digital delivery','Gift lifecycle and ownership management',
            'Claim and redemption tracking','CRM and campaign attribution','Basic messaging and reporting',
        ],
    ]);
} catch (MgCommissionException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.commission_profile_failed', 'Unable to load merchant commission profile.', ['exception_type' => get_class($error)], $userId);
    mg_fail('Unable to load commission terms.', 500);
}
