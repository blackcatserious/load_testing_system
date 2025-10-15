import { Router } from 'express';
import { fetchDashboardMetrics } from '../data/dashboard.js';
import type { ApiResponseDTO, DashboardMetricsDTO } from '../types/dto.js';
import { OrchestrationError } from '../utils/errors.js';

const router = Router();

router.get('/', async (_req, res, next) => {
  try {
    const metrics = await fetchDashboardMetrics();
    const response: ApiResponseDTO<DashboardMetricsDTO> = {
      status: 'success',
      data: metrics,
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
