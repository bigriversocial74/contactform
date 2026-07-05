<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/blog/blog-functions.php';
require_once __DIR__ . '/includes/blog/blog-settings.php';
header('Content-Type: application/rss+xml; charset=UTF-8');
$pdo=mg_db();$schema=mg_blog_schema_ready($pdo);$settings=mg_blog_get_settings($pdo);$posts=$schema['ready']&&$settings['rss_enabled']==='1'?mg_blog_list_public_posts($pdo,['limit'=>30]):[];function mg_blog_rss_e(string $v): string{return htmlspecialchars($v,ENT_XML1|ENT_COMPAT,'UTF-8');}function mg_blog_rss_date(?string $v): string{try{return(new DateTimeImmutable((string)$v))->format(DATE_RSS);}catch(Throwable){return gmdate(DATE_RSS);}}echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<rss version="2.0"><channel><title><?= mg_blog_rss_e($settings['blog_title']) ?></title><link>https://microgifter.com/blog.php</link><description><?= mg_blog_rss_e($settings['blog_description']) ?></description><language>en-us</language><lastBuildDate><?= mg_blog_rss_e(gmdate(DATE_RSS)) ?></lastBuildDate><?php foreach($posts as $post): $url='https://microgifter.com'.mg_blog_public_post_url($post); ?><item><title><?= mg_blog_rss_e((string)$post['title']) ?></title><link><?= mg_blog_rss_e($url) ?></link><guid isPermaLink="true"><?= mg_blog_rss_e($url) ?></guid><description><?= mg_blog_rss_e((string)($post['excerpt']?:mg_blog_excerpt((string)$post['body']))) ?></description><pubDate><?= mg_blog_rss_e(mg_blog_rss_date($post['published_at']??$post['created_at']??null)) ?></pubDate><?php if(!empty($post['category_name'])): ?><category><?= mg_blog_rss_e((string)$post['category_name']) ?></category><?php endif; ?></item><?php endforeach; ?></channel></rss>
