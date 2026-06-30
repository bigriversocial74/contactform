# Stage 9 Examples/Labs Merge Note

This branch was created from:

```text
training-lab-stage2-stage4-autobuild
```

Current working codebase source provided by David:

```text
training_backup.zip
```

Correct working folder:

```text
examples/labs/
```

The uploaded current codebase preserves the correct database config placement:

```text
examples/labs/labs/config.php
examples/labs/labs/config-example.php
```

From:

```text
examples/labs/includes/training-lab-db.php
```

The DB loader should resolve:

```php
dirname(__DIR__) . '/labs/config.php'
```

which points to:

```text
examples/labs/labs/config.php
```

Stage 9 changes merged on top locally:

```text
examples/labs/includes/training-lab-db.php
examples/labs/api/training/db-status.php
examples/labs/stage-9-db-diagnostic-build-report.md
```

Stage 9 additions:

```text
config.source
config.port_present
missing_tables
```

The merged package created from the uploaded current codebase is:

```text
training-lab-examples-labs-current-stage9-merged.zip
```

Important: do not move this work back to `/labs`, `contactform/labs`, or `contactform/examples/training-labs/labs`. David clarified that the active working folder is only:

```text
examples/labs/
```
