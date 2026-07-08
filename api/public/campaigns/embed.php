<?php
declare(strict_types=1);

require_once __DIR__ . '/_embed_cors.php';
mg_public_campaign_embed_cors();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_public_campaign_embed_cors();

function mg_campaign_embed_base_url(): string
{
    $base = rtrim((string)(defined('MG_APP_URL') ? MG_APP_URL : ''), '/');
    if ($base !== '') return $base;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function mg_campaign_embed_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}

function mg_campaign_embed_endpoint(string $type): string
{
    return '/api/public/campaigns/embed-submit.php';
}

function mg_campaign_embed_submit_label(string $type): string
{
    return match ($type) {
        'newsletter_signup' => 'Join and claim reward',
        'contest_giveaway' => 'Enter contest',
        'qr_reward_drop' => 'Claim QR reward',
        'referral_reward' => 'Join referral campaign',
        'birthday_vip' => 'Join birthday rewards',
        'agent_offer' => 'Add offer interest',
        default => 'Submit',
    };
}

function mg_campaign_embed_type_label(string $type): string
{
    return match ($type) {
        'newsletter_signup' => 'Newsletter signup',
        'contest_giveaway' => 'Contest / giveaway',
        'qr_reward_drop' => 'QR reward drop',
        'referral_reward' => 'Referral reward',
        'birthday_vip' => 'Birthday / VIP reward',
        'agent_offer' => 'Agent offer',
        default => 'Campaign',
    };
}

function mg_campaign_embed_value(array $campaign): string
{
    $type = (string)($campaign['value_type'] ?? '');
    $rewardType = (string)($campaign['reward_type'] ?? '');
    if (in_array($rewardType, ['audio_pack', 'media_pack'], true)) return $rewardType === 'audio_pack' ? 'Audio pack' : 'Media pack';
    if ($type === 'percent' && ($campaign['value_percent'] ?? null) !== null) return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '% reward';
    if (in_array($type, ['free_item', 'custom'], true) || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) return (string)($campaign['reward_template_title'] ?? 'Reward');
    $cents = (int)($campaign['value_amount_cents'] ?? 0);
    return $cents > 0 ? ((string)($campaign['currency'] ?? 'USD') . ' ' . number_format($cents / 100, 2) . ' value') : 'Reward';
}

function mg_campaign_embed_public_path(string $type): string
{
    return match ($type) {
        'newsletter_signup' => '/newsletter-signup.php',
        'contest_giveaway' => '/contest.php',
        'qr_reward_drop' => '/qr-reward.php',
        'referral_reward' => '/referral-reward.php',
        'birthday_vip' => '/birthday-vip.php',
        'agent_offer' => '/agent-offer.php',
        default => '/campaign.php',
    };
}

function mg_campaign_embed_public_url(array $campaign): string
{
    $base = mg_campaign_embed_base_url();
    $type = (string)($campaign['campaign_type'] ?? '');
    $path = mg_campaign_embed_public_path($type);
    if ($type === 'qr_reward_drop' && !empty($campaign['qr_code_token'])) {
        return $base . $path . '?token=' . rawurlencode((string)$campaign['qr_code_token']);
    }
    $ref = trim((string)($campaign['public_slug'] ?? '')) !== '' ? (string)$campaign['public_slug'] : (string)$campaign['public_id'];
    return $base . $path . '?campaign=' . rawurlencode($ref);
}

function mg_campaign_embed_origin(): ?string
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '' || filter_var($origin, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($origin);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) ? $origin : null;
}

mg_require_method('GET');
$ref = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
$token = trim((string)($_GET['token'] ?? $_GET['qr_token'] ?? ''));

if ($ref === '' && $token === '') {
    mg_fail('Campaign is required.', 422);
}
if (($ref !== '' && strlen($ref) > 180) || ($token !== '' && strlen($token) > 180)) {
    mg_fail('Campaign reference is invalid.', 422);
}

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT c.public_id,c.public_slug,c.qr_code_token,c.campaign_type,c.title,c.description,c.form_headline,c.form_description,c.success_message,c.status,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.rules_json,
            u.display_name merchant_user_display_name,u.full_name merchant_user_full_name,
            pp.slug merchant_profile_slug,pp.display_name merchant_profile_display_name,pp.headline merchant_profile_headline,pp.avatar_url merchant_profile_avatar_url,pp.location_label merchant_profile_location,
            rt.public_id reward_template_public_id,rt.title reward_template_title,rt.description reward_template_description,rt.reward_type,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,rt.redemption_instructions,rt.expiration_rule,rt.expiration_days,rt.expires_at reward_expires_at
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        LEFT JOIN users u ON u.id = c.merchant_user_id
        LEFT JOIN public_profiles pp ON pp.user_id = c.merchant_user_id AND pp.status = 'active' AND pp.visibility IN ('public','unlisted')
        WHERE c.status = 'active' AND ((? <> '' AND (c.public_id = ? OR c.public_slug = ?)) OR (? <> '' AND c.qr_code_token = ?))
        LIMIT 1");
    $stmt->execute([$ref, $ref, $ref, $token, $token]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Campaign is not available.', 404);

    $now = time();
    $available = true;
    $availabilityMessage = 'Campaign is available.';
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) {
        $available = false;
        $availabilityMessage = 'This campaign has not started yet.';
    }
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) {
        $available = false;
        $availabilityMessage = 'This campaign has ended.';
    }
    if (($campaign['quantity_limit'] ?? null) !== null && (int)($campaign['issued_count'] ?? 0) >= (int)$campaign['quantity_limit']) {
        $available = false;
        $availabilityMessage = 'This campaign reward limit has been reached.';
    }

    $campaignType = (string)$campaign['campaign_type'];
    $headline = trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title'];
    $description = trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Enter your information below to engage with this Microgifter campaign.');
    $merchantName = trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
    $merchantSlug = trim((string)($campaign['merchant_profile_slug'] ?? ''));
    $base = mg_campaign_embed_base_url();
    $publicUrl = mg_campaign_embed_public_url($campaign);
    $submitEndpoint = $base . mg_campaign_embed_endpoint($campaignType);
    $origin = mg_campaign_embed_origin();

    mg_ok([
        'campaign' => [
            'id' => (string)$campaign['public_id'],
            'slug' => $campaign['public_slug'] ?? null,
            'campaign_type' => $campaignType,
            'type_label' => mg_campaign_embed_type_label($campaignType),
            'title' => (string)$campaign['title'],
            'headline' => $headline,
            'description' => $description,
            'success_message' => trim((string)($campaign['success_message'] ?? '')) ?: 'Campaign response submitted.',
            'status' => (string)$campaign['status'],
            'available' => $available,
            'availability_message' => $availabilityMessage,
            'qr_token' => $campaignType === 'qr_reward_drop' ? ($campaign['qr_code_token'] ?? null) : null,
            'rules' => json_decode((string)($campaign['rules_json'] ?? ''), true) ?: [],
            'public_url' => $publicUrl,
            'submit_endpoint' => $submitEndpoint,
            'submit_label' => mg_campaign_embed_submit_label($campaignType),
        ],
        'merchant' => [
            'name' => $merchantName,
            'headline' => trim((string)($campaign['merchant_profile_headline'] ?? '')),
            'location' => trim((string)($campaign['merchant_profile_location'] ?? '')),
            'avatar_url' => mg_campaign_embed_safe_url($campaign['merchant_profile_avatar_url'] ?? null),
            'profile_url' => $merchantSlug !== '' ? $base . '/profile.php?slug=' . rawurlencode($merchantSlug) : null,
        ],
        'reward' => [
            'id' => $campaign['reward_template_public_id'] ?? null,
            'title' => trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Microgifter reward',
            'description' => trim((string)($campaign['reward_template_description'] ?? '')),
            'value_label' => mg_campaign_embed_value($campaign),
            'currency' => $campaign['currency'] ?? 'USD',
            'redemption_instructions' => trim((string)($campaign['redemption_instructions'] ?? '')),
            'expires_at' => $campaign['reward_expires_at'] ?? null,
        ],
        'embed' => [
            'version' => 2,
            'adopts_host_css' => true,
            'render_modes' => ['inline', 'button'],
            'request_origin' => $origin,
            'cors_mode' => 'public_no_credentials',
            'health' => [
                'active' => (string)$campaign['status'] === 'active',
                'available' => $available,
                'has_public_url' => $publicUrl !== '',
                'has_submit_endpoint' => $submitEndpoint !== '',
                'host_css_adoption' => true,
            ],
        ],
    ], 'Campaign embed loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'public.campaign_embed.unavailable', 'Unable to load campaign embed.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_fail('Campaign embed unavailable.', 500);
}
