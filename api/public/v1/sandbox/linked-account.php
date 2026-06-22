<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_public.php';

mg_require_method('POST');
$context = mg_public_context('distribution:rewards.issue');
mg_public_sandbox_only($context);
$pdo = $context['pdo'];
$input = mg_input();

$externalUserId = trim((string)($input['external_user_id'] ?? 'sandbox-user'));
if ($externalUserId === '' || mb_strlen($externalUserId) > 180) {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Invalid sandbox external user id.');
    mg_fail('A sandbox external_user_id up to 180 characters is required.', 422);
}

$linkedAccountId = mg_public_sandbox_linked_account_id($context, $externalUserId);
mg_public_log($pdo, $context, 201, 'sandbox_linked_account');
mg_ok([
    'sandbox' => true,
    'linked_account_id' => $linkedAccountId,
    'external_user_id' => $externalUserId,
    'status' => 'active',
], 'Sandbox linked account prepared.', 201);
