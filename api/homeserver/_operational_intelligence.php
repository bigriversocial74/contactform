<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-campaign-send-service.php';

const MG_HOMESERVER_OPERATIONAL_CONTRACT_VERSION = 2;
const MG_HOMESERVER_OPERATIONAL_MAX_RECORDS = 250;
const MG_HOMESERVER_OPERATIONAL_MAX_EVENTS = 250;

/**
 * Fixed provider catalog. Table and column names are code-owned and never
 * accepted from a HomeServer request. Missing optional tables produce an
 * unavailable/empty dataset rather than arbitrary SQL.
 */
function mg_homeserver_operational_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) return $catalog;

    $commonUses = ['analysis', 'goal_matching', 'reporting', 'recommendations', 'supervised_planning'];
    $catalog = [
        'merchant.profile' => mg_homeserver_dataset('Merchant profile', 'business', $commonUses, [
            mg_homeserver_source('users', 'id', 'id', 'updated_at', ['id','public_id','display_name','name','business_name','email','phone','status','created_at','updated_at']),
        ], ['include_contact_details']),
        'merchant.locations' => mg_homeserver_dataset('Store locations', 'business', $commonUses, [
            mg_homeserver_source('merchant_locations', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','name','address_line1','address_line2','city','state','postal_code','country','latitude','longitude','timezone','hours_json','status','created_at','updated_at']),
            mg_homeserver_source('merchant_profiles', 'user_id', 'id', 'updated_at', ['id','public_id','user_id','business_name','address','city','state','postal_code','latitude','longitude','hours_json','created_at','updated_at']),
        ]),
        'merchant.products' => mg_homeserver_dataset('Products and offers', 'business', $commonUses, [
            mg_homeserver_source('products', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','name','title','description','sku','price_cents','currency','status','quantity','inventory_count','category','metadata_json','created_at','updated_at']),
        ]),
        'merchant.inventory' => mg_homeserver_dataset('Inventory and availability', 'business', $commonUses, [
            mg_homeserver_source('products', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','name','title','sku','status','quantity','inventory_count','quantity_limit','issued_count','created_at','updated_at']),
            mg_homeserver_source('merchant_inventory', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','product_id','sku','quantity','reserved_quantity','available_quantity','status','created_at','updated_at']),
        ]),
        'merchant.staff' => mg_homeserver_dataset('Merchant staff and assignments', 'restricted', $commonUses, [
            mg_homeserver_source('merchant_staff', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','name','email','phone','role','status','location_id','schedule_json','created_at','updated_at']),
        ], ['include_contact_details']),
        'merchant.store_activity' => mg_homeserver_dataset('Store activity', 'business', $commonUses, [
            mg_homeserver_source('merchant_activity_events', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','location_id','event_type','source_type','source_public_id','metadata_json','occurred_at','created_at']),
        ]),
        'reviews.customer_reviews' => mg_homeserver_dataset('Customer reviews', 'restricted', array_merge($commonUses, ['sentiment_analysis','semantic_clustering','service_recovery']), [
            mg_homeserver_source('customer_reviews', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','profile_id','campaign_id','reviewer_user_id','contact_id','wallet_item_id','reviewer_name','rating','review_title','review_body','status','metadata_json','submitted_at','created_at','updated_at']),
            mg_homeserver_source('reviews', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','order_id','product_id','location_id','rating','title','review_text','body','status','source','merchant_response','response_status','created_at','updated_at']),
            mg_homeserver_source('merchant_reviews', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','customer_user_id','order_id','product_id','location_id','rating','title','review_text','body','status','source','merchant_response','response_status','created_at','updated_at']),
            mg_homeserver_source('product_reviews', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','product_id','order_id','rating','title','review_text','body','status','merchant_response','created_at','updated_at']),
        ], ['include_message_bodies']),
        'reviews.resolution_history' => mg_homeserver_dataset('Review responses and resolutions', 'restricted', array_merge($commonUses, ['sentiment_analysis','service_recovery']), [
            mg_homeserver_source('review_responses', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','review_id','response_text','status','resolution_type','campaign_id','contact_id','responded_by_user_id','created_at','updated_at']),
            mg_homeserver_source('review_resolution_events', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','review_id','event_type','resolution_status','context_json','created_at']),
        ], ['include_message_bodies']),
        'conversations.threads' => mg_homeserver_dataset('Conversation threads', 'restricted', array_merge($commonUses, ['conversation_continuity','follow_up']), [
            mg_homeserver_source('merchant_crm_threads', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','contact_id','user_id','channel','subject','status','intent','summary','last_message_at','closed_at','created_at','updated_at']),
            mg_homeserver_source('crm_message_threads', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','contact_id','user_id','channel','subject','status','intent','summary','last_message_at','closed_at','created_at','updated_at']),
        ], ['include_message_bodies']),
        'conversations.messages' => mg_homeserver_dataset('Conversation messages', 'sensitive', array_merge($commonUses, ['conversation_continuity','sentiment_analysis','commitment_extraction','follow_up']), [
            mg_homeserver_source('merchant_crm_messages', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','thread_id','contact_id','user_id','sender_type','channel','subject','body','message','content','delivery_status','sent_at','created_at','updated_at']),
            mg_homeserver_source('crm_messages', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','thread_id','contact_id','user_id','sender_type','channel','subject','body','message','content','delivery_status','sent_at','created_at','updated_at']),
            mg_homeserver_source('messages', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','thread_id','sender_user_id','recipient_user_id','subject','body','message','content','status','sent_at','created_at','updated_at']),
        ], ['include_message_bodies']),
        'conversations.follow_ups' => mg_homeserver_dataset('Conversation commitments and follow-ups', 'restricted', array_merge($commonUses, ['commitment_extraction','follow_up']), [
            mg_homeserver_source('merchant_crm_followups', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','contact_id','thread_id','task_id','status','due_at','completed_at','owner_user_id','summary','context_json','created_at','updated_at']),
            mg_homeserver_source('campaign_followups', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','campaign_id','contact_id','wallet_item_id','trigger_event','status','due_at','completed_at','context_json','created_at','updated_at']),
        ]),
        'crm.contacts' => mg_homeserver_dataset('CRM contacts', 'sensitive', array_merge($commonUses, ['relationship_management','campaign_targeting']), [
            mg_homeserver_source('merchant_crm_contacts', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','primary_email','primary_phone','display_name','lifecycle_stage','crm_status','last_campaign_type','last_source_type','first_seen_at','last_seen_at','last_engaged_at','last_purchased_at','last_reward_issued_at','last_reward_claimed_at','last_reward_redeemed_at','total_purchase_cents','total_rewards_issued','total_rewards_claimed','total_rewards_redeemed','source_summary_json','tags_json','metadata_json','created_at','updated_at']),
            mg_homeserver_source('campaign_contacts', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','campaign_id','user_id','email','phone','name','source','opt_in_status','metadata_json','created_at','updated_at']),
        ], ['include_contact_details']),
        'crm.activities' => mg_homeserver_dataset('CRM activities and timeline', 'restricted', array_merge($commonUses, ['relationship_management','follow_up']), [
            mg_homeserver_source('merchant_crm_activities', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','contact_id','campaign_id','activity_type','subject','body','status','occurred_at','metadata_json','created_at','updated_at']),
            mg_homeserver_source('merchant_crm_contact_events', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','crm_contact_id','campaign_id','campaign_type','event_type','source_type','source_public_id','user_id','email','phone','name','value_cents','metadata_json','created_at']),
        ], ['include_message_bodies']),
        'crm.tasks' => mg_homeserver_dataset('CRM tasks', 'restricted', array_merge($commonUses, ['relationship_management','follow_up']), [
            mg_homeserver_source('merchant_crm_tasks', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','contact_id','assigned_user_id','title','description','status','priority','due_at','completed_at','created_at','updated_at']),
        ]),
        'crm.notes' => mg_homeserver_dataset('CRM notes', 'sensitive', array_merge($commonUses, ['relationship_management','conversation_continuity']), [
            mg_homeserver_source('merchant_crm_notes', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','contact_id','author_user_id','title','body','note','visibility','created_at','updated_at']),
        ], ['include_message_bodies']),
        'crm.consent' => mg_homeserver_dataset('Communication consent', 'sensitive', array_merge($commonUses, ['campaign_targeting','consent_enforcement']), [
            mg_homeserver_source('merchant_crm_contacts', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','email','phone','status','consent_json','preferences_json','created_at','updated_at']),
            mg_homeserver_source('campaign_contacts', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','email','phone','opt_in_status','metadata_json','created_at','updated_at']),
        ], ['include_contact_details']),
        'commerce.orders' => mg_homeserver_dataset('Orders and purchase history', 'sensitive', array_merge($commonUses, ['customer_value','product_affinity','service_recovery']), [
            mg_homeserver_source('orders', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','contact_id','status','subtotal_cents','discount_cents','tax_cents','total_cents','currency','source','campaign_id','location_id','placed_at','fulfilled_at','refunded_at','metadata_json','created_at','updated_at']),
        ], ['include_purchase_history']),
        'commerce.order_items' => mg_homeserver_dataset('Order items and product history', 'sensitive', array_merge($commonUses, ['customer_value','product_affinity','service_recovery']), [
            mg_homeserver_source('order_items', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','order_id','product_id','sku','title','quantity','unit_price_cents','discount_cents','total_cents','metadata_json','created_at','updated_at']),
        ], ['include_purchase_history']),
        'commerce.refunds' => mg_homeserver_dataset('Refund and recovery history', 'sensitive', array_merge($commonUses, ['service_recovery','duplicate_prevention']), [
            mg_homeserver_source('refunds', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','order_id','user_id','contact_id','amount_cents','currency','reason','status','campaign_id','created_at','updated_at']),
        ], ['include_purchase_history']),
        'gifts.ownership' => mg_homeserver_dataset('Gift and Wallet ownership', 'sensitive', array_merge($commonUses, ['gifting_relationships','service_recovery']), [
            mg_homeserver_source('wallet_items', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','user_id','contact_id','merchant_user_id','reward_template_id','campaign_id','source_type','source_id','status','value_cents_snapshot','currency_snapshot','title_snapshot','metadata_json','issued_at','claimed_at','redeemed_at','refunded_at','expires_at','created_at','updated_at']),
        ], ['include_gift_ownership']),
        'gifts.claims' => mg_homeserver_dataset('Gift claims', 'sensitive', array_merge($commonUses, ['gifting_relationships','service_recovery']), [
            mg_homeserver_source('claim_events', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','wallet_item_id','user_id','contact_id','event_type','status','context_json','created_at']),
            mg_homeserver_source('claims', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','wallet_item_id','user_id','contact_id','status','claimed_at','metadata_json','created_at','updated_at']),
        ], ['include_gift_ownership']),
        'gifts.redemptions' => mg_homeserver_dataset('Gift redemptions', 'sensitive', array_merge($commonUses, ['gifting_relationships','service_recovery']), [
            mg_homeserver_source('redemptions', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','wallet_item_id','user_id','contact_id','location_id','status','value_cents','redeemed_at','metadata_json','created_at','updated_at']),
        ], ['include_gift_ownership']),
        'campaigns.definition' => mg_homeserver_dataset('Campaign definitions', 'business', array_merge($commonUses, ['campaign_management','campaign_targeting']), [
            mg_homeserver_source('campaigns', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','title','description','campaign_type','status','reward_template_id','starts_at','ends_at','quantity_limit','issued_count','audience_json','rules_json','metadata_json','created_at','updated_at']),
        ]),
        'campaigns.performance' => mg_homeserver_dataset('Campaign performance and events', 'restricted', array_merge($commonUses, ['campaign_management','campaign_optimization']), [
            mg_homeserver_source('campaign_events', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','campaign_id','wallet_item_id','contact_id','event_type','event_context_json','created_at']),
            mg_homeserver_source('merchant_crm_contact_events', 'merchant_user_id', 'id', 'created_at', ['id','public_id','merchant_user_id','campaign_id','campaign_type','event_type','source_type','source_public_id','user_id','email','name','value_cents','metadata_json','created_at']),
        ]),
        'campaigns.authorizations' => mg_homeserver_dataset('Agent campaign authorizations', 'sensitive', ['analysis','campaign_management','policy_enforcement'], [
            mg_homeserver_source('homeserver_campaign_authorizations', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','device_id','merchant_user_id','campaign_type','authorization_state','authority_level','allowed_campaign_ids_json','allowed_product_ids_json','allowed_channels_json','allowed_audience_rules_json','maximum_value_cents','maximum_daily_value_cents','maximum_total_value_cents','maximum_recipients','approval_threshold_cents','duplicate_window_days','require_consent','require_evidence','allowed_send_start','allowed_send_end','timezone_name','approved_at','expires_at','policy_hash','created_at','updated_at']),
        ]),
        'creator.attribution' => mg_homeserver_dataset('Creator and referral attribution', 'restricted', array_merge($commonUses, ['campaign_optimization']), [
            mg_homeserver_source('creator_campaign_attributions', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','creator_user_id','campaign_id','contact_id','user_id','source','status','value_cents','metadata_json','created_at','updated_at']),
        ]),
    ];
    return $catalog;
}

function mg_homeserver_dataset(string $label, string $classification, array $uses, array $sources, array $requiredFlags = []): array
{
    return [
        'label' => $label,
        'classification' => $classification,
        'authority' => 'microgifter',
        'sync_modes' => ['snapshot','incremental','event'],
        'permitted_uses' => array_values(array_unique($uses)),
        'required_grant_flags' => $requiredFlags,
        'sources' => $sources,
    ];
}

function mg_homeserver_source(string $table, string $merchantColumn, string $idColumn, string $updatedColumn, array $columns): array
{
    return compact('table', 'merchantColumn', 'idColumn', 'updatedColumn', 'columns');
}

function mg_homeserver_operational_tables_ready(PDO $pdo): bool
{
    return mg_homeserver_table_exists($pdo, 'homeserver_dataset_grants')
        && mg_homeserver_table_exists($pdo, 'homeserver_operational_export_receipts')
        && mg_homeserver_table_exists($pdo, 'homeserver_campaign_authorizations')
        && mg_homeserver_table_exists($pdo, 'homeserver_campaign_action_receipts');
}

function mg_homeserver_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return $cache[$table] = ((int)$stmt->fetchColumn() === 1);
}

function mg_homeserver_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return $cache[$table] = array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
}

function mg_homeserver_device_merchant_id(array $device): int
{
    $merchantId = (int)($device['owner_user_id'] ?? 0);
    if ($merchantId <= 0) mg_fail('HomeServer device owner is invalid.', 403);
    return $merchantId;
}

function mg_homeserver_operational_grant(PDO $pdo, array $device, string $datasetKey): array
{
    if (!isset(mg_homeserver_operational_catalog()[$datasetKey])) mg_fail('Operational dataset is not declared by the Microgifter provider.', 422);
    if (!mg_homeserver_operational_tables_ready($pdo)) mg_fail('HomeServer operational intelligence schema is not installed.', 503);
    $stmt = $pdo->prepare("SELECT * FROM homeserver_dataset_grants WHERE device_id=? AND merchant_user_id=? AND dataset_key=? AND grant_state='enabled' LIMIT 1");
    $stmt->execute([(int)$device['id'], mg_homeserver_device_merchant_id($device), $datasetKey]);
    $grant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$grant) mg_fail('This operational dataset is not enabled for the paired HomeServer.', 403);
    foreach (mg_homeserver_operational_catalog()[$datasetKey]['required_grant_flags'] as $flag) {
        if ((int)($grant[$flag] ?? 0) !== 1) mg_fail('The dataset grant does not permit the required sensitive context.', 403, ['required_flag' => $flag]);
    }
    return $grant;
}

function mg_homeserver_operational_manifest(PDO $pdo, array $device): array
{
    $merchantId = mg_homeserver_device_merchant_id($device);
    $grants = [];
    if (mg_homeserver_operational_tables_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT dataset_key,grant_state,classification,permitted_uses_json,permitted_fields_json,retention_days,include_message_bodies,include_contact_details,include_purchase_history,include_gift_ownership,approved_at,updated_at FROM homeserver_dataset_grants WHERE device_id=? AND merchant_user_id=?');
        $stmt->execute([(int)$device['id'], $merchantId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $grants[(string)$row['dataset_key']] = $row;
    }
    $datasets = [];
    foreach (mg_homeserver_operational_catalog() as $key => $definition) {
        $availableSources = [];
        foreach ($definition['sources'] as $source) {
            if (mg_homeserver_table_exists($pdo, $source['table'])) $availableSources[] = $source['table'];
        }
        $grant = $grants[$key] ?? null;
        $datasets[] = [
            'key' => $key,
            'label' => $definition['label'],
            'authority' => 'microgifter',
            'classification' => $definition['classification'],
            'sync_modes' => $definition['sync_modes'],
            'permitted_uses' => $definition['permitted_uses'],
            'required_grant_flags' => $definition['required_grant_flags'],
            'available' => $availableSources !== [],
            'source_contracts' => $availableSources,
            'grant' => $grant ? [
                'state' => (string)$grant['grant_state'],
                'classification' => (string)$grant['classification'],
                'permitted_uses' => json_decode((string)$grant['permitted_uses_json'], true) ?: [],
                'permitted_fields' => json_decode((string)($grant['permitted_fields_json'] ?? 'null'), true),
                'retention_days' => (int)$grant['retention_days'],
                'include_message_bodies' => (bool)$grant['include_message_bodies'],
                'include_contact_details' => (bool)$grant['include_contact_details'],
                'include_purchase_history' => (bool)$grant['include_purchase_history'],
                'include_gift_ownership' => (bool)$grant['include_gift_ownership'],
                'approved_at' => $grant['approved_at'],
                'updated_at' => $grant['updated_at'],
            ] : null,
        ];
    }
    return [
        'provider' => 'microgifter',
        'contract_version' => MG_HOMESERVER_OPERATIONAL_CONTRACT_VERSION,
        'device_id' => (string)$device['public_id'],
        'tenant_id' => (string)$merchantId,
        'site_id' => null,
        'provider_authoritative' => true,
        'imported_copy_is_evidence' => true,
        'raw_payment_credentials_exported' => false,
        'datasets' => $datasets,
        'generated_at' => gmdate(DATE_ATOM),
    ];
}

function mg_homeserver_operational_cursor_decode(?string $cursor): array
{
    if ($cursor === null || trim($cursor) === '') return ['updated_at' => '1970-01-01 00:00:00', 'id' => '0'];
    $decoded = mg_homeserver_base64url_decode(trim($cursor));
    if (!is_string($decoded)) mg_fail('Operational cursor is invalid.', 422);
    $value = json_decode($decoded, true);
    if (!is_array($value) || !isset($value['updated_at'], $value['id']) || strlen((string)$value['updated_at']) > 30 || strlen((string)$value['id']) > 190) mg_fail('Operational cursor is invalid.', 422);
    return ['updated_at' => (string)$value['updated_at'], 'id' => (string)$value['id']];
}

function mg_homeserver_operational_cursor_encode(string $updatedAt, string $id): string
{
    return mg_homeserver_base64url_encode(mg_homeserver_json(['updated_at' => $updatedAt, 'id' => $id]));
}

function mg_homeserver_operational_export(PDO $pdo, array $device, array $input): array
{
    $startedAt = gmdate('Y-m-d H:i:s');
    $datasetKey = strtolower(trim((string)($input['dataset_key'] ?? '')));
    $mode = strtolower(trim((string)($input['mode'] ?? 'incremental')));
    $cursorBefore = isset($input['cursor']) ? trim((string)$input['cursor']) : null;
    $limit = max(1, min(MG_HOMESERVER_OPERATIONAL_MAX_RECORDS, (int)($input['limit'] ?? 100)));
    if (!in_array($mode, ['snapshot','incremental','event'], true)) mg_fail('Unsupported operational export mode.', 422);
    $definition = mg_homeserver_operational_catalog()[$datasetKey] ?? null;
    if (!$definition) mg_fail('Operational dataset is not declared by the Microgifter provider.', 422);
    $grant = mg_homeserver_operational_grant($pdo, $device, $datasetKey);
    $merchantId = mg_homeserver_device_merchant_id($device);
    $cursor = $mode === 'snapshot' ? ['updated_at' => '1970-01-01 00:00:00', 'id' => '0'] : mg_homeserver_operational_cursor_decode($cursorBefore);
    $records = [];
    $lastUpdated = $cursor['updated_at'];
    $lastId = $cursor['id'];

    foreach ($definition['sources'] as $source) {
        if (count($records) >= $limit || !mg_homeserver_table_exists($pdo, $source['table'])) continue;
        $existing = mg_homeserver_table_columns($pdo, $source['table']);
        if (!isset($existing[$source['merchantColumn']], $existing[$source['idColumn']])) continue;
        $updatedColumn = isset($existing[$source['updatedColumn']]) ? $source['updatedColumn'] : (isset($existing['created_at']) ? 'created_at' : null);
        if ($updatedColumn === null) continue;
        $columns = array_values(array_filter($source['columns'], static fn(string $column): bool => isset($existing[$column])));
        if (!in_array($source['idColumn'], $columns, true)) $columns[] = $source['idColumn'];
        if (!in_array($updatedColumn, $columns, true)) $columns[] = $updatedColumn;
        $quoted = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
        $remaining = $limit - count($records);
        $sql = 'SELECT ' . $quoted . ' FROM `' . $source['table'] . '` WHERE `' . $source['merchantColumn'] . '`=? AND (`' . $updatedColumn . '`>? OR (`' . $updatedColumn . '`=? AND CAST(`' . $source['idColumn'] . '` AS CHAR)>?)) ORDER BY `' . $updatedColumn . '` ASC, `' . $source['idColumn'] . '` ASC LIMIT ' . (int)$remaining;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$merchantId, $cursor['updated_at'], $cursor['updated_at'], $cursor['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = mg_homeserver_operational_filter_row($row, $grant);
            $sourceId = (string)($row['public_id'] ?? $row[$source['idColumn']] ?? '');
            $sourceUpdated = (string)($row[$updatedColumn] ?? gmdate('Y-m-d H:i:s'));
            $payloadJson = mg_homeserver_json($row);
            $revision = hash('sha256', $source['table'] . '|' . $sourceId . '|' . $sourceUpdated . '|' . $payloadJson);
            $records[] = [
                'source_object_type' => $source['table'],
                'source_object_id' => $sourceId,
                'source_revision' => $revision,
                'source_updated_at_utc' => gmdate(DATE_ATOM, strtotime($sourceUpdated) ?: time()),
                'payload' => $row,
                'payload_hash' => hash('sha256', $payloadJson),
            ];
            $lastUpdated = $sourceUpdated;
            $lastId = (string)($row[$source['idColumn']] ?? $sourceId);
        }
    }

    $cursorAfter = mg_homeserver_operational_cursor_encode($lastUpdated, $lastId);
    $events = [];
    if ($mode === 'event') {
        foreach ($records as $record) {
            $payload = is_array($record['payload'] ?? null) ? $record['payload'] : [];
            $events[] = [
                'source_event_id' => (string)$record['source_object_type'] . ':' . (string)$record['source_object_id'] . ':' . (string)$record['source_revision'],
                'event_type' => (string)($payload['event_type'] ?? $payload['activity_type'] ?? $payload['trigger_event'] ?? $datasetKey . '.updated'),
                'occurred_at_utc' => (string)$record['source_updated_at_utc'],
                'source_revision' => (string)$record['source_revision'],
                'payload' => $payload,
                'payload_hash' => (string)$record['payload_hash'],
            ];
        }
        $records = [];
    }
    $sourceRevision = hash('sha256', $datasetKey . '|' . $cursorAfter . '|' . count($records) . '|' . count($events));
    $envelope = [
        'provider_key' => 'microgifter',
        'contract_version' => MG_HOMESERVER_OPERATIONAL_CONTRACT_VERSION,
        'device_id' => (string)$device['public_id'],
        'tenant_id' => (string)$merchantId,
        'site_id' => null,
        'dataset_key' => $datasetKey,
        'import_mode' => $mode,
        'cursor_before' => $cursorBefore,
        'cursor_after' => $cursorAfter,
        'source_revision' => $sourceRevision,
        'records' => $records,
        'events' => $events,
        'provider_authoritative' => true,
        'evidence_trust_state' => 'untrusted_provider_evidence',
        'generated_at' => gmdate(DATE_ATOM),
    ];
    $payloadHash = hash('sha256', mg_homeserver_json($envelope));
    $envelope['payload_hash'] = $payloadHash;
    mg_homeserver_operational_record_receipt($pdo, $device, $datasetKey, $mode, $cursorBefore, $cursorAfter, $sourceRevision, count($records), count($events), $payloadHash, ($records === [] && $events === []) ? 'empty' : 'accepted', null, $startedAt);
    return $envelope;
}

function mg_homeserver_operational_filter_row(array $row, array $grant): array
{
    $neverExport = ['password','password_hash','token','token_hash','api_key','secret','private_key','card_number','pan','cvv','cvc','bank_account','routing_number','payment_method_token','processor_token'];
    foreach (array_keys($row) as $field) {
        $lower = strtolower((string)$field);
        if (in_array($lower, $neverExport, true) || str_contains($lower, 'password') || str_contains($lower, 'secret') || str_contains($lower, 'private_key') || str_contains($lower, 'card_number') || str_contains($lower, 'cvv')) unset($row[$field]);
    }
    if (!(bool)$grant['include_message_bodies']) {
        foreach (['body','message','content','review_text','response_text','note','description','summary','subject'] as $field) unset($row[$field]);
    }
    if (!(bool)$grant['include_contact_details']) {
        foreach (['email','phone','address','address_line1','address_line2','birthday','first_name','last_name'] as $field) unset($row[$field]);
    }
    $allowedFields = json_decode((string)($grant['permitted_fields_json'] ?? 'null'), true);
    if (is_array($allowedFields) && $allowedFields !== []) {
        $always = ['id','public_id','merchant_user_id','created_at','updated_at'];
        $row = array_intersect_key($row, array_fill_keys(array_unique(array_merge($allowedFields, $always)), true));
    }
    return $row;
}

function mg_homeserver_operational_record_receipt(PDO $pdo, array $device, string $datasetKey, string $mode, ?string $cursorBefore, ?string $cursorAfter, string $sourceRevision, int $records, int $events, string $payloadHash, string $disposition, ?string $reasonCode, string $requestedAt): void
{
    $stmt = $pdo->prepare('INSERT INTO homeserver_operational_export_receipts (public_id,device_id,merchant_user_id,dataset_key,export_mode,cursor_before,cursor_after,source_revision,record_count,event_count,payload_hash,disposition,reason_code,requested_at,completed_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?,UTC_TIMESTAMP())');
    $stmt->execute([mg_homeserver_public_uuid(), (int)$device['id'], mg_homeserver_device_merchant_id($device), $datasetKey, $mode, $cursorBefore, $cursorAfter, $sourceRevision, $records, $events, $payloadHash, $disposition, $reasonCode, $requestedAt, mg_homeserver_json(['provider_authoritative' => true, 'trust_state' => 'untrusted_provider_evidence'])]);
}

function mg_homeserver_campaign_authorization(PDO $pdo, array $device, string $campaignType): array
{
    if (!mg_homeserver_operational_tables_ready($pdo)) mg_fail('HomeServer campaign authority schema is not installed.', 503);
    $stmt = $pdo->prepare("SELECT * FROM homeserver_campaign_authorizations WHERE device_id=? AND merchant_user_id=? AND campaign_type=? AND authorization_state='enabled' AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) LIMIT 1");
    $stmt->execute([(int)$device['id'], mg_homeserver_device_merchant_id($device), $campaignType]);
    $authorization = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$authorization) mg_fail('The merchant has not authorized this HomeServer campaign type.', 403);
    return $authorization;
}

function mg_homeserver_campaign_action(PDO $pdo, array $device, array $input): array
{
    $merchantId = mg_homeserver_device_merchant_id($device);
    $actionType = strtolower(trim((string)($input['action_type'] ?? '')));
    $campaignType = strtolower(trim((string)($input['campaign_type'] ?? '')));
    $campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
    $contactId = strtolower(trim((string)($input['contact_id'] ?? '')));
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
    $evidence = is_array($input['evidence'] ?? null) ? $input['evidence'] : [];
    $requestedChannel = strtolower(trim((string)($input['channel'] ?? '')));
    $allowedActions = ['campaign.draft','campaign.publish','campaign.pause','campaign.resume','campaign.send_make_good','campaign.send_authorized'];
    if (!in_array($actionType, $allowedActions, true) || preg_match('/^[a-z0-9_]{3,80}$/', $campaignType) !== 1 || $idempotencyKey === '' || strlen($idempotencyKey) > 190) {
        mg_fail('Invalid HomeServer campaign action request.', 422);
    }
    if ($campaignId !== '' && !mg_homeserver_is_uuid($campaignId)) mg_fail('Campaign identity is invalid.', 422);
    if ($contactId !== '' && !mg_homeserver_is_uuid($contactId)) mg_fail('CRM contact identity is invalid.', 422);
    if ($actionType === 'campaign.send_make_good' && $campaignType !== 'customer_refund') mg_fail('Make-Good actions require a Customer Refund campaign.', 422);

    $authorization = mg_homeserver_campaign_authorization($pdo, $device, $campaignType);
    $existingStmt = $pdo->prepare('SELECT * FROM homeserver_campaign_action_receipts WHERE device_id=? AND idempotency_key=? LIMIT 1');
    $existingStmt->execute([(int)$device['id'], $idempotencyKey]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) return ['duplicate' => true, 'receipt' => mg_homeserver_campaign_receipt_payload($existing)];

    $allowedCampaigns = json_decode((string)($authorization['allowed_campaign_ids_json'] ?? 'null'), true);
    if (is_array($allowedCampaigns) && $allowedCampaigns !== [] && !in_array($campaignId, $allowedCampaigns, true)) mg_fail('Campaign is outside the merchant authorization.', 403);
    if ((bool)$authorization['require_evidence'] && $evidence === []) mg_fail('Evidence is required for this campaign action.', 422);

    $sendActions = ['campaign.send_make_good','campaign.send_authorized'];
    $isSend = in_array($actionType, $sendActions, true);
    if (($isSend || in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume'], true)) && $campaignId === '') mg_fail('Campaign identity is required for this action.', 422);
    if ($isSend && $contactId === '') mg_fail('CRM contact identity is required for a campaign send.', 422);

    $actualValueCents = 0;
    $recipientCount = $isSend ? 1 : max(0, (int)($input['recipient_count'] ?? 0));
    $campaign = null;
    $contact = null;
    $channel = $requestedChannel;
    if ($campaignId !== '') {
        $campaignStmt = $pdo->prepare('SELECT c.*,rt.public_id reward_template_public_id,rt.value_amount_cents,rt.currency,rt.status reward_template_status FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.public_id=? AND c.merchant_user_id=? LIMIT 1');
        $campaignStmt->execute([$campaignId, $merchantId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) mg_fail('Merchant campaign was not found.', 404);
        if ((string)$campaign['campaign_type'] !== $campaignType) mg_fail('Campaign type does not match the merchant authorization.', 409);
        $actualValueCents = max(0, (int)($campaign['value_amount_cents'] ?? 0));
    }
    if ($isSend) {
        if (!$campaign || (string)$campaign['status'] !== 'active' || (string)($campaign['reward_template_status'] ?? '') !== 'active') mg_fail('Authorized campaign and reward must be active before sending.', 409);
        $contactStmt = $pdo->prepare('SELECT * FROM campaign_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $contactStmt->execute([$contactId, $merchantId]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) mg_fail('Merchant CRM contact was not found.', 404);
        $hasAccount = mg_crm_campaign_send_contact_has_account($pdo, $merchantId, $contactId);
        $channel = $channel !== '' ? $channel : ($hasAccount ? 'microgifter_inbox' : 'email');
        if ((bool)$authorization['require_consent']) {
            $consent = strtolower(trim((string)($contact['opt_in_status'] ?? '')));
            if (!in_array($consent, ['opted_in','subscribed','consented'], true)) mg_fail('The CRM contact has not consented to this campaign channel.', 403);
        }
    }

    $allowedChannels = json_decode((string)$authorization['allowed_channels_json'], true) ?: [];
    if (($isSend || $requestedChannel !== '') && !in_array($channel, $allowedChannels, true)) mg_fail('Campaign channel is outside the merchant authorization.', 403);
    if ($authorization['maximum_value_cents'] !== null && $actualValueCents > (int)$authorization['maximum_value_cents']) mg_fail('Campaign value exceeds the per-recipient authorization.', 403);
    if ($authorization['maximum_recipients'] !== null && $recipientCount > (int)$authorization['maximum_recipients']) mg_fail('Campaign audience exceeds the authorization.', 403);

    if ($isSend && ($authorization['allowed_send_start'] !== null || $authorization['allowed_send_end'] !== null)) {
        $zone = new DateTimeZone((string)$authorization['timezone_name']);
        $now = new DateTimeImmutable('now', $zone);
        $clock = $now->format('H:i:s');
        $startTime = (string)($authorization['allowed_send_start'] ?? '00:00:00');
        $endTime = (string)($authorization['allowed_send_end'] ?? '23:59:59');
        $inside = $startTime <= $endTime ? ($clock >= $startTime && $clock <= $endTime) : ($clock >= $startTime || $clock <= $endTime);
        if (!$inside) mg_fail('Campaign send is outside the merchant-authorized sending hours.', 403);
    }

    if ($isSend && (int)$authorization['duplicate_window_days'] > 0) {
        $duplicateStmt = $pdo->prepare("SELECT public_id FROM homeserver_campaign_action_receipts WHERE merchant_user_id=? AND campaign_type=? AND contact_public_id=? AND action_type IN ('campaign.send_make_good','campaign.send_authorized') AND disposition='executed' AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY) LIMIT 1");
        $duplicateStmt->execute([$merchantId, $campaignType, $contactId, (int)$authorization['duplicate_window_days']]);
        if ($duplicateStmt->fetchColumn()) mg_fail('A matching authorized campaign was already sent inside the duplicate-prevention window.', 409);
    }

    $spentStmt = $pdo->prepare("SELECT COALESCE(SUM(value_cents),0) total_spent,COALESCE(SUM(CASE WHEN created_at>=UTC_DATE() THEN value_cents ELSE 0 END),0) daily_spent FROM homeserver_campaign_action_receipts WHERE authorization_id=? AND disposition='executed'");
    $spentStmt->execute([(int)$authorization['id']]);
    $spent = $spentStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_spent' => 0, 'daily_spent' => 0];
    if ($authorization['maximum_daily_value_cents'] !== null && ((int)$spent['daily_spent'] + ($actualValueCents * $recipientCount)) > (int)$authorization['maximum_daily_value_cents']) mg_fail('Campaign action exceeds the merchant-authorized daily value.', 403);
    if ($authorization['maximum_total_value_cents'] !== null && ((int)$spent['total_spent'] + ($actualValueCents * $recipientCount)) > (int)$authorization['maximum_total_value_cents']) mg_fail('Campaign action exceeds the merchant-authorized total value.', 403);

    $authorityLevel = (string)$authorization['authority_level'];
    $requiresApproval = $authorityLevel === 'approval_required'
        || ($authorization['approval_threshold_cents'] !== null && $actualValueCents > (int)$authorization['approval_threshold_cents']);
    if ($isSend && !in_array($authorityLevel, ['approval_required','authorized_execution'], true)) mg_fail('This campaign authorization does not permit sending.', 403);
    if (in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume'], true) && !in_array($authorityLevel, ['approval_required','authorized_execution'], true)) mg_fail('This campaign authorization does not permit provider changes.', 403);
    if ($authorityLevel === 'authorized_execution') $requiresApproval = false;

    $request = $input;
    unset($request['merchant_approval_token'], $request['merchant_approval_hash'], $request['value_cents']);
    $request['provider_calculated_value_cents'] = $actualValueCents;
    $request['provider_selected_channel'] = $channel !== '' ? $channel : null;
    $requestHash = hash('sha256', mg_homeserver_json($request));
    $receiptId = mg_homeserver_public_uuid();
    $disposition = $actionType === 'campaign.draft' ? 'drafted' : ($requiresApproval ? 'awaiting_approval' : 'executed');
    $providerResponse = null;

    if (!$requiresApproval && $actionType !== 'campaign.draft') {
        if (in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume'], true)) {
            $status = match ($actionType) { 'campaign.publish','campaign.resume' => 'active', 'campaign.pause' => 'paused' };
            $stmt = $pdo->prepare('UPDATE campaigns SET status=?,updated_at=UTC_TIMESTAMP() WHERE public_id=? AND merchant_user_id=?');
            $stmt->execute([$status, $campaignId, $merchantId]);
            if ($stmt->rowCount() !== 1) mg_fail('Merchant campaign could not be updated.', 409);
            $providerResponse = ['campaign_id' => $campaignId, 'status' => $status, 'authority' => 'microgifter'];
        } elseif ($isSend) {
            $merchantStmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
            $merchantStmt->execute([$merchantId]);
            $merchantUser = $merchantStmt->fetch(PDO::FETCH_ASSOC);
            if (!$merchantUser) mg_fail('Merchant account is unavailable.', 404);
            try {
                $providerResponse = mg_crm_campaign_send_for_contact($pdo, $merchantId, $merchantUser, [
                    'contact_id' => $contactId,
                    'campaign_id' => $campaignId,
                    'reward_template_id' => (string)($campaign['reward_template_public_id'] ?? ''),
                    'required_campaign_type' => $campaignType,
                    'note' => trim((string)($input['message'] ?? $input['note'] ?? '')),
                    'idempotency_key' => 'homeserver:' . $idempotencyKey,
                ]);
            } catch (MgCrmCampaignSendException $error) {
                mg_fail($error->getMessage(), $error->httpStatus, $error->context);
            }
        }
    }

    $stmt = $pdo->prepare('INSERT INTO homeserver_campaign_action_receipts (public_id,device_id,merchant_user_id,authorization_id,idempotency_key,action_type,campaign_type,campaign_public_id,contact_public_id,evidence_json,request_json,request_hash,policy_hash,disposition,reason_code,provider_response_json,value_cents,recipient_count,executed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $stmt->execute([$receiptId, (int)$device['id'], $merchantId, (int)$authorization['id'], $idempotencyKey, $actionType, $campaignType, $campaignId ?: null, $contactId ?: null, mg_homeserver_json($evidence), mg_homeserver_json($request), $requestHash, (string)$authorization['policy_hash'], $disposition, $requiresApproval ? 'merchant_approval_required' : null, $providerResponse === null ? null : mg_homeserver_json($providerResponse), $actualValueCents * max(1, $recipientCount), $recipientCount, $disposition === 'executed' ? gmdate('Y-m-d H:i:s') : null]);
    return [
        'duplicate' => false,
        'receipt' => [
            'receipt_id' => $receiptId,
            'action_type' => $actionType,
            'campaign_type' => $campaignType,
            'campaign_id' => $campaignId ?: null,
            'contact_id' => $contactId ?: null,
            'channel' => $channel !== '' ? $channel : null,
            'value_cents' => $actualValueCents,
            'disposition' => $disposition,
            'reason_code' => $requiresApproval ? 'merchant_approval_required' : null,
            'request_hash' => $requestHash,
            'policy_hash' => (string)$authorization['policy_hash'],
            'provider_response' => $providerResponse,
            'authority' => 'microgifter',
            'created_at' => gmdate(DATE_ATOM),
        ],
    ];
}

function mg_homeserver_campaign_receipt_payload(array $row): array
{
    return [
        'receipt_id' => (string)$row['public_id'],
        'action_type' => (string)$row['action_type'],
        'campaign_type' => (string)$row['campaign_type'],
        'campaign_id' => $row['campaign_public_id'] ?: null,
        'contact_id' => $row['contact_public_id'] ?: null,
        'disposition' => (string)$row['disposition'],
        'reason_code' => $row['reason_code'] ?: null,
        'request_hash' => (string)$row['request_hash'],
        'policy_hash' => (string)$row['policy_hash'],
        'provider_response' => json_decode((string)($row['provider_response_json'] ?? 'null'), true),
        'authority' => 'microgifter',
        'created_at' => $row['created_at'],
    ];
}
