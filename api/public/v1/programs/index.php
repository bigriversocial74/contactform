<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_public.php';

mg_require_method('GET');
$context = mg_public_context('distribution:programs.read');
$pdo = $context['pdo'];
$stmt = $pdo->prepare("SELECT dp.public_id,dp.name,dp.program_type,dp.status,dp.starts_at,dp.ends_at,dp.budget_cents,dp.max_items,dp.per_recipient_limit,COUNT(dpp.id) AS product_count FROM distribution_programs dp LEFT JOIN distribution_program_products dpp ON dpp.program_id=dp.id AND dpp.status='active' WHERE dp.merchant_user_id=? AND dp.status IN ('scheduled','active','paused') GROUP BY dp.id ORDER BY dp.updated_at DESC,dp.id DESC");
$stmt->execute([(int)$context['merchant_user_id']]);
$programs = $stmt->fetchAll();
mg_public_log($pdo, $context, 200, 'ok');
mg_ok(['programs'=>$programs]);
