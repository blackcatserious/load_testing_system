import dayjs from 'dayjs';
import { nanoid } from 'nanoid';
import type { CreatePlanPayload, TestPlan } from '../types/index.js';

const plans: TestPlan[] = [
  {
    id: 'plan-aurora',
    name: 'Aurora Checkout Resilience',
    status: 'ready',
    description: 'Baseline checkout funnel performance with multi-region failover.',
    owner: 'Serena Vale',
    team: 'Ecommerce Core',
    lastEdited: dayjs().subtract(1, 'day').toISOString(),
    tags: ['checkout', 'resilience', 'critical'],
    loadShape: 'soak',
    durationMinutes: 180,
    targets: [
      {
        id: 'svc-checkout',
        name: 'Checkout API',
        protocol: 'https',
        region: 'us-east-1',
        baselineRps: 850,
        maxRps: 2400,
        latencySloMs: 320,
      },
      {
        id: 'svc-payments',
        name: 'Payments Gateway',
        protocol: 'https',
        region: 'eu-central-1',
        baselineRps: 420,
        maxRps: 1100,
        latencySloMs: 280,
      },
    ],
    entryCriteria: [
      'Synthetic monitoring stable <1% error rate for 24h',
      'Canary deploy green in staging',
    ],
    exitCriteria: [
      'p95 latency < 400ms for 95% of ramp',
      'No error budget alerts triggered',
    ],
  },
  {
    id: 'plan-sonar',
    name: 'Sonar Streaming Spike',
    status: 'scheduled',
    description: 'Ultra-high concurrency spike test against media ingestion APIs.',
    owner: 'Gabriel Ortiz',
    team: 'Streaming Platform',
    lastEdited: dayjs().subtract(2, 'hour').toISOString(),
    tags: ['streaming', 'spike', 'ingress'],
    loadShape: 'spike',
    durationMinutes: 45,
    targets: [
      {
        id: 'svc-ingest',
        name: 'Ingest Gateway',
        protocol: 'grpc',
        region: 'ap-southeast-1',
        baselineRps: 1200,
        maxRps: 6000,
        latencySloMs: 180,
      },
    ],
    entryCriteria: [
      'Synthetic streaming lab green',
      'ML anomaly detector disabled for window',
    ],
    exitCriteria: [
      'No more than 0.5% frame drops',
      'Downstream consumer queue < 65% saturation',
    ],
  },
  {
    id: 'plan-lumina',
    name: 'Lumina API Latency Hunt',
    status: 'draft',
    description: 'Latency regression deep dive across the search surface.',
    owner: 'Priya Banerjee',
    team: 'Search Experience',
    lastEdited: dayjs().subtract(4, 'day').toISOString(),
    tags: ['search', 'latency'],
    loadShape: 'custom',
    durationMinutes: 120,
    targets: [
      {
        id: 'svc-query',
        name: 'Query Service',
        protocol: 'https',
        region: 'us-west-2',
        baselineRps: 2000,
        maxRps: 5400,
        latencySloMs: 210,
      },
      {
        id: 'svc-ranking',
        name: 'Ranking Engine',
        protocol: 'https',
        region: 'us-west-2',
        baselineRps: 2000,
        maxRps: 5200,
        latencySloMs: 230,
      },
    ],
    entryCriteria: [
      'Feature flags locked',
      'Search relevancy guardrail stable',
    ],
    exitCriteria: [
      '95% queries < 220ms',
      'No new 5xx regressions',
    ],
  },
];

export function listTestPlans(): TestPlan[] {
  return plans;
}

export function getTestPlan(id: string): TestPlan | undefined {
  return plans.find((plan) => plan.id === id);
}

export function createTestPlan(payload: CreatePlanPayload): TestPlan {
  const now = dayjs().toISOString();
  const plan: TestPlan = {
    id: `plan-${nanoid(6)}`,
    status: 'draft',
    lastEdited: now,
    tags: [],
    ...payload,
  };
  plans.unshift(plan);
  return plan;
}

export function updateTestPlanStatus(id: string, status: TestPlan['status']): TestPlan | undefined {
  const plan = getTestPlan(id);
  if (!plan) return undefined;
  plan.status = status;
  plan.lastEdited = dayjs().toISOString();
  return plan;
}
