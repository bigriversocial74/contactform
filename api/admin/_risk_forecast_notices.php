<?php
declare(strict_types=1);

require_once __DIR__ . '/_queue_alerts.php';

function mg_risk_forecast_notice_exists(PDO $pdo, string $type): bool
{
    $stmt = $pdo->prepare('SELECT id FROM admin_queue_notifications WHERE notification_type = ? AND DATE(created_at) = CURDATE() LIMIT 1');
    $stmt->execute([$type]);
    return (bool)$stmt->fetchColumn();
}

function mg_risk_forecast_notice(PDO $pdo, int $actorId, string $type, string $severity, string $title, string $message, array $metadata): void
{
    if (mg_risk_forecast_notice_exists($pdo, $type)) {
        return;
    }
    mg_queue_notice_create($pdo, [
        'note_id' => null,
        'target_user_id' => null,
        'assigned_admin_user_id' => null,
        'actor_user_id' => $actorId,
        'notification_type' => $type,
        'severity' => $severity,
        'title' => $title,
        'message' => $message,
        'metadata' => $metadata,
    ]);
}

function mg_risk_forecast_notify(PDO $pdo, int $actorId, array $payload): void
{
    $risk = $payload['risk'] ?? [];
    $forecast = $payload['forecast'] ?? [];
    if (in_array((string)($risk['level'] ?? 'low'), ['high','critical'], true)) {
        mg_risk_forecast_notice($pdo, $actorId, 'risk_forecast_high', (string)$risk['level'] === 'critical' ? 'critical' : 'warning', 'Predictive ops risk is high', 'The operations risk forecast reached ' . (string)$risk['level'] . ' with score ' . (string)$risk['score'] . '.', ['risk'=>$risk]);
    }
    if ((int)($forecast['sla_breaches_24h'] ?? 0) > 0) {
        mg_risk_forecast_notice($pdo, $actorId, 'forecasted_sla_breach', 'warning', 'Forecasted SLA breach detected', 'Queue work is forecasted to breach SLA within 24 hours.', ['forecasted_sla_breaches'=>$forecast['sla_breaches_24h']]);
    }
    if (count($forecast['queue_overload_lanes'] ?? []) > 0) {
        mg_risk_forecast_notice($pdo, $actorId, 'queue_overload_predicted', 'warning', 'Queue overload predicted', 'One or more admin lanes are forecasted to overload.', ['lanes'=>$forecast['queue_overload_lanes']]);
    }
}
