<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/pwa-push.php';

if (!function_exists('mg_public_campaign_notification_uuid')) {
    function mg_public_campaign_notification_uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

if (!function_exists('mg_public_campaign_notification_has_column')) {
    function mg_public_campaign_notification_has_column(PDO $pdo, string $column): bool
    {
        static $columns = null;
        if ($columns === null) {
            try {
                $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                $stmt->execute(['notifications']);
                $columns = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
            } catch (Throwable) {
                $columns = [];
            }
        }
        return isset($columns[$column]);
    }
}

if (!function_exists('mg_public_campaign_create_notification')) {
    function mg_public_campaign_create_notification(PDO $pdo, int $userId, string $type, string $title, string $body, string $actionUrl = '', array $context = []): array
    {
        if ($userId < 1) return ['created' => false, 'reason' => 'missing_user'];
        try {
            $publicId = mg_public_campaign_notification_uuid();
            $hasContext = $context !== [] && mg_public_campaign_notification_has_column($pdo, 'context_json');
            if ($hasContext) {
                $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
                $stmt->execute([$publicId, $userId, $type, mb_substr($title, 0, 160), mb_substr($body, 0, 500), mb_substr($actionUrl, 0, 500), json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())');
                $stmt->execute([$publicId, $userId, $type, mb_substr($title, 0, 160), mb_substr($body, 0, 500), mb_substr($actionUrl, 0, 500)]);
            }
            $notificationId = (int)$pdo->lastInsertId();
            $pwa = ['queued' => 0, 'reason' => 'not_available'];
            if (function_exists('mg_pwa_push_queue_for_notification')) {
                $pwa = mg_pwa_push_queue_for_notification($pdo, $notificationId);
            }
            return ['created' => true, 'type' => $type, 'action_url' => $actionUrl, 'context_stored' => $hasContext, 'pwa_push' => $pwa];
        } catch (Throwable $error) {
            mg_security_log('warning', 'public.campaign.notification_failed', 'Unable to create campaign notification.', [
                'exception_class' => $error::class,
                'message' => $error->getMessage(),
                'type' => $type,
                'user_id' => $userId,
            ], $userId);
            return ['created' => false, 'reason' => 'notification_failed'];
        }
    }
}

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

if (!function_exists('mg_public_campaign_embed_notification_attr')) {
    function mg_public_campaign_embed_notification_attr(array $explicit = []): array
    {
        if ($explicit !== []) return $explicit;
        $ambient = $GLOBALS['embedAttribution'] ?? [];
        return is_array($ambient) ? $ambient : [];
    }
}

if (!function_exists('mg_public_campaign_embed_notification_is_attributed')) {
    function mg_public_campaign_embed_notification_is_attributed(array $attr): bool
    {
        return !empty($attr['website_embed']) || !empty($attr['origin_host']) || !empty($attr['page_url']) || !empty($attr['embed_mode']);
    }
}

if (!function_exists('mg_public_campaign_embed_notification_action_url')) {
    function mg_public_campaign_embed_notification_action_url(array $campaign, string $source, array $attr): string
    {
        $campaignRef = (string)($campaign['public_slug'] ?? $campaign['public_id'] ?? '');
        $params = ['days' => '7'];
        if ($campaignRef !== '') $params['campaign'] = $campaignRef;
        if (!empty($attr['origin_host'])) $params['origin_host'] = (string)$attr['origin_host'];
        if ($source !== '') $params['source'] = $source;
        return '/merchant-campaign-embed-leads.php?' . http_build_query($params);
    }
}

if (!function_exists('mg_public_campaign_embed_notification_body')) {
    function mg_public_campaign_embed_notification_body(string $displayName, string $campaignTitle, array $attr): string
    {
        $origin = trim((string)($attr['origin_host'] ?? '')) ?: 'your website';
        $page = trim((string)($attr['page_url'] ?? ''));
        $mode = trim((string)($attr['embed_mode'] ?? ''));
        $bits = [$displayName . ' submitted ' . $campaignTitle . ' from ' . $origin . '.'];
        if ($page !== '') $bits[] = 'Page: ' . $page;
        if ($mode !== '') $bits[] = 'Mode: ' . $mode;
        return implode(' ', $bits);
    }
}

if (!function_exists('mg_public_campaign_embed_notification_context')) {
    function mg_public_campaign_embed_notification_context(array $campaign, array $contact, string $email, string $name, string $phone, string $source, array $crm, array $attr): array
    {
        return array_filter([
            'campaign_embed_notification_v4_3' => true,
            'campaign_id' => (string)($campaign['public_id'] ?? ''),
            'campaign_slug' => (string)($campaign['public_slug'] ?? ''),
            'campaign_type' => (string)($campaign['campaign_type'] ?? ''),
            'campaign_title' => (string)($campaign['title'] ?? ''),
            'campaign_contact_id' => (string)($contact['public_id'] ?? ''),
            'crm_contact_id' => (string)($crm['contact_id'] ?? ''),
            'crm_event_id' => (string)($crm['event_id'] ?? ''),
            'lead_email' => $email,
            'lead_name' => $name,
            'lead_phone' => $phone,
            'source' => $source,
            'origin_host' => (string)($attr['origin_host'] ?? ''),
            'page_url' => (string)($attr['page_url'] ?? ''),
            'embed_mode' => (string)($attr['embed_mode'] ?? ''),
            'embed_source' => (string)($attr['embed_source'] ?? 'website_embed'),
        ], static fn($value) => $value !== '' && $value !== null);
    }
}

if (!function_exists('mg_public_campaign_notify_merchant_contact')) {
    function mg_public_campaign_notify_merchant_contact(PDO $pdo, array $campaign, array $contact, string $email, string $name = '', string $phone = '', string $source = '', array $crm = [], bool $isNewContact = true, array $embedAttribution = []): array
    {
        $merchantId = (int)($campaign['merchant_user_id'] ?? 0);
        if ($merchantId < 1) return ['created' => false, 'reason' => 'missing_merchant'];
        if (!$isNewContact) return ['created' => false, 'reason' => 'existing_contact'];
        $campaignType = (string)($campaign['campaign_type'] ?? 'campaign');
        $campaignPublicId = (string)($campaign['public_id'] ?? '');
        $contactPublicId = (string)($contact['public_id'] ?? '');
        $displayName = trim($name) !== '' ? trim($name) : $email;
        $campaignTitle = trim((string)($campaign['title'] ?? '')) ?: 'campaign';
        $embedAttribution = mg_public_campaign_embed_notification_attr($embedAttribution);
        if (mg_public_campaign_embed_notification_is_attributed($embedAttribution)) {
            $origin = trim((string)($embedAttribution['origin_host'] ?? '')) ?: 'your website';
            $type = 'merchant_campaign_website_embed_lead';
            $title = 'New website embed lead from ' . $origin;
            $body = mg_public_campaign_embed_notification_body($displayName, $campaignTitle, $embedAttribution);
            $actionUrl = mg_public_campaign_embed_notification_action_url($campaign, $source, $embedAttribution);
            $context = mg_public_campaign_embed_notification_context($campaign, $contact, $email, $name, $phone, $source, $crm, $embedAttribution);
            return mg_public_campaign_create_notification($pdo, $merchantId, $type, $title, $body, $actionUrl, $context);
        }
        $type = mg_public_campaign_merchant_notification_type($campaignType, $source);
        $title = mg_public_campaign_merchant_notification_title($campaignType);
        $body = mg_public_campaign_merchant_notification_body($campaignType, $displayName, $campaignTitle);
        $actionUrl = '/merchant-crm.php?campaign=' . rawurlencode($campaignPublicId) . '&contact=' . rawurlencode($contactPublicId);
        return mg_public_campaign_create_notification($pdo, $merchantId, $type, $title, $body, $actionUrl);
    }
}

if (!function_exists('mg_public_campaign_notify_merchant_lifecycle')) {
    function mg_public_campaign_notify_merchant_lifecycle(PDO $pdo, array $campaign, string $eventType): array
    {
        $merchantId = (int)($campaign['merchant_user_id'] ?? 0);
        if ($merchantId < 1) return ['created' => false, 'reason' => 'missing_merchant'];
        $campaignTitle = trim((string)($campaign['title'] ?? '')) ?: 'Campaign';
        if ($eventType === 'campaign.launched') {
            return mg_public_campaign_create_notification($pdo, $merchantId, 'merchant_campaign_launched', 'Campaign launched', $campaignTitle . ' is active and ready to collect demand.', '/merchant-campaigns.php#campaign-active');
        }
        if ($eventType === 'campaign.created') {
            return mg_public_campaign_create_notification($pdo, $merchantId, 'merchant_campaign_created', 'Campaign created', $campaignTitle . ' was created.', '/merchant-campaigns.php#campaign-drafts');
        }
        return ['created' => false, 'reason' => 'unsupported_event'];
    }
}
