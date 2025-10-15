import { orchestrationClient } from '../clients/orchestrationClient.js';
import { mapReport } from '../mappers/reportsMapper.js';
import type { ReportDTO } from '../types/dto.js';
import { OrchestrationError, isOrchestrationFailure } from '../utils/errors.js';

const REPORTS_ENDPOINT = 'reports_endpoint.php';

export const fetchReports = async (): Promise<ReportDTO[]> => {
  const payload = await orchestrationClient.get<any>(REPORTS_ENDPOINT, { action: 'list' });

  if (isOrchestrationFailure(payload)) {
    throw new OrchestrationError(payload.message ?? payload.error ?? 'Failed to load reports');
  }

  const records: any[] = payload.reports ?? payload.data?.reports ?? payload.data ?? [];
  return records.map(mapReport);
};
