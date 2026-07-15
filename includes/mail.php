<?php
/**
 * Microgifter mail helpers.
 *
 * Authentication emails are first-party and provider-agnostic. Production must
 * configure MG_BASE_URL and a real mail provider; log mode is development-only.
 */
declare(strict_types=1);

function mg_mail_config(): array
{
    $config = mg_app_config();
    $mail = $config['mail'] ?? [];
    return is_array($mail) ? $mail : [];
}

function mg_mail_enabled(): bool
{
    return (bool) (mg_mail_config()['enabled'] ?? false);
}

function mg_mail_provider(): string
{
    return strtolower((string) (mg_mail_config()['provider'] ?? 'log'));
}

function mg_mail_from_email(): string
{
    return (string) (mg_mail_config()['from_email'] ?? 'no-reply@microgifter.com');
}

function mg_mail_from_name(): string
{
    return (string) (mg_mail_config()['from_name'] ?? 'Microgifter');
}

function mg_app_base_url(): string
{
    $baseUrl = rtrim((string) mg_config_value('app', 'base_url', ''), '/');
    if ($baseUrl !== '') {
        if (!preg_match('#^https?://[a-z0-9.-]+(?::\d+)?(?:/.*)?$#i', $baseUrl)) {
            throw new RuntimeException('Configured application base URL is invalid.');
        }
        return $baseUrl;
    }

    $environment = strtolower((string) mg_config_value('app', 'env', 'production'));
    if ($environment === 'production') {
        throw new RuntimeException('MG_BASE_URL is required in production.');
    }

    $https = function_exists('mg_is_https_request') ? mg_is_https_request() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    if (!preg_match('/^(?:localhost|[a-z0-9.-]+)(?::\d+)?$/', $host)) $host = 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}

function mg_mail_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mg_mail_header_value(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
}

function mg_email_layout(string $title, string $bodyHtml, string $previewText = ''): string
{
    $safeTitle = mg_mail_escape($title);
    $safePreview = mg_mail_escape($previewText);

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $safeTitle . '</title></head>'
        . '<body style="margin:0;background:#f4f7fb;color:#071225;font-family:Arial,sans-serif;">'
        . '<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">' . $safePreview . '</span>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:28px 0;">'
        . '<tr><td align="center"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #dbe6f5;border-radius:24px;overflow:hidden;box-shadow:0 24px 70px rgba(15,23,42,.10);">'
        . '<tr><td style="padding:28px 30px;border-bottom:1px solid #e5edf8;">'
        . '<div style="font-weight:900;font-size:22px;letter-spacing:-.04em;">⚡ Microgifter</div>'
        . '</td></tr><tr><td style="padding:30px;">'
        . '<h1 style="margin:0 0 14px;font-size:28px;line-height:1.08;letter-spacing:-.04em;">' . $safeTitle . '</h1>'
        . $bodyHtml
        . '</td></tr><tr><td style="padding:20px 30px;border-top:1px solid #e5edf8;color:#64748b;font-size:12px;line-height:1.5;">'
        . 'This message was sent by Microgifter. If you did not request this, you can ignore it.'
        . '</td></tr></table></td></tr></table></body></html>';
}

function mg_email_button(string $url, string $label): string
{
    return '<p style="margin:24px 0;"><a href="' . mg_mail_escape($url) . '" style="display:inline-block;background:#071225;color:#ffffff;text-decoration:none;border-radius:999px;padding:13px 20px;font-weight:800;">' . mg_mail_escape($label) . '</a></p>';
}

function mg_email_template(string $template, array $data = []): array
{
    $baseUrl = mg_app_base_url();
    $name = (string) ($data['name'] ?? 'there');

    if ($template === 'email_verification') {
        $url = (string) ($data['url'] ?? ($baseUrl . '/verify-email.php'));
        $body = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', confirm your email address so your Microgifter account is ready for secure gifting workflows.</p>'
            . mg_email_button($url, 'Verify email')
            . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">This verification link expires for your protection.</p>';
        return [
            'subject' => 'Verify your Microgifter email',
            'html' => mg_email_layout('Verify your email', $body, 'Confirm your Microgifter email address.'),
            'text' => "Hi {$name}, verify your Microgifter email: {$url}",
        ];
    }

    if ($template === 'password_reset') {
        $url = (string) ($data['url'] ?? ($baseUrl . '/reset-password.php'));
        $body = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', use the secure link below to reset your Microgifter password.</p>'
            . mg_email_button($url, 'Reset password')
            . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">This link expires soon. If you did not request this reset, no action is needed.</p>';
        return [
            'subject' => 'Reset your Microgifter password',
            'html' => mg_email_layout('Reset your password', $body, 'Reset your Microgifter password.'),
            'text' => "Hi {$name}, reset your Microgifter password: {$url}",
        ];
    }

    if ($template === 'security_alert') {
        $event = (string) ($data['event'] ?? 'Security activity');
        $body = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', we noticed security activity on your Microgifter account.</p>'
            . '<p style="margin:0 0 16px;color:#071225;font-size:16px;line-height:1.6;font-weight:800;">' . mg_mail_escape($event) . '</p>'
            . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">If this was not you, sign in and review your sessions.</p>';
        return [
            'subject' => 'Microgifter security alert',
            'html' => mg_email_layout('Security alert', $body, 'Security activity on your Microgifter account.'),
            'text' => "Hi {$name}, security activity: {$event}",
        ];
    }

    $body = '<p style="margin:0;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', welcome to Microgifter.</p>';
    return [
        'subject' => 'Welcome to Microgifter',
        'html' => mg_email_layout('Welcome to Microgifter', $body, 'Welcome to Microgifter.'),
        'text' => "Hi {$name}, welcome to Microgifter.",
    ];
}

function mg_send_email(string $toEmail, string $subject, string $html, ?string $text = null, array $metadata = []): bool
{
    $toEmail = trim($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        mg_security_log('warning', 'mail.invalid_recipient', 'Invalid email recipient.', ['to' => $toEmail] + $metadata);
        return false;
    }

    $provider = mg_mail_provider();
    $enabled = mg_mail_enabled();
    $environment = strtolower((string) mg_config_value('app', 'env', 'production'));
    $logPayload = [
        'provider' => $provider,
        'enabled' => $enabled,
        'to' => $toEmail,
        'subject' => $subject,
    ] + $metadata;

    if (!$enabled || $provider === 'log') {
        error_log('[microgifter-mail] ' . json_encode($logPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if ($environment === 'production') {
            mg_security_log('critical', 'mail.production_not_configured', 'Production mail delivery is not configured.', $logPayload);
            return false;
        }
        return true;
    }

    if ($provider === 'mail') {
        $fromEmail = mg_mail_header_value(mg_mail_from_email());
        $fromName = mg_mail_header_value(mg_mail_from_name());
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            mg_security_log('critical', 'mail.invalid_from_address', 'Configured mail sender is invalid.', ['from' => $fromEmail]);
            return false;
        }
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];
        $sent = mail($toEmail, mg_mail_header_value($subject), $html, implode("\r\n", $headers));
        if (!$sent) mg_security_log('error', 'mail.send_failed', 'PHP mail() failed.', $logPayload);
        return $sent;
    }

    error_log('[microgifter-mail-adapter-missing] ' . json_encode($logPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    mg_security_log('critical', 'mail.adapter_missing', 'Configured mail provider has no adapter.', $logPayload);
    return false;
}

function mg_send_template_email(string $toEmail, string $template, array $data = [], array $metadata = []): bool
{
    $rendered = mg_email_template($template, $data);
    return mg_send_email($toEmail, $rendered['subject'], $rendered['html'], $rendered['text'] ?? null, ['template' => $template] + $metadata);
}

function mg_create_email_verification_token(int $userId): ?string
{
    try {
        if (!function_exists('mg_db')) require_once dirname(__DIR__) . '/api/db.php';
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $minutes = max(10, (int) mg_config_value('security', 'verify_token_minutes', 1440));
        $expiresAt = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $pdo = mg_db();
        $pdo->prepare('UPDATE email_verification_tokens SET used_at=COALESCE(used_at,NOW()) WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
        $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$userId, $hash, $expiresAt]);
        return $token;
    } catch (Throwable $e) {
        mg_security_log('error', 'mail.verification_token_failed', 'Could not create email verification token.', ['exception_class' => $e::class], $userId);
        return null;
    }
}

function mg_queue_verification_email(int $userId, string $email, string $name = ''): bool
{
    $token = mg_create_email_verification_token($userId);
    if (!$token) return false;
    try {
        $url = mg_app_base_url() . '/verify-email.php?token=' . urlencode($token);
        return mg_send_template_email($email, 'email_verification', [
            'name' => $name !== '' ? $name : $email,
            'url' => $url,
        ], ['user_id' => $userId]);
    } catch (Throwable $e) {
        mg_security_log('critical', 'mail.verification_delivery_failed', 'Verification email could not be delivered.', ['exception_class' => $e::class], $userId);
        return false;
    }
}

function mg_send_password_reset_email(int $userId, string $email, string $name, string $token): bool
{
    try {
        $url = mg_app_base_url() . '/reset-password.php?token=' . urlencode($token);
        return mg_send_template_email($email, 'password_reset', [
            'name' => $name !== '' ? $name : $email,
            'url' => $url,
        ], ['user_id' => $userId]);
    } catch (Throwable $e) {
        mg_security_log('critical', 'mail.password_reset_delivery_failed', 'Password reset email could not be delivered.', ['exception_class' => $e::class], $userId);
        return false;
    }
}
