<?php
declare(strict_types=1);

require_once __DIR__ . '/campaign-types.php';

function mg_campaign_landing_request_ref(): string
{
    return strtolower(trim((string)($_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? '')));
}

function mg_campaign_landing_request_token(): string
{
    return trim((string)($_GET['token'] ?? $_GET['qr_token'] ?? ''));
}

function mg_campaign_landing_preview_requested(): bool
{
    $value = strtolower(trim((string)($_GET['preview'] ?? '')));
    return in_array($value, ['1', 'true', 'yes', 'merchant'], true);
}

function mg_campaign_landing_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 900 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (!is_array($parts)) return null;
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    return in_array($scheme, ['http', 'https'], true)
        && !empty($parts['host'])
        && !isset($parts['user'], $parts['pass'])
        ? $url
        : null;
}

function mg_campaign_landing_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'MG';
    $parts = preg_split('/\s+/u', $name) ?: [];
    $first = mb_substr((string)($parts[0] ?? 'M'), 0, 1);
    $last = count($parts) > 1 ? mb_substr((string)$parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function mg_campaign_landing_rules(array $campaign): array
{
    $json = trim((string)($campaign['rules_json'] ?? ''));
    $decoded = $json !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_landing_reward_metadata(array $campaign): array
{
    $json = trim((string)($campaign['reward_metadata_json'] ?? $campaign['reward_template_metadata_json'] ?? ''));
    $decoded = $json !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_landing_campaign_image(array $campaign): ?string
{
    $rules = mg_campaign_landing_rules($campaign);
    foreach (['campaign_image_url', 'media_image_url', 'scratch_image_url', 'reward_image_url'] as $key) {
        $url = mg_campaign_landing_safe_url($rules[$key] ?? null);
        if ($url !== null) return $url;
    }
    return null;
}

function mg_campaign_landing_reward_cover(array $campaign): ?string
{
    $metadata = mg_campaign_landing_reward_metadata($campaign);
    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    foreach (['reward_image_url', 'cover_image_url'] as $key) {
        $url = mg_campaign_landing_safe_url($metadata[$key] ?? $pack[$key] ?? null);
        if ($url !== null) return $url;
    }
    return null;
}

function mg_campaign_landing_primary_image(array $campaign): ?string
{
    return mg_campaign_landing_campaign_image($campaign)
        ?? mg_campaign_landing_reward_cover($campaign)
        ?? mg_campaign_landing_safe_url($campaign['merchant_profile_cover_url'] ?? null);
}

function mg_campaign_landing_background_image(array $campaign): ?string
{
    return mg_campaign_landing_safe_url($campaign['merchant_profile_cover_url'] ?? null)
        ?? mg_campaign_landing_campaign_image($campaign)
        ?? mg_campaign_landing_reward_cover($campaign);
}

function mg_campaign_landing_value(array $campaign): string
{
    $type = (string)($campaign['value_type'] ?? '');
    $rewardType = (string)($campaign['reward_type'] ?? '');
    if (in_array($rewardType, ['audio_pack', 'media_pack'], true)) {
        return $rewardType === 'audio_pack' ? 'Audio pack' : 'Media pack';
    }
    if ($type === 'percent' && ($campaign['value_percent'] ?? null) !== null) {
        return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '% reward';
    }
    if (in_array($type, ['free_item', 'custom'], true)
        || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) {
        return trim((string)($campaign['reward_template_title'] ?? '')) ?: 'Reward';
    }
    $cents = max(0, (int)($campaign['value_amount_cents'] ?? 0));
    $currency = strtoupper(trim((string)($campaign['currency'] ?? 'USD')) ?: 'USD');
    return $cents > 0 ? $currency . ' ' . number_format($cents / 100, 2) . ' value' : 'Reward';
}

function mg_campaign_landing_preview_user_id(): ?int
{
    if (!function_exists('mg_current_user') || !function_exists('mg_has_permission')) return null;
    if (!mg_has_permission('merchant.campaigns.view')) return null;
    $user = mg_current_user();
    $id = (int)($user['id'] ?? 0);
    return $id > 0 ? $id : null;
}

function mg_campaign_landing_load(
    ?string $expectedType,
    string $campaignRef,
    string $token = '',
    bool $previewMode = false
): ?array {
    if ($campaignRef === '' && $token === '') return null;

    $previewUserId = $previewMode ? mg_campaign_landing_preview_user_id() : null;
    if ($previewMode && !$previewUserId) return null;

    $sql = "SELECT c.*, u.display_name merchant_user_display_name, u.full_name merchant_user_full_name,
                   pp.public_id merchant_profile_public_id, pp.slug merchant_profile_slug,
                   pp.display_name merchant_profile_display_name, pp.headline merchant_profile_headline,
                   pp.avatar_url merchant_profile_avatar_url, pp.cover_url merchant_profile_cover_url,
                   pp.location_label merchant_profile_location,
                   rt.public_id reward_template_public_id, rt.title reward_template_title,
                   rt.description reward_template_description, rt.reward_type, rt.value_type,
                   rt.value_amount_cents, rt.value_percent, rt.currency, rt.redemption_instructions,
                   rt.expiration_rule, rt.expiration_days, rt.expires_at reward_expires_at,
                   rt.metadata_json reward_metadata_json
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
            LEFT JOIN users u ON u.id = c.merchant_user_id
            LEFT JOIN public_profiles pp ON pp.user_id = c.merchant_user_id
                AND pp.status = 'active' AND pp.visibility IN ('public','unlisted')
            WHERE ((? <> '' AND (c.public_id = ? OR c.public_slug = ?))
                OR (? <> '' AND c.qr_code_token = ?))";
    $params = [$campaignRef, $campaignRef, $campaignRef, $token, $token];

    if ($previewMode) {
        $sql .= ' AND c.merchant_user_id = ?';
        $params[] = $previewUserId;
    } else {
        $sql .= " AND c.status = 'active'";
    }

    if ($expectedType !== null && $expectedType !== '') {
        $sql .= ' AND c.campaign_type = ?';
        $params[] = $expectedType;
    }

    $sql .= ' LIMIT 1';
    $stmt = mg_db()->prepare($sql);
    $stmt->execute($params);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    return $campaign ?: null;
}

function mg_campaign_landing_state(?array $campaign, bool $previewMode = false, ?int $now = null): array
{
    $now ??= time();
    if (!$campaign) {
        return [
            'available' => false,
            'closed' => true,
            'code' => 'unavailable',
            'message' => 'Campaign not available.',
            'status_label' => 'UNAVAILABLE',
            'active_status' => 'Campaign not available',
        ];
    }

    $type = (string)($campaign['campaign_type'] ?? '');
    if (!mg_campaign_type_public_enabled($type)) {
        return [
            'available' => false,
            'closed' => true,
            'code' => 'internal',
            'message' => 'This campaign does not have a public landing page.',
            'status_label' => 'INTERNAL',
            'active_status' => 'Public landing disabled',
        ];
    }

    $status = strtolower(trim((string)($campaign['status'] ?? 'draft')) ?: 'draft');
    $statusLabel = strtoupper(str_replace('_', ' ', $status));

    if ($previewMode) {
        return [
            'available' => true,
            'closed' => false,
            'code' => 'preview',
            'message' => 'Merchant preview. Customer submissions are disabled.',
            'status_label' => $statusLabel,
            'active_status' => 'Merchant preview',
        ];
    }

    if ($status !== 'active') {
        return [
            'available' => false,
            'closed' => true,
            'code' => 'inactive',
            'message' => 'This campaign is not active.',
            'status_label' => $statusLabel,
            'active_status' => 'Campaign inactive',
        ];
    }

    $startsAt = trim((string)($campaign['starts_at'] ?? ''));
    if ($startsAt !== '') {
        $timestamp = strtotime($startsAt);
        if ($timestamp !== false && $timestamp > $now) {
            return [
                'available' => true,
                'closed' => true,
                'code' => 'scheduled',
                'message' => 'This campaign has not started yet.',
                'status_label' => $statusLabel,
                'active_status' => 'Scheduled to start',
            ];
        }
    }

    $endsAt = trim((string)($campaign['ends_at'] ?? ''));
    if ($endsAt !== '') {
        $timestamp = strtotime($endsAt);
        if ($timestamp !== false && $timestamp < $now) {
            return [
                'available' => true,
                'closed' => true,
                'code' => 'ended',
                'message' => 'This campaign has ended.',
                'status_label' => $statusLabel,
                'active_status' => 'Campaign ended',
            ];
        }
    }

    if (($campaign['quantity_limit'] ?? null) !== null
        && (int)($campaign['issued_count'] ?? 0) >= (int)$campaign['quantity_limit']) {
        return [
            'available' => true,
            'closed' => true,
            'code' => 'limit_reached',
            'message' => 'This campaign reward limit has been reached.',
            'status_label' => $statusLabel,
            'active_status' => 'Reward limit reached',
        ];
    }

    return [
        'available' => true,
        'closed' => false,
        'code' => 'active',
        'message' => '',
        'status_label' => $statusLabel,
        'active_status' => 'Active and ready',
    ];
}

function mg_campaign_landing_profile(array $campaign): array
{
    $name = trim((string)($campaign['merchant_profile_display_name'] ?? ''))
        ?: (trim((string)($campaign['merchant_user_display_name'] ?? ''))
            ?: (trim((string)($campaign['merchant_user_full_name'] ?? '')) ?: 'Microgifter merchant'));
    $slug = trim((string)($campaign['merchant_profile_slug'] ?? ''));
    return [
        'name' => $name,
        'headline' => trim((string)($campaign['merchant_profile_headline'] ?? '')),
        'location' => trim((string)($campaign['merchant_profile_location'] ?? '')),
        'avatar_url' => mg_campaign_landing_safe_url($campaign['merchant_profile_avatar_url'] ?? null),
        'cover_url' => mg_campaign_landing_safe_url($campaign['merchant_profile_cover_url'] ?? null),
        'profile_url' => $slug !== '' ? '/profile.php?slug=' . rawurlencode($slug) : null,
        'initials' => mg_campaign_landing_initials($name),
    ];
}

function mg_campaign_landing_prefill(): array
{
    $user = function_exists('mg_current_user') ? mg_current_user() : null;
    return [
        'name' => is_array($user) ? trim((string)($user['display_name'] ?? $user['full_name'] ?? '')) : '',
        'email' => is_array($user) ? strtolower(trim((string)($user['email'] ?? ''))) : '',
    ];
}

function mg_campaign_landing_document_title(array $campaign, string $fallback = 'Microgifter Campaign'): string
{
    $headline = trim((string)($campaign['form_headline'] ?? ''))
        ?: trim((string)($campaign['title'] ?? ''));
    return ($headline !== '' ? $headline : $fallback) . ' | Microgifter';
}

function mg_campaign_landing_meta(array $campaign): array
{
    $type = (string)($campaign['campaign_type'] ?? '');
    $headline = trim((string)($campaign['form_headline'] ?? ''))
        ?: (trim((string)($campaign['title'] ?? '')) ?: mg_campaign_type_label($type));
    $description = trim((string)($campaign['form_description'] ?? ''))
        ?: trim((string)($campaign['description'] ?? ''));
    $path = mg_campaign_type_public_path($type);
    $ref = trim((string)($campaign['public_slug'] ?? ''))
        ?: trim((string)($campaign['public_id'] ?? ''));
    $canonical = $path !== '' && $ref !== '' ? $path . '?campaign=' . rawurlencode($ref) : '';
    return [
        'description' => $description,
        'canonical' => $canonical,
        'og_title' => $headline . ' | Microgifter',
        'og_description' => $description,
        'og_image' => mg_campaign_landing_primary_image($campaign) ?? '',
    ];
}

function mg_campaign_landing_bootstrap(?string $expectedType, string $fallbackTitle): array
{
    $campaignRef = mg_campaign_landing_request_ref();
    $campaignToken = mg_campaign_landing_request_token();
    $previewMode = mg_campaign_landing_preview_requested();
    try {
        $campaign = mg_campaign_landing_load($expectedType, $campaignRef, $campaignToken, $previewMode);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'public.campaign_landing.bootstrap_failed', 'Unable to load campaign landing page.', [
                'campaign_type' => $expectedType,
                'exception_class' => $error::class,
            ]);
        }
        $campaign = null;
    }
    return [
        'campaign' => $campaign,
        'campaign_ref' => $campaignRef,
        'campaign_token' => $campaignToken,
        'preview' => $previewMode,
        'page_title' => is_array($campaign) ? mg_campaign_landing_document_title($campaign, $fallbackTitle) : $fallbackTitle,
        'page_meta' => is_array($campaign) ? mg_campaign_landing_meta($campaign) : [],
    ];
}

function mg_campaign_landing_render_unavailable(string $label, string $intro, string $message = ''): void
{
    $message = trim($message) ?: 'Use the campaign link or QR code from the merchant to open the correct page.';
    ?>
    <section class="mg-rl-page mg-rl-campaign-foundation mg-rl-campaign-unavailable">
      <div class="mg-rl-bg"></div>
      <div class="mg-rl-wrap">
        <div class="mg-rl-left">
          <article class="mg-rl-card mg-rl-state-card">
            <span class="mg-rl-eyebrow"><?= mg_e($label) ?></span>
            <h2>Campaign not available</h2>
            <p><?= mg_e($intro) ?></p>
            <p><?= mg_e($message) ?></p>
            <a class="mg-rl-btn mg-rl-btn-soft" href="/discover.php">Explore Microgifter</a>
          </article>
        </div>
      </div>
    </section>
    <?php
}

function mg_campaign_landing_render_profile(array $profile, string $linkClass = 'mg-rl-btn mg-rl-btn-soft mg-rl-profile-link'): void
{
    $avatarUrl = $profile['avatar_url'] ?? null;
    $profileUrl = $profile['profile_url'] ?? null;
    ?>
    <div class="mg-rl-profile">
      <div class="mg-rl-avatar">
        <?php if ($avatarUrl): ?>
          <img src="<?= mg_e((string)$avatarUrl) ?>" alt="<?= mg_e((string)$profile['name']) ?> profile image">
        <?php else: ?>
          <span><?= mg_e((string)$profile['initials']) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <h2><?= mg_e((string)$profile['name']) ?></h2>
        <?php if (!empty($profile['headline'])): ?><p><?= mg_e((string)$profile['headline']) ?></p><?php endif; ?>
        <?php if (!empty($profile['location'])): ?><p><?= mg_e((string)$profile['location']) ?></p><?php endif; ?>
        <?php if ($profileUrl): ?><a class="<?= mg_e($linkClass) ?>" href="<?= mg_e((string)$profileUrl) ?>">View profile</a><?php endif; ?>
      </div>
    </div>
    <?php
}

function mg_campaign_landing_render_bottom_cards(array $context): void
{
    if (!empty($context['hidden'])) return;
    $campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : [];
    $state = is_array($context['state'] ?? null) ? $context['state'] : [];
    $rewardTitle = trim((string)($context['reward_title'] ?? '')) ?: 'Microgifter reward';
    $rewardValue = trim((string)($context['reward_value'] ?? '')) ?: 'Reward';
    $outcomeTitle = trim((string)($context['outcome_title'] ?? '')) ?: 'Campaign reward';
    $outcomeCopy = trim((string)($context['outcome_copy'] ?? '')) ?: 'Eligible rewards continue through Microgifter Inbox.';
    ?>
    <div class="mg-rl-bottom" data-campaign-foundation-cards>
      <article class="mg-rl-card">
        <span class="mg-rl-eyebrow">Reward Info</span>
        <h3><?= mg_e($rewardTitle) ?></h3>
        <p><?= mg_e($rewardValue) ?></p>
        <?php if (!empty($campaign['redemption_instructions'])): ?><p><?= mg_e((string)$campaign['redemption_instructions']) ?></p><?php endif; ?>
        <?php if (!empty($campaign['ends_at'])): ?><span class="mg-rl-pill">Ends <?= mg_e(date('M j, Y', strtotime((string)$campaign['ends_at']))) ?></span><?php endif; ?>
      </article>
      <article class="mg-rl-card">
        <span class="mg-rl-eyebrow">Reward Levels</span>
        <h3><?= mg_e($outcomeTitle) ?></h3>
        <ul class="mg-rl-list">
          <li><strong>Level 1</strong><?= mg_e($outcomeCopy) ?></li>
          <li><strong>Inbox delivery</strong>Eligible rewards are issued into the customer Microgifter Inbox.</li>
          <li><strong>PPPM tracked</strong>Campaign source and reward lifecycle stay connected.</li>
        </ul>
      </article>
      <article class="mg-rl-card">
        <span class="mg-rl-eyebrow">Active Status &amp; Updates</span>
        <h3><?= mg_e((string)($state['active_status'] ?? 'Campaign status')) ?></h3>
        <ul class="mg-rl-list">
          <li><strong>Campaign status</strong><?= mg_e((string)($state['status_label'] ?? 'UNKNOWN')) ?></li>
          <li><strong>Updates</strong>Submit the form to record the campaign action and show reward status.</li>
          <li><strong>CRM rule</strong>First issued reward or purchased value creates or promotes the merchant CRM contact.</li>
        </ul>
      </article>
    </div>
    <?php
}
