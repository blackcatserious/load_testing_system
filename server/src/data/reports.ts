import type { ReportSummary } from '../types/dto.js';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import { OrchestratorError } from '../utils/errors.js';
import { mapReport } from '../utils/mappers.js';

export async function getReports(client: OrchestratorClient): Promise<ReportSummary[]> {
  try {
    const reports = await client.fetchReports();
    return reports.map(mapReport);
  } catch (err) {
    if (err instanceof OrchestratorError) {
      throw err;
    }
    throw new OrchestratorError('Failed to fetch reports', { cause: err });
  }
}
