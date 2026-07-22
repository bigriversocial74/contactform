<?php
declare(strict_types=1);
$placeholderViews=[];
if($merchantView==='overview'):
    require __DIR__.'/merchant-overview-dashboard.php';
elseif($merchantView==='notifications'):
    require __DIR__.'/merchant-notifications-view.php';
elseif($merchantView==='onboarding'):
    ?><section class="mg-merchant-heading"><div><span class="mg-eyebrow">Activation</span><h1>Merchant onboarding</h1><p>Finish the ordered setup path and verify the workspace before beta launch.</p></div></section><section class="mg-app-panel"><div class="mg-app-panel-body"><div class="mg-onboarding-list" data-merchant-onboarding></div></div></section><?php
elseif($merchantView==='products'):
    require __DIR__.'/merchant-products-view.php';
elseif($merchantView==='bundles'):
    require __DIR__.'/merchant-bundles-view.php';
elseif($merchantView==='bundle_invitations'):
    require __DIR__.'/merchant-bundle-invitations-view.php';
elseif($merchantView==='reward_templates'):
    require __DIR__.'/merchant-reward-templates-view.php';
elseif($merchantView==='campaigns'):
    require __DIR__.'/merchant-campaigns-view.php';
elseif($merchantView==='creator_campaigns' && !empty($merchantCreatorParticipation)):
    require __DIR__.'/merchant-creator-campaign-participation-view.php';
elseif($merchantView==='creator_campaigns'):
    require __DIR__.'/merchant-creator-campaigns-view.php';
elseif($merchantView==='creator_campaign_builder'):
    require __DIR__.'/merchant-creator-campaign-builder-view.php';
elseif($merchantView==='loyalty_quests'):
    require __DIR__.'/merchant-loyalty-quests-view.php';
elseif($merchantView==='quest_creative'):
    require __DIR__.'/merchant-loyalty-quest-creative-view.php';
elseif($merchantView==='quest_reviews'):
    require __DIR__.'/merchant-loyalty-quest-reviews-view.php';
elseif($merchantView==='quest_delivery'):
    require __DIR__.'/merchant-loyalty-quest-delivery-view.php';
elseif($merchantView==='quest_analytics'):
    require __DIR__.'/merchant-loyalty-quest-analytics-view.php';
elseif($merchantView==='merchant_crm'):
    require __DIR__.'/merchant-crm-view.php';
elseif($merchantView==='reviews'):
    require __DIR__.'/merchant-reviews-view.php';
elseif($merchantView==='campaign_stamps'):
    require __DIR__.'/merchant-campaign-stamps-view.php';
elseif($merchantView==='stamps'):
    require __DIR__.'/merchant-stamps-view.php';
elseif($merchantView==='product_detail'):
    require __DIR__.'/merchant-product-detail-view.php';
elseif($merchantView==='media'):
    require __DIR__.'/merchant-media-view.php';
elseif($merchantView==='storefront'):
    require __DIR__.'/merchant-storefront-view.php';
elseif($merchantView==='merchant_pwa'):
    require __DIR__.'/merchant-pwa-view.php';
elseif($merchantView==='storefront_preview'):
    require __DIR__.'/merchant-storefront-preview-view.php';
elseif($merchantView==='orders'):
    require __DIR__.'/merchant-orders-view.php';
elseif($merchantView==='pppm'):
    require __DIR__.'/merchant-pppm-view.php';
elseif($merchantView==='pppm_item'):
    require __DIR__.'/merchant-pppm-item-view.php';
elseif($merchantView==='distribution'):
    require __DIR__.'/merchant-distribution-view.php';
elseif($merchantView==='distribution_program'):
    require __DIR__.'/merchant-distribution-program-view.php';
elseif($merchantView==='developer_api'):
    require __DIR__.'/merchant-developer-api-view.php';
elseif($merchantView==='hosted_games'):
    require __DIR__.'/merchant-hosted-games-view.php';
elseif($merchantView==='intelligence'):
    require __DIR__.'/merchant-intelligence-view.php';
elseif($merchantView==='claims'):
    require __DIR__.'/merchant-claims-view.php';
elseif($merchantView==='claim_detail'):
    require __DIR__.'/merchant-claim-detail-view.php';
elseif($merchantView==='payments'):
    require __DIR__.'/merchant-payments-view.php';
elseif($merchantView==='settings'):
    ?><section class="mg-merchant-heading"><div><span class="mg-eyebrow">Configuration</span><h1>Business settings</h1><p>Manage the identity and defaults used across products, locations, claims, and future commerce.</p></div></section><section class="mg-app-panel"><div class="mg-app-panel-body"><form class="mg-merchant-form" data-merchant-settings-form><div class="mg-grid-2"><label>Display name<input name="display_name" required></label><label>Legal name<input name="legal_name"></label></div><div class="mg-grid-2"><label>Business type<input name="business_type"></label><label>Website<input name="website_url" type="url"></label></div><div class="mg-grid-2"><label>Support email<input name="support_email" type="email"></label><label>Support phone<input name="support_phone"></label></div><div class="mg-grid-2"><label>Default currency<select name="default_currency"><option>USD</option><option>CAD</option><option>EUR</option><option>GBP</option></select></label><label>Timezone<input name="timezone" value="UTC"></label></div><div class="mg-form-status" data-merchant-form-status></div><button class="mg-btn mg-btn-primary" type="submit">Save business settings</button></form></div></section><?php
elseif($merchantView==='locations'):
    require __DIR__.'/merchant-locations-view.php';
elseif($merchantView==='team'):
    require __DIR__.'/merchant-team-view.php';
elseif($merchantView==='integrations'):
    require __DIR__.'/merchant-integrations-view.php';
elseif(isset($placeholderViews[$merchantView])):
    ?><section class="mg-merchant-heading"><div><span class="mg-eyebrow">Merchantİ½É­ÍÁ…”ğ½ÍÁ…¸øñ Äøğüôµ}”¡Õİ½É‘Ì¡ÍÑÉ}É•Á±…” |œ°œ€œ°‘µ•É¡…¹ÑY¥•Ü¤¤¤€üøğ½ ÄøñÀøğüôµ}” ‘Á±…•¡½±‘•ÉY¥•İÍl‘µ•É¡…¹ÑY¥•İt¤€üøğ½Àøğ½‘¥Øøğ½Í•Ñ¥½¸øñÍ•Ñ¥½¸±…ÍÌô‰µœµ…ÁÀµÁ…¹•°ˆøñ‘¥Ø±…ÍÌô‰µœµ…ÁÀµÁ…¹•°µ‰½‘äˆøñ‘¥Ø±…ÍÌô‰µœµ•µÁÑäµÍÑ…Ñ”ˆøñÍÑÉ½¹œù]½É­ÍÁ…”É½ÕÑ”É•Í•ÉÙ•ğ½ÍÑÉ½¹œøñÀùQ¡”½Á•É…Ñ¥¹œµ½‘Õ±”İ¥±°‰”½¹¹•Ñ•¥¸¥ÑÌÁ±…¹¹•MÑ…”€ÔÍÕ‰Á¡…Í”¸ğ½Àøğ½‘¥Øøğ½Í•Ñ¥½¸øğıÁ¡À)•¹‘¥˜ì