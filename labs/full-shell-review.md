# Training Lab Stage 1 Full Shell Review

Branch:

```text
training-lab-stage-1-ui-shell
```

Target host:

```text
https://labs.microgifter.com
```

## Review scope

```text
Public pages
Participant app pages
Backend pages
Shared layout
Shared CSS
Shared component helpers
Route notes
Syntax check report
Image placeholder system
```

## Public flow checklist

```text
/ -> signup.php
/ -> how-it-works.php
pricing.php -> cart.php
cart.php -> checkout.php
checkout.php -> success.php
success.php -> receipt.php
receipt.php -> app/index.php
blog.php -> blog-article.php
contact.php visual form only
```

## Participant flow checklist

```text
app/index.php -> campaigns.php
campaigns.php -> campaign-detail.php
campaign-detail.php -> sequence-tasks.php
sequence-tasks.php -> proof-upload.php
app/index.php -> rewards.php
rewards.php -> wallet.php
```

## Backend flow checklist

```text
admin/index.php -> review-queue.php
admin/index.php -> campaigns.php
admin/campaigns.php -> review-queue.php
```

## Visual checks

```text
Header is consistent
Footer is consistent
Public nav scrolls on mobile
Workspace sidebar scrolls on mobile
Cards stack on mobile
Tables scroll horizontally on mobile
Image slots display without missing asset errors
Buttons are visual or static links only
Forms do not submit to real handlers
```

## Stage 1 safety checks

```text
No database writes
No real upload handling
No payment integration
No wallet balance changes
No claim/redeem logic
No separate account system
No production DNS or hosting config changes
```

## Next recommended pass

```text
Run local php -l syntax check across all labs/*.php, labs/app/*.php, and labs/admin/*.php.
Replace image placeholder slots with approved image assets.
Review browser flow from public pages to app and backend.
```
