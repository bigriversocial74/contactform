# 03Z Microgifter Stage 1 Owner Admin Email Finalization

## Purpose

This pass closes the last Stage 1 adjacent foundation items before Stage 2 profile and merchant identity work.

It defines platform ownership, adds a safe first owner bootstrap path, adds a provider neutral email template layer, and wires registration to attempt email verification without breaking account creation.

## Super admin rule

The first real platform owner can be user 1, but runtime authorization must remain role based.

Approved rule: if no user currently has the super_admin role, promote user 1 to super_admin once and write an audit log.

Rejected rule: always treat user_id 1 as super admin.

The source of truth is the roles and user_roles permission model.

## Added files

- includes/mail.php
- scripts/bootstrap_super_admin.php
- database/03Z_bootstrap_super_admin_user1.sql
- docs/stages/03Z_stage1_owner_admin_email_finalization.md

## Updated files

- includes/app.php
- api/auth/register.php
- api/config.local.example.php

## Mail behavior

The new mail helper supports provider neutral templates.

Current modes:

- log: default safe mode; writes mail metadata to the server log.
- mail: PHP mail mode when explicitly enabled in local config.

Future adapters can be added later without changing auth endpoints.

Default committed behavior is safe. Mail is disabled/log-only unless enabled in api/config.local.php.

## Templates added

- email_verification
- password_reset
- security_alert
- welcome

Registration now attempts to create an email verification token and queue the verification email after account creation.

If the token table or mail provider is unavailable, registration should still succeed. The problem is logged server-side and does not block the account flow.

## Super admin bootstrap options

Option A: run database/03Z_bootstrap_super_admin_user1.sql through phpMyAdmin.

Option B: if terminal access is available, run php scripts/bootstrap_super_admin.php.

The script refuses web/browser execution.

## Acceptance checks

- /api/health.php returns database connected.
- /signup.php still creates a new test account.
- Registration still redirects to /account.php.
- PHP error log does not show fatal mail errors.
- User 1 can be promoted to super_admin once.
- Super admin access is role based, not hardcoded to ID 1.

## Stage boundary

This pass does not build the full admin dashboard. Full admin UI expansion should wait until there are real Stage 2 or later objects to manage.

Stage 2 should still begin with 02A_microgifter_profiles_creator_merchant_identity_schema_design.
