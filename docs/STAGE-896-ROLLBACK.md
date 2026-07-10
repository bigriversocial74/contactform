# Stage 896 endpoint rollback

Disable new pilot issue requests:

```text
MG_TRAINING_LAB_PILOT_ISSUE_ENABLED=false
```

Do not remove existing Microgift, Action Center, idempotency, or audit records. Keep the Stage 894 read-only lookup available while Training Lab verifies any uncertain pilot.
