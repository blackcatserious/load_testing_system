import { orchestrationClient } from '../clients/orchestrationClient.js';
import { mapRunDetail, mapRunSummary, mapRuntimeMetadata } from '../mappers/runsMapper.js';
import type { TestRunDetailDTO, TestRunSummaryDTO } from '../types/dto.js';
import { OrchestrationError, isOrchestrationFailure } from '../utils/errors.js';
import { fetchRuntimeMetadata } from './runtime.js';

interface RunsListPayload {
  runs?: any[];
  [key: string]: any;
}

const RUNS_ENDPOINT = 'runs_endpoint.php';

export const fetchTestRuns = async (limit = 50): Promise<TestRunSummaryDTO[]> => {
  const payload = await orchestrationClient.get<{ runs: any[] }>(RUNS_ENDPOINT, { limit });

  if (isOrchestrationFailure(payload)) {
    throw new OrchestrationError(payload.message ?? payload.error ?? 'Failed to load test runs');
  }

  const runtime = await fetchRuntimeMetadata();
  const data = (payload.data ?? payload).runs ?? [];

  return data.map((run: any) => mapRunSummary(run, runtime));
};

export const fetchTestRun = async (runId: string): Promise<TestRunDetailDTO> => {
  const payload = await orchestrationClient.get<any>(RUNS_ENDPOINT, { run_id: runId });

  if (isOrchestrationFailure(payload)) {
    throw new OrchestrationError(payload.message ?? payload.error ?? `Failed to load run ${runId}`);
  }

  const runtime =
    mapRuntimeMetadata(payload.runtime ?? payload.data?.runtime ?? payload.metrics?.runtime) ??
    (await fetchRuntimeMetadata());

  const run = payload.data?.run ?? payload.data ?? payload;

  if (!run || !run.run_id) {
    throw new OrchestrationError(`Run ${runId} not found`, 404);
  }

  return mapRunDetail(run, runtime ?? undefined, {
    metrics: payload.data?.metrics ?? payload.metrics,
    waf_detections: payload.data?.waf_detections ?? payload.waf_detections,
    method_performance: payload.data?.method_performance ?? payload.method_performance,
    run_config: payload.data?.run_config ?? payload.run_config,
  });
};
