<?php
 declare(strict_types=1);
 require_once __DIR__ . '/_distribution.php';

 function mg_distribution_available_program_products(PDO $pdo, int $merchantUserId): array
 {
     $stmt = $pdo->prepare(
         "SELECT cpt.public_id AS template_id,
                 cp.public_id AS product_id,
                 cp.product_type,
                 cp.status AS product_status,
                 cpv.public_id AS version_id,
                 cpv.title,
                 cpv.description,
                 cpv.unit_value_cents,
                 cpv.currency,
                 cpt.item_type,
                 cpt.status AS template_status,
                 cp.updated_at
          FROM catalog_pppm_templates cpt
          INNER JOIN catalog_product_versions cpv ON cpv.id = cpt.product_version_id
          INNER JOIN catalog_products cp ON cp.id = cpv.product_id
          WHERE cp.merchant_user_id = ?
            AND cp.status = 'published'
            AND cpt.status = 'active'
          ORDER BY cp.updated_at DESC, cp.id DESC"
     );
     $stmt->execute([$merchantUserId]);
     return $stmt->fetchAll();
 }

 function mg_distribution_selected_program_products(PDO $pdo, int $merchantUserId, string $programId): array
 {
     if ($programId === '') return [];
     $stmt = $pdo->prepare(
         'SELECT dpp.id,
                 cpt.public_id AS template_id,
                 cp.public_id AS product_id,
                 cp.product_type,
                 cp.status AS product_status,
                 cpv.title,
                 cpv.unit_value_cents,
                 cpv.currency,
                 dpp.weight,
                 dpp.quantity_limit,
                 dpp.quantity_issued,
                 dpp.status
          FROM distribution_program_products dpp
          INNER JOIN distribution_programs dp ON dp.id = dpp.program_id
          INNER JOIN catalog_pppm_templates cpt ON cpt.id = dpp.pppm_template_id
          INNER JOIN catalog_product_versions cpv ON cpv.id = cpt.product_version_id
          INNER JOIN catalog_products cp ON cp.id = cpv.product_id
          WHERE dp.public_id = ? AND dp.merchant_user_id = ?
          ORDER BY dpp.id'
     );
     $stmt->execute([$programId, $merchantUserId]);
     return $stmt->fetchAll();
 }

 $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
 $user = mg_require_permission($method === 'GET' ? 'distribution.analytics.view' : 'distribution.programs.manage');
 $pdo = mg_db();

 if ($method === 'GET') {
     $programId = trim((string) ($_GET['program_id'] ?? ''));
     mg_ok([
         'products' => mg_distribution_selected_program_products($pdo, (int) $user['id'], $programId),
         'available_products' => mg_distribution_available_program_products($pdo, (int) $user['id']),
     ]);
 }

 if ($method !== 'POST') mg_fail('Method not allowed.', 405);

 $input = mg_input();
 mg_require_csrf_for_write($input);

 $action = trim((string) ($input['action'] ?? 'save'));
 $programId = trim((string) ($input['program_id'] ?? ''));

 if ($action === 'sync') {
     $templateIds = $input['template_ids'] ?? [];
     if (!is_array($templateIds)) mg_fail('Invalid product selection.', 422);
     $templateIds = array_values(array_unique(array_filter(array_map(
         static fn(mixed $value): string => trim((string) $value),
         $templateIds
     ), static fn(string $value): bool => $value !== '')));

     $pdo->beginTransaction();
     try {
         $program = mg_distribution_program_for_update($pdo, (int) $user['id'], $programId);
         $templateDbIds = [];

         if ($templateIds !== []) {
             $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
             $stmt = $pdo->prepare(
                 "SELECT cpt.id, cpt.public_id
                  FROM catalog_pppm_templates cpt
                  INNER JOIN catalog_product_versions cpv ON cpv.id = cpt.product_version_id
                  INNER JOIN catalog_products cp ON cp.id = cpv.product_id
                  WHERE cpt.public_id IN ($placeholders)
                    AND cp.merchant_user_id = ?
                    AND cp.status = 'published'
                    AND cpt.status = 'active'"
             );
             $params = $templateIds;
             $params[] = (int) $user['id'];
             $stmt->execute($params);
             foreach ($stmt->fetchAll() as $row) {
                 $templateDbIds[(string) $row['public_id']] = (int) $row['id'];
             }
             if (count($templateDbIds) !== count($templateIds)) {
                 mg_fail('One or more selected products are unavailable.', 422);
             }
         }

         if ($templateDbIds === []) {
             $pdo->prepare("UPDATE distribution_program_products SET status='inactive' WHERE program_id=?")
                 ->execute([(int) $program['id']]);
         } else {
             $dbIds = array_values($templateDbIds);
             $placeholders = implode(',', array_fill(0, count($dbIds), '?'));
             $params = [(int) $program['id']];
             foreach ($dbIds as $dbId) $params[] = $dbId;
             $pdo->prepare("UPDATE distribution_program_products SET status='inactive' WHERE program_id=? AND pppm_template_id NOT IN ($placeholders)")
                 ->execute($params);

             $insert = $pdo->prepare(
                 "INSERT INTO distribution_program_products (program_id, pppm_template_id, weight, quantity_limit, status, created_at)
                  VALUES (?, ?, 1, NULL, 'active', NOW())
                  ON DUPLICATE KEY UPDATE status='active'"
             );
             foreach ($templateIds as $templateId) {
                 $insert->execute([(int) $program['id'], $templateDbIds[$templateId]]);
             }
         }

         $pdo->commit();
         mg_audit('distribution.program_products_synced', 'distribution_program', ['program_id' => $program['public_id'], 'product_count' => count($templateDbIds)], (int) $user['id']);
         mg_ok([
             'program_id' => $program['public_id'],
             'products' => mg_distribution_selected_program_products($pdo, (int) $user['id'], (string) $program['public_id']),
         ], 'Program products saved.', 201);
     } catch (Throwable $e) {
         if ($pdo->inTransaction()) $pdo->rollBack();
         mg_fail('Unable to sync program products.', 500);
     }
 }

 $program = mg_distribution_program_for_update($pdo, (int) $user['id'], $programId);
 $templateId = trim((string) ($input['template_id'] ?? ''));
 $weight = max(1, (int) ($input['weight'] ?? 1));
 $limit = isset($input['quantity_limit']) && $input['quantity_limit'] !== '' ? max(1, (int) $input['quantity_limit']) : null;
 $status = trim((string) ($input['status'] ?? 'active'));
 if (!in_array($status, ['active', 'inactive', 'exhausted'], true)) mg_fail('Invalid program product status.', 422);

 $stmt = $pdo->prepare("SELECT cpt.id FROM catalog_pppm_templates cpt INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id INNER JOIN catalog_products cp ON cp.id=cpv.product_id WHERE cpt.public_id=? AND cp.merchant_user_id=? AND cp.status='published' AND cpt.status='active' LIMIT 1");
 $stmt->execute([$templateId, (int) $user['id']]);
 $templateDbId = $stmt->fetchColumn();
 if (!$templateDbId) mg_fail('PPPM template not found.', 404);

 $pdo->prepare("INSERT INTO distribution_program_products (program_id,pppm_template_id,weight,quantity_limit,status,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE weight=VALUES(weight),quantity_limit=VALUES(quantity_limit),status=VALUES(status)")
     ->execute([(int) $program['id'], (int) $templateDbId, $weight, $limit, $status]);
 mg_audit('distribution.program_product_saved', 'distribution_program', ['program_id' => $program['public_id'], 'template_id' => $templateId], (int) $user['id']);
 mg_ok(['program_id' => $program['public_id'], 'template_id' => $templateId, 'status' => $status], 'Program product saved.', 201);
