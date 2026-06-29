# Training Lab Agent Branch Index

Use this file to orient future agents working on the Training Lab build.

## Primary branch for agents

```text
training-lab-stage2-stage4-autobuild
```

This is the branch other agents should start from first. It contains the recovered Training Lab docs, config rule, DB diagnostic files, stage planning docs, and the expected `/examples/training-labs/` folder structure.

## Branches created during recovery

These branches were created while recovering and testing the Training Lab examples/assets structure:

```text
training-lab-stage2-stage4-autobuild        # primary recovered/docs branch
training-lab-image-assets                   # image asset staging branch
training-lab-asset-commit-test              # temporary asset commit test branch
training-lab-tree-test                      # temporary tree test branch
training-lab-tree-test-x                    # temporary tree test branch
training-lab-tree-test-y                    # temporary tree test branch
training-lab-tree-test-z                    # temporary tree test branch
training-lab-tree-test-w                    # temporary tree test branch
training-lab-tree-test-v                    # temporary tree test branch
training-lab-tree-test-u                    # temporary tree test branch
training-lab-assets-folder-index            # temporary asset folder index branch
training-lab-assets-folder-index-2          # temporary asset folder index branch
training-lab-assets-folder-index-3          # temporary asset folder index branch
training-lab-assets-folder-index-4          # temporary asset folder index branch
training-lab-assets-folder-index-5          # temporary asset folder index branch
```

## Which branch should agents use?

Start here:

```text
training-lab-stage2-stage4-autobuild
```

Do not start from the temporary test branches unless David explicitly asks to inspect asset upload experiments.

## Current committed folder

```text
examples/training-labs/
```

Committed under that folder:

```text
README.md
config-example.php
docs/recovery-manifest.md
docs/agent-branch-index.md
labs/includes/training-lab-db.php
labs/api/training/db-status.php
```

## Image status

The image/template assets were moved into the correct folder structure in the latest zip package:

```text
training-lab-examples-with-images-in-folders.zip
```

That zip contains:

```text
examples/training-labs/template-assets/public-pages/
examples/training-labs/template-assets/app-pages/
examples/training-labs/template-assets/admin-pages/
examples/training-labs/template-assets/icons/
examples/training-labs/template-assets/raw-mockups/
```

The binary image files are preserved in the zip. They still need a full binary commit into the primary branch when using a normal Git client or a successful binary upload pass.

## Config rule

Only ship:

```text
config-example.php
```

Never commit or overwrite:

```text
config.php
```

David renames `config-example.php` to `config.php` locally.

## Correct DB loader path

From:

```text
/contactform/labs/includes/training-lab-db.php
```

Load:

```php
dirname(__DIR__, 2) . '/config.php'
```

## Current next step

Make sure the next coding agent can see this branch and this file. Then apply the latest recovered zip contents into the primary branch so the full code and binary image assets are committed in one clean branch.
