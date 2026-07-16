<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)$user['id'];
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    mg_user_agent_api_run(static function () use ($pdo,$userId): array {
        $mode = strtolower(mg_personal_agent_text($_GET['mode'] ?? 'dashboard',30));
        if ($mode === 'preferences') return ['preferences'=>mg_personal_agent_recovery_preferences($pdo,$userId)];
        if ($mode === 'context') return ['recovery'=>mg_personal_agent_recovery_context($pdo,$userId)];
        $status = mg_personal_agent_text($_GET['status'] ?? 'active',30);
        $limit = max(1,min(200,(int)($_GET['limit'] ?? 100)));
        return [
            'preferences'=>mg_personal_agent_recovery_preferences($pdo,$userId),
            'followups'=>mg_personal_agent_recovery_followups($pdo,$userId,$status,$limit),
        ];
    });
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

mg_user_agent_api_run(static function () use ($pdo,$userId,$input): array {
    $action = strtolower(mg_personal_agent_text($input['action'] ?? '',30));
    if ($action === 'preferences') {
        return ['preferences'=>mg_personal_agent_recovery_update_preferences($pdo,$userId,$input)];
    }
    if ($action === 'schedule') {
        $publicId = mg_personal_agent_text($input['opportunity_id'] ?? '',36);
        $token = mg_personal_agent_text($input['attribution_token'] ?? '',64);
        $opportunity = mg_personal_agent_opportunity_find($pdo,$userId,$publicId,$token);
        $preferences = mg_personal_agent_recovery_preferences($pdo,$userId);
        $scheduledAt = trim((string)($input['scheduled_at'] ?? ''));
        if ($scheduledAt === '') {
            $hours = max(1,min(8760,(int)($input['delay_hours'] ?? $preferences['default_snooze_hours'])));
            $scheduledAt = (new DateTimeImmutable('now',new DateTimeZone((string)$preferences['timezone'])))->modify('+' . $hours . ' hours')->format(DateTimeInterface::ATOM);
        }
        $when = mg_personal_agent_recovery_datetime($scheduledAt,(string)$preferences['timezone']);
        $followup = mg_personal_agent_recovery_schedule($pdo,$opportunity,'manual',$when,[
            'source'=>'customer','page_path'=>mg_personal_agent_text($input['page_path'] ?? '',500),
        ],'manual:' . (string)$opportunity['public_id'] . ':' . str_replace(['-',':',' '],'',$when));
        mg_audit('user_agent.recovery_followup_scheduled','personal_agent_opportunity_followup',[
            'opportunity_id'=>(string)$opportunity['public_id'],'followup_id'=>$followup['id'] ?? null,'scheduled_for'=>$when,
        ],$userId);
        return ['followup'=>$followup];
    }
    if (in_array($action,['snooze','dismiss','mute','resume'],true)) {
        $followupId = mg_personal_agent_text($input['followup_id'] ?? '',36);
        if ($followupId === '') throw new InvalidArgumentException('Follow-up identifier is required.');
        $followup = mg_personal_agent_recovery_action($pdo,$userId,$followupId,$action,$input);
        mg_audit('user_agent.recovery_followup_' . $action,'personal_agent_opportunity_followup',[
            'followup_id'=>$followupId,'opportunity_id'=>$followup['opportunity_id'] ?? null,
        ],$userId);
        return ['followup'=>$followup];
    }
    throw new InvalidArgumentException('Unsupported recovery action.');
});
