<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/bootstrap.php';
require_once dirname(__DIR__).'/includes/creator-campaigns.php';
$pdo=mg_db();

function ccm9m(bool $ok,string $message,array $context=[]):void
{
    if(!$ok) throw new RuntimeException($message.' '.json_encode($context,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

$section=strtolower(trim((string)($argv[1]??'all')));
$results=[];

if($section==='all'||$section==='tables'){
    $tables=mg_creator_campaign_message_required_tables();
    $p=implode(',',array_fill(0,count($tables),'?'));
    $stmt=$pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$p}) ORDER BY table_name");
    $stmt->execute($tables);
    $found=$stmt->fetchAll(PDO::FETCH_COLUMN);
    ccm9m(count($found)===count($tables),'Phase 9 canonical or bridge tables are incomplete.',['expected'=>$tables,'found'=>$found]);
    $results['tables']=$found;
}

if($section==='all'||$section==='permissions'){
    $permissions=['merchant.creator_messages.view','merchant.creator_messages.manage','merchant.creator_notes.manage','creator.campaign_messages.view_own','creator.campaign_messages.send_own'];
    $p=implode(',',array_fill(0,count($permissions),'?'));
    $stmt=$pdo->prepare("SELECT slug FROM permissions WHERE slug IN ({$p}) ORDER BY slug");
    $stmt->execute($permissions);
    $found=$stmt->fetchAll(PDO::FETCH_COLUMN);
    ccm9m(count($found)===count($permissions),'Phase 9 permissions are incomplete.',['expected'=>$permissions,'found'=>$found]);
    $results['permissions']=$found;
}

if($section==='all'||$section==='indexes'){
    $stmt=$pdo->query("SELECT CONCAT(table_name,':',index_name) index_key FROM information_schema.statistics WHERE table_schema=DATABASE() AND ((table_name='creator_campaign_message_contexts' AND index_name IN('uq_cc_message_context_thread','uq_cc_message_context_participant')) OR (table_name='creator_campaign_message_links' AND index_name IN('uq_cc_message_link_message','uq_cc_message_link_idempotency')) OR (table_name='creator_campaign_internal_notes' AND index_name='uq_cc_internal_note_idempotency')) AND non_unique=0 GROUP BY table_name,index_name ORDER BY table_name,index_name");
    $found=$stmt->fetchAll(PDO::FETCH_COLUMN);
    ccm9m(count($found)===5,'Phase 9 uniqueness and idempotency controls are incomplete.',['found'=>$found]);
    $results['indexes']=$found;
}

if($section==='all'||$section==='columns'){
    $stmt=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='messages' AND column_name IN('source_type','source_reference','moderation_status') ORDER BY column_name");
    $found=$stmt->fetchAll(PDO::FETCH_COLUMN);
    ccm9m(count($found)===3,'Canonical message source or moderation columns are incomplete.',['found'=>$found]);
    $results['message_columns']=$found;
}

if($section==='all'||$section==='boundaries'){
    $stmt=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN('creator_campaign_messages','creator_campaign_notifications','creator_campaign_notification_jobs','creator_campaign_attachment_blobs') ORDER BY table_name");
    $found=$stmt->fetchAll(PDO::FETCH_COLUMN);
    ccm9m($found===[],'Phase 9 created a duplicate messaging, notification, or attachment store.',['found'=>$found]);
    $results['duplicate_stores']=$found;
}

if($section==='all'||$section==='creator_access'){
    $stmt=$pdo->query("SELECT p.slug FROM role_permissions rp INNER JOIN roles r ON r.id=rp.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='creator' AND p.slug IN('gift.message.send','notification.view') ORDER BY p.slug");
    $found=$stmt->fetchAll(PDO::FETCH_COLUMN);
    ccm9m(count($found)===2,'Creator role cannot use the canonical Messages or Notifications center.',['found'=>$found]);
    $results['creator_center_permissions']=$found;
}

echo json_encode(['ok'=>true,'section'=>$section,'results'=>$results,'canonical_messages_reused'=>true,'canonical_notifications_reused'=>true,'duplicate_stores'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
