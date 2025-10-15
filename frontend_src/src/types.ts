export type LoadShape = 'spike' | 'stress' | 'soak' | 'baseline' | 'custom';

export interface ScenarioTarget {
  id: string;
  name: string;
  region: string;
  protocol: 'http' | 'https' | 'grpc' | 'websocket';
  baselineRps: number;
  maxRps: number;
  latencySloMs: number;
}

export interface TestPlan {
  id: string;
  name: string;
  status: 'draft' | 'scheduled' | 'ready';
  description: string;
  owner: string;
  team: string;
  lastEdited: string;
  tags: string[];
  loadShape: LoadShape;
  durationMinutes: number;
  targets: ScenarioTarget[];
  entryCriteria: string[];
  exitCriteria: string[];
}

export interface TestRunPhase {
  phase: 'warmup' | 'ramp' | 'peak' | 'sustain' | 'cooldown';
  targetRps: number;
  durationMinutes: number;
}

export interface TimelineCheckpoint {
  label: string;
  timestamp: string;
  note?: string;
}

export interface TestRun {
  id: string;
  name: string;
  planId: string;
  status: 'preparing' | 'running' | 'pausing' | 'completed' | 'failed';
  owner: string;
  environment: 'staging' | 'production' | 'qa';
  startTime: string;
  estimatedEndTime: string;
  lastUpdated: string;
  progress: number;
  concurrency: number;
  peakRps: number;
  errorBudgetConsumed: number;
  blockers: string[];
  phases: TestRunPhase[];
  checkpoints: TimelineCheckpoint[];
}

export interface MetricPoint {
  timestamp: string;
  throughput: number;
  p95Latency: number;
  errorRate: number;
  saturation: number;
}

export interface DashboardOverview {
  updatedAt: string;
  readinessScore: number;
  readinessSummary: string;
  keyAlerts: string[];
  activeRuns: TestRun[];
  upcomingMilestones: TimelineCheckpoint[];
  metrics: {
    avgLatency: number;
    p95Latency: number;
    errorRate: number;
    throughput: number;
    concurrency: number;
  };
  trend: MetricPoint[];
}

export interface TestReport {
  id: string;
  runId: string;
  planId: string;
  title: string;
  generatedAt: string;
  author: string;
  status: 'draft' | 'published';
  format: 'pdf' | 'html';
  sections: {
    title: string;
    summary: string;
    insights: string[];
  }[];
  metrics: {
    peakThroughput: number;
    avgLatency: number;
    errorRate: number;
    availability: number;
  };
}
