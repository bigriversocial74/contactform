<?php
declare(strict_types=1);

require_once __DIR__ . '/_tips.php';

/**
 * Read-only public capability adapter for Stage 12 profile tipping.
 * It preserves the existing canonical resolver while replacing legacy numeric
 * profile references with the Stage 2 public profile identifier at the boundary.
 */
function mg_tip_public_profile_capability(PDO $pdo,string $profilePublicId,?int $viewerId=null): array
{
    $profilePublicId=trim($profilePublicId);
    if($profilePublicId==='')return ['available'=>false];

    $stmt=$pdo->prepare(
        "SELECT pp.user_id
         FROM public_profiles pp
         INNER JOIN users u ON u.id=pp.user_id
         WHERE pp.public_id=? AND pp.status='active'
           AND pp.visibility IN ('public','unlisted') AND u.status='active'
         LIMIT 1"
    );
    $stmt->execute([$profilePublicId]);
    $recipientId=(int)($stmt->fetchColumn()?:0);
    if($recipientId<1||($viewerId!==null&&$viewerId===$recipientId))return ['available'=>false];

    try{
        $canonical=mg_tip_resolve_target($pdo,'profile',(string)$recipientId);
    }catch(Throwable){
        return ['available'=>false];
    }
    if((int)$canonical['recipient_user_id']!==$recipientId)return ['available'=>false];

    return [
        'available'=>true,
        'target'=>[
            'type'=>'profile',
            'id'=>$profilePublicId,
        ],
    ];
}
