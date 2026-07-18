<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_loyalty_quest_notifications.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    if ($campaignRef !== '' && (strlen($campaignRef) !== 36 || preg_match('/^[a-f0-9-]{36}$/',$campaignRef)!==1)) mg_fail('Invalid Loyalty Quest.',422);
    $campaignsStmt = $pdo->prepare("SELECT public_id,title,status,starts_at,ends_at FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest' AND status IN ('draft','active','paused') ORDER BY updated_at DESC,id DESC LIMIT 100");
    $campaignsStmt->execute([$merchantId]);
    $contacts = [];
    if ($campaignRef !== '') {
        $stmt = $pdo->prepare("SELECT cc.public_id,cc.name,cc.email,cc.user_id,cc.opt_in_status,cc.updated_at FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id AND c.merchant_user_id=cc.merchant_user_id WHERE c.public_id=? AND c.merchant_user_id=? AND c.campaign_type='loyalty_quest' ORDER BY COALESCE(NULLIF(cc.name,''),cc.email) ASC LIMIT 500");
        $stmt->execute([$campaignRef,$merchantId]);
        $contacts = array_map(static function(array $row): array {
            return [
                'id'=>(string)$row['public_id'],
                'name'=>(string)($row['name'] ?? ''),
                'email'=>(string)$row['email'],
                'has_account'=>(int)($row['user_id'] ?? 0)>0,
                'opt_in_status'=>(string)$row['opt_in_status'],
                'deliverable'=>!in_array((string)$row['opt_in_status'],['opted_out','bounced','complained'],true),
                'updated_at'=>$row['updated_at'] ?? null,
            ];
        },$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
    mg_ok(['campaigns'=>$campaignsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],'contacts'=>$contacts]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? '')));
$contactRefs = $input['contact_ids'] ?? [];
if (!is_array($contactRefs)) $contactRefs = [$contactRefs];
$contactRefs = array_values(array_unique(array_filter(array_map(static fn($value):string=>strtolower(trim((string)$value)),$contactRefs))));
if (strlen($campaignRef)!==36 || preg_match('/^[a-f0-9-]{36}$/',$campaignRef)!==1 || $contactRefs===[] || count($contactRefs)>100) mg_fail('Choose a Loyalty Quest and up to 100 contacts.',422);
foreach ($contactRefs as $ref) if (strlen($ref)!==36 || preg_match('/^[a-f0-9-]{36}$/',$ref)!==1) mg_fail('Invalid campaign contact.',422);

$packageContext = mg_user_package_context($pdo,$user);
if (!mg_package_limit_value($packageContext,'email_stamps_enabled')) mg_fail('Email Stamps are not enabled for this package.',402);
mg_delivery_install_schema($pdo);

$pdo->beginTransaction();
try {
    $campaignStmt = $pdo->prepare("SELECT c.*,COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name FROM campaigns c INNER JOIN users u ON u.id=c.merchant_user_id LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id WHERE c.public_id=? AND c.merchant_user_id=? AND c.campaign_type='loyalty_quest' AND c.status='active' LIMIT 1 FOR UPDATE");
    $campaignStmt->execute([$campaignRef,$merchantId]);
    $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Active Loyalty Quest not found.',404);

    $placeholders = implode(',',array_fill(0,count($contactRefs),'?'));
    $params = array_merge([$merchantId,(int)$campaign['id']],$contactRefs);
    $contactStmt = $pdo->prepare("SELECT * FROM campaign_contacts WHERE merchant_user_id=? AND campaign_id=? AND public_id IN ({$placeholders}) ORDER BY id ASC FOR UPDATE");
    $contactStmt->execute($params);
    $contacts = $contactStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($contacts)!==count($contactRefs)) mg_fail('One or more campaign contacts were not found.',404);

    $sent=0;$skipped=0;$items=[];
    foreach ($contacts as $contact) {
        $result = mg_lqn_invite_contact($pdo,$campaign,$contact,['source_public_id'=>(string)$contact['public_id']]);
        $delivered = (($result['email']['status'] ?? '') === 'queued') || !empty($result['in_app']);
        if ($delivered) $sent++; else $skipped++;
        $items[]=['contact_id'=>(string)$contact['public_id'],'status'=>$delivered?'queued':'skipped','delivery'=>$result];
        $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([
            mg_public_uuid(),$merchantId,(int)$campaign['id'],null,(int)$contact['id'],$delivered?'quest.invitation_queued':'quest.invitation_skipped',json_encode(['contact_id'=>(string)$contact['public_id'],'delivery'=>$result],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ]);
    }
    mg_audit('merchant.loyalty_quest_invitations_queued','campaign',['campaign_id'=>$campaignRef,'selected'=>count($contacts),'queued'=>$sent,'skipped'=>$skipped],$merchantId);
    $pdo->commit();
    mg_ok(['campaign_id'=>$campaignRef,'selected'=>count($contacts),'queued'=>$sent,'skipped'=>$skipped,'items'=>$items],$sent>0?'Loyalty Quest invitations queued.':'No invitations were queued.',201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($error instanceof MgDeliveryException) mg_fail('Unable to queue Loyalty Quest invitations.', $error->httpStatus);
    mg_security_log('error','merchant.loyalty_quest_invitation_failed','Unable to queue Loyalty Quest invitations.',['exception_class'=>$error::class],$merchantId);
    mg_fail('Unable to queue Loyalty Quest invitations.',500);
}
