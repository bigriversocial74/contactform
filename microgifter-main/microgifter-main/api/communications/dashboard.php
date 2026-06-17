<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
mg_require_method('GET');
$user=mg_require_permission('notification.view');
$pdo=mg_db();
$limit=max(1,min(100,(int)($_GET['limit']??50)));

$notifications=$pdo->prepare("SELECT n.public_id,n.type,n.title,n.body,n.action_url,n.read_at,n.created_at,CASE WHEN n.type IN ('claim_locked','claim_expired','delivery_failed','distribution_failed','security','system_alert') THEN 'operational' WHEN n.type='message' THEN 'message' ELSE 'activity' END category FROM notifications n WHERE n.user_id=? ORDER BY n.created_at DESC,n.id DESC LIMIT {$limit}");
$notifications->execute([(int)$user['id']]);

$threads=$pdo->prepare("SELECT mt.public_id,mt.subject,mt.updated_at,g.public_id gift_id,pi.public_id pppm_id,latest.body latest_message,latest.created_at latest_at,COALESCE(mts.archived_at IS NOT NULL,0) archived,COALESCE(mts.pinned_at IS NOT NULL,0) pinned,CASE WHEN latest.created_at IS NOT NULL AND (mtp.last_read_at IS NULL OR latest.created_at>mtp.last_read_at) AND latest.sender_user_id<>? THEN 1 ELSE 0 END unread FROM message_thread_participants mtp INNER JOIN message_threads mt ON mt.id=mtp.thread_id LEFT JOIN message_thread_settings mts ON mts.thread_id=mt.id AND mts.user_id=mtp.user_id LEFT JOIN gifts g ON g.id=mt.gift_id LEFT JOIN pppm_items pi ON pi.id=mt.pppm_item_id LEFT JOIN messages latest ON latest.id=(SELECT m2.id FROM messages m2 WHERE m2.thread_id=mt.id ORDER BY m2.created_at DESC,m2.id DESC LIMIT 1) WHERE mtp.user_id=? AND mts.archived_at IS NULL ORDER BY pinned DESC,COALESCE(latest.created_at,mt.updated_at) DESC LIMIT {$limit}");
$threads->execute([(int)$user['id'],(int)$user['id']]);

$alerts=$pdo->prepare("SELECT public_id,alert_type,severity,status,title,body,action_url,created_at,acknowledged_at,resolved_at FROM operational_alerts WHERE user_id=? AND status IN ('open','acknowledged') ORDER BY FIELD(severity,'critical','high','warning','info'),created_at DESC LIMIT {$limit}");
$alerts->execute([(int)$user['id']]);

$counts=$pdo->prepare("SELECT (SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL) notification_unread,(SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND status='open') open_alerts,(SELECT COUNT(*) FROM message_thread_participants mtp INNER JOIN message_threads mt ON mt.id=mtp.thread_id LEFT JOIN messages latest ON latest.id=(SELECT m2.id FROM messages m2 WHERE m2.thread_id=mt.id ORDER BY m2.created_at DESC,m2.id DESC LIMIT 1) WHERE mtp.user_id=? AND latest.created_at IS NOT NULL AND (mtp.last_read_at IS NULL OR latest.created_at>mtp.last_read_at) AND latest.sender_user_id<>?) message_unread");
$counts->execute([(int)$user['id'],(int)$user['id'],(int)$user['id'],(int)$user['id']]);
mg_ok(['notifications'=>$notifications->fetchAll(),'threads'=>$threads->fetchAll(),'alerts'=>$alerts->fetchAll(),'counts'=>$counts->fetch()?:[]]);
