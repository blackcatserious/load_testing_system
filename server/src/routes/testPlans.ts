import { Router } from 'express';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import type { ApiResponse, TestPlan } from '../types/dto.js';
import { getTestPlans } from '../data/testPlans.js';

export function createTestPlansRouter(client: OrchestratorClient): Router {
  const router = Router();

  router.get('/', async (req, res, next) => {
    try {
      const limit = req.query.limit ? Number(req.query.limit) : 50;
      const plans = await getTestPlans(Number.isFinite(limit) && limit > 0 ? limit : 50, client);
      const response: ApiResponse<TestPlan[]> = { status: 'ok', data: plans };
      res.json(response);
    } catch (err) {
      next(err);
    }
  });

  return router;
}
