<?php
declare(strict_types=1);

require_once __DIR__ . '/_loyalty_quest_operations.php';

function mg_admin_system_health_runtime_probe(
    PDO $pdo,
    string $key,
    string $label,
    array $warningTypes,
    callable $probe
): array {
    $started = microtime(true);
    try {
        $details = $probe($pdo);
        if (!is_array($details)) $details = [];
        return [
            'key' => $key,
            'label' => $label,
            'ready' => true,
            'status' => 'healthy',
            'summary' => $label . ' is responding.',
            'warning_types' => array_values($warningTypes),
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'details' => $details,
            'error_class' => null,
            'error_code' => null,
        ];
    } catch (Throwable $error) {
        return [
            'key' => $key,
            'label' => $label,
            'ready' => false,
            'status' => 'critical',
            'summary' => $label . ' requires attention.',
            'warning_types' => array_values($warningTypes),
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'details' => [],
            'error_class' => $error::class,
            'error_code' => (string)$error->getCode(),
        ];
    }
}

function mg_admin_system_health_runtime_columns(PDO $pdo, string $table): array
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) {
        throw new InvalidArgumentException('Invalid runtime probe table.');
    }
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    $stmt->execute([$table]);
    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
}

function mg_admin_system_health_runtime_require_columns(PDO $pdo, array $requirements): array
{
    $checked = [];
    foreach ($requirements as $table => $columns) {
        $found = mg_admin_system_health_runtime_columns($pdo, (string)$table);
        if ($found === []) throw new RuntimeException('Required runtime table is unavailable.');
        foreach ($columns as $column) {
            if (!in_array($column, $found, true)) {
                throw new RuntimeException('Required runtime column is unavailable.');
            }
        }
        $checked[(string)$table] = count($columns);
    }
    return ['tables' => count($checked), 'columns' => array_sum($checked)];
}

function mg_admin_system_health_runtime_execute(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ['query_prepared' => true];
}

function mg_admin_system_health_runtime_checks(PDO $pdo): array
{
    $checks = [];

    $checks['loyalty_quest_integrity_schema'] = mg_admin_system_health_runtime_probe(
        $pdo,
        'loyalty_quest_integrity_schema',
        'Loyalty Quest integrity schema',
        ['loyalty.quest.integrity_schema_missing'],
        static fn(PDO $pdo): array => mg_admin_system_health_runtime_require_columns($pdo, [
            'loyalty_quest_integrity_attempts' => ['participant_user_id','action_type','ip_hash','device_hash','request_hash','created_at'],
            'loyalty_quest_integrity_signals' => ['campaign_id','participant_user_id','evidence_id','signal_type','severity','score','status'],
            'loyalty_quest_evidence' => ['evidence_fingerprint','ip_hash','device_hash','integrity_score','integrity_status'],
        ])
    );

    $checks['loyalty_quest_integrity_pepper'] = mg_admin_system_health_runtime_probe(
        $pdo,
        'loyalty_quest_integrity_pepper',
        'Loyalty Quest integrity secret',
        ['loyalty.quest.integrity_pepper_missing'],
        static function (PDO $pdo): array {
            $pepper = trim((string)(getenv('MG_LOYALTY_QUEST_INTEGRITY_PEPPER') ?: ''));
            $source = 'environment';
            if ($pepper === '') {
                $pepper = trim((string)mg_config_value('security', 'claim_code_pepper', ''));
                $source = 'security.claim_code_pepper';
            }
            if (strlen($pepper) < 24) throw new RuntimeException('Integrity secret is not configured.');
            return ['configured' => true, 'source' => $source, 'minimum_length_met' => true];
        }
    );

    $checks['loyalty_quest_public_marketplace'] = mg_admin_system_health_runtime_probe(
        $pdo,
        'loyalty_quest_public_marketplace',
        'Public Loyalty Quest marketplace',
        ['public.loyalty_quests.unavailable'],
        static fn(PDO $pdo): array => mg_admin_system_health_runtime_execute($pdo, "SELECT c.public_id,c.public_slug,c.title,c.description,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.rules_json,c.created_at,c.updated_at,
            rt.title reward_title,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,rt.quantity_limit reward_quantity_limit,rt.issued_count reward_issued_count,
            COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name,
            pp.slug merchant_slug,pp.avatar_url merchant_avatar,pp.headline merchant_headline,
            ml.public_id location_public_id,ml.name location_name,ml.city,ml.region,ml.postal_code,ml.address_line1,ml.metadata_json location_metadata
            FROM campaigns c
            INNER JOIN users u ON u.id=c.merchant_user_id AND u.status='active'
            LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.status='active'
            LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility='public'
            LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
            LEFT JOIN merchant_locations ml ON ml.merchant_user_id=c.merchant_user_id AND ml.status='active' AND ml.public_id=JSON_UNQUOTE(JSON_EXTRACT(c.rules_json,'$.location_id'))
            WHERE 1=0 LIMIT 0")
    );

    $checks['loyalty_quest_account_portfolio'] = mg_admin_system_health_runtime_probe(
        $pdo,
        'loyalty_quest_account_portfolio',
        'Participant Loyalty Quest portfolio',
        ['account.loyalty_quests.unavailable'],
        static fn(PDO $pdo): array => mg_admin_system_health_runtime_execute($pdo, "SELECT lqp.public_id,lqp.status,lqp.progress_count,lqp.required_count,lqp.completion_percent,lqp.joined_at,lqp.started_at,lqp.submitted_at,lqp.reviewed_at,lqp.completed_at,lqp.last_activity_at,
            c.public_id campaign_public_id,c.public_slug,c.title,c.description,c.status campaign_status,c.ends_at,c.rules_json,
            COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name,
            rt.title reward_title,wi.public_id wallet_item_public_id,wi.status wallet_item_status,wi.expires_at wallet_expires_at,
            (SELECT COUNT(*) FROM loyalty_quest_evidence lqe WHERE lqe.participation_id=lqp.id AND lqe.participant_user_id=lqp.participant_user_id) evidence_count,
            (SELECT lqe.review_note FROM loyalty_quest_evidence lqe WHERE lqe.participation_id=lqp.id AND lqe.participant_user_id=lqp.participant_user_id AND lqe.review_note IS NOT NULL ORDER BY lqe.updated_at DESC,lqe.id DESC LIMIT 1) latest_review_note,
            (SELECT lqe.status FROM loyalty_quest_evidence lqe WHERE lqe.participation_id=lqp.id AND lqe.participant_user_id=lqp.participant_user_id ORDER BY lqe.created_at DESC,lqe.id DESC LIMIT 1) latest_evidence_status
            FROM loyalty_quest_participations lqp
            INNER JOIN campaigns c ON c.id=lqp.campaign_id AND c.campaign_type='loyalty_quest'
            INNER JOIN users u ON u.id=lqp.merchant_user_id
            LEFT JOIN public_profiles pp ON pp.user_id=lqp.merchant_user_id
            LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=lqp.merchant_user_id
            LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
            LEFT JOIN wallet_items wi ON wi.id=lqp.wallet_item_id
            WHERE lqp.participant_user_id=? AND 1=0 LIMIT 0", [0])
    );

    $checks['loyalty_quest_admin_operations'] = mg_admin_system_health_runtime_probe(
        $pdo,
        'loyalty_quest_admin_operations',
        'Loyalty Quest admin operations',
        ['admin.loyalty_quest_operations_load_failed'],
        static function (PDO $pdo): array {
            $campaigns = mg_lqo_campaigns($pdo, 'all', '', 1);
            $evidence = mg_lqo_evidence_queue($pdo, 1);
            $deliveries = mg_lqo_delivery_queue($pdo, 1);
            $events = mg_lqo_recent_events($pdo, 1);
            return [
                'campaign_query' => true,
                'evidence_query' => true,
                'delivery_query' => true,
                'activity_query' => true,
                'delivery_ready' => (bool)($campaigns['delivery_ready'] ?? false),
                'sample_counts' => [
                    'campaigns' => count($campaigns['items'] ?? []),
                    'evidence' => count($evidence),
                    'deliveries' => count($deliveries),
                    'events' => count($events),
                ],
            ];
        }
    );

    $checks['campaign_ads_catalog_picker'] = mg_admin_system_health_runtime_probe(
        $pdo,
        'campaign_ads_catalog_picker',
        'Campaign Ads catalog picker',
        ['ads.picker_catalog_products_failed'],
        static fn(PDO $pdo): array => mg_admin_system_health_runtime_execute($pdo, "SELECT p.public_id,p.product_type,p.slug,p.status,p.updated_at,v.title,v.description,v.unit_value_cents,v.currency,a.public_id asset_public_id,a.storage_provider,a.storage_key
            FROM catalog_products p
            LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
            LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=p.current_version_id AND pva.role IN ('cover','thumbnail','gallery')
            LEFT JOIN catalog_assets a ON a.id=pva.asset_id AND a.asset_type='image' AND a.status='ready'
            WHERE 1=0
            GROUP BY p.id LIMIT 0")
    );

    $warningState = [];
    foreach ($checks as $check) {
        foreach ($check['warning_types'] as $type) {
            $warningState[(string)$type] = [
                'active' => !$check['ready'],
                'check_key' => $check['key'],
                'summary' => $check['summary'],
            ];
        }
    }

    $loyaltyKeys = [
        'loyalty_quest_integrity_schema',
        'loyalty_quest_integrity_pepper',
        'loyalty_quest_public_marketplace',
        'loyalty_quest_account_portfolio',
        'loyalty_quest_admin_operations',
    ];
    $loyaltyReady = !array_filter($loyaltyKeys, static fn(string $key): bool => empty($checks[$key]['ready']));
    $adsReady = !empty($checks['campaign_ads_catalog_picker']['ready']);
    $failed = array_values(array_map(
        static fn(array $check): string => (string)$check['key'],
        array_filter($checks, static fn(array $check): bool => empty($check['ready']))
    ));

    return [
        'ready' => $failed === [],
        'generated_at' => gmdate('c'),
        'counts' => [
            'checks' => count($checks),
            'healthy' => count($checks) - count($failed),
            'failed' => count($failed),
        ],
        'groups' => [
            'loyalty_quests' => [
                'ready' => $loyaltyReady,
                'status' => $loyaltyReady ? 'healthy' : 'critical',
                'check_count' => count($loyaltyKeys),
            ],
            'campaign_ads_catalog' => [
                'ready' => $adsReady,
                'status' => $adsReady ? 'healthy' : 'warning',
                'check_count' => 1,
            ],
        ],
        'failed_checks' => $failed,
        'checks' => array_values($checks),
        'warning_state' => $warningState,
    ];
}
