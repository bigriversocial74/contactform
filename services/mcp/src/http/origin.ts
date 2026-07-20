export function isOriginAllowed(origin: string | undefined, allowedOrigins: readonly string[]): boolean {
  if (origin === undefined || origin.trim() === "") return true;
  return allowedOrigins.includes(origin.trim());
}
