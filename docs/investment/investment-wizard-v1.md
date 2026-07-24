# Investor Role & Investment Wizard v1

## Purpose

This release adds a governed Investor identity and planning system to Microgifter. It is an administrative planning and private-information portal. It does not issue securities, process investment funds, verify accreditation, create legally binding agreements, or replace the company’s official stock ledger or professional legal and financial review.

## Entry points

- `/investor-access.php` — authenticated Investor access application.
- `/admin/investor-access-requests.php` — Super Admin review queue.
- `/admin/investment-wizard.php` — saved company, scenario, round, evidence and AI planning workspace.
- `/investor-portal.php` — protected approved-investor portal.

## Initial setup

1. Import `database/20260723_investor_role_investment_wizard_v1_single_install.sql`.
2. Upload the branch code.
3. Sign in as Super Admin and open `/admin/investment-wizard.php`.
4. Create the first workspace. The system seeds:
   - Minimum Launch — $250K
   - Balanced Growth — $500K
   - Full Market Launch — $750K
5. Enter company, capitalization, operating plan and assumption data.
6. Save and compare scenarios before adopting one as an official planning round.

## Data boundaries

- Roles remain additive. Investor approval does not remove Customer, Merchant, Creator, or other roles.
- Access request status and the `investor` role are separate authorities.
- Scenario calculations are deterministic PHP calculations.
- Official rounds freeze the complete scenario, company, capitalization, budget, goal and projection snapshot at adoption.
- Every official-round update creates a new immutable version with a required change reason.
- Claude output is stored as draft analysis only and cannot alter roles, official terms, funded amounts, round status, or published content.
- Investor Portal data is aggregated and reads only approved round visibility, approved evidence metrics, and published documents.

## Claude configuration

The build reuses Microgifter’s existing `includes/ai/anthropic-client.php` client.

Environment variables:

```text
MG_ANTHROPIC_API_KEY=...
MG_INVESTMENT_CLAUDE_MODEL=<optional enabled Anthropic model override>
MG_ANTHROPIC_TIMEOUT_SECONDS=45
```

When no override is set, the system selects the enabled default Anthropic model from Microgifter’s canonical AI model catalog. No API key is stored in the database or browser. Claude requests occur only when a permitted administrator presses the analysis button.

## Privacy and retention

The migration registers retention categories for Investor access profiles and official investment-round records. Investor role access is removed during revocation, related sessions are revoked, and selected-round access is closed. Corporate, accounting, audit and legal evidence is retained under the existing privacy-retention authority.

## Validation

```bash
php scripts/validate_investor_role_investment_wizard_v1.php
```
