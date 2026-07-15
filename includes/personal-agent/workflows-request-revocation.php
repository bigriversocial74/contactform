<?php
declare(strict_types=1);

function mg_personal_workflows_revoke_data_request_safe(PDO $pdo, int $subjectUserId, string $publicId): array
{
    $nullableFields = [
        'gift_preferences','interests','allergies_or_restrictions','preferred_merchants','preferred_categories',
        'budget_min','budget_max','address_line_1','address_line_2','city','state_region','postal_code',
        'country_code','birthdate',
    ];
    $resetFields = ['birth_year_visible' => 0];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_recipient_data_requests
            WHERE subject_user_id=? AND public_id=? AND status IN ('approved','partially_approved')
            LIMIT 1 FOR UPDATE");
        $stmt->execute([$subjectUserId, $publicId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new RuntimeException('Approved recipient data request not found.');

        $requesterId = (int) $request['requester_user_id'];
        $approved = mg_personal_workflows_request_scopes(
            mg_personal_agent_json($request['approved_scopes_json'] ?? null)
        );
        if ($approved === []) throw new RuntimeException('This request has no approved scopes to revoke.');

        foreach ($approved as $scope) {
            $pdo->prepare("UPDATE user_contact_profile_permissions
                SET status='revoked',revoked_at=NOW(),updated_at=NOW()
                WHERE subject_user_id=? AND grantee_user_id=? AND permission_scope=?")
                ->execute([$subjectUserId, $requesterId, $scope]);
        }

        $imports = $pdo->prepare("SELECT id,user_contact_id
            FROM user_contact_profile_imports
            WHERE owner_user_id=? AND subject_user_id=? AND status='active' FOR UPDATE");
        $imports->execute([$requesterId, $subjectUserId]);

        foreach ($imports->fetchAll(PDO::FETCH_ASSOC) as $import) {
            $fields = $pdo->prepare("SELECT id,permission_scope,field_name,value_hash
                FROM user_contact_profile_import_fields
                WHERE import_id=? AND status='active'");
            $fields->execute([(int) $import['id']]);

            foreach ($fields->fetchAll(PDO::FETCH_ASSOC) as $field) {
                $scope = (string) $field['permission_scope'];
                if (!in_array($scope, $approved, true)) continue;

                $name = (string) $field['field_name'];
                if (!in_array($name, $nullableFields, true) && !array_key_exists($name, $resetFields)) continue;

                $valueStmt = $pdo->prepare('SELECT ' . $name . ' FROM user_contacts WHERE id=? AND owner_user_id=? LIMIT 1');
                $valueStmt->execute([(int) $import['user_contact_id'], $requesterId]);
                $current = $valueStmt->fetchColumn();
                $currentHash = mg_personal_workflows_value_hash($current, $name);

                if (hash_equals((string) $field['value_hash'], $currentHash)) {
                    if (array_key_exists($name, $resetFields)) {
                        $pdo->prepare('UPDATE user_contacts SET ' . $name . '=?,updated_at=NOW() WHERE id=? AND owner_user_id=?')
                            ->execute([$resetFields[$name], (int) $import['user_contact_id'], $requesterId]);
                    } else {
                        $pdo->prepare('UPDATE user_contacts SET ' . $name . '=NULL,updated_at=NOW() WHERE id=? AND owner_user_id=?')
                            ->execute([(int) $import['user_contact_id'], $requesterId]);
                    }
                }

                $pdo->prepare("UPDATE user_contact_profile_import_fields
                    SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE id=?")
                    ->execute([(int) $field['id']]);
            }

            $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM user_contact_profile_import_fields
                WHERE import_id=? AND status='active'");
            $activeStmt->execute([(int) $import['id']]);
            $activeCount = (int) $activeStmt->fetchColumn();
            $importStatus = $activeCount > 0 ? 'active' : 'revoked';
            $pdo->prepare("UPDATE user_contact_profile_imports
                SET status=?,revoked_at=IF(?='revoked',NOW(),NULL),updated_at=NOW() WHERE id=?")
                ->execute([$importStatus, $importStatus, (int) $import['id']]);
        }

        $pdo->prepare("UPDATE user_recipient_data_requests
            SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int) $request['id']]);

        mg_personal_workflows_notify(
            $pdo,
            $requesterId,
            'recipient_data_request_revoked',
            'Gifting information permission revoked',
            'Previously shared information from this request is no longer available to the Personal Gifting Agent.',
            '/agent.php?view=requests'
        );
        $pdo->commit();

        mg_audit('user_recipient_data_request.revoked', 'user_recipient_data_request', [
            'request_id' => $publicId,
            'revoked_scopes' => $approved,
            'unrelated_scopes_preserved' => true,
            'manual_edits_preserved' => true,
        ], $subjectUserId);

        return ['id'=>$publicId,'status'=>'revoked','revoked_scopes'=>$approved];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
