<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function mg_admin_commerce_filters(array $input): array
{
    $domain = strtolower(mg_admin_commerce_text($input['domain'] ?? 'all', 24));
    $allowedDomains = ['all','order','refund','dispute','subscription','tip','microgift','case'];
    if (!in_array($domain, $allowedDomains, true)) throw new MgAdminCommerceException('Invalid commerce domain filter.', 422);
    $status = strtolower(mg_admin_commerce_text($input['status'] ?? '', 50));
    if ($status !== '' && preg_match('/^[a-z0-9._-]+$/', $status) !== 1) throw new MgAdminCommerceException('Invalid commerce status filter.', 422);
    $priority = strtolower(mg_admin_commerce_text($input['priority'] ?? '', 16));
    if ($priority !== '' && !in_array($priority, ['low','normal','high','urgent'], true)) throw new MgAdminCommerceException('Invalid case priority filter.', 422);
    return [
        'q'=>mb_strtolower(mg_admin_commerce_text($input['q'] ?? '',160)),'domain'=>$domain,'status'=>$status,'priority'=>$priority,
        'merchant_user_id'=>mg_admin_commerce_user_id($input['merchant_user_id']??null),'customer_user_id'=>mg_admin_commerce_user_id($input['customer_user_id']??null),
        'date_from'=>mg_admin_commerce_date($input['date_from']??null),'date_to'=>mg_admin_commerce_date($input['date_to']??null),
        'limit'=>mg_admin_commerce_limit($input['limit']??MG_ADMIN_COMMERCE_DEFAULT_LIMIT),'page'=>mg_admin_commerce_page($input['page']??1),
    ];
}

function mg_admin_commerce_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_admin_commerce_schema_ready(PDO $pdo): bool
{
    foreach (['users','commerce_orders','payment_refunds','payment_disputes','subscriptions','subscription_plans','tips','microgift_instances','microgift_claim_attempts','commerce_operation_cases'] as $table) {
        if (!mg_admin_commerce_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_admin_commerce_empty(array $filters): array
{
    return ['items'=>[],'page'=>$filters['page'],'limit'=>$filters['limit'],'has_more'=>false,'next_page'=>null,'filters'=>$filters,'summary'=>['orders_total'=>0,'orders_attention'=>0,'refunds_attention'=>0,'disputes_open'=>0,'subscriptions_attention'=>0,'tips_posted'=>0,'microgifts_active'=>0,'claim_failures_24h'=>0,'open_cases'=>0,'paid_volume_30d_cents'=>0,'refund_volume_30d_cents'=>0,'tip_volume_30d_cents'=>0,'currency'=>'USD','generated_at'=>gmdate('c'),'setup_required'=>true]];
}

function mg_admin_commerce_subqueries(string $domain): array
{
    $queries=[
'order'=><<<'SQL'
SELECT 'order' entity_type,o.id entity_id,o.public_id,o.payment_status status,o.fulfillment_status secondary_status,o.total_cents amount_cents,o.currency,o.merchant_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_display_name,mu.email merchant_email,o.buyer_user_id customer_user_id,COALESCE(cu.display_name,cu.full_name,cu.email) customer_display_name,cu.email customer_email,
CONCAT('Order ',LEFT(o.public_id,8)) title,o.source_type subtitle,o.created_at,o.updated_at,IF(o.payment_status IN ('requires_action','failed','disputed') OR o.fulfillment_status='failed',1,0) attention,NULL priority
FROM commerce_orders o INNER JOIN users mu ON mu.id=o.merchant_user_id INNER JOIN users cu ON cu.id=o.buyer_user_id
SQL,
'refund'=><<<'SQL'
SELECT 'refund' entity_type,r.id entity_id,r.public_id,r.status,r.reason secondary_status,r.amount_cents,r.currency,COALESCE(r.merchant_user_id,t.recipient_user_id) merchant_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_display_name,mu.email merchant_email,COALESCE(o.buyer_user_id,t.sender_user_id) customer_user_id,COALESCE(cu.display_name,cu.full_name,cu.email) customer_display_name,cu.email customer_email,
CONCAT('Refund ',LEFT(r.public_id,8)) title,CONCAT(COALESCE(r.source_type,'order'),' ',LEFT(COALESCE(r.source_reference,o.public_id,t.public_id,''),12)) subtitle,r.created_at,r.updated_at,IF(r.status IN ('pending','processing','failed'),1,0) attention,NULL priority
FROM payment_refunds r LEFT JOIN commerce_orders o ON o.id=r.order_id LEFT JOIN tips t ON t.id=r.tip_id LEFT JOIN users mu ON mu.id=COALESCE(r.merchant_user_id,t.recipient_user_id) LEFT JOIN users cu ON cu.id=COALESCE(o.buyer_user_id,t.sender_user_id)
SQL,
'dispute'=><<<'SQL'
SELECT 'dispute' entity_type,d.id entity_id,d.public_id,d.status,COALESCE(d.reason,'provider dispute') secondary_status,d.amount_cents,d.currency,COALESCE(d.merchant_user_id,t.recipient_user_id) merchant_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_display_name,mu.email merchant_email,COALESCE(o.buyer_user_id,t.sender_user_id) customer_user_id,COALESCE(cu.display_name,cu.full_name,cu.email) customer_display_name,cu.email customer_email,
CONCAT('Dispute ',LEFT(d.public_id,8)) title,CONCAT(COALESCE(d.source_type,'order'),' ',LEFT(COALESCE(d.source_reference,o.public_id,t.public_id,''),12)) subtitle,d.created_at,d.updated_at,IF(d.status IN ('warning_needs_response','needs_response','under_review'),1,0) attention,NULL priority
FROM payment_disputes d LEFT JOIN commerce_orders o ON o.id=d.order_id LEFT JOIN tips t ON t.id=d.tip_id LEFT JOIN users mu ON mu.id=COALESCE(d.merchant_user_id,t.recipient_user_id) LEFT JOIN users cu ON cu.id=COALESCE(o.buyer_user_id,t.sender_user_id)
SQL,
'subscription'=><<<'SQL'
SELECT 'subscription' entity_type,s.id entity_id,s.public_id,s.status,s.recovery_status secondary_status,s.amount_cents,s.currency,s.recipient_user_id merchant_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_display_name,mu.email merchant_email,s.subscriber_user_id customer_user_id,COALESCE(cu.display_name,cu.full_name,cu.email) customer_display_name,cu.email customer_email,
p.name title,CONCAT(p.interval_count,' ',p.interval_unit) subtitle,s.created_at,s.updated_at,IF(s.status IN ('pending_payment','past_due','paused') OR s.recovery_status<>'clear',1,0) attention,NULL priority
FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id INNER JOIN users mu ON mu.id=s.recipient_user_id INNER JOIN users cu ON cu.id=s.subscriber_user_id
SQL,
'tip'=><<<'SQL'
SELECT 'tip' entity_type,t.id entity_id,t.public_id,t.status,t.target_type secondary_status,t.amount_cents,t.currency,t.recipient_user_id merchant_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_display_name,mu.email merchant_email,t.sender_user_id customer_user_id,COALESCE(cu.display_name,cu.full_name,cu.email) customer_display_name,cu.email customer_email,
CONCAT('Tip to ',COALESCE(mu.display_name,mu.full_name,mu.email)) title,t.target_reference subtitle,t.created_at,t.updated_at,IF(t.status IN ('failed','disputed','requires_action','processing'),1,0) attention,NULL priority
FROM tips t INNER JOIN users mu ON mu.id=t.recipient_user_id INNER JOIN users cu ON cu.id=t.sender_user_id
SQL,
'microgift'=><<<'SQL'
SELECT 'microgift' entity_type,m.id entity_id,m.public_id,m.status,m.source_type secondary_status,COALESCE(m.face_value_cents,0) amount_cents,m.currency,m.issuer_user_id merchant_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_display_name,mu.email merchant_email,COALESCE(m.recipient_user_id,m.owner_user_id) customer_user_id,COALESCE(cu.display_name,cu.full_name,cu.email) customer_display_name,cu.email customer_email,
m.title_snapshot title,m.source_reference subtitle,m.created_at,m.updated_at,IF(m.status IN ('claim_pending','expired','cancelled','revoked'),1,0) attention,NULL priority
FROM microgift_instances m INNER JOIN users mu ON mu.id=m.issuer_user_id LEFT JOIN users cu ON cu.id=COALESCE(m.recipient_user_id,m.owner_user_id)
SQL,
'case'=><<<'SQL'
SELECT 'case' entity_type,c.id entity_id,c.public_id,c.status,c.subject_type secondary_status,NULL amount_cents,NULL currency,NULL merchant_user_id,NULL merchant_display_name,NULL merchant_email,NULL customer_user_id,NULL customer_display_name,NULL customer_email,
c.summary title,c.subject_reference subtitle,c.created_at,c.updated_at,IF(c.status IN ('open','reviewing') AND c.priority IN ('high','urgent'),1,0) attention,c.priority
FROM commerce_operation_cases c
SQL,
    ];
    return $domain==='all'?array_values($queries):[$queries[$domain]];
}

function mg_admin_commerce_list(PDO $pdo,array $input): array
{
    $f=mg_admin_commerce_filters($input);
    if (!mg_admin_commerce_schema_ready($pdo)) return mg_admin_commerce_empty($f);
    $union=implode("\nUNION ALL\n",mg_admin_commerce_subqueries($f['domain']));
    $sql='SELECT activity.*,CASE WHEN activity.entity_type="case" THEN 0 ELSE (SELECT COUNT(*) FROM commerce_operation_cases cc WHERE cc.subject_type=activity.entity_type AND cc.subject_reference=activity.public_id AND cc.status IN ("open","reviewing")) END case_count FROM ('.$union.') activity WHERE 1=1';$params=[];
    if($f['q']!==''){$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$f['q']).'%';$sql.=' AND LOWER(CONCAT_WS(" ",activity.public_id,activity.title,activity.subtitle,activity.merchant_display_name,activity.merchant_email,activity.customer_display_name,activity.customer_email)) LIKE ? ESCAPE "!"';$params[]=$needle;}
    if($f['status']==='attention')$sql.=' AND activity.attention=1';elseif($f['status']!==''){$sql.=' AND (LOWER(activity.status)=? OR LOWER(COALESCE(activity.secondary_status,""))=?)';$params[]=$f['status'];$params[]=$f['status'];}
    if($f['priority']!==''){$sql.=' AND activity.priority=?';$params[]=$f['priority'];}
    if($f['merchant_user_id']!==null){$sql.=' AND activity.merchant_user_id=?';$params[]=$f['merchant_user_id'];}
    if($f['customer_user_id']!==null){$sql.=' AND activity.customer_user_id=?';$params[]=$f['customer_user_id'];}
    if($f['date_from']!==null){$sql.=' AND activity.created_at>=?';$params[]=$f['date_from'].' 00:00:00';}
    if($f['date_to']!==null){$until=(new DateTimeImmutable($f['date_to'],new DateTimeZone('UTC')))->modify('+1 day');$sql.=' AND activity.created_at<?';$params[]=$until->format('Y-m-d 00:00:00');}
    $offset=($f['page']-1)*$f['limit'];$sql.=' ORDER BY activity.created_at DESC,activity.entity_type ASC,activity.entity_id DESC LIMIT '.($f['limit']+1).' OFFSET '.$offset;
    $rows=mg_admin_commerce_all($pdo,$sql,$params);$hasMore=count($rows)>$f['limit'];if($hasMore)array_pop($rows);
    $items=array_map(static fn(array $r):array=>[
        'entity_type'=>(string)$r['entity_type'],'entity_id'=>(int)$r['entity_id'],'public_id'=>(string)$r['public_id'],'status'=>(string)$r['status'],'secondary_status'=>$r['secondary_status']!==null?(string)$r['secondary_status']:null,
        'amount_cents'=>$r['amount_cents']!==null?(int)$r['amount_cents']:null,'currency'=>$r['currency']!==null?(string)$r['currency']:null,'merchant'=>mg_admin_commerce_user_summary($r,'merchant'),'customer'=>mg_admin_commerce_user_summary($r,'customer'),
        'title'=>(string)$r['title'],'subtitle'=>$r['subtitle']!==null?(string)$r['subtitle']:null,'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at'],'attention'=>(bool)$r['attention'],'priority'=>$r['priority']!==null?(string)$r['priority']:null,'case_count'=>(int)$r['case_count'],
    ],$rows);
    return ['items'=>$items,'page'=>$f['page'],'limit'=>$f['limit'],'has_more'=>$hasMore,'next_page'=>$hasMore?$f['page']+1:null,'filters'=>$f,'summary'=>mg_admin_commerce_summary($pdo)];
}

function mg_admin_commerce_summary(PDO $pdo): array
{
    if (!mg_admin_commerce_schema_ready($pdo)) return mg_admin_commerce_empty(['page'=>1,'limit'=>MG_ADMIN_COMMERCE_DEFAULT_LIMIT])['summary'];
    $r=mg_admin_commerce_one($pdo,<<<'SQL'
SELECT
(SELECT COUNT(*) FROM commerce_orders) orders_total,
(SELECT COUNT(*) FROM commerce_orders WHERE payment_status IN ('requires_action','failed','disputed') OR fulfillment_status='failed') orders_attention,
(SELECT COUNT(*) FROM payment_refunds WHERE status IN ('pending','processing','failed')) refunds_attention,
(SELECT COUNT(*) FROM payment_disputes WHERE status IN ('warning_needs_response','needs_response','under_review')) disputes_open,
(SELECT COUNT(*) FROM subscriptions WHERE status IN ('pending_payment','past_due','paused') OR recovery_status<>'clear') subscriptions_attention,
(SELECT COUNT(*) FROM tips WHERE status='posted') tips_posted,
(SELECT COUNT(*) FROM microgift_instances WHERE status IN ('issued','delivered','claim_pending','claimed','redeemable')) microgifts_active,
(SELECT COUNT(*) FROM microgift_claim_attempts WHERE result<>'approved' AND attempted_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)) claim_failures_24h,
(SELECT COUNT(*) FROM commerce_operation_cases WHERE status IN ('open','reviewing')) open_cases,
(SELECT COALESCE(SUM(total_cents),0) FROM commerce_orders WHERE payment_status IN ('paid','partially_refunded','refunded') AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) paid_volume_30d_cents,
(SELECT COALESCE(SUM(amount_cents),0) FROM payment_refunds WHERE status='succeeded' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) refund_volume_30d_cents,
(SELECT COALESCE(SUM(amount_cents),0) FROM tips WHERE status IN ('posted','reversed') AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) tip_volume_30d_cents
SQL)??[];
    $out=[];foreach(['orders_total','orders_attention','refunds_attention','disputes_open','subscriptions_attention','tips_posted','microgifts_active','claim_failures_24h','open_cases','paid_volume_30d_cents','refund_volume_30d_cents','tip_volume_30d_cents'] as $key)$out[$key]=(int)($r[$key]??0);$out['currency']='USD';$out['generated_at']=gmdate('c');return $out;
}
