<?php
declare(strict_types=1);
require_once dirname(__DIR__,2) . '/bootstrap.php';
require_once dirname(__DIR__,3) . '/includes/reviews-case-studies.php';
mg_require_method('GET');$pdo=mg_db();
try{
 if(!mg_rcs_table_exists($pdo,'featured_case_studies'))mg_ok(['items'=>[],'schema_ready'=>false]);
 $q=trim((string)($_GET['q']??''));$category=trim((string)($_GET['category']??''));$sort=strtolower(trim((string)($_GET['sort']??'featured')));$limit=max(1,min(50,(int)($_GET['limit']??24)));$where=["f.status='published'"];$args=[];
 if($q!==''){$where[]='(COALESCE(f.title,p.display_name) LIKE ? OR COALESCE(f.subtitle,p.headline) LIKE ? OR p.display_name LIKE ?)';$like='%'.$q.'%';array_push($args,$like,$like,$like);}if($category!==''){$where[]='(p.profile_type=? OR p.category=?)';array_push($args,$category,$category);}
 $order=match($sort){'newest'=>'COALESCE(f.published_at,f.updated_at) DESC','active'=>'campaign_total DESC,product_total DESC',default=>'f.hero_featured DESC,f.display_order ASC,COALESCE(f.published_at,f.updated_at) DESC'};
 $sql="SELECT f.public_id case_study_id,f.status,f.display_order,f.hero_featured,f.title,f.subtitle,f.challenge_text,f.solution_text,f.outcomes_json,f.testimonial_text,f.testimonial_name,f.testimonial_role,f.published_at,p.public_id profile_id,p.slug,p.display_name,p.headline,p.profile_type,p.category,p.location_label,p.avatar_asset_id,p.cover_asset_id,
 (SELECT COUNT(*) FROM catalog_products cp WHERE cp.merchant_user_id=f.merchant_user_id AND cp.status='published') product_total,
 (SELECT COUNT(*) FROM campaigns c WHERE c.merchant_user_id=f.merchant_user_id AND c.status='active') campaign_total,
 (SELECT COUNT(*) FROM customer_reviews r WHERE r.merchant_user_id=f.merchant_user_id AND r.status='published') review_total,
 (SELECT COALESCE(AVG(r.rating),0) FROM customer_reviews r WHERE r.merchant_user_id=f.merchant_user_id AND r.status='published') review_average
 FROM featured_case_studies f INNER JOIN public_profiles p ON p.id=f.profile_id WHERE ".implode(' AND ',$where)." ORDER BY $order LIMIT $limit";
 $stmt=$pdo->prepare($sql);$stmt->execute($args);$items=array_map(static function(array $r):array{return ['id'=>(string)$r['case_study_id'],'profile_id'=>(string)$r['profile_id'],'slug'=>(string)$r['slug'],'business_name'=>(string)$r['display_name'],'display_name'=>(string)$r['display_name'],'headline'=>(string)($r['title']?:$r['headline']),'subtitle'=>(string)($r['subtitle']??''),'profile_type'=>(string)($r['profile_type']??'merchant'),'category'=>(string)($r['category']??''),'location'=>(string)($r['location_label']??''),'published_products'=>(int)$r['product_total'],'published_campaigns'=>(int)$r['campaign_total'],'review_total'=>(int)$r['review_total'],'review_average'=>round((float)$r['review_average'],1),'hero_featured'=>!empty($r['hero_featured']),'display_order'=>(int)$r['display_order'],'published_at'=>$r['published_at'],'challenge'=>$r['challenge_text'],'solution'=>$r['solution_text'],'outcomes'=>mg_rcs_decode($r['outcomes_json'],[]),'testimonial'=>['text'=>$r['testimonial_text'],'name'=>$r['testimonial_name'],'role'=>$r['testimonial_role']]];},$stmt->fetchAll(PDO::FETCH_ASSOC));
 mg_ok(['items'=>$items,'schema_ready'=>true]);
}catch(Throwable $e){mg_security_log('error','public.case_studies.list_failed','Unable to load case studies.',['message'=>$e->getMessage()],null);mg_fail('Unable to load case studies.',500);}
