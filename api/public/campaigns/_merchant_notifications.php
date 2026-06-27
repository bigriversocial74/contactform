<?php
declare(strict_types=1);

if (!function_exists('mg_public_campaign_merchant_notification_type')) {
    function mg_public_campaign_merchant_notification_type(string $campaignType, string $source): string
    {
        return match ($campaignType) {
            'newsletter_signup' => 'merchant_campaign_newsletter_signup',
            'contest_giveaway' => 'merchant_campaign_contest_entry',
            'qr_reward_drop' => 'merchant_campaign_qr_pickup',
            'referral_reward' => 'merchant_campaign_referral_signup',
            'birthday_vip' => 'merchant_campaign_birthday_signup',
            'agent_offer' => 'merchant_campaign_agent_offer',
            default => 'merchant_campaign_engagement',
        };
    }
}

if (!function_exists('mg_public_campaign_merchant_notification_title')) {
    function mg_public_campaign_merchant_notification_title(string $campaignType): string
    {
        return match ($campaignType) {
            'newsletter_signup' => 'New newsletter signup',
            'contest_giveaway' => 'New contest entry',
            'qr_reward_drop' => 'New QR reward pickup',
            'referral_reward' => 'New referral campaign signup',
            'birthday_vip' => 'New birthday/VIP signup',
            'agent_offer' => 'New agent offer lead',
            default => 'New campaign engagement',
        };
    }
}

if (!function_exists('mg_public_campaign_merchant_notification_body')) {
    function mg_public_campaign_merchant_notification_body(string $campaignType, string $displayName, string $campaignTitle): string
    {
        return match ($campaignType) {
            'newsletter_signup' => $displayName . ' signed up for ' . $campaignTitle . '.',
            'contest_giveaway' => $displayName . ' entered ' . $campaignTitle . '.',
            'qr_reward_drop' => $displayName . ' picked up a QR reward from ' . $campaignTitle . '.',
            'referral_reward' => $displayName . ' joined referral campaign ' . $campaignTitle . '.',
            'birthday_vip' => $displayName . ' joined birthday/VIP campaign ' . $campaignTitle . '.',
            'agent_offer' => $displayName . ' requested an agent offer from ' . $campaignTitle . '.',
            default => $displayName . ' engaged with ' . $campaignTitle . '.',
        };
    }
}

if (!function_exists('mg_public_campaign_notify_merchant_contact')) {
    function mg_public_campaign_notify_merchant_contact(PDO $pdo, array $campaign, array $contact, string $email, string $name = '', string $phone = '', string $source = '', array $crm = [], bool $isNewContact = true): array
    {
        $merchantId = (int)($campaign['merchant_user_id'] ?? 0);
        if ($merchantId < 1) return ['created' => false, 'reason' => 'missing_merchant'];
        if (!$isNewContact) return ['created' => false, 'reason' => 'existing_contact'];

        $campaignType = (string)($campaign['campaign_type'] ?? 'campaign');
        $campaignPublicId = (string)($campaign['public_id'] ?? '');
        $contactPublicId = (string)($contact['public_id'] ?? '');
        $displayName = trim($name) !== '' ? trim($name) : $email;
        $campaignTitle = trim((string)($campaign['title'] ?? '')) ?: 'campaign';
        $type = mg_public_campaign_merchant_notification_type($campaignType, $source);
        $title = mg_public_campaign_merchant_notification_title($campaignType);
        $body = mg_public_campaign_merchant_notification_body($campaignType, $displayName, $campaignTitle);
        $actionUrl = '/merchant-crm.php?campaign=' . rawurlencode($campaignPublicId) . '&contact=' . rawurlencode($contactPublicId);

        try {
            $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())');
            $stmt->execute([mg_public_campaign_uuid(), $merchantId, $type, $title, $body, $actionUrl]);
            return ['created' => true, 'type' => $type, 'action_url' => $actionUrl];
        } catch (Throwable $error) {
            mg_security_log('warning', 'public.campaign.merchant_notification_failed', 'Unable to create merchant campaign notification.', [
                'exception_class' => $error::class,
                'message' => $error->getMessage(),
                'campaign_id' => $campaignPublicId,
                'campaign_type' => $campaignType,
                'contact_id' => $contactPublicId,
                'source' => $source,
                'crm' => $crm,
            ], $merchantId);
            return ['created' => false, 'reason' => 'notification_failed'];
        }
    }
}
