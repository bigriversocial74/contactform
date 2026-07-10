<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm.php';
require_once dirname(__DIR__) . '/campaigns/_limits.php';
require_once dirname(__DIR__) . '/campaigns/_followups.php';

function mg_lqp_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_lqp_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_lqp_campaign(PDO $pdo, string $ref, bool $forUpdate = false, bool $enforceAvailability = true): array
{
    $sql = "SELECT c.*,rt.id reward_template_db_id,rt.public_id reward_template_public_id,rt.title reward_template_title,
        rt.description reward_template_description,rt.redemption_instructions,rt.reward_type,rt.value_type,
        rt.value_amount_cents,rt.value_percent,rt.currency,rt.expiration_rule,rt.expiration_days,rt.expires_at,
        rt.quantity_limit reward_template_quantity_limit,rt.issued_count reward_template_issued_count,
        rt.per_user_limit reward_template_per_user_limit,rt.status reward_template_status,
        COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name,
        pp.slug merchant_slug,pp.avatar_url merchant_avatar,
        ml.id location_db_id,ml.public_id location_public_id,ml.name location_name,ml.address_line1,ml.city,ml.region,ml.postal_code,
        JSON_UNQUOTE(JSON_EXTRACT(ml.metadata_json,'$.latitude')) location_latitude,
        JSON_UNQUOTE(JSON_EXTRACT(ml.metadata_json,'$.longitude')) location_longitude,
        JSON_UNQUOTE(JSON_EXTRACT(ml.metadata_json,'$.check_in_radius_meters')) location_radius
        FROM campaigns c
        INNER JOIN users u ON u.id=c.merchant_user_id
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
        LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
        LEFT JOIN merchant_locations ml ON ml.merchant_user_id=c.merchant_user_id AND ml.public_id=JSON_UNQUOTE(JSON_EXTRACT(c.rules_json,'$.location_id'))
        WHERE c.campaign_type='loyalty_quest' AND (c.public_id=? OR c.public_slug=?) LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ref, $ref]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Loyalty Quest not found.', 404);
    $campaign['rules'] = mg_lqp_json($campaign['rules_json'] ?? null);
    if (!$enforceAvailability) return $campaign;

    if ((string)$campaign['status'] !== 'active' || (string)($campaign['reward_template_status'] ?? '') !== 'active') {
        mg_fail('Loyalty Quest is not available.', 409);
    }
    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) mg_fail('Loyalty Quest has not started yet.', 409);
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) <= $now) mg_fail('Loyalty Quest has ended.', 409);
    if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) mg_fail('Loyalty Quest reward limit has been reached.', 409);
    if ($campaign['reward_template_quantity_limit'] !== null && (int)$campaign['reward_template_issued_count'] >= (int)$campaign['reward_template_quantity_limit']) mg_fail('Reward availability has been exhausted.', 409);
    return $campaign;
}

function mg_lqp_required_count(array $campaign): int
{
    return max(1, min(100, (int)($campaign['rules']['required_count'] ?? 1)));
}

function mg_lqp_contact(PDO $pdo, array $campaign, array $user): array
{
    $email = strtolower(trim((string)($user['email'] ?? '')));
    $name = trim((string)($user['display_name'] ?? $user['full_name'] ?? ''));
    $find = $pdo->prepare('SELECT * FROM campaign_contacts WHERE campaign_id=? AND (user_id=? OR email=?) ORDER BY user_id IS NOT NULL DESC,id ASC LIMIT 1 FOR UPDATE');
    $find->execute([(int)$campaign['id'], (int)$user['id'], $email]);
    $contact = $find->fetch(PDO::FETCH_ASSOC);
    if ($contact) {
        if ((int)($contact['user_id'] ?? 0) !== (int)$user['id'] || ($name !== '' && (string)($contact['name'] ?? '') === '')) {
            $pdo->prepare('UPDATE campaign_contacts SET user_id=?,name=COALESCE(NULLIF(name,\'\'),?),updated_at=NOW() WHERE id=? AND merchant_user_id=?')
                ->execute([(int)$user['id'], $name !== '' ? $name : null, (int)$contact['id'], (int)$campaign['merchant_user_id']]);
            $find->execute([(int)$campaign['id'], (int)$user['id'], $email]);
            $contact = $find->fetch(PDO::FETCH_ASSOC);
        }
        return $contact;
    }

    mg_public_campaign_enforce_crm_contact_limit($pdo, (int)$campaign['merchant_user_id'], $email, true);
    $publicId = mg_lqp_uuid();
    $metadata = ['campaign_type'=>'loyalty_quest','source'=>'participant_quest_experience','microgifter_user_id'=>(int)$user['id']];
    $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,'loyalty_quest','unknown',?,NOW(),NOW())")
        ->execute([$publicId,(int)$campaign['merchant_user_id'],(int)$campaign['id'],(int)$user['id'],$email,$name!==''?$name:null,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    $find->execute([(int)$campaign['id'], (int)$user['id'], $email]);
    $contact = $find->fetch(PDO::FETCH_ASSOC);
    if (!$contact) throw new RuntimeException('Unable to create participant contact.');
    mg_merchant_crm_record_event($pdo, ['merchant_user_id'=>(int)$campaign['merchant_user_id'],'campaign_id'=>(int)$campaign['id'],'campaign_type'=>'loyalty_quest','event_type'=>'quest.joined','source_type'=>'loyalty_quest','source_public_id'=>(string)$contact['public_id'],'user_id'=>(int)$user['id'],'email'=>$email,'name'=>$name,'metadata'=>$metadata]);
    return $contact;
}

function mg_lqp_event(PDO $pdo, array $campaign, ?int $walletItemId, ?int $contactId, string $eventType, array $context = []): void
{
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([mg_lqp_uuid(),(int)$campaign['merchant_user_id'],(int)$campaign['id'],$walletItemId,$contactId,$eventType,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    mg_campaign_followup_schedule($pdo, ['merchant_user_id'=>(int)$campaign['merchant_user_id'],'campaign_id'=>(int)$campaign['id'],'contact_id'=>$contactId,'wallet_item_id'=>$walletItemId,'trigger_event'=>$eventType,'context'=>$context]);
}

function mg_lqp_distance_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}

function mg_lqp_audience_require(PDO $pdo, array $campaign, array $user, array $input): array
{
    $rules = $campaign['rules'];
    $visibility = (string)($rules['visibility'] ?? 'public');
    $userId = (int)$user['id'];
    $merchantId = (int)$campaign['merchant_user_id'];
    $email = strtolower(trim((string)($user['email'] ?? '')));
    $context = ['visibility'=>$visibility];

    if ($visibility === 'public') return $context;
    if ($visibility === 'invite_only') {
        $invite = strtoupper(trim((string)($input['invite_code'] ?? '')));
        $expected = strtolower(trim((string)($rules['invite_code_hash'] ?? '')));
        if ($invite === '' || $expected === '' || !hash_equals($expected, hash('sha256', $invite))) mg_fail('Enter the valid invite code to join this Loyalty Quest.', 403);
        $context['invite_verified'] = true;
        return $context;
    }

    $merchantContact = $pdo->prepare('SELECT COUNT(*) FROM campaign_contacts WHERE merchant_user_id=? AND (user_id=? OR email=?)');
    $merchantContact->execute([$merchantId, $userId, $email]);
    $hasMerchantContact = (int)$merchantContact->fetchColumn() > 0;
    $campaignContact = $pdo->prepare('SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id=? AND merchant_user_id=? AND (user_id=? OR email=?)');
    $campaignContact->execute([(int)$campaign['id'], $merchantId, $userId, $email]);
    $hasCampaignContact = (int)$campaignContact->fetchColumn() > 0;
    $wallet = $pdo->prepare("SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND user_id=? AND status IN ('issued','viewed','claimed','redeemed')");
    $wallet->execute([$merchantId, $userId]);
    $hasMerchantReward = (int)$wallet->fetchColumn() > 0;

    if ($visibility === 'customers' && !$hasMerchantContact && !$hasMerchantReward) mg_fail('This Loyalty Quest is available to existing merchant customers.', 403);
    if ($visibility === 'loyalty_members' && !$hasMerchantReward) mg_fail('This Loyalty Quest is available to merchant loyalty members.', 403);
    if ($visibility === 'new_customers' && ($hasMerchantContact || $hasMerchantReward)) mg_fail('This Loyalty Quest is reserved for new customers.', 403);
    if ($visibility === 'campaign_contacts' && !$hasCampaignContact) mg_fail('This Loyalty Quest is available to invited campaign contacts.', 403);
    if ($visibility === 'geographic_radius') {
        $latitude = is_numeric($input['latitude'] ?? null) ? (float)$input['latitude'] : null;
        $longitude = is_numeric($input['longitude'] ?? null) ? (float)$input['longitude'] : null;
        if ($latitude === null || $longitude === null) mg_fail('Share your location to join this regional Loyalty Quest.', 422);
        if ($campaign['location_latitude'] === null || $campaign['location_longitude'] === null) mg_fail('Merchant location access is not configured.', 409);
        $distance = mg_lqp_distance_meters($latitude, $longitude, (float)$campaign['location_latitude'], (float)$campaign['location_longitude']);
        $radius = max(25, min(250000, (int)($rules['radius_meters'] ?? $campaign['location_radius'] ?? 5000)));
        if ($distance > $radius) mg_fail('This Loyalty Quest is not available from your current location.', 403);
        $context['distance_meters'] = round($distance, 2);
    }
    return $context;
}

function mg_lqp_enforce_daily_limit(PDO $pdo, array $campaign): void
{
    $limit = (int)($campaign['rules']['daily_limit'] ?? 0);
    if ($limit <= 0) return;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_quest_evidence WHERE campaign_id=? AND merchant_user_id=? AND status='verified' AND COALESCE(verified_at,created_at)>=CURRENT_DATE()");
    $stmt->execute([(int)$campaign['id'], (int)$campaign['merchant_user_id']]);
    if ((int)$stmt->fetchColumn() >= $limit) mg_fail('This Loyalty Quest has reached today’s verified-completion limit.', 409);
}

function mg_lqp_enforce_cooldown(PDO $pdo, array $campaign, array $participation): void
{
    $hours = (int)($campaign['rules']['cooldown_hours'] ?? 0);
    if ($hours <= 0 || (int)$participation['progress_count'] <= 0) return;
    $cutoff = gmdate('Y-m-d H:i:s', time() - ($hours * 3600));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_quest_evidence WHERE participation_id=? AND status IN ('submitted','verified') AND created_at>=?");
    $stmt->execute([(int)$participation['id'], $cutoff]);
    if ((int)$stmt->fetchColumn() > 0) mg_fail('This quest action is still in its repeat-action cooldown period.', 409);
}

function mg_lqp_enforce_budget(PDO $pdo, array $campaign): void
{
    $budget = (float)($campaign['rules']['budget_limit'] ?? 0);
    if ($budget <= 0) return;
    $budgetCents = (int)round($budget * 100);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(value_cents_snapshot),0) FROM wallet_items WHERE campaign_id=? AND merchant_user_id=? AND status<>'cancelled'");
    $stmt->execute([(int)$campaign['id'], (int)$campaign['merchant_user_id']]);
    $spent = (int)$stmt->fetchColumn();
    $next = max(0, (int)($campaign['value_amount_cents'] ?? 0));
    if ($spent + $next > $budgetCents) mg_fail('This Loyalty Quest reward budget has been reached.', 409);
}

function mg_lqp_expiry(array $campaign): ?string
{
    $rule = (string)($campaign['expiration_rule'] ?? 'none');
    if (in_array($rule, ['fixed_date','event_date'], true)) return $campaign['expires_at'] ?: null;
    if ($rule === 'after_issue' && !empty($campaign['expiration_days'])) return gmdate('Y-m-d H:i:s', time() + ((int)$campaign['expiration_days'] * 86400));
    return null;
}

function mg_lqp_issue_reward(PDO $pdo, array $campaign, array $contact, array $participation, array $user): array
{
    $existing = $pdo->prepare("SELECT id,public_id,status FROM wallet_items WHERE campaign_id=? AND user_id=? AND source_type='loyalty_quest' AND status<>'cancelled' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $existing->execute([(int)$campaign['id'], (int)$user['id']]);
    $wallet = $existing->fetch(PDO::FETCH_ASSOC);
    if ($wallet) return ['wallet_item_id'=>(string)$wallet['public_id'],'wallet_status'=>(string)$wallet['status'],'already_issued'=>true];
    if (empty($campaign['reward_template_db_id']) || (string)($campaign['reward_template_status'] ?? '') !== 'active') mg_fail('The quest reward is temporarily unavailable. The merchant has been asked to review it.', 409);
    mg_lqp_enforce_budget($pdo, $campaign);
    mg_public_campaign_enforce_reward_limits($pdo, $campaign, (int)$user['id'], (string)$user['email']);
    $walletPublicId = mg_lqp_uuid();
    $expiresAt = mg_lqp_expiry($campaign);
    $metadata = ['campaign_type'=>'loyalty_quest','participation_id'=>(string)$participation['public_id'],'verification_type'=>(string)($campaign['rules']['verification_type']??''),'reward_template_id'=>(string)$campaign['reward_template_public_id']];
    $pdo->prepare("INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,'loyalty_quest',?,'issued',?,?,?,?,NOW(),?,NOW(),NOW())")
        ->execute([$walletPublicId,(int)$user['id'],(int)$contact['id'],(int)$campaign['merchant_user_id'],(int)$campaign['reward_template_db_id'],(int)$campaign['id'],(string)$participation['public_id'],(int)($campaign['value_amount_cents']??0),(string)($campaign['currency']??'USD'),(string)$campaign['reward_template_title'],json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$expiresAt]);
    $walletDbId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE campaigns SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$campaign['id']]);
    $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$campaign['reward_template_db_id']]);
    $pdo->prepare("UPDATE loyalty_quest_participations SET wallet_item_id=?,status='completed',completion_percent=100,completed_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=? AND participant_user_id=?")
        ->execute([$walletDbId,(int)$participation['id'],(int)$user['id']]);
    $bridge = mg_zero_reward_issue_from_wallet($pdo, ['merchant_user_id'=>(int)$campaign['merchant_user_id'],'recipient_user_id'=>(int)$user['id'],'recipient_external_id'=>(string)$contact['public_id'],'recipient_name'=>(string)($user['display_name']??$user['full_name']??''),'wallet_item_db_id'=>$walletDbId,'wallet_item_public_id'=>$walletPublicId,'campaign_public_id'=>(string)$campaign['public_id'],'reward_template_public_id'=>(string)$campaign['reward_template_public_id'],'source_type'=>'loyalty_quest','source_reference'=>$walletPublicId,'source_line_reference'=>(string)$participation['public_id'],'title'=>(string)$campaign['reward_template_title'],'description'=>$campaign['description']??null,'currency'=>(string)($campaign['currency']??'USD'),'display_value_cents'=>(int)($campaign['value_amount_cents']??0),'expires_at'=>$expiresAt,'redemption_instructions'=>$campaign['redemption_instructions']??null,'terms'=>['campaign_type'=>'loyalty_quest']]);
    mg_lqp_event($pdo, $campaign, $walletDbId, (int)$contact['id'], 'quest.completed', ['participation_id'=>(string)$participation['public_id'],'wallet_item_id'=>$walletPublicId,'pppm_bridge'=>$bridge]);
    mg_lqp_event($pdo, $campaign, $walletDbId, (int)$contact['id'], 'wallet_item.issued', ['wallet_item_id'=>$walletPublicId,'source'=>'loyalty_quest']);
    return ['wallet_item_id'=>$walletPublicId,'wallet_status'=>'issued','already_issued'=>false,'reward_title'=>(string)$campaign['reward_template_title'],'expires_at'=>$expiresAt,'pppm_bridge'=>$bridge];
}
