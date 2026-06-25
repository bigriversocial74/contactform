<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/_email_suppression.php';

$token = trim((string)($_GET['token'] ?? (mg_input()['token'] ?? '')));
$decoded = $token !== '' ? mg_campaign_email_decode_token($token) : null;
if (!$decoded) {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><title>Invalid unsubscribe link</title><main style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;"><h1>Invalid unsubscribe link</h1><p>This unsubscribe link is invalid or expired.</p></main>';
    exit;
}
try {
    $result = mg_campaign_email_suppress(mg_db(), $decoded, 'unsubscribe_link');
    mg_security_log('info','campaign.email_unsubscribed','Campaign email unsubscribe recorded.',['scope'=>$result['scope'],'merchant_user_id'=>$decoded['merchant_user_id'],'campaign_id'=>$decoded['campaign_id'] ?? null]);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><title>Unsubscribed</title><main style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;"><h1>You are unsubscribed</h1><p>You will no longer receive this Microgifter campaign email type.</p></main>';
} catch (Throwable $error) {
    mg_security_log('error','campaign.email_unsubscribe_failed','Unable to unsubscribe campaign email.',['exception_class'=>$error::class]);
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><title>Unable to unsubscribe</title><main style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;"><h1>Unable to unsubscribe</h1><p>Please try again shortly.</p></main>';
}
