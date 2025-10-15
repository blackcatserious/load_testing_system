import type { Router } from 'express';
import { getDashboardOverview } from '../data/dashboard.js';

export function registerDashboardRoutes(router: Router): void {
  router.get('/dashboard/overview', (_req, res) => {
    res.json(getDashboardOverview());
  });
}
