<?php
/**
 * Microgifter mail helpers.
 *
 * Stage 1 rule: email generation is first-party and provider-agnostic.
 * HostGator can use PHP mail or log-only mode. SMTP/transactional providers can
 * be added later without changing auth/business endpoints.
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
        return $baseUrl;
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https://' : 'http://') . $host;
}

function mg_mail_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">If you did not request this reset, no action is needed.</p>';
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
    $logPayload = [
        'provider' => $provider,
        'enabled' => $enabled,
        'to' => $toEmail,
        'subject' => $subject,
    ] + $metadata;

    if (!$enabled || $provider === 'log') {
        error_log('[microgifter-mail] ' . json_encode($logPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return true;
    }

    if ($provider === 'mail') {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . mg_mail_from_name() . ' <' . mg_mail_from_email() . '>',
        ];
        $sent = mail($toEmail, $subject, $html, implode("\r\n", $headers));
        if (!$sent) {
            mg_security_log('error', 'mail.send_failed', 'PHP mail() failed.', $logPayload);
        }
        return $sent;
    }

    // SMTP/API providers are intentionally adapter points for later production hardening.
    error_log('[microgifter-mail-adapter-missing] ' . json_encode($logPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
        if (!function_exists('mg_db')) {
            require_once dirname(__DIR__) . '/api/db.php';
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $minutes = (int) mg_config_value('security', 'verify_token_minutes', 1440);
        $expiresAt = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $pdo = mg_db();
        $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$userId, $hash, $expiresAt]);
        return $token;
    } catch (Throwable $e) {
        mg_security_log('error', 'mail.verification_token_failed', 'Could not create email verification token.', ['exception' => $e->getMessage()], $userId);
        return null;
    }
}

function mg_queue_verification_email(int $userId, string $email, string $name = ''): void
{
    $token = mg_create_email_verification_token($userId);
    if (!$token) {
        return;
    }

    $url = mg_app_base_url() . '/verify-email.php?token=' . urlencode($token);
    mg_send_template_email($email, 'email_verification', [
        'name' => $name !== '' ? $name : $email,
        'url' => $url,
    ], ['user_id' => $userId]);
}
