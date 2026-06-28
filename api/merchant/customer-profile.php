<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

function mg_cp_uuid(): string { return mg_merchant_crm_uuid(); }
function mg_cp_param(string $key): string { return trim((string)($_GET[$key] ?? '')); }
function mg_cp_json(mixed $json): array { $data = is_array($json) ? $json : json_decode((string)$json, true); return is_array($data) ? $data : []; }
function mg_cp_money(int $cents, string $currency = 'USD'): string { return strtoupper($currency ?: 'USD') . ' ' . number_format($cents / 100, 2); }
function mg_cp_label(string $value): string { return ucwords(str_replace(['_', '-'], ' ', $value)); }
function mg_cp_url(string $path, array $params = []): string { $params = array_filter($params, static fn($v) => $v !== null && $v !== ''); return $path . ($params ? '?' . http_build_query($params) : ''); }
function mg_cp_initials(string $name, string $email = ''): string
{
    $base = trim($name) ?: trim($email);
    $parts = preg_split('/\s+/', $base) ?: [];
    $out = '';
    foreach ($parts as $p) { $out .= mb_substr((string)$p, 0, 1); if (mb_strlen($out) >= 2) break; }
    return strtoupper($out ?: 'C');
}
function mg_cp_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) { return $cache[$table] = false; }
}
function mg_cp_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->execute([$table, $column]);
        return $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable) { return $cache[$key] = false; }
}
function mg_cp_action_url(string $type, string $id = '', array $context = []): string
{
    $type = strtolower($type);
    $wallet = trim((string)($context['wallet_item_id'] ?? $context['wallet_id'] ?? ($type === 'reward' || $type === 'wallet' ? $id : '')));
    $thread = trim((string)($context['thread_id'] ?? ($type === 'message' ? $id : '')));
    $redemption = trim((string)($context['redemption_id'] ?? $context['claim_id'] ?? ($type === 'redemption' || $type === 'claim' ? $id : '')));
    $tip = trim((string)($context['tip_id'] ?? ($type === 'tip' ? $id : '')));
    $campaign = trim((string)($context['campaign_id'] ?? ($type === 'campaign' ? $id : '')));
    if ($wallet !== '') return mg_cp_url('/merchant-notifications.php', ['filter' => 'rewards', 'item' => $wallet]);
    if ($thread !== '') return mg_cp_url('/merchant-crm.php', ['tab' => 'messages', 'thread' => $thread]);
    if ($redemption !== '') return mg_cp_url('/merchant-claims.php', ['q' => $redemption]);
    if ($tip !== '') return mg_cp_url('/merchant-notifications.php', ['filter' => 'tips', 'tip' => $tip]);
    if ($campaign !== '') return mg_cp_url('/merchant-campaigns.php', ['campaign' => $campaign]);
    return '';
}

function mg_cp_synthetic_contact(array $cc, int $merchantId): array
{
    return [
        'id' => 0,
        'public_id' => 'campaign-' . (string)($cc['public_id'] ?? mg_cp_uuid()),
        'merchant_user_id' => $merchantId,
        'user_id' => (int)($cc['user_id'] ?? 0),
        'primary_email' => strtolower((string)($cc['email'] ?? '')),
        'primary_phone' => (string)($cc['phone'] ?? ''),
        'display_name' => (string)($cc['name'] ?? $cc['email'] ?? 'Customer'),
        'lifecycle_stage' => 'lead',
        'crm_status' => 'active',
        'tags_json' => '[]',
        'first_seen_at' => $cc['created_at'] ?? null,
        'last_seen_at' => $cc['updated_at'] ?? $cc['created_at'] ?? null,
        'last_engaged_at' => $cc['updated_at'] ?? $cc['created_at'] ?? null,
        'last_campaign_type' => (string)($cc['source'] ?? 'campaign_contact'),
        'total_purchase_cents' => 0,
        '_campaign_contact_public_id' => (string)($cc['public_id'] ?? ''),
        '_campaign_contact_db_id' => (int)($cc['id'] ?? 0),
    ];
}
function mg_cp_ensure_crm_contact(PDO $pdo, int $merchantId, array $cc): array
{
    $email = strtolower(trim((string)($cc['email'] ?? '')));
    $userId = (int)($cc['user_id'] ?? 0);
    try {
        $existing = mg_merchant_crm_contact($pdo, $merchantId, $userId ?: null, filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null);
        if ($existing) {
            $existing['_campaign_contact_public_id'] = (string)($cc['public_id'] ?? '');
            $existing['_campaign_contact_db_id'] = (int)($cc['id'] ?? 0);
            return $existing;
        }
        $record = mg_merchant_crm_record_event($pdo, [
            'merchant_user_id' => $merchantId,
            'campaign_id' => (int)($cc['campaign_id'] ?? 0),
            'campaign_type' => (string)($cc['source'] ?? 'campaign_contact'),
            'event_type' => 'customer_profile.created_from_campaign_contact',
            'source_type' => (string)($cc['source'] ?? 'campaign_contact'),
            'source_public_id' => (string)($cc['public_id'] ?? ''),
            'user_id' => $userId ?: null,
            'email' => $email,
            'phone' => (string)($cc['phone'] ?? ''),
            'name' => (string)($cc['name'] ?? ''),
            'metadata' => ['campaign_contact_id' => (string)($cc['public_id'] ?? '')],
        ]);
        if (!empty($record['contact_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
            $stmt->execute([(string)$record['contact_id'], $merchantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['_campaign_contact_public_id'] = (string)($cc['public_id'] ?? '');
                $row['_campaign_contact_db_id'] = (int)($cc['id'] ?? 0);
                return $row;
            }
        }
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.customer_profile.ensure_contact_failed', 'Customer profile CRM contact could not be created.', ['exception_class' => $error::class], $merchantId);
    }
    return mg_cp_synthetic_contact($cc, $merchantId);
}
function mg_cp_find_contact(PDO $pdo, int $merchantId): array
{
    $contactRef = strtolower(mg_cp_param('contact_id') ?: mg_cp_param('crm_contact_id') ?: mg_cp_param('id'));
    $campaignContactRef = strtolower(mg_cp_param('campaign_contact_id'));
    $walletRef = strtolower(mg_cp_param('wallet_item_id') ?: mg_cp_param('wallet'));
    $email = strtolower(mg_cp_param('email'));
    $userId = (int)(mg_cp_param('user_id') ?: 0);
    try {
        if ($contactRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $contactRef) === 1) {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
            $stmt->execute([$contactRef, $merchantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) return $row;
        }
        if ($campaignContactRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $campaignContactRef) === 1) {
            $stmt = $pdo->prepare('SELECT * FROM campaign_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
            $stmt->execute([$campaignContactRef, $merchantId]);
            $cc = $stmt->fetch(PDO::FETCH_ASSOC); if ($cc) return mg_cp_ensure_crm_contact($pdo, $merchantId, $cc);
        }
        if ($walletRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $walletRef) === 1 && mg_cp_table_exists($pdo, 'wallet_items')) {
            $stmt = $pdo->prepare('SELECT cc.* FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wi.public_id=? AND wi.merchant_user_id=? LIMIT 1');
            $stmt->execute([$walletRef, $merchantId]);
            $cc = $stmt->fetch(PDO::FETCH_ASSOC); if ($cc) return mg_cp_ensure_crm_contact($pdo, $merchantId, $cc);
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
            $stmt->execute([$merchantId, $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) return $row;
            $stmt = $pdo->prepare('SELECT * FROM campaign_contacts WHERE merchant_user_id=? AND email=? ORDER BY updated_at DESC,id DESC LIMIT 1');
            $stmt->execute([$merchantId, $email]);
            $cc = $stmt->fetch(PDO::FETCH_ASSOC); if ($cc) return mg_cp_ensure_crm_contact($pdo, $merchantId, $cc);
        }
        if ($userId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND user_id=? LIMIT 1');
            $stmt->execute([$merchantId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) return $row;
            $stmt = $pdo->prepare('SELECT * FROM campaign_contacts WHERE merchant_user_id=? AND user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1');
            $stmt->execute([$merchantId, $userId]);
            $cc = $stmt->fetch(PDO::FETCH_ASSOC); if ($cc) return mg_cp_ensure_crm_contact($pdo, $merchantId, $cc);
        }
        $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1');
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) return $row;
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.customer_profile.find_contact_failed', 'Customer profile lookup failed.', ['exception_class' => $error::class], $merchantId);
    }
    mg_fail('Customer profile not found for this merchant.', 404);
}
function mg_cp_contact_public_ids(PDO $pdo, int $merchantId, array $contact): array
{
    $ids = array_values(array_filter([(string)($contact['_campaign_contact_public_id'] ?? '')]));
    $email = strtolower((string)($contact['primary_email'] ?? ''));
    $userId = (int)($contact['user_id'] ?? 0);
    if ($email === '' && $userId <= 0) return array_values(array_unique($ids));
    try {
        $where = [];$params = [$merchantId];
        if ($email !== '') { $where[] = 'email=?'; $params[] = $email; }
        if ($userId > 0) { $where[] = 'user_id=?'; $params[] = $userId; }
        $stmt = $pdo->prepare('SELECT public_id FROM campaign_contacts WHERE merchant_user_id=? AND (' . implode(' OR ', $where) . ') ORDER BY updated_at DESC,id DESC LIMIT 50');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $ids[] = (string)$r['public_id'];
    } catch (Throwable) {}
    return array_values(array_unique(array_filter($ids)));
}
function mg_cp_contact_db_ids(PDO $pdo, int $merchantId, array $contact): array
{
    $ids = array_values(array_filter([(int)($contact['_campaign_contact_db_id'] ?? 0)]));
    $email = strtolower((string)($contact['primary_email'] ?? ''));
    $userId = (int)($contact['user_id'] ?? 0);
    if ($email === '' && $userId <= 0) return array_values(array_unique($ids));
    try {
        $where = [];$params = [$merchantId];
        if ($email !== '') { $where[] = 'email=?'; $params[] = $email; }
        if ($userId > 0) { $where[] = 'user_id=?'; $params[] = $userId; }
        $stmt = $pdo->prepare('SELECT id FROM campaign_contacts WHERE merchant_user_id=? AND (' . implode(' OR ', $where) . ') ORDER BY updated_at DESC,id DESC LIMIT 50');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $ids[] = (int)$r['id'];
    } catch (Throwable) {}
    return array_values(array_unique(array_filter($ids)));
}
function mg_cp_wallet_items(PDO $pdo, int $merchantId, array $contact, array $contactDbIds, int $limit = 12): array
{
    if (!mg_cp_table_exists($pdo, 'wallet_items')) return [];
    $userId = (int)($contact['user_id'] ?? 0);$where = ['wi.merchant_user_id=?'];$params = [$merchantId];$sub = [];
    if ($userId > 0) { $sub[] = 'wi.user_id=?'; $params[] = $userId; }
    if ($contactDbIds && mg_cp_column_exists($pdo, 'wallet_items', 'contact_id')) { $sub[] = 'wi.contact_id IN (' . implode(',', array_fill(0, count($contactDbIds), '?')) . ')'; array_push($params, ...$contactDbIds); }
    if (!$sub) return [];
    $where[] = '(' . implode(' OR ', $sub) . ')';
    try {
        $sql = 'SELECT wi.public_id,wi.title_snapshot,wi.status,wi.value_cents_snapshot,wi.currency_snapshot,wi.issued_at,wi.claimed_at,wi.redeemed_at,wi.expires_at,c.title campaign_title,c.public_id campaign_public_id,c.campaign_type FROM wallet_items wi LEFT JOIN campaigns c ON c.id=wi.campaign_id WHERE ' . implode(' AND ', $where) . ' ORDER BY wi.issued_at DESC,wi.id DESC LIMIT ' . max(1, min(50, $limit));
        $stmt = $pdo->prepare($sql);$stmt->execute($params);
        return array_map(static function ($r) {
            $walletId = (string)$r['public_id'];$campaignId = (string)($r['campaign_public_id'] ?? '');
            return ['id'=>$walletId,'wallet_item_id'=>$walletId,'title'=>(string)($r['title_snapshot'] ?: 'Reward'),'campaign'=>(string)($r['campaign_title'] ?? ''),'campaign_id'=>$campaignId,'campaign_type'=>(string)($r['campaign_type'] ?? ''),'status'=>(string)$r['status'],'value'=>mg_cp_money((int)$r['value_cents_snapshot'], (string)$r['currency_snapshot']),'sent_at'=>$r['issued_at'] ?? null,'claimed_at'=>$r['claimed_at'] ?? null,'redeemed_at'=>$r['redeemed_at'] ?? null,'expires_at'=>$r['expires_at'] ?? null,'action_url'=>mg_cp_action_url('reward', $walletId),'campaign_url'=>$campaignId !== '' ? mg_cp_action_url('campaign', $campaignId) : ''];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $error) { mg_security_log('warning', 'merchant.customer_profile.wallets_failed', 'Unable to load profile wallets.', ['exception_class'=>$error::class], $merchantId); return []; }
}
function mg_cp_wallet_stats(array $wallets): array
{
    $total = count($wallets);$claimed = 0;$redeemed = 0;$open = 0;$openValue = 0;$value = 0;$currency = 'USD';
    foreach ($wallets as $w) { $status = (string)($w['status'] ?? '');$cents = (int)round((float)preg_replace('/[^0-9.]/', '', (string)($w['value'] ?? '0')) * 100);$currency = strtoupper(substr((string)($w['value'] ?? 'USD'), 0, 3)) ?: $currency; if (in_array($status, ['claimed','redeemed'], true)) $claimed++; if ($status === 'redeemed') $redeemed++; if (in_array($status, ['issued','viewed','claimed'], true)) { $open++; $openValue += $cents; } $value += $cents; }
    return ['total'=>$total,'claimed'=>$claimed,'redeemed'=>$redeemed,'open'=>$open,'open_value_cents'=>$openValue,'value_cents'=>$value,'currency'=>$currency,'last_reward'=>$wallets[0] ?? null];
}
function mg_cp_messages(PDO $pdo, int $merchantId, array $contactPublicIds, int $limit = 12): array
{
    if (!$contactPublicIds || !mg_cp_table_exists($pdo, 'message_threads') || !mg_cp_table_exists($pdo, 'messages')) return [];
    try {
        $items = [];
        foreach (array_slice($contactPublicIds, 0, 10) as $contactId) {
            $stmt = $pdo->prepare("SELECT m.public_id,m.body,m.sender_user_id,m.created_at,mt.public_id thread_public_id,COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email,'Microgifter user') sender_name FROM message_threads mt INNER JOIN messages m ON m.thread_id=mt.id LEFT JOIN users u ON u.id=m.sender_user_id WHERE mt.conversation_key LIKE ? AND mt.created_by_user_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT " . max(1, min(30, $limit)));
            $stmt->execute(['crm:' . $contactId . ':%', $merchantId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $items[] = ['id'=>(string)$r['public_id'],'message_id'=>(string)$r['public_id'],'thread_id'=>(string)$r['thread_public_id'],'body'=>(string)$r['body'],'sender_name'=>(string)$r['sender_name'],'mine'=>(int)$r['sender_user_id']===$merchantId,'created_at'=>$r['created_at'] ?? null,'action_url'=>mg_cp_action_url('message', (string)$r['thread_public_id'])];
        }
        usort($items, fn($a,$b)=>(strtotime((string)($b['created_at']??''))?:0)<=>(strtotime((string)($a['created_at']??''))?:0));
        return array_slice($items, 0, $limit);
    } catch (Throwable) { return []; }
}
function mg_cp_notes(PDO $pdo, int $merchantId, int $crmContactId, int $limit = 10): array
{
    if ($crmContactId < 1 || !mg_cp_table_exists($pdo, 'merchant_crm_notes')) return [];
    try { $stmt=$pdo->prepare('SELECT n.public_id,n.note,n.created_at,n.updated_at,COALESCE(NULLIF(u.display_name,\'\'),NULLIF(u.full_name,\'\'),u.email,\'Team member\') author_name FROM merchant_crm_notes n LEFT JOIN users u ON u.id=n.author_user_id WHERE n.merchant_user_id=? AND n.crm_contact_id=? ORDER BY n.created_at DESC,n.id DESC LIMIT '.max(1,min(30,$limit)));$stmt->execute([$merchantId,$crmContactId]);return array_map(fn($r)=>['id'=>(string)$r['public_id'],'note_id'=>(string)$r['public_id'],'note'=>(string)$r['note'],'author'=>(string)$r['author_name'],'created_at'=>$r['created_at']??null,'updated_at'=>$r['updated_at']??null,'action_url'=>'#customer-notes'],$stmt->fetchAll(PDO::FETCH_ASSOC)); } catch (Throwable) { return []; }
}
function mg_cp_campaign_sources(PDO $pdo, int $merchantId, int $crmContactId): array
{
    if ($crmContactId < 1 || !mg_cp_table_exists($pdo, 'merchant_crm_contact_campaigns')) return [];
    try { $stmt=$pdo->prepare('SELECT mccc.campaign_type,mccc.first_event_at,mccc.last_event_at,mccc.event_count,c.title campaign_title,c.public_id campaign_public_id FROM merchant_crm_contact_campaigns mccc LEFT JOIN campaigns c ON c.id=mccc.campaign_id WHERE mccc.merchant_user_id=? AND mccc.crm_contact_id=? ORDER BY mccc.last_event_at DESC LIMIT 10');$stmt->execute([$merchantId,$crmContactId]);return array_map(fn($r)=>['campaign'=>(string)($r['campaign_title']?:mg_cp_label((string)$r['campaign_type'])),'campaign_id'=>(string)($r['campaign_public_id']??''),'type'=>(string)$r['campaign_type'],'first_seen'=>$r['first_event_at']??null,'last_seen'=>$r['last_event_at']??null,'interactions'=>(int)$r['event_count'],'action_url'=>!empty($r['campaign_public_id'])?mg_cp_action_url('campaign',(string)$r['campaign_public_id']):''],$stmt->fetchAll(PDO::FETCH_ASSOC)); } catch (Throwable) { return []; }
}
function mg_cp_redemptions(PDO $pdo, int $merchantId, array $contact, int $limit = 12): array
{
    $userId = (int)($contact['user_id'] ?? 0); if ($userId < 1) return [];$items=[];
    try { if (mg_cp_table_exists($pdo,'wallet_item_redemptions')) { $stmt=$pdo->prepare("SELECT wr.public_id,wr.amount_cents,wr.currency,wr.status,wr.redeemed_at,wr.location_reference,ml.name location_name,wi.public_id wallet_item_id,wi.title_snapshot FROM wallet_item_redemptions wr LEFT JOIN wallet_items wi ON wi.id=wr.wallet_item_id LEFT JOIN merchant_locations ml ON ml.id=wr.location_id WHERE wr.merchant_user_id=? AND wr.user_id=? ORDER BY wr.redeemed_at DESC,wr.id DESC LIMIT ".max(1,min(30,$limit)));$stmt->execute([$merchantId,$userId]);foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['public_id'];$items[]=['id'=>$id,'redemption_id'=>$id,'gift_id'=>(string)($r['wallet_item_id']??''),'location'=>(string)($r['location_name']?:$r['location_reference']??''),'amount'=>mg_cp_money((int)$r['amount_cents'],(string)$r['currency']),'status'=>(string)$r['status'],'redeemed_at'=>$r['redeemed_at']??null,'action_url'=>mg_cp_action_url('redemption',$id)];}} } catch (Throwable) {}
    try { if (mg_cp_table_exists($pdo,'microgift_redemptions')) { $stmt=$pdo->prepare("SELECT mr.public_id,mr.amount_cents,mr.currency,mr.status,mr.redeemed_at,mr.location_reference,ml.name location_name,mi.public_id instance_id FROM microgift_redemptions mr LEFT JOIN microgift_instances mi ON mi.id=mr.instance_id LEFT JOIN merchant_locations ml ON ml.id=mr.location_id WHERE mr.merchant_user_id=? AND mr.claimant_user_id=? ORDER BY mr.redeemed_at DESC,mr.id DESC LIMIT ".max(1,min(30,$limit)));$stmt->execute([$merchantId,$userId]);foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['public_id'];$items[]=['id'=>$id,'redemption_id'=>$id,'gift_id'=>(string)($r['instance_id']??''),'location'=>(string)($r['location_name']?:$r['location_reference']??''),'amount'=>mg_cp_money((int)$r['amount_cents'],(string)$r['currency']),'status'=>(string)$r['status'],'redeemed_at'=>$r['redeemed_at']??null,'action_url'=>mg_cp_action_url('redemption',$id)];}} } catch (Throwable) {}
    usort($items, fn($a,$b)=>(strtotime((string)($b['redeemed_at']??''))?:0)<=>(strtotime((string)($a['redeemed_at']??''))?:0));
    return array_slice($items,0,$limit);
}
function mg_cp_tips(PDO $pdo, int $merchantId, array $contact, int $limit = 8): array
{
    if (!mg_cp_table_exists($pdo,'notifications')) return [];$userId=(int)($contact['user_id']??0);$email=strtolower((string)($contact['primary_email']??''));
    try {$where=['user_id=?',"type LIKE '%tip%'"];$params=[$merchantId];$customer=[];if($userId>0){$customer[]="JSON_UNQUOTE(JSON_EXTRACT(context_json,'$.customer_user_id'))=?";$params[]=(string)$userId;}if($email!==''){$customer[]="LOWER(JSON_UNQUOTE(JSON_EXTRACT(context_json,'$.customer_email')))=?";$params[]=$email;}if($customer)$where[]='('.implode(' OR ',$customer).')';$stmt=$pdo->prepare('SELECT public_id,type,title,body,context_json,created_at FROM notifications WHERE '.implode(' AND ',$where).' ORDER BY created_at DESC,id DESC LIMIT '.max(1,min(30,$limit)));$stmt->execute($params);$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){$ctx=mg_cp_json($r['context_json']??null);$tipId=(string)($ctx['tip_id']??$r['public_id']);$out[]=['id'=>(string)$r['public_id'],'tip_id'=>$tipId,'type'=>(string)$r['type'],'amount'=>(string)($ctx['amount']??$ctx['amount_label']??''),'note'=>(string)($r['body']??$r['title']??''),'created_at'=>$r['created_at']??null,'action_url'=>mg_cp_action_url('tip',$tipId)];}return $out;}catch(Throwable){return[];}
}
function mg_cp_events(PDO $pdo, int $merchantId, int $crmContactId, array $wallets, array $messages, array $redemptions, array $tips, array $notes): array
{
    $events=[];
    if ($crmContactId > 0 && mg_cp_table_exists($pdo,'merchant_crm_contact_events')) { try { $stmt=$pdo->prepare('SELECT public_id,event_type,campaign_type,source_type,source_public_id,value_cents,metadata_json,created_at FROM merchant_crm_contact_events WHERE merchant_user_id=? AND crm_contact_id=? ORDER BY created_at DESC,id DESC LIMIT 30');$stmt->execute([$merchantId,$crmContactId]);foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){$ctx=mg_cp_json($r['metadata_json']??null);$events[]=['id'=>(string)$r['public_id'],'type'=>(string)$r['event_type'],'title'=>mg_cp_label((string)$r['event_type']),'body'=>mg_cp_label((string)$r['campaign_type']).' · '.mg_cp_label((string)$r['source_type']),'at'=>$r['created_at']??null,'icon'=>'➤','tone'=>'is-blue','object_id'=>(string)($ctx['wallet_item_id']??$ctx['thread_id']??$ctx['redemption_id']??$r['source_public_id']??''),'action_url'=>''];}} catch(Throwable){} }
    foreach($wallets as $w){$events[]=['id'=>(string)$w['wallet_item_id'],'type'=>'reward','title'=>'Reward '.mg_cp_label((string)$w['status']),'body'=>(string)$w['title'],'at'=>$w['sent_at']??null,'icon'=>'🎁','tone'=>'is-blue','object_id'=>(string)$w['wallet_item_id'],'action_url'=>(string)$w['action_url']];if(!empty($w['claimed_at']))$events[]=['id'=>(string)$w['wallet_item_id'],'type'=>'claim','title'=>'Reward Claimed','body'=>(string)$w['title'],'at'=>$w['claimed_at'],'icon'=>'✓','tone'=>'is-green','object_id'=>(string)$w['wallet_item_id'],'action_url'=>(string)$w['action_url']];if(!empty($w['redeemed_at']))$events[]=['id'=>(string)$w['wallet_item_id'],'type'=>'redeem','title'=>'Reward Redeemed','body'=>(string)$w['title'],'at'=>$w['redeemed_at'],'icon'=>'✓','tone'=>'is-green','object_id'=>(string)$w['wallet_item_id'],'action_url'=>(string)$w['action_url']];}
    foreach($messages as $m)$events[]=['id'=>(string)$m['message_id'],'type'=>'message','title'=>!empty($m['mine'])?'Merchant Replied':'Customer Sent Message','body'=>(string)$m['body'],'at'=>$m['created_at']??null,'icon'=>!empty($m['mine'])?'↩':'💬','tone'=>!empty($m['mine'])?'is-blue':'is-purple','object_id'=>(string)$m['thread_id'],'action_url'=>(string)$m['action_url']];
    foreach($redemptions as $r)$events[]=['id'=>(string)$r['redemption_id'],'type'=>'redemption','title'=>'Reward Redeemed','body'=>trim(($r['amount']??'').' · '.($r['location']??''),' ·'),'at'=>$r['redeemed_at']??null,'icon'=>'✓','tone'=>'is-green','object_id'=>(string)$r['redemption_id'],'action_url'=>(string)$r['action_url']];
    foreach($tips as $t)$events[]=['id'=>(string)$t['tip_id'],'type'=>'tip','title'=>'Tip Activity','body'=>trim(($t['amount']??'').' · '.($t['note']??''),' ·'),'at'=>$t['created_at']??null,'icon'=>'♥','tone'=>'is-pink','object_id'=>(string)$t['tip_id'],'action_url'=>(string)$t['action_url']];
    foreach($notes as $n)$events[]=['id'=>(string)$n['note_id'],'type'=>'note','title'=>'CRM Note Added','body'=>(string)$n['note'],'at'=>$n['created_at']??null,'icon'=>'✎','tone'=>'is-orange','object_id'=>(string)$n['note_id'],'action_url'=>'#customer-notes'];
    usort($events,fn($a,$b)=>(strtotime((string)($b['at']??''))?:0)<=>(strtotime((string)($a['at']??''))?:0));return array_slice($events,0,30);
}
function mg_cp_activity_chart(array $wallets): array
{
    $months=[];for($i=5;$i>=0;$i--){$key=date('Y-m',strtotime('-'.$i.' months'));$months[$key]=['label'=>date("M 'y",strtotime($key.'-01')),'sent'=>0,'claimed'=>0];}foreach($wallets as $w){$k=!empty($w['sent_at'])?date('Y-m',strtotime((string)$w['sent_at'])):'';if(isset($months[$k]))$months[$k]['sent']++;$c=!empty($w['claimed_at'])?date('Y-m',strtotime((string)$w['claimed_at'])):'';if(isset($months[$c]))$months[$c]['claimed']++;}return array_values($months);
}
function mg_cp_build_profile(PDO $pdo, int $merchantId, array $contact): array
{
    $contactDbIds=mg_cp_contact_db_ids($pdo,$merchantId,$contact);$contactPublicIds=mg_cp_contact_public_ids($pdo,$merchantId,$contact);$primaryCampaignContactId=(string)($contact['_campaign_contact_public_id']??$contactPublicIds[0]??'');$crmContactId=(string)$contact['public_id'];$email=(string)($contact['primary_email']??'');$userId=(int)($contact['user_id']??0);
    $wallets=mg_cp_wallet_items($pdo,$merchantId,$contact,$contactDbIds,12);$walletStats=mg_cp_wallet_stats($wallets);$messages=mg_cp_messages($pdo,$merchantId,$contactPublicIds,12);$notes=mg_cp_notes($pdo,$merchantId,(int)($contact['id']??0),10);$campaigns=mg_cp_campaign_sources($pdo,$merchantId,(int)($contact['id']??0));$redemptions=mg_cp_redemptions($pdo,$merchantId,$contact,12);$tips=mg_cp_tips($pdo,$merchantId,$contact,12);$tags=mg_cp_json($contact['tags_json']??null);if(!$tags)$tags=array_values(array_filter([(string)($contact['lifecycle_stage']??''),(string)($contact['last_campaign_type']??'')]));
    $claimed=max(0,(int)$walletStats['claimed']);$total=max(0,(int)$walletStats['total']);$redemptionRate=$total>0?(int)round(($claimed/$total)*100):0;$tipCents=0;$tipCount=count($tips);$valueCents=(int)($contact['total_purchase_cents']??0)+(int)$walletStats['value_cents']+$tipCents;$latest=$contact['last_engaged_at']??$contact['last_seen_at']??$contact['updated_at']??null;
    $actionIds=['crm_contact_id'=>$crmContactId,'contact_id'=>$crmContactId,'campaign_contact_id'=>$primaryCampaignContactId,'campaign_contact_ids'=>$contactPublicIds,'customer_user_id'=>$userId,'user_id'=>$userId,'email'=>$email];
    $actions=['can_message'=>$primaryCampaignContactId!=='','can_send_reward'=>$primaryCampaignContactId!==''&&$userId>0,'can_followup'=>$primaryCampaignContactId!=='','can_add_note'=>(int)($contact['id']??0)>0,'message_endpoint'=>'/api/merchant/crm-message.php','reward_endpoint'=>'/api/merchant/crm-send-gift.php','followup_endpoint'=>'/api/merchant/crm-followup.php','note_endpoint'=>'/api/merchant/customer-profile.php'];
    return ['customer'=>['id'=>$crmContactId,'crm_contact_id'=>$crmContactId,'campaign_contact_id'=>$primaryCampaignContactId,'campaign_contact_ids'=>$contactPublicIds,'user_id'=>$userId,'name'=>(string)($contact['display_name']?:$email?:'Customer'),'email'=>$email,'phone'=>(string)($contact['primary_phone']??''),'initials'=>mg_cp_initials((string)($contact['display_name']??''),$email),'status'=>(string)($contact['crm_status']??'active'),'stage'=>(string)($contact['lifecycle_stage']??'lead'),'tags'=>$tags,'first_seen_at'=>$contact['first_seen_at']??null,'last_activity_at'=>$latest,'source_campaign'=>mg_cp_label((string)($contact['last_campaign_type']??'unknown')),'preferred_location'=>$redemptions[0]['location']??'—'],'action_ids'=>$actionIds,'actions'=>$actions,'links'=>['customer_profile'=>mg_cp_url('/merchant-customer.php',['contact_id'=>$crmContactId]),'message'=>$primaryCampaignContactId!==''?mg_cp_url('/merchant-crm.php',['tab'=>'contacts','action'=>'message','campaign_contact_id'=>$primaryCampaignContactId]):'','send_reward'=>$primaryCampaignContactId!==''?mg_cp_url('/merchant-crm.php',['tab'=>'contacts','action'=>'reward','campaign_contact_id'=>$primaryCampaignContactId]):'','followup'=>$primaryCampaignContactId!==''?mg_cp_url('/merchant-crm.php',['tab'=>'contacts','action'=>'followup','campaign_contact_id'=>$primaryCampaignContactId]):'','notes'=>'#customer-notes'],'metrics'=>['wallet_rewards_received'=>$total,'claimed_rewards'=>$claimed,'open_wallet_items'=>$walletStats['open'],'open_wallet_value'=>mg_cp_money((int)$walletStats['open_value_cents'],$walletStats['currency']),'tips_total'=>mg_cp_money($tipCents),'tip_count'=>$tipCount,'redemption_rate'=>$redemptionRate,'estimated_customer_value'=>mg_cp_money($valueCents)],'snapshot'=>['last_reward'=>$walletStats['last_reward']['title']??'—','current_open_wallet_item'=>((int)$walletStats['open']>0?$walletStats['open'].' open wallet item'.((int)$walletStats['open']===1?'':'s'):'No open wallet items'),'last_claim_location'=>$redemptions[0]['location']??'—','favorite_campaign_type'=>$contact['last_campaign_type']?mg_cp_label((string)$contact['last_campaign_type']):'—','average_redemption_delay'=>'—'],'activity_chart'=>mg_cp_activity_chart($wallets),'messages'=>$messages,'rewards'=>$wallets,'tips'=>$tips,'campaign_sources'=>$campaigns,'notes'=>$notes,'redemptions'=>$redemptions,'timeline'=>mg_cp_events($pdo,$merchantId,(int)($contact['id']??0),$wallets,$messages,$redemptions,$tips,$notes),'debug'=>['fail_soft'=>true,'profile_version'=>2]];
}

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=$method==='POST'?mg_require_permission('merchant.campaigns.manage'):mg_require_permission('merchant.campaigns.view');
$merchantId=(int)$user['id'];$pdo=mg_db();mg_merchant_ensure_workspace($pdo,$user);
if($method==='POST'){
    $input=mg_input();mg_require_csrf_for_write($input);$contactId=strtolower(trim((string)($input['contact_id']??'')));$note=trim((string)($input['note']??''));if(!preg_match('/^[0-9a-f-]{36}$/i',$contactId)||$note===''||mb_strlen($note)>4000)mg_fail('A valid customer and note are required.',422);$stmt=$pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');$stmt->execute([$contactId,$merchantId]);$contact=$stmt->fetch(PDO::FETCH_ASSOC);if(!$contact)mg_fail('Customer profile not found for this merchant.',404);$publicId=mg_cp_uuid();$pdo->prepare("INSERT INTO merchant_crm_notes (public_id,merchant_user_id,crm_contact_id,author_user_id,note,visibility,created_at,updated_at) VALUES (?,?,?,?,?,'merchant_internal',NOW(),NOW())")->execute([$publicId,$merchantId,(int)$contact['id'],$merchantId,$note]);mg_merchant_crm_record_event($pdo,['merchant_user_id'=>$merchantId,'campaign_type'=>'merchant_crm','event_type'=>'crm.note.added','source_type'=>'merchant_customer_profile','source_public_id'=>$publicId,'user_id'=>(int)($contact['user_id']??0),'email'=>(string)($contact['primary_email']??''),'name'=>(string)($contact['display_name']??''),'metadata'=>['note_id'=>$publicId,'crm_contact_id'=>(string)$contact['public_id']]]);mg_ok(['note_id'=>$publicId,'profile'=>mg_cp_build_profile($pdo,$merchantId,$contact)],'CRM note added.',201);
}
if($method!=='GET')mg_fail('Method not allowed.',405);
try{$contact=mg_cp_find_contact($pdo,$merchantId);mg_ok(mg_cp_build_profile($pdo,$merchantId,$contact));}catch(Throwable $error){mg_security_log('error','merchant.customer_profile.failed','Unable to load customer profile.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);mg_fail($error instanceof MgHttpException ? $error->getMessage() : 'Unable to load customer profile.', $error instanceof MgHttpException ? $error->getCode() : 500);}
