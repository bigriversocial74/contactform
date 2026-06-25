<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

$user=mg_require_permission('merchant.campaigns.view');$pdo=mg_db();$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
if($method==='GET'){$thread=trim((string)($_GET['thread']??''));if($thread!==''){$stmt=$pdo->prepare('SELECT * FROM message_threads WHERE public_id=? OR id=?');$stmt->execute([$thread,(int)$thread]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)mg_fail('Thread not found.',404);$messages=$pdo->prepare('SELECT * FROM messages WHERE thread_id=? ORDER BY created_at ASC LIMIT 200');$messages->execute([(int)$row['id']]);mg_ok(['thread'=>$row,'messages'=>$messages->fetchAll(PDO::FETCH_ASSOC)]);} $stmt=$pdo->prepare("SELECT t.* FROM message_threads t WHERE t.conversation_key LIKE ? ORDER BY t.updated_at DESC LIMIT 100");$stmt->execute(['crm:%:merchant:'.(int)$user['id']]);mg_ok(['threads'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);}
if($method!=='POST')mg_fail('Method not allowed.',405);$input=mg_input();mg_require_csrf_for_write($input);$threadId=(int)($input['thread_id']??0);$body=trim((string)($input['body']??''));if($threadId<1||$body==='')mg_fail('Thread and body are required.',422);$messageId=mg_message_insert($pdo,$threadId,(int)$user['id'],$body,['source_type'=>'merchant_crm']);mg_ok(['message_id'=>$messageId],'CRM reply sent.');
