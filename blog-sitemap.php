<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/blog/blog-functions.php';
require_once __DIR__ . '/includes/blog/blog-settings.php';
header('Content-Type: application/xml; charset=UTF-8');
$pdo=mg_db();$schema=mg_blog_schema_ready($pdo);$settings=mg_blog_get_settings($pdo);$enabled=$settings['sitemap_enabled']==='1';$posts=$schema['ready']&&$enabled?mg_blog_list_public_posts($pdo,['limit'=>200]):[];$categories=$schema['ready']&&$enabled?mg_blog_categories($pdo,true):[];function mg_blog_xml_e(string $v): string{return htmlspecialchars($v,ENT_XML1|ENT_COMPAT,'UTF-8');}function mg_blog_xml_date(?string $v): string{try{return(new DateTimeImmutable((string)$v))->format('Y-m-d');}catch(Throwable){return gmdate('Y-m-d');}}echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://microgifter.com/blog.php</loc><changefreq>weekly</changefreq><priority>0.8</priority></url><?php if($settings['rss_enabled']==='1'): ?><url><loc>https://microgifter.com/blog-rss.php</loc><changefreq>weekly</changefreq><priority>0.3</priority></url><?php endif; ?><?php foreach($categories as $category): ?><url><loc><?= mg_blog_xml_e('https://microgifter.com'.mg_blog_public_category_url($category)) ?></loc><changefreq>weekly</changefreq><priority>0.6</priority></url><?php endforeach; ?><?php foreach($posts as $post): ?><url><loc><?= mg_blog_xml_e('https://microgifter.com'.mg_blog_public_post_url($post)) ?></loc><lastmod><?= mg_blog_xml_e(mg_blog_xml_date($post['updated_at']??$post['published_at']??null)) ?></lastmod><changefreq>monthly</changefreq><priority><?= !empty($post['is_featured'])?'0.8':'0.7' ?></priority></url><?php endforeach; ?></urlset>
