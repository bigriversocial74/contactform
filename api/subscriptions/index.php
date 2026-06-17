<?php
declare(strict_types=1);
require_once __DIR__ . '/_subscriptions.php';
mg_require_method('GET');
$user=mg_require_permission('subscriptions.manage_own');
$role=trim((string)($_GET['role']??'subscriber'));
if(!in_array($role,['subscriber','recipient'],true))mg_fail('Invalid subscription role.',422);
$field=$role==='recipient'?'recipient_user_id':'subscriber_user_id';
$stmt=mg_db()->prepare("SELECT s.public_id,s.status,s.target_type,s.target_reference,s.amount_cents,s.currency,s.funding_type,s.current_period_start,s.current_period_end,s.next_billing_at,s.trial_ends_at,s.initial_payment_required,s.funded_at,s.activated_at,s.cancel_at_period_end,s.retry_count,s.last_failure_message,s.recovery_status,s.recovery_reference,s.recovery_started_at,s.recovery_resolved_at,s.access_suspended_at,p.public_id plan_id,p.name plan_name,p.interval_unit,p.interval_count FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.{$field}=? ORDER BY s.id DESC LIMIT 100");
$stmt->execute([(int)$user['id']]);
mg_ok(['role'=>$role,'subscriptions'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
