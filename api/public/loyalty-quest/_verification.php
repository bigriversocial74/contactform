<?php
declare(strict_types=1);

function mg_lqv_safe_proof_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '') return null;
    if (strlen($url) > 700 || filter_var($url, FILTER_VALIDATE_URL) === false) mg_fail('Invalid proof URL.', 422);
    $parts = parse_url($url);
    if (!is_array($parts)) mg_fail('Invalid proof URL.', 422);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $local = in_array($host, ['localhost','127.0.0.1','::1'], true);
    if ($host === '' || ($scheme !== 'https' && !($local && $scheme === 'http')) || !empty($parts['user']) || !empty($parts['pass'])) mg_fail('Proof links must use a safe HTTPS URL.', 422);
    return $url;
}

function mg_lqv_base64url_decode(string $value): string|false
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) return false;
    $padding = (4 - strlen($value) % 4) % 4;
    return base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
}

function mg_lqv_signed_qr(array $campaign, string $code): array
{
    $parts = explode('.', $code);
    if (count($parts) !== 3) mg_fail('The signed quest QR code is invalid.', 422);
    [$payloadEncoded, $nonce, $signature] = $parts;
    if (strlen($nonce) < 16 || strlen($nonce) > 190 || preg_match('/^[A-Za-z0-9_-]+$/', $nonce) !== 1 || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1) mg_fail('The signed quest QR code is invalid.', 422);
    $secret = (string)($campaign['qr_code_token'] ?? '');
    if ($secret === '') mg_fail('Signed quest verification is not configured.', 409);
    $expected = hash_hmac('sha256', $payloadEncoded . '.' . $nonce, $secret);
    if (!hash_equals($expected, strtolower($signature))) mg_fail('The signed quest QR code is invalid.', 422);
    $decoded = mg_lqv_base64url_decode($payloadEncoded);
    $payload = is_string($decoded) ? json_decode($decoded, true) : null;
    if (!is_array($payload)) mg_fail('The signed quest QR payload is invalid.', 422);
    $campaignRef = strtolower(trim((string)($payload['campaign'] ?? $payload['campaign_id'] ?? '')));
    $validRefs = [strtolower((string)$campaign['public_id']), strtolower((string)($campaign['public_slug'] ?? ''))];
    if ($campaignRef === '' || !in_array($campaignRef, $validRefs, true)) mg_fail('This signed QR code belongs to another Loyalty Quest.', 422);
    $expires = filter_var($payload['exp'] ?? null, FILTER_VALIDATE_INT);
    if ($expires === false || $expires < time()) mg_fail('This signed quest QR code has expired.', 409);
    if ($expires > time() + 2678400) mg_fail('The signed quest QR expiration is invalid.', 422);
    $issued = filter_var($payload['iat'] ?? null, FILTER_VALIDATE_INT);
    if ($issued !== false && $issued > time() + 300) mg_fail('The signed quest QR issue time is invalid.', 422);
    return ['payload'=>$payload,'nonce_hash'=>hash('sha256', $nonce),'code_hash'=>hash('sha256', $code)];
}

function mg_lqv_reference_unique(PDO $pdo, array $campaign, string $referenceId): void
{
    if ($referenceId === '') return;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_quest_evidence WHERE campaign_id=? AND merchant_user_id=? AND reference_id=? AND status<>'rejected'");
    $stmt->execute([(int)$campaign['id'], (int)$campaign['merchant_user_id'], $referenceId]);
    if ((int)$stmt->fetchColumn() > 0) mg_fail('This purchase or completion reference has already been submitted.', 409);
}

function mg_lqv_use_signed_code(PDO $pdo, array $campaign, array $user, array $signed): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO loyalty_quest_code_uses (campaign_id,participant_user_id,code_hash,nonce_hash,used_at) VALUES (?,?,?,?,NOW())');
        $stmt->execute([(int)$campaign['id'], (int)$user['id'], (string)$signed['code_hash'], (string)$signed['nonce_hash']]);
    } catch (PDOException $error) {
        $sqlState = (string)($error->errorInfo[0] ?? $error->getCode());
        if ($sqlState === '23000') mg_fail('This signed quest QR code has already been used.', 409);
        throw $error;
    }
}

function mg_lqv_resolve(PDO $pdo, array $campaign, array $participation, array $user, array $input): array
{
    $rules = $campaign['rules'];
    $verification = (string)($rules['verification_type'] ?? 'manual_review');
    if ($verification === 'event_checkin') $verification = 'event_check_in';
    $action = (string)($rules['action_type'] ?? 'milestone');
    $code = trim((string)($input['code'] ?? $input['qr_code'] ?? ''));
    $proofNote = trim((string)($input['proof_note'] ?? ''));
    $proofUrl = mg_lqv_safe_proof_url($input['proof_url'] ?? '');
    $referenceId = trim((string)($input['reference_id'] ?? ''));
    $latitude = is_numeric($input['latitude'] ?? null) ? (float)$input['latitude'] : null;
    $longitude = is_numeric($input['longitude'] ?? null) ? (float)$input['longitude'] : null;
    $accuracy = is_numeric($input['accuracy_meters'] ?? null) ? max(0.0, (float)$input['accuracy_meters']) : null;
    if (mb_strlen($proofNote) > 4000 || mb_strlen($referenceId) > 190 || strlen($code) > 1000) mg_fail('Invalid quest submission.', 422);
    if (($latitude === null) !== ($longitude === null) || ($latitude !== null && ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180))) mg_fail('Invalid location evidence.', 422);

    $result = [
        'evidence_type'=>'note','verified'=>false,'code_hash'=>null,'nonce_hash'=>null,
        'latitude'=>$latitude,'longitude'=>$longitude,'accuracy_meters'=>$accuracy,'distance_meters'=>null,
        'proof_url'=>$proofUrl,'proof_note'=>$proofNote!==''?$proofNote:null,'reference_id'=>$referenceId!==''?$referenceId:null,
        'verification_type'=>$verification,'action_type'=>$action,'signed_payload'=>null,
    ];

    if ($verification === 'signed_qr') {
        if ($code === '') mg_fail('Scan the signed quest QR code.', 422);
        $signed = mg_lqv_signed_qr($campaign, $code);
        mg_lqv_use_signed_code($pdo, $campaign, $user, $signed);
        $result['evidence_type'] = 'qr';
        $result['verified'] = true;
        $result['code_hash'] = $signed['code_hash'];
        $result['nonce_hash'] = $signed['nonce_hash'];
        $result['signed_payload'] = $signed['payload'];
        return $result;
    }
    if ($verification === 'static_qr') {
        if ($code === '') mg_fail('Scan the quest QR code or enter its completion code.', 422);
        $codeHash = hash('sha256', strtoupper($code));
        $expectedHash = strtolower(trim((string)($rules['completion_code_hash'] ?? '')));
        if ($expectedHash === '' || !hash_equals($expectedHash, $codeHash)) mg_fail('The quest completion code is invalid.', 422);
        $result['evidence_type'] = 'manual_code';
        $result['verified'] = true;
        $result['code_hash'] = $codeHash;
        return $result;
    }
    if ($verification === 'geolocation') {
        if ($latitude === null || $longitude === null || $accuracy === null) mg_fail('Allow precise location access to verify this quest.', 422);
        if ($campaign['location_latitude'] === null || $campaign['location_longitude'] === null) mg_fail('Merchant location verification is not configured.', 409);
        $distance = mg_lqp_distance_meters($latitude, $longitude, (float)$campaign['location_latitude'], (float)$campaign['location_longitude']);
        $radius = max(25, min(5000, (int)($rules['radius_meters'] ?? $campaign['location_radius'] ?? 150)));
        $accuracyLimit = max(25, min(1000, (int)($rules['maximum_accuracy_meters'] ?? 250)));
        if ($accuracy > $accuracyLimit) mg_fail('Location accuracy is too low. Move closer and try again.', 422);
        if ($distance > $radius) mg_fail('You are outside the allowed quest location.', 422);
        $result['evidence_type'] = 'geolocation';
        $result['verified'] = true;
        $result['distance_meters'] = $distance;
        return $result;
    }
    if (in_array($verification, ['purchase_record','microgifter_transaction'], true)) {
        if ($referenceId === '') mg_fail('A purchase or Microgifter transaction reference is required.', 422);
        mg_lqv_reference_unique($pdo, $campaign, $referenceId);
        $result['evidence_type'] = 'purchase';
        return $result;
    }
    if ($verification === 'staff_confirmation' || $verification === 'event_check_in') {
        if ($code === '') mg_fail($verification === 'staff_confirmation' ? 'Enter the staff confirmation code.' : 'Enter the event check-in code.', 422);
        $codeHash = hash('sha256', strtoupper($code));
        $key = $verification === 'staff_confirmation' ? 'staff_confirmation_code_hash' : 'event_checkin_code_hash';
        $expectedHash = strtolower(trim((string)($rules[$key] ?? '')));
        if ($expectedHash === '' || !hash_equals($expectedHash, $codeHash)) mg_fail($verification === 'staff_confirmation' ? 'Staff confirmation code is invalid.' : 'Event check-in code is invalid.', 422);
        $result['evidence_type'] = $verification === 'staff_confirmation' ? 'staff_confirmation' : 'event_checkin';
        $result['verified'] = true;
        $result['code_hash'] = $codeHash;
        return $result;
    }
    if ($verification === 'referral_conversion') {
        if ($referenceId === '' && $proofNote === '') mg_fail('Add the referral or conversion reference for merchant review.', 422);
        mg_lqv_reference_unique($pdo, $campaign, $referenceId);
        $result['evidence_type'] = 'referral';
        return $result;
    }
    if ($proofNote === '' && $proofUrl === null && $referenceId === '') mg_fail('Add proof or a completion note for merchant review.', 422);
    mg_lqv_reference_unique($pdo, $campaign, $referenceId);
    $result['evidence_type'] = match ($action) {
        'referral' => 'referral',
        'social_action' => 'social',
        'milestone','sequence','multi_location' => 'milestone',
        default => 'receipt',
    };
    return $result;
}
