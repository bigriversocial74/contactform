<?php
declare(strict_types=1);

require_once __DIR__ . '/investment-access.php';
require_once dirname(__DIR__) . '/mail.php';

const MG_INVESTOR_INVITATION_MIGRATION = 'database/20260729_investor_invite_onboarding_v1.sql';
const MG_INVESTOR_INVITATION_DISCLOSURE_VERSION = 'investor-invite-v1';

function mg_investment_invitation_tables_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'investor_invitations'");
        if (!$stmt->fetchColumn()) return false;
        $stmt = $pdo->query("SHOW TABLES LIKE 'investor_invitation_events'");
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_investment_invitation_require_schema(PDO $pdo): void
{
    if (!mg_investment_invitation_tables_ready($pdo)) {
        throw new MgInvestmentException('Investor invitation schema is not installed. Import ' . MG_INVESTOR_INVITATION_MIGRATION . '.', 503);
    }
}

function mg_investment_invitation_normalize_email(mixed $value): string
{
    $email = strtolower(trim((string)$value));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new MgInvestmentException('Enter a valid recipient email address.');
    }
    return $email;
}

function mg_investment_invitation_is_uuid(string $value): bool
{
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value) === 1;
}

function mg_investment_invitation_token(string $raw): string
{
    $token = trim($raw);
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        throw new MgInvestmentException('Investor invitation link is invalid.', 404);
    }
    return strtolower($token);
}

function mg_investment_invitation_mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') return 'Private recipient';
    $visible = mb_substr($local, 0, 1);
    return $visible . str_repeat('•', max(3, min(8, mb_strlen($local) - 1))) . '@' . $domain;
}

function mg_investment_invitation_event(PDO $pdo, int $invitationId, ?int $actorUserId, string $eventType, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO investor_invitation_events (invitation_id,actor_user_id,event_type,details_json,created_at) VALUES (?,?,?,?,NOW())');
    $stmt->execute([$invitationId,$actorUserId,$eventType,$details === [] ? null : mg_investment_json($details)]);
}

function mg_investment_invitation_expire_stale(PDO $pdo): void
{
    if (!mg_investment_invitation_tables_ready($pdo)) return;
    $pdo->exec("UPDATE investor_invitations SET status='expired',updated_at=NOW() WHERE status IN ('created','sent','viewed') AND expires_at<=NOW()");
}

function mg_investment_invitation_select_sql(string $where): string
{
    return "SELECT i.*,r.public_id round_public_id,r.public_name round_name,r.status round_status,
                   inviter.full_name inviter_name,inviter.display_name inviter_display_name,
                   accepted.full_name accepted_name,accepted.display_name accepted_display_name,
                   ar.public_id request_public_id,ar.status request_status
            FROM investor_invitations i
            LEFT JOIN investment_rounds r ON r.id=i.round_id
            INNER JOIN users inviter ON inviter.id=i.invited_by_user_id
            LEFT JOIN users accepted ON accepted.id=i.accepted_by_user_id
            LEFT JOIN investor_access_requests ar ON ar.id=i.request_id
            WHERE {$where}";
}

function mg_investment_invitation_row_by_public_id(PDO $pdo, string $publicId, bool $forUpdate = false): array
{
    if (!mg_investment_invitation_is_uuid($publicId)) throw new MgInvestmentException('Investor invitation not found.', 404);
    $sql = mg_investment_invitation_select_sql('i.public_id=?') . ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgInvestmentException('Investor invitation not found.', 404);
    return $row;
}

function mg_investment_invitation_row_by_token(PDO $pdo, string $token, bool $forUpdate = false): array
{
    mg_investment_invitation_require_schema($pdo);
    $hash = hash('sha256', mg_investment_invitation_token($token));
    $sql = mg_investment_invitation_select_sql('i.token_hash=?') . ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgInvestmentException('Investor invitation not found.', 404);
    return $row;
}

function mg_investment_invitation_public(array $row, bool $includeEmail = false): array
{
    $email = (string)($row['invited_email'] ?? '');
    return [
        'id' => (string)$row['public_id'],
        'status' => (string)$row['status'],
        'delivery_status' => (string)($row['delivery_status'] ?? 'not_sent'),
        'email' => $includeEmail ? $email : null,
        'email_masked' => mg_investment_invitation_mask_email($email),
        'contact_name' => $row['contact_name'] !== null ? (string)$row['contact_name'] : null,
        'firm_name' => $row['firm_name'] !== null ? (string)$row['firm_name'] : null,
        'investor_type' => (string)$row['investor_type'],
        'expected_investment_range' => (string)$row['expected_investment_range'],
        'round_id' => $row['round_public_id'] !== null ? (string)$row['round_public_id'] : null,
        'round_name' => $row['round_name'] !== null ? (string)$row['round_name'] : null,
        'personal_message' => $row['personal_message'] !== null ? (string)$row['personal_message'] : null,
        'inviter_name' => (string)($row['inviter_display_name'] ?? $row['inviter_name'] ?? 'Microgifter'),
        'request_id' => $row['request_public_id'] !== null ? (string)$row['request_public_id'] : null,
        'request_status' => $row['request_status'] !== null ? (string)$row['request_status'] : null,
        'accepted_name' => $row['accepted_display_name'] !== null ? (string)$row['accepted_display_name'] : ($row['accepted_name'] !== null ? (string)$row['accepted_name'] : null),
        'expires_at' => (string)$row['expires_at'],
        'sent_at' => $row['sent_at'] !== null ? (string)$row['sent_at'] : null,
        'last_sent_at' => $row['last_sent_at'] !== null ? (string)$row['last_sent_at'] : null,
        'first_viewed_at' => $row['first_viewed_at'] !== null ? (string)$row['first_viewed_at'] : null,
        'last_viewed_at' => $row['last_viewed_at'] !== null ? (string)$row['last_viewed_at'] : null,
        'view_count' => (int)$row['view_count'],
        'send_count' => (int)$row['send_count'],
        'accepted_at' => $row['accepted_at'] !== null ? (string)$row['accepted_at'] : null,
        'revoked_at' => $row['revoked_at'] !== null ? (string)$row['revoked_at'] : null,
        'revocation_reason' => $row['revocation_reason'] !== null ? (string)$row['revocation_reason'] : null,
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_investment_invitation_round_options(PDO $pdo): array
{
    try {
        return $pdo->query('SELECT public_id,public_name,status,visibility,target_close_at FROM investment_rounds WHERE status NOT IN ("cancelled") ORDER BY updated_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function mg_investment_invitation_admin_list(PDO $pdo, array $filters = []): array
{
    mg_investment_invitation_require_schema($pdo);
    mg_investment_invitation_expire_stale($pdo);
    $status = strtolower(trim((string)($filters['status'] ?? '')));
    $allowed = ['', 'created', 'sent', 'viewed', 'accepted', 'expired', 'revoked'];
    if (!in_array($status, $allowed, true)) throw new MgInvestmentException('Invalid invitation status filter.');
    $search = trim((string)($filters['q'] ?? ''));
    $params = [];
    $where = ['1=1'];
    if ($status !== '') {
        $where[] = 'i.status=?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[] = '(i.invited_email LIKE ? OR i.contact_name LIKE ? OR i.firm_name LIKE ?)';
        $needle = '%' . mb_substr($search, 0, 120) . '%';
        array_push($params, $needle, $needle, $needle);
    }
    $sql = mg_investment_invitation_select_sql(implode(' AND ', $where)) . ' ORDER BY i.created_at DESC LIMIT 250';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(static fn(array $row): array => mg_investment_invitation_public($row, true), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_investment_invitation_events(PDO $pdo, string $publicId): array
{
    $row = mg_investment_invitation_row_by_public_id($pdo, $publicId);
    $stmt = $pdo->prepare('SELECT e.event_type,e.details_json,e.created_at,u.full_name actor_name,u.display_name actor_display_name FROM investor_invitation_events e LEFT JOIN users u ON u.id=e.actor_user_id WHERE e.invitation_id=? ORDER BY e.id DESC LIMIT 100');
    $stmt->execute([(int)$row['id']]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
        $items[] = [
            'event_type' => (string)$event['event_type'],
            'details' => mg_investment_decode_json($event['details_json'] ?? null),
            'actor_name' => (string)($event['actor_display_name'] ?? $event['actor_name'] ?? 'System'),
            'created_at' => (string)$event['created_at'],
        ];
    }
    return $items;
}

function mg_investment_invitation_email(array $row, string $url): array
{
    $name = trim((string)($row['contact_name'] ?? '')) ?: 'there';
    $firm = trim((string)($row['firm_name'] ?? ''));
    $round = trim((string)($row['round_name'] ?? ''));
    $message = trim((string)($row['personal_message'] ?? ''));
    $body = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', you have been invited to complete Microgifter’s governed Investor onboarding process.</p>'
        . ($firm !== '' ? '<p style="margin:0 0 14px;color:#334155;font-size:15px;line-height:1.6;"><strong>Organization:</strong> ' . mg_mail_escape($firm) . '</p>' : '')
        . ($round !== '' ? '<p style="margin:0 0 14px;color:#334155;font-size:15px;line-height:1.6;"><strong>Round context:</strong> ' . mg_mail_escape($round) . '</p>' : '')
        . ($message !== '' ? '<p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">' . nl2br(mg_mail_escape($message)) . '</p>' : '')
        . mg_email_button($url, 'Review Investor invitation')
        . '<p style="margin:14px 0 0;color:#64748b;font-size:13px;line-height:1.6;">This private link is bound to the invited email address, expires at ' . mg_mail_escape((string)$row['expires_at']) . ', and does not grant Investor or securities access by itself.</p>';
    return [
        'subject' => 'Private Microgifter Investor invitation',
        'html' => mg_email_layout('Investor invitation', $body, 'Review your private Microgifter Investor invitation.'),
        'text' => "Hi {$name},\n\nYou have been invited to complete Microgifter Investor onboarding.\n\nReview invitation: {$url}\n\nThis link expires at {$row['expires_at']} and does not grant Investor or securities access by itself.",
    ];
}

function mg_investment_invitation_send_email(PDO $pdo, array $row, string $token, int $actorId): bool
{
    $url = mg_app_base_url() . '/investor-invitation.php?token=' . rawurlencode($token);
    $email = mg_investment_invitation_email($row, $url);
    $sent = mg_send_email((string)$row['invited_email'], $email['subject'], $email['html'], $email['text'], [
        'template' => 'investor_invitation',
        'invitation_id' => (string)$row['public_id'],
        'round_id' => $row['round_public_id'] ?? null,
    ]);
    $pdo->prepare('UPDATE investor_invitations SET delivery_status=?,status=CASE WHEN status="created" THEN "sent" ELSE status END,sent_at=COALESCE(sent_at,IF(?=1,NOW(),NULL)),last_sent_at=IF(?=1,NOW(),last_sent_at),send_count=send_count+1,updated_at=NOW() WHERE id=?')->execute([$sent ? 'sent' : 'failed',$sent ? 1 : 0,$sent ? 1 : 0,(int)$row['id']]);
    mg_investment_invitation_event($pdo, (int)$row['id'], $actorId, $sent ? 'email_sent' : 'email_failed', ['recipient' => mg_investment_invitation_mask_email((string)$row['invited_email'])]);
    return $sent;
}

function mg_investment_invitation_create(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor, 'admin.investor_access.manage');
    if (!mg_investment_is_super($actor)) throw new MgInvestmentException('Only a Super Admin can create Investor invitations.', 403);
    mg_investment_invitation_require_schema($pdo);
    $email = mg_investment_invitation_normalize_email($input['email'] ?? '');
    $contactName = mg_investment_optional_text($input['contact_name'] ?? null, 180, 'Contact name');
    $firmName = mg_investment_optional_text($input['firm_name'] ?? null, 180, 'Firm name');
    $investorType = mg_investment_enum($input['investor_type'] ?? 'individual', ['individual','angel','investment_firm','venture_fund','family_office','strategic_partner','company_entity','other'], 'Investor type');
    $range = mg_investment_enum($input['expected_investment_range'] ?? 'undecided', ['undecided','under_10k','10k_25k','25k_50k','50k_100k','100k_250k','over_250k'], 'Expected investment range');
    $message = mg_investment_optional_long_text($input['personal_message'] ?? null, 3000, 'Personal message');
    $days = max(1, min(30, (int)($input['expires_in_days'] ?? 7)));
    $roundId = null;
    $roundPublicId = trim((string)($input['round_id'] ?? ''));
    if ($roundPublicId !== '') {
        $round = mg_investment_round_by_public_id($pdo, $roundPublicId);
        $roundId = (int)$round['id'];
    }
    $active = $pdo->prepare("SELECT public_id FROM investor_invitations WHERE invited_email_hash=? AND status IN ('created','sent','viewed') AND expires_at>NOW() LIMIT 1");
    $active->execute([hash('sha256', $email)]);
    if ($active->fetchColumn()) throw new MgInvestmentException('An active Investor invitation already exists for this email.', 409);
    $existingUser = $pdo->prepare('SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id LEFT JOIN investor_profiles ip ON ip.user_id=u.id WHERE LOWER(u.email)=? AND r.slug="investor" AND ip.status="active" LIMIT 1');
    $existingUser->execute([$email]);
    if ($existingUser->fetchColumn()) throw new MgInvestmentException('This email already has active Investor access.', 409);
    $publicId = mg_investment_uuid();
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));
    $actorId = (int)$actor['id'];
    $stmt = $pdo->prepare('INSERT INTO investor_invitations (public_id,invited_email,invited_email_hash,contact_name,firm_name,investor_type,expected_investment_range,round_id,personal_message,status,delivery_status,token_hash,token_created_at,expires_at,invited_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,"created","not_sent",?,NOW(),?,?,NOW(),NOW())');
    $stmt->execute([$publicId,$email,hash('sha256',$email),$contactName,$firmName,$investorType,$range,$roundId,$message,$tokenHash,$expiresAt,$actorId]);
    $invitationId = (int)$pdo->lastInsertId();
    mg_investment_invitation_event($pdo, $invitationId, $actorId, 'created', ['expires_at' => $expiresAt, 'round_id' => $roundPublicId !== '' ? $roundPublicId : null]);
    $row = mg_investment_invitation_row_by_public_id($pdo, $publicId);
    $sent = mg_investment_invitation_send_email($pdo, $row, $token, $actorId);
    mg_audit('investor_invitation_created', 'investor_invitation', ['invitation_id' => $publicId, 'round_id' => $roundPublicId !== '' ? $roundPublicId : null, 'delivery_status' => $sent ? 'sent' : 'failed'], $actorId);
    $current = mg_investment_invitation_row_by_public_id($pdo, $publicId);
    return [
        'invitation' => mg_investment_invitation_public($current, true),
        'share_url' => mg_app_base_url() . '/investor-invitation.php?token=' . $token,
        'email_sent' => $sent,
    ];
}

function mg_investment_invitation_resend(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor, 'admin.investor_access.manage');
    if (!mg_investment_is_super($actor)) throw new MgInvestmentException('Only a Super Admin can resend Investor invitations.', 403);
    $publicId = mg_investment_text($input['invitation_id'] ?? '', 36, 36, 'Invitation identifier');
    $days = max(1, min(30, (int)($input['expires_in_days'] ?? 7)));
    $actorId = (int)$actor['id'];
    $pdo->beginTransaction();
    try {
        $row = mg_investment_invitation_row_by_public_id($pdo, $publicId, true);
        if (in_array((string)$row['status'], ['accepted','revoked'], true)) throw new MgInvestmentException('Accepted or revoked invitations cannot be resent.', 409);
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));
        $pdo->prepare("UPDATE investor_invitations SET token_hash=?,token_created_at=NOW(),expires_at=?,status='created',delivery_status='not_sent',first_viewed_at=NULL,last_viewed_at=NULL,view_count=0,updated_at=NOW() WHERE id=?")->execute([$tokenHash,$expiresAt,(int)$row['id']]);
        mg_investment_invitation_event($pdo, (int)$row['id'], $actorId, 'token_rotated', ['expires_at' => $expiresAt]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $row = mg_investment_invitation_row_by_public_id($pdo, $publicId);
    $sent = mg_investment_invitation_send_email($pdo, $row, $token, $actorId);
    mg_audit('investor_invitation_resent', 'investor_invitation', ['invitation_id' => $publicId, 'delivery_status' => $sent ? 'sent' : 'failed'], $actorId);
    return [
        'invitation' => mg_investment_invitation_public(mg_investment_invitation_row_by_public_id($pdo, $publicId), true),
        'share_url' => mg_app_base_url() . '/investor-invitation.php?token=' . $token,
        'email_sent' => $sent,
    ];
}

function mg_investment_invitation_revoke(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor, 'admin.investor_access.manage');
    if (!mg_investment_is_super($actor)) throw new MgInvestmentException('Only a Super Admin can revoke Investor invitations.', 403);
    $publicId = mg_investment_text($input['invitation_id'] ?? '', 36, 36, 'Invitation identifier');
    $reason = mg_investment_long_text($input['reason'] ?? '', 2000, 8, 'Revocation reason');
    $actorId = (int)$actor['id'];
    $pdo->beginTransaction();
    try {
        $row = mg_investment_invitation_row_by_public_id($pdo, $publicId, true);
        if ((string)$row['status'] === 'accepted') throw new MgInvestmentException('This invitation already entered the Investor Access review workflow. Decide the access request instead.', 409);
        if ((string)$row['status'] === 'revoked') throw new MgInvestmentException('Investor invitation is already revoked.', 409);
        $pdo->prepare("UPDATE investor_invitations SET status='revoked',revoked_by_user_id=?,revoked_at=NOW(),revocation_reason=?,updated_at=NOW() WHERE id=?")->execute([$actorId,$reason,(int)$row['id']]);
        mg_investment_invitation_event($pdo, (int)$row['id'], $actorId, 'revoked', ['reason' => $reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    mg_audit('investor_invitation_revoked', 'investor_invitation', ['invitation_id' => $publicId, 'reason' => $reason], $actorId);
    return mg_investment_invitation_public(mg_investment_invitation_row_by_public_id($pdo, $publicId), true);
}

function mg_investment_invitation_view(PDO $pdo, string $token, ?array $viewer = null, bool $recordView = true): array
{
    $row = mg_investment_invitation_row_by_token($pdo, $token, false);
    $status = (string)$row['status'];
    $activeStatuses = ['created', 'sent', 'viewed'];
    $expired = strtotime((string)$row['expires_at']) <= time();

    if ($expired && in_array($status, $activeStatuses, true)) {
        $pdo->prepare("UPDATE investor_invitations SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
        mg_investment_invitation_event($pdo, (int)$row['id'], $viewer ? (int)$viewer['id'] : null, 'expired');
        throw new MgInvestmentException('This Investor invitation has expired.', 410);
    }
    if ($status === 'accepted') {
        throw new MgInvestmentException('This Investor invitation has already been used.', 410);
    }
    if ($status === 'revoked') {
        throw new MgInvestmentException('This Investor invitation has been revoked.', 410);
    }
    if ($status === 'expired') {
        throw new MgInvestmentException('This Investor invitation has expired.', 410);
    }
    if (!in_array($status, $activeStatuses, true)) {
        throw new MgInvestmentException('This Investor invitation is no longer available.', 410);
    }

    if ($recordView) {
        $pdo->prepare("UPDATE investor_invitations SET status='viewed',first_viewed_at=COALESCE(first_viewed_at,NOW()),last_viewed_at=NOW(),view_count=view_count+1,updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
        mg_investment_invitation_event($pdo, (int)$row['id'], $viewer ? (int)$viewer['id'] : null, 'viewed', ['authenticated' => $viewer !== null]);
        $row = mg_investment_invitation_row_by_token($pdo, $token, false);
    }
    $viewerEmail = strtolower(trim((string)($viewer['email'] ?? '')));
    $matches = $viewerEmail !== '' && hash_equals((string)$row['invited_email_hash'], hash('sha256', $viewerEmail));
    $public = mg_investment_invitation_public($row, $matches);
    $public['actionable'] = true;
    $public['authenticated'] = $viewer !== null;
    $public['email_matches'] = $matches;
    return $public;
}

function mg_investment_invitation_prefill(PDO $pdo, string $token): ?array
{
    if (!mg_investment_invitation_tables_ready($pdo)) return null;
    try {
        $row = mg_investment_invitation_row_by_token($pdo, $token, false);
        if (!in_array((string)$row['status'], ['created','sent','viewed'], true) || strtotime((string)$row['expires_at']) <= time()) return null;
        return [
            'email' => (string)$row['invited_email'],
            'contact_name' => (string)($row['contact_name'] ?? ''),
            'firm_name' => (string)($row['firm_name'] ?? ''),
        ];
    } catch (Throwable) {
        return null;
    }
}

function mg_investment_invitation_accept(PDO $pdo, array $user, array $input): array
{
    mg_investment_invitation_require_schema($pdo);
    if (function_exists('mg_email_verification_gate_enabled') && mg_email_verification_gate_enabled() && empty($user['email_verified_at'])) {
        throw new MgInvestmentException('Verify your email before completing Investor onboarding.', 403);
    }
    $token = trim((string)($input['token'] ?? ''));
    $payload = mg_investment_access_payload($input);
    if (!mg_investment_bool($input['identity_acknowledgement'] ?? false)) {
        throw new MgInvestmentException('Confirm that the professional information is accurate.');
    }
    if (!mg_investment_bool($input['confidentiality_acknowledgement'] ?? false)) {
        throw new MgInvestmentException('Accept the private-information acknowledgement.');
    }
    $userId = (int)$user['id'];
    $email = strtolower(trim((string)$user['email']));
    $pdo->beginTransaction();
    try {
        $invitation = mg_investment_invitation_row_by_token($pdo, $token, true);
        if (!in_array((string)$invitation['status'], ['created','sent','viewed'], true)) {
            throw new MgInvestmentException('This Investor invitation is no longer available.', 409);
        }
        if (strtotime((string)$invitation['expires_at']) <= time()) {
            $pdo->prepare("UPDATE investor_invitations SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$invitation['id']]);
            mg_investment_invitation_event($pdo, (int)$invitation['id'], $userId, 'expired');
            $pdo->commit();
            throw new MgInvestmentException('This Investor invitation has expired.', 410);
        }
        if (!hash_equals((string)$invitation['invited_email_hash'], hash('sha256', $email))) {
            throw new MgInvestmentException('This invitation belongs to a different email address. Sign in with the invited account.', 403);
        }
        $current = mg_investment_find_access_request($pdo, $userId, true);
        if ($current && in_array((string)$current['status'], ['pending','more_information_requested','approved'], true)) {
            throw new MgInvestmentException('Your account already has an active Investor Access workflow.', 409);
        }
        if ($current && !(bool)$current['reapplication_allowed']) throw new MgInvestmentException('Reapplication is not currently allowed.', 409);
        if ($current && $current['reapplication_after'] !== null && strtotime((string)$current['reapplication_after']) > time()) {
            throw new MgInvestmentException('Reapplication is not available until ' . $current['reapplication_after'] . '.', 409);
        }
        $requestPublicId = mg_investment_uuid();
        $stmt = $pdo->prepare('INSERT INTO investor_access_requests (public_id,user_id,status,firm_name,job_title,website_url,primary_social_url,linkedin_url,additional_social_url,investor_type,expected_investment_range,referral_source,phone,request_reason,acknowledgement_at,requested_at,created_at,updated_at) VALUES (?,? ,"pending",?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW(),NOW())');
        $stmt->execute([$requestPublicId,$userId,$payload['firm_name'],$payload['job_title'] ?: null,$payload['website_url'],$payload['primary_social_url'],$payload['linkedin_url'],$payload['additional_social_url'],$payload['investor_type'],$payload['expected_investment_range'],$payload['referral_source'] ?: 'Super Admin invitation',$payload['phone'] ?: null,$payload['request_reason']]);
        $requestId = (int)$pdo->lastInsertId();
        mg_investment_event($pdo, $requestId, $userId, 'invitation_onboarding_submitted', ['invitation_id' => (string)$invitation['public_id'], 'round_id' => $invitation['round_public_id'] ?? null]);
        $pdo->prepare("UPDATE investor_invitations SET status='accepted',accepted_by_user_id=?,accepted_at=NOW(),request_id=?,disclosure_version=?,disclosure_accepted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$userId,$requestId,MG_INVESTOR_INVITATION_DISCLOSURE_VERSION,(int)$invitation['id']]);
        mg_investment_invitation_event($pdo, (int)$invitation['id'], $userId, 'accepted', ['request_id' => $requestPublicId, 'disclosure_version' => MG_INVESTOR_INVITATION_DISCLOSURE_VERSION]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    mg_audit('investor_invitation_accepted', 'investor_invitation', ['invitation_id' => (string)$invitation['public_id'], 'request_id' => $requestPublicId], $userId);
    mg_event('investment.invitation.accepted', ['invitation_id' => (string)$invitation['public_id'], 'request_id' => $requestPublicId], $userId);
    $request = mg_investment_find_access_request($pdo, $userId);
    return ['request' => $request ? mg_investment_access_public($request) : null, 'redirect' => '/investor-access.php?source=invitation'];
}

function mg_investment_invitation_enrich_access_items(PDO $pdo, array $items): array
{
    if ($items === [] || !mg_investment_invitation_tables_ready($pdo)) return $items;
    $requestIds = [];
    foreach ($items as $item) {
        $id = (string)($item['id'] ?? '');
        if ($id !== '') $requestIds[$id] = true;
    }
    if ($requestIds === []) return $items;
    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
    $stmt = $pdo->prepare("SELECT i.public_id invitation_public_id,i.request_id,i.invited_email,i.contact_name,i.firm_name,i.round_id,i.accepted_at,r.public_id round_public_id,r.public_name round_name,ar.public_id request_public_id FROM investor_invitations i INNER JOIN investor_access_requests ar ON ar.id=i.request_id LEFT JOIN investment_rounds r ON r.id=i.round_id WHERE ar.public_id IN ({$placeholders})");
    $stmt->execute(array_keys($requestIds));
    $byRequest = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $byRequest[(string)$row['request_public_id']] = $row;
    foreach ($items as &$item) {
        $requestId = (string)($item['id'] ?? '');
        if (!isset($byRequest[$requestId])) {
            $item['source'] = 'profile_request';
            $item['invitation'] = null;
            continue;
        }
        $row = $byRequest[$requestId];
        $item['source'] = 'admin_invitation';
        $item['invitation'] = [
            'id' => (string)$row['invitation_public_id'],
            'email' => (string)$row['invited_email'],
            'contact_name' => $row['contact_name'] !== null ? (string)$row['contact_name'] : null,
            'firm_name' => $row['firm_name'] !== null ? (string)$row['firm_name'] : null,
            'round_id' => $row['round_public_id'] !== null ? (string)$row['round_public_id'] : null,
            'round_name' => $row['round_name'] !== null ? (string)$row['round_name'] : null,
            'accepted_at' => $row['accepted_at'] !== null ? (string)$row['accepted_at'] : null,
        ];
    }
    unset($item);
    return $items;
}
