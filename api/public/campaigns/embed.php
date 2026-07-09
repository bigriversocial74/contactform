<?php
declare(strict_types=1);

require_once __DIR__ . '/_embed_cors.php';
mg_public_campaign_embed_cors();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';
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

function mg_campaign_embed_definition(string $type): array
{
    return mg_campaign_type_get($type) ?? [];
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
        'watch_video_reward' => 'Open Watch Video Reward',
        'listen_music_reward' => 'Open Listen Music Reward',
        default => 'Submit',
    };
}

function mg_campaign_embed_type_label(string $type): string
{
    $definition = mg_campaign_embed_definition($type);
    return (string)($definition['label'] ?? mg_campaign_type_label($type));
}

function mg_campaign_embed_layout(mixed $value): string
{
    $layout = strtolower(trim((string)$value));
    return in_array($layout, ['inline', 'button', 'compact'], true) ? $layout : 'inline';
}

function mg_campaign_embed_domains(mixed $value): array
{
    $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;
    return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
}

function mg_campaign_embed_settings(PDO $pdo, int $campaignId): array
{
    $defaults = ['embed_enabled' => true, 'default_layout' => 'inline', 'custom_button_text' => null, 'custom_success_message' => null, 'allowed_domains' => []];
    try {
        $stmt = $pdo->prepare('SELECT embed_enabled, default_layout, custom_button_text, custom_success_message, allowed_domains_json FROM campaign_embed_settings WHERE campaign_id = ? LIMIT 1');
        $stmt->execute([$campaignId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $defaults;
        return [
            'embed_enabled' => (bool)((int)$row['embed_enabled']),
            'default_layout' => mg_campaign_embed_layout($row['default_layout'] ?? 'inline'),
            'custom_button_text' => $row['custom_button_text'] ?: null,
            'custom_success_message' => $row['custom_success_message'] ?: null,
            'allowed_domains' => mg_campaign_embed_domains($row['allowed_domains_json'] ?? null),
        ];
    } catch (Throwable $error) {
        mg_security_log('warning', 'public.campaign_embed.settings_missing', 'Campaign embed settings unavailable; using defaults.', ['exception_class' => $error::class]);
        return $defaults;
    }
}

function mg_campaign_embed_request_origin_host(): ?string
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '' || filter_var($origin, FILTER_VALIDATE_URL) === false) return null;
    $host = parse_url($origin, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? strtolower(preg_replace('/^www\./', '', $host) ?: $host) : null;
}

function mg_campaign_embed_domain_allowed(array $settings): bool
{
    $domains = is_array($settings['allowed_domains'] ?? null) ? $settings['allowed_domains'] : [];
    if (!$domains) return true;
    $host = mg_campaign_embed_request_origin_host();
    if ($host === null) return true;
    foreach ($domains as $domain) {
        $domain = strtolower(preg_replace('/^www\./', '', (string)$domain) ?: (string)$domain);
        if ($host === $domain || str_ends_with($host, '.' . $domain)) return true;
    }
    return false;
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
    $definition = mg_campaign_embed_definition($type);
    return (string)($definition['public_path'] ?? '/campaign.php');
}

function mg_campaign_embed_public_url(array $campaign): string
{
    $base = mg_campaign_embed_base_url();
    $type = (string)($campaign['campaign_type'] ?? '');
    $path = mg_campaign_embed_public_path($type);
    if ($path === '') $path = '/campaign.php';
    if ($type === 'qr_reward_drop' && !empty($campaign['qr_code_token'])) return $base . $path . '?token=' . rawurlencode((string)$campaign['qr_code_token']);
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

function mg_campaign_embed_rules(mixed $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_embed_milestone_summary(array $milestones): array
{
    $items = [];
    foreach ($milestones as $milestone) {
        if (!is_array($milestone)) continue;
        $percent = (int)($milestone['percent'] ?? 0);
        if ($percent <= 0) continue;
        $items[] = [
            'percent' => $percent,
            'label' => (string)($milestone['label'] ?? ($percent . '% milestone')),
        ];
    }
    return $items;
}

function mg_campaign_embed_media(string $type, array $rules, string $publicUrl): ?array
{
    if ($type === 'watch_video_reward') {
        $provider = in_array((string)($rules['video_provider'] ?? 'youtube'), ['youtube', 'uploaded'], true) ? (string)$rules['video_provider'] : 'youtube';
        return [
            'kind' => 'watch_video_reward',
            'provider' => $provider,
            'provider_label' => $provider === 'uploaded' ? 'Uploaded video' : 'YouTube video',
            'youtube_video_id' => $provider === 'youtube' ? (string)($rules['youtube_video_id'] ?? '') : '',
            'uploaded_video_url' => $provider === 'uploaded' ? mg_campaign_embed_safe_url($rules['uploaded_video_url'] ?? null, true) : null,
            'required_percent' => (int)($rules['required_percent'] ?? 80),
            'milestones' => mg_campaign_embed_milestone_summary(is_array($rules['milestones'] ?? null) ? $rules['milestones'] : []),
            'embed_behavior' => 'open_full_media_page',
            'cta_url' => $publicUrl,
            'cta_label' => 'Open Watch Video Reward',
            'note' => 'Watch progress and milestone rewards run on the full Microgifter media reward page so Inbox delivery and PPPM tracking stay accurate.',
        ];
    }
    if ($type === 'listen_music_reward') {
        $provider = in_array((string)($rules['audio_provider'] ?? 'spotify'), ['spotify', 'uploaded'], true) ? (string)$rules['audio_provider'] : 'spotify';
        return [
            'kind' => 'listen_music_reward',
            'provider' => $provider,
            'provider_label' => $provider === 'uploaded' ? 'Uploaded audio' : 'Spotify listen intent',
            'spotify_track_id' => $provider === 'spotify' ? (string)($rules['spotify_track_id'] ?? '') : '',
            'uploaded_audio_url' => $provider === 'uploaded' ? mg_campaign_embed_safe_url($rules['uploaded_audio_url'] ?? null, true) : null,
            'track_title' => (string)($rules['track_title'] ?? ''),
            'artist_name' => (string)($rules['artist_name'] ?? ''),
            'required_percent' => (int)($rules['required_percent'] ?? 80),
            'milestones' => mg_campaign_embed_milestone_summary(is_array($rules['milestones'] ?? null) ? $rules['milestones'] : []),
            'embed_behavior' => 'open_full_media_page',
            'cta_url' => $publicUrl,
            'cta_label' => 'Open Listen Music Reward',
            'note' => 'Spotify uses listen-intent confirmation. Uploaded audio progress and milestone rewards run on the full Microgifter media reward page so Inbox delivery and PPPM tracking stay accurate.',
        ];
    }
    return null;
}

mg_require_method('GET');
$ref = strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
$token = trim((string)($_GET['token'] ?? $_GET['qr_token'] ?? ''));
if ($ref === '' && $token === '') mg_fail('Campaign is required.', 422);
if (($ref !== '' && strlen($ref) > 180) || ($token !== '' && strlen($token) > 180)) mg_fail('Campaign reference is invalid.', 422);

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT c.id campaign_db_id,c.public_id,c.public_slug,c.qr_code_token,c.campaign_type,c.title,c.description,c.form_headline,c.form_description,c.success_message,c.status,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.rules_json,
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

    $campaignType = (string)$campaign['campaign_type'];
    $definition = mg_campaign_embed_definition($campaignType);
    if (empty($definition['public_enabled']) || empty($definition['embed_allowed']) || trim((string)($definition['public_path'] ?? '')) === '') {
        mg_fail('Campaign embed is not available for this campaign type.', 404);
    }

    $settings = mg_campaign_embed_settings($pdo, (int)$campaign['campaign_db_id']);
    if (!$settings['embed_enabled']) mg_fail('Campaign embed is disabled by the merchant.', 403);
    if (!mg_campaign_embed_domain_allowed($settings)) mg_fail('This campaign embed is not enabled for this website domain.', 403);

    $now = time();
    $available = true;
    $availabilityMessage = 'Campaign is available.';
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) { $available = false; $availabilityMessage = 'This campaign has not started yet.'; }
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) { $available = false; $availabilityMessage = 'This campaign has ended.'; }
    if (($campaign['quantity_limit'] ?? null) !== null && (int)($campaign['issued_count'] ?? 0) >= (int)$campaign['quantity_limit']) { $available = false; $availabilityMessage = 'This campaign reward limit has been reached.'; }

    $rules = mg_campaign_embed_rules($campaign['rules_json'] ?? null);
    $headline = trim((string)($campaign['form_headline'] ?? '')) ?: (string)$campaign['title'];
    $description = trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Enter your information below to engage with this Microgifter campaign.');
    $merchantName = trim((string)($campaign['merchant_profile_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_display_name'] ?? '')) ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
    $merchantSlug = trim((string)($campaign['merchant_profile_slug'] ?? ''));
    $base = mg_campaign_embed_base_url();
    $publicUrl = mg_campaign_embed_public_url($campaign);
    $submitEndpoint = $base . mg_campaign_embed_endpoint($campaignType);
    $origin = mg_campaign_embed_origin();
    $successMessage = $settings['custom_success_message'] ?: (trim((string)($campaign['success_message'] ?? '')) ?: 'Campaign response submitted.');
    $media = mg_campaign_embed_media($campaignType, $rules, $publicUrl);

    mg_ok([
        'campaign' => [
            'id' => (string)$campaign['public_id'],
            'slug' => $campaign['public_slug'] ?? null,
            'campaign_type' => $campaignType,
            'type_label' => mg_campaign_embed_type_label($campaignType),
            'title' => (string)$campaign['title'],
            'headline' => $headline,
            'description' => $description,
            'success_message' => $successMessage,
            'status' => (string)$campaign['status'],
            'available' => $available,
            'availability_message' => $availabilityMessage,
            'qr_token' => $campaignType === 'qr_reward_drop' ? ($campaign['qr_code_token'] ?? null) : null,
            'rules' => $rules,
            'media' => $media,
            'media_embed_mode' => $media ? 'open_full_media_page' : null,
            'public_url' => $publicUrl,
            'submit_endpoint' => $submitEndpoint,
            'submit_label' => $settings['custom_button_text'] ?: mg_campaign_embed_submit_label($campaignType),
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
            'version' => 4,
            'adopts_host_css' => true,
            'render_modes' => ['inline', 'button', 'compact'],
            'request_origin' => $origin,
            'cors_mode' => 'public_no_credentials',
            'settings' => $settings,
            'health' => [
                'active' => (string)$campaign['status'] === 'active',
                'available' => $available,
                'embed_enabled' => (bool)$settings['embed_enabled'],
                'domain_allowed' => true,
                'has_public_url' => $publicUrl !== '',
                'has_submit_endpoint' => $submitEndpoint !== '',
                'host_css_adoption' => true,
                'media_handoff' => $media !== null,
            ],
        ],
    ], 'Campaign embed loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'public.campaign_embed.unavailable', 'Unable to load campaign embed.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_fail('Campaign embed unavailable.', 500);
}
