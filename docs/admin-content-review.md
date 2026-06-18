# Admin content review center

The content review center is available at `/admin/moderation.php` for administrators with content or profile review permissions.

## Supported report subjects

Members can report:

- Public profiles and user accounts
- Feed posts and comments
- Uploaded feed images, audio, and video
- Direct messages from conversations they participate in

The report endpoint validates that the reporter can access the subject, prevents self-reporting, stores a bounded evidence snapshot, and returns an existing active report rather than creating duplicates.

## Review queue

The queue supports status, subject type, severity, assignment, and text filters. Reports are ordered by severity and age. The review panel displays:

- The original report and reporter details
- Live reported content or the stored snapshot when content is unavailable
- Attached media previews
- The linked account and activity totals
- Active account restrictions
- Complete review-action history

## Review actions

Administrators with manage access can:

- Claim a report or add an internal note
- Dismiss or resolve a report
- Hide or restore posts, comments, profiles, and messages
- Quarantine or restore uploaded media
- Warn an account
- Restrict new posts and feed-media uploads
- Suspend or reactivate an account

Every action is permission checked, CSRF protected, rate limited, transactional, and written to the audit and event logs. Account actions cannot target the acting administrator, and super-administrator accounts are protected from account-level actions in the review center.

## Content delivery effects

Hidden posts are excluded by the existing social visibility policy. Hidden messages are excluded from conversation delivery. Quarantined media is switched out of the `ready` storage state and cannot be served by the public media endpoint until restored.

## Deployment

Run the canonical migration runner after deployment:

```bash
php scripts/run_migrations.php
```

The new migration is `database/stage_18j_content_moderation.sql`.
