# Stage 9E Source of Truth Note

The canonical PPPM ownership service after this pass is:

`mg_pppm_transfer_owner_canonical()` in `api/pppm/_ownership.php`

The canonical PPPM redemption service is:

`mg_pppm_redeem()` in `api/pppm/_pppm.php`

Microgift claim and redemption flows now call these canonical services. Future code should not directly mutate `pppm_items.owner_user_id` or `pppm_items.status` from Microgift, entitlement, wallet, commerce, or admin endpoints.
