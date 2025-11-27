export interface OrchestratorConfig {
  baseUrl: string;
  timeoutMs: number;
}

const baseUrl = process.env.ORCHESTRATION_BASE_URL || 'http://127.0.0.1:8080';
const timeout = process.env.ORCHESTRATION_TIMEOUT_MS
  ? parseInt(process.env.ORCHESTRATION_TIMEOUT_MS, 10)
  : 15000;

export const orchestratorConfig: OrchestratorConfig = {
  baseUrl,
  timeoutMs: Number.isFinite(timeout) && timeout > 0 ? timeout : 15000,
};
