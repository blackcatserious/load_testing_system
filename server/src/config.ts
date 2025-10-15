export const ORCHESTRATOR_BASE_URL =
  process.env.ORCHESTRATOR_BASE_URL ?? 'http://127.0.0.1:9000/api';

export const ORCHESTRATOR_TIMEOUT = Number(process.env.ORCHESTRATOR_TIMEOUT ?? 15000);

export const FALLBACK_METRICS_ENDPOINT = process.env.FALLBACK_METRICS_ENDPOINT ??
  '../working_metrics_endpoint.php';
