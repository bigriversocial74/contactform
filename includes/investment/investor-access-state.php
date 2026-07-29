<?php
declare(strict_types=1);

/**
 * Resolve the effective Investor account state from both role assignment and
 * the canonical investor profile. The role alone never grants portal access.
 *
 * @return array{
 *   state:string,
 *   label:string,
 *   has_role:bool,
 *   profile_status:?string,
 *   request_status:?string,
 *   can_open_portal:bool,
 *   needs_admin_repair:bool
 * }
 */
function mg_investor_access_state(PDO $pdo, array $user): array
{
    $userId = (int)($user['id'] ?? 0);
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $hasRole = in_array('investor', $roles, true);
    $cacheKey = $userId . ':' . ($hasRole ? '1' : '0');

    static $cache = [];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $profileStatus = null;
    $requestStatus = null;

    if ($userId > 0) {
        try {
            $profile = $pdo->prepare('SELECT status FROM investor_profiles WHERE user_id=? ORDER BY id DESC LIMIT 1');
            $profile->execute([$userId]);
            $value = $profile->fetchColumn();
            $profileStatus = is_string($value) && $value !== '' ? strtolower($value) : null;
        } catch (Throwable) {
            $profileStatus = null;
        }

        try {
            $request = $pdo->prepare('SELECT status FROM investor_access_requests WHERE user_id=? ORDER BY id DESC LIMIT 1');
            $request->execute([$userId]);
            $value = $request->fetchColumn();
            $requestStatus = is_string($value) && $value !== '' ? strtolower($value) : null;
        } catch (Throwable) {
            $requestStatus = null;
        }
    }

    $state = 'not_requested';
    $label = 'Investor access not requested';
    $needsRepair = false;

    if ($hasRole && $profileStatus === 'active') {
        $state = 'approved_active';
        $label = 'Investor access active';
    } elseif ($hasRole && $profileStatus !== 'active') {
        $state = 'role_without_active_profile';
        $label = 'Investor access requires administrator repair';
        $needsRepair = true;
    } elseif (!$hasRole && $profileStatus === 'active') {
        $state = 'active_profile_without_role';
        $label = 'Investor access requires administrator repair';
        $needsRepair = true;
    } elseif ($profileStatus === 'revoked' || $requestStatus === 'revoked') {
        $state = 'revoked';
        $label = 'Investor access revoked';
    } elseif ($requestStatus === 'pending') {
        $state = 'pending';
        $label = 'Investor request pending review';
    } elseif ($requestStatus === 'more_information_requested') {
        $state = 'more_information_requested';
        $label = 'More information requested';
    } elseif ($requestStatus === 'approved') {
        $state = 'approved_incomplete';
        $label = 'Investor approval is incomplete';
        $needsRepair = true;
    } elseif ($requestStatus === 'denied') {
        $state = 'denied';
        $label = 'Investor request denied';
    } elseif ($requestStatus === 'withdrawn') {
        $state = 'withdrawn';
        $label = 'Investor request withdrawn';
    }

    return $cache[$cacheKey] = [
        'state' => $state,
        'label' => $label,
        'has_role' => $hasRole,
        'profile_status' => $profileStatus,
        'request_status' => $requestStatus,
        'can_open_portal' => $state === 'approved_active',
        'needs_admin_repair' => $needsRepair,
    ];
}
