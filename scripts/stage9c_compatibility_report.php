<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
$giftCount=(int)$pdo->query('SELECT COUNT(*) FROM gifts')->fetchColumn();
$mappedCount=(int)$pdo->query('SELECT COUNT(*) FROM microgift_instances WHERE legacy_gift_id IS NOT NULL')->fetchColumn();
$claimCount=(int)$pdo->query('SELECT COUNT(*) FROM gift_claims')->fetchColumn();
$canonicalClaimCount=(int)$pdo->query('SELECT COUNT(*) FROM microgift_claims')->fetchColumn();
echo json_encode([
 'legacy_gifts'=>$giftCount,
 'mapped_legacy_gifts'=>$mappedCount,
 'unmapped_legacy_gifts'=>max(0,$giftCount-$mappedCount),
 'legacy_claims'=>$claimCount,
 'canonical_claims'=>$canonicalClaimCount,
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
