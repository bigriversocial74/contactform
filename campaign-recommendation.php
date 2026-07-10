<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/store/_canvas_campaign_recommendations.php';

$recommendationId = strtolower(trim((string)($_GET['recommendation'] ?? '')));
$returnPath = '/notifications.php';
$user = mg_require_auth('/signin.php', '/campaign-recommendation.php?recommendation=' . rawurlencode($recommendationId));
$pdo = mg_db();

try {
    $recommendationId = mg_store_safe_public_id($recommendationId, 'Campaign recommendation');
    $stmt = $pdo->prepare(
        "SELECT r.*,s.public_id store_session_public_id
         FROM mg_merchant_canvas_action_receipts r
         INNER JOIN mg_store_sessions s ON s.id=r.store_session_id
         WHERE r.public_id=? AND r.customer_user_id=? AND r.action_type='campaign_recommendation' AND r.status='completed'
         LIMIT 1"
    );
    $stmt->execute([$recommendationId, (int)$user['id']]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) throw new RuntimeException('Campaign recommendation is unavailable.');

    $response = mg_store_manual_ops_decode_json($receipt['response_json'] ?? '');
    $campaign = is_array($response['campaign'] ?? null) ? $response['campaign'] : [];
    $landingUrl = mg_notification_safe_action_url((string)($campaign['landing_url'] ?? ''));
    if ($landingUrl === null) throw new RuntimeException('Campaign participation page is unavailable.');

    $pdo->beginTransaction();
    try {
        $sessionStmt = $pdo->prepare('SELECT * FROM mg_store_sessions WHERE id=? AND merchant_user_id=? AND customer_user_id=? LIMIT 1 FOR UPDATE');
        $sessionStmt->execute([(int)$receipt['store_session_id'], (int)$receipt['merchant_user_id'], (int)$user['id']]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) throw new RuntimeException('Campaign recommendation session is unavailable.');

        $openKey = 'campaign-recommendation-open:' . str_replace('-', '', $recommendationId);
        $openHash = mg_store_manual_ops_request_hash([
            'recommendation_id' => $recommendationId,
            'customer_user_id' => (int)$user['id'],
            'campaign_id' => (string)($campaign['id'] ?? ''),
        ]);
        $openReceipt = mg_store_manual_ops_receipt_claim(
            $pdo,
            (int)$receipt['merchant_user_id'],
            (int)$user['id'],
            (int)$receipt['store_session_id'],
            (int)$user['id'],
            'campaign_recommendation_open',
            $openKey,
            $openHash
        );
        if (empty($openReceipt['duplicate'])) {
            mg_store_log_event($pdo, $session, 'campaign_recommendation_opened', 'Campaign recommendation opened', [
                'recommendation_id' => $recommendationId,
                'campaign_id' => (string)($campaign['id'] ?? ''),
                'campaign_title' => (string)($campaign['title'] ?? ''),
                'campaign_type' => (string)($campaign['campaign_type'] ?? ''),
                'reward_issued' => false,
                'reward_issue_policy' => 'campaign_completion_only',
            ]);
            mg_store_manual_ops_receipt_complete($pdo, (int)$openReceipt['id'], $recommendationId, [
                'recommendation_id' => $recommendationId,
                'campaign_id' => (string)($campaign['id'] ?? ''),
                'opened' => true,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $separator = str_contains($landingUrl, '?') ? '&' : '?';
    header('Location: ' . $landingUrl . $separator . 'source=store_canvas_notification&recommendation=' . rawurlencode($recommendationId), true, 302);
    exit;
} catch (Throwable $error) {
    mg_security_log('warning', 'store_canvas.campaign_recommendation_open_failed', 'Campaign recommendation open failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    header('Location: ' . $returnPath . '?campaign_recommendation=unavailable', true, 302);
    exit;
}
