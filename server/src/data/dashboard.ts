import type { DashboardMetrics } from '../types/dto.js';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import { OrchestratorError } from '../utils/errors.js';
import { mapDashboardMetrics } from '../utils/mappers.js';

export async function getDashboardMetrics(
  client: OrchestratorClient,
  includeAntiDetect = true
): Promise<DashboardMetrics> {
  try {
    const raw = await client.fetchDashboardMetrics(includeAntiDetect);
    return mapDashboardMetrics(raw);
  } catch (err) {
    if (err instanceof OrchestratorError) {
      throw err;
    }
    throw new OrchestratorError('Failed to fetch dashboard metrics', { cause: err });
  }
}
