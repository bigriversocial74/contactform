# Branch Validation Workflow

Use the same validation sequence for every stage or feature branch.

## Required sequence

1. Branch from the current `main`.
2. Implement the planned code and its tests on that branch.
3. Run `composer validate-branch -- origin/main` before opening the pull request.
4. Fix every failure on the branch.
5. Open the pull request only after branch validation passes.
6. Let GitHub Actions confirm the same branch state.
7. Merge only after all required checks are green.
8. Start the next stage from the newly updated `main`.

## Test discovery

The preflight script automatically runs:

- syntax checks for changed PHP files;
- frontend contract checks when frontend-related files change;
- every changed PHPUnit test file;
- all Action Center PHPUnit contracts when Action Center code changes.

New Action Center stage tests are discovered by filename instead of requiring a manual list update.

## Rule

GitHub Actions is the confirmation layer. It should not be the first place basic branch errors are discovered.
