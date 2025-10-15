import dayjs from 'dayjs';
import { nanoid } from 'nanoid';
import type { CreateRunPayload, MetricPoint, TestRun } from '../types/index.js';

const base = dayjs();

const runs: TestRun[] = [
  {
    id: 'run-neo-445',
    name: 'Aurora Checkout Resilience - Cycle 5',
    planId: 'plan-aurora',
    status: 'running',
    owner: 'Serena Vale',
    environment: 'staging',
    startTime: base.subtract(25, 'minute').toISOString(),
    estimatedEndTime: base.add(2, 'hour').toISOString(),
    lastUpdated: base.toISOString(),
    progress: 42,
    concurrency: 1800,
    peakRps: 2200,
    errorBudgetConsumed: 0.32,
    blockers: ['Canary checkout node draining slower than expected'],
    phases: [
      { phase: 'warmup', targetRps: 600, durationMinutes: 15 },
      { phase: 'ramp', targetRps: 1800, durationMinutes: 30 },
      { phase: 'peak', targetRps: 2200, durationMinutes: 60 },
      { phase: 'sustain', targetRps: 2000, durationMinutes: 45 },
      { phase: 'cooldown', targetRps: 400, durationMinutes: 15 },
    ],
    checkpoints: [
      {
        label: 'Synthetic monitors green',
        timestamp: base.subtract(55, 'minute').toISOString(),
        note: 'Ready to trigger after pre-flight checks',
      },
      {
        label: 'Ramp started',
        timestamp: base.subtract(20, 'minute').toISOString(),
      },
      {
        label: 'Payments failover requested',
        timestamp: base.add(15, 'minute').toISOString(),
        note: 'Coordinating with SRE payments',
      },
    ],
  },
  {
    id: 'run-sonar-127',
    name: 'Sonar Streaming Spike - Canary',
    planId: 'plan-sonar',
    status: 'preparing',
    owner: 'Gabriel Ortiz',
    environment: 'qa',
    startTime: base.add(4, 'hour').toISOString(),
    estimatedEndTime: base.add(5, 'hour').toISOString(),
    lastUpdated: base.subtract(5, 'minute').toISOString(),
    progress: 12,
    concurrency: 0,
    peakRps: 0,
    errorBudgetConsumed: 0.05,
    blockers: [],
    phases: [
      { phase: 'warmup', targetRps: 500, durationMinutes: 10 },
      { phase: 'ramp', targetRps: 3000, durationMinutes: 10 },
      { phase: 'peak', targetRps: 6000, durationMinutes: 10 },
      { phase: 'cooldown', targetRps: 500, durationMinutes: 15 },
    ],
    checkpoints: [
      {
        label: 'Streaming operations go/no-go',
        timestamp: base.add(3, 'hour').toISOString(),
      },
    ],
  },
  {
    id: 'run-lumina-312',
    name: 'Lumina Latency Hunt - rehearsal',
    planId: 'plan-lumina',
    status: 'completed',
    owner: 'Priya Banerjee',
    environment: 'staging',
    startTime: base.subtract(2, 'day').toISOString(),
    estimatedEndTime: base.subtract(2, 'day').add(2, 'hour').toISOString(),
    lastUpdated: base.subtract(2, 'day').add(2, 'hour').toISOString(),
    progress: 100,
    concurrency: 3400,
    peakRps: 5200,
    errorBudgetConsumed: 0.44,
    blockers: ['Search cache warmed slower in eu-west-1'],
    phases: [
      { phase: 'warmup', targetRps: 1200, durationMinutes: 20 },
      { phase: 'ramp', targetRps: 4200, durationMinutes: 30 },
      { phase: 'peak', targetRps: 5200, durationMinutes: 50 },
      { phase: 'sustain', targetRps: 4700, durationMinutes: 30 },
      { phase: 'cooldown', targetRps: 1000, durationMinutes: 20 },
    ],
    checkpoints: [
      {
        label: 'Regressions acknowledged',
        timestamp: base.subtract(2, 'day').add(3, 'hour').toISOString(),
      },
    ],
  },
];

const trend: MetricPoint[] = Array.from({ length: 12 }).map((_, idx) => {
  const timestamp = base.subtract(11 - idx, 'minute');
  return {
    timestamp: timestamp.toISOString(),
    throughput: 1200 + Math.round(Math.sin(idx / 2) * 320 + idx * 24),
    p95Latency: 280 + Math.round(Math.cos(idx / 3) * 18),
    errorRate: Number((0.8 + Math.sin(idx) * 0.15).toFixed(2)),
    saturation: Number((0.55 + Math.cos(idx / 4) * 0.1).toFixed(2)),
  };
});

export function listTestRuns(): TestRun[] {
  return runs;
}

export function listActiveTestRuns(): TestRun[] {
  return runs.filter((run) => run.status === 'running' || run.status === 'preparing');
}

export function getTestRun(id: string): TestRun | undefined {
  return runs.find((run) => run.id === id);
}

export function createTestRun(payload: CreateRunPayload): TestRun {
  const now = dayjs();
  const run: TestRun = {
    id: `run-${nanoid(6)}`,
    name: `Ad-hoc run for ${payload.planId}`,
    planId: payload.planId,
    status: 'preparing',
    owner: payload.owner,
    environment: payload.environment,
    startTime: now.add(10, 'minute').toISOString(),
    estimatedEndTime: now.add(2, 'hour').toISOString(),
    lastUpdated: now.toISOString(),
    progress: 0,
    concurrency: 0,
    peakRps: 0,
    errorBudgetConsumed: 0,
    blockers: [],
    phases: [
      { phase: 'warmup', targetRps: 500, durationMinutes: 15 },
      { phase: 'ramp', targetRps: 1500, durationMinutes: 20 },
      { phase: 'peak', targetRps: 2000, durationMinutes: 40 },
      { phase: 'cooldown', targetRps: 400, durationMinutes: 15 },
    ],
    checkpoints: [],
  };
  runs.unshift(run);
  return run;
}

export function getTrend(): MetricPoint[] {
  return trend;
}
