import type { TestRun, TestRunDetails } from '../types/dto.js';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import { OrchestratorError } from '../utils/errors.js';
import { mapRun, mapRunDetails } from '../utils/mappers.js';

const normalizeNotFound = (err: OrchestratorError, runId: string): never => {
  if (err.statusCode === 404 || /not\s+found/i.test(err.message)) {
    throw new OrchestratorError(`Test run ${runId} not found`, {
      statusCode: 404,
      code: 'RUN_NOT_FOUND',
      details: err.details,
    });
  }

  throw err;
};

export async function getTestRuns(limit: number, client: OrchestratorClient): Promise<TestRun[]> {
  try {
    const runs = await client.fetchRuns(limit);
    return runs.map(mapRun);
  } catch (err) {
    if (err instanceof OrchestratorError) {
      throw err;
    }
    throw new OrchestratorError('Failed to fetch test runs', { cause: err });
  }
}

export async function getTestRun(runId: string, client: OrchestratorClient): Promise<TestRunDetails> {
  try {
    const run = await client.fetchRun(runId);
    return mapRunDetails(run);
  } catch (err) {
    if (err instanceof OrchestratorError) {
      normalizeNotFound(err, runId);
      throw err;
    }
    throw new OrchestratorError(`Failed to fetch test run ${runId}`, { cause: err });
  }
}
