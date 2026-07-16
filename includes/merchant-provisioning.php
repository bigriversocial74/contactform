<?php
declare(strict_types=1);

if (!function_exists('mg_subscription_workspace_uuid')) {
    function mg_subscription_workspace_uuid(): string
    {
        if (function_exists('mg_public_uuid')) return mg_public_uuid();
        if (function_exists('mg_uuid')) return mg_uuid();
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
    }
}

if (!function_exists('mg_subscription_provision_merchant_workspace')) {
    function mg_subscription_provision_merchant_workspace(PDO $pdo, int $userId, string $displayName = '', string $source = 'subscription_activation'): array
    {
        if ($userId < 1) throw new InvalidArgumentException('A valid user is required to provision a merchant workspace.');

        $displayName = trim($displayName);
        if ($displayName === '') {
            $userStmt = $pdo->prepare('SELECT COALESCE(NULLIF(display_name, ""), NULLIF(full_name, ""), email) FROM users WHERE id=? LIMIT 1');
            $userStmt->execute([$userId]);
            $displayName = trim((string)$userStmt->fetchColumn());
        }
        if ($displayName === '') $displayName = 'Merchant workspace';

        $existingStmt = $pdo->prepare('SELECT * FROM merchant_workspaces WHERE merchant_user_id=? LIMIT 1');
        $existingStmt->execute([$userId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $pdo->prepare("INSERT INTO merchant_team_members (public_id,workspace_id,user_id,display_name,role_key,status,invited_by_user_id,invited_at,accepted_at,created_at,updated_at) VALUES (?,?,?,?, 'owner','active',?,NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE role_key='owner',status='active',accepted_at=COALESCE(accepted_at,NOW()),updated_at=NOW()")
                ->execute([mg_subscription_workspace_uuid(),(int)$existing['id'],$userId,$displayName,$userId]);
            return $existing;
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $publicId = mg_subscription_workspace_uuid();
            $pdo->prepare("INSERT INTO merchant_workspaces (public_id,merchant_user_id,display_name,status,eligibility_status,onboarding_percent,created_at,updated_at) VALUES (?,?,?,'draft','not_started',0,NOW(),NOW())")
                ->execute([$publicId,$userId,$displayName]);
            $workspaceId = (int)$pdo->lastInsertId();
            $steps = [
                ['business_profile',1],['eligibility',2],['first_location',3],['claim_configuration',4],
                ['first_product',5],['storefront',6],['payment_readiness',7],['test_pppm',8],
                ['test_claim',9],['analytics_verification',10],['beta_readiness',11],
            ];
            $stepStmt = $pdo->prepare('INSERT INTO merchant_onboarding_steps (workspace_id,step_key,step_order,status,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
            foreach ($steps as $index => $step) $stepStmt->execute([$workspaceId,$step[0],$step[1],$index===0?'available':'locked']);
            $pdo->prepare('INSERT INTO merchant_payment_readiness (workspace_id,created_at,updated_at) VALUES (?,NOW(),NOW())')->execute([$workspaceId]);
            $pdo->prepare("INSERT INTO merchant_team_members (public_id,workspace_id,user_id,display_name,role_key,status,invited_by_user_id,invited_at,accepted_at,created_at,updated_at) VALUES (?,?,?,?, 'owner','active',?,NOW(),NOW(),NOW(),NOW())")
                ->execute([mg_subscription_workspace_uuid(),$workspaceId,$userId,$displayName,$userId]);
            if ($ownsTransaction) $pdo->commit();

            $reload = $pdo->prepare('SELECT * FROM merchant_workspaces WHERE id=? LIMIT 1');
            $reload->execute([$workspaceId]);
            $workspace = $reload->fetch(PDO::FETCH_ASSOC);
            if (!$workspace) throw new RuntimeException('Merchant workspace provisioning did not persist.');
            if (function_exists('mg_audit')) mg_audit('merchant.workspace_provisioned','merchant_workspace',['workspace_id'=>$publicId,'source'=>$source],$userId);
            if (function_exists('mg_event')) mg_event('merchant.workspace_provisioned',['workspace_id'=>$publicId,'source'=>$source],$userId);
            return $workspace;
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
