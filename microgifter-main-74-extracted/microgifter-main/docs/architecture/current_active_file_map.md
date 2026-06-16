# Current Active File Map

This file records the active Stage 1 runtime files after the PHP-only cleanup.

## Source of truth

GitHub repository:

```text
stonefellow74-debug/microgifter
```

HostGator/cPanel is the current staging/private build runtime.

## Active public PHP pages

```text
index.php
build.php
agent.php
signup.php
signin.php
account.php
```

## Active API endpoints for Stage 1

```text
api/health.php
api/auth/register.php
api/auth/login.php
api/auth/logout.php
api/me/profile.php
api/me/sessions.php
api/admin/security-logs.php
```

## Active shared includes

```text
includes/app.php
includes/header.php
includes/footer.php
includes/runtime.php
includes/ids.php
includes/authorization.php
includes/delivery.php
```

## Active global assets

```text
assets/css/microgifter.css
assets/js/microgifter.js
assets/js/api-client.js
assets/js/auth.js
assets/js/auth-state.js
assets/js/onboarding.js
```

## Section CSS files

```text
assets/css/sections/agent.css
assets/css/sections/builder.css
assets/css/sections/social.css
assets/css/sections/ecommerce.css
assets/css/sections/pppm.css
```

## Local-only config

This file should exist on HostGator but should not be committed with real credentials:

```text
api/config.local.php
```

The safe template is:

```text
api/config.local.example.php
```

## Deleted/non-runtime prototype files

These files must not be active runtime files:

```text
index.html
build.html
builder.html
agent.html
signin.html
signup.html
```

Old HTML URLs should return `410 Gone`.

## Current database install file

For a fresh database install, use:

```text
database/compiled/microgifter_stage_1_current_compiled.sql
```

Do not import individual migrations after importing the compiled SQL into a fresh database.

## Current runtime profile

HostGator staging mode:

```text
MG_RUNTIME_PROFILE=hostgator
MG_ENABLE_POLLING_NOTIFICATIONS=true
MG_ENABLE_DB_OUTBOX=true
MG_ENABLE_QUEUE_WORKER=false
MG_ENABLE_REDIS=false
MG_ENABLE_WEBSOCKETS=false
MG_ENABLE_SSE=false
```

## Next implementation stage

Recommended next stage:

```text
04A_microgifter_product_and_gift_schema_design
```
