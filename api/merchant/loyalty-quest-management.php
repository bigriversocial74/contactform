<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_lqm_uuid_valid(string $value): bool
{
    return strlen($value) === 36 && preg_match('/^[a-f0-9-]{36}$/', $value) === 1;
}

function mg_lqm_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_lqm_slug(PDO $pdo, int $merchantId, string $title): string
{
    $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'loyalty-quest';
    $base = substr($base, 0, 110);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND public_slug=?');
    while (true) {
        $stmt->execute([$merchantId, $candidate]);
        if ((int)$stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 118 - strlen((string)$suffix))) . '-' . $suffix;
    }
}

function mg_lqm_public_url(array $row): string
{
    $ref = (string)($row['public_slug'] ?: $row['public_id']);
    return '/loyalty-quest.php?campaign=' . rawurlencode($ref);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET'
    ? mg_merchant_require_permission('merchant.campaigns.view')
    : mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    $campaignId = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    if ($campaignId !== '' && !mg_lqm_uuid_valid($campaignId)) mg_fail('Invalid Loyalty Quest.', 422);
    $where = "c.merchant_user_id=? AND c.campaign_type='loyalty_quest'";
    $params = [$merchantId];
    if ($campaignId !== '') { $where .= ' AND c.public_id=?'; $params[] = $campaignId; }
    $sql = "SELECT c.*,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.status reward_template_status,
        (SELECT COUNT(*) FROM campaign_contacts cc WHERE cc.campaign_id=c.id AND cc.merchant_user_id=c.merchant_user_id) participant_count,
        (SELECT COUNT(*) FROM campaign_events ce WHERE ce.campaign_id=c.id AND ce.merchant_user_id=c.merchant_user_id) event_count,
        (SELECT COUNT(*) FROM wallet_items wi WHERE wi.campaign_id=c.id AND wi.merchant_user_id=c.merchant_user_id AND wi.status<>'cancelled') issued_count_live,
        (SELECT COUNT(*) FROM wallet_items wi WHERE wi.campaign_id=c.id AND wi.merchant_user_id=c.merchant_user_id AND wi.status='claimed') claimed_count,
        (SELECT COUNT(*) FROM wallet_items wi WHERE wi.campaign_id=c.id AND wi.merchant_user_id=c.merchant_user_id AND wi.status='redeemed') redeemed_count,
        (SELECT MAX(ce.created_at) FROM campaign_events ce WHERE ce.campaign_id=c.id AND ce.merchant_user_id=c.merchant_user_id) last_event_at
        FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE {$where}
        ORDER BY c.updated_at DESC,c.id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $quests = array_map(static function(array $row): array {
        $rules = mg_lqm_rules($row['rules_json'] ?? null);
        return [
            'id'=>(string)$row['public_id'],'slug'=>$row['public_slug'] ?? null,'title'=>(string)$row['title'],
            'description'=>(string)($row['description'] ?? ''),'status'=>(string)$row['status'],
            'starts_at'=>$row['starts_at'] ?? null,'ends_at'=>$row['ends_at'] ?? null,
            'quantity_limit'=>$row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
            'per_user_limit'=>(int)($row['per_user_limit'] ?? 1),'rules'=>$rules,
            'action_type'=>(string)($rules['action_type'] ?? ''),'verification_type'=>(string)($rules['verification_type'] ?? ''),
            'visibility'=>(string)($rules['visibility'] ?? 'public'),'location_id'=>$rules['location_id'] ?? null,
            'reward_template'=>['id'=>$row['reward_template_public_id'] ?? null,'title'=>$row['reward_template_title'] ?? null,'status'=>$row['reward_template_status'] ?? null],
            'participants'=>(int)$row['participant_count'],'events'=>(int)$row['event_count'],
            'issued'=>(int)$row['issued_count_live'],'claimed'=>(int)$row['claimed_count'],'redeemed'=>(int)$row['redeemed_count'],
            'last_event_at'=>$row['last_event_at'] ?? null,'public_url'=>mg_lqm_public_url($row),
            'created_at'=>$row['created_at'] ?? null,'updated_at'=>$row['updated_at'] ?? null,
        ];
    }, $rows);
    $totals=['total'=>count($quests),'active'=>0,'participants'=>0,'issued'=>0,'claimed'=>0,'redeemed'=>0];
    foreach($quests as $quest){if($quest['status']==='active')$totals['active']++;foreach(['participants','issued','claimed','redeemed'] as $key)$totals[$key]+=(int)$quest[$key];}
    mg_ok(['quests'=>$quests,'totals'=>$totals,'schema_ready'=>true]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
$action = strtolower(trim((string)($input['action'] ?? '')));
if (!mg_lqm_uuid_valid($campaignId) || !in_array($action,['publish','pause','resume','complete','archive','duplicate'],true)) mg_fail('Invalid Loyalty Quest action.',422);

$pdo->beginTransaction();
try {
    $stmt=$pdo->prepare("SELECT c.*,rt.status reward_template_status FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.public_id=? AND c.merchant_user_id=? AND c.campaign_type='loyalty_quest' LIMIT 1 FOR UPDATE");
    $stmt->execute([$campaignId,$merchantId]); $quest=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$quest) mg_fail('Loyalty Quest not found.',404);
    $previous=(string)$quest['status']; $rules=mg_lqm_rules($quest['rules_json'] ?? null);

    if($action==='duplicate'){
        $newId=mg_merchant_uuid(); $newTitle=mb_substr((string)$quest['title'].' Copy',0,180); $newSlug=mg_lqm_slug($pdo,$merchantId,$newTitle);
        if(isset($rules['qr_code_token']))$rules['qr_code_token']=bin2hex(random_bytes(16));
        $rules['duplicated_from']=$campaignId; $rules['duplicated_at']=gmdate('c');
        $rulesJson=json_encode($rules,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $insert=$pdo->prepare('INSERT INTO campaigns (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,rules_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,\'draft\',NULL,NULL,?,?,?,?,?,?,NOW(),NOW())');
        $insert->execute([$newId,$merchantId,$quest['reward_template_id'],'loyalty_quest',$newTitle,$quest['description'],$quest['form_headline'],$quest['form_description'],$quest['success_message'],$quest['quantity_limit'],$quest['per_user_limit'],$quest['agent_discoverable'],$newSlug,null,$rulesJson]);
        mg_audit('merchant.loyalty_quest_duplicated','campaign',['source_campaign_id'=>$campaignId,'campaign_id'=>$newId],$merchantId);
        $pdo->commit(); mg_ok(['campaign_id'=>$newId,'status'=>'draft'],'Loyalty Quest duplicated.');
    }

    $transitions=[
        'publish'=>['from'=>['draft','paused'],'to'=>'active'],
        'pause'=>['from'=>['active'],'to'=>'paused'],
        'resume'=>['from'=>['paused'],'to'=>'active'],
        'complete'=>['from'=>['active','paused'],'to'=>'ended'],
        'archive'=>['from'=>['draft','paused','ended'],'to'=>'archived'],
    ];
    $transition=$transitions[$action];
    if(!in_array($previous,$transition['from'],true)) mg_fail('This Loyalty Quest action is not allowed from its current status.',409);
    $next=$transition['to'];
    if(in_array($action,['publish','resume'],true)){
        if(empty($quest['reward_template_id']) || (string)$quest['reward_template_status']!=='active') mg_fail('Publishing requires an active reward template.',422);
        if(trim((string)($quest['form_description'] ?? $quest['description'] ?? ''))==='') mg_fail('Publishing requires participant instructions.',422);
        $actionType=(string)($rules['action_type'] ?? '');
        if(in_array($actionType,['location_visit','multi_location','event_attendance'],true) && empty($rules['location_id'])) mg_fail('Location-based Loyalty Quests require a merchant location.',422);
        if(!empty($quest['ends_at']) && strtotime((string)$quest['ends_at'])<=time()) mg_fail('The Loyalty Quest end date must be in the future.',422);
        $activeCount=$pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active' AND public_id<>?");
        $activeCount->execute([$merchantId,$campaignId]);
        mg_package_require_limit_available($pdo,$user,'max_active_campaigns',(int)$activeCount->fetchColumn(),'Active campaign limit reached.');
    }
    $update=$pdo->prepare("UPDATE campaigns SET status=?,updated_at=NOW() WHERE id=? AND merchant_user_id=? AND campaign_type='loyalty_quest'");
    $update->execute([$next,(int)$quest['id'],$merchantId]);
    $eventType='merchant.loyalty_quest_'.$action;
    mg_audit($eventType,'campaign',['campaign_id'=>$campaignId,'from'=>$previous,'to'=>$next],$merchantId);
    if(function_exists('mg_event'))mg_event('campaign.'.$action,['campaign_id'=>$campaignId,'campaign_type'=>'loyalty_quest','from'=>$previous,'to'=>$next],$merchantId);
    $pdo->commit(); mg_ok(['campaign_id'=>$campaignId,'status'=>$next],'Loyalty Quest updated.');
} catch(Throwable $error) {
    if($pdo->inTransaction())$pdo->rollBack();
    if($error instanceof RuntimeException) throw $error;
    mg_security_log('error','merchant.loyalty_quest_management_failed','Unable to update Loyalty Quest.',['exception_class'=>$error::class],$merchantId);
    mg_fail('Unable to update Loyalty Quest.',500);
}
