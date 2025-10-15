import dayjs from 'dayjs';
import type { TestReport } from '../types/index.js';

export const reports: TestReport[] = [
  {
    id: 'rpt-neo-445',
    runId: 'run-neo-445',
    planId: 'plan-aurora',
    title: 'Aurora Checkout Cycle 5',
    generatedAt: dayjs().subtract(3, 'hour').toISOString(),
    author: 'Serena Vale',
    status: 'draft',
    format: 'html',
    metrics: {
      peakThroughput: 2187,
      avgLatency: 268,
      errorRate: 0.7,
      availability: 99.2,
    },
    sections: [
      {
        title: 'Executive Summary',
        summary: 'Peak throughput exceeded SLO while maintaining error budget headroom.',
        insights: [
          'Payments failover increased p95 latency by 12%',
          'No saturation across CDN nodes',
        ],
      },
      {
        title: 'Bottlenecks',
        summary: 'Identified TLS handshakes as the dominant latency contributor during ramp.',
        insights: [
          'Rotate to faster certificates before production push',
          'Coordinate with security to pre-warm WAF rules',
        ],
      },
    ],
  },
  {
    id: 'rpt-lumina-312',
    runId: 'run-lumina-312',
    planId: 'plan-lumina',
    title: 'Lumina Regression Hunt',
    generatedAt: dayjs().subtract(1, 'day').toISOString(),
    author: 'Priya Banerjee',
    status: 'published',
    format: 'pdf',
    metrics: {
      peakThroughput: 5075,
      avgLatency: 244,
      errorRate: 0.9,
      availability: 99.6,
    },
    sections: [
      {
        title: 'Key Findings',
        summary: 'Cache rehydration impacted EU latency by 21% during peak.',
        insights: [
          'Introduce region-aware warmers ahead of ramp',
          'Streaming search logs uncovered new GC pattern',
        ],
      },
      {
        title: 'Mitigations',
        summary: 'Coordination with platform SRE to increase autoscale headroom before next run.',
        insights: [
          'Autoscale min nodes +2 for EU cluster',
          'Add 5xx regression checks to anomaly detector',
        ],
      },
    ],
  },
];

export function listReports(): TestReport[] {
  return reports;
}

export function getReport(id: string): TestReport | undefined {
  return reports.find((report) => report.id === id);
}
