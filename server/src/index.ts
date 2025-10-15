import cors from 'cors';
import express from 'express';
import { registerDashboardRoutes } from './routes/dashboard.js';
import { registerTestPlanRoutes } from './routes/testPlans.js';
import { registerTestRunRoutes } from './routes/testRuns.js';
import { registerReportRoutes } from './routes/reports.js';

const app = express();
const port = Number(process.env.PORT ?? 4000);

app.use(cors());
app.use(express.json());

app.get('/api/health', (_req, res) => {
  res.json({ status: 'ok', message: 'PulseLoad control plane online' });
});

const apiRouter = express.Router();
registerDashboardRoutes(apiRouter);
registerTestPlanRoutes(apiRouter);
registerTestRunRoutes(apiRouter);
registerReportRoutes(apiRouter);

app.use('/api', apiRouter);

app.use((_req, res) => {
  res.status(404).json({ message: 'Route not found' });
});

app.use((err: unknown, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  console.error('Unexpected error', err);
  res.status(500).json({ message: 'Unexpected error occurred' });
});

app.listen(port, () => {
  console.log(`PulseLoad API listening on port ${port}`);
});
