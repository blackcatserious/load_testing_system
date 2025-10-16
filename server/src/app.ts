import express, { type Express, type NextFunction, type Request, type Response } from 'express';
import type { Router as ExpressRouter } from 'express';
import { defaultOrchestratorClient, type OrchestratorClient } from './services/orchestratorClient.js';
import { createDashboardRouter } from './routes/dashboard.js';
import { createTestRunsRouter } from './routes/testRuns.js';
import { createTestPlansRouter } from './routes/testPlans.js';
import { createReportsRouter } from './routes/reports.js';
import { createLegacyRouter } from './routes/legacy.js';
import { normalizeError } from './utils/errors.js';
import type { ApiErrorResponse } from './types/dto.js';

export function createApp(client: OrchestratorClient = defaultOrchestratorClient): Express {
  const app = express();

  app.use(express.json());

  const mountRouter = (paths: string[], routerFactory: () => ExpressRouter) => {
    const router = routerFactory();
    paths.forEach((path) => app.use(path, router));
  };

  mountRouter(['/dashboard', '/api/dashboard'], () => createDashboardRouter(client));
  mountRouter(['/test-runs', '/api/test-runs'], () => createTestRunsRouter(client));
  mountRouter(['/test-plans', '/api/test-plans'], () => createTestPlansRouter(client));
  mountRouter(['/reports', '/api/reports'], () => createReportsRouter(client));

  const legacyRouter = createLegacyRouter(client);
  app.use('/', legacyRouter);
  app.use('/api', legacyRouter);

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
