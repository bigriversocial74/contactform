<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_permission($method === 'GET' ? 'notification.view' : 'notification.preferences.manage');
$pdo = mg_db();

$definitions = [
    'gift' => ['label'=>'Gifts and Microgifts','description'=>'New gifts, Microgifts, and important gift activity sent directly to you.'],
    'message' => ['label'=>'Messages','description'=>'New messages in gift and Microgifter item conversations.'],
    'social' => ['label'=>'Social activity','description'=>'New followers and other activity involving your public profile.'],
    'claim' => ['label'=>'Claims','description'=>'Claim-code, claim-window, and recipient claim updates.'],
    'delivery' => ['label'=>'Delivery','description'=>'Delivery status, failures, and fulfillment updates.'],
    'distribution' => ['label'=>'Distribution','description'=>'Distribution program and recipient-delivery activity.'],
    'campaign' => ['label'=>'Campaigns','description'=>'Campaign status and scheduled campaign activity.'],
    'merchant' => ['label'=>'Merchant operations','description'=>'Merchant account, location, and operational updates.'],
    'share_market' => ['label'=>'DAVE Share Market','description'=>'Merchant opt-in, series review, admin decisions, and Share Market workflow alerts.'],
    'security' => ['label'=>'Security','description'=>'Account security, sign-in, and recovery notifications.'],
    'system' => ['label'=>'System','description'=>'Required service and platform notices.'],
];
$types = array_keys($definitions);

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT notification_type,in_app_enabled,email_enabled,sms_enabled,push_enabled,digest_mode,
                quiet_hours_start,quiet_hours_end,timezone
         FROM notification_preferences
         WHERE user_id=? ORDER BY notification_type'
    );
    $stmt->execute([(int)$user['id']]);
    $saved = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $saved[(string)$row['notification_type']] = $row;

    $preferences = [];
    foreach ($types as $type) {
        $row = $saved[$type] ?? [
            'notification_type'=>$type,
            'in_app_enabled'=>1,
            'email_enabled'=>1,
            'sms_enabled'=>0,
            'push_enabled'=>1,
            'digest_mode'=>'immediate',
            'quiet_hours_start'=>null,
            'quiet_hours_end'=>null,
            'timezone'=>'UTC',
        ];
        $row['label'] = $definitions[$type]['label'];
        $row['description'] = $definitions[$type]['description'];
        $preferences[] = $row;
    }
    mg_ok(['preferences'=>$preferences]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$type = strtolower(trim((string)($input['notification_type'] ?? '')));
if (!in_array($type, $types, true)) mg_fail('Invalid notification type.', 422);
$digest = strtolower(trim((string)($input['digest_mode'] ?? 'immediate')));
if (!in_array($digest, ['immediate','hourly','daily','weekly','off'], true)) mg_fail('Invalid digest mode.', 422);

$timezone = trim((string)($input['timezone'] ?? 'UTC')) ?: 'UTC';
try {
    new DateTimeZone($timezone);
} catch (Throwable) {
    mg_fail('Invalid timezone.', 422);
}

$quietStart = trim((string)($input['quiet_hours_start'] ?? ''));
$quietEnd = trim((string)($input['quiet_hours_end'] ?? ''));
foreach ([$quietStart,$quietEnd] as $quietValue) {
    if ($quietValue !== '' && preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $quietValue) !== 1) {
        mg_fail('Quiet hours must use a valid 24-hour time.', 422);
    }
}
if (($quietStart === '') !== ($quietEnd === '')) mg_fail('Choose both a quiet-hours start and end time.', 422);

$values = [
    (int)$user['id'],
    $type,
    !empty($input['in_app_enabled']) ? 1 : 0,
    !empty($input['email_enabled']) ? 1 : 0,
    !empty($input['sms_enabled']) ? 1 : 0,
    !empty($input['push_enabled']) ? 1 : 0,
    $digest,
    $quietStart !== '' ? $quietStart : null,
    $quietEnd !== '' ? $quietEnd : null,
    $timezone,
];
$pdo->prepare(
    'INSERT INTO notification_preferences
     (user_id,notification_type,in_app_enabled,email_enabled,sms_enabled,push_enabled,digest_mode,
      quiet_hours_start,quiet_hours_end,timezone,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
     ON DUPLICATE KEY UPDATE
       in_app_enabled=VALUES(in_app_enabled),email_enabled=VALUES(email_enabled),
       sms_enabled=VALUES(sms_enabled),push_enabled=VALUES(push_enabled),
       digest_mode=VALUES(digest_mode),quiet_hours_start=VALUES(quiet_hours_start),
       quiet_hours_end=VALUES(quiet_hours_end),timezone=VALUES(timezone),updated_at=NOW()'
)->execute($values);

mg_audit('notification.preference_updated','notification_preference',[
    'notification_type'=>$type,
    'digest_mode'=>$digest,
    'in_app_enabled'=>!empty($input['in_app_enabled']),
    'email_enabled'=>!empty($input['email_enabled']),
    'sms_enabled'=>!empty($input['sms_enabled']),
    'push_enabled'=>!empty($input['push_enabled']),
],(int)$user['id']);
mg_ok(['notification_type'=>$type],'Notification preference saved.');
