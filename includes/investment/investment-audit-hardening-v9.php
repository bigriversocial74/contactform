<?php
declare(strict_types=1);

function mg_investment_decimal_input_audited(mixed $value,string $label='Amount'): string
{
    if(is_int($value))return (string)$value;
    if(is_float($value))$value=number_format($value,2,'.','');
    $raw=trim(str_replace([',','$',' '],'',(string)$value));
    if($raw==='')$raw='0';
    if(!preg_match('/^\d{1,15}(?:\.\d{1,2})?$/',$raw))throw new MgInvestmentException($label.' must be a non-negative decimal amount with no more than two decimal places.');
    [$whole,$fraction]=array_pad(explode('.',$raw,2),2,'');
    $whole=ltrim($whole,'0');if($whole==='')$whole='0';$fraction=str_pad($fraction,2,'0');
    return $whole.'.'.$fraction;
}

function mg_investment_save_scenario_audited(PDO $pdo,array $actor,array $input): array
{
    $safe=$input;
    foreach(['minimum_raise','target_raise','maximum_raise','valuation_cap','pre_money_valuation','minimum_investment','existing_safe'] as $field)if(array_key_exists($field,$safe))$safe[$field]=mg_investment_decimal_input_audited($safe[$field],ucwords(str_replace('_',' ',$field)));
    return mg_investment_save_scenario($pdo,$actor,$safe);
}

function mg_investment_replace_budget_audited(PDO $pdo,array $actor,array $input): array
{
    $safe=$input;$safe['items']=[];
    foreach(array_slice(is_array($input['items']??null)?$input['items']:[],0,100) as $item){if(!is_array($item))continue;$item['amount']=mg_investment_decimal_input_audited($item['amount']??0,'Budget amount');$safe['items'][]=$item;}
    return mg_investment_replace_budget($pdo,$actor,$safe);
}

function mg_investment_replace_goals_audited(PDO $pdo,array $actor,array $input): array
{
    $safe=$input;$safe['items']=[];
    foreach(array_slice(is_array($input['items']??null)?$input['items']:[],0,100) as $item){if(!is_array($item))continue;$item['budget']=mg_investment_decimal_input_audited($item['budget']??0,'Goal budget');$safe['items'][]=$item;}
    return mg_investment_replace_goals($pdo,$actor,$safe);
}

function mg_investment_update_round_audited(PDO $pdo,array $actor,array $input): array
{
    $publicId=mg_investment_text($input['round_id']??'',36,36,'Round identifier');$q=$pdo->prepare('SELECT * FROM investment_rounds WHERE public_id=? LIMIT 1');$q->execute([$publicId]);$round=$q->fetch(PDO::FETCH_ASSOC);if(!$round)throw new MgInvestmentException('Investment round not found.',404);
    $from=(string)$round['status'];$to=(string)($input['status']??$from);
    $transitions=[
      'planning'=>['planning','awaiting_counsel','paused','cancelled'],
      'awaiting_counsel'=>['planning','awaiting_counsel','private_preview','paused','cancelled'],
      'private_preview'=>['private_preview','open','paused','cancelled'],
      'open'=>['open','minimum_reached','closing','paused','cancelled'],
      'minimum_reached'=>['minimum_reached','closing','paused','cancelled'],
      'closing'=>['closing','closed','paused','cancelled'],
      'closed'=>['closed'],
      'paused'=>['planning','awaiting_counsel','private_preview','open','minimum_reached','closing','paused','cancelled'],
      'cancelled'=>['cancelled'],
    ];
    if(!in_array($to,$transitions[$from]??[$from],true))throw new MgInvestmentException('Invalid official-round transition: '.mg_investment_readable_stage($from).' → '.mg_investment_readable_stage($to).',',409);
    if($to==='closed'){
        $pending=$pdo->prepare('SELECT COUNT(*) FROM investment_financial_verification_requests WHERE round_id=? AND status="pending"');$pending->execute([(int)$round['id']]);if((int)$pending->fetchColumn()>0)throw new MgInvestmentException('Resolve all financial verification requests before closing the round.',409);
        $profile=$pdo->prepare('SELECT stage,counsel_status,board_status FROM investment_closing_profiles WHERE round_id=? LIMIT 1');$profile->execute([(int)$round['id']]);$closing=$profile->fetch(PDO::FETCH_ASSOC);if(!$closing||!in_array((string)$closing['stage'],['complete','post_close_review'],true)||(string)$closing['counsel_status']!=='approved'||!in_array((string)$closing['board_status'],['approved','not_applicable'],true))throw new MgInvestmentException('Closing profile, counsel, and board review must be complete before the official round is closed.',409);
    }
    $safe=$input;
    $safe['soft_commitment']=number_format((int)$round['soft_commitment_cents']/100,2,'.','');
    $safe['signed']=number_format((int)$round['signed_cents']/100,2,'.','');
    $safe['funded']=number_format((int)$round['funded_cents']/100,2,'.','');
    $result=mg_investment_update_round($pdo,$actor,$safe);
    mg_investment_recalculate_round_totals_audited($pdo,(int)$round['id'],(int)$actor['id']);
    return $result;
}

function mg_investment_save_documents_audited(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');$workspace=mg_investment_workspace_by_public_id($pdo,mg_investment_text($input['workspace_id']??'',36,36,'Workspace identifier'));$items=is_array($input['items']??null)?$input['items']:[];$statuses=['missing','draft','internal_review','counsel_review','approved','published','superseded','archived'];$visibility=['super_admin','approved_investors','selected_investors','funded_investors','public_summary'];$actorId=(int)$actor['id'];
    return mg_investment_audit_transaction($pdo,function()use($pdo,$actor,$workspace,$items,$statuses,$visibility,$actorId):array{
        foreach(array_slice($items,0,100) as $item){if(!is_array($item))continue;$publicId=mg_investment_text($item['id']??'',36,36,'Document identifier');$q=$pdo->prepare('SELECT * FROM investment_documents WHERE public_id=? AND workspace_id=? LIMIT 1 FOR UPDATE');$q->execute([$publicId,(int)$workspace['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);if(!$current)throw new MgInvestmentException('Investment document not found.',404);$status=(string)($item['status']??'missing');$scope=(string)($item['visibility']??'super_admin');if(!in_array($status,$statuses,true)||!in_array($scope,$visibility,true))throw new MgInvestmentException('Invalid document status or visibility.');$url=mg_investment_url($item['external_url']??'');if($status==='published'){mg_investment_require_permission($actor,'admin.investment.publish');if($url===null)throw new MgInvestmentException('Published documents require an approved external URL.');if(!in_array((string)$current['status'],['approved','published'],true))throw new MgInvestmentException('Approve an investment document in a separate step before publishing it.',409);}
            $title=mg_investment_text($item['title']??'',180,2,'Document title');$reason=mg_investment_text($item['change_reason']??'',500,8,'Document change reason');$version=(int)$current['current_version_number']+1;$pdo->prepare('UPDATE investment_documents SET title=?,status=?,external_url=?,visibility=?,current_version_number=?,notes=?,updated_at=NOW() WHERE id=?')->execute([$title,$status,$url,$scope,$version,mg_investment_long_text($item['notes']??'',4000)?:null,(int)$current['id']]);if($status==='published')$pdo->prepare('UPDATE investment_document_versions SET status="superseded" WHERE document_id=? AND status="published"')->execute([(int)$current['id']]);$pdo->prepare('INSERT INTO investment_document_versions (public_id,document_id,version_number,title,status,external_url,visibility,change_reason,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')->execute([mg_investment_uuid(),(int)$current['id'],$version,$title,$status,$url,$scope,$reason,$actorId]);}
        mg_audit('investment_documents_saved','investment_workspace',['workspace_id'=>$workspace['public_id'],'versioned'=>true],$actorId);return mg_investment_workspace_detail($pdo,(string)$workspace['public_id']);
    });
}

function mg_investment_publication_save_audited(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.publish');$round=mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$status=(string)($input['publication_status']??'draft');if(!in_array($status,['draft','internal_preview','private_preview','published','paused','archived'],true))throw new MgInvestmentException('Invalid publication status.');if(in_array($status,['private_preview','published'],true)&&!in_array((string)$round['status'],['private_preview','open','minimum_reached','closing','closed'],true))throw new MgInvestmentException('The official round must be in private preview or later before portal publication.',409);if(in_array($status,['private_preview','published'],true)&&(string)$round['counsel_status']!=='approved')throw new MgInvestmentException('Counsel status must be approved before investor publication.',409);
    $sections=mg_investment_publication_default_sections();foreach($sections as $key=>$default)$sections[$key]=mg_investment_bool($input['sections'][$key]??$default);$founder=mg_investment_long_text($input['founder_update']??'',12000)?:null;$notice=mg_investment_long_text($input['important_notice']??'',6000)?:null;$reason=mg_investment_text($input['change_reason']??'',500,8,'Publication change reason');$actorId=(int)$actor['id'];
    return mg_investment_audit_transaction($pdo,function()use($pdo,$round,$status,$sections,$founder,$notice,$reason,$actorId):array{
        $q=$pdo->prepare('SELECT * FROM investment_round_publication WHERE round_id=? LIMIT 1 FOR UPDATE');$q->execute([(int)$round['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);$version=(int)($current['current_version_number']??0)+1;
        if($current&&$current['publication_status']==='archived'&&$status!=='archived')throw new MgInvestmentException('Archived round publications cannot be reopened. Create a new official round.',409);
        if(!$current){$pdo->prepare('INSERT INTO investment_round_publication (round_id,publication_status,current_version_number,sections_json,founder_update,important_notice,published_by_user_id,published_at,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,IF(? IN ("private_preview","published"),?,NULL),IF(? IN ("private_preview","published"),NOW(),NULL),?,NOW(),NOW())')->execute([(int)$round['id'],$status,$version,mg_investment_json_encode($sections),$founder,$notice,$status,$actorId,$status,$actorId]);}
        else{$pdo->prepare('UPDATE investment_round_publication SET publication_status=?,current_version_number=?,sections_json=?,founder_update=?,important_notice=?,published_by_user_id=IF(? IN ("private_preview","published"),?,published_by_user_id),published_at=IF(? IN ("private_preview","published"),COALESCE(published_at,NOW()),published_at),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$status,$version,mg_investment_json_encode($sections),$founder,$notice,$status,$actorId,$status,$actorId,(int)$current['id']]);}
        $pdo->prepare('INSERT INTO investment_round_publication_versions (public_id,round_id,version_number,publication_status,sections_json,founder_update,important_notice,change_reason,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')->execute([mg_investment_uuid(),(int)$round['id'],$version,$status,mg_investment_json_encode($sections),$founder,$notice,$reason,$actorId]);mg_audit('investment_round_publication_saved','investment_round',['round_id'=>$round['public_id'],'publication_status'=>$status,'version'=>$version,'sections'=>$sections],$actorId);return mg_investment_publication_preview($pdo,(string)$round['public_id']);
    });
}
