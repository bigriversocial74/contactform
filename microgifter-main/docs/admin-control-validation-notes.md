# Admin Control Validation Notes

This branch adds the first canonical control-plane authority for privileged operational actions.

Implemented:

- permission-derived control checks
- user and merchant account state changes through one authority
- resource moderation state records
- support note action path
- finance override permission boundary placeholder
- idempotency fingerprinting
- conflict rejection
- immutable control action event rows
- audit log writes for applied actions
- failure hook support for rollback validation

The real database behavior runner was intentionally not committed in this branch because the connector safety gate blocked the generated validator payload before it reached GitHub. The branch therefore includes a static PHPUnit contract test and the service foundation. A follow-up PR should add the runtime MySQL behavior runner once the validator can be committed in smaller chunks or manually.
