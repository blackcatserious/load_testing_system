import { orchestrationClient } from '../clients/orchestrationClient.js';
import { FALLBACK_METRICS_ENDPOINT } from '../config.js';
import { mapRuntimeMetadata } from '../mappers/runsMapper.js';
import { OrchestrationError, isOrchestrationFailure } from '../utils/errors.js';
import type { RuntimeMetadataDTO } from '../types/dto.js';

const METRICS_PATH = 'metrics_endpoint.php';

const extractRuntime = (payload: any): RuntimeMetadataDTO | undefined =>
  mapRuntimeMetadata({
    proxy_stats: payload?.proxy_stats ?? payload?.metrics?.proxy_stats,
    current_proxy: payload?.current_proxy ?? payload?.metrics?.current_proxy,
    user_agent: payload?.user_agent ?? payload?.metrics?.user_agent,
    user_agent_rotation: payload?.metrics?.user_agent_rotation,
    tls_profile: payload?.tls_profile ?? payload?.metrics?.tls_profile,
  }) ?? undefined;

export const fetchRuntimeMetadata = async (): Promise<RuntimeMetadataDTO | undefined> => {
  try {
    const payload = await orchestrationClient.get<any>(METRICS_PATH, {
      include_anti_detect: true,
    });

    if (isOrchestrationFailure(payload)) {
      throw new OrchestrationError(payload.message ?? payload.error ?? 'Metrics request failed');
    }

    return extractRuntime(payload);
  } catch (error) {
    if (FALLBACK_METRICS_ENDPOINT) {
      try {
        const payload = await orchestrationClient.get<any>(FALLBACK_METRICS_ENDPOINT, {
          include_anti_detect: true,
        });

        return extractRuntime(payload);
      } catch (fallbackError) {
        console.warn('Failed to fetch runtime metadata from fallback endpoint', fallbackError);
        return undefined;
      }
    }

    console.warn('Failed to fetch runtime metadata from orchestration layer', error);
    return undefined;
  }
};
