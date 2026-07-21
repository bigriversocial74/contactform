import { randomUUID } from "node:crypto";

export type RuntimeEnvironment = "development" | "test" | "staging" | "production";
export type RuntimeLogLevel = "debug" | "info" | "warn" | "error" | "silent";

export interface RuntimeConfig {
  readonly environment: RuntimeEnvironment;
  readonly release: string;
  readonly publicBaseUrl: string;
  readonly shutdownGraceMs: number;
  readonly logLevel: RuntimeLogLevel;
  readonly allowNonLoopbackBind: boolean;
}

export interface RuntimeSnapshot {
  readonly status: "live" | "draining";
  readonly activeRequests: number;
  readonly startedAt: string;
  readonly uptimeSeconds: number;
}

export type RuntimeLogFields = Readonly<Record<string, unknown>>;

export interface RuntimeLogger {
  debug(event: string, fields?: RuntimeLogFields): void;
  info(event: string, fields?: RuntimeLogFields): void;
  warn(event: string, fields?: RuntimeLogFields): void;
  error(event: string, fields?: RuntimeLogFields): void;
}

const DEFAULT_RUNTIME_CONFIG: RuntimeConfig = {
  environment: "test",
  release: "development",
  publicBaseUrl: "",
  shutdownGraceMs: 30_000,
  logLevel: "silent",
  allowNonLoopbackBind: false,
};

export function resolveRuntimeConfig(config: RuntimeConfig | undefined): RuntimeConfig {
  return config ?? DEFAULT_RUNTIME_CONFIG;
}

export class ServiceRuntimeState {
  readonly #startedAt = new Date();
  #draining = false;
  #activeRequests = 0;
  readonly #idleListeners = new Set<() => void>();

  get draining(): boolean {
    return this.#draining;
  }

  get activeRequests(): number {
    return this.#activeRequests;
  }

  beginRequest(): () => void {
    this.#activeRequests += 1;
    let finished = false;
    return () => {
      if (finished) return;
      finished = true;
      this.#activeRequests = Math.max(0, this.#activeRequests - 1);
      if (this.#activeRequests === 0) {
        for (const listener of this.#idleListeners) listener();
      }
    };
  }

  beginDrain(): void {
    this.#draining = true;
  }

  snapshot(): RuntimeSnapshot {
    return {
      status: this.#draining ? "draining" : "live",
      activeRequests: this.#activeRequests,
      startedAt: this.#startedAt.toISOString(),
      uptimeSeconds: Math.max(0, Math.floor((Date.now() - this.#startedAt.getTime()) / 1000)),
    };
  }

  waitForIdle(timeoutMs: number): Promise<boolean> {
    if (this.#activeRequests === 0) return Promise.resolve(true);

    return new Promise((resolve) => {
      let settled = false;
      const finish = (idle: boolean) => {
        if (settled) return;
        settled = true;
        clearTimeout(timer);
        this.#idleListeners.delete(onIdle);
        resolve(idle);
      };
      const onIdle = () => finish(true);
      const timer = setTimeout(() => finish(false), Math.max(1, timeoutMs));
      timer.unref();
      this.#idleListeners.add(onIdle);
    });
  }
}

const levelRank: Record<RuntimeLogLevel, number> = {
  debug: 10,
  info: 20,
  warn: 30,
  error: 40,
  silent: 100,
};

const sensitiveKeyFragments = [
  "authorization",
  "bearer",
  "cookie",
  "password",
  "secret",
  "token",
  "credential",
  "api_key",
  "apikey",
  "private_key",
  "signature",
];

function sanitizeString(value: string): string {
  return value
    .replace(/Bearer\s+[A-Za-z0-9._~+\/-]+=*/gi, "Bearer [REDACTED]")
    .replace(/([?&](?:token|key|secret|code)=)[^&\s]+/gi, "$1[REDACTED]")
    .slice(0, 4_000);
}

function sanitizeValue(value: unknown, key = "", depth = 0): unknown {
  const normalizedKey = key.toLowerCase();
  if (sensitiveKeyFragments.some((fragment) => normalizedKey.includes(fragment))) {
    return "[REDACTED]";
  }
  if (depth > 5) return "[TRUNCATED]";
  if (value instanceof Error) {
    return {
      name: value.name,
      message: sanitizeString(value.message),
      stack: value.stack ? sanitizeString(value.stack) : undefined,
    };
  }
  if (Array.isArray(value)) {
    return value.slice(0, 100).map((item) => sanitizeValue(item, "", depth + 1));
  }
  if (value && typeof value === "object") {
    const output: Record<string, unknown> = {};
    for (const [childKey, childValue] of Object.entries(value).slice(0, 100)) {
      output[childKey] = sanitizeValue(childValue, childKey, depth + 1);
    }
    return output;
  }
  if (typeof value === "string") return sanitizeString(value);
  if (["number", "boolean"].includes(typeof value) || value === null || value === undefined) return value;
  return String(value);
}

export function createRuntimeLogger(
  minimumLevel: RuntimeLogLevel,
  service = "microgifter-mcp",
): RuntimeLogger {
  const write = (level: Exclude<RuntimeLogLevel, "silent">, event: string, fields: RuntimeLogFields = {}) => {
    if (levelRank[level] < levelRank[minimumLevel]) return;
    const safeFields = sanitizeValue(fields) as Record<string, unknown>;
    const payload = {
      timestamp: new Date().toISOString(),
      level,
      service,
      event: event.slice(0, 160),
      ...safeFields,
      request_id: typeof fields.requestId === "string" ? fields.requestId : randomUUID(),
    };
    const line = `${JSON.stringify(payload)}\n`;
    (level === "error" || level === "warn" ? process.stderr : process.stdout).write(line);
  };

  return {
    debug: (event, fields) => write("debug", event, fields),
    info: (event, fields) => write("info", event, fields),
    warn: (event, fields) => write("warn", event, fields),
    error: (event, fields) => write("error", event, fields),
  };
}

export function safeRequestId(value: unknown): string {
  if (typeof value !== "string") return randomUUID();
  const candidate = value.trim().replace(/[^A-Za-z0-9._-]/g, "").slice(0, 80);
  return candidate || randomUUID();
}
