import { Router } from 'express';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import type { ApiResponse, TestRun, TestRunDetails } from '../types/dto.js';
import { getTestRun, getTestRuns } from '../data/testRuns.js';

export function createTestRunsRouter(client: OrchestratorClient): Router {
  const router = Router();

  router.get('/', async (req, res, next) => {
    try {
      const limit = req.query.limit ? Number(req.query.limit) : 50;
      const runs = await getTestRuns(Number.isFinite(limit) && limit > 0 ? limit : 50, client);
      const response: ApiResponse<TestRun[]> = { status: 'ok', data: runs };
      res.json(response);
    } catch (err) {
      next(err);
    }
  });

  router.get('/:runId', async (req, res, next) => {
    try {
      const run = await getTestRun(req.params.runId, client);
      const response: ApiResponse<TestRunDetails> = { status: 'ok', data: run };
      res.json(response);
    } catch (err) {
      next(err);
    }
  });

  return router;
}
