<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../profiles/_public_profile.php';

function mg_customer_review_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_customer_review_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_customer_review_period_start(string $period): string
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

mg_require_method('GET');
$pdo = mg_db();
$slug = mg_public_profile_slug((string)($_GET['slug'] ?? ''));
$currentUser = mg_current_user();
$viewerId = (int)($currentUser['id'] ?? 0);
$viewerId = $viewerId > 0 ? $viewerId : null;

try {
    $profileData = mg_public_profile_read($pdo, $slug, [
        'viewer_id' => $viewerId,
        'preview' => !empty($_GET['preview']),
        'product_limit' => 1,
        'post_limit' => 1,
        'plan_limit' => 1,
    ]);
    $profileStmt = $pdo->prepare('SELECT id,public_id,user_id,display_name FROM public_profiles WHERE slug=? LIMIT 1');
    $profileStmt->execute([$slug]);
    $profileRow = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if (!$profileRow) mg_fail('Profile not found.', 404);

    $merchantId = (int)$profileRow['user_id'];
    $isOwner = $viewerId !== null && $viewerId === $merchantId;
    $schemaReady = mg_customer_review_table_exists($pdo, 'customer_reviews');

    $campaignStmt = $pdo->prepare(
        "SELECT c.id,c.public_id,c.title,c.description,c.form_headline,c.form_description,c.success_message,c.rules_json,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,
                rt.public_id reward_template_public_id,rt.title reward_title,rt.description reward_description,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency
         FROM campaigns c
         INNER JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.status='active'
         WHERE c.merchant_user_id=? AND c.campaign_type='customer_review' AND c.status='active'
           AND (c.starts_at IS NULL OR c.starts_at<=NOW())
           AND (c.ends_at IS NULL OR c.ends_at>=NOW())
           AND (c.quantity_limit IS NULL OR c.issued_count<c.quantity_limit)
         ORDER BY c.updated_at DESC,c.id DESC
         LIMIT 1"
    );
    $campaignStmt->execute([$merchantId]);
    $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $reviews = [];
    $summary = [
        'count' => 0,
        'average' => 0.0,
        'distribution' => ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0],
    ];

    if ($schemaReady) {
        $summaryStmt = $pdo->prepare(
            "SELECT COUNT(*) total,COALESCE(AVG(rating),0) average_rating,
                    SUM(rating=5) five_star,SUM(rating=4) four_star,SUM(rating=3) three_star,SUM(rating=2) two_star,SUM(rating=1) one_star
             FROM customer_reviews
             WHERE merchant_user_id=? AND status='published'"
        );
        $summaryStmt->execute([$merchantId]);
        $row = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary = [
            'count' => (int)($row['total'] ?? 0),
            'average' => round((float)($row['average_rating'] ?? 0), 1),
            'distribution' => [
                '5' => (int)($row['five_star'] ?? 0),
                '4' => (int)($row['four_star'] ?? 0),
                '3' => (int)($row['three_star'] ?? 0),
                '2' => (int)($row['two_star'] ?? 0),
                '1' => (int)($row['one_star'] ?? 0),
            ],
        ];

        $reviewStmt = $pdo->prepare(
            "SELECT public_id,reviewer_name,rating,review_title,review_body,submitted_at
             FROM customer_reviews
             WHERE merchant_user_id=? AND status='published'
             ORDER BY submitted_at DESC,id DESC
             LIMIT 50"
        );
        $reviewStmt->execute([$merchantId]);
        $reviews = array_map(static fn(array $row): array => [
            'id' => (string)$row['public_id'],
            'reviewer_name' => (string)$row['reviewer_name'],
            'rating' => (int)$row['rating'],
            'title' => $row['review_title'] !== null ? (string)$row['review_title'] : null,
            'body' => (string)$row['review_body'],
            'submitted_at' => (string)$row['submitted_at'],
        ], $reviewStmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $eligibility = [
        'authenticated' => $viewerId !== null,
        'is_owner' => $isOwner,
        'can_review' => false,
        'used' => 0,
        'remaining' => 0,
        'max_reviews' => 0,
        'period' => 'month',
        'period_start' => null,
        'reason' => $viewerId === null ? 'Sign in to submit a review and receive the reward.' : 'No active Customer Review campaign is available.',
    ];

    $campaignPayload = null;
    if ($campaign) {
        $rules = mg_customer_review_rules($campaign['rules_json'] ?? null);
        $period = strtolower((string)($rules['limit_period'] ?? 'month'));
        if (!in_array($period, ['day', 'week', 'month', 'quarter', 'year'], true)) $period = 'month';
        $maxReviews = max(1, min(1000, (int)($rules['max_reviews_per_period'] ?? 1)));
        $periodStart = mg_customer_review_period_start($period);
        $used = 0;

        if ($schemaReady && $viewerId !== null) {
            $usedStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM customer_reviews
                 WHERE campaign_id=? AND reviewer_user_id=? AND status IN ('published','pending') AND submitted_at>=?"
            );
            $usedStmt->execute([(int)$campaign['id'], $viewerId, $periodStart]);
            $used = (int)$usedStmt->fetchColumn();
        }

        $rewardValue = '';
        if ((string)($campaign['value_type'] ?? '') === 'percent' && $campaign['value_percent'] !== null) {
            $rewardValue = rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '%';
        } elseif ((int)($campaign['value_amount_cents'] ?? 0) > 0) {
            $currency = strtoupper((string)($campaign['currency'] ?? 'USD'));
            $rewardValue = ($currency === 'USD' ? '$' : $currency . ' ') . number_format((int)$campaign['value_amount_cents'] / 100, 2);
        }

        $remaining = max(0, $maxReviews - $used);
        $canReview = $schemaReady && $viewerId !== null && !$isOwner && $remaining > 0;
        $reason = '';
        if (!$schemaReady) $reason = 'The review database migration has not been installed.';
        elseif ($viewerId === null) $reason = 'Sign in to submit a review and receive the reward.';
        elseif ($isOwner) $reason = 'You cannot review your own merchant profile.';
        elseif ($remaining < 1) $reason = 'You reached this campaign review limit for the current ' . $period . '.';

        $eligibility = [
            'authenticated' => $viewerId !== null,
            'is_owner' => $isOwner,
            'can_review' => $canReview,
            'used' => $used,
            'remaining' => $remaining,
            'max_reviews' => $maxReviews,
            'period' => $period,
            'period_start' => $periodStart,
            'reason' => $reason,
        ];

        $campaignPayload = [
            'id' => (string)$campaign['public_id'],
            'title' => (string)$campaign['title'],
            'headline' => trim((string)($campaign['form_headline'] ?? '')) ?: 'Share your experience',
            'description' => trim((string)($campaign['form_description'] ?? '')) ?: (trim((string)($campaign['description'] ?? '')) ?: 'Rate your experience and write a customer review.'),
            'prompt' => trim((string)($rules['prompt'] ?? '')) ?: 'Tell us about your experience.',
            'max_reviews' => $maxReviews,
            'period' => $period,
            'reward' => [
                'id' => (string)$campaign['reward_template_public_id'],
                'title' => (string)$campaign['reward_title'],
                'description' => $campaign['reward_description'] !== null ? (string)$campaign['reward_description'] : null,
                'value' => $rewardValue,
                'destination' => 'Wallet → Inbox PPPM',
            ],
        ];
    }

    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok([
        'profile' => [
            'id' => (string)$profileRow['public_id'],
            'slug' => $slug,
            'display_name' => (string)$profileRow['display_name'],
        ],
        'summary' => $summary,
        'reviews' => $reviews,
        'campaign' => $campaignPayload,
        'eligibility' => $eligibility,
        'schema_ready' => $schemaReady,
        'review_module' => 'customer_review_profile_module_v1',
        'profile_context' => $profileData['profile'] ?? [],
    ]);
} catch (InvalidArgumentException|RuntimeException) {
    mg_fail('Profile not found.', 404);
} catch (Throwable $error) {
    mg_security_log('error', 'public.profile_reviews_failed', 'Unable to load profile reviews.', [
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], $viewerId);
    mg_fail('Unable to load reviews.', 500);
}
