<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/reviews-case-studies.php';

$pdo = mg_db();
$user = mg_rcs_admin_user();
$actorId = (int)$user['id'];
$canManage = mg_rcs_admin_can_manage($user);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
    $rating = (int)($_GET['rating'] ?? 0);
    $query = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
    $where = ['1=1']; $args = [];
    if (in_array($status, ['pending','published','hidden','removed'], true)) { $where[] = 'r.status=?'; $args[] = $status; }
    if ($rating >= 1 && $rating <= 5) { $where[] = 'r.rating=?'; $args[] = $rating; }
    if ($query !== '') { $where[] = '(r.reviewer_name LIKE ? OR r.review_title LIKE ? OR r.review_body LIKE ? OR p.display_name LIKE ?)'; $like = '%'.$query.'%'; array_push($args,$like,$like,$like,$like); }
    $sql = "SELECT r.*,p.display_name merchant_name,p.slug profile_slug,rr.public_id reply_public_id,rr.reply_body,rr.status reply_status,rr.created_at reply_created_at,rr.updated_at reply_updated_at
            FROM customer_reviews r INNER JOIN public_profiles p ON p.id=r.profile_id
            LEFT JOIN customer_review_replies rr ON rr.review_id=r.id AND rr.status<>'removed'
            WHERE ".implode(' AND ',$where)." ORDER BY r.submitted_at DESC,r.id DESC LIMIT ".$limit;
    $stmt = $pdo->prepare($sql); $stmt->execute($args);
    $reviews = array_map('mg_rcs_review_payload', $stmt->fetchAll(PDO::FETCH_ASSOC));

    $caseStmt = $pdo->query("SELECT f.*,p.display_name merchant_name,p.slug profile_slug,r.public_id selected_review_public_id
                            FROM featured_case_studies f INNER JOIN public_profiles p ON p.id=f.profile_id
                            LEFT JOIN customer_reviews r ON r.id=f.selected_review_id
                            ORDER BY f.hero_featured DESC,f.display_order ASC,f.updated_at DESC");
    $cases = array_map(static function(array $row): array {
        return [
            'id'=>(string)$row['public_id'],'profile_id'=>(int)$row['profile_id'],'merchant_user_id'=>(int)$row['merchant_user_id'],
            'merchant_name'=>(string)$row['merchant_name'],'profile_slug'=>(string)$row['profile_slug'],'status'=>(string)$row['status'],
            'display_order'=>(int)$row['display_order'],'hero_featured'=>!empty($row['hero_featured']),'title'=>$row['title'],'subtitle'=>$row['subtitle'],
            'challenge'=>$row['challenge_text'],'solution'=>$row['solution_text'],'outcomes'=>mg_rcs_decode($row['outcomes_json'],[]),
            'testimonial_text'=>$row['testimonial_text'],'testimonial_name'=>$row['testimonial_name'],'testimonial_role'=>$row['testimonial_role'],
            'selected_review_id'=>$row['selected_review_public_id'],'published_at'=>$row['published_at'],'updated_at'=>$row['updated_at']
        ];
    }, $caseStmt->fetchAll(PDO::FETCH_ASSOC));

    $auditStmt = $pdo->query("SELECT a.public_id,a.action,a.created_at,a.metadata_json,COALESCE(NULLIF(u.display_name,''),u.full_name,u.email) actor_name,p.display_name merchant_name
                             FROM review_case_study_audit a LEFT JOIN users u ON u.id=a.actor_user_id
                             LEFT JOIN public_profiles p ON p.user_id=a.merchant_user_id
                             ORDER BY a.created_at DESC,a.id DESC LIMIT 100");
    $audit = array_map(static fn(array $r): array => ['id'=>(string)$r['public_id'],'action'=>(string)$r['action'],'created_at'=>(string)$r['created_at'],'actor_name'=>(string)($r['actor_name']??''),'merchant_name'=>(string)($r['merchant_name']??''),'metadata'=>mg_rcs_decode($r['metadata_json'],[])], $auditStmt->fetchAll(PDO::FETCH_ASSOC));

    mg_ok(['reviews'=>$reviews,'case_studies'=>$cases,'audit'=>$audit,'can_manage'=>$canManage]);
}

mg_require_method('POST');
if (!$canManage) mg_fail('Administrative management permission required.',403);
$input = mg_input(); mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? '')));

try {
    $pdo->beginTransaction();
    if ($action === 'moderate_review') {
        $reviewRef = strtolower(trim((string)($input['review_id'] ?? '')));
        $status = strtolower(trim((string)($input['status'] ?? 'published')));
        if (!in_array($status,['pending','published','hidden','removed'],true)) mg_fail('Invalid review status.',422);
        $stmt=$pdo->prepare('SELECT * FROM customer_reviews WHERE public_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$reviewRef]);$before=$stmt->fetch(PDO::FETCH_ASSOC);if(!$before)mg_fail('Review not found.',404);
        $featuredProfile=!empty($input['featured_on_profile'])?1:0;$featuredCase=!empty($input['featured_in_case_study'])?1:0;$notes=trim((string)($input['moderation_notes']??''))?:null;
        $pdo->prepare('UPDATE customer_reviews SET status=?,featured_on_profile=?,featured_in_case_study=?,moderation_notes=?,moderated_by_user_id=?,moderated_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$status,$featuredProfile,$featuredCase,$notes,$actorId,(int)$before['id']]);
        $after=$before;$after['status']=$status;$after['featured_on_profile']=$featuredProfile;$after['featured_in_case_study']=$featuredCase;$after['moderation_notes']=$notes;
        mg_rcs_audit($pdo,['actor_user_id'=>$actorId,'merchant_user_id'=>(int)$before['merchant_user_id'],'review_id'=>(int)$before['id'],'action'=>'admin.review.moderated','before'=>$before,'after'=>$after]);
        $pdo->commit();mg_ok(['review_id'=>$reviewRef],'Review updated.');
    }

    if ($action === 'save_case_study') {
        $caseRef=strtolower(trim((string)($input['case_study_id']??'')));$profileRef=strtolower(trim((string)($input['profile_id']??'')));
        $profileStmt=$pdo->prepare('SELECT id,user_id,public_id,display_name,slug FROM public_profiles WHERE public_id=? OR slug=? LIMIT 1 FOR UPDATE');$profileStmt->execute([$profileRef,$profileRef]);$profile=$profileStmt->fetch(PDO::FETCH_ASSOC);if(!$profile)mg_fail('Merchant profile not found.',404);
        $status=strtolower(trim((string)($input['status']??'draft')));if(!in_array($status,['draft','published','hidden','archived'],true))mg_fail('Invalid case study status.',422);
        $reviewDbId=null;$selectedReview=trim((string)($input['selected_review_id']??''));if($selectedReview!==''){$r=$pdo->prepare('SELECT id FROM customer_reviews WHERE public_id=? AND merchant_user_id=? LIMIT 1');$r->execute([$selectedReview,(int)$profile['user_id']]);$reviewDbId=$r->fetchColumn();if(!$reviewDbId)mg_fail('Selected review not found for this merchant.',422);}
        $payload=['status'=>$status,'display_order'=>(int)($input['display_order']??100),'hero_featured'=>!empty($input['hero_featured'])?1:0,'title'=>trim((string)($input['title']??''))?:null,'subtitle'=>trim((string)($input['subtitle']??''))?:null,'challenge_text'=>trim((string)($input['challenge']??''))?:null,'solution_text'=>trim((string)($input['solution']??''))?:null,'outcomes_json'=>mg_rcs_json(array_values(array_filter(array_map('trim',(array)($input['outcomes']??[]))))),'testimonial_text'=>trim((string)($input['testimonial_text']??''))?:null,'testimonial_name'=>trim((string)($input['testimonial_name']??''))?:null,'testimonial_role'=>trim((string)($input['testimonial_role']??''))?:null,'internal_notes'=>trim((string)($input['internal_notes']??''))?:null];
        if($payload['hero_featured'])$pdo->exec("UPDATE featured_case_studies SET hero_featured=0 WHERE hero_featured=1");
        $existing=$pdo->prepare('SELECT * FROM featured_case_studies WHERE profile_id=? LIMIT 1 FOR UPDATE');$existing->execute([(int)$profile['id']]);$before=$existing->fetch(PDO::FETCH_ASSOC);
        if($before){$caseId=(int)$before['id'];$caseRef=(string)$before['public_id'];$pdo->prepare('UPDATE featured_case_studies SET selected_review_id=?,status=?,display_order=?,hero_featured=?,title=?,subtitle=?,challenge_text=?,solution_text=?,outcomes_json=?,testimonial_text=?,testimonial_name=?,testimonial_role=?,internal_notes=?,published_at=IF(?="published",COALESCE(published_at,NOW()),published_at),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$reviewDbId,$payload['status'],$payload['display_order'],$payload['hero_featured'],$payload['title'],$payload['subtitle'],$payload['challenge_text'],$payload['solution_text'],$payload['outcomes_json'],$payload['testimonial_text'],$payload['testimonial_name'],$payload['testimonial_role'],$payload['internal_notes'],$payload['status'],$actorId,$caseId]);}
        else{$caseRef=mg_rcs_uuid();$pdo->prepare('INSERT INTO featured_case_studies (public_id,profile_id,merchant_user_id,selected_review_id,status,display_order,hero_featured,title,subtitle,challenge_text,solution_text,outcomes_json,testimonial_text,testimonial_name,testimonial_role,internal_notes,published_at,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?="published",NOW(),NULL),?,?,NOW(),NOW())')->execute([$caseRef,(int)$profile['id'],(int)$profile['user_id'],$reviewDbId,$payload['status'],$payload['display_order'],$payload['hero_featured'],$payload['title'],$payload['subtitle'],$payload['challenge_text'],$payload['solution_text'],$payload['outcomes_json'],$payload['testimonial_text'],$payload['testimonial_name'],$payload['testimonial_role'],$payload['internal_notes'],$payload['status'],$actorId,$actorId]);$caseId=(int)$pdo->lastInsertId();}
        mg_rcs_audit($pdo,['actor_user_id'=>$actorId,'merchant_user_id'=>(int)$profile['user_id'],'case_study_id'=>$caseId,'action'=>'admin.case_study.saved','before'=>$before,'after'=>$payload,'metadata'=>['profile_slug'=>$profile['slug']]]);
        $pdo->commit();mg_ok(['case_study_id'=>$caseRef,'profile_slug'=>$profile['slug']],'Case study saved.');
    }
    mg_fail('Unsupported action.',422);
} catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();if($error instanceof RuntimeException)throw $error;mg_security_log('error','admin.reviews_case_studies.failed','Management action failed.',['action'=>$action,'message'=>$error->getMessage()],$actorId);mg_fail('Unable to complete management action.',500);}
