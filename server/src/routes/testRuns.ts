import type { Router } from 'express';
import { createTestRun, getTestRun, listTestRuns } from '../data/testRuns.js';

export function registerTestRunRoutes(router: Router): void {
  router.get('/runs', (_req, res) => {
    res.json(listTestRuns());
  });

  router.get('/runs/:id', (req, res) => {
    const run = getTestRun(req.params.id);
    if (!run) {
      res.status(404).json({ message: 'Test run not found' });
      return;
    }
    res.json(run);
  });

  router.post('/runs', (req, res) => {
    const run = createTestRun(req.body);
    res.status(201).json(run);
  });
}
