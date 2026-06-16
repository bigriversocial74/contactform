<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach(['stage_14_posts_feed_social.sql','stage_14b_social_content.sql'] as $file){
    $sql=file_get_contents(dirname(__DIR__).'/database/'.$file);
    if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Missing Stage 14 migration: '.$file);
    $pdo->exec($sql);
}
fwrite(STDOUT,"Stage 14 posts, feed, and social migrations applied.\n");
