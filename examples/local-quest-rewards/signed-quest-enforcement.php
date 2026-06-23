<?php
declare(strict_types=1);

function lqr_quest_requires_signed_code(array $quest): bool
{
    $controls = function_exists('lqr_quest_controls') ? lqr_quest_controls($quest) : (array)($quest['controls'] ?? []);
    return !empty($controls['requires_signed_code']);
}

function lqr_signed_code_replay_key(array $verified): string
{
    $payload = is_array($verified['payload'] ?? null) ? $verified['payload'] : [];
    $nonce = (string)($payload['nonce'] ?? '');
    $questId = (string)($payload['quest_id'] ?? '');
    $iat = (string)($payload['iat'] ?? '');
    return 'signed_quest:' . hash('sha256', $questId . '|' . $nonce . '|' . $iat);
}

function lqr_enforce_signed_quest_code(array $config, array &$state, string $questId, array $quest, string $rawPayload): array
{
    $controls = function_exists('lqr_quest_controls') ? lqr_quest_controls($quest) : (array)($quest['controls'] ?? []);
    if (empty($controls['requires_signed_code'])) {
        return ['required' => false, 'verified' => false, 'payload' => null, 'message' => 'Signed code not required.'];
    }

    $rawPayload = trim($rawPayload);
    if ($rawPayload === '') throw new RuntimeException('This quest requires a signed QR code. Scan the official venue QR code to complete it.');

    $expectedType = (string)($controls['signed_code_type'] ?? 'quest_checkin');
    $verified = lqr_verify_signed_payload($config, $rawPayload, $expectedType);
    $payload = is_array($verified['payload'] ?? null) ? $verified['payload'] : [];

    if ((string)($payload['quest_id'] ?? '') !== $questId) throw new RuntimeException('Signed QR code does not match this quest.');

    $replayKey = lqr_signed_code_replay_key($verified);
    if (lqr_replay_seen($state, $replayKey)) throw new RuntimeException('Signed QR code has already been used.');

    lqr_mark_replay($state, $replayKey);
    lqr_add_event($state, 'quest.signed_code_verified', 'Signed quest QR verified.', ['quest_id'=>$questId, 'type'=>$expectedType, 'replay_key'=>$replayKey]);

    return ['required' => true, 'verified' => true, 'payload' => $payload, 'replay_key' => $replayKey, 'message' => 'Signed QR code verified.'];
}
