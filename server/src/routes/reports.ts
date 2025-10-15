import type { Router } from 'express';
import { getReport, listReports } from '../data/reports.js';

export function registerReportRoutes(router: Router): void {
  router.get('/reports', (_req, res) => {
    res.json(listReports());
  });

  router.get('/reports/:id', (req, res) => {
    const report = getReport(req.params.id);
    if (!report) {
      res.status(404).json({ message: 'Report not found' });
      return;
    }
    res.json(report);
  });
}
