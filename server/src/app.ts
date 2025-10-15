import express, { type Express, type NextFunction, type Request, type Response } from 'express';
import { defaultOrchestratorClient, type OrchestratorClient } from './services/orchestratorClient.js';
import { createDashboardRouter } from './routes/dashboard.js';
import { createTestRunsRouter } from './routes/testRuns.js';
import { createTestPlansRouter } from './routes/testPlans.js';
import { createReportsRouter } from './routes/reports.js';
import { normalizeError } from './utils/errors.js';
import type { ApiErrorResponse } from './types/dto.js';

export function createApp(client: OrchestratorClient = defaultOrchestratorClient): Express {
  const app = express();

  app.use(express.json());

  app.use('/dashboard', createDashboardRouter(client));
  app.use('/test-runs', createTestRunsRouter(client));
  app.use('/test-plans', createTestPlansRouter(client));
  app.use('/reports', createReportsRouter(client));

  app.use((err: unknown, _req: Request, res: Response, _next: NextFunction) => {
    const normalized = normalizeError(err);
    const response: ApiErrorResponse = {
      status: 'error',
      error: {
        message: normalized.message,
        code: normalized.code,
        details: normalized.details,
      },
    };
    res.status(normalized.statusCode).json(response);
  });

  return app;
}
