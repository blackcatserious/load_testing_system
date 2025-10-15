import dayjs from 'dayjs';
import { listActiveTestRuns, getTrend } from './testRuns.js';
import type { DashboardOverview } from '../types/index.js';

export function getDashboardOverview(): DashboardOverview {
  const activeRuns = listActiveTestRuns();
  const trend = getTrend();

  const readinessScore = 86;
  const readinessSummary = 'Platform ready for high intensity campaigns with minor coordination required.';
  const keyAlerts = [
    'Payments failover path latency +12% vs baseline',
    'Synthetic login monitors stable 48h',
    'EU CDN saturation trending towards 70%',
  ];

  const metrics = {
    avgLatency: 248,
    p95Latency: trend[trend.length - 1]?.p95Latency ?? 310,
    errorRate: trend[trend.length - 1]?.errorRate ?? 1.2,
    throughput: trend[trend.length - 1]?.throughput ?? 1200,
    concurrency: activeRuns.reduce((sum, run) => sum + run.concurrency, 0),
  };

  const upcomingMilestones = [
    {
      label: 'Launch window: Aurora cycle 5',
      timestamp: dayjs().add(2, 'hour').toISOString(),
      note: 'Coordinate failover validation with SRE',
    },
    {
      label: 'Sonar spike rehearsal',
      timestamp: dayjs().add(4, 'hour').toISOString(),
      note: 'QA streaming cluster ready',
    },
    {
      label: 'Production blackout',
      timestamp: dayjs().add(1, 'day').toISOString(),
      note: 'No production load tests for maintenance',
    },
  ];

  return {
    updatedAt: dayjs().toISOString(),
    readinessScore,
    readinessSummary,
    keyAlerts,
    activeRuns,
    upcomingMilestones,
    metrics,
    trend,
  };
}
