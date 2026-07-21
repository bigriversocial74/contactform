import { createHash, timingSafeEqual } from "node:crypto";
import type { IncomingHttpHeaders } from "node:http";
import type { ConnectionContext } from "../contracts.js";

export interface InternalPrincipal {
  readonly connection: ConnectionContext;
  readonly authenticationType: "internal_bearer_sha256";
}

export function extractBearerToken(headers: IncomingHttpHeaders): string | null {
  const raw = headers.authorization;
  if (typeof raw !== "string") return null;
  const match = /^Bearer\s+([^\s]+)$/i.exec(raw.trim());
  return match?.[1] ?? null;
}

export function hashBearerToken(token: string): string {
  return createHash("sha256").update(token, "utf8").digest("hex");
}

export function authenticateInternalBearer(
  headers: IncomingHttpHeaders,
  expectedSha256: string,
  connection: ConnectionContext,
): InternalPrincipal | null {
  const token = extractBearerToken(headers);
  if (!token || !/^[a-f0-9]{64}$/.test(expectedSha256)) return null;

  const actual = Buffer.from(hashBearerToken(token), "hex");
  const expected = Buffer.from(expectedSha256, "hex");
  if (actual.length !== expected.length || !timingSafeEqual(actual, expected)) return null;

  return { connection, authenticationType: "internal_bearer_sha256" };
}
