<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';
$files = [
    'loyalty-quest.php','my-quests.php','merchant-quest-reviews.php',
    'api/public/loyalty-quest/_participant.php','api/public/loyalty-quest/_verification.php','api/public/loyalty-quest/_reward.php',
    'api/public/loyalty-quest/detail.php','api/public/loyalty-quest/start.php','api/public/loyalty-quest/submit.php',
    'api/account/loyalty-quests.php','api/merchant/loyalty-quest-campaigns.php','api/merchant/loyalty-quest-reviews.php','api/merchant/loyalty-quest-signed-code.php',
    'includes/loyalty-quest-campaign-type.php','includes/merchant-quest-reviews-view.php','includes/merchant-navigation.php',
    'assets/js/loyalty-quest-participant.js','assets/js/my-loyalty-quests.js','assets/js/merchant-quest-reviews.js',
    'assets/css/loyalty-quest-participant.css','assets/css/my-loyalty-quests.css','assets/css/merchant-quest-reviews.css',
    'database/loyalty_quest_participant_experience_v1.sql','config/migrations.php',
    '.github/workflows/participant-quest-experience-validation.yml',
];
$checks = [];
foreach ($files as $file) $checks[] = ['name'=>'file:' . $file,'ok'=>is_file($root . '/' . $file)];

$participant = $read('api/public/loyalty-quest/_participant.php');
$verification = $read('api/public/loyalty-quest/_verification.php');
$reward = $read('api/public/loyalty-quest/_reward.php');
$detail = $read('api/public/loyalty-quest/detail.php');
$start = $read('api/public/loyalty-quest/start.php');
$submit = $read('api/public/loyalty-quest/submit.php');
$portfolioApi = $read('api/account/loyalty-quests.php');
$campaignApi = $read('api/merchant/loyalty-quest-campaigns.php');
$reviewApi = $read('api/merchant/loyalty-quest-reviews.php');
$signedApi = $read('api/merchant/loyalty-quest-signed-code.php');
$rules = $read('includes/loyalty-quest-campaign-type.php');
$page = $read('loyalty-quest.php');
$participantJs = $read('assets/js/loyalty-quest-participant.js');
$participantCss = $read('assets/css/loyalty-quest-participant.css');
$myPage = $read('my-quests.php');
$myJs = $read('assets/js/my-loyalty-quests.js');
$reviewPage = $read('merchant-quest-reviews.php');
$reviewView = $read('includes/merchant-quest-reviews-view.php');
$reviewJs = $read('assets/js/merchant-quest-reviews.js');
$reviewCss = $read('assets/css/merchant-quest-reviews.css');
$sql = $read('database/loyalty_quest_participant_experience_v1.sql');
$migrations = $read('config/migrations.php');
$accountNav = $read('includes/header-templates/logged-in.php');
$merchantNav = $read('includes/merchant-navigation.php');
$merchantManager = $read('assets/js/merchant-loyalty-quests.js');

$checks[] = ['name'=>'SQL participation evidence replay model','ok'=>str_contains($sql,'CREATE TABLE IF NOT EXISTS loyalty_quest_participations')&&str_contains($sql,'CREATE TABLE IF NOT EXISTS loyalty_quest_evidence')&&str_contains($sql,'CREATE TABLE IF NOT EXISTS loyalty_quest_code_uses')&&str_contains($sql,'uq_lq_code_nonce_replay')&&str_contains($sql,'uq_loyalty_quest_participant_campaign_user')];
$checks[] = ['name'=>'canonical migration order','ok'=>str_contains($migrations,"'shared_microgifter_identity_v1.sql'")&&str_contains($migrations,"'loyalty_quest_participant_experience_v1.sql'")&&strpos($migrations,"'shared_microgifter_identity_v1.sql'")<strpos($migrations,"'loyalty_quest_participant_experience_v1.sql'")];
$checks[] = ['name'=>'authenticated CSRF participant writes','ok'=>str_contains($start,'mg_require_api_user')&&str_contains($start,'mg_require_csrf_for_write')&&str_contains($submit,'mg_require_api_user')&&str_contains($submit,'mg_require_csrf_for_write')];
$checks[] = ['name'=>'audience and invite enforcement','ok'=>str_contains($participant,'mg_lqp_audience_require')&&str_contains($participant,"\$visibility === 'invite_only'")&&str_contains($participant,"\$visibility === 'customers'")&&str_contains($participant,"\$visibility === 'loyalty_members'")&&str_contains($participant,"\$visibility === 'new_customers'")&&str_contains($participant,"\$visibility === 'campaign_contacts'")&&str_contains($participant,"\$visibility === 'geographic_radius'")&&str_contains($start,'mg_lqp_audience_require')];
$checks[] = ['name'=>'one-way campaign secrets','ok'=>str_contains($rules,'invite_code_hash')&&str_contains($rules,'completion_code_hash')&&str_contains($rules,'staff_confirmation_code_hash')&&str_contains($rules,'event_checkin_code_hash')&&str_contains($rules,"hash('sha256'")&&!str_contains($detail,'invite_code_hash')];
$checks[] = ['name'=>'signed QR authority','ok'=>str_contains($verification,"hash_hmac('sha256'")&&str_contains($verification,'This signed QR code belongs to another Loyalty Quest.')&&str_contains($verification,'has expired')&&str_contains($verification,'mg_lqv_use_signed_code')&&str_contains($verification,'loyalty_quest_code_uses')];
$checks[] = ['name'=>'merchant signed-code issuance','ok'=>str_contains($signedApi,"mg_merchant_require_permission('merchant.campaigns.manage')")&&str_contains($signedApi,'random_bytes(18)')&&str_contains($signedApi,"hash_hmac('sha256'")&&str_contains($signedApi,'expires_minutes')&&str_contains($merchantManager,'loyalty-quest-signed-code.php')];
$checks[] = ['name'=>'verification method completeness','ok'=>str_contains($verification,"'signed_qr'")&&str_contains($verification,"'static_qr'")&&str_contains($verification,"'geolocation'")&&str_contains($verification,"'purchase_record'")&&str_contains($verification,"'microgifter_transaction'")&&str_contains($verification,"'staff_confirmation'")&&str_contains($verification,"'event_check_in'")&&str_contains($verification,"'referral_conversion'")];
$checks[] = ['name'=>'geolocation trust controls','ok'=>str_contains($verification,'maximum_accuracy_meters')&&str_contains($verification,'mg_lqp_distance_meters')&&str_contains($verification,'outside the allowed quest location')&&str_contains($participantJs,'enableHighAccuracy:true')];
$checks[] = ['name'=>'proof and duplicate-reference controls','ok'=>str_contains($verification,'mg_lqv_safe_proof_url')&&str_contains($verification,"\$scheme !== 'https'")&&str_contains($verification,'safe HTTPS URL')&&str_contains($verification,'mg_lqv_reference_unique')&&str_contains($verification,'has already been submitted')&&str_contains($submit,"'ip_hash'")];
$checks[] = ['name'=>'daily cooldown and budget controls','ok'=>str_contains($participant,'mg_lqp_enforce_daily_limit')&&str_contains($participant,'mg_lqp_enforce_cooldown')&&str_contains($participant,'mg_lqp_enforce_budget')&&str_contains($submit,'mg_lqp_enforce_cooldown')&&str_contains($reviewApi,'mg_lqp_enforce_daily_limit')];
$checks[] = ['name'=>'stamp-backed idempotent reward issuance','ok'=>str_contains($reward,'mg_public_campaign_debit_reward_stamp')&&str_contains($reward,'mg_lqp_issue_reward')&&str_contains($reward,"source_type='loyalty_quest'")&&str_contains($participant,'mg_public_campaign_enforce_reward_limits')&&str_contains($participant,'mg_zero_reward_issue_from_wallet')&&str_contains($submit,'mg_lqr_issue_reward')&&str_contains($reviewApi,'mg_lqr_issue_reward')];
$checks[] = ['name'=>'guarded merchant activation','ok'=>str_contains($campaignApi,'mg_lq_status_transition_allowed')&&str_contains($campaignApi,"'archived' => []")&&str_contains($campaignApi,"'max_active_campaigns'")&&str_contains($campaignApi,'Active campaign limit reached.')&&str_contains($campaignApi,'Active Loyalty Quests require a future end date.')];
$checks[] = ['name'=>'participant guided UX','ok'=>str_contains($page,'data-lqp-invite-field')&&str_contains($page,'data-lqp-start-location-field')&&str_contains($page,'data-lqp-camera')&&str_contains($page,'data-lqp-evidence-list')&&str_contains($participantJs,'BarcodeDetector')&&str_contains($participantJs,'availabilityMessage')];
$checks[] = ['name'=>'participant progress portfolio','ok'=>str_contains($myPage,'data-my-loyalty-quests')&&str_contains($portfolioApi,'latest_review_note')&&str_contains($portfolioApi,'latest_evidence_status')&&str_contains($myJs,'Correct and resubmit')&&str_contains($myJs,'Merchant review')];
$checks[] = ['name'=>'merchant-scoped evidence review','ok'=>substr_count($reviewApi,'merchant_user_id')>=12&&str_contains($reviewApi,"mg_merchant_require_permission(\$method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage')")&&str_contains($reviewApi,'Add a reason so the participant knows what to correct.')&&str_contains($reviewApi,'mg_audit')];
$checks[] = ['name'=>'merchant review workspace','ok'=>preg_match('/\$merchantView\s*=\s*[\'\"]quest_reviews[\'\"]\s*;/', $reviewPage)===1&&str_contains($reviewView,'data-quest-review-workspace')&&str_contains($reviewView,'aria-live="polite"')&&str_contains($reviewView,'<dialog')&&str_contains($reviewJs,'data-dialog-decision')&&preg_match('/@media\s*\(\s*max-width\s*:\s*680px\s*\)/',$reviewCss)===1];
$checks[] = ['name'=>'compact dropdown omits quest shortcuts','ok'=>!str_contains($accountNav,'/my-quests.php')&&!str_contains($accountNav,'/merchant-loyalty-quests.php')&&str_contains($accountNav,'/merchant-quest-reviews.php')&&str_contains($merchantNav,"'quest_reviews' => 'campaigns'")&&str_contains($merchantNav,"'campaigns' => ['Campaigns'")];
$checks[] = ['name'=>'safe historical detail state','ok'=>str_contains($detail,'availability')&&str_contains($detail,'can_start')&&str_contains($detail,'can_submit')&&str_contains($detail,'mg_lqp_campaign($pdo, $ref, false, false)')&&str_contains($participant,'$enforceAvailability = true')];
$checks[] = ['name'=>'responsive accessible camera flow','ok'=>str_contains($page,'aria-modal="true"')&&str_contains($page,'aria-live="polite"')&&str_contains($participantJs,'scannerTrigger')&&str_contains($participantJs,"event.key==='Escape'")&&str_contains($participantCss,'@media(max-width:680px)')&&str_contains($participantCss,':focus-visible')];
$checks[] = ['name'=>'audit and campaign events','ok'=>str_contains($start,"mg_audit('participant.loyalty_quest_joined'")&&str_contains($submit,'participant.loyalty_quest_evidence_submitted')&&str_contains($submit,'participant.loyalty_quest_evidence_verified')&&str_contains($reviewApi,'merchant.loyalty_quest_evidence_approved')&&str_contains($reviewApi,'merchant.loyalty_quest_evidence_rejected')];

$failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['ok']));
$score = max(0, 10 - count($failed) * 0.4);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1) . '/10','checks'=>$checks,'failed'=>$failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed === [] ? 0 : 1);