import { Router } from 'express';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import { getDashboardMetrics } from '../data/dashboard.js';
import { getTestRuns, getTestRun } from '../data/testRuns.js';
import { getTestPlans } from '../data/testPlans.js';
import { getReports } from '../data/reports.js';
import { normalizeError } from '../utils/errors.js';
import type {
  DashboardMetrics,
  ReportSummary,
  TestPlan,
  TestRun,
  TestRunDetails,
} from '../types/dto.js';

const toLegacyMetrics = (metrics: DashboardMetrics) => {
  const totalRequests = metrics.total_requests ?? 0;
  const successRate = metrics.success_rate ?? 0;
  const errors = metrics.errors ?? 0;
  const errorRatePercent = totalRequests > 0 ? (errors / totalRequests) * 100 : 0;
  const successCount = Math.round(totalRequests * successRate);

  return {
    status: metrics.status,
    success: true,
    timestamp: metrics.timestamp,
    metrics: {
      requests_per_second: metrics.rps,
      total_requests: metrics.total_requests,
      success_rate: metrics.success_rate,
      avg_latency_ms: metrics.avg_response_time,
      active_threads: metrics.threads,
      threads_used: metrics.threads,
      error_rate_percent: Number.isFinite(errorRatePercent) ? errorRatePercent : 0,
      success_count: successCount,
      failure_count: errors,
      active_connections: metrics.active_connections,
    },
    status_codes: metrics.status_codes,
    proxy_stats: metrics.proxy_stats,
    fingerprint_stats: metrics.fingerprint_stats,
    stealth_stats: metrics.stealth_stats,
    escalation: metrics.escalation,
    resistance: metrics.resistance,
    target_metrics: metrics.target_metrics,
  };
};

const wrapSuccess = <T>(data: T) => ({
  status: 'success' as const,
  data,
});

const handleLegacyError = (res: any, err: unknown) => {
  const normalized = normalizeError(err);
  res
    .status(normalized.statusCode)
    .json({ status: 'error', message: normalized.message, code: normalized.code, details: normalized.details });
};

const toLegacyRunsResponse = (runs: TestRun[]) => wrapSuccess({ runs });

const toLegacyRunResponse = (run: TestRunDetails) => wrapSuccess(run);

const toLegacyPlansResponse = (plans: TestPlan[]) => wrapSuccess({ groups: plans });

const toLegacyReportsResponse = (reports: ReportSummary[]) => wrapSuccess({ reports });

export function createLegacyRouter(client: OrchestratorClient): Router {
  const router = Router();

  router.get('/metrics_endpoint.php', async (req, res) => {
    try {
      const includeAntiDetect = req.query.include_anti_detect !== '0' && req.query.include_anti_detect !== 'false';
      const metrics = await getDashboardMetrics(client, includeAntiDetect);
      res.json(toLegacyMetrics(metrics));
    } catch (err) {
      handleLegacyError(res, err);
    }
  });

  router.get('/runs_endpoint.php', async (req, res) => {
    try {
      const runId = typeof req.query.run_id === 'string' ? req.query.run_id : undefined;
      if (runId) {
        const run = await getTestRun(runId, client);
        res.json(toLegacyRunResponse(run));
        return;
      }

      const limitParam = typeof req.query.limit === 'string' ? Number(req.query.limit) : undefined;
      const limit = Number.isFinite(limitParam) && (limitParam ?? 0) > 0 ? Number(limitParam) : 50;
      const runs = await getTestRuns(limit, client);
      res.json(toLegacyRunsResponse(runs));
    } catch (err) {
      handleLegacyError(res, err);
    }
  });

  router.get('/group_runs_endpoint.php', async (req, res) => {
    const action = typeof req.query.action === 'string' ? req.query.action : 'list';
    if (action !== 'list') {
      res.status(400).json({ status: 'error', message: `Unsupported action: ${action}` });
      return;
    }

    try {
      const limitParam = typeof req.query.limit === 'string' ? Number(req.query.limit) : undefined;
      const limit = Number.isFinite(limitParam) && (limitParam ?? 0) > 0 ? Number(limitParam) : 50;
      const plans = await getTestPlans(limit, client);
      res.json(toLegacyPlansResponse(plans));
    } catch (err) {
      handleLegacyError(res, err);
    }
  });

  router.get('/reports_endpoint.php', async (req, res) => {
    const action = typeof req.query.action === 'string' ? req.query.action : 'list';

    if (action !== 'list') {
      res.status(501).json({ status: 'error', message: `Action ${action} is not supported by the API server` });
      return;
    }

    try {
      const reports = await getReports(client);
      res.json(toLegacyReportsResponse(reports));
    } catch (err) {
      handleLegacyError(res, err);
    }
  });

  return router;
}
