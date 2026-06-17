# Stage 5B Product, Builder, Publishing, and Asset Management Integration

Stage 5B connects the Stage 4A catalog and Stage 4B builder systems to the shared Stage 5 merchant workspace.

## Included

- Merchant product list with search and status filters
- Draft, published, and archived product counts
- Builder links for create and edit workflows
- Product detail sheet with current state, immutable version history, and attached media
- Merchant media library with type, status, filename, processing state, and usage counts
- Publish and archive actions through the existing catalog lifecycle
- Continued use of immutable published versions and PPPM template generation
- Shared merchant workspace navigation and responsive styling

## Lifecycle boundary

Stage 5B does not replace the catalog or builder APIs. It exposes them through merchant operations. Published versions remain immutable. Editing a published product creates or updates a builder draft and later publishes a new version.

## Carry-forward

Stage 5C will connect storefront placement and public preview. Stage 5D will connect orders and PPPM operations. Stage 5E will connect distribution eligibility and program assignment.
