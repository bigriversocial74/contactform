# Security Policy

## Supported code

Security fixes are prepared against the active deployment line:

- `integration-from-repair-20260628`

`main` is supported when the active deployment line has been intentionally synchronized into it. Historical feature, recovery, and experiment branches are not supported release lines.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability, leaked credential, authorization bypass, payment defect, ownership defect, or private-data exposure.

Use GitHub private vulnerability reporting when it is available for this repository. Otherwise, contact the repository administrator through an established private channel and include:

- The affected route, file, or workflow.
- Reproduction steps using non-production data.
- The user role and authorization state involved.
- The expected and observed behavior.
- Potential impact on identity, ownership, redemption, payments, notifications, or merchant data.
- Any suggested remediation.

Never include production credentials, claim codes, payment tokens, private customer data, or reusable session material in a report.

## Response expectations

A report should be acknowledged as soon as practical. Confirmed issues are prioritized by impact:

1. Credential exposure, remote code execution, payment or ledger corruption, cross-account access, or gift-ownership bypass.
2. Authentication, authorization, claim, redemption, notification, or private-data defects.
3. Availability, integrity, information-disclosure, and defense-in-depth issues.

A fix must use a scoped branch and pull request, include a regression test or validator, state whether SQL is required, and pass the repository production-quality and relevant module workflows before merge.

## Security boundaries

- Commerce owns order truth.
- Signed provider webhooks own payment confirmation.
- The ledger owns financial truth.
- PPPM and Microgift instances own issued-unit identity and ownership.
- Merchant locations and claim codes own redemption authority.
- The Action Center is a read model and must not become a separate ownership or payment authority.
- Production secrets belong in environment variables or ignored `api/config.local.php`, never in tracked source.
