import { orchestrationClient } from '../clients/orchestrationClient.js';
import { mapTestPlan } from '../mappers/testPlansMapper.js';
import type { TestPlanDTO } from '../types/dto.js';
import { OrchestrationError, isOrchestrationFailure } from '../utils/errors.js';
import { fetchRuntimeMetadata } from './runtime.js';
import { fetchTestRuns } from './testRuns.js';

const GROUPS_ENDPOINT = 'group_runs_endpoint.php';
const GROUPS_FALLBACK_ENDPOINT = '../deployment_ready/api/group_runs_endpoint_backup.php';

type GroupResponse = {
  success?: boolean;
  status?: 'success' | 'error';
  error?: string;
  message?: string;
  groups?: any[];
  group_info?: any;
  data?: any;
  [key: string]: any;
};

const requestGroupData = async (params: Record<string, any>): Promise<GroupResponse> => {
  try {
    return await orchestrationClient.get<GroupResponse>(GROUPS_ENDPOINT, params);
  } catch (primaryError) {
    return orchestrationClient.get<GroupResponse>(GROUPS_FALLBACK_ENDPOINT, params);
  }
};

const ensureSuccess = (payload: GroupResponse) => {
  if (isOrchestrationFailure(payload)) {
    throw new OrchestrationError(payload.message ?? payload.error ?? 'Failed to load test plans');
  }
};

const derivePlansFromRuns = async (): Promise<TestPlanDTO[]> => {
  const runs = await fetchTestRuns(200);
  const grouped = new Map<string, { runs: typeof runs; base: TestPlanDTO }>();

  runs.forEach((run) => {
    if (!run.group_id) {
      return;
    }
    if (!grouped.has(run.group_id)) {
      grouped.set(run.group_id, {
        runs: [],
        base: {
          id: run.group_id,
          status: run.status,
          started_at: run.started_at,
          finished_at: run.finished_at,
          targets: run.target_url ? [run.target_url] : undefined,
          runtime: run.proxy_pool
            ? {
                proxy_pool: run.proxy_pool,
                current_proxy: run.proxy_pool ? null : undefined,
                user_agent: run.user_agent,
                tls: run.tls,
              }
            : undefined,
        } as TestPlanDTO,
      });
    }
    const entry = grouped.get(run.group_id)!;
    entry.runs.push(run);
    entry.base.targets = entry.base.targets ?? [];
    if (run.target_url && !entry.base.targets!.includes(run.target_url)) {
      entry.base.targets!.push(run.target_url);
    }
    entry.base.status = run.status;
    entry.base.started_at = entry.base.started_at ?? run.started_at;
    entry.base.finished_at = entry.base.finished_at ?? run.finished_at;
  });

  return Array.from(grouped.values()).map(({ base }) => base);
};

export const fetchTestPlans = async (): Promise<TestPlanDTO[]> => {
  try {
    const payload = await requestGroupData({ action: 'list' });
    ensureSuccess(payload);

    const baseRuntime = await fetchRuntimeMetadata();
    const recordsRaw = payload.groups ?? payload.data?.groups ?? payload.data ?? [];
    const records = Array.isArray(recordsRaw) ? recordsRaw : Object.values(recordsRaw ?? {});

    return records.map((record) => {
      const mapped = mapTestPlan(record);
      return baseRuntime && !mapped.runtime ? { ...mapped, runtime: baseRuntime } : mapped;
    });
  } catch (error) {
    const derived = await derivePlansFromRuns();
    if (derived.length === 0) {
      throw error;
    }
    return derived;
  }
};

export const fetchTestPlan = async (groupId: string): Promise<TestPlanDTO> => {
  try {
    const payload = await requestGroupData({ action: 'status', group_id: groupId });
    ensureSuccess(payload);

    const group = payload.group_info ?? payload.group ?? payload.data ?? payload;

    if (!group || !group.group_id) {
      throw new OrchestrationError(`Test plan ${groupId} not found`, 404);
    }

    const runtime = group.runtime ?? (await fetchRuntimeMetadata());

    return mapTestPlan({ ...group, runtime });
  } catch (error) {
    const derived = await fetchTestPlans();
    const plan = derived.find((item) => item.id === groupId);
    if (!plan) {
      throw error instanceof OrchestrationError ? error : new OrchestrationError(`Test plan ${groupId} not found`, 404);
    }
    return plan;
  }
};
