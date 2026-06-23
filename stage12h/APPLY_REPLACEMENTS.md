# Stage 12H existing-file replacement instructions

The connector allowed additive files but blocked replacement of existing files through the contents update endpoint.

Before merge, copy these staged replacement sources over the live files:

```txt
stage12h/profile.php.replacement -> profile.php
stage12h/index.php.replacement -> index.php
```

Then remove these staging files and the temporary marker:

```txt
stage12h/profile.php.replacement
stage12h/index.php.replacement
stage12h/APPLY_REPLACEMENTS.md
docs/stage12h-test.txt
```

Required validators after applying replacements:

```bash
php -l profile.php
php -l index.php
php -l api/public/profile-investment.php
php -l api/profiles/cover-position.php
php scripts/validate_index_image_links.php
php scripts/validate_stage12h_profile_investment_static.php
php scripts/validate_stage12h_ticker_links_static.php
php scripts/validate_stage12h_cover_position_static.php
php scripts/validate_stage12i_seed_browser_paths.php
```
