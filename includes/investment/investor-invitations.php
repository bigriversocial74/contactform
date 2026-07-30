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
        throw new MgInvestmentException('Enter a valid invitation email address.');
    }
    return $email;
}

function mg_investment_invitation_mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') return 'Private recipient';
    $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local))));
    return $visible . str_repeat('•', max(3, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
}

function mg_investment_invitation_token(): string
{
    return bin2hex(random_bytes(32));
}

function mg_investment_invitation_token_hash(string $token): string
{
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new MgInvestmentException('Investor invitation link is invalid.', 404);
    }
    return hash('sha256', $token);
}

function mg_investment_invitation_url(string $token): string
{
    return mg_app_base_url() . '/investor-invitation.php?token=' . rawurlencode($token);
}

function mg_investment_invitation_event(PDO $pdo, int $invitationId, ?int $actorUserId, string $eventType, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO investor_invitation_events (invitation_id,actor_user_id,event_type,details_json,created_at) VALUES (?,?,?,?,NOW())');
    $stmt->execute([$invitationId, $actorUserId, $eventType, $details ? mg_investment_json_encode($details) : null]);
}

function mg_investment_invitation_expire_stale(PDO $pdo): void
{
    if (!mg_investment_invitation_tables_ready($pdo)) return;
    $pdo->exec("UPDATE investor_invitations SET status='expired',updated_at=NOW() WHERE status IN ('created','sent','viewed') AND expires_at<=NOW()");
}

function mg_investment_invitation_row_by_public_id(PDO $pdo, string $publicId, bool $lock = false): array
{
    mg_investment_invitation_require_schema($pdo);
    $sql = 'SELECT i.*,r.public_id AS round_public_id,r.public_name AS round_name,
                   inviter.full_name AS inviter_name,inviter.display_name AS inviter_display_name,
                   accepted.full_name AS accepted_name,accepted.display_name AS accepted_display_name,accepted.email AS accepted_email,
                   req.public_id AS request_public_id,req.status AS request_status
            FROM investor_invitations i
            LEFT JOIN investment_rounds r ON r.id=i.round_id
            INNER JOIN users inviter ON inviter.id=i.invited_by_user_id
            LEFT JOIN users accepted ON accepted.id=i.accepted_by_user_id
            LEFT JOIN investor_access_requests req ON req.id=i.request_id
            WHERE i.public_id=? LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgInvestmentException('Investor invitation not found.', 404);
    return $row;
}

function mg_investment_invitation_row_by_token(PDO $pdo, string $token, bool $lock = false): array
{
    mg_investment_invitation_require_schema($pdo);
    $hash = mg_investment_invitation_token_hash($token);
    $sql = 'SELECT i.*,r.public_id AS round_public_id,r.public_name AS round_name,
                   inviter.full_name AS inviter_name,inviter.display_name AS inviter_display_name,
                   accepted.full_name AS accepted_name,accepted.display_name AS accepted_display_name,accepted.email AS accepted_email,
                   req.public_id AS request_public_id,req.status AS request_status
            FROM investor_invitations i
            LEFT JOIN investment_rounds r ON r.id=i.round_id
            INNER JOIN users inviter ON inviter.id=i.invited_by_user_id
            LEFT JOIN users accepted ON accepted.id=i.accepted_by_user_id
            LEFT JOIN investor_access_requests req ON req.id=i.request_id
            WHERE i.token_hash=? LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgInvestmentException('Investor invitation link is invalid.', 404);
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
        $where[] = '(i.invited_email LIKE ? OR i.contact_name LIKE ? OR i.firm_name LIKE ? OR r.public_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $sql = 'SELECT i.*,r.public_id AS round_public_id,r.public_name AS round_name,
                   inviter.full_name AS inviter_name,inviter.display_name AS inviter_display_name,
                   accepted.full_name AS accepted_name,accepted.display_name AS accepted_display_name,accepted.email AS accepted_email,
                   req.public_id AS request_public_id,req.status AS request_status
            FROM investor_invitations i
            LEFT JOIN investment_rounds r ON r.id=i.round_id
            INNER JOIN users inviter ON inviter.id=i.invited_by_user_id
            LEFT JOIN users accepted ON accepted.id=i.accepted_by_user_id
            LEFT JOIN investor_access_requests req ON req.id=i.request_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY FIELD(i.status,"created","sent","viewed","accepted","expired","revoked"),i.created_at DESC LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(static fn(array $row): array => mg_investment_invitation_public($row, true), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_investment_invitation_payload(PDO $pdo, array $input): array
{
    $email = mg_investment_invitation_normalize_email($input['email'] ?? '');
    $investorTypes = ['individual','angel','investment_firm','venture_fund','family_office','strategic_partner','company_entity','other'];
    $ranges = ['undecided','under_10k','10k_25k','25k_50k','50k_100k','100k_250k','over_250k'];
    $type = strtolower(mg_investment_text($input['investor_type'] ?? 'individual', 60, 1, 'Investor type'));
    $range = strtolower(mg_investment_text($input['expected_investment_range'] ?? 'undecided', 60, 1, 'Expected investment range'));
    if (!in_array($type, $investorTypes, true) || !in_array($range, $ranges, true)) {
        throw new MgInvestmentException('Invalid investor type or expected range.');
    }
    $roundId = null;
    $roundPublicId = trim((string)($input['round_id'] ?? ''));
    if ($roundPublicId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM investment_rounds WHERE public_id=? AND status<>"cancelled" LIMIT 1');
        $stmt->execute([$roundPublicId]);
        $roundId = (int)$stmt->fetchColumn();
        if ($roundId < 1) throw new MgInvestmentException('Selected funding round is unavailable.');
    }
    $days = max(1, min(60, (int)($input['expires_in_days'] ?? 14)));
    return [
        'email' => $email,
        'email_hash' => hash('sha256', $email),
        'contact_name' => mg_investment_text($input['contact_name'] ?? '', 180),
        'firm_name' => mg_investment_text($input['firm_name'] ?? '', 180),
        'investor_type' => $type,
        'expected_investment_range' => $range,
        'round_id' => $roundId,
        'personal_message' => mg_investment_long_text($input['personal_message'] ?? '', 4000),
        'expires_at' => date('Y-m-d H:i:s', time() + ($days * 86400)),
        'expires_in_days' => $days,
        'send_email' => mg_investment_bool($input['send_email'] ?? true),
    ];
}

function mg_investment_invitation_assert_account_available(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('SELECT u.id,ip.status AS investor_profile_status,
        (SELECT iar.status FROM investor_access_requests iar WHERE iar.user_id=u.id ORDER BY iar.id DESC LIMIT 1) AS request_status
        FROM users u LEFT JOIN investor_profiles ip ON ip.user_id=u.id WHERE LOWER(u.email)=? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;
    if ((string)($row['investor_profile_status'] ?? '') === 'active') {
        throw new MgInvestmentException('This email already belongs to an active Investor account.', 409);
    }
    if (in_array((string)($row['request_status'] ?? ''), ['pending','more_information_requested','approved'], true)) {
        throw new MgInvestmentException('This account already has an active Investor Access workflow. Review that request instead of sending a new invitation.', 409);
    }
}

function mg_investment_invitation_email(array $row, string $inviteUrl): array
{
    $name = trim((string)($row['contact_name'] ?? '')) ?: 'there';
    $inviter = trim((string)($row['inviter_display_name'] ?? $row['inviter_name'] ?? 'Microgifter')) ?: 'Microgifter';
    $round = trim((string)($row['round_name'] ?? ''));
    $message = trim((string)($row['personal_message'] ?? ''));
    $body = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', ' . mg_mail_escape($inviter) . ' invited you to begin Microgifter’s governed Investor onboarding process.</p>'
        . ($round !== '' ? '<p style="margin:0 0 16px;color:#071225;font-size:16px;line-height:1.6;"><strong>Round context:</strong> ' . mg_mail_escape($round) . '</p>' : '')
        . ($message !== '' ? '<div style="margin:0 0 16px;padding:16px 18px;border-radius:16px;background:#f4f7fb;color:#334155;font-size:15px;line-height:1.6;">' . nl2br(mg_mail_escape($message)) . '</div>' : '')
        . mg_email_button($inviteUrl, 'Continue Investor onboarding')
        . '<p style="margin:0 0 10px;color:#64748b;font-size:13px;line-height:1.6;">Create or sign in to a Microgifter account using this same email address. You will complete professional information and disclosures before a Super Admin reviews the request.</p>'
        . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">This invitation does not create an investment commitment, allocation, approval, securities offer, or automatic Data Room access.</p>';
    return [
        'subject' => 'You are invited to Microgifter Investor onboarding',
        'html' => mg_email_layout('Investor onboarding invitation', $body, 'Complete Microgifter Investor onboarding.'),
        'text' => "Hi {$name},\n\n{$inviter} invited you to begin Microgifter Investor onboarding.\n\nContinue: {$inviteUrl}\n\nThis invitation does not create an investment commitment, allocation, approval, securities offer, or automatic Data Room access.",
    ];
}

function mg_investment_invitation_deliver(PDO $pdo, int $invitationId, string $inviteUrl): bool
{
    $stmt = $pdo->prepare('SELECT i.*,r.public_name AS round_name,inviter.full_name AS inviter_name,inviter.display_name AS inviter_display_name FROM investor_invitations i LEFT JOIN investment_rounds r ON r.id=i.round_id INNER JOIN users inviter ON inviter.id=i.invited_by_user_id WHERE i.id=? LIMIT 1');
    $stmt->execute([$invitationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgInvestmentException('Investor invitation not found.', 404);
    $email = mg_investment_invitation_email($row, $inviteUrl);
    $sent = mg_send_email((string)$row['invited_email'], $email['subject'], $email['html'], $email['text'], [
        'template' => 'investor_invitation',
        'invitation_id' => (string)$row['public_id'],
        'round_id' => $row['round_id'] !== null ? (int)$row['round_id'] : null,
    ]);
    if ($sent) {
        $pdo->prepare("UPDATE investor_invitations SET status='sent',delivery_status='sent',sent_at=COALESCE(sent_at,NOW()),last_sent_at=NOW(),send_count=send_count+1,updated_at=NOW() WHERE id=? AND status IN ('created','sent','viewed','expired')")->execute([$invitationId]);
        mg_investment_invitation_event($pdo, $invitationId, null, 'email_sent', ['provider' => mg_mail_provider()]);
    } else {
        $pdo->prepare("UPDATE investor_invitations SET delivery_status='failed',updated_at=NOW() WHERE id=?")->execute([$invitationId]);
        mg_investment_invitation_event($pdo, $invitationId, null, 'email_failed', ['provider' => mg_mail_provider()]);
    }
    return $sent;
}

function mg_investment_invitation_create(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor, 'admin.investor_access.manage');
    if (!mg_investment_is_super($actor)) throw new MgInvestmentException('Only a Super Admin can send Investor invitations.', 403);
    mg_investment_invitation_require_schema($pdo);
    $payload = mg_investment_invitation_payload($pdo, $input);
    mg_investment_invitation_assert_account_available($pdo, $payload['email']);
    $token = mg_investment_invitation_token();
    $inviteUrl = mg_investment_invitation_url($token);
    $publicId = mg_investment_uuid();
    $actorId = (int)$actor['id'];
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare("SELECT id FROM investor_invitations WHERE invited_email_hash=? AND status IN ('created','sent','viewed') AND expires_at>NOW() LIMIT 1 FOR UPDATE");
        $existing->execute([$payload['email_hash']]);
        if ($existing->fetchColumn()) throw new MgInvestmentException('An active invitation already exists for this email. Resend or revoke the existing invitation.', 409);
        $stmt = $pdo->prepare("INSERT INTO investor_invitations (public_id,invited_email,invited_email_hash,contact_name,firm_name,investor_type,expected_investment_range,round_id,personal_message,status,delivery_status,token_hash,token_created_at,expires_at,invited_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'created','not_sent',?,NOW(),?,?,NOW(),NOW())");
        $stmt->execute([$publicId,$payload['email'],$payload['email_hash'],$payload['contact_name'] ?: null,$payload['firm_name'] ?: null,$payload['investor_type'],$payload['expected_investment_range'],$payload['round_id'],$payload['personal_message'] ?: null,hash('sha256',$token),$payload['expires_at'],$actorId]);
        $invitationId = (int)$pdo->lastInsertId();
        mg_investment_invitation_event($pdo, $invitationId, $actorId, 'created', ['expires_at' => $payload['expires_at'], 'round_id' => $payload['round_id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $delivered = $payload['send_email'] ? mg_investment_invitation_deliver($pdo, $invitationId, $inviteUrl) : false;
    mg_audit('investor_invitation_created', 'investor_invitation', ['invitation_id' => $publicId, 'delivery_status' => $delivered ? 'sent' : ($payload['send_email'] ? 'failed' : 'not_sent')], $actorId);
    mg_event('investment.invitation.created', ['invitation_id' => $publicId, 'delivery_status' => $delivered ? 'sent' : ($payload['send_email'] ? 'failed' : 'not_sent')], $actorId);
    $row = mg_investment_invitation_row_by_public_id($pdo, $publicId);
    return ['invitation' => mg_investment_invitation_public($row, true), 'invite_url' => $inviteUrl, 'delivered' => $delivered];
}

function mg_investment_invitation_resend(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor, 'admin.investor_access.manage');
    if (!mg_investment_is_super($actor)) throw new MgInvestmentException('Only a Super Admin can resend Investor invitations.', 403);
    $publicId = mg_investment_text($input['invitation_id'] ?? '', 36, 36, 'Invitation identifier');
    $days = max(1, min(60, (int)($input['expires_in_days'] ?? 14)));
    $token = mg_investment_invitation_token();
    $inviteUrl = mg_investment_invitation_url($token);
    $actorId = (int)$actor['id'];
    $pdo->beginTransaction();
    try {
        $row = mg_investment_invitation_row_by_public_id($pdo, $publicId, true);
        if (in_array((string)$row['status'], ['accepted','revoked'], true)) {
            throw new MgInvestmentException('Accepted or revoked invitations cannot be resent.', 409);
        }
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));
        $pdo->prepare("UPDATE investor_invitations SET status='created',delivery_status='not_sent',token_hash=?,token_created_at=NOW(),expires_at=?,revoked_by_user_id=NULL,revoked_at=NULL,revocation_reason=NULL,updated_at=NOW() WHERE id=?")->execute([hash('sha256',$token),$expiresAt,(int)$row['id']]);
        mg_investment_invitation_event($pdo, (int)$row['id'], $actorId, 'resent', ['expires_at' => $expiresAt]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $delivered = mg_investment_invitation_deliver($pdo, (int)$row['id'], $inviteUrl);
    mg_audit('investor_invitation_resent', 'investor_invitation', ['invitation_id' => $publicId, 'delivered' => $delivered], $actorId);
    return ['invitation' => mg_investment_invitation_public(mg_investment_invitation_row_by_public_id($pdo, $publicId), true), 'invite_url' => $inviteUrl, 'delivered' => $delivered];
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
    $now = time();
    $expired = strtotime((string)$row['expires_at']) <= $now;
    if ($expired && in_array((string)$row['status'], ['created','sent','viewed'], true)) {
        $pdo->prepare("UPDATE investor_invitations SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
        $row['status'] = 'expired';
        mg_investment_invitation_event($pdo, (int)$row['id'], $viewer ? (int)$viewer['id'] : null, 'expired');
    }
    if ($recordView && !$expired && in_array((string)$row['status'], ['created','sent','viewed'], true)) {
        $pdo->prepare("UPDATE investor_invitations SET status='viewed',first_viewed_at=COALESCE(first_viewed_at,NOW()),last_viewed_at=NOW(),view_count=view_count+1,updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
        mg_investment_invitation_event($pdo, (int)$row['id'], $viewer ? (int)$viewer['id'] : null, 'viewed', ['authenticated' => $viewer !== null]);
        $row = mg_investment_invitation_row_by_token($pdo, $token, false);
    }
    $viewerEmail = strtolower(trim((string)($viewer['email'] ?? '')));
    $matches = $viewerEmail !== '' && hash_equals((string)$row['invited_email_hash'], hash('sha256', $viewerEmail));
    $public = mg_investment_invitation_public($row, $matches);
    $public['actionable'] = in_array((string)$row['status'], ['created','sent','viewed'], true) && !$expired;
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
    $ids = array_values(array_filter(array_map(static fn(array $item): string => (string)($item['id'] ?? ''), $items)));
    if ($ids === []) return $items;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT req.public_id AS request_public_id,i.public_id AS invitation_public_id,i.round_id,r.public_name AS round_name FROM investor_invitations i INNER JOIN investor_access_requests req ON req.id=i.request_id LEFT JOIN investment_rounds r ON r.id=i.round_id WHERE req.public_id IN (' . $placeholders . ')');
    $stmt->execute($ids);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $map[(string)$row['request_public_id']] = $row;
    foreach ($items as &$item) {
        $context = $map[(string)($item['id'] ?? '')] ?? null;
        $item['source'] = $context ? 'admin_invitation' : 'profile_request';
        $item['invitation_id'] = $context ? (string)$context['invitation_public_id'] : null;
        $item['invitation_round_name'] = $context && $context['round_name'] !== null ? (string)$context['round_name'] : null;
    }
    unset($item);
    return $items;
}
