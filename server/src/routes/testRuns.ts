import { Router } from 'express';
import { fetchTestRun, fetchTestRuns } from '../data/testRuns.js';
import type { ApiResponseDTO, TestRunDetailDTO, TestRunSummaryDTO } from '../types/dto.js';
import { OrchestrationError } from '../utils/errors.js';

const router = Router();

router.get('/', async (req, res, next) => {
  try {
    const limit = req.query.limit ? Number(req.query.limit) : 50;
    const runs = await fetchTestRuns(limit);
    const response: ApiResponseDTO<TestRunSummaryDTO[]> = {
      status: 'success',
      data: runs,
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

router.get('/:runId', async (req, res, next) => {
  try {
    const run = await fetchTestRun(req.params.runId);
    const response: ApiResponseDTO<TestRunDetailDTO> = {
      status: 'success',
      data: run,
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
