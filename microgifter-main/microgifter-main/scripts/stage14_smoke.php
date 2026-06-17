<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach(['social_follows','social_mutes','social_blocks','feed_post_reactions','feed_post_comments','feed_post_saves','feed_post_shares','social_reports'] as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 14 table: {$table}");
}
foreach(['social.posts.create','social.engage','social.moderate'] as $permission){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');$stmt->execute([$permission]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 14 permission: {$permission}");
}
fwrite(STDOUT,"Stage 14 posts, feed, and social smoke validation passed.\n");
