<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';

$pdo=mg_db();
$tables=['tips','tip_velocity_counters','tip_reversals'];
foreach($tables as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 12 table: {$table}");
}
$permissions=['tips.create','tips.view_own','tips.reverse'];
$stmt=$pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
foreach($permissions as $permission){
    $stmt->execute([$permission]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 12 permission: {$permission}");
}
fwrite(STDOUT,"Stage 12 Universal Tips smoke validation passed.\n");
