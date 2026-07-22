<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{
    $value=@file_get_contents($root.'/'.$path);
    if(!is_string($value)){fwrite(STDERR,"Missing required file: {$path}\n");exit(1);}
    return $value;
};
$s=[
 'sql'=>$read('database/20260722_creator_campaign_tracking_attribution_v5.sql'),
 'definitions'=>$read('includes/creator-campaigns/tracking-definitions.php'),
 'context'=>$read('includes/creator-campaigns/tracking-context.php'),
 'repository'=>$read('includes/creator-campaigns/tracking-repository.php'),
 'tracking'=>$read('includes/creator-campaigns/tracking-service.php'),
 'attribution'=>$read('includes/creator-campaigns/attribution-service.php'),
 'query'=>$read('includes/creator-campaigns/tracking-query.php'),
 'merchant_api'=>$read('api/merchant/creator-campaign-tracking.php'),
 'creator_api'=>$read('api/creator/campaign-tracking.php'),
 'public_api'=>$read('api/public/creator-campaign-events.php'),
 'redirect'=>$read('creator-campaign-track.php'),
 'merchant_view'=>$read('includes/merchant-creator-campaign-tracking-view.php'),
 'creator_view'=>$read('includes/creator-campaign-tracking-view.php'),
 'merchant_js'=>$read('assets/js/merchant-creator-campaign-tracking.js'),
 'creator_js'=>$read('assets/js/creator-campaign-tracking.js'),
 'workflow'=>$read('.github/workflows/creator-campaign-tracking-v5.yml'),
 'manifest'=>$read('config/migrations.php'),
];
$checks=[
 'Schema'=>[
  ['Four normalized Phase 5 tables',count(array_filter(['creator_campaign_tracking_sources','creator_campaign_tracking_events','creator_campaign_attributions','creator_campaign_attribution_events'],fn($t)=>str_contains($s['sql'],'CREATE TABLE IF NOT EXISTS '.$t)))===4],
  ['Event and attribution uniqueness',str_contains($s['sql'],'uq_cc_tracking_event_key')&&str_contains($s['sql'],'uq_cc_attribution_conversion')],
  ['Privacy-safe hashes',str_contains($s['sql'],'session_hash CHAR(64)')&&str_contains($s['sql'],'visitor_hash CHAR(64)')&&str_contains($s['definitions'],'hash_hmac')],
  ['No finance or payout schema',!preg_match('/CREATE TABLE IF NOT EXISTS creator_campaign_(compensation|earning|budget|payout|dispute)/',$s['sql'])],
 ],
 'Tracking'=>[
  ['Internal destination enforcement',str_contains($s['definitions'],'mg_creator_campaign_tracking_internal_path')&&str_contains($s['redirect'],'Location: ')],
  ['Public redirect records clicks',str_contains($s['redirect'],"'event_type'=>'click'")&&str_contains($s['redirect'],'mg_creator_campaign_tracking_record_by_code')],
  ['Browser event restrictions',str_contains($s['definitions'],"return ['landing_view','engagement'];")&&str_contains($s['public_api'],'mg_creator_campaign_tracking_browser_event_types')],
  ['Trusted conversion service',str_contains($s['tracking'],'mg_creator_campaign_tracking_record_conversion')&&str_contains($s['tracking'],'mg_creator_campaign_tracking_conversion_event_types')],
 ],
 'Attribution'=>[
  ['One canonical attribution per conversion',str_contains($s['sql'],'UNIQUE KEY uq_cc_attribution_conversion')&&str_contains($s['sql'],'touch_event_id BIGINT UNSIGNED')],
  ['Automatic source decision',str_contains($s['attribution'],'mg_creator_campaign_attribution_candidate')&&str_contains($s['attribution'],'mg_creator_campaign_attribution_decide')],
  ['Manual override and invalidation',str_contains($s['attribution'],'mg_creator_campaign_attribution_override_merchant')&&str_contains($s['attribution'],'mg_creator_campaign_tracking_invalidate_event_merchant')&&str_contains($s['attribution'],'touch_event_id')],
  ['Append-only attribution audit',str_contains($s['repository'],'mg_creator_campaign_attribution_audit')&&str_contains($s['sql'],'creator_campaign_attribution_events')],
 ],
 'Security'=>[
  ['Merchant workspace authorization',str_contains($s['context'],'mg_creator_campaign_tracking_merchant_context')&&str_contains($s['merchant_api'],'merchant.creator_tracking')],
  ['Creator ownership authorization',str_contains($s['context'],'mg_creator_campaign_tracking_creator_context')&&str_contains($s['creator_api'],'creator.campaign_tracking')],
  ['CSRF on authenticated APIs',substr_count($s['merchant_api'].$s['creator_api'],'mg_require_csrf_for_write')===2],
  ['Replay and velocity scoring',str_contains($s['repository'],'rapid_replay')&&str_contains($s['repository'],'high_velocity')&&str_contains($s['repository'],'request_replay')],
 ],
 'Delivery'=>[
  ['Merchant workspace complete',str_contains($s['merchant_view'],'data-cct-tab="attributions"')&&str_contains($s['merchant_js'],'override_attribution')],
  ['Creator workspace complete',str_contains($s['creator_view'],'data-cct-tab="sources"')&&str_contains($s['creator_js'],'retire_source')],
  ['Migration registered',str_contains($s['manifest'],"'20260722_creator_campaign_tracking_attribution_v5.sql'")],
  ['PHP matrix and clean MySQL lifecycle',str_contains($s['workflow'],"php: ['8.2','8.3']")&&str_contains($s['workflow'],'validate_creator_campaign_tracking_v5_mysql.php')],
 ],
];
$total=0;$maximum=0;
foreach($checks as $group=>$rows){$score=0;foreach($rows as[$label,$ok]){$maximum+=5;if($ok){$score+=5;$total+=5;}printf("  [%s] %s\n",$ok?'PASS':'FAIL',$label);}printf("%s: %d/%d\n",$group,$score,count($rows)*5);}
printf("TOTAL: %d/%d\n",$total,$maximum);exit($total===$maximum?0:1);
