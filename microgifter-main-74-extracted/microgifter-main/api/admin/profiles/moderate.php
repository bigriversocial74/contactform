<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$admin = mg_require_permission('admin.users.view');
$profileId = (int) ($input['profile_id'] ?? 0);
$status = trim((string) ($input['status'] ?? ''));
$reason = trim((string) ($input['reason'] ?? ''));

if ($profileId <= 0) {
    mg_fail('Profile is required.', 422, ['profile_id' => 'Profile is required.']);
}
if (!in_array($status, ['draft', 'active', 'hidden', 'suspended'], true)) {
    mg_fail('Invalid profile status.', 422, ['status' => 'Invalid profile status.']);
}

$pdo = mg_db();
$stmt = $pdo->prepare('UPDATE public_profiles SET status = ?, updated_at = NOW() WHERE id = ?');
$stmt->execute([$status, $profileId]);

mg_audit('profile.moderated', 'public_profile', [
    'profile_id' => $profileId,
    'status' => $status,
    'reason' => $reason,
], (int) $admin['id']);

mg_ok(['profile_id' => $profileId, 'status' => $status], 'Profile moderated.');
