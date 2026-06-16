# Microgifter Stage 1 Build Manifest

Stage 1 establishes the identity, onboarding, permission, and deployment-preflight foundation for Microgifter.

This manifest does not create a ZIP package. The GitHub repository is the source of truth.

## Active root pages

- `index.php` — public landing and onboarding entry
- `build.php` — guest/test builder entry with account unlock flow
- `agent.php` — permission-aware agent workspace shell
- `signin.php` — sign-in page
- `signup.php` — account creation page
- `forgot-password.php` — password recovery request page
- `reset-password.php` — password reset page
- `verify-email.php` — email verification page
- `account.php` — authenticated account dashboard

## Preserved prototype pages

The original root HTML files are retained as current reference/prototype files while the active app moves to PHP:

- `index.html`
- `build.html`
- `agent.html`

## Shared PHP includes

- `includes/app.php` — shared page bootstrap
- `includes/auth.php` — page-level auth helpers
- `includes/csrf.php` — CSRF token helpers
- `includes/permissions.php` — page-level permission helpers
- `includes/header.php` — shared header and account navigation
- `includes/footer.php` — shared footer and script loading
- `includes/layout.php` — shared layout helpers

## API foundation

- `api/config.php` — environment-backed configuration
- `api/bootstrap.php` — API bootstrap, auth, permission, audit, and event helpers
- `api/db.php` — database connection helper
- `api/response.php` — JSON response helper

## Auth endpoints

- `api/auth/register.php`
- `api/auth/login.php`
- `api/auth/logout.php`
- `api/auth/me.php`
- `api/auth/password/forgot.php`
- `api/auth/password/reset.php`
- `api/auth/email/verify-request.php`
- `api/auth/email/verify.php`

## Role/admin endpoints

- `api/roles/index.php`
- `api/permissions/index.php`
- `api/admin/users.php`
- `api/admin/audit-logs.php`

## Stylesheets

- `assets/css/microgifter.css` — global design system
- `assets/css/sections/builder.css` — builder-specific layout
- `assets/css/sections/agent.css` — agent-specific layout
- `assets/css/sections/social.css` — future social module styles
- `assets/css/sections/ecommerce.css` — future ecommerce module styles
- `assets/css/sections/pppm.css` — future PPPM/program module styles

## JavaScript modules

- `assets/js/microgifter.js` — global helpers and UI utilities
- `assets/js/api-client.js` — shared API client
- `assets/js/auth.js` — auth form handling and account actions
- `assets/js/onboarding.js` — guest-to-account onboarding helpers
- `assets/js/builder.js` — builder page behavior
- `assets/js/agent.js` — agent page behavior
- `assets/js/social.js` — future social module placeholder
- `assets/js/commerce.js` — future commerce module placeholder
- `assets/js/programs.js` — future PPPM/program module placeholder

## Database

- `database/stage_1_identity.sql`

Expected Stage 1 tables include:

- `users`
- `user_profiles`
- `roles`
- `permissions`
- `role_permissions`
- `user_roles`
- `user_sessions`
- `password_reset_tokens`
- `email_verification_tokens`
- `audit_logs`
- `events`

## Installation and security docs

- `docs/installation/stage_1_installation_and_local_test_guide.md`
- `docs/installation/first_run_admin_setup.md`
- `docs/installation/stage_1_server_preflight_checklist.md`
- `docs/security/stage_1_server_security_checklist.md`
- `docs/security/sensitive_file_access_policy.md`

## Testing docs

- `tests/stage_1_auth_smoke_checklist.md`
- `tests/stage_1_api_curl_smoke_examples.md`

## Stage build notes completed

- `docs/stages/03B_existing_ui_asset_extraction.md`
- `docs/stages/03C_js_cleanup_and_auth_flow.md`
- `docs/stages/03D_script_loading_and_api_client_cleanup.md`
- `docs/stages/03E_auth_endpoint_alignment.md`
- `docs/stages/03G_current_user_and_audit_endpoint_fix.md`
- `docs/stages/03H_logout_account_header_flow.md`
- `docs/stages/03I_auth_page_polish_and_smoke_tests.md`
- `docs/stages/03J_stage1_installation_and_local_test_guide.md`
- `docs/stages/03K_stage1_preflight_and_config_hardening.md`
- `docs/stages/03L_stage1_final_review_and_manifest.md`

## Stage 1 status

Stage 1 is ready for server/database installation testing after configuring environment values and importing the SQL schema.

Stage 1 should not yet be considered production launched until the smoke checklist is executed on the target server and any server-specific issues are resolved.
