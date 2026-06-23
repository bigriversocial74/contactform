# Index image link audit

This document tracks the landing page image references that must be present under `/images/`.

Required references:

```txt
/images/microgifter-table-tent-phone-removebg-preview.png
/images/reward_saved_limited_coffee_for_two.png
/images/reward_saved_coffee_for_two.png
/images/coffee_for_two_reward_screen.png
```

Run this validator before merge:

```bash
php scripts/validate_index_image_links.php
```

The validator checks every `/images/...` reference in `index.php` and fails if any referenced file is missing.
