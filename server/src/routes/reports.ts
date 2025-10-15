import { Router } from 'express';
import { fetchReports } from '../data/reports.js';
import type { ApiResponseDTO, ReportDTO } from '../types/dto.js';
import { OrchestrationError } from '../utils/errors.js';

const router = Router();

router.get('/', async (_req, res, next) => {
  try {
    const reports = await fetchReports();
    const response: ApiResponseDTO<ReportDTO[]> = {
      status: 'success',
      data: reports,
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
