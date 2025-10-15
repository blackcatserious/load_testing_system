import { Router } from 'express';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import type { ApiResponse, ReportSummary } from '../types/dto.js';
import { getReports } from '../data/reports.js';

export function createReportsRouter(client: OrchestratorClient): Router {
  const router = Router();

  router.get('/', async (_req, res, next) => {
    try {
      const reports = await getReports(client);
      const response: ApiResponse<ReportSummary[]> = { status: 'ok', data: reports };
      res.json(response);
    } catch (err) {
      next(err);
    }
  });

  return router;
}
