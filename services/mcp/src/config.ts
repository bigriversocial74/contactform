export interface McpFoundationConfig {
  readonly enabled: boolean;
  readonly externalHttpEnabled: boolean;
  readonly schedulerEnabled: boolean;
  readonly workerEnabled: boolean;
  readonly writeToolsEnabled: boolean;
  readonly boundedAutomationEnabled: boolean;
}

export const DISABLED_FOUNDATION_CONFIG: McpFoundationConfig = Object.freeze({
  enabled: false,
  externalHttpEnabled: false,
  schedulerEnabled: false,
  workerEnabled: false,
  writeToolsEnabled: false,
  boundedAutomationEnabled: false,
});

export function validateFoundationConfig(config: McpFoundationConfig): void {
  if (!config.enabled) {
    const unsafe = [
      config.externalHttpEnabled,
      config.schedulerEnabled,
      config.workerEnabled,
      config.writeToolsEnabled,
      config.boundedAutomationEnabled,
    ].some(Boolean);
    if (unsafe) {
      throw new Error("MCP child capabilities cannot be enabled while the platform is disabled.");
    }
  }

  if (config.boundedAutomationEnabled && (!config.workerEnabled || !config.writeToolsEnabled)) {
    throw new Error("Bounded automation requires workers and write tools.");
  }

  if (config.writeToolsEnabled && !config.enabled) {
    throw new Error("Write tools require the MCP platform.");
  }
}
