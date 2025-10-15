import { orchestrationClient } from '../clients/orchestrationClient.js';
import { mapDashboardMetrics } from '../mappers/dashboardMapper.js';
import type { DashboardMetricsDTO } from '../types/dto.js';
import { OrchestrationError, isOrchestrationFailure } from '../utils/errors.js';
import { FALLBACK_METRICS_ENDPOINT } from '../config.js';

const METRICS_ENDPOINT = 'metrics_endpoint.php';

export const fetchDashboardMetrics = async (): Promise<DashboardMetricsDTO> => {
  try {
    const payload = await orchestrationClient.get<any>(METRICS_ENDPOINT, {
      include_anti_detect: true,
    });

    if (isOrchestrationFailure(payload)) {
      throw new OrchestrationError(payload.message ?? payload.error ?? 'Failed to load metrics');
    }

    return mapDashboardMetrics(payload);
  } catch (error) {
    if (!FALLBACK_METRICS_ENDPOINT) {
      throw error;
    }

    const fallbackPayload = await orchestrationClient.get<any>(FALLBACK_METRICS_ENDPOINT, {
      include_anti_detect: true,
    });

    if (!fallbackPayload) {
      throw new OrchestrationError('Metrics orchestration unavailable');
    }

    return mapDashboardMetrics(fallbackPayload);
  }
};
