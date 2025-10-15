import express from 'express';
import cors from 'cors';
import createError from 'http-errors';
import dashboardRouter from './routes/dashboard.js';
import testRunsRouter from './routes/testRuns.js';
import testPlansRouter from './routes/testPlans.js';
import reportsRouter from './routes/reports.js';
import type { ApiErrorDTO } from './types/dto.js';

const app = express();

app.use(cors());
app.use(express.json());

app.use('/dashboard', dashboardRouter);
app.use('/test-runs', testRunsRouter);
app.use('/test-plans', testPlansRouter);
app.use('/reports', reportsRouter);

app.use((req, res, next) => {
  next(createError(404, `Route ${req.originalUrl} not found`));
});

app.use((err: any, req: express.Request, res: express.Response, _next: express.NextFunction) => {
  const status = err.status || 500;
  const response: ApiErrorDTO = {
    status: 'error',
    message: err.expose ? err.message : 'Unexpected server error',
    details: err.details ?? (err.expose ? undefined : err.message),
  };

  res.status(status).json(response);
});

if (process.env.NODE_ENV !== 'test') {
  const port = process.env.PORT ? Number(process.env.PORT) : 4000;
  app.listen(port, () => {
    console.log(`Server listening on port ${port}`);
  });
}

export default app;
