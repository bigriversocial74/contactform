<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_provider_credentials.php';

const MG_COMMISSION_RULE_VERSION = 'merchant-bundle-commission-v1';
const MG_COMMISSION_PLATFORM_SETTINGS_KEY = 'default';

final class MgCommissionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_commission_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_commission_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return false;
    static $cache = [];
    $key = spl_object_id($pdo) . '|' . strtolower($table);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $stmt->execute([$table]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function mg_commission_schema_ready(PDO $pdo): bool
{
    foreach ([
        'commission_platform_settings','commission_platform_history','merchant_commission_profiles',
        'merchant_commission_history','bundle_commission_profiles','bundle_commission_participant_terms',
        'checkout_draft_commission_snapshots','commerce_order_commission_snapshots',
        'commerce_order_item_commission_snapshots',
    ] as $table) {
        if (!mg_commission_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_commission_require_schema(PDO $pdo): void
{
    if (!mg_commission_schema_ready($pdo)) {
        throw new MgCommissionException('Commission setup is incomplete. Import database/20260719_merchant_bundle_commission_authority_v1.sql, then retry.', 409);
    }
}

function mg_commission_normalize_bps(mixed $value, string $label = 'Commission rate'): int
{
    if (!is_numeric($value)) throw new InvalidArgumentException($label . ' is required.');
    $bps = (int)$value;
    if ($bps < 0 || $bps > 10000) throw new InvalidArgumentException($label . ' must be between 0 and 10000 basis points.');
    return $bps;
}

function mg_commission_mysql_datetime(mixed $value, ?string $fallback = null): ?string
{
    $text = trim((string)$value);
    if ($text === '') return $fallback;
    $timestamp = strtotime($text);
    if ($timestamp === false) throw new InvalidArgumentException('Invalid commission effective date.');
    return date('Y-m-d H:i:s', $timestamp);
}

function mg_commission_platform_settings(PDO $pdo, bool $forUpdate = false): array
{
    mg_commission_require_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM commission_platform_settings WHERE settings_key=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([MG_COMMISSION_PLATFORM_SETTINGS_KEY]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    $provider = mg_payment_platform_config($pdo, 'stripe', mg_payment_mode());
    $startingBps = max(0, min(10000, (int)($provider['platform_fee_bps'] ?? 1500)));
    $pdo->prepare('INSERT INTO commission_platform_settings (public_id,settings_key,starting_commission_bps,rule_version,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
        ->execute([mg_public_uuid(), MG_COMMISSION_PLATFORM_SETTINGS_KEY, $startingBps, MG_COMMISSION_RULE_VERSION]);
    $stmt->execute([MG_COMMISSION_PLATFORM_SETTINGS_KEY]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Unable to initialize platform commission settings.');
    return $row;
}

function mg_commission_platform_starting_bps(PDO $pdo): int
{
    return (int)mg_commission_platform_settings($pdo)['starting_commission_bps'];
}

function mg_commission_update_platform_starting_rate(PDO $pdo, int $rateBps, int $actorUserId, string $reason): array
{
    $rateBps = mg_commission_normalize_bps($rateBps, 'Platform starting commission');
    $reason = trim($reason) ?: 'Platform commission setting updated.';
    $settings = mg_commission_platform_settings($pdo, true);
    $previous = (int)$settings['starting_commission_bps'];
    if ($previous === $rateBps) return $settings + ['changed' => false];

    $pdo->prepare('UPDATE commission_platform_settings SET starting_commission_bps=?,rule_version=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
        ->execute([$rateBps, MG_COMMISSION_RULE_VERSION, $actorUserId, (int)$settings['id']]);
    $pdo->prepare('INSERT INTO commission_platform_history (public_id,settings_id,previous_commission_bps,new_commission_bps,change_reason,changed_by_user_id,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(), (int)$settings['id'], $previous, $rateBps, mb_substr($reason, 0, 500), $actorUserId]);
    $pdo->prepare("UPDATE payment_platform_credentials SET platform_fee_bps=?,updated_by_user_id=?,updated_at=NOW() WHERE provider_key='stripe'")
        ->execute([$rateBps, $actorUserId]);
    $settings['starting_commission_bps'] = $rateBps;
    return $settings + ['changed' => true, 'previous_commission_bps' => $previous];
}

function mg_commission_profile_modes(): array
{
    return ['fixed_merchant_rate','follow_platform_default','promotional_rate','contract_rate'];
}

function mg_commission_effective_profile(PDO $pdo, int $merchantUserId, ?string $at = null, bool $forUpdate = false): ?array
{
    mg_commission_require_schema($pdo);
    if ($merchantUserId < 1) throw new InvalidArgumentException('Valid merchant is required.');
    $at = mg_commission_mysql_datetime($at, date('Y-m-d H:i:s'));
    $stmt = $pdo->prepare("SELECT * FROM merchant_commission_profiles WHERE merchant_user_id=? AND status IN ('active','scheduled') AND effective_from<=? AND (effective_until IS NULL OR effective_until>?) ORDER BY effective_from DESC,version_number DESC,id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$merchantUserId, $at, $at]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_commission_latest_profile(PDO $pdo, int $merchantUserId, bool $forUpdate = false): ?array
{
    mg_commission_require_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM merchant_commission_profiles WHERE merchant_user_id=? ORDER BY version_number DESC,id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$merchantUserId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_commission_initialize_merchant_profile(PDO $pdo, int $merchantUserId, ?int $actorUserId = null): array
{
    mg_commission_require_schema($pdo);
    $existing = mg_commission_latest_profile($pdo, $merchantUserId, true);
    if ($existing) return $existing + ['initialized' => false];
    $user = $pdo->prepare('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE');
    $user->execute([$merchantUserId]);
    if (!$user->fetchColumn()) throw new MgCommissionException('Merchant account not found.', 404);

    $rate = mg_commission_platform_starting_bps($pdo);
    $reason = 'Initialized from the platform starting commission at merchant activation.';
    try {
        $pdo->prepare("INSERT INTO merchant_commission_profiles (public_id,merchant_user_id,version_number,rate_mode,default_commission_bps,initialized_from_platform_bps,effective_from,effective_until,status,reason,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,1,'fixed_merchant_rate',?,?,NOW(),NULL,'active',?,?,?,NOW(),NOW())")
            ->execute([mg_public_uuid(), $merchantUserId, $rate, $rate, $reason, $actorUserId, $actorUserId]);
    } catch (PDOException $error) {
        if ((string)$error->getCode() !== '23000') throw $error;
        $concurrent = mg_commission_latest_profile($pdo, $merchantUserId);
        if ($concurrent) return $concurrent + ['initialized' => false, 'concurrent_initialization' => true];
        throw $error;
    }
    $profileId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO merchant_commission_history (public_id,merchant_user_id,previous_profile_id,new_profile_id,previous_commission_bps,new_commission_bps,previous_rate_mode,new_rate_mode,effective_from,effective_until,change_reason,changed_by_user_id,created_at) VALUES (?,?,?,?,NULL,?,NULL,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(), $merchantUserId, null, $profileId, $rate, 'fixed_merchant_rate', date('Y-m-d H:i:s'), null, $reason, $actorUserId]);
    $created = mg_commission_latest_profile($pdo, $merchantUserId);
    if (!$created) throw new RuntimeException('Unable to initialize merchant commission profile.');
    return $created + ['initialized' => true];
}

function mg_commission_save_merchant_profile(PDO $pdo, int $merchantUserId, array $input, int $actorUserId): array
{
    mg_commission_require_schema($pdo);
    if ($merchantUserId < 1) throw new InvalidArgumentException('Valid merchant is required.');
    $mode = strtolower(trim((string)($input['rate_mode'] ?? 'fixed_merchant_rate')));
    if (!in_array($mode, mg_commission_profile_modes(), true)) throw new InvalidArgumentException('Invalid merchant commission rate mode.');
    $rate = $mode === 'follow_platform_default' ? null : mg_commission_normalize_bps($input['commission_rate_bps'] ?? null, 'Merchant commission rate');
    $from = mg_commission_mysql_datetime($input['effective_from'] ?? '', date('Y-m-d H:i:s'));
    $until = mg_commission_mysql_datetime($input['effective_until'] ?? '', null);
    if ($until !== null && strtotime($until) <= strtotime((string)$from)) throw new InvalidArgumentException('Commission end date must be after its effective date.');
    $reason = trim((string)($input['reason'] ?? ''));
    if ($reason === '' && $mode !== 'follow_platform_default') throw new InvalidArgumentException('A reason is required for a merchant-specific commission rate.');
    if ($reason === '') $reason = 'Merchant follows the platform starting commission.';

    $user = $pdo->prepare('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE');
    $user->execute([$merchantUserId]);
    if (!$user->fetchColumn()) throw new MgCommissionException('Merchant account not found.', 404);
    $latest = mg_commission_latest_profile($pdo, $merchantUserId, true);
    $current = mg_commission_effective_profile($pdo, $merchantUserId, null, true);
    $version = $latest ? (int)$latest['version_number'] + 1 : 1;

    $overlap = $pdo->prepare("SELECT id FROM merchant_commission_profiles WHERE merchant_user_id=? AND status IN ('active','scheduled') AND effective_from<COALESCE(?,'9999-12-31 23:59:59') AND COALESCE(effective_until,'9999-12-31 23:59:59')>? ORDER BY effective_from,id FOR UPDATE");
    $overlap->execute([$merchantUserId, $until, $from]);
    foreach ($overlap->fetchAll(PDO::FETCH_COLUMN) as $candidateId) {
        if ($current && (int)$candidateId === (int)$current['id']) continue;
        throw new MgCommissionException('The proposed commission period overlaps an existing scheduled merchant rate.', 409);
    }

    $status = strtotime((string)$from) <= time() ? 'active' : 'scheduled';
    if ($current) {
        $pdo->prepare("UPDATE merchant_commission_profiles SET effective_until=CASE WHEN effective_until IS NULL OR effective_until>? THEN ? ELSE effective_until END,status=CASE WHEN ?='active' THEN 'retired' ELSE status END,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$from, $from, $status, $actorUserId, (int)$current['id']]);
    }
    $pdo->prepare('INSERT INTO merchant_commission_profiles (public_id,merchant_user_id,version_number,rate_mode,default_commission_bps,initialized_from_platform_bps,effective_from,effective_until,status,reason,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute([mg_public_uuid(), $merchantUserId, $version, $mode, $rate, mg_commission_platform_starting_bps($pdo), $from, $until, $status, mb_substr($reason,0,500), $actorUserId, $actorUserId]);
    $newId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO merchant_commission_history (public_id,merchant_user_id,previous_profile_id,new_profile_id,previous_commission_bps,new_commission_bps,previous_rate_mode,new_rate_mode,effective_from,effective_until,change_reason,changed_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(), $merchantUserId, $current['id'] ?? null, $newId, $current['default_commission_bps'] ?? null, $rate, $current['rate_mode'] ?? null, $mode, $from, $until, mb_substr($reason,0,500), $actorUserId]);
    $stmt = $pdo->prepare('SELECT * FROM merchant_commission_profiles WHERE id=? LIMIT 1');
    $stmt->execute([$newId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_commission_resolve_merchant_rate(PDO $pdo, int $merchantUserId, array $context = []): array
{
    $profile = mg_commission_effective_profile($pdo, $merchantUserId, $context['at'] ?? null);
    if (!$profile && (!array_key_exists('initialize',$context) || !empty($context['initialize']))) {
        $profile = mg_commission_initialize_merchant_profile($pdo, $merchantUserId, $context['actor_user_id'] ?? null);
    }
    $platform = mg_commission_platform_starting_bps($pdo);
    if (!$profile) return [
        'merchant_user_id'=>$merchantUserId,'commission_rate_bps'=>$platform,'rate_mode'=>'platform_starting_rate',
        'rate_source'=>'platform_starting_fallback','profile_id'=>null,'profile_public_id'=>null,'profile_version'=>null,
        'effective_from'=>null,'effective_until'=>null,'rule_version'=>MG_COMMISSION_RULE_VERSION,
    ];
    $follows = (string)$profile['rate_mode'] === 'follow_platform_default';
    return [
        'merchant_user_id'=>$merchantUserId,'commission_rate_bps'=>$follows ? $platform : (int)$profile['default_commission_bps'],
        'rate_mode'=>(string)$profile['rate_mode'],'rate_source'=>$follows ? 'merchant_follows_platform' : 'merchant_commission_profile',
        'profile_id'=>(int)$profile['id'],'profile_public_id'=>(string)$profile['public_id'],'profile_version'=>(int)$profile['version_number'],
        'effective_from'=>$profile['effective_from'],'effective_until'=>$profile['effective_until'],'rule_version'=>MG_COMMISSION_RULE_VERSION,
    ];
}

function mg_commission_save_bundle_profile(PDO $pdo, string $bundleReference, array $input, int $actorUserId): array
{
    mg_commission_require_schema($pdo);
    $bundleReference = trim($bundleReference);
    if ($bundleReference === '' || mb_strlen($bundleReference) > 190) throw new InvalidArgumentException('Valid bundle reference is required.');
    $mode = strtolower(trim((string)($input['commission_mode'] ?? 'merchant_default')));
    if (!in_array($mode, ['merchant_default','bundle_starting_rate','custom_participant_rates'], true)) throw new InvalidArgumentException('Invalid bundle commission mode.');
    $starting = $mode === 'bundle_starting_rate'
        ? (isset($input['starting_commission_bps']) && $input['starting_commission_bps'] !== '' ? mg_commission_normalize_bps($input['starting_commission_bps'],'Bundle starting commission') : mg_commission_platform_starting_bps($pdo))
        : null;
    $status = strtolower(trim((string)($input['status'] ?? 'draft')));
    if (!in_array($status, ['draft','locked'], true)) throw new InvalidArgumentException('Invalid bundle commission profile status.');
    $latest = $pdo->prepare('SELECT * FROM bundle_commission_profiles WHERE bundle_reference=? ORDER BY version_number DESC,id DESC LIMIT 1 FOR UPDATE');
    $latest->execute([$bundleReference]);
    $previous = $latest->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($previous && (string)$previous['status'] !== 'superseded') $pdo->prepare("UPDATE bundle_commission_profiles SET status='superseded',updated_by_user_id=?,updated_at=NOW() WHERE id=?")->execute([$actorUserId,(int)$previous['id']]);
    $pdo->prepare('INSERT INTO bundle_commission_profiles (public_id,bundle_reference,version_number,commission_mode,starting_commission_bps,status,reason,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute([mg_public_uuid(),$bundleReference,$previous ? (int)$previous['version_number']+1 : 1,$mode,$starting,$status,mb_substr(trim((string)($input['reason'] ?? '')) ?: 'Bundle commission terms configured.',0,500),$actorUserId,$actorUserId]);
    $stmt = $pdo->prepare('SELECT * FROM bundle_commission_profiles WHERE id=? LIMIT 1');
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_commission_save_bundle_participant_terms(PDO $pdo, int $bundleProfileId, int $merchantUserId, array $input, int $actorUserId): array
{
    mg_commission_require_schema($pdo);
    if ($bundleProfileId < 1 || $merchantUserId < 1) throw new InvalidArgumentException('Valid bundle profile and merchant are required.');
    $proposed = mg_commission_normalize_bps($input['proposed_commission_bps'] ?? null, 'Proposed bundle commission');
    $status = strtolower(trim((string)($input['status'] ?? 'proposed')));
    if (!in_array($status, ['proposed','countered','accepted','declined','revoked'], true)) throw new InvalidArgumentException('Invalid participant commission status.');
    $accepted = $status === 'accepted' ? mg_commission_normalize_bps($input['accepted_commission_bps'] ?? $proposed, 'Accepted bundle commission') : null;
    $stmt = $pdo->prepare('SELECT * FROM bundle_commission_participant_terms WHERE bundle_profile_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$bundleProfileId,$merchantUserId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    $reason = mb_substr(trim((string)($input['reason'] ?? '')) ?: 'Bundle participant commission terms updated.',0,500);
    if ($existing) {
        $pdo->prepare('UPDATE bundle_commission_participant_terms SET proposed_commission_bps=?,accepted_commission_bps=?,terms_status=?,terms_source=?,reason=?,accepted_by_user_id=?,accepted_at=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$proposed,$accepted,$status,'bundle_participant_terms',$reason,$status==='accepted'?$actorUserId:null,$status==='accepted'?date('Y-m-d H:i:s'):null,$actorUserId,(int)$existing['id']]);
        $id = (int)$existing['id'];
    } else {
        $pdo->prepare('INSERT INTO bundle_commission_participant_terms (public_id,bundle_profile_id,merchant_user_id,proposed_commission_bps,accepted_commission_bps,terms_status,terms_source,reason,accepted_by_user_id,accepted_at,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([mg_public_uuid(),$bundleProfileId,$merchantUserId,$proposed,$accepted,$status,'bundle_participant_terms',$reason,$status==='accepted'?$actorUserId:null,$status==='accepted'?date('Y-m-d H:i:s'):null,$actorUserId,$actorUserId]);
        $id = (int)$pdo->lastInsertId();
    }
    $stmt = $pdo->prepare('SELECT * FROM bundle_commission_participant_terms WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_commission_resolve_bundle_rate(PDO $pdo, int $merchantUserId, string $bundleReference, array $context = []): array
{
    $bundleReference = trim($bundleReference);
    if ($bundleReference === '') return mg_commission_resolve_merchant_rate($pdo,$merchantUserId,$context);
    $stmt = $pdo->prepare("SELECT * FROM bundle_commission_profiles WHERE bundle_reference=? AND status IN ('locked','draft') ORDER BY CASE status WHEN 'locked' THEN 0 ELSE 1 END,version_number DESC,id DESC LIMIT 1");
    $stmt->execute([$bundleReference]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) return mg_commission_resolve_merchant_rate($pdo,$merchantUserId,$context) + ['bundle_reference'=>$bundleReference];
    $termsStmt = $pdo->prepare("SELECT * FROM bundle_commission_participant_terms WHERE bundle_profile_id=? AND merchant_user_id=? AND terms_status='accepted' LIMIT 1");
    $termsStmt->execute([(int)$profile['id'],$merchantUserId]);
    $terms = $termsStmt->fetch(PDO::FETCH_ASSOC);
    if ($terms && $terms['accepted_commission_bps'] !== null) return [
        'merchant_user_id'=>$merchantUserId,'commission_rate_bps'=>(int)$terms['accepted_commission_bps'],'rate_mode'=>'bundle_participant_rate',
        'rate_source'=>'accepted_bundle_participant_terms','profile_id'=>null,'profile_public_id'=>null,'profile_version'=>null,
        'bundle_profile_id'=>(int)$profile['id'],'bundle_profile_public_id'=>(string)$profile['public_id'],
        'bundle_terms_id'=>(int)$terms['id'],'bundle_terms_public_id'=>(string)$terms['public_id'],
        'bundle_reference'=>$bundleReference,'bundle_terms_version'=>(int)$profile['version_number'],'rule_version'=>MG_COMMISSION_RULE_VERSION,
    ];
    if ((string)$profile['commission_mode']==='bundle_starting_rate' && $profile['starting_commission_bps']!==null) return [
        'merchant_user_id'=>$merchantUserId,'commission_rate_bps'=>(int)$profile['starting_commission_bps'],'rate_mode'=>'bundle_starting_rate',
        'rate_source'=>'bundle_starting_rate','profile_id'=>null,'profile_public_id'=>null,'profile_version'=>null,
        'bundle_profile_id'=>(int)$profile['id'],'bundle_profile_public_id'=>(string)$profile['public_id'],'bundle_terms_id'=>null,
        'bundle_reference'=>$bundleReference,'bundle_terms_version'=>(int)$profile['version_number'],'rule_version'=>MG_COMMISSION_RULE_VERSION,
    ];
    if ((string)$profile['commission_mode']==='custom_participant_rates' && !empty($context['require_accepted_bundle_terms'])) throw new MgCommissionException('This merchant has not accepted commission terms for the bundle.',409);
    return mg_commission_resolve_merchant_rate($pdo,$merchantUserId,$context) + [
        'bundle_profile_id'=>(int)$profile['id'],'bundle_profile_public_id'=>(string)$profile['public_id'],'bundle_terms_id'=>null,
        'bundle_reference'=>$bundleReference,'bundle_terms_version'=>(int)$profile['version_number'],
    ];
}

function mg_commission_quote_with_rate(int $amountCents, array $rate, int $fixedFeeCents = 0): array
{
    $amountCents = max(0,$amountCents);
    $bps = mg_commission_normalize_bps($rate['commission_rate_bps'] ?? null);
    $percentage = intdiv(($amountCents * $bps) + 5000,10000);
    $fixed = min(max(0,$fixedFeeCents),max(0,$amountCents-$percentage));
    $total = min($amountCents,$percentage+$fixed);
    return $rate + [
        'commissionable_amount_cents'=>$amountCents,'percentage_commission_cents'=>$percentage,'fixed_fee_cents'=>$fixed,
        'commission_amount_cents'=>$total,'merchant_net_amount_cents'=>$amountCents-$total,'rule_version'=>MG_COMMISSION_RULE_VERSION,
    ];
}

function mg_commission_quote_order(PDO $pdo, int $merchantUserId, int $amountCents, string $currency = 'USD', array $context = []): array
{
    mg_commission_require_schema($pdo);
    $bundle = trim((string)($context['bundle_reference'] ?? ''));
    $rate = $bundle !== '' ? mg_commission_resolve_bundle_rate($pdo,$merchantUserId,$bundle,$context) : mg_commission_resolve_merchant_rate($pdo,$merchantUserId,$context);
    $provider = strtolower(trim((string)($context['provider_key'] ?? 'stripe'))) ?: 'stripe';
    $mode = (string)($context['payment_mode'] ?? mg_payment_mode()) === 'live' ? 'live' : 'test';
    $config = mg_payment_platform_config($pdo,$provider,$mode);
    $fixed = array_key_exists('include_fixed_fee',$context) && empty($context['include_fixed_fee']) ? 0 : max(0,(int)($config['fixed_fee_cents'] ?? 0));
    return mg_commission_quote_with_rate($amountCents,$rate,$fixed) + ['currency'=>strtoupper(substr(trim($currency) ?: 'USD',0,3)),'provider_key'=>$provider,'payment_mode'=>$mode,'quote_type'=>'order'];
}

function mg_commission_quote_component(PDO $pdo, int $merchantUserId, int $amountCents, string $currency = 'USD', array $context = []): array
{
    $context['include_fixed_fee'] = false;
    return mg_commission_quote_order($pdo,$merchantUserId,$amountCents,$currency,$context) + ['quote_type'=>'component'];
}

function mg_commission_snapshot_checkout_draft(PDO $pdo, int $draftId, int $merchantUserId, array $quote, array $context = []): array
{
    mg_commission_require_schema($pdo);
    $existing = $pdo->prepare('SELECT * FROM checkout_draft_commission_snapshots WHERE checkout_draft_id=? LIMIT 1 FOR UPDATE');
    $existing->execute([$draftId]);
    if ($row=$existing->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$row['merchant_user_id']!==$merchantUserId || (int)$row['commission_rate_bps']!==(int)$quote['commission_rate_bps'] || (int)$row['commission_amount_cents']!==(int)$quote['commission_amount_cents']) throw new MgCommissionException('Checkout commission snapshot conflicts with the existing draft.',409);
        return $row + ['duplicate'=>true];
    }
    $pdo->prepare('INSERT INTO checkout_draft_commission_snapshots (public_id,checkout_draft_id,merchant_user_id,merchant_profile_id,bundle_profile_id,bundle_terms_id,commissionable_amount_cents,commission_rate_bps,percentage_commission_cents,fixed_fee_cents,commission_amount_cents,merchant_net_amount_cents,rate_mode,rate_source,rule_version,bundle_reference,bundle_terms_version,inputs_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$draftId,$merchantUserId,$quote['profile_id']??null,$quote['bundle_profile_id']??null,$quote['bundle_terms_id']??null,(int)$quote['commissionable_amount_cents'],(int)$quote['commission_rate_bps'],(int)$quote['percentage_commission_cents'],(int)$quote['fixed_fee_cents'],(int)$quote['commission_amount_cents'],(int)$quote['merchant_net_amount_cents'],(string)$quote['rate_mode'],(string)$quote['rate_source'],MG_COMMISSION_RULE_VERSION,$quote['bundle_reference']??null,$quote['bundle_terms_version']??null,mg_commission_json($context+['quote'=>$quote])]);
    $existing->execute([$draftId]);
    return ($existing->fetch(PDO::FETCH_ASSOC) ?: []) + ['duplicate'=>false];
}

function mg_commission_checkout_draft_snapshot(PDO $pdo, int $draftId, bool $forUpdate = false): ?array
{
    mg_commission_require_schema($pdo);
    $stmt=$pdo->prepare('SELECT * FROM checkout_draft_commission_snapshots WHERE checkout_draft_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([$draftId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_commission_promote_draft_to_order(PDO $pdo, int $draftId, int $orderId): array
{
    mg_commission_require_schema($pdo);
    $draftStmt=$pdo->prepare('SELECT * FROM checkout_drafts WHERE id=? LIMIT 1 FOR UPDATE');
    $draftStmt->execute([$draftId]);
    $draft=$draftStmt->fetch(PDO::FETCH_ASSOC);
    if (!$draft) throw new RuntimeException('Checkout draft not found for commission snapshot promotion.');
    $snapshot=mg_commission_checkout_draft_snapshot($pdo,$draftId,true);
    if (!$snapshot) {
        $quote=mg_commission_quote_order($pdo,(int)$draft['merchant_user_id'],(int)$draft['subtotal_cents'],(string)$draft['currency'],['source_type'=>'legacy_checkout_draft_recovery']);
        $snapshot=mg_commission_snapshot_checkout_draft($pdo,$draftId,(int)$draft['merchant_user_id'],$quote,['source_type'=>'legacy_checkout_draft_recovery']);
    }
    $existing=$pdo->prepare('SELECT * FROM commerce_order_commission_snapshots WHERE order_id=? LIMIT 1 FOR UPDATE');
    $existing->execute([$orderId]);
    $orderSnapshot=$existing->fetch(PDO::FETCH_ASSOC);
    if (!$orderSnapshot) {
        $pdo->prepare('INSERT INTO commerce_order_commission_snapshots (public_id,order_id,checkout_draft_snapshot_id,merchant_user_id,merchant_profile_id,bundle_profile_id,bundle_terms_id,commissionable_amount_cents,commission_rate_bps,percentage_commission_cents,fixed_fee_cents,commission_amount_cents,merchant_net_amount_cents,rate_mode,rate_source,rule_version,bundle_reference,bundle_terms_version,inputs_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([mg_public_uuid(),$orderId,(int)$snapshot['id'],(int)$snapshot['merchant_user_id'],$snapshot['merchant_profile_id'],$snapshot['bundle_profile_id'],$snapshot['bundle_terms_id'],(int)$snapshot['commissionable_amount_cents'],(int)$snapshot['commission_rate_bps'],(int)$snapshot['percentage_commission_cents'],(int)$snapshot['fixed_fee_cents'],(int)$snapshot['commission_amount_cents'],(int)$snapshot['merchant_net_amount_cents'],(string)$snapshot['rate_mode'],(string)$snapshot['rate_source'],(string)$snapshot['rule_version'],$snapshot['bundle_reference'],$snapshot['bundle_terms_version'],$snapshot['inputs_json']]);
        $existing->execute([$orderId]);
        $orderSnapshot=$existing->fetch(PDO::FETCH_ASSOC);
    }
    if (!$orderSnapshot) throw new RuntimeException('Unable to create order commission snapshot.');

    $items=$pdo->prepare('SELECT * FROM commerce_order_items WHERE order_id=? ORDER BY id FOR UPDATE');
    $items->execute([$orderId]);
    foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $merchantId=(int)($item['merchant_user_id'] ?? $snapshot['merchant_user_id']);
        $rate=['merchant_user_id'=>$merchantId,'commission_rate_bps'=>(int)$snapshot['commission_rate_bps'],'rate_mode'=>(string)$snapshot['rate_mode'],'rate_source'=>(string)$snapshot['rate_source'],'profile_id'=>$snapshot['merchant_profile_id'],'bundle_profile_id'=>$snapshot['bundle_profile_id'],'bundle_terms_id'=>$snapshot['bundle_terms_id'],'bundle_reference'=>$snapshot['bundle_reference'],'bundle_terms_version'=>$snapshot['bundle_terms_version'],'rule_version'=>(string)$snapshot['rule_version']];
        $quote=mg_commission_quote_with_rate((int)$item['line_total_cents'],$rate,0);
        $pdo->prepare('INSERT INTO commerce_order_item_commission_snapshots (public_id,order_id,order_item_id,order_commission_snapshot_id,merchant_user_id,merchant_profile_id,bundle_profile_id,bundle_terms_id,commissionable_amount_cents,commission_rate_bps,commission_amount_cents,merchant_net_amount_cents,rate_mode,rate_source,rule_version,bundle_reference,bundle_terms_version,inputs_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE order_item_id=VALUES(order_item_id)')
            ->execute([mg_public_uuid(),$orderId,(int)$item['id'],(int)$orderSnapshot['id'],$merchantId,$snapshot['merchant_profile_id'],$snapshot['bundle_profile_id'],$snapshot['bundle_terms_id'],(int)$quote['commissionable_amount_cents'],(int)$quote['commission_rate_bps'],(int)$quote['percentage_commission_cents'],(int)$quote['merchant_net_amount_cents'],(string)$snapshot['rate_mode'],(string)$snapshot['rate_source'],(string)$snapshot['rule_version'],$snapshot['bundle_reference'],$snapshot['bundle_terms_version'],mg_commission_json(['order_item_id'=>(string)$item['public_id'],'fixed_fee_allocated'=>false])]);
    }
    return $orderSnapshot;
}

function mg_commission_order_snapshot(PDO $pdo, int $orderId): ?array
{
    mg_commission_require_schema($pdo);
    $stmt=$pdo->prepare('SELECT * FROM commerce_order_commission_snapshots WHERE order_id=? LIMIT 1');
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_commission_public_profile(PDO $pdo, int $merchantUserId, bool $initialize = true): array
{
    $rate=mg_commission_resolve_merchant_rate($pdo,$merchantUserId,['initialize'=>$initialize]);
    $fixed=max(0,(int)mg_payment_platform_config($pdo,'stripe',mg_payment_mode())['fixed_fee_cents']);
    $example=mg_commission_quote_with_rate(10000,$rate,$fixed);
    return [
        'merchant_user_id'=>$merchantUserId,'commission_rate_bps'=>(int)$rate['commission_rate_bps'],'commission_rate_percent'=>(int)$rate['commission_rate_bps']/100,
        'rate_mode'=>(string)$rate['rate_mode'],'rate_source'=>(string)$rate['rate_source'],'profile_id'=>$rate['profile_public_id']??null,
        'profile_version'=>$rate['profile_version']??null,'effective_from'=>$rate['effective_from']??null,'effective_until'=>$rate['effective_until']??null,
        'rule_version'=>MG_COMMISSION_RULE_VERSION,'platform_starting_bps'=>mg_commission_platform_starting_bps($pdo),
        'example_100_dollar_sale'=>['gross_cents'=>10000,'percentage_commission_cents'=>(int)$example['percentage_commission_cents'],'fixed_fee_cents'=>(int)$example['fixed_fee_cents'],'commission_total_cents'=>(int)$example['commission_amount_cents'],'merchant_net_cents'=>(int)$example['merchant_net_amount_cents'],'currency'=>'USD'],
    ];
}
