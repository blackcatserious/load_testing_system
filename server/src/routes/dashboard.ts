import { Router } from 'express';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import type { ApiResponse, DashboardMetrics } from '../types/dto.js';
import { getDashboardMetrics } from '../data/dashboard.js';

export function createDashboardRouter(client: OrchestratorClient): Router {
  const router = Router();

  router.get('/', async (req, res, next) => {
    try {
      const includeAntiDetect = req.query.includeAntiDetect !== 'false';
      const metrics = await getDashboardMetrics(client, includeAntiDetect);
      const response: ApiResponse<DashboardMetrics> = { status: 'ok', data: metrics };
      res.json(response);
    } catch (err) {
      next(err);
    }
  });

  return router;
}
