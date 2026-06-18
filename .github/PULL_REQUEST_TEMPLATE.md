## Summary

Describe the bounded change and the ownership boundary it touches.

## Validation

- [ ] `composer preflight`
- [ ] Targeted behavior validators
- [ ] `composer recovery-baseline`
- [ ] Recovery Baseline GitHub Action

## Database

- [ ] No schema change
- [ ] Additive migration added to `config/migrations.php`
- [ ] Existing applied migrations were not modified

## Risk

Describe rollback steps and any runtime monitoring required.
