<?php
/**
 * Microgifter Campaign Ads Manager Phase 1 shared helpers.
 *
 * Phase 1 is intentionally controlled: merchant campaign boosts and sponsored
 * local drops with admin approval, placement rendering, and attribution events.
 */
declare(strict_types=1);

if (!function_exists('mg_ads_allowed_statuses')) {
    function mg_ads_allowed_statuses(): array
    {
        return ['draft','pending_review','approved','active','paused','rejected','completed','archived'];
    }
}

if (!function_exists('mg_ads_allowed_objectives')) {
    function mg_ads_allowed_objectives(): array
    {
        return ['claim_growth','redemption_growth','gift_sales','loyalty_growth','referral_growth','event_traffic','reengagement','local_awareness','local_drop','target_zone_activation'];
    }
}

if (!function_exists('mg_ads_allowed_budget_types')) {
    function mg_ads_allowed_budget_types(): array
    {
        return ['none','flat_boost','claim_cap','redemption_cap','sponsored_reward_budget'];
    }
}

if (!function_exists('mg_ads_allowed_placements')) {
    function mg_ads_allowed_placements(): array
    {
        return ['feed_sponsored_card','sidebar_sponsored_card','world_canvas_sponsored_pin','target_zone_sponsored_drop','wallet_recommendation','inbox_recommendation','claim_success_recommendation','campaign_drops_map'];
    }
}

if (!function_exists('mg_ads_primary_placements')) {
    function mg_ads_primary_placements(): array
    {
        return ['feed_sponsored_card','sidebar_sponsored_card','world_canvas_sponsored_pin','target_zone_sponsored_drop'];
    }
}

if (!function_exists('mg_ads_allowed_events')) {
    function mg_ads_allowed_events(): array
    {
        return ['impression','click','claim','wallet_save','gift_send','share','redeem','followup_created','crm_contact_created'];
    }
}

if (!function_exists('mg_ads_table_exists')) {
    function mg_ads_table_exists(PDO $pdo, string $table): bool
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return false;
        }
        static $cache = [];
        $database = '';
        try {
            $database = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
        } catch (Throwable) {
            $database = '';
        }
        $cacheKey = spl_object_id($pdo) . '|' . $database . '|' . strtolower($table);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        if ($database !== '') {
            try {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1');
                $stmt->execute([$database, $table]);
                if ($stmt->fetchColumn()) {
                    return $cache[$cacheKey] = true;
                }
            } catch (Throwable) {
                // Runtime probes below are intentionally retained for hosts with restricted information_schema access.
            }
        }
        try {
            $quoted = $pdo->quote($table);
            if (is_string($quoted) && $quoted !== '') {
                $stmt = $pdo->query('SHOW TABLES LIKE ' . $quoted);
                if ($stmt && $stmt->fetchColumn()) {
                    return $cache[$cacheKey] = true;
                }
            }
        } catch (Throwable) {
            // Fall through to direct probe.
        }
        try {
            $pdo->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 0');
            return $cache[$cacheKey] = true;
        } catch (Throwable) {
            return $cache[$cacheKey] = false;
        }
    }
}

if (!function_exists('mg_ads_required_tables')) {
    function mg_ads_required_tables(): array
    {
        return ['ad_campaigns','ad_creatives','ad_placements','ad_campaign_placements','ad_targeting_rules','ad_events','ad_reviews'];
    }
}

if (!function_exists('mg_ads_schema_status')) {
    function mg_ads_schema_status(PDO $pdo): array
    {
        $tables = [];
        foreach (mg_ads_required_tables() as $table) {
            $tables[$table] = mg_ads_table_exists($pdo, $table);
        }
        return ['ready' => !in_array(false, $tables, true), 'tables' => $tables];
    }
}

if (!function_exists('mg_ads_require_schema')) {
    function mg_ads_require_schema(PDO $pdo): void
    {
        $status = mg_ads_schema_status($pdo);
        if (!$status['ready']) {
            $missing = array_keys(array_filter($status['tables'], static fn($ready) => !$ready));
            throw new RuntimeException('Campaign Ads Manager setup is incomplete. Missing: ' . implode(', ', $missing) . '. Run database/microgifter_ads_manager_phase1.sql on the active database.');
        }
    }
}

if (!function_exists('mg_ads_json')) {
    function mg_ads_json(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : null;
    }
}

if (!function_exists('mg_ads_decode_json')) {
    function mg_ads_decode_json(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('mg_ads_text')) {
    function mg_ads_text(mixed $value, int $max = 190, string $default = ''): string
    {
        $text = trim((string)$value);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text) ?? '';
        if ($text === '') {
            $text = $default;
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) : $text;
    }
}

if (!function_exists('mg_ads_nullable_text')) {
    function mg_ads_nullable_text(mixed $value, int $max = 2000): ?string
    {
        $text = mg_ads_text($value, $max, '');
        return $text === '' ? null : $text;
    }
}

if (!function_exists('mg_ads_enum')) {
    function mg_ads_enum(mixed $value, array $allowed, string $default): string
    {
        $value = strtolower(trim((string)$value));
        return in_array($value, $allowed, true) ? $value : $default;
    }
}

if (!function_exists('mg_ads_public_id')) {
    function mg_ads_public_id(): string
    {
        return function_exists('mg_public_uuid') ? mg_public_uuid() : bin2hex(random_bytes(16));
    }
}

if (!function_exists('mg_ads_safe_url')) {
    function mg_ads_safe_url(mixed $value): ?string
    {
        $url = trim((string)$value);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return substr($url, 0, 700);
        }
        if (filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1) {
            return substr($url, 0, 700);
        }
        return null;
    }
}

if (!function_exists('mg_ads_user_can_admin')) {
    function mg_ads_user_can_admin(array $user): bool
    {
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        if (in_array('admin', $roles, true) || in_array('super_admin', $roles, true)) {
            return true;
        }
        if (function_exists('mg_api_user_has_permission')) {
            return mg_api_user_has_permission($user, 'ads.manage') || mg_api_user_has_permission($user, 'ads.review') || mg_api_user_has_permission($user, 'admin.access');
        }
        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        return in_array('ads.manage', $permissions, true) || in_array('ads.review', $permissions, true) || in_array('admin.access', $permissions, true);
    }
}

if (!function_exists('mg_ads_require_admin_user')) {
    function mg_ads_require_admin_user(array $user): void
    {
        if (!mg_ads_user_can_admin($user)) {
            if (function_exists('mg_fail')) {
                mg_fail('Permission denied.', 403);
            }
            throw new RuntimeException('Permission denied.');
        }
    }
}

if (!function_exists('mg_ads_user_can_merchant')) {
    function mg_ads_user_can_merchant(array $user, PDO $pdo): bool
    {
        if (function_exists('mg_user_has_merchant_access') && mg_user_has_merchant_access($user, $pdo)) {
            return true;
        }
        if (function_exists('mg_ads_user_can_admin') && mg_ads_user_can_admin($user)) {
            return true;
        }
        if (function_exists('mg_api_user_has_permission')) {
            return mg_api_user_has_permission($user, 'merchant.manage') || mg_api_user_has_permission($user, 'campaigns.manage');
        }
        return false;
    }
}

if (!function_exists('mg_ads_require_merchant_user')) {
    function mg_ads_require_merchant_user(array $user, PDO $pdo): void
    {
        if (!mg_ads_user_can_merchant($user, $pdo)) {
            if (function_exists('mg_fail')) {
                mg_fail('Merchant access required.', 403);
            }
            throw new RuntimeException('Merchant access required.');
        }
    }
}

if (!function_exists('mg_ads_merchant_profile')) {
    function mg_ads_merchant_profile(PDO $pdo, int $merchantId): array
    {
        $profile = ['merchant_name' => 'Microgifter Merchant', 'merchant_avatar_url' => null];
        if (mg_ads_table_exists($pdo, 'public_profiles')) {
            try {
                $stmt = $pdo->prepare('SELECT display_name, avatar_url FROM public_profiles WHERE user_id=? LIMIT 1');
                $stmt->execute([$merchantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $name = mg_ads_text($row['display_name'] ?? '', 140, '');
                    $avatar = mg_ads_safe_url($row['avatar_url'] ?? '');
                    if ($name !== '') $profile['merchant_name'] = $name;
                    if ($avatar !== null) $profile['merchant_avatar_url'] = $avatar;
                }
            } catch (Throwable) {
                // Fallback to users table below.
            }
        }
        if ($profile['merchant_name'] === 'Microgifter Merchant' && mg_ads_table_exists($pdo, 'users')) {
            try {
                $stmt = $pdo->prepare('SELECT display_name, full_name, email FROM users WHERE id=? LIMIT 1');
                $stmt->execute([$merchantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $profile['merchant_name'] = mg_ads_text($row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? '', 140, 'Microgifter Merchant');
                }
            } catch (Throwable) {
                // Keep safe default.
            }
        }
        return $profile;
    }
}

if (!function_exists('mg_ads_seed_placements')) {
    function mg_ads_seed_placements(PDO $pdo): void
    {
        mg_ads_require_schema($pdo);
        $placements = [
            ['feed_sponsored_card','Feed Sponsored Card','feed','Full sponsored campaign card inside the Microgifter social feed.', 1, 2],
            ['sidebar_sponsored_card','Sidebar Sponsored Card','sidebar','Compact sponsored campaign card in right/sidebar panels.', 1, 1],
            ['world_canvas_sponsored_pin','World Canvas Sponsored Pin','world_canvas','Sponsored local opportunity marker for map/canvas surfaces.', 1, 5],
            ['target_zone_sponsored_drop','Target Zone Sponsored Drop','target_zone','Sponsored local drop connected to a region, radius, or trigger zone.', 1, 5],
            ['wallet_recommendation','Wallet Recommendation','wallet','Future recommendation placement for wallet surfaces.', 0, 1],
            ['inbox_recommendation','Inbox Recommendation','inbox','Future recommendation placement for inbox surfaces.', 0, 1],
            ['claim_success_recommendation','Claim Success Recommendation','claim','Future post-claim promotional placement.', 0, 1],
            ['campaign_drops_map','Campaign Drops Map','campaign_drops','Future campaign drops map placement.', 0, 5],
        ];
        $stmt = $pdo->prepare('INSERT INTO ad_placements (placement_key,placement_name,surface,description,is_active,max_ads,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE placement_name=VALUES(placement_name),surface=VALUES(surface),description=VALUES(description),max_ads=VALUES(max_ads),updated_at=NOW()');
        foreach ($placements as $placement) {
            $stmt->execute($placement);
        }
    }
}

if (!function_exists('mg_ads_campaign_from_input')) {
    function mg_ads_campaign_from_input(array $input): array
    {
        $placements = $input['placements'] ?? $input['placement_keys'] ?? [];
        if (is_string($placements)) {
            $placements = array_filter(array_map('trim', explode(',', $placements)));
        }
        if (!is_array($placements)) {
            $placements = [];
        }
        $placements = array_values(array_unique(array_filter(array_map(static function ($placement) {
            return mg_ads_enum($placement, mg_ads_allowed_placements(), '');
        }, $placements))));
        if ($placements === []) {
            $placements = ['feed_sponsored_card'];
        }

        $targeting = $input['targeting'] ?? [];
        if (!is_array($targeting)) {
            $targeting = [];
        }
        $targetZoneId = (int)($input['target_zone_id'] ?? 0);
        if ($targetZoneId > 0) {
            $targeting['target_zone_id'] = $targetZoneId;
        }
        $targetZonePublicId = mg_ads_nullable_text($input['target_zone_public_id'] ?? '', 80);
        if ($targetZonePublicId !== null) {
            $targeting['target_zone_public_id'] = $targetZonePublicId;
        }

        return [
            'title' => mg_ads_text($input['title'] ?? $input['headline'] ?? 'Sponsored Campaign', 190, 'Sponsored Campaign'),
            'campaign_id' => (int)($input['campaign_id'] ?? 0) > 0 ? (int)$input['campaign_id'] : null,
            'target_zone_id' => $targetZoneId > 0 ? $targetZoneId : null,
            'objective' => mg_ads_enum($input['objective'] ?? 'claim_growth', mg_ads_allowed_objectives(), 'claim_growth'),
            'budget_type' => mg_ads_enum($input['budget_type'] ?? 'none', mg_ads_allowed_budget_types(), 'none'),
            'budget_amount' => isset($input['budget_amount']) && $input['budget_amount'] !== '' ? max(0, (float)$input['budget_amount']) : null,
            'claim_cap' => isset($input['claim_cap']) && $input['claim_cap'] !== '' ? max(0, (int)$input['claim_cap']) : null,
            'redemption_cap' => isset($input['redemption_cap']) && $input['redemption_cap'] !== '' ? max(0, (int)$input['redemption_cap']) : null,
            'starts_at' => mg_ads_datetime_or_null($input['starts_at'] ?? null),
            'ends_at' => mg_ads_datetime_or_null($input['ends_at'] ?? null),
            'headline' => mg_ads_text($input['headline'] ?? $input['title'] ?? 'Sponsored Local Offer', 190, 'Sponsored Local Offer'),
            'description' => mg_ads_nullable_text($input['description'] ?? '', 2000),
            'image_url' => mg_ads_safe_url($input['image_url'] ?? ''),
            'cta_label' => mg_ads_text($input['cta_label'] ?? 'Claim Reward', 80, 'Claim Reward'),
            'destination_type' => mg_ads_nullable_text($input['destination_type'] ?? '', 64),
            'destination_id' => (int)($input['destination_id'] ?? 0) > 0 ? (int)$input['destination_id'] : null,
            'destination_url' => mg_ads_safe_url($input['destination_url'] ?? ''),
            'sponsored_label' => mg_ads_text($input['sponsored_label'] ?? 'Sponsored', 60, 'Sponsored'),
            'placements' => $placements,
            'targeting' => $targeting,
        ];
    }
}

if (!function_exists('mg_ads_datetime_or_null')) {
    function mg_ads_datetime_or_null(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try {
            $dt = new DateTimeImmutable($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('mg_ads_upsert_campaign')) {
    function mg_ads_upsert_campaign(PDO $pdo, int $merchantId, array $input, ?string $publicId = null): array
    {
        mg_ads_require_schema($pdo);
        mg_ads_seed_placements($pdo);
        $data = mg_ads_campaign_from_input($input);
        $pdo->beginTransaction();
        try {
            $campaignInternalId = null;
            if ($publicId !== null && $publicId !== '') {
                $stmt = $pdo->prepare("SELECT id,status FROM ad_campaigns WHERE public_id=? AND merchant_id=? AND status<>'archived' LIMIT 1 FOR UPDATE");
                $stmt->execute([$publicId, $merchantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Ad campaign is not available.');
                $campaignInternalId = (int)$row['id'];
                $stmt = $pdo->prepare('UPDATE ad_campaigns SET campaign_id=?,target_zone_id=?,title=?,objective=?,budget_type=?,budget_amount=?,claim_cap=?,redemption_cap=?,starts_at=?,ends_at=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([$data['campaign_id'],$data['target_zone_id'],$data['title'],$data['objective'],$data['budget_type'],$data['budget_amount'],$data['claim_cap'],$data['redemption_cap'],$data['starts_at'],$data['ends_at'],$campaignInternalId]);
            } else {
                $publicId = mg_ads_public_id();
                $stmt = $pdo->prepare('INSERT INTO ad_campaigns (public_id,merchant_id,campaign_id,target_zone_id,title,objective,status,budget_type,budget_amount,claim_cap,redemption_cap,starts_at,ends_at,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
                $stmt->execute([$publicId,$merchantId,$data['campaign_id'],$data['target_zone_id'],$data['title'],$data['objective'],'draft',$data['budget_type'],$data['budget_amount'],$data['claim_cap'],$data['redemption_cap'],$data['starts_at'],$data['ends_at'],$merchantId]);
                $campaignInternalId = (int)$pdo->lastInsertId();
            }

            $creativeStmt = $pdo->prepare('SELECT id FROM ad_creatives WHERE ad_campaign_id=? LIMIT 1');
            $creativeStmt->execute([$campaignInternalId]);
            $creativeId = (int)($creativeStmt->fetchColumn() ?: 0);
            $meta = ['destination_url' => $data['destination_url']];
            if ($creativeId > 0) {
                $stmt = $pdo->prepare('UPDATE ad_creatives SET headline=?,description=?,image_url=?,cta_label=?,destination_type=?,destination_id=?,sponsored_label=?,metadata_json=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([$data['headline'],$data['description'],$data['image_url'],$data['cta_label'],$data['destination_type'],$data['destination_id'],$data['sponsored_label'],mg_ads_json($meta),$creativeId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO ad_creatives (public_id,ad_campaign_id,headline,description,image_url,cta_label,destination_type,destination_id,sponsored_label,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
                $stmt->execute([mg_ads_public_id(),$campaignInternalId,$data['headline'],$data['description'],$data['image_url'],$data['cta_label'],$data['destination_type'],$data['destination_id'],$data['sponsored_label'],mg_ads_json($meta)]);
            }

            $pdo->prepare('DELETE FROM ad_campaign_placements WHERE ad_campaign_id=?')->execute([$campaignInternalId]);
            $placeStmt = $pdo->prepare("INSERT INTO ad_campaign_placements (ad_campaign_id,placement_key,priority,status,created_at,updated_at) VALUES (?,?,100,'active',NOW(),NOW())");
            foreach ($data['placements'] as $placement) {
                $placeStmt->execute([$campaignInternalId, $placement]);
            }

            $pdo->prepare('DELETE FROM ad_targeting_rules WHERE ad_campaign_id=?')->execute([$campaignInternalId]);
            if ($data['targeting'] !== []) {
                $ruleStmt = $pdo->prepare('INSERT INTO ad_targeting_rules (ad_campaign_id,rule_type,rule_value_json,created_at) VALUES (?,?,?,NOW())');
                foreach ($data['targeting'] as $key => $value) {
                    $ruleStmt->execute([$campaignInternalId, mg_ads_text($key, 80, 'custom'), mg_ads_json($value)]);
                }
            }
            $pdo->commit();
            return mg_ads_load_campaign($pdo, $publicId, $merchantId, false) ?: ['public_id' => $publicId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('mg_ads_load_campaign')) {
    function mg_ads_load_campaign(PDO $pdo, string $publicId, ?int $merchantId = null, bool $admin = false): ?array
    {
        mg_ads_require_schema($pdo);
        $params = [$publicId];
        $where = 'c.public_id=?';
        if (!$admin && $merchantId !== null) {
            $where .= ' AND c.merchant_id=?';
            $params[] = $merchantId;
        }
        $stmt = $pdo->prepare("SELECT c.*,cr.public_id creative_public_id,cr.headline,cr.description,cr.image_url,cr.cta_label,cr.destination_type,cr.destination_id,cr.sponsored_label,cr.metadata_json creative_metadata_json FROM ad_campaigns c LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id WHERE {$where} AND c.status<>'archived' LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? mg_ads_public_campaign($pdo, $row, true) : null;
    }
}

if (!function_exists('mg_ads_list_campaigns')) {
    function mg_ads_list_campaigns(PDO $pdo, ?int $merchantId = null, bool $admin = false, string $status = '', int $limit = 50): array
    {
        mg_ads_require_schema($pdo);
        $limit = max(1, min(100, $limit));
        $params = [];
        $where = "c.status<>'archived'";
        if (!$admin && $merchantId !== null) {
            $where .= ' AND c.merchant_id=?';
            $params[] = $merchantId;
        }
        if ($status !== '' && in_array($status, mg_ads_allowed_statuses(), true)) {
            $where .= ' AND c.status=?';
            $params[] = $status;
        }
        $stmt = $pdo->prepare("SELECT c.*,cr.public_id creative_public_id,cr.headline,cr.description,cr.image_url,cr.cta_label,cr.destination_type,cr.destination_id,cr.sponsored_label,cr.metadata_json creative_metadata_json FROM ad_campaigns c LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id WHERE {$where} ORDER BY c.updated_at DESC,c.id DESC LIMIT {$limit}");
        $stmt->execute($params);
        return array_map(static fn($row) => mg_ads_public_campaign($pdo, $row, true), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

if (!function_exists('mg_ads_public_campaign')) {
    function mg_ads_public_campaign(PDO $pdo, array $row, bool $includeAdmin = false): array
    {
        $creativeMeta = mg_ads_decode_json($row['creative_metadata_json'] ?? null);
        $profile = mg_ads_merchant_profile($pdo, (int)($row['merchant_id'] ?? 0));
        $placements = [];
        try {
            $stmt = $pdo->prepare('SELECT placement_key FROM ad_campaign_placements WHERE ad_campaign_id=? AND status<>\'archived\' ORDER BY priority ASC,id ASC');
            $stmt->execute([(int)$row['id']]);
            $placements = array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        } catch (Throwable) {
            $placements = [];
        }
        $targeting = [];
        try {
            $stmt = $pdo->prepare('SELECT rule_type,rule_value_json FROM ad_targeting_rules WHERE ad_campaign_id=? ORDER BY id ASC');
            $stmt->execute([(int)$row['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rule) {
                $targeting[(string)$rule['rule_type']] = mg_ads_decode_json($rule['rule_value_json'] ?? null);
            }
        } catch (Throwable) {
            $targeting = [];
        }
        $campaign = [
            'id' => (string)($row['public_id'] ?? ''),
            'public_id' => (string)($row['public_id'] ?? ''),
            'title' => (string)($row['title'] ?? 'Sponsored Campaign'),
            'objective' => (string)($row['objective'] ?? 'claim_growth'),
            'status' => (string)($row['status'] ?? 'draft'),
            'budget_type' => (string)($row['budget_type'] ?? 'none'),
            'budget_amount' => $row['budget_amount'] !== null ? (float)$row['budget_amount'] : null,
            'claim_cap' => $row['claim_cap'] !== null ? (int)$row['claim_cap'] : null,
            'redemption_cap' => $row['redemption_cap'] !== null ? (int)$row['redemption_cap'] : null,
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
            'target_zone_id' => $row['target_zone_id'] !== null ? (int)$row['target_zone_id'] : null,
            'placements' => $placements,
            'targeting' => $targeting,
            'merchant' => $profile,
            'creative' => [
                'id' => (string)($row['creative_public_id'] ?? ''),
                'headline' => (string)($row['headline'] ?? $row['title'] ?? 'Sponsored Local Offer'),
                'description' => (string)($row['description'] ?? ''),
                'image_url' => $row['image_url'] ?? null,
                'cta_label' => (string)($row['cta_label'] ?? 'Claim Reward'),
                'destination_type' => $row['destination_type'] ?? null,
                'destination_id' => $row['destination_id'] !== null ? (int)$row['destination_id'] : null,
                'destination_url' => $creativeMeta['destination_url'] ?? null,
                'sponsored_label' => (string)($row['sponsored_label'] ?? 'Sponsored'),
            ],
            'updated_at' => $row['updated_at'] ?? null,
        ];
        if ($includeAdmin) {
            $campaign['internal_id'] = (int)($row['id'] ?? 0);
            $campaign['merchant_id'] = (int)($row['merchant_id'] ?? 0);
            $campaign['campaign_id'] = $row['campaign_id'] !== null ? (int)$row['campaign_id'] : null;
        }
        return $campaign;
    }
}

if (!function_exists('mg_ads_submit_campaign')) {
    function mg_ads_submit_campaign(PDO $pdo, int $merchantId, string $publicId): array
    {
        mg_ads_require_schema($pdo);
        $stmt = $pdo->prepare("SELECT id,status FROM ad_campaigns WHERE public_id=? AND merchant_id=? AND status IN ('draft','rejected','paused') LIMIT 1");
        $stmt->execute([$publicId, $merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Ad campaign cannot be submitted from its current status.');
        $campaignId = (int)$row['id'];
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE ad_campaigns SET status='pending_review',updated_at=NOW() WHERE id=?")->execute([$campaignId]);
            $pdo->prepare("INSERT INTO ad_reviews (ad_campaign_id,review_status,review_notes,created_at,updated_at) VALUES (?,'pending',NULL,NOW(),NOW())")->execute([$campaignId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return mg_ads_load_campaign($pdo, $publicId, $merchantId, false) ?: ['public_id' => $publicId];
    }
}

if (!function_exists('mg_ads_review_campaign')) {
    function mg_ads_review_campaign(PDO $pdo, int $adminUserId, string $publicId, string $action, ?string $notes = null): array
    {
        mg_ads_require_schema($pdo);
        $action = mg_ads_enum($action, ['approve','reject','pause','reactivate'], 'approve');
        $stmt = $pdo->prepare("SELECT id,status FROM ad_campaigns WHERE public_id=? AND status<>'archived' LIMIT 1");
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Ad campaign is not available.');
        $campaignId = (int)$row['id'];
        $newStatus = ['approve'=>'approved','reject'=>'rejected','pause'=>'paused','reactivate'=>'active'][$action];
        $reviewStatus = ['approve'=>'approved','reject'=>'rejected','pause'=>'paused','reactivate'=>'approved'][$action];
        $pdo->beginTransaction();
        try {
            $approvedSql = $action === 'approve' || $action === 'reactivate' ? ',approved_by_user_id=?,approved_at=NOW()' : ',approved_by_user_id=approved_by_user_id';
            $params = [$newStatus];
            if ($action === 'approve' || $action === 'reactivate') $params[] = $adminUserId;
            $params[] = $campaignId;
            $pdo->prepare("UPDATE ad_campaigns SET status=?,updated_at=NOW() {$approvedSql} WHERE id=?")->execute($params);
            $pdo->prepare('INSERT INTO ad_reviews (ad_campaign_id,review_status,review_notes,reviewed_by_user_id,reviewed_at,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW(),NOW())')->execute([$campaignId,$reviewStatus,$notes,$adminUserId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return mg_ads_load_campaign($pdo, $publicId, null, true) ?: ['public_id' => $publicId];
    }
}

if (!function_exists('mg_ads_render_placement')) {
    function mg_ads_render_placement(PDO $pdo, string $placementKey, int $limit = 1): array
    {
        mg_ads_require_schema($pdo);
        mg_ads_seed_placements($pdo);
        $placementKey = mg_ads_enum($placementKey, mg_ads_allowed_placements(), 'feed_sponsored_card');
        $limit = max(1, min(8, $limit));
        $stmt = $pdo->prepare("SELECT c.*,cr.public_id creative_public_id,cr.headline,cr.description,cr.image_url,cr.cta_label,cr.destination_type,cr.destination_id,cr.sponsored_label,cr.metadata_json creative_metadata_json,cp.placement_key FROM ad_campaigns c INNER JOIN ad_campaign_placements cp ON cp.ad_campaign_id=c.id LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id WHERE cp.placement_key=? AND cp.status='active' AND c.status IN ('approved','active') AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW()) ORDER BY cp.priority ASC,c.approved_at DESC,c.updated_at DESC,c.id DESC LIMIT {$limit}");
        $stmt->execute([$placementKey]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = mg_ads_public_campaign($pdo, $row, false);
            $item['placement_key'] = $placementKey;
            $item['surface'] = mg_ads_surface_for_placement($placementKey);
            $item['tracking'] = ['ad_campaign_id' => $item['public_id'], 'placement_key' => $placementKey, 'surface' => $item['surface']];
            $item['zone'] = mg_ads_zone_payload($pdo, $row);
            $items[] = $item;
        }
        return $items;
    }
}

if (!function_exists('mg_ads_surface_for_placement')) {
    function mg_ads_surface_for_placement(string $placementKey): string
    {
        return match ($placementKey) {
            'feed_sponsored_card' => 'feed',
            'sidebar_sponsored_card' => 'sidebar',
            'world_canvas_sponsored_pin' => 'world_canvas',
            'target_zone_sponsored_drop' => 'target_zone',
            default => 'ads',
        };
    }
}

if (!function_exists('mg_ads_zone_payload')) {
    function mg_ads_zone_payload(PDO $pdo, array $row): ?array
    {
        $targetZoneId = (int)($row['target_zone_id'] ?? 0);
        if ($targetZoneId <= 0) return null;
        if (!mg_ads_table_exists($pdo, 'mg_store_trigger_zones')) {
            return ['id' => $targetZoneId];
        }
        try {
            $stmt = $pdo->prepare('SELECT id,public_id,name,x_percent,y_percent,width_percent,height_percent,status,metadata_json FROM mg_store_trigger_zones WHERE id=? LIMIT 1');
            $stmt->execute([$targetZoneId]);
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($zone)) return ['id' => $targetZoneId];
            return [
                'id' => (int)$zone['id'],
                'public_id' => (string)($zone['public_id'] ?? ''),
                'name' => (string)($zone['name'] ?? 'Sponsored Target Zone'),
                'x' => isset($zone['x_percent']) ? (float)$zone['x_percent'] : null,
                'y' => isset($zone['y_percent']) ? (float)$zone['y_percent'] : null,
                'width' => isset($zone['width_percent']) ? (float)$zone['width_percent'] : null,
                'height' => isset($zone['height_percent']) ? (float)$zone['height_percent'] : null,
                'status' => (string)($zone['status'] ?? 'active'),
            ];
        } catch (Throwable) {
            return ['id' => $targetZoneId];
        }
    }
}

if (!function_exists('mg_ads_hash')) {
    function mg_ads_hash(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        $secret = trim((string)getenv('MG_DISTRIBUTION_HASH_SECRET')) ?: trim((string)getenv('MG_ADS_HASH_SECRET')) ?: 'microgifter_ads_local_hash';
        return hash_hmac('sha256', $value, $secret);
    }
}

if (!function_exists('mg_ads_track_event')) {
    function mg_ads_track_event(PDO $pdo, array $input, ?array $user = null): array
    {
        mg_ads_require_schema($pdo);
        $eventType = mg_ads_enum($input['event_type'] ?? '', mg_ads_allowed_events(), '');
        if ($eventType === '') throw new RuntimeException('Unsupported ad event type.');
        $placementKey = mg_ads_enum($input['placement_key'] ?? '', mg_ads_allowed_placements(), '');
        if ($placementKey === '') throw new RuntimeException('Unsupported ad placement.');
        $publicId = mg_ads_text($input['ad_campaign_id'] ?? $input['public_id'] ?? '', 80, '');
        if ($publicId === '') throw new RuntimeException('Ad campaign id is required.');
        $stmt = $pdo->prepare("SELECT id,merchant_id,campaign_id,target_zone_id FROM ad_campaigns WHERE public_id=? AND status<>'archived' LIMIT 1");
        $stmt->execute([$publicId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($campaign)) throw new RuntimeException('Ad campaign is not available.');
        $surface = mg_ads_text($input['surface'] ?? mg_ads_surface_for_placement($placementKey), 90, mg_ads_surface_for_placement($placementKey));
        $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
        $metadata['source_url'] = mg_ads_safe_url($_SERVER['HTTP_REFERER'] ?? '') ?? null;
        $stmt = $pdo->prepare('INSERT INTO ad_events (public_id,ad_campaign_id,merchant_id,user_id,event_type,surface,placement_key,campaign_id,target_zone_id,wallet_item_id,claim_id,redemption_id,session_key,ip_hash,user_agent_hash,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([
            mg_ads_public_id(),
            (int)$campaign['id'],
            $campaign['merchant_id'] !== null ? (int)$campaign['merchant_id'] : null,
            is_array($user) && isset($user['id']) ? (int)$user['id'] : null,
            $eventType,
            $surface,
            $placementKey,
            $campaign['campaign_id'] !== null ? (int)$campaign['campaign_id'] : null,
            $campaign['target_zone_id'] !== null ? (int)$campaign['target_zone_id'] : null,
            (int)($input['wallet_item_id'] ?? 0) ?: null,
            (int)($input['claim_id'] ?? 0) ?: null,
            (int)($input['redemption_id'] ?? 0) ?: null,
            session_id() ?: null,
            mg_ads_hash((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            mg_ads_hash((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
            mg_ads_json($metadata),
        ]);
        return ['tracked' => true, 'event_type' => $eventType, 'placement_key' => $placementKey];
    }
}

if (!function_exists('mg_ads_performance')) {
    function mg_ads_performance(PDO $pdo, ?int $merchantId = null, ?string $publicId = null, bool $admin = false): array
    {
        mg_ads_require_schema($pdo);
        $params = [];
        $where = '1=1';
        if (!$admin && $merchantId !== null) {
            $where .= ' AND c.merchant_id=?';
            $params[] = $merchantId;
        }
        if ($publicId !== null && $publicId !== '') {
            $where .= ' AND c.public_id=?';
            $params[] = $publicId;
        }
        $stmt = $pdo->prepare("SELECT e.event_type,COUNT(*) total FROM ad_events e INNER JOIN ad_campaigns c ON c.id=e.ad_campaign_id WHERE {$where} GROUP BY e.event_type");
        $stmt->execute($params);
        $counts = array_fill_keys(mg_ads_allowed_events(), 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string)$row['event_type']] = (int)$row['total'];
        }
        $budget = 0.0;
        try {
            $budgetStmt = $pdo->prepare("SELECT COALESCE(SUM(budget_amount),0) FROM ad_campaigns c WHERE {$where}");
            $budgetStmt->execute($params);
            $budget = (float)$budgetStmt->fetchColumn();
        } catch (Throwable) {
            $budget = 0.0;
        }
        return [
            'impressions' => $counts['impression'],
            'clicks' => $counts['click'],
            'claims' => $counts['claim'],
            'wallet_saves' => $counts['wallet_save'],
            'gift_sends' => $counts['gift_send'],
            'shares' => $counts['share'],
            'redemptions' => $counts['redeem'],
            'crm_contacts_created' => $counts['crm_contact_created'],
            'claimed_value' => 0,
            'redeemed_value' => 0,
            'unredeemed_future_demand' => 0,
            'pre_sale_revenue_impact' => 0,
            'cost_per_claim' => $counts['claim'] > 0 ? round($budget / max(1, $counts['claim']), 2) : null,
            'cost_per_redemption' => $counts['redeem'] > 0 ? round($budget / max(1, $counts['redeem']), 2) : null,
            'event_counts' => $counts,
            'notes' => 'Value attribution fields are reserved for wallet/claim/redemption integration and intentionally return zero until wired to live value sources.',
        ];
    }
}