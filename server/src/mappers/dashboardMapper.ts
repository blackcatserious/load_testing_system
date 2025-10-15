import { DashboardMetricsDTO, RuntimeMetadataDTO } from '../types/dto.js';
import { mapRuntimeMetadata, mapProxyStats } from './runsMapper.js';

interface MetricsEnvelope {
  timestamp?: string;
  metrics?: Record<string, any>;
  status_codes?: Record<string, number>;
  attack_status?: Record<string, any>;
  anti_detect?: Record<string, any>;
  proxy_stats?: Record<string, any>;
  current_proxy?: Record<string, any> | null;
  user_agent?: Record<string, any> | string;
  user_agent_rotation?: boolean;
  tls_profile?: Record<string, any>;
  runtime?: Record<string, any>;
  [key: string]: any;
}

export const mapDashboardMetrics = (payload: MetricsEnvelope): DashboardMetricsDTO => {
  const metrics = payload.metrics ?? {};
  const runtimePayload: RuntimeMetadataDTO | undefined =
    mapRuntimeMetadata({
      proxy_stats: payload.proxy_stats ?? payload.runtime?.proxy_stats,
      current_proxy: payload.current_proxy ?? payload.runtime?.current_proxy,
      user_agent: payload.user_agent ?? payload.runtime?.user_agent,
      user_agent_rotation:
        payload.user_agent_rotation ?? payload.runtime?.user_agent_rotation ?? true,
      tls_profile: payload.tls_profile ?? payload.runtime?.tls_profile,
    }) ?? {
      proxy_pool: mapProxyStats(payload.proxy_stats ?? payload.runtime?.proxy_stats) ?? {
        total_proxies: 0,
        active_proxies: 0,
      },
      current_proxy: null,
    };

  return {
    timestamp: payload.timestamp ?? new Date().toISOString(),
    metrics: {
      requests_per_second: Number(metrics.requests_per_second ?? metrics.rps ?? 0),
      active_threads: Number(metrics.active_threads ?? metrics.threads ?? 0),
      avg_latency_ms: Number(metrics.avg_latency_ms ?? metrics.avg_response_time ?? 0),
      total_requests: Number(metrics.total_requests ?? 0),
      success_rate: Number(metrics.success_rate ?? metrics.success ?? 0),
      error_rate_percent: metrics.error_rate_percent != null ? Number(metrics.error_rate_percent) : undefined,
    },
    status_codes: payload.status_codes ?? metrics.status_codes ?? {},
    attack_status: payload.attack_status,
    runtime: runtimePayload,
  };
};
