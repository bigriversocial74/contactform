<?php
declare(strict_types=1);

final class MgInvestmentException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

function mg_investment_uuid(): string
{
    return function_exists('mg_public_uuid') ? mg_public_uuid() : sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(6))
    );
}

function mg_investment_json(mixed $value, array $fallback = []): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return $fallback;
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function mg_investment_json_encode(array $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) throw new MgInvestmentException('Unable to encode investment data.', 500);
    return $json;
}

function mg_investment_text(mixed $value, int $max = 500, int $min = 0, string $label = 'Value'): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $length = mb_strlen($text);
    if ($length < $min || $length > $max) {
        throw new MgInvestmentException($label . ' must be between ' . $min . ' and ' . $max . ' characters.');
    }
    return $text;
}

function mg_investment_long_text(mixed $value, int $max = 12000, int $min = 0, string $label = 'Value'): string
{
    $text = trim((string)$value);
    $length = mb_strlen($text);
    if ($length < $min || $length > $max) {
        throw new MgInvestmentException($label . ' must be between ' . $min . ' and ' . $max . ' characters.');
    }
    return $text;
}

function mg_investment_money(mixed $value): int
{
    $raw = trim((string)$value);
    if ($raw === '') return 0;
    $raw = str_replace([',', '$', ' '], '', $raw);
    if (!is_numeric($raw)) throw new MgInvestmentException('Enter a valid money amount.');
    return max(0, (int)round(((float)$raw) * 100));
}

function mg_investment_bps(mixed $value, int $max = 10000): int
{
    $raw = trim((string)$value);
    if ($raw === '') return 0;
    if (!is_numeric($raw)) throw new MgInvestmentException('Enter a valid percentage.');
    return max(0, min($max, (int)round(((float)$raw) * 100)));
}

function mg_investment_bool(mixed $value): bool
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function mg_investment_date(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $time = strtotime($value);
    if ($time === false) throw new MgInvestmentException('Enter a valid date.');
    return date('Y-m-d H:i:s', $time);
}

function mg_investment_url(mixed $value, bool $required = false): ?string
{
    $url = trim((string)$value);
    if ($url === '') {
        if ($required) throw new MgInvestmentException('A valid URL is required.');
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true)) {
        throw new MgInvestmentException('Enter a valid http or https URL.');
    }
    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP)) {
        throw new MgInvestmentException('Local or private-network URLs are not allowed.');
    }
    return mb_substr($url, 0, 500);
}

function mg_investment_has_permission(array $user, string $permission): bool
{
    if (in_array('super_admin', is_array($user['roles'] ?? null) ? $user['roles'] : [], true)) return true;
    if (function_exists('mg_api_user_has_permission')) return mg_api_user_has_permission($user, $permission);
    return in_array($permission, is_array($user['permissions'] ?? null) ? $user['permissions'] : [], true);
}

function mg_investment_require_permission(array $user, string $permission): void
{
    if (!mg_investment_has_permission($user, $permission)) throw new MgInvestmentException('Permission denied.', 403);
}

function mg_investment_is_super(array $user): bool
{
    return in_array('super_admin', is_array($user['roles'] ?? null) ? $user['roles'] : [], true);
}

function mg_investment_event(PDO $pdo, int $requestId, ?int $actorUserId, string $eventType, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO investor_access_events (request_id,actor_user_id,event_type,details_json,created_at) VALUES (?,?,?,?,NOW())');
    $stmt->execute([$requestId, $actorUserId, $eventType, mg_investment_json_encode($details)]);
}

function mg_investment_find_access_request(PDO $pdo, int $userId, bool $lock = false): ?array
{
    $sql = 'SELECT * FROM investor_access_requests WHERE user_id=? ORDER BY id DESC LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_investment_access_public(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'user_id' => (int)$row['user_id'],
        'status' => (string)$row['status'],
        'firm_name' => (string)$row['firm_name'],
        'job_title' => $row['job_title'] !== null ? (string)$row['job_title'] : null,
        'website_url' => $row['website_url'] !== null ? (string)$row['website_url'] : null,
        'primary_social_url' => (string)$row['primary_social_url'],
        'linkedin_url' => $row['linkedin_url'] !== null ? (string)$row['linkedin_url'] : null,
        'additional_social_url' => $row['additional_social_url'] !== null ? (string)$row['additional_social_url'] : null,
        'investor_type' => (string)$row['investor_type'],
        'expected_investment_range' => (string)$row['expected_investment_range'],
        'referral_source' => $row['referral_source'] !== null ? (string)$row['referral_source'] : null,
        'phone' => $row['phone'] !== null ? (string)$row['phone'] : null,
        'request_reason' => (string)$row['request_reason'],
        'more_information_message' => $row['more_information_message'] !== null ? (string)$row['more_information_message'] : null,
        'review_notes' => $row['review_notes'] !== null ? (string)$row['review_notes'] : null,
        'reapplication_allowed' => (bool)$row['reapplication_allowed'],
        'reapplication_after' => $row['reapplication_after'] !== null ? (string)$row['reapplication_after'] : null,
        'requested_at' => (string)$row['requested_at'],
        'reviewed_at' => $row['reviewed_at'] !== null ? (string)$row['reviewed_at'] : null,
        'revoked_at' => $row['revoked_at'] !== null ? (string)$row['revoked_at'] : null,
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_investment_access_payload(array $input): array
{
    $investorTypes = ['individual','angel','investment_firm','venture_fund','family_office','strategic_partner','company_entity','other'];
    $ranges = ['undecided','under_10k','10k_25k','25k_50k','50k_100k','100k_250k','over_250k'];
    $type = strtolower(mg_investment_text($input['investor_type'] ?? 'individual', 60, 1, 'Investor type'));
    $range = strtolower(mg_investment_text($input['expected_investment_range'] ?? 'undecided', 60, 1, 'Expected investment range'));
    if (!in_array($type, $investorTypes, true) || !in_array($range, $ranges, true)) throw new MgInvestmentException('Invalid investor type or investment range.');
    if (!mg_investment_bool($input['acknowledgement'] ?? false)) throw new MgInvestmentException('You must accept the non-commitment acknowledgement.');
    return [
        'firm_name' => mg_investment_text($input['firm_name'] ?? '', 180, 2, 'Firm or organization name'),
        'job_title' => mg_investment_text($input['job_title'] ?? '', 160),
        'website_url' => mg_investment_url($input['website_url'] ?? ''),
        'primary_social_url' => mg_investment_url($input['primary_social_url'] ?? '', true),
        'linkedin_url' => mg_investment_url($input['linkedin_url'] ?? ''),
        'additional_social_url' => mg_investment_url($input['additional_social_url'] ?? ''),
        'investor_type' => $type,
        'expected_investment_range' => $range,
        'referral_source' => mg_investment_text($input['referral_source'] ?? '', 180),
        'phone' => mg_investment_text($input['phone'] ?? '', 60),
        'request_reason' => mg_investment_long_text($input['request_reason'] ?? '', 4000, 20, 'Reason for requesting access'),
    ];
}

function mg_investment_submit_access_request(PDO $pdo, array $user, array $input): array
{
    $payload = mg_investment_access_payload($input);
    $userId = (int)$user['id'];
    $pdo->beginTransaction();
    try {
        $current = mg_investment_find_access_request($pdo, $userId, true);
        if ($current && in_array((string)$current['status'], ['pending','approved'], true)) {
            throw new MgInvestmentException($current['status'] === 'approved' ? 'Investor access is already active.' : 'An investor-access request is already pending.', 409);
        }
        if ($current && !$current['reapplication_allowed']) throw new MgInvestmentException('Reapplication is not currently allowed.', 409);
        if ($current && $current['reapplication_after'] !== null && strtotime((string)$current['reapplication_after']) > time()) {
            throw new MgInvestmentException('Reapplication is not available until ' . $current['reapplication_after'] . '.', 409);
        }
        $publicId = mg_investment_uuid();
        $stmt = $pdo->prepare('INSERT INTO investor_access_requests (public_id,user_id,status,firm_name,job_title,website_url,primary_social_url,linkedin_url,additional_social_url,investor_type,expected_investment_range,referral_source,phone,request_reason,acknowledgement_at,requested_at,created_at,updated_at) VALUES (?,? ,"pending",?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW(),NOW())');
        $stmt->execute([$publicId,$userId,$payload['firm_name'],$payload['job_title'] ?: null,$payload['website_url'],$payload['primary_social_url'],$payload['linkedin_url'],$payload['additional_social_url'],$payload['investor_type'],$payload['expected_investment_range'],$payload['referral_source'] ?: null,$payload['phone'] ?: null,$payload['request_reason']]);
        $requestId = (int)$pdo->lastInsertId();
        mg_investment_event($pdo,$requestId,$userId,'submitted',['firm_name'=>$payload['firm_name'],'investor_type'=>$payload['investor_type']]);
        $pdo->commit();
        mg_audit('investor_access_requested','investor_access',['request_id'=>$publicId],$userId);
        mg_event('investment.access.requested',['request_id'=>$publicId],$userId);
        return mg_investment_access_public(mg_investment_find_access_request($pdo,$userId) ?? []);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_investment_withdraw_access_request(PDO $pdo, array $user): array
{
    $userId = (int)$user['id'];
    $pdo->beginTransaction();
    try {
        $current = mg_investment_find_access_request($pdo,$userId,true);
        if (!$current || !in_array((string)$current['status'], ['pending','more_information_requested'], true)) throw new MgInvestmentException('There is no withdrawable request.',409);
        $pdo->prepare('UPDATE investor_access_requests SET status="withdrawn",updated_at=NOW() WHERE id=?')->execute([(int)$current['id']]);
        mg_investment_event($pdo,(int)$current['id'],$userId,'withdrawn');
        $pdo->commit();
        mg_audit('investor_access_withdrawn','investor_access',['request_id'=>$current['public_id']],$userId);
        return mg_investment_access_public(mg_investment_find_access_request($pdo,$userId) ?? []);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_investment_admin_request(PDO $pdo, string $publicId, bool $lock = false): array
{
    $sql = 'SELECT iar.*,u.email,u.full_name,u.display_name FROM investor_access_requests iar INNER JOIN users u ON u.id=iar.user_id WHERE iar.public_id=? LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgInvestmentException('Investor-access request not found.',404);
    return $row;
}

function mg_investment_role_id(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT id FROM roles WHERE slug="investor" LIMIT 1');
    $id = (int)$stmt->fetchColumn();
    if ($id < 1) throw new MgInvestmentException('Investor role is not installed.',500);
    return $id;
}

function mg_investment_admin_decide_access(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_access.manage');
    if (!mg_investment_is_super($actor)) throw new MgInvestmentException('Only a super administrator can approve or revoke Investor access.',403);
    $publicId = mg_investment_text($input['request_id'] ?? '', 36, 36, 'Request identifier');
    $action = strtolower(mg_investment_text($input['action'] ?? '',60,1,'Action'));
    $allowed = ['approve','deny','request_information','revoke','allow_reapplication'];
    if (!in_array($action,$allowed,true)) throw new MgInvestmentException('Invalid review action.');
    $notes = mg_investment_long_text($input['notes'] ?? '',4000,$action === 'approve' ? 0 : 8,'Review notes');
    $actorId = (int)$actor['id'];
    $pdo->beginTransaction();
    try {
        $request = mg_investment_admin_request($pdo,$publicId,true);
        $requestId = (int)$request['id'];
        $targetUserId = (int)$request['user_id'];
        if ($action === 'approve') {
            $roleId = mg_investment_role_id($pdo);
            $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id,created_at) VALUES (?,?,NOW())')->execute([$targetUserId,$roleId]);
            $pdo->prepare('UPDATE investor_access_requests SET status="approved",reviewed_by_user_id=?,reviewed_at=NOW(),review_notes=?,more_information_message=NULL,reapplication_allowed=1,reapplication_after=NULL,updated_at=NOW() WHERE id=?')->execute([$actorId,$notes ?: null,$requestId]);
            $profile = $pdo->prepare('INSERT INTO investor_profiles (public_id,user_id,source_request_id,status,firm_name,job_title,website_url,primary_social_url,investor_type,expected_investment_range,approved_by_user_id,approved_at,created_at,updated_at) VALUES (?,?,?,"active",?,?,?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE source_request_id=VALUES(source_request_id),status="active",firm_name=VALUES(firm_name),job_title=VALUES(job_title),website_url=VALUES(website_url),primary_social_url=VALUES(primary_social_url),investor_type=VALUES(investor_type),expected_investment_range=VALUES(expected_investment_range),approved_by_user_id=VALUES(approved_by_user_id),approved_at=NOW(),updated_at=NOW()');
            $profile->execute([mg_investment_uuid(),$targetUserId,$requestId,$request['firm_name'],$request['job_title'],$request['website_url'],$request['primary_social_url'],$request['investor_type'],$request['expected_investment_range'],$actorId]);
        } elseif ($action === 'request_information') {
            $pdo->prepare('UPDATE investor_access_requests SET status="more_information_requested",reviewed_by_user_id=?,reviewed_at=NOW(),more_information_message=?,review_notes=?,updated_at=NOW() WHERE id=?')->execute([$actorId,$notes,$notes,$requestId]);
        } elseif ($action === 'deny') {
            $allowedAgain = mg_investment_bool($input['reapplication_allowed'] ?? true);
            $after = mg_investment_date($input['reapplication_after'] ?? null);
            $pdo->prepare('UPDATE investor_access_requests SET status="denied",reviewed_by_user_id=?,reviewed_at=NOW(),review_notes=?,reapplication_allowed=?,reapplication_after=?,updated_at=NOW() WHERE id=?')->execute([$actorId,$notes,$allowedAgain ? 1 : 0,$after,$requestId]);
        } elseif ($action === 'revoke') {
            $roleId = mg_investment_role_id($pdo);
            $pdo->prepare('DELETE FROM user_roles WHERE user_id=? AND role_id=?')->execute([$targetUserId,$roleId]);
            $pdo->prepare('UPDATE investor_access_requests SET status="revoked",revoked_by_user_id=?,revoked_at=NOW(),review_notes=?,reapplication_allowed=?,reapplication_after=?,updated_at=NOW() WHERE id=?')->execute([$actorId,$notes,mg_investment_bool($input['reapplication_allowed'] ?? true)?1:0,mg_investment_date($input['reapplication_after'] ?? null),$requestId]);
            $pdo->prepare('UPDATE investor_profiles SET status="revoked",updated_at=NOW() WHERE user_id=?')->execute([$targetUserId]);
            $pdo->prepare('UPDATE investment_round_access SET status="revoked",revoked_at=NOW() WHERE investor_user_id=? AND status="granted"')->execute([$targetUserId]);
            $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$targetUserId]);
        } else {
            $pdo->prepare('UPDATE investor_access_requests SET reapplication_allowed=1,reapplication_after=NULL,review_notes=?,updated_at=NOW() WHERE id=?')->execute([$notes ?: null,$requestId]);
        }
        mg_investment_event($pdo,$requestId,$actorId,$action,['notes'=>$notes]);
        $pdo->commit();
        mg_audit('investor_access_' . $action,'investor_access',['request_id'=>$publicId,'target_user_id'=>$targetUserId,'notes'=>$notes],$actorId);
        mg_event('investment.access.' . $action,['request_id'=>$publicId,'target_user_id'=>$targetUserId],$actorId);
        return mg_investment_admin_request($pdo,$publicId);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_investment_admin_access_queue(PDO $pdo, array $input): array
{
    $status = strtolower(mg_investment_text($input['status'] ?? '',60));
    $allowed = ['','pending','more_information_requested','approved','denied','revoked','withdrawn'];
    if (!in_array($status,$allowed,true)) throw new MgInvestmentException('Invalid status filter.');
    $params = [];
    $sql = 'SELECT iar.*,u.email,u.full_name,u.display_name FROM investor_access_requests iar INNER JOIN users u ON u.id=iar.user_id WHERE 1=1';
    if ($status !== '') { $sql .= ' AND iar.status=?'; $params[] = $status; }
    $sql .= ' ORDER BY FIELD(iar.status,"pending","more_information_requested","approved","denied","revoked","withdrawn"),iar.requested_at ASC LIMIT 250';
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return array_map(static function(array $row): array {
        return mg_investment_access_public($row) + [
            'email'=>(string)$row['email'],
            'full_name'=>(string)$row['full_name'],
            'display_name'=>(string)($row['display_name'] ?? $row['full_name']),
        ];
    },$stmt->fetchAll(PDO::FETCH_ASSOC));
}
