import { Router } from 'express';
import { fetchTestPlan, fetchTestPlans } from '../data/testPlans.js';
import type { ApiResponseDTO, TestPlanDTO } from '../types/dto.js';
import { OrchestrationError } from '../utils/errors.js';

const router = Router();

router.get('/', async (_req, res, next) => {
  try {
    const plans = await fetchTestPlans();
    const response: ApiResponseDTO<TestPlanDTO[]> = {
      status: 'success',
      data: plans,
    };
    res.json(response);
  } catch (error) {
    if (error instanceof OrchestrationError) {
      res.status(error.statusCode).json({
        status: 'error',
        message: error.message,
        details: error.details,
      });
      return;
    }
    next(error);
  }
});

router.get('/:planId', async (req, res, next) => {
  try {
    const plan = await fetchTestPlan(req.params.planId);
    const response: ApiResponseDTO<TestPlanDTO> = {
      status: 'success',
      data: plan,
    };
    res.json(response);
  } catch (error) {
    if (error instanceof OrchestrationError) {
      res.status(error.statusCode).json({
        status: 'error',
        message: error.message,
        details: error.details,
      });
      return;
    }
    next(error);
  }
});

export default router;
