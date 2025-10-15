import type { Router } from 'express';
import { createTestPlan, getTestPlan, listTestPlans, updateTestPlanStatus } from '../data/testPlans.js';

export function registerTestPlanRoutes(router: Router): void {
  router.get('/test-plans', (_req, res) => {
    res.json(listTestPlans());
  });

  router.get('/test-plans/:id', (req, res) => {
    const plan = getTestPlan(req.params.id);
    if (!plan) {
      res.status(404).json({ message: 'Test plan not found' });
      return;
    }
    res.json(plan);
  });

  router.post('/test-plans', (req, res) => {
    const plan = createTestPlan(req.body);
    res.status(201).json(plan);
  });

  router.patch('/test-plans/:id/status', (req, res) => {
    const plan = updateTestPlanStatus(req.params.id, req.body.status);
    if (!plan) {
      res.status(404).json({ message: 'Test plan not found' });
      return;
    }
    res.json(plan);
  });
}
