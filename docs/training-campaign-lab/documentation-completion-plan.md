# Training Campaign Lab Documentation Completion Plan

## Purpose

This document defines the complete documentation plan for the Training Campaign Lab before additional implementation work continues.

The goal is to complete the planning layer so the build can proceed without guessing, redesigning pages mid-stream, or accidentally replacing the original Loyalty Quest / Local Quest behavior.

## Documentation goal

Before writing more implementation code, the project should have complete documentation for:

```text
branch strategy
product requirements
route map
UI page map
UI layout specification
component inventory
responsive behavior
mockup index
UI/data binding
schema outline
schema install plan
status model
data lifecycle
security and permissions
admin workflows
participant workflows
implementation tickets
QA testing
open questions
```

## Completion checklist

### Branch and build control

```text
[x] branch-strategy.md
[x] build-plan.md
[x] implementation-tickets.md
[x] qa-test-script.md
```

### Product and scope

```text
[x] product-requirements.md
[x] expanded-platform-outline.md
[x] mvp-scope.md
[x] demo-script.md
[x] acceptance-checklist.md
```

### UI planning

```text
[x] ui/ui-page-map.md
[x] ui/ui-layout-spec.md
[x] ui/component-inventory.md
[x] ui/responsive-rules.md
[x] ui/mockups.md
[x] ui/mockups/README.md
```

### Data and schema planning

```text
[x] schema.md
[x] status-model.md
[x] ui-data-map.md
[x] data-lifecycle.md
[x] schema-install.md
```

### Role and workflow planning

```text
[x] security-permissions.md
[x] admin-workflows.md
[x] participant-workflows.md
```

### Decision tracking

```text
[x] open-questions.md
```

## Documentation-to-build sequence

Use this order when building from the docs:

```text
1. Confirm branch strategy
2. Confirm product requirements
3. Confirm route map
4. Confirm UI/data requirements
5. Confirm status model
6. Confirm schema install plan
7. Confirm data lifecycle
8. Confirm permissions
9. Confirm workflows
10. Build from implementation tickets
11. Validate with QA script
```

## Build stop rule

If a feature is not covered by these docs, stop and document it first.

Do not implement undocumented behavior for:

```text
reward issuing
proof file access
review permissions
receipt creation
wallet display
admin settings
audit logs
schema changes
```

## Protected outcome

The documentation package should make the next implementation phase predictable:

```text
No unclear route names
No unclear user roles
No unclear proof status
No unclear review status
No unclear reward issue status
No unclear receipt logic
No unclear schema install order
No accidental changes to the original Loyalty Quest flow
```
