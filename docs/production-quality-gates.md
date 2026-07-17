# Production Quality Gates

Microgifter uses a reproducible **100-point production-quality audit**. The audit is implemented by `scripts/audit_repository_production_quality.php` and enforced by `.github/workflows/repository-production-quality.yml` on PHP 8.2 and PHP 8.3.

A score of **10/10** means every documented automated gate passed for the audited commit. It does not mean software can never contain a future defect, and it does not replace production browser QA, payment-provider testing, database backup verification, or human review.

## Scoring categories

Each category is worth 10 points:

1. **Runtime correctness** — strict Composer validation, full PHP syntax, first-party JavaScript syntax, and shell syntax.
2. **Dependency and supply chain** — committed Composer lockfile, advisory audit, and automated Composer/GitHub Actions update monitoring.
3. **Secret and configuration hygiene** — ignore rules, tracked-sensitive-file checks, and high-confidence credential scanning.
4. **Dangerous runtime primitives** — no web-accessible eval, command execution, unsafe unserialize, or request-controlled include paths.
5. **Request, SQL, and data integrity** — no direct request-to-SQL interpolation, validated migration manifest, and documented canonical ownership boundaries.
6. **Error handling and observability** — no debug output in web code, no raw generic exceptions exposed to users, centralized security logging, and CI failure artifacts.
7. **Frontend safety and contracts** — frontend contract validation, unsafe JavaScript checks, safe new-window links, and browser validation coverage.
8. **Tests and continuous integration** — substantial PHPUnit/validator coverage, complete changed-file preflight, full recovery baseline, and bounded multi-version audit CI.
9. **Deployment and recovery safety** — release, migration, backup/restore, rollback, configuration, health, and clean-database boot controls.
10. **Maintainability and governance** — canonical README guidance, security policy, contribution standards, editor rules, Git attributes, and this scoring specification.

## Commands

Generate a report without enforcing the score:

```bash
php scripts/audit_repository_production_quality.php
```

Enforce the full score:

```bash
php scripts/audit_repository_production_quality.php --gate
```

Composer alias:

```bash
composer audit-repository-quality
```

Reports are written to:

- `build/repository-production-audit.json`
- `build/repository-production-audit.md`

## Repair policy

A failed check must be handled in one of three ways:

- Fix the verified defect.
- Improve the audit when a check is demonstrably producing a false positive, while preserving or strengthening the intended safety boundary.
- Document and explicitly approve an exception in code and tests. Silent exclusions are not acceptable.

The score must never be raised by removing a meaningful control solely to make CI pass.
