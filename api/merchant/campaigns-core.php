<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/public/campaigns/_merchant_notifications.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-feature.php';

function mg_campaign_slug(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? ''));
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'campaign', 0, 120);
}

function mg_campaign_unique_slug(PDO $pdo, int $merchantId, string $title, string $excludePublicId = ''): string
{
    $base = mg_campaign_slug($title);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id = ? AND public_slug = ? AND public_id <> ?');
    while (true) {
        $stmt->execute([$merchantId, $candidate, $excludePublicId]);
        if ((int)$stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 120 - strlen((string)$suffix) - 1)) . '-' . $suffix;
    }
}

function mg_campaign_decode_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_datetime(?string $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw) === 1) $raw .= ':00';
    $ts = strtotime($raw);
    if ($ts === false) mg_fail('Invalid campaign date.', 422);
    return date('Y-m-d H:i:s', $ts);
}

function mg_campaign_youtube_id(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1) return $value;
    $parts = parse_url($value);
    if (!is_array($parts)) return '';
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = trim((string)($parts['path'] ?? ''), '/');
    parse_str((string)($parts['query'] ?? ''), $query);
    if (isset($query['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string)$query['v']) === 1) return (string)$query['v'];
    if (str_contains($host, 'youtu.be') && preg_match('/^[A-Za-z0-9_-]{11}$/', $path) === 1) return $path;
    if (str_contains($host, 'youtube.com') && preg_match('~(?:embed|shorts)/([A-Za-z0-9_-]{11})~', $path, $match) === 1) return $match[1];
    return '';
}

function mg_campaign_spotify_track_id(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('/^[A-Za-z0-9]{22}$/', $value) === 1) return $value;
    if (preg_match('/^spotify:track:([A-Za-z0-9]{22})$/', $value, $match) === 1) return $match[1];
    $parts = parse_url($value);
    if (!is_array($parts)) return '';
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = trim((string)($parts['path'] ?? ''), '/');
    if (str_contains($host, 'open.spotify.com') && preg_match('~(?:intl-[a-z]{2}/)?track/([A-Za-z0-9]{22})~i', $path, $match) === 1) return $match[1];
    return '';
}

function mg_campaign_percent(mixed $value, int $fallback): int
{
    $number = (int)$value;
    if ($number < 1) $number = $fallback;
    return max(1, min(100, $number));
}

function mg_campaign_watch_provider(array $input): string
{
    $provider = strtolower(trim((string)($input['watch_video_provider'] ?? 'youtube')));
    return in_array($provider, ['youtube', 'uploaded'], true) ? $provider : 'youtube';
}

function mg_campaign_listen_provider(array $input): string
{
    $provider = strtolower(trim((string)($input['listen_music_provider'] ?? 'spotify')));
    return in_array($provider, ['spotify', 'uploaded'], true) ? $provider : 'spotify';
}

function mg_campaign_asset(PDO $pdo, int $merchantId, string $publicId, string $assetType, string $label): ?array
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (strlen($publicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) {
        mg_fail('Invalid ' . $label . ' asset.', 422);
    }
    $stmt = $pdo->prepare("SELECT public_id,mime_type,byte_size FROM catalog_assets WHERE public_id=? AND owner_user_id=? AND asset_type=? AND status='ready' LIMIT 1");
    $stmt->execute([$publicId, $merchantId, $assetType]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$asset) mg_fail(ucfirst($label) . ' asset not found.', 404);
    return $asset;
}

function mg_campaign_watch_asset(PDO $pdo, int $merchantId, string $publicId): ?array
{
    return mg_campaign_asset($pdo, $merchantId, $publicId, 'video', 'uploaded video');
}

function mg_campaign_listen_asset(PDO $pdo, int $merchantId, string $publicId): ?array
{
    return mg_campaign_asset($pdo, $merchantId, $publicId, 'audio', 'uploaded audio');
}

function mg_campaign_image_asset(PDO $pdo, int $merchantId, string $publicId): ?array
{
    return mg_campaign_asset($pdo, $merchantId, $publicId, 'image', 'campaign image');
}

function mg_campaign_dynamic_milestones(array $input, string $jsonField, int $requiredPercent, string $defaultNoun): array
{
    $raw = trim((string)($input[$jsonField] ?? ''));
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    $rows = is_array($decoded) ? $decoded : [];
    if (!$rows) {
        $rows = [[
            'percent' => $requiredPercent,
            'reward_template_id' => '',
            'label' => $requiredPercent . '% ' . $defaultNoun,
        ]];
    }
    $items = [];
    foreach ($rows as $item) {
        if (!is_array($item)) continue;
        $percent = mg_campaign_percent($item['percent'] ?? 0, $requiredPercent);
        $reward = strtolower(trim((string)($item['reward_template_id'] ?? '')));
        $label = trim((string)($item['label'] ?? ''));
        $items[$percent] = [
            'percent' => $percent,
            'reward_template_id' => $reward,
            'label' => $label !== '' ? mb_substr($label, 0, 120) : ($percent . '% ' . $defaultNoun),
        ];
    }
    ksort($items);
    return array_values($items ?: [
        $requiredPercent => [
            'percent' => $requiredPercent,
            'reward_template_id' => '',
            'label' => $requiredPercent . '% ' . $defaultNoun,
        ],
    ]);
}

function mg_campaign_watch_milestones(array $input, int $requiredPercent): array
{
    return mg_campaign_dynamic_milestones($input, 'watch_video_reward_gates_json', $requiredPercent, 'watched gift');
}

function mg_campaign_listen_milestones(array $input, int $requiredPercent): array
{
    return mg_campaign_dynamic_milestones($input, 'listen_reward_gates_json', $requiredPercent, 'listened gift');
}

function mg_campaign_media_image_rules(
    PDO $pdo,
    int $merchantId,
    array $input,
    array $existingRules,
    string $assetField,
    string $urlField
): array {
    $assetId = strtolower(trim((string)($input[$assetField] ?? '')));
    $url = trim((string)($input[$urlField] ?? ''));
    if ($assetId === '' && $url === '') {
        $assetId = strtolower(trim((string)($existingRules['media_image_asset_id'] ?? '')));
        $url = trim((string)($existingRules['media_image_url'] ?? ''));
    }
    $asset = $assetId !== '' ? mg_campaign_image_asset($pdo, $merchantId, $assetId) : null;
    return [
        'media_image_asset_id' => $asset ? (string)$asset['public_id'] : '',
        'media_image_url' => $url,
    ];
}

function mg_campaign_build_rules(PDO $pdo, int $merchantId, string $campaignType, array $input, ?int $quantityLimit, array $existingRules = []): ?string
{
    $definition = mg_campaign_type_get($campaignType) ?? [];
    $rules = [
        'campaign_type' => $campaignType,
        'version' => 2,
        'registry' => 'campaign_types_v2_dynamic_gates',
    ];

    if ($campaignType === 'contest_giveaway') {
        $mode = trim((string)($input['contest_mode'] ?? 'first_x'));
        $allowed = ['first_x', 'instant_reward', 'random_draw', 'manual_winner'];
        if (!in_array($mode, $allowed, true)) $mode = 'first_x';
        $winnerLimitRaw = trim((string)($input['contest_winner_limit'] ?? ''));
        $winnerLimit = $winnerLimitRaw === '' ? null : max(1, (int)$winnerLimitRaw);
        if ($mode === 'first_x' && $winnerLimit === null) $winnerLimit = $quantityLimit ?? 100;
        $rules += [
            'mode' => $mode,
            'winner_limit' => $winnerLimit,
            'draw_at' => mg_campaign_datetime((string)($input['contest_draw_at'] ?? '')),
            'entry_reward_enabled' => $mode === 'first_x' || $mode === 'instant_reward' || !empty($input['contest_entry_reward_enabled']),
            'official_rules' => trim((string)($input['contest_rules'] ?? '')) ?: null,
        ];
    } elseif ($campaignType === 'qr_reward_drop') {
        $rules += ['mode' => 'qr_claim', 'entry_reward_enabled' => true];
    } elseif ($campaignType === 'referral_reward') {
        $rules += ['mode' => 'referral_capture', 'instructions' => trim((string)($input['referral_instructions'] ?? '')) ?: null];
    } elseif ($campaignType === 'birthday_vip') {
        $rules += ['mode' => 'birthday_capture', 'instructions' => trim((string)($input['vip_instructions'] ?? '')) ?: null];
    } elseif ($campaignType === 'agent_offer') {
        $rules += ['mode' => 'agent_interest', 'instructions' => trim((string)($input['agent_offer_instructions'] ?? '')) ?: null];
    } elseif ($campaignType === 'customer_refund') {
        $rules += ['mode' => 'merchant_initiated', 'internal_only' => true, 'entry_reward_enabled' => true];
    } elseif ($campaignType === 'instant_win_reward') {
        $mode = trim((string)($input['instant_win_mode'] ?? $existingRules['play_mode'] ?? 'scratch_card'));
        if (!in_array($mode, ['scratch_card', 'spin_wheel'], true)) $mode = 'scratch_card';
        $odds = max(0, min(100, (int)($input['instant_win_odds_percent'] ?? $existingRules['odds_percent'] ?? 100)));
        $noWin = trim((string)($input['instant_win_no_win_message'] ?? $existingRules['no_win_message'] ?? 'Not a winner this time — thanks for playing.'));
        $image = mg_campaign_media_image_rules($pdo, $merchantId, $input, $existingRules, 'instant_win_scratch_image_asset_id', 'instant_win_scratch_image_url');
        $rules += [
            'mode' => $mode,
            'play_mode' => $mode,
            'odds_percent' => $odds,
            'instant_win_odds_percent' => $odds,
            'no_win_message' => mb_substr($noWin !== '' ? $noWin : 'Not a winner this time — thanks for playing.', 0, 240),
            'online_play' => !empty($input['instant_win_online_play']),
            'entry_reward_enabled' => true,
        ] + $image;
    } elseif ($campaignType === 'stamp_card_reward') {
        $required = max(1, min(100, (int)($input['stamp_required_count'] ?? $existingRules['required_count'] ?? 5)));
        $label = trim((string)($input['stamp_label'] ?? $existingRules['stamp_label'] ?? 'Visit'));
        $cooldown = max(0, min(8760, (int)($input['stamp_cooldown_hours'] ?? $existingRules['cooldown_hours'] ?? 0)));
        $image = mg_campaign_media_image_rules($pdo, $merchantId, $input, $existingRules, 'stamp_card_image_asset_id', 'stamp_card_image_url');
        $rules += [
            'mode' => 'verified_stamp_card',
            'required_count' => $required,
            'stamp_required_count' => $required,
            'stamp_label' => mb_substr($label !== '' ? $label : 'Visit', 0, 40),
            'cooldown_hours' => $cooldown,
            'stamp_cooldown_hours' => $cooldown,
            'cashier_verification_required' => !empty($input['stamp_cashier_verification_required']),
            'entry_reward_enabled' => !empty($input['stamp_card_reward_enabled']),
        ] + $image;
    } elseif ($campaignType === 'watch_video_reward') {
        $provider = mg_campaign_watch_provider($input);
        $required = mg_campaign_percent($input['watch_video_required_percent'] ?? 80, 80);
        $url = trim((string)($input['watch_video_url'] ?? ''));
        $videoId = mg_campaign_youtube_id($url);
        $assetId = strtolower(trim((string)($input['watch_video_upload_asset_id'] ?? '')));
        $uploadedUrl = trim((string)($input['watch_video_uploaded_url'] ?? ''));
        $asset = $provider === 'uploaded' ? mg_campaign_watch_asset($pdo, $merchantId, $assetId) : null;
        $image = mg_campaign_media_image_rules($pdo, $merchantId, $input, $existingRules, 'watch_media_image_asset_id', 'watch_media_image_url');
        $rules += [
            'mode' => 'video_watch_milestones',
            'video_provider' => $provider,
            'youtube_url' => $provider === 'youtube' ? $url : '',
            'youtube_video_id' => $provider === 'youtube' ? $videoId : '',
            'uploaded_asset_id' => $asset ? (string)$asset['public_id'] : '',
            'uploaded_video_url' => $provider === 'uploaded' ? $uploadedUrl : '',
            'uploaded_mime_type' => $asset ? (string)$asset['mime_type'] : '',
            'required_percent' => $required,
            'milestones' => mg_campaign_watch_milestones($input, $required),
            'anti_skip_note' => 'Browser watch progress is recorded in campaign events; server issues only unissued milestone gifts.',
        ] + $image;
    } elseif ($campaignType === 'listen_music_reward') {
        $provider = mg_campaign_listen_provider($input);
        $required = mg_campaign_percent($input['listen_required_percent'] ?? 80, 80);
        $spotifyUrl = trim((string)($input['listen_spotify_url'] ?? ''));
        $spotifyTrackId = mg_campaign_spotify_track_id($spotifyUrl);
        $assetId = strtolower(trim((string)($input['listen_audio_upload_asset_id'] ?? '')));
        $uploadedUrl = trim((string)($input['listen_audio_uploaded_url'] ?? ''));
        $asset = $provider === 'uploaded' ? mg_campaign_listen_asset($pdo, $merchantId, $assetId) : null;
        $image = mg_campaign_media_image_rules($pdo, $merchantId, $input, $existingRules, 'listen_media_image_asset_id', 'listen_media_image_url');
        $rules += [
            'mode' => 'audio_listen_milestones',
            'audio_provider' => $provider,
            'spotify_url' => $provider === 'spotify' ? $spotifyUrl : '',
            'spotify_track_id' => $provider === 'spotify' ? $spotifyTrackId : '',
            'uploaded_asset_id' => $asset ? (string)$asset['public_id'] : '',
            'uploaded_audio_url' => $provider === 'uploaded' ? $uploadedUrl : '',
            'uploaded_mime_type' => $asset ? (string)$asset['mime_type'] : '',
            'track_title' => trim((string)($input['listen_track_title'] ?? '')),
            'artist_name' => trim((string)($input['listen_artist_name'] ?? '')),
            'required_percent' => $required,
            'milestones' => mg_campaign_listen_milestones($input, $required),
            'spotify_note' => 'Spotify links are embedded/listen-intent rewards; uploaded audio supports true percent listened milestones.',
        ] + $image;
    } elseif ($campaignType === 'public_donation') {
        $rules += [
            'mode' => 'merchant_initiated_bulk',
            'public_mode' => 'informational',
            'public_transactional' => false,
            'entry_reward_enabled' => false,
        ];
    } else {
        $rules += [
            'mode' => (string)($definition['rules_schema']['mode'] ?? 'instant_reward'),
            'entry_reward_enabled' => true,
        ];
    }

    return json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function mg_campaign_row(array $row): array
{
    $type = (string)$row['campaign_type'];
    $typeDefinition = mg_campaign_type_get($type) ?? [];
    $rules = mg_campaign_decode_rules($row['rules_json'] ?? null);
    return [
        'id' => (string)$row['public_id'],
        'reward_template_id' => $row['reward_template_public_id'] ?? null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'reward_template_status' => $row['reward_template_status'] ?? null,
        'reward_attached' => !empty($row['reward_template_public_id']),
        'campaign_type' => $type,
        'campaign_type_label' => (string)($typeDefinition['label'] ?? mg_campaign_type_label($type)),
        'campaign_type_category' => (string)($typeDefinition['category'] ?? 'campaign'),
        'public_enabled' => !empty($typeDefinition['public_enabled']),
        'public_transactional' => mg_campaign_type_public_transactional($type),
        'public_mode' => mg_campaign_type_public_mode($type),
        'internal_only' => !empty($typeDefinition['internal_only']),
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'form_headline' => (string)($row['form_headline'] ?? ''),
        'form_description' => (string)($row['form_description'] ?? ''),
        'success_message' => (string)($row['success_message'] ?? ''),
        'status' => (string)$row['status'],
        'starts_at' => $row['starts_at'] ?? null,
        'ends_at' => $row['ends_at'] ?? null,
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
        'issued_count' => (int)($row['issued_count'] ?? 0),
        'per_user_limit' => (int)($row['per_user_limit'] ?? 1),
        'agent_discoverable' => (bool)((int)($row['agent_discoverable'] ?? 0)),
        'public_slug' => $row['public_slug'] ?? null,
        'qr_code_token' => $row['qr_code_token'] ?? null,
        'rules' => $rules,
        'activity' => [
            'contacts' => (int)($row['contact_count'] ?? 0),
            'wallet_items' => (int)($row['wallet_item_count'] ?? 0),
            'events' => (int)($row['event_count'] ?? 0),
            'last_event_at' => $row['last_event_at'] ?? null,
        ],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_campaign_reward_template_id(PDO $pdo, int $merchantId, string $publicId, string $campaignStatus): ?int
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $publicId)) {
        mg_fail('Invalid reward template.', 422);
    }
    $stmt = $pdo->prepare("SELECT id,status FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? AND status <> 'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) mg_fail('Reward template not found.', 404);
    if ($campaignStatus === 'active' && (string)$template['status'] !== 'active') {
        mg_fail('Active campaigns require an active reward template.', 422);
    }
    return (int)$template['id'];
}

function mg_campaign_requires_reward_template(string $campaignType, string $status): bool
{
    return mg_campaign_type_requires_reward_template($campaignType, $status);
}

function mg_campaign_active_usage(PDO $pdo, int $merchantId, string $excludePublicId = ''): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id = ? AND status = 'active' AND public_id <> ?");
    $stmt->execute([$merchantId, $excludePublicId]);
    return (int)$stmt->fetchColumn();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET'
    ? mg_merchant_require_permission('merchant.campaigns.view')
    : mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    try {
        $status = trim((string)($_GET['status'] ?? 'all'));
        $allowedStatus = ['draft', 'active', 'paused', 'ended', 'archived'];
        $sql = "SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title,
                       rt.status reward_template_status,
                       (SELECT COUNT(*) FROM campaign_contacts cc WHERE cc.campaign_id = c.id) contact_count,
                       (SELECT COUNT(*) FROM wallet_items wi WHERE wi.campaign_id = c.id AND wi.status <> 'cancelled') wallet_item_count,
                       (SELECT COUNT(*) FROM campaign_events ce WHERE ce.campaign_id = c.id) event_count,
                       (SELECT MAX(ce2.created_at) FROM campaign_events ce2 WHERE ce2.campaign_id = c.id) last_event_at
                  FROM campaigns c
                  LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
                 WHERE c.merchant_user_id = ?";
        $params = [$merchantId];
        if (in_array($status, $allowedStatus, true)) {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY c.updated_at DESC, c.id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = array_map('mg_campaign_row', $stmt->fetchAll());
        mg_ok([
            'campaigns' => $campaigns,
            'campaign_types' => mg_public_donations_campaign_type_options($merchantId, $user, true),
            'schema_ready' => true,
            'package' => mg_merchant_package_context($pdo, $user),
        ]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaigns.schema_unavailable', 'Campaign schema is unavailable.', [
            'exception_class' => $error::class,
        ], $merchantId);
        mg_ok([
            'campaigns' => [],
            'campaign_types' => mg_public_donations_campaign_type_options($merchantId, $user, true),
            'schema_ready' => false,
        ], 'Campaigns unavailable until the Stage 12 schema is installed.');
    }
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);

$input = mg_input();
mg_require_csrf_for_write($input);
$campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
$title = trim((string)($input['title'] ?? ''));
$campaignType = trim((string)($input['campaign_type'] ?? 'newsletter_signup'));
$status = trim((string)($input['status'] ?? 'draft'));
$description = trim((string)($input['description'] ?? '')) ?: null;
$formHeadline = trim((string)($input['form_headline'] ?? '')) ?: null;
$formDescription = trim((string)($input['form_description'] ?? '')) ?: null;
$successMessage = trim((string)($input['success_message'] ?? '')) ?: null;
$startsAt = mg_campaign_datetime((string)($input['starts_at'] ?? ''));
$endsAt = mg_campaign_datetime((string)($input['ends_at'] ?? ''));
$quantityLimitRaw = trim((string)($input['quantity_limit'] ?? ''));
$quantityLimit = $quantityLimitRaw === '' ? null : max(1, (int)$quantityLimitRaw);
$perUserLimit = max(1, (int)($input['per_user_limit'] ?? 1));
$agentDiscoverable = !empty($input['agent_discoverable']) ? 1 : 0;

if (($campaignId !== '' && (strlen($campaignId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignId)))
    || $title === ''
    || mb_strlen($title) > 180
    || !mg_campaign_type_is_valid($campaignType, true)
    || !in_array($status, ['draft', 'active', 'paused', 'ended', 'archived'], true)) {
    mg_fail('Invalid campaign.', 422);
}

if ($campaignType === 'public_donation' && !mg_public_donations_is_enabled_for($merchantId, $user)) {
    mg_fail('Public Donations campaigns are not enabled for this merchant.', 403);
}

if ($campaignType === 'watch_video_reward' && $status === 'active') {
    $provider = mg_campaign_watch_provider($input);
    if ($provider === 'uploaded') {
        if (trim((string)($input['watch_video_upload_asset_id'] ?? '')) === '') {
            mg_fail('Active Watch Video Reward campaigns require an uploaded video or YouTube URL.', 422);
        }
    } elseif (mg_campaign_youtube_id((string)($input['watch_video_url'] ?? '')) === '') {
        mg_fail('Active Watch Video Reward campaigns require a valid YouTube URL/video ID or uploaded video.', 422);
    }
}

if ($campaignType === 'listen_music_reward' && $status === 'active') {
    $provider = mg_campaign_listen_provider($input);
    if ($provider === 'uploaded') {
        if (trim((string)($input['listen_audio_upload_asset_id'] ?? '')) === '') {
            mg_fail('Active Listen Music Reward campaigns require an uploaded MP3/audio file or Spotify track link.', 422);
        }
    } elseif (mg_campaign_spotify_track_id((string)($input['listen_spotify_url'] ?? '')) === '') {
        mg_fail('Active Listen Music Reward campaigns require a valid Spotify track link or uploaded MP3/audio file.', 422);
    }
}

if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) >= strtotime($endsAt)) {
    mg_fail('Campaign end date must be after the start date.', 422);
}

if ($campaignType === 'contest_giveaway'
    && trim((string)($input['contest_mode'] ?? 'first_x')) === 'first_x'
    && $quantityLimit === null) {
    $quantityLimit = max(1, (int)($input['contest_winner_limit'] ?? 100));
}

$existingRules = [];
if ($campaignId !== '') {
    $existingRulesStmt = $pdo->prepare('SELECT rules_json FROM campaigns WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
    $existingRulesStmt->execute([$campaignId, $merchantId]);
    $existingRules = mg_campaign_decode_rules($existingRulesStmt->fetchColumn() ?: null);
}

$rewardTemplateId = mg_campaign_reward_template_id(
    $pdo,
    $merchantId,
    (string)($input['reward_template_id'] ?? ''),
    $status
);
$rulesJson = mg_campaign_build_rules($pdo, $merchantId, $campaignType, $input, $quantityLimit, $existingRules);

if (mg_campaign_requires_reward_template($campaignType, $status) && $rewardTemplateId === null) {
    mg_fail('Active campaigns require an attached reward template.', 422);
}
if ($status === 'active') {
    mg_package_require_limit_available(
        $pdo,
        $user,
        'max_active_campaigns',
        mg_campaign_active_usage($pdo, $merchantId, $campaignId),
        'Active campaign limit reached.'
    );
}

try {
    $previousStatus = null;
    $isNew = $campaignId === '';
    if ($isNew) {
        $campaignId = mg_merchant_uuid();
        $slug = mg_campaign_type_public_enabled($campaignType)
            ? mg_campaign_unique_slug($pdo, $merchantId, $title)
            : null;
        $qrToken = $campaignType === 'qr_reward_drop' ? bin2hex(random_bytes(16)) : null;
        $stmt = $pdo->prepare('INSERT INTO campaigns
            (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,rules_json,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([
            $campaignId, $merchantId, $rewardTemplateId, $campaignType, $title, $description,
            $formHeadline, $formDescription, $successMessage, $status, $startsAt, $endsAt,
            $quantityLimit, $perUserLimit, $agentDiscoverable, $slug, $qrToken, $rulesJson,
        ]);
        $dbId = (int)$pdo->lastInsertId();
        $message = 'Campaign created.';
    } else {
        $lookup = $pdo->prepare('SELECT id, qr_code_token, status FROM campaigns WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
        $lookup->execute([$campaignId, $merchantId]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        $dbId = (int)($existing['id'] ?? 0);
        if ($dbId <= 0) mg_fail('Campaign not found.', 404);
        $previousStatus = (string)($existing['status'] ?? '');
        $slug = mg_campaign_type_public_enabled($campaignType)
            ? mg_campaign_unique_slug($pdo, $merchantId, $title, $campaignId)
            : null;
        $qrToken = $campaignType === 'qr_reward_drop'
            ? ((string)($existing['qr_code_token'] ?? '') ?: bin2hex(random_bytes(16)))
            : null;
        $stmt = $pdo->prepare('UPDATE campaigns SET
            reward_template_id=?,campaign_type=?,title=?,description=?,form_headline=?,form_description=?,success_message=?,status=?,starts_at=?,ends_at=?,quantity_limit=?,per_user_limit=?,agent_discoverable=?,public_slug=?,qr_code_token=?,rules_json=?,updated_at=NOW()
            WHERE id=? AND public_id=? AND merchant_user_id=?');
        $stmt->execute([
            $rewardTemplateId, $campaignType, $title, $description, $formHeadline, $formDescription,
            $successMessage, $status, $startsAt, $endsAt, $quantityLimit, $perUserLimit,
            $agentDiscoverable, $slug, $qrToken, $rulesJson, $dbId, $campaignId, $merchantId,
        ]);
        $message = 'Campaign updated.';
    }

    $select = $pdo->prepare('SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title,
        rt.status reward_template_status, 0 contact_count, 0 wallet_item_count, 0 event_count, NULL last_event_at
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        WHERE c.id = ? AND c.merchant_user_id = ? LIMIT 1');
    $select->execute([$dbId, $merchantId]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Campaign could not be loaded.', 500);

    $notification = ['created' => false, 'reason' => 'not_required'];
    if ($status === 'active' && ($isNew || $previousStatus !== 'active')) {
        $notification = mg_public_campaign_notify_merchant_lifecycle($pdo, $row, 'campaign.launched');
    } elseif ($isNew) {
        $notification = mg_public_campaign_notify_merchant_lifecycle($pdo, $row, 'campaign.created');
    }

    mg_audit('merchant.campaign_saved', 'campaign', [
        'campaign_id' => $campaignId,
        'campaign_type' => $campaignType,
        'campaign_type_label' => mg_campaign_type_label($campaignType),
        'status' => $status,
        'reward_attached' => $rewardTemplateId !== null,
        'rules' => mg_campaign_decode_rules($rulesJson),
        'notification' => $notification,
    ], $merchantId);

    mg_ok([
        'campaign' => mg_campaign_row($row),
        'notification' => $notification,
        'schema_ready' => true,
        'package' => mg_merchant_package_context($pdo, $user),
    ], $message, 201);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.campaigns.save_failed', 'Unable to save campaign.', [
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], $merchantId);
    mg_fail('Unable to save campaign.', 500);
}