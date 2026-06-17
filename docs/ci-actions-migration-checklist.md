# CI Consolidation Migration Checklist

After the consolidation pull request is merged:

- [ ] Confirm `PR Validation / validate` passes on the consolidation PR.
- [ ] Confirm `Main Regression / regression` runs once on the merge commit.
- [ ] Open repository Settings -> Branches -> branch protection for `main`.
- [ ] Remove historical Stage 4/5/6 and Security Foundation required check names.
- [ ] Add `PR Validation / validate` as the required pull-request check.
- [ ] Keep `Deep Validation` manual/weekly; it does not need to block every PR.
- [ ] For future failures, use **Re-run failed jobs**, not **Re-run all jobs**.
- [ ] Keep new stage tests in PHPUnit; do not create a new permanent workflow per stage.
