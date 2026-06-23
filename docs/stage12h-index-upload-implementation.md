# Stage 12H uploaded index implementation

The uploaded black/gold Microgifter landing page has been wired as the root `index.php` page through ordered include parts under:

```txt
includes/landing/index-v3/part01.php
includes/landing/index-v3/part02.php
includes/landing/index-v3/part03.php
includes/landing/index-v3/part04.php
includes/landing/index-v3/part05.php
includes/landing/index-v3/part06.php
includes/landing/index-v3/part07.php
includes/landing/index-v3/part08.php
includes/landing/index-v3/part09.php
includes/landing/index-v3/part10.php
includes/landing/index-v3/part11.php
includes/landing/index-v3/part12.php
```

Root `index.php` now explicitly requires those parts in order.

`profile.php` was not modified.

The obsolete staged replacement instructions were removed where possible. The connector blocked deleting `stage12h/profile.php.replacement`, so that file was neutralized and now contains only a do-not-apply marker.

Known follow-up: several image references used by the uploaded index source are still missing from the branch and should be added before visual QA:

```txt
/images/cosmic_golden_network_on_black.png
/images/merchant_analytics_dashboard_design.png
/images/limited_coffee_for_two_reward_screen.png
/images/nearby_offers_in_belmont_ca.png
/images/sleek_saas_ui_with_gold_accents.png
```
