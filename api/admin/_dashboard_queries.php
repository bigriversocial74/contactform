<?php
declare(strict_types=1);

const MG_ADMIN_DASHBOARD_RECENT_LIMIT = 8;

function mg_admin_dashboard_query_count_reset(): void { $GLOBALS['mg_admin_dashboard_query_count'] = 0; }
function mg_admin_dashboard_query_count(): int { return (int)($GLOBALS['mg_admin_dashboard_query_count'] ?? 0); }
function mg_admin_dashboard_query(PDO $pdo,string $sql,array $params=[]): PDOStatement
{
    $GLOBALS['mg_admin_dashboard_query_count'] = mg_admin_dashboard_query_count() + 1;
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt;
}

function mg_admin_dashboard_existing_tables(PDO $pdo): array
{
    $names=['users','public_profiles','user_model_assignments','merchant_storefronts','catalog_products','feed_posts','commerce_orders','payment_refunds','payment_disputes','subscriptions','tips','microgift_instances','microgift_claims','microgift_redemptions','operational_alerts','security_logs','audit_logs','user_sessions','demand_signal_orchestrations','operational_incidents','deployment_releases','operational_check_results','profile_moderation_cases','profile_moderation_actions','profile_moderation_appeals'];
    $stmt=mg_admin_dashboard_query($pdo,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($names),'?')).')',$names);
    return array_fill_keys(array_map('strval',array_column($stmt->fetchAll(PDO::FETCH_ASSOC),'TABLE_NAME')),true);
}

function mg_admin_dashboard_platform(PDO $pdo,array $tables,string $cutoff,bool $includeModeration=false): ?array
{
    foreach(['users','public_profiles','user_model_assignments','merchant_storefronts','catalog_products','feed_posts'] as $table)if(empty($tables[$table]))return null;
    $selects=[
      '(SELECT COUNT(*) FROM users) users_total',
      "(SELECT COUNT(*) FROM users WHERE status='active') users_active",
      "(SELECT COUNT(*) FROM users WHERE status='pending') users_pending",
      '(SELECT COUNT(*) FROM users WHERE created_at>=?) users_new',
      "(SELECT COUNT(*) FROM public_profiles WHERE status='active') profiles_active",
      "(SELECT COUNT(*) FROM public_profiles WHERE status='draft') profiles_draft",
      "(SELECT COUNT(*) FROM public_profiles WHERE status='suspended') profiles_suspended",
      "(SELECT COUNT(*) FROM user_model_assignments WHERE status='pending') model_requests_pending",
      "(SELECT COUNT(*) FROM merchant_storefronts WHERE status='published') storefronts_published",
      "(SELECT COUNT(*) FROM catalog_products WHERE status='published') products_published",
      "(SELECT COUNT(*) FROM feed_posts WHERE status IN ('published','promoted')) posts_published",
    ];
    if($includeModeration&&!empty($tables['profile_moderation_cases'])){
      $selects[]="(SELECT COUNT(*) FROM profile_moderation_cases WHERE status IN ('open','in_review','actioned','appealed')) moderation_active";
      $selects[]="(SELECT COUNT(*) FROM profile_moderation_cases WHERE status='appealed') moderation_appealed";
      $selects[]="(SELECT COUNT(*) FROM profile_moderation_cases WHERE priority='urgent' AND status IN ('open','in_review','actioned','appealed')) moderation_urgent";
    }
    $row=mg_admin_dashboard_query($pdo,'SELECT '.implode(',',$selects),[$cutoff])->fetch(PDO::FETCH_ASSOC)?:[];
    $result=array_map('intval',$row);
    foreach(['moderation_active','moderation_appealed','moderation_urgent'] as $key)if(!array_key_exists($key,$result))$result[$key]=null;
    return $result;
}

function mg_admin_dashboard_commerce(PDO $pdo,array $tables,string $cutoff): ?array
{
    foreach(['commerce_orders','payment_refunds','payment_disputes','subscriptions','tips','microgift_instances','microgift_claims','microgift_redemptions'] as $table)if(empty($tables[$table]))return null;
    $row=mg_admin_dashboard_query($pdo,"SELECT
      (SELECT COUNT(*) FROM commerce_orders WHERE payment_status='paid' AND paid_at>=?) paid_orders,
      (SELECT COALESCE(SUM(total_cents),0) FROM commerce_orders WHERE payment_status IN ('paid','partially_refunded') AND paid_at>=?) gross_volume_cents,
      (SELECT COUNT(*) FROM commerce_orders WHERE fulfillment_status IN ('pending','issuing','partial','failed')) fulfillment_attention,
      (SELECT COUNT(*) FROM payment_refunds WHERE status IN ('pending','processing')) open_refunds,
      (SELECT COUNT(*) FROM payment_disputes WHERE status IN ('warning_needs_response','needs_response','under_review')) open_disputes,
      (SELECT COUNT(*) FROM subscriptions WHERE status IN ('trialing','active','cancel_pending') AND recovery_status='clear' AND current_period_end>UTC_TIMESTAMP()) active_subscriptions,
      (SELECT COALESCE(SUM(amount_cents),0) FROM subscriptions WHERE status IN ('trialing','active','cancel_pending') AND recovery_status='clear' AND current_period_end>UTC_TIMESTAMP()) recurring_value_cents,
      (SELECT COUNT(*) FROM tips WHERE status='posted' AND posted_at>=?) posted_tips,
      (SELECT COALESCE(SUM(amount_cents),0) FROM tips WHERE status='posted' AND posted_at>=?) tip_volume_cents,
      (SELECT COUNT(*) FROM microgift_instances WHERE status IN ('issued','delivered','claim_pending','claimed','redeemable')) open_microgifts,
      (SELECT COUNT(*) FROM microgift_claims WHERE status='completed' AND completed_at>=?) completed_claims,
      (SELECT COUNT(*) FROM microgift_redemptions WHERE status='completed' AND redeemed_at>=?) completed_redemptions",[$cutoff,$cutoff,$cutoff,$cutoff,$cutoff,$cutoff])->fetch(PDO::FETCH_ASSOC)?:[];
    return array_map('intval',$row);
}

function mg_admin_dashboard_operations(PDO $pdo,array $tables,string $cutoff): array
{
    $result=['open_alerts'=>null,'critical_alerts'=>null,'security_warnings'=>null,'active_sessions'=>null,'open_incidents'=>null,'failed_orchestrations'=>null,'review_orchestrations'=>null,'failed_checks'=>null];$selects=[];$params=[];
    if(!empty($tables['operational_alerts'])){$selects[]="(SELECT COUNT(*) FROM operational_alerts WHERE status IN ('open','acknowledged')) open_alerts";$selects[]="(SELECT COUNT(*) FROM operational_alerts WHERE severity='critical' AND status IN ('open','acknowledged')) critical_alerts";}
    if(!empty($tables['security_logs'])){$selects[]="(SELECT COUNT(*) FROM security_logs WHERE severity IN ('warning','error','critical') AND created_at>=?) security_warnings";$params[]=$cutoff;}
    if(!empty($tables['user_sessions']))$selects[]="(SELECT COUNT(*) FROM user_sessions WHERE revoked_at IS NULL AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())) active_sessions";
    if(!empty($tables['operational_incidents']))$selects[]="(SELECT COUNT(*) FROM operational_incidents WHERE status IN ('open','investigating','mitigated')) open_incidents";
    if(!empty($tables['demand_signal_orchestrations'])){$selects[]="(SELECT COUNT(*) FROM demand_signal_orchestrations WHERE status='failed' AND updated_at>=?) failed_orchestrations";$params[]=$cutoff;$selects[]="(SELECT COUNT(*) FROM demand_signal_orchestrations WHERE status='review_required') review_orchestrations";}
    if(!empty($tables['operational_check_results']))$selects[]="(SELECT COUNT(*) FROM operational_check_results WHERE status='fail' AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())) failed_checks";
    if($selects){$row=mg_admin_dashboard_query($pdo,'SELECT '.implode(',',$selects),$params)->fetch(PDO::FETCH_ASSOC)?:[];foreach($row as $key=>$value)$result[$key]=(int)$value;}
    return $result;
}

function mg_admin_dashboard_safe_url(mixed $value): ?string
{
    $url=trim((string)$value);
    if($url===''||strlen($url)>500||preg_match('/[\x00-\x1F\x7F]/',$url)===1)return null;
    if(str_starts_with($url,'/')&&!str_starts_with($url,'//'))return $url;
    if(filter_var($url,FILTER_VALIDATE_URL)===false)return null;
    $parts=parse_url($url);
    if(!is_array($parts)||!isset($parts['scheme'],$parts['host']))return null;
    if(!in_array(strtolower((string)$parts['scheme']),['http','https'],true))return null;
    if(isset($parts['user'])||isset($parts['pass']))return null;
    return $url;
}

function mg_admin_dashboard_recent_alerts(PDO $pdo,array $tables): array
{
    if(empty($tables['operational_alerts']))return [];
    $rows=mg_admin_dashboard_query($pdo,"SELECT public_id,alert_type,severity,status,title,body,action_url,created_at FROM operational_alerts WHERE status IN ('open','acknowledged') ORDER BY FIELD(severity,'critical','high','warning','info'),created_at DESC LIMIT ".MG_ADMIN_DASHBOARD_RECENT_LIMIT)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row):array=>['id'=>(string)$row['public_id'],'type'=>(string)$row['alert_type'],'severity'=>(string)$row['severity'],'status'=>(string)$row['status'],'title'=>(string)$row['title'],'body'=>$row['body']!==null?mb_substr((string)$row['body'],0,300):null,'action_url'=>mg_admin_dashboard_safe_url($row['action_url']??null),'created_at'=>(string)$row['created_at']],$rows);
}

function mg_admin_dashboard_recent_security(PDO $pdo,array $tables): array
{
    if(empty($tables['security_logs']))return [];
    $rows=mg_admin_dashboard_query($pdo,"SELECT request_id,user_id,severity,event_type,message,created_at FROM security_logs WHERE severity IN ('warning','error','critical') ORDER BY created_at DESC,id DESC LIMIT ".MG_ADMIN_DASHBOARD_RECENT_LIMIT)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row):array=>['request_id'=>$row['request_id']!==null?(string)$row['request_id']:null,'user_id'=>$row['user_id']!==null?(int)$row['user_id']:null,'severity'=>(string)$row['severity'],'event_type'=>(string)$row['event_type'],'message'=>mb_substr((string)$row['message'],0,255),'created_at'=>(string)$row['created_at']],$rows);
}

function mg_admin_dashboard_recent_audit(PDO $pdo,array $tables): array
{
    if(empty($tables['audit_logs'])||empty($tables['users']))return [];
    $rows=mg_admin_dashboard_query($pdo,"SELECT al.user_id,u.display_name,al.action,al.entity_type,al.created_at FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC,al.id DESC LIMIT ".MG_ADMIN_DASHBOARD_RECENT_LIMIT)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function(array $row):array{
        $actor=trim((string)($row['display_name']??''));
        if($actor==='')$actor=$row['user_id']!==null?'User #'.(int)$row['user_id']:'System';
        return ['user_id'=>$row['user_id']!==null?(int)$row['user_id']:null,'actor'=>$actor,'action'=>(string)$row['action'],'entity_type'=>(string)$row['entity_type'],'created_at'=>(string)$row['created_at']];
    },$rows);
}

function mg_admin_dashboard_recent_checks(PDO $pdo,array $tables): array
{
    if(empty($tables['operational_check_results']))return [];
    $rows=mg_admin_dashboard_query($pdo,"SELECT public_id,check_key,check_scope,status,summary,checked_at,expires_at FROM operational_check_results ORDER BY checked_at DESC,id DESC LIMIT ".MG_ADMIN_DASHBOARD_RECENT_LIMIT)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row):array=>['id'=>(string)$row['public_id'],'key'=>(string)$row['check_key'],'scope'=>(string)$row['check_scope'],'status'=>(string)$row['status'],'summary'=>mb_substr((string)$row['summary'],0,500),'checked_at'=>(string)$row['checked_at'],'expires_at'=>$row['expires_at']!==null?(string)$row['expires_at']:null],$rows);
}

function mg_admin_dashboard_recent_incidents(PDO $pdo,array $tables): array
{
    if(empty($tables['operational_incidents']))return [];
    $rows=mg_admin_dashboard_query($pdo,"SELECT public_id,incident_key,title,severity,status,service_key,opened_at,updated_at FROM operational_incidents WHERE status IN ('open','investigating','mitigated') ORDER BY FIELD(severity,'sev1','sev2','sev3','sev4'),opened_at DESC LIMIT ".MG_ADMIN_DASHBOARD_RECENT_LIMIT)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row):array=>['id'=>(string)$row['public_id'],'key'=>(string)$row['incident_key'],'title'=>(string)$row['title'],'severity'=>(string)$row['severity'],'status'=>(string)$row['status'],'service'=>(string)$row['service_key'],'opened_at'=>(string)$row['opened_at'],'updated_at'=>(string)$row['updated_at']],$rows);
}

function mg_admin_dashboard_latest_release(PDO $pdo,array $tables): ?array
{
    if(empty($tables['deployment_releases']))return null;
    $row=mg_admin_dashboard_query($pdo,"SELECT public_id,release_version,git_commit_sha,environment,status,approved_at,deployed_at,updated_at FROM deployment_releases ORDER BY created_at DESC,id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;
    return ['id'=>(string)$row['public_id'],'version'=>(string)$row['release_version'],'commit'=>substr((string)$row['git_commit_sha'],0,12),'environment'=>(string)$row['environment'],'status'=>(string)$row['status'],'approved_at'=>$row['approved_at']!==null?(string)$row['approved_at']:null,'deployed_at'=>$row['deployed_at']!==null?(string)$row['deployed_at']:null,'updated_at'=>(string)$row['updated_at']];
}
