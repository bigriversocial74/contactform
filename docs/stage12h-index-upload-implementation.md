# Stage 12H uploaded landing/profile implementation

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

Root `index.php` explicitly requires those parts in order.

After PR #85 was merged, `profile.php` was hotfixed on `main` with the current uploaded merchant/investment profile page. The profile keeps the public profile runtime/storefront/engagement scripts and adds the investment profile stylesheet/script.

The obsolete staged replacement files and instructions were removed. The leftover neutralized `stage12h/profile.php.replacement` artifact was also removed from `main` after the merge.

The uploaded index image dependencies were added to avoid broken-image fallbacks:

```txt
/images/cosmic_golden_network_on_black.png
/images/merchant_analytics_dashboard_design.png
/images/limited_coffee_for_two_reward_screen.png
/images/nearby_offers_in_belmont_ca.png
/images/sleek_saas_ui_with_gold_accents.png
```
