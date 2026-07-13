<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm-value-events.php';
require_once __DIR__ . '/_limits.php';
require_once __DIR__ . '/_merchant_notifications.php';

function mg_customer_review_submit_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_customer_review_submit_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_customer_review_submit_period_start(string $period): string
{
    $now = new DateTimeImmutable('now');
    return match ($period) {
        'day' => $now->setTime(0, 0)->format('Y-m-d H:i:s'),
        'week' => $now->modify('monday this week')->setTime(0, 0)->format('Y-m-d H:i:s'),
        'quarter' => $now->setDate(
            (int)$now->format('Y'),
            ((int)floor(((int)$now->format('n') - 1) / 3) * 3) + 1,
            1
        )->setTime(0, 0)->format('Y-m-d H:i:s'),
        'year' => $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0)->format('Y-m-d H:i:s'),
        default => $now->modify('first day of this month')->setTime(0, 0)->format('Y-m-d H:i:s'),
    };
}

function mg_customer_review_submit_expiry(array $campaign): ?string
{
    $rule = (string)($campaign['expiration_rule'] ?? 'none');
    if (in_array($rule, ['fixed_date', 'event_date'], true)) return $campaign['expires_at'] ?: null;
    if ($rule === 'after_issue' && !empty($campaign['expiration_days'])) {
        return date('Y-m-d H:i:s', time() + ((int)$campaign['expiration_days'] * 86400));
    }
    return null;
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$sessionUser = mg_current_user();
$userId = (int)($sessionUser['id'] ?? 0);
if ($userId < 1) mg_fail('Sign in to submit a review and receive the reward.', 401);

$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? '')));
$profileSlug = strtolower(trim((string)($input['profile_slug'] ?? '')));
$rating = (int)($input['rating'] ?? 0);
$reviewTitle = trim((string)($input['review_title'] ?? ''));
$reviewBody = trim((string)($input['review_body'] ?? ''));
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));

if (preg_match('/^[a-f0-9-]{36}$/', $campaignRef) !== 1) mg_fail('Invalid review campaign.', 422);
if ($profileSlug === '' || strlen($profileSlug) > 120 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $profileSlug) !== 1) {
    mg_fail('Invalid merchant profile.', 422);
}
if ($rating < 1 || $rating > 5) mg_fail('Choose a rating from one to five stars.', 422);
if ($reviewTitle !== '' && mb_strlen($reviewTitle) > 180) mg_fail('Review title must be 180 characters or fewer.', 422);
if (mb_strlen($reviewBody) < 10 || mb_strlen($reviewBody) > 3000) {
    mg_fail('Review must be between 10 and 3000 characters.', 422);
}
if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 190) {
    $idempotencyKey = 'customer-review:' . $userId . ':' . hash('sha256', $campaignRef . '|' . $reviewBody . '|' . microtime(true));
}

$pdo = mg_db();

try {
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare("SELECT id,email,display_name,full_name,status FROM users WHERE id=? LIMIT 1 FOR UPDATE");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || (string)$user['status'] !== 'active') {
        $pdo->rollBack();
        mg_fail('Your account is not available for review rewards.', 403);
    }
    $email = strtolower(trim((string)$user['email']));
    $reviewerName = trim((string)($user['display_name'] ?? '')) ?: trim((string)($user['full_name'] ?? '')) ?: 'Microgifter Customer';

    $duplicateStmt = $pdo->prepare(
        "SELECT cr.public_id,wi.public_id wallet_public_id,wi.status wallet_status,rt.title reward_title
         FROM customer_reviews cr
         LEFT JOIN wallet_items wi ON wi.id=cr.wallet_item_id
         LEFT JOIN campaigns c ON c.id=cr.campaign_id
         LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
         WHERE cr.reviewer_user_id=? AND cr.idempotency_key=?
         LIMIT 1 FOR UPDATE"
    );
    $duplicateStmt->execute([$userId, $idempotencyKey]);
    $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate) {
        $pdo->commit();
        mg_ok([
            'review_id' => (string)$duplicate['public_id'],
            'wallet_item_id' => $duplicate['wallet_public_id'] !== null ? (string)$duplicate['wallet_public_id'] : null,
            'wallet_status' => $duplicate['wallet_status'] !== null ? (string)$duplicate['wallet_status'] : null,
            'reward_title' => $duplicate['reward_title'] !== null ? (string)$duplicate['reward_title'] : null,
            'already_submitted' => true,
            'inbox_url' => '/inbox.php',
        ], 'This review was already submitted.');
    }

    $campaignStmt = $pdo->prepare(
        "SELECT c.*,pp.id profile_db_id,pp.public_id profile_public_id,pp.slug profile_slug,pp.display_name merchant_name,
                rt.id reward_template_db_id,rt.public_id reward_template_public_id,rt.title reward_template_title,
                rt.description reward_template_description,rt.redemption_instructions,rt.value_amount_cents,rt.currency,
                rt.expiration_rule,rt.expiration_days,rt.expires_at,rt.quantity_limit reward_template_quantity_limit,
                rt.issued_count reward_template_issued_count,rt.per_user_limit reward_template_per_user_limit
         FROM campaigns c
         INNER JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.slug=? AND pp.status='active' AND pp.visibility IN ('public','unlisted')
         INNER JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.status='active'
         WHERE c.public_id=? AND c.campaign_type='customer_review' AND c.status='active'
         LIMIT 1 FOR UPDATE"
    );
    $campaignStmt->execute([$profileSlug, $campaignRef]);
    $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        $pdo->rollBack();
        mg_fail('Customer Review campaign is not available.', 404);
    }
    if ((int)$campaign['merchant_user_id'] === $userId) {
        $pdo->rollBack();
        mg_fail('You cannot review your own merchant profile.', 409);
    }

    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) {
        $pdo->rollBack();
        mg_fail('Customer Review campaign has not started yet.', 409);
    }
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) {
        $pdo->rollBack();
        mg_fail('Customer Review campaign has ended.', 409);
    }
    if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Customer Review reward limit has been reached.', 409);
    }
    if ($campaign['reward_template_quantity_limit'] !== null
        && (int)$campaign['reward_template_issued_count'] >= (int)$campaign['reward_template_quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Review reward template limit has been reached.', 409);
    }

    $rules = mg_customer_review_submit_rules($campaign['rules_json'] ?? null);
    $period = strtolower((string)($rules['limit_period'] ?? 'month'));
    if (!in_array($period, ['day', 'week', 'month', 'quarter', 'year'], true)) $period = 'month';
    $maxReviews = max(1, min(1000, (int)($rules['max_reviews_per_period'] ?? 1)));
    $periodStart = mg_customer_review_submit_period_start($period);

    $limitStmt = $pdo->prepare(
        "SELECT id FROM customer_reviews
         WHERE campaign_id=? AND reviewer_user_id=? AND status IN ('published','pending') AND submitted_at>=?
         FOR UPDATE"
    );
    $limitStmt->execute([(int)$campaign['id'], $userId, $periodStart]);
    $used = count($limitStmt->fetchAll(PDO::FETCH_COLUMN));
    if ($used >= $maxReviews) {
        $pdo->rollBack();
        mg_fail('You reached the maximum number of reviews allowed for this campaign this ' . $period . '.', 409);
    }

    $existingContactStmt = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1 FOR UPDATE');
    $existingContactStmt->execute([(int)$campaign['id'], $email]);
    $existingContact = $existingContactStmt->fetch(PDO::FETCH_ASSOC);
    mg_public_campaign_enforce_crm_contact_limit($pdo, (int)$campaign['merchant_user_id'], $email, !$existingContact);

    $contactPublicId = $existingContact ? (string)$existingContact['public_id'] : mg_customer_review_submit_uuid();
    $contactMetadata = [
        'campaign_type' => 'customer_review',
        'profile_slug' => $profileSlug,
        'review_rating' => $rating,
        'review_period' => $period,
        'reward_destination' => 'wallet_pppm_inbox',
        'crm_creation_boundary' => 'first_value_event',
        'value_event' => true,
        'value_event_type' => 'customer_review_submitted',
        'ip' => mg_client_ip(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ];
    $contactStmt = $pdo->prepare(
        "INSERT INTO campaign_contacts
         (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,NULL,?,'customer_review','opted_in',?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),name=VALUES(name),source='customer_review',metadata_json=VALUES(metadata_json),updated_at=NOW()"
    );
    $contactStmt->execute([
        $contactPublicId,
        (int)$campaign['merchant_user_id'],
        (int)$campaign['id'],
        $userId,
        $email,
        $reviewerName,
        json_encode($contactMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    $contactLookup = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1');
    $contactLookup->execute([(int)$campaign['id'], $email]);
    $contact = $contactLookup->fetch(PDO::FETCH_ASSOC);
    $contactId = (int)($contact['id'] ?? 0);

    $reviewPublicId = mg_customer_review_submit_uuid();
    $reviewMetadata = [
        'campaign_public_id' => (string)$campaign['public_id'],
        'profile_public_id' => (string)$campaign['profile_public_id'],
        'profile_slug' => $profileSlug,
        'period' => $period,
        'period_start' => $periodStart,
        'max_reviews_per_period' => $maxReviews,
        'submission_number_in_period' => $used + 1,
        'reward_destination' => 'wallet_pppm_inbox',
    ];
    $reviewStmt = $pdo->prepare(
        "INSERT INTO customer_reviews
         (public_id,merchant_user_id,profile_id,campaign_id,reviewer_user_id,contact_id,wallet_item_id,idempotency_key,reviewer_name,rating,review_title,review_body,status,metadata_json,submitted_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?,NULL,?,?,?,?,?,'published',?,NOW(),NOW(),NOW())"
    );
    $reviewStmt->execute([
        $reviewPublicId,
        (int)$campaign['merchant_user_id'],
        (int)$campaign['profile_db_id'],
        (int)$campaign['id'],
        $userId,
        $contactId ?: null,
        $idempotencyKey,
        $reviewerName,
        $rating,
        $reviewTitle !== '' ? $reviewTitle : null,
        $reviewBody,
        json_encode($reviewMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    $reviewDbId = (int)$pdo->lastInsertId();

    $crm = mg_merchant_crm_record_value_event($pdo, [
        'merchant_user_id' => (int)$campaign['merchant_user_id'],
        'campaign_id' => (int)$campaign['id'],
        'campaign_type' => 'customer_review',
        'event_type' => 'customer_review.submitted',
        'source_type' => 'customer_review',
        'source_public_id' => $reviewPublicId,
        'user_id' => $userId,
        'email' => $email,
        'phone' => '',
        'name' => $reviewerName,
        'metadata' => $reviewMetadata + [
            'rating' => $rating,
            'review_title' => $reviewTitle,
            'review_body' => $reviewBody,
        ],
    ]);
    $merchantNotification = mg_public_campaign_notify_merchant_contact(
        $pdo,
        $campaign,
        $contact ?: [],
        $email,
        $reviewerName,
        '',
        'customer_review',
        $crm,
        !$existingContact
    );

    $eventStmt = $pdo->prepare(
        'INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at)
         VALUES (?,?,?,?,?,?,?,NOW())'
    );
    $eventStmt->execute([
        mg_customer_review_submit_uuid(),
        (int)$campaign['merchant_user_id'],
        (int)$campaign['id'],
        null,
        $contactId ?: null,
        'customer_review.submitted',
        json_encode([
            'review_id' => $reviewPublicId,
            'rating' => $rating,
            'period' => $period,
            'submission_number_in_period' => $used + 1,
            'merchant_crm' => $crm,
            'merchant_notification' => $merchantNotification,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    mg_public_campaign_enforce_monthly_stamp_limit($pdo, (int)$campaign['merchant_user_id']);
    $walletPublicId = mg_customer_review_submit_uuid();
    $stampLedger = mg_public_campaign_debit_reward_stamp($pdo, $campaign, $walletPublicId, 'customer_review', [
        'review_id' => $reviewPublicId,
        'rating' => $rating,
        'contact_id' => $contactPublicId,
    ]);
    $expiresAt = mg_customer_review_submit_expiry($campaign);
    $walletMetadata = [
        'campaign_type' => 'customer_review',
        'review_id' => $reviewPublicId,
        'review_rating' => $rating,
        'review_title' => $reviewTitle,
        'profile_slug' => $profileSlug,
        'reward_template_id' => (string)$campaign['reward_template_public_id'],
        'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
        'reward_destination' => 'wallet_pppm_inbox',
        'value_event' => true,
        'value_event_type' => 'customer_review_reward_issued',
    ];
    $walletStmt = $pdo->prepare(
        "INSERT INTO wallet_items
         (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?,'customer_review',?,'issued',?,?,?,?,NOW(),?,NOW(),NOW())"
    );
    $walletStmt->execute([
        $walletPublicId,
        $userId,
        $contactId ?: null,
        (int)$campaign['merchant_user_id'],
        (int)$campaign['reward_template_db_id'],
        (int)$campaign['id'],
        $reviewPublicId,
        (int)($campaign['value_amount_cents'] ?? 0),
        (string)($campaign['currency'] ?? 'USD'),
        (string)$campaign['reward_template_title'],
        json_encode($walletMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $expiresAt,
    ]);
    $walletDbId = (int)$pdo->lastInsertId();

    $pdo->prepare('UPDATE campaigns SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$campaign['id']]);
    $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$campaign['reward_template_db_id']]);
    $pdo->prepare('UPDATE customer_reviews SET wallet_item_id=?,updated_at=NOW() WHERE id=?')->execute([$walletDbId, $reviewDbId]);

    $bridge = mg_zero_reward_issue_from_wallet($pdo, [
        'merchant_user_id' => (int)$campaign['merchant_user_id'],
        'recipient_user_id' => $userId,
        'recipient_external_id' => $reviewPublicId,
        'recipient_name' => $reviewerName,
        'wallet_item_db_id' => $walletDbId,
        'wallet_item_public_id' => $walletPublicId,
        'campaign_public_id' => (string)$campaign['public_id'],
        'reward_template_public_id' => (string)$campaign['reward_template_public_id'],
        'source_type' => 'customer_review',
        'source_reference' => $walletPublicId,
        'source_line_reference' => $reviewPublicId,
        'title' => (string)$campaign['reward_template_title'],
        'description' => $campaign['reward_template_description'] ?? $campaign['description'] ?? null,
        'currency' => (string)($campaign['currency'] ?? 'USD'),
        'display_value_cents' => (int)($campaign['value_amount_cents'] ?? 0),
        'expires_at' => $expiresAt,
        'redemption_instructions' => $campaign['redemption_instructions'] ?? null,
        'terms' => [
            'campaign_type' => 'customer_review',
            'review_id' => $reviewPublicId,
            'rating' => $rating,
        ],
    ]);

    $eventStmt->execute([
        mg_customer_review_submit_uuid(),
        (int)$campaign['merchant_user_id'],
        (int)$campaign['id'],
        $walletDbId,
        $contactId ?: null,
        'wallet_item.issued',
        json_encode([
            'wallet_item_id' => $walletPublicId,
            'review_id' => $reviewPublicId,
            'campaign_type' => 'customer_review',
            'source' => 'customer_review',
            'pppm_bridge' => $bridge,
            'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    $pdo->commit();

    mg_ok([
        'review' => [
            'id' => $reviewPublicId,
            'reviewer_name' => $reviewerName,
            'rating' => $rating,
            'title' => $reviewTitle !== '' ? $reviewTitle : null,
            'body' => $reviewBody,
            'submitted_at' => date('Y-m-d H:i:s'),
        ],
        'review_id' => $reviewPublicId,
        'campaign_id' => (string)$campaign['public_id'],
        'campaign_type' => 'customer_review',
        'wallet_item_id' => $walletPublicId,
        'wallet_status' => 'issued',
        'reward_title' => (string)$campaign['reward_template_title'],
        'expires_at' => $expiresAt,
        'pppm_bridge' => $bridge,
        'stamp_ledger' => $stampLedger,
        'merchant_crm' => $crm,
        'merchant_notification' => $merchantNotification,
        'inbox_url' => '/inbox.php',
        'already_submitted' => false,
    ], (string)($campaign['success_message'] ?? 'Review submitted. Your reward is in your wallet and Microgifter Inbox.'), 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.customer_review_submit_failed', 'Unable to submit customer review.', [
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], $userId);
    mg_fail('Unable to submit your review.', 500);
}
