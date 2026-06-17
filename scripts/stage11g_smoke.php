<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';

$pdo=mg_db();
$columns=[
    'message_threads'=>['microgift_instance_id'],
    'messages'=>['recipient_user_id','idempotency_key','source_type','source_reference'],
];
$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
foreach($columns as $table=>$names){
    foreach($names as $column){
        $stmt->execute([$table,$column]);
        if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 11G column: {$table}.{$column}");
    }
}
$indexes=['uq_message_threads_microgift_instance'=>['message_threads'],'uq_messages_sender_idempotency'=>['messages']];
$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
foreach($indexes as $index=>$tables){
    $stmt->execute([$tables[0],$index]);
    if((int)$stmt->fetchColumn()<1)throw new RuntimeException('Missing Stage 11G index: '.$index);
}
$stmt=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
$stmt->execute(['stage_11g_action_center_durable_messaging']);
if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Stage 11G migration record is missing.');
fwrite(STDOUT,"Stage 11G Action Center durable messaging smoke validation passed.\n");
