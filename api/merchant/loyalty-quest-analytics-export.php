<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__,2) . '/includes/loyalty-quest-analytics.php';
require_once dirname(__DIR__,2) . '/includes/loyalty-quest-analytics-accuracy.php';

mg_require_method('GET');
$user=mg_merchant_require_permission('merchant.campaigns.view');
mg_require_permission('intelligence.exports.create');
$merchantId=(int)$user['id'];
$pdo=mg_db();
mg_merchant_ensure_workspace($pdo,$user);

function mg_lqa_export_cell(mixed $value): string
{
    if($value===null)return '';
    if(is_bool($value))return $value?'true':'false';
    $text=(string)$value;
    if($text!==''&&in_array($text[0],['=','+','-','@'],true))$text="'".$text;
    return $text;
}

try{
    $days=mg_lqa_days($_GET['days']??30);
    $campaignRef=mg_lqa_campaign_ref($_GET['campaign_id']??'');
    $format=strtolower(trim((string)($_GET['format']??'csv')));
    if(!in_array($format,['csv','json'],true))mg_fail('Export format must be CSV or JSON.',422);
    $report=mg_lqa_apply_accuracy($pdo,$merchantId,mg_lqa_report($pdo,$merchantId,$days,$campaignRef));
    $suffix=$campaignRef!==''?'-'.substr($campaignRef,0,8):'-all';
    $filename='microgifter-loyalty-quest-analytics-'.$report['date_from'].'-to-'.$report['date_to'].$suffix.'.'.$format;
    mg_audit('merchant.loyalty_quest_analytics_exported','campaign',['days'=>$days,'campaign_id'=>$campaignRef?:null,'format'=>$format,'contains_personal_data'=>false,'currency_values_combined'=>false],$merchantId);
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    if($format==='json'){
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['generated_at'=>gmdate('c'),'report'=>$report],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }else{
        header('Content-Type: text/csv; charset=UTF-8');
        $out=fopen('php://output','wb');
        if($out===false)throw new RuntimeException('Unable to create export.');
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['Microgifter Loyalty Quest Analytics']);
        fputcsv($out,['Date from',$report['date_from'],'Date to',$report['date_to'],'Days',$report['days']]);
        fputcsv($out,[]);
        fputcsv($out,['Summary metric','Value']);
        foreach($report['summary'] as $key=>$value)fputcsv($out,[mg_lqa_export_cell($key),mg_lqa_export_cell($value)]);
        fputcsv($out,[]);fputcsv($out,['Currency','Inbox delivered','Redeemed','Issued value cents','Redeemed value cents']);
        foreach($report['value_by_currency'] as $row)fputcsv($out,array_map('mg_lqa_export_cell',[$row['currency'],$row['inbox_delivered'],$row['redeemed'],$row['issued_value_cents'],$row['redeemed_value_cents']]));
        fputcsv($out,[]);
        $headers=['Campaign ID','Campaign','Status','Contacts','Participants','In progress','Pending review','Completed','Completion rate %','Evidence verified','Evidence rejected','Evidence approval rate %','Inbox delivered','Claimed','Redeemed','Claim rate %','Redemption rate %','Currency','Issued value cents','Redeemed value cents','Average completion minutes','Average review minutes','Average redemption minutes','Delivery total','Delivery success rate %'];
        fputcsv($out,$headers);
        foreach($report['campaigns'] as $row){
            fputcsv($out,array_map('mg_lqa_export_cell',[
                $row['id'],$row['title'],$row['status'],$row['contacts'],$row['participants'],$row['in_progress'],$row['pending_review'],$row['completed'],$row['completion_rate'],$row['evidence_verified'],$row['evidence_rejected'],$row['evidence_approval_rate'],$row['inbox_delivered'],$row['claimed'],$row['redeemed'],$row['claim_rate'],$row['redemption_rate'],$row['currency'],$row['issued_value_cents'],$row['redeemed_value_cents'],$row['avg_completion_minutes'],$row['avg_review_minutes'],$row['avg_redemption_minutes'],$row['delivery_total'],$row['delivery_success_rate'],
            ]));
        }
        fputcsv($out,[]);fputcsv($out,['Verification type','Total','Verified','Rejected','Approval rate %','Average distance meters (groups of 5+)']);
        foreach($report['verification'] as $row)fputcsv($out,array_map('mg_lqa_export_cell',[$row['type'],$row['total'],$row['verified'],$row['rejected'],$row['approval_rate'],$row['avg_distance_meters']]));
        fputcsv($out,[]);fputcsv($out,['Source','Participants','Completed','Completion rate %']);
        foreach($report['sources'] as $row)fputcsv($out,array_map('mg_lqa_export_cell',[$row['source'],$row['participants'],$row['completed'],$row['completion_rate']]));
        fputcsv($out,[]);fputcsv($out,['Privacy note','This export contains aggregate campaign metrics only. It excludes names, emails, user IDs, proof content, claim codes, QR secrets, and precise coordinates. Currency values remain separated by ISO currency.']);
        fclose($out);
    }
    exit;
}catch(InvalidArgumentException $error){mg_fail($error->getMessage(),422);}catch(RuntimeException $error){mg_fail($error->getMessage(),str_contains($error->getMessage(),'require')?409:404);}catch(Throwable $error){mg_security_log('error','merchant.loyalty_quest_analytics_export_failed','Unable to export Loyalty Quest analytics.',['exception_class'=>$error::class],$merchantId);mg_fail('Unable to export Loyalty Quest analytics.',500);}
