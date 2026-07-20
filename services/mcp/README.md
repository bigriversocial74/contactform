# Microgifter MCP Service

This directory is the separately deployable TypeScript control plane for Microgifter Platform Phase 5.

Current Phase 1 posture:

- disabled by default;
- stateless Streamable HTTP at `/mcp` for internal development only;
- SHA-256 bearer-token verification;
- strict Origin and Host validation;
- deterministic, scope-filtered tool discovery;
- fixed-window per-connection rate limits;
- safe invocation receipts;
- no external OAuth;
- no scheduler or worker;
- no write-capable tools;
- no database credentials;
- catalog tools fail closed until the protected canonical PHP bridge is merged.

The only currently functional tool is `microgifter.account.get_connection_context`, which returns minimized connection context from the authenticated internal principal.

## Local validation

```bash
npm ci
npm run check
```

## Internal development launch

The process will refuse to start unless both platform and internal HTTP flags are enabled and a SHA-256 bearer-token hash plus explicit scopes are provided.

```bash
MICROGIFTER_MCP_ENABLED=true \
MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED=true \
MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256=<64-character-sha256> \
MICROGIFTER_MCP_INTERNAL_SCOPES=profile:read,catalog:read \
npm start
```

External deployment and OAuth remain disabled until later scoped phases.
