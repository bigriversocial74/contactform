# Admin health recovery notes

This branch addresses the admin System Health critical state.

Changes:

- The production release trigger migration key now covers the current manifest.
- Commerce queue returns an empty setup-required response when operating tables are not available.
- Content review queue returns an empty setup-required response when report tables are not available.

This reduces repeated queue failure warnings while keeping real operational problems visible.
