import type { TestPlan } from '../types/dto.js';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import { OrchestratorError } from '../utils/errors.js';
import { mapTestPlan } from '../utils/mappers.js';

export async function getTestPlans(limit: number, client: OrchestratorClient): Promise<TestPlan[]> {
  try {
    const plans = await client.fetchTestPlans(limit);
    return plans.map(mapTestPlan);
  } catch (err) {
    if (err instanceof OrchestratorError) {
      throw err;
    }
    throw new OrchestratorError('Failed to fetch test plans', { cause: err });
  }
}
