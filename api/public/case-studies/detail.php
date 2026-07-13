<?php
declare(strict_types=1);
require_once dirname(__DIR__,2) . '/bootstrap.php';
require_once dirname(__DIR__,3) . '/includes/reviews-case-studies.php';
mg_require_method('GET');$pdo=mg_db();$slug=strtolower(trim((string)($_GET['slug']??'')));if($slug===''||preg_match('/^[a-z0-9][a-z0-9-]{0,119}$/',$slug)!==1)mg_fail('Invalid case study.',422);
try{
 $stmt=$pdo->prepare("SELECT f.*,p.public_id profile_public_id,p.slug,p.display_name,p.headline,p.bio,p.location_label,r.public_id selected_review_public_id,r.reviewer_name,r.rating,r.review_title,r.review_body,rr.reply_body,rr.created_at reply_created_at
 FROM featured_case_studies f INNER JOIN public_profiles p ON p.id=f.profile_id
 LEFT JOIN customer_reviews r ON r.id=f.selected_review_id AND r.status='published'
 LEFT JOIN customer_review_replies rr ON rr.review_id=r.id AND rr.status='published'
 WHERE p.slug=? AND f.status='published' LIMIT 1");$stmt->execute([$slug]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)mg_fail('Case study not found.',404);
 $featuredReviewsStmt=$pdo->prepare("SELECT r.public_id,r.reviewer_name,r.rating,r.review_title,r.review_body,r.submitted_at,rr.public_id reply_id,rr.reply_body,rr.created_at reply_created_at
 FROM customer_reviews r LEFT JOIN customer_review_replies rr ON rr.review_id=r.id AND rr.status='published'
 WHERE r.merchant_user_id=? AND r.status='published' AND (r.featured_in_case_study=1 OR r.id=?) ORDER BY (r.id=?) DESC,r.submitted_at DESC LIMIT 6");$featuredReviewsStmt->execute([(int)$row['merchant_user_id'],(int)($row['selected_review_id']??0),(int)($row['selected_review_id']??0)]);$reviews=array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'reviewer_name'=>(string)$r['reviewer_name'],'rating'=>(int)$r['rating'],'title'=>$r['review_title'],'body'=>(string)$r['review_body'],'submitted_at'=>(string)$r['submitted_at'],'reply'=>!empty($r['reply_id'])?['id'=>(string)$r['reply_id'],'body'=>(string)$r['reply_body'],'created_at'=>(string)$r['reply_created_at']]:null],$featuredReviewsStmt->fetchAll(PDO::FETCH_ASSOC));
 mg_ok(['case_study'=>['id'=>(string)$row['public_id'],'profile_id'=>(string)$row['profile_public_id'],'slug'=>(string)$row['slug'],'merchant_user_id'=>(int)$row['merchant_user_id'],'merchant_name'=>(string)$row['display_name'],'profile_headline'=>(string)($row['headline']??''),'bio'=>(string)($row['bio']??''),'location'=>(string)($row['location_label']??''),'title'=>$row['title'],'subtitle'=>$row['subtitle'],'challenge'=>$row['challenge_text'],'solution'=>$row['solution_text'],'outcomes'=>mg_rcs_decode($row['outcomes_json'],[]),'testimonial'=>['text'=>$row['testimonial_text'],'name'=>$row['testimonial_name'],'role'=>$row['testimonial_role']],'hero_featured'=>!empty($row['hero_featured']),'published_at'=>$row['published_at'],'reviews'=>$reviews]]);
}catch(Throwable $e){mg_security_log('error','public.case_studies.detail_failed','Unable to load case study.',['slug'=>$slug,'message'=>$e->getMessage()],null);mg_fail('Unable to load case study.',500);}
